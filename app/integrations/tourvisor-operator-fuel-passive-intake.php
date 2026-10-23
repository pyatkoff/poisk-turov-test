<?php
declare(strict_types=1);

require_once __DIR__ . '/tourvisor-operator-fuel-retained-intake.php';
require_once __DIR__ . '/operator-fuel-rule-store.php';

/**
 * Response-transparent bridge for the already executed Search3 tour -> flights flow.
 *
 * It performs no supplier I/O and no database writes. The existing Tourvisor offer
 * store supplies the exact party (including child ages); the existing private
 * `searches` directory holds both the short-lived pair and operator-fuel evidence.
 */
final class AnyTourTourvisorOperatorFuelPassiveIntakeV1
{
    private const PENDING_PREFIX = 'tourvisor-fuel-pending-v1-';
    private const PENDING_TTL = 900;
    private const EVIDENCE_TTL = 86400;
    private const MAX_PENDING_BYTES = 262144;
    private const DIAGNOSTIC_PREFIX = 'tourvisor-fuel-intake-receipt-v1-';
    private const DIAGNOSTIC_CODES = [
        'TV_PASSIVE_TOUR_ID', 'TV_PASSIVE_OPERATOR', 'TV_PASSIVE_OPERATOR_CONFLICT',
        'TV_PASSIVE_DATE', 'TV_PASSIVE_DATE_CONFLICT', 'TV_PASSIVE_PARTY_CONFLICT',
        'TV_PASSIVE_LOCK', 'TV_PASSIVE_PAYLOAD_INTEGRITY', 'TV_PASSIVE_CONTEXT_INTEGRITY',
        'TV_PASSIVE_PARTY', 'TV_PASSIVE_CONTEXT_CONFLICT', 'TV_PASSIVE_PENDING_INVALID',
        'TV_PASSIVE_FLIGHTS', 'TV_PASSIVE_MARKET', 'TV_PASSIVE_DIRECTORY', 'TV_PASSIVE_WRITE',
        'TV_FUEL_TOUR', 'TV_FUEL_FLIGHTS', 'TV_FUEL_TOUR_ID', 'TV_FUEL_OPERATOR',
        'TV_FUEL_PARTY', 'TV_FUEL_RESPONSE_ERROR', 'TV_FUEL_COMBINATIONS', 'TV_FUEL_DEFAULT',
        'TV_FUEL_FORWARD', 'TV_FUEL_BACKWARD', 'TV_FUEL_DATE', 'TV_FUEL_AMOUNT',
        'TV_FUEL_AMOUNT_FLIGHT_MISSING', 'TV_FUEL_AMOUNT_FLIGHT_SHAPE',
        'TV_FUEL_AMOUNT_VALUE_MISSING', 'TV_FUEL_AMOUNT_VALUE_FORMAT',
        'TV_FUEL_AMOUNT_NONPOSITIVE', 'TV_FUEL_AMOUNT_TOUR_VALUE',
        'TV_FUEL_AMOUNT_CONFLICT', 'TV_FUEL_CURRENCY_CONFLICT', 'TV_FUEL_INFO', 'TV_FUEL_FLAGS',
        'TV_FUEL_REQUIRED_CHARGES', 'TV_FUEL_SOURCE_DIGEST', 'TV_FUEL_TIME', 'TV_FUEL_MARKET',
        'TV_FUEL_PERIOD', 'TV_FUEL_LEG', 'TV_FUEL_PORT', 'TV_FUEL_CARRIER', 'TV_FUEL_FLIGHT',
        'TV_FUEL_CURRENCY', 'OPERATOR_FUEL_STORE_CONFLICT', 'OPERATOR_FUEL_STORE_SIZE',
        'OPERATOR_FUEL_STORE_WRITE', 'OPERATOR_FUEL_STORE_READBACK', 'OPERATOR_FUEL_STORE_INVALID',
        'OPERATOR_FUEL_STORE_ROOT', 'OPERATOR_FUEL_STORE_DIGEST',
    ];

    public static function captureTour(
        string $tourId,
        array $tour,
        PDO $db,
        string $directory,
        DateTimeImmutable $now
    ): array {
        return self::withDiagnostic($tourId, 'tour', $tour, $directory, $now,
            static fn(): array => self::retainTour($tourId, $tour, $db, $directory, $now));
    }

    private static function retainTour(
        string $tourId,
        array $tour,
        PDO $db,
        string $directory,
        DateTimeImmutable $now
    ): array {
        self::directory($directory);
        $tourId = self::tourId($tourId);
        if (self::tourId($tour['id'] ?? null) !== $tourId) {
            throw new InvalidArgumentException('TV_PASSIVE_TOUR_ID');
        }

        $operator = self::operatorLabel($tour['operator'] ?? null);
        if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) === null) {
            return self::receipt(false, 'operator_not_target');
        }

        $context = self::offerContext($db, $tourId, $now, $directory, $tour);
        if ($context === null) return self::receipt(false, 'offer_context_missing');
        if ($context['operator_family'] !== AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator)) {
            throw new DomainException('TV_PASSIVE_OPERATOR_CONFLICT');
        }

        $checkin = AnyTourTourvisorOperatorFuelRetainedIntakeV1::supplierDate($tour['date'] ?? null, 'TV_PASSIVE_DATE');
        if ($checkin !== $context['checkin']) throw new DomainException('TV_PASSIVE_DATE_CONFLICT');
        if (($tour['adults'] ?? null) !== $context['party']['adults']
            || ($tour['childs'] ?? null) !== $context['party']['children']) {
            throw new DomainException('TV_PASSIVE_PARTY_CONFLICT');
        }

        $observedAt = $now->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
        $periodStart = substr($checkin, 0, 8) . '01';
        $periodEnd = (new DateTimeImmutable($periodStart, new DateTimeZone('UTC')))
            ->modify('last day of this month')->format('Y-m-d');
        $pending = [
            'version' => 1,
            'tour_id' => $tourId,
            'tour' => $tour,
            'party' => $context['party'],
            'market' => self::market($tour['departure'] ?? null),
            'observed_at' => $observedAt,
            'pending_expires_at' => $observedAt + self::PENDING_TTL,
            'evidence_expires_at' => $observedAt + self::EVIDENCE_TTL,
            'valid_from' => $periodStart,
            'valid_to' => $periodEnd,
            'tour_response_sha256' => AnyTourOperatorFuelRuleEvidenceV1::hash($tour),
        ];
        self::atomicWrite(self::pendingPath($directory, $tourId), $pending, self::MAX_PENDING_BYTES);
        return self::receipt(true, null, ['status'=>'tour_retained','tourId'=>$tourId]);
    }

    public static function captureFlights(
        string $tourId,
        array $flights,
        string $directory,
        DateTimeImmutable $now,
        ?callable $emitHeader = null
    ): array {
        $header = null;
        $observe = static function(array $rows) use (&$header, $now): void {
            $header = AnyTourOperatorFuelRuleStoreV1::monitoringHeader($rows, $now->getTimestamp());
        };
        $receipt = self::withDiagnostic($tourId, 'flights', $flights, $directory, $now,
            static fn(): array => self::retainFlights($tourId, $flights, $directory, $now, $observe));
        // Emit only after successful capture, diagnostic and lock release. Never touch
        // the supplier body/status, and never add fields to the private receipt schema.
        if (($receipt['ok'] ?? false) === true && $header !== null) {
            try {
                $line = 'X-AnyTour-Fuel-Monitoring: ' . $header;
                if ($emitHeader !== null) $emitHeader($line);
                elseif (PHP_SAPI !== 'cli' && !headers_sent()) header($line, true);
            } catch (Throwable $ignored) {}
        }
        return $receipt;
    }

    private static function retainFlights(
        string $tourId,
        array $flights,
        string $directory,
        DateTimeImmutable $now,
        callable $observe
    ): array {
        self::directory($directory);
        $tourId = self::tourId($tourId);
        $lockPath = rtrim($directory, '/') . '/tourvisor-fuel-passive-v1.lock';
        if (is_link($lockPath)) throw new RuntimeException('TV_PASSIVE_LOCK');
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('TV_PASSIVE_LOCK');
        try {
            $path = self::pendingPath($directory, $tourId);
            $pending = self::readPending($path);
            if ($pending === null) return self::receipt(false, 'tour_pair_missing');
            $nowTs = $now->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
            if (($pending['pending_expires_at'] ?? 0) <= $nowTs) {
                @unlink($path);
                return self::receipt(false, 'tour_pair_expired');
            }

            $retained = [
                'tour_id' => $tourId,
                'tour' => $pending['tour'],
                'flights' => $flights,
                'party' => $pending['party'],
                'market' => $pending['market'],
                'observed_at' => $pending['observed_at'],
                'expires_at' => $pending['evidence_expires_at'],
                'valid_from' => $pending['valid_from'],
                'valid_to' => $pending['valid_to'],
                'tour_response_sha256' => $pending['tour_response_sha256'],
                'flights_response_sha256' => AnyTourOperatorFuelRuleEvidenceV1::hash($flights),
            ];
            $observation = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($retained);
            $append = AnyTourOperatorFuelRuleStoreV1::append(
                $directory,
                $observation,
                static fn(string $target, array $value): bool => self::atomicWrite($target, $value, 131072),
                $observe
            );
            $default = self::defaultFlight($flights);
            $confirmed = AnyTourOperatorFuelRuleStoreV1::inputForTarget($directory, [
                'provider' => 'tourvisor',
                'operator' => $observation['operator_raw'],
                'scope' => $observation['scope'],
                'offer_ref_digest' => $observation['offer_ref_digest'],
                'flight_dates' => [
                    AnyTourTourvisorOperatorFuelRetainedIntakeV1::supplierDate($default['dateForward'], 'TV_FUEL_DATE'),
                    AnyTourTourvisorOperatorFuelRetainedIntakeV1::supplierDate($default['dateBackward'], 'TV_FUEL_DATE'),
                ],
            ], $nowTs) !== null;
            @unlink($path);
            return self::receipt(true, null, [
                'status' => $append['status'],
                'tourId' => $tourId,
                'observationCount' => $append['observationCount'],
                'ruleConfirmed' => $confirmed,
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Last per-stage snapshot, not a rule, request ledger or replay authorization. */
    private static function withDiagnostic(
        string $tourId,
        string $stage,
        array $response,
        string $directory,
        DateTimeImmutable $now,
        callable $capture
    ): array {
        $started = hrtime(true);
        $result = null;
        $error = null;
        try {
            return $result = $capture();
        } catch (Throwable $failed) {
            $error = $failed;
            throw $failed;
        } finally {
            // Best effort only: never replace the original capture result/exception.
            try {
                self::directory($directory);
                $id = self::tourId($tourId);
                $code = $error === null ? ($result['status'] ?? $result['reason'] ?? null) : $error->getMessage();
                if ($stage === 'flights' && $error !== null) {
                    $code = AnyTourTourvisorOperatorFuelRetainedIntakeV1::amountDiagnostic($error) ?? $code;
                }
                $allowed = $error === null
                    ? ['tour_retained', 'created', 'appended', 'unchanged', 'operator_not_target',
                       'offer_context_missing', 'tour_pair_missing', 'tour_pair_expired']
                    : self::DIAGNOSTIC_CODES;
                $observed = $now->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
                $snapshot = [
                    'schema_version'=>1,
                    'source'=>'tourvisor-operator-fuel-passive-intake-v1',
                    'stage'=>$stage,
                    'outcome'=>$error !== null ? 'rejected' : (($result['ok'] ?? false) === true ? 'retained' : 'skipped'),
                    'code'=>in_array($code, $allowed, true) ? $code : 'unclassified_error',
                    'observed_at'=>$observed,
                    'expires_at'=>$observed + self::EVIDENCE_TTL,
                    // Local processing only; never supplier transport latency.
                    'elapsed_ms'=>max(0, (int)ceil((hrtime(true) - $started) / 1000000)),
                    'offer_ref_digest'=>hash('sha256', 'tourvisor:tour:' . $id),
                    'normalized_response_sha256'=>AnyTourOperatorFuelRuleEvidenceV1::hash($response),
                    'observation_count'=>is_int($result['observationCount'] ?? null) ? $result['observationCount'] : null,
                    'rule_confirmed'=>is_bool($result['ruleConfirmed'] ?? null) ? $result['ruleConfirmed'] : null,
                ];
                $path = rtrim($directory, '/') . '/' . self::DIAGNOSTIC_PREFIX . $stage . '-' . hash('sha256', $id) . '.json';
                self::atomicWrite($path, $snapshot, 2048);
            } catch (Throwable $ignored) {
            }
        }
    }

    /** Retain identity/party only; this never makes a listing price ready. */
    public static function captureSearchContexts(array $entries, string $directory, DateTimeImmutable $now): array
    {
        self::directory($directory);
        if (!array_is_list($entries) || count($entries) > 5000) {
            throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
        }
        $at = $now->getTimestamp();
        $records = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !array_key_exists('anytour_hotel_id', $entry)) {
                throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
            }
            if ($entry['anytour_hotel_id'] === null) continue;
            if (!is_int($entry['anytour_hotel_id']) || $entry['anytour_hotel_id'] < 1) {
                throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
            }
            $offer = $entry['offer'] ?? null;
            if (!is_array($offer) || ($offer['provider'] ?? null) !== 'tourvisor'
                || ($offer['observed_at'] ?? null) !== gmdate('Y-m-d\\TH:i:s\\Z', $at)) {
                throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
            }
            $family = AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($offer['operator']['raw'] ?? null);
            if ($family === null) continue;
            $identity = $offer['identity'] ?? null;
            if (!is_array($identity) || !self::contextKeys($identity,
                ['search_ref_digest','offer_ref_digest','provider_hotel_ref_digest'])) {
                throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
            }
            foreach ($identity as $digest) {
                if (!is_string($digest) || preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
                    throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
                }
            }
            $context = self::searchContext([
                'operator_family'=>$family,'checkin'=>$offer['checkin'] ?? null,
                'party'=>$offer['party'] ?? null,
            ]);
            $records[] = [
                'offer_ref_digest'=>$identity['offer_ref_digest'],
                'context'=>$context,'search_ref_digest'=>$identity['search_ref_digest'],
                'provider_hotel_ref_digest'=>$identity['provider_hotel_ref_digest'],
                'observed_at'=>$at,'expires_at'=>$at+self::PENDING_TTL,
            ];
        }
        if ($records === []) return self::receipt(true, null, ['status'=>'search_contexts_retained','contextCount'=>0]);
        $lockPath = rtrim($directory, '/') . '/tourvisor-fuel-passive-v1.lock';
        if (is_link($lockPath)) throw new RuntimeException('TV_PASSIVE_LOCK');
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('TV_PASSIVE_LOCK');
        try {
            foreach ($records as $record) {
                $digest = $record['offer_ref_digest'];
                $cohort = [];
                foreach (self::searchContexts($directory, $digest, $at) as $prior) {
                    $cohort[self::searchContextKey($prior)] = $prior;
                }
                $cohort[self::searchContextKey($record)] = $record;
                if (count($cohort) > 8) throw new DomainException('TV_PASSIVE_CONTEXT_CONFLICT');
                self::atomicWrite(self::searchContextPath($directory, $digest), [
                    'version'=>1,'offer_ref_digest'=>$digest,'contexts'=>array_values($cohort),
                ], 16384);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return self::receipt(true, null, ['status'=>'search_contexts_retained','contextCount'=>count($records)]);
    }

    private static function contextKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected) && array_diff($expected, array_keys($value)) === [];
    }

    private static function searchContext(mixed $value): array
    {
        if (!is_array($value) || !self::contextKeys($value, ['operator_family','checkin','party'])
            || !in_array($value['operator_family'] ?? null, ['intourist','fun_and_sun','biblio_globus'], true)) {
            throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
        }
        $party = $value['party'];
        if (!is_array($party) || !self::contextKeys($party, ['adults','children','child_ages'])
            || !is_int($party['adults']) || !is_int($party['children'])
            || !is_array($party['child_ages']) || !array_is_list($party['child_ages'])
            || count($party['child_ages']) !== $party['children']) {
            throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
        }
        self::smallInt($party['adults'], 1, 9, 'TV_PASSIVE_CONTEXT_INTEGRITY');
        self::smallInt($party['children'], 0, 9, 'TV_PASSIVE_CONTEXT_INTEGRITY');
        foreach ($party['child_ages'] as $age) {
            if (!is_int($age)) throw new InvalidArgumentException('TV_PASSIVE_CONTEXT_INTEGRITY');
            self::smallInt($age, 0, 17, 'TV_PASSIVE_CONTEXT_INTEGRITY');
        }
        sort($party['child_ages'], SORT_NUMERIC);
        return ['operator_family'=>$value['operator_family'],
            'checkin'=>self::date($value['checkin'], 'TV_PASSIVE_CONTEXT_INTEGRITY'),'party'=>$party];
    }

    private static function searchContextKey(array $record): string
    {
        return AnyTourOperatorFuelRuleEvidenceV1::hash([
            $record['context'], $record['provider_hotel_ref_digest'],
        ]);
    }

    private static function searchContextPath(string $directory, string $digest): string
    {
        return rtrim($directory, '/') . '/tourvisor-fuel-search-context-v1-' . $digest . '.json';
    }

    private static function searchContexts(string $directory, string $digest, int $now): array
    {
        $path = self::searchContextPath($directory, $digest);
        if (is_link($path)) throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
        if (!file_exists($path)) return [];
        $before = lstat($path);
        if (!is_array($before) || ($before['mode'] & 0170000) !== 0100000
            || ($before['mode'] & 0777) !== 0600 || $before['nlink'] !== 1
            || $before['uid'] !== fileowner($directory) || $before['size'] < 2 || $before['size'] > 16384) {
            throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
        try {
            $opened = fstat($handle);
            $raw = stream_get_contents($handle, 16385);
            $after = fstat($handle);
        } finally {
            fclose($handle);
        }
        clearstatcache(true, $path);
        $entry = lstat($path);
        foreach (['dev','ino','mode','nlink','uid','size','mtime','ctime'] as $key) {
            if (($opened[$key] ?? null) !== $before[$key] || ($after[$key] ?? null) !== $before[$key]
                || ($entry[$key] ?? null) !== $before[$key]) throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
        }
        if (!is_string($raw) || strlen($raw) !== $before['size']) throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
        $value = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($value) || !self::contextKeys($value, ['version','offer_ref_digest','contexts'])
            || $value['version'] !== 1 || $value['offer_ref_digest'] !== $digest
            || !is_array($value['contexts']) || !array_is_list($value['contexts']) || count($value['contexts']) > 8) {
            throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
        }
        $result = [];
        foreach ($value['contexts'] as $record) {
            if (!is_array($record) || !self::contextKeys($record, ['offer_ref_digest','context',
                'search_ref_digest','provider_hotel_ref_digest','observed_at','expires_at'])
                || $record['offer_ref_digest'] !== $digest || !is_int($record['observed_at'])
                || !is_int($record['expires_at']) || $record['observed_at'] < 1 || $record['observed_at'] > $now
                || $record['expires_at'] !== $record['observed_at'] + self::PENDING_TTL) {
                throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
            }
            foreach (['search_ref_digest','provider_hotel_ref_digest'] as $key) {
                if (!is_string($record[$key]) || preg_match('/\A[a-f0-9]{64}\z/D', $record[$key]) !== 1) {
                    throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
                }
            }
            $record['context'] = self::searchContext($record['context']);
            if ($record['expires_at'] > $now) $result[] = $record;
        }
        return $result;
    }

    private static function offerContext(PDO $db, string $tourId, DateTimeImmutable $now, string $directory, array $tour): ?array
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $query = $db->prepare(
            "SELECT operator_json,checkin,adults,children,child_ages_json,payload_json,payload_sha256 "
            . "FROM anytour_offers WHERE provider='tourvisor' AND offer_ref_digest=:offer "
            . "AND is_active=1 AND expires_at>:now ORDER BY last_seen_at DESC,id DESC LIMIT 8"
        );
        $query->execute([
            'offer' => hash('sha256', 'tourvisor:tour:' . $tourId),
            'now' => $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
        $contexts = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payloadRaw = (string)($row['payload_json'] ?? '');
            if ($payloadRaw === '' || !hash_equals((string)($row['payload_sha256'] ?? ''), hash('sha256', $payloadRaw))) {
                throw new RuntimeException('TV_PASSIVE_PAYLOAD_INTEGRITY');
            }
            $operator = json_decode((string)($row['operator_json'] ?? ''), true, 16, JSON_THROW_ON_ERROR);
            $ages = json_decode((string)($row['child_ages_json'] ?? ''), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($operator) || !is_array($ages) || !array_is_list($ages)) {
                throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
            }
            $operatorRaw = $operator['raw'] ?? null;
            $family = AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operatorRaw);
            $adults = self::smallInt($row['adults'] ?? null, 1, 9, 'TV_PASSIVE_PARTY');
            $children = self::smallInt($row['children'] ?? null, 0, 9, 'TV_PASSIVE_PARTY');
            $ages = array_map(static fn(mixed $age): int => self::smallInt($age, 0, 17, 'TV_PASSIVE_PARTY'), $ages);
            sort($ages, SORT_NUMERIC);
            if ($family === null || count($ages) !== $children) throw new RuntimeException('TV_PASSIVE_CONTEXT_INTEGRITY');
            $context = [
                'operator_family' => $family,
                'checkin' => self::date($row['checkin'] ?? null, 'TV_PASSIVE_DATE'),
                'party' => ['adults'=>$adults,'children'=>$children,'child_ages'=>$ages],
            ];
            $contexts[AnyTourOperatorFuelRuleEvidenceV1::hash($context)] = $context;
        }
        foreach (self::searchContexts($directory, hash('sha256', 'tourvisor:tour:' . $tourId), $now->getTimestamp()) as $record) {
            $hotel = self::smallInt($tour['hotel']['id'] ?? null, 1, 999999999, 'TV_PASSIVE_CONTEXT_INTEGRITY');
            if (!hash_equals($record['provider_hotel_ref_digest'], hash('sha256', 'tourvisor:hotel:' . $hotel))) {
                throw new DomainException('TV_PASSIVE_CONTEXT_CONFLICT');
            }
            $context = $record['context'];
            $contexts[AnyTourOperatorFuelRuleEvidenceV1::hash($context)] = $context;
        }
        if ($contexts === []) return null;
        if (count($contexts) !== 1) throw new DomainException('TV_PASSIVE_CONTEXT_CONFLICT');
        return array_values($contexts)[0];
    }

    private static function readPending(string $path): ?array
    {
        if (is_link($path)) throw new RuntimeException('TV_PASSIVE_PENDING_INVALID');
        if (!file_exists($path)) return null;
        if (!is_file($path) || filesize($path) < 2 || filesize($path) > self::MAX_PENDING_BYTES) {
            throw new RuntimeException('TV_PASSIVE_PENDING_INVALID');
        }
        $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value) || ($value['version'] ?? null) !== 1) throw new RuntimeException('TV_PASSIVE_PENDING_INVALID');
        return $value;
    }

    private static function defaultFlight(array $response): array
    {
        $rows = $response['flights'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) throw new InvalidArgumentException('TV_PASSIVE_FLIGHTS');
        $defaults = array_values(array_filter($rows, static fn(mixed $row): bool => is_array($row) && ($row['isDefault'] ?? null) === true));
        if (count($defaults) !== 1) throw new InvalidArgumentException('TV_PASSIVE_FLIGHTS');
        return $defaults[0];
    }

    private static function market(mixed $departure): string
    {
        if (!is_array($departure) || array_is_list($departure)) throw new InvalidArgumentException('TV_PASSIVE_MARKET');
        $id = $departure['id'] ?? null;
        if ((is_int($id) && $id > 0) || (is_string($id) && preg_match('/\A[1-9][0-9]{0,12}\z/D', $id) === 1)) {
            return 'tourvisor:departure:' . (string)$id;
        }
        $name = $departure['name'] ?? null;
        if (!is_string($name) || trim($name) !== $name || $name === '') throw new InvalidArgumentException('TV_PASSIVE_MARKET');
        $value = 'tourvisor:' . $name;
        if (strlen($value) > 80 || preg_match('/[\x00-\x1F\x7F*]/', $value)) throw new InvalidArgumentException('TV_PASSIVE_MARKET');
        return $value;
    }

    private static function operatorLabel(mixed $value): string
    {
        if (!is_array($value) || array_is_list($value)) throw new InvalidArgumentException('TV_PASSIVE_OPERATOR');
        foreach (['russianName','name','fullName'] as $key) {
            $label = $value[$key] ?? null;
            if (is_string($label) && trim($label) === $label && $label !== '' && strlen($label) <= 160) return $label;
        }
        throw new InvalidArgumentException('TV_PASSIVE_OPERATOR');
    }

    private static function tourId(mixed $value): string
    {
        if (is_int($value) && $value > 0) return (string)$value;
        if (!is_string($value) || preg_match('/\A[1-9][0-9]{0,31}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('TV_PASSIVE_TOUR_ID');
        }
        return $value;
    }

    private static function date(mixed $value, string $reason): string
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC')) : false;
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function smallInt(mixed $value, int $min, int $max, string $reason): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || (int)$number < $min || (int)$number > $max) throw new InvalidArgumentException($reason);
        return (int)$number;
    }

    private static function directory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory) || basename(rtrim($directory, '/')) !== 'searches') {
            throw new InvalidArgumentException('TV_PASSIVE_DIRECTORY');
        }
    }

    private static function pendingPath(string $directory, string $tourId): string
    {
        return rtrim($directory, '/') . '/' . self::PENDING_PREFIX . hash('sha256', $tourId) . '.json';
    }

    private static function atomicWrite(string $path, array $value, int $limit): bool
    {
        if (is_link($path)) throw new RuntimeException('TV_PASSIVE_WRITE');
        $bytes = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($bytes) < 2 || strlen($bytes) > $limit) throw new RuntimeException('TV_PASSIVE_WRITE');
        $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $file = fopen($temp, 'x');
        if ($file === false) throw new RuntimeException('TV_PASSIVE_WRITE');
        @chmod($temp, 0600);
        try {
            if (fwrite($file, $bytes) !== strlen($bytes) || !fflush($file)) throw new RuntimeException('TV_PASSIVE_WRITE');
            if (function_exists('fsync') && !fsync($file)) throw new RuntimeException('TV_PASSIVE_WRITE');
        } finally {
            fclose($file);
        }
        if (!rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('TV_PASSIVE_WRITE');
        }
        return true;
    }

    private static function receipt(bool $ok, ?string $reason, array $extra = []): array
    {
        return ['source'=>'tourvisor-operator-fuel-passive-intake-v1','ok'=>$ok,'reason'=>$reason] + $extra;
    }
}
