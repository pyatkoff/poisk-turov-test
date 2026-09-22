<?php
declare(strict_types=1);

require_once __DIR__ . '/tourvisor-operator-fuel-retained-intake.php';
require_once __DIR__ . '/operator-fuel-rule-store.php';
require_once dirname(__DIR__, 2) . '/v2/data/db-v1.php';

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

    public static function captureTour(
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

        $context = self::offerContext($db, $tourId, $now);
        if ($context === null) return self::receipt(false, 'offer_context_missing');
        if ($context['operator_family'] !== AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator)) {
            throw new DomainException('TV_PASSIVE_OPERATOR_CONFLICT');
        }

        $checkin = self::date($tour['date'] ?? null, 'TV_PASSIVE_DATE');
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
        DateTimeImmutable $now
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
                static fn(string $target, array $value): bool => self::atomicWrite($target, $value, 131072)
            );
            $default = self::defaultFlight($flights);
            $confirmed = AnyTourOperatorFuelRuleStoreV1::inputForTarget($directory, [
                'provider' => 'tourvisor',
                'operator' => $observation['operator_raw'],
                'scope' => $observation['scope'],
                'offer_ref_digest' => $observation['offer_ref_digest'],
                'flight_dates' => [$default['dateForward'], $default['dateBackward']],
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

    private static function offerContext(PDO $db, string $tourId, DateTimeImmutable $now): ?array
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
