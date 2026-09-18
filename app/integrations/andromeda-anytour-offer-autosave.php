<?php
declare(strict_types=1);

require_once __DIR__ . '/three-provider-operator.php';
require_once __DIR__ . '/three-provider-availability.php';
require_once __DIR__ . '/three-provider-flight-details.php';
require_once __DIR__ . '/three-provider-meal-family.php';
require_once __DIR__ . '/three-provider-room-placement.php';
require_once __DIR__ . '/three-provider-money-facts.php';
require_once __DIR__ . '/three-provider-offer-contract.php';
require_once __DIR__ . '/three-provider-offer-context.php';
require_once __DIR__ . '/three-provider-search-handoff.php';
require_once __DIR__ . '/anytour-offer-snapshot-producer.php';
require_once __DIR__ . '/andromeda-pagination.php';

/**
 * Supplier-free Andromeda/SAMO -> AnyTour offer-store bridge.
 *
 * The caller already owns the Andromeda search lock. This class only reads retained
 * page/checkpoint files and already-saved surcharge evidence. It never logs in, calls
 * a supplier, accepts hotel identity, selects a tour or books anything.
 */
final class AnyTourAndromedaOfferAutosaveV1
{
    private const MAX_PAGES = 1000;
    private const MAX_OFFERS = 5000;
    private const CONTEXT_TTL = 900;
    private const CHECKPOINT_VERSION = 1;
    private const REJECTION_REQUIRED_FIELDS = [
        'id','hotelKey','operatorKey','isOperatorHotelKey','price','currency','currencyKey','checkIn','nights',
        'hotel','operator','meal','mealKey','room','htplace','adult','child',
    ];

    /**
     * @param callable(array):array $mappingReader current [namespace,external] -> legacy local map
     * @param callable(array):array $canonicalResolver legacy local -> AnyTour own id/null map
     * @param callable(array,int,array,array):?array $surchargeReader retained local pricing reader
     * @param callable(string,array):bool $save atomic private checkpoint writer
     * @param callable(string,array,array,DateTimeImmutable):array $ingest LOCAL snapshot ingestor
     */
    public static function consume(
        array $request,
        string $directory,
        string $searchRef,
        int $generation,
        DateTimeImmutable $now,
        callable $mappingReader,
        callable $canonicalResolver,
        callable $surchargeReader,
        callable $save,
        callable $ingest
    ): array {
        if (isset($request['hotel_scope']) || isset($request['andromeda_operator_ids'])
            || isset($request['action'])) {
            return self::receipt(false, 'not_authoritative_search', 0, 0, 0);
        }
        if (!isset($request['params']) || !is_array($request['params'])
            || ($request['generation'] ?? null) !== $generation
            || !preg_match('/\A[a-f0-9]{64}\z/D', $searchRef)
            || $generation < 1 || !is_dir($directory) || is_link($directory)
            || basename($directory) !== 'searches') {
            return self::receipt(false, 'context_invalid', 0, 0, 0);
        }
        $nowTs = $now->getTimestamp();
        if ($nowTs < 1) return self::receipt(false, 'context_invalid', 0, 0, 0);

        $firstPath = $directory . '/' . $searchRef . '-1.json';
        $first = self::readState($firstPath, true);
        if ($first === null) return self::receipt(false, 'cohort_incomplete', 0, 0, 0);
        $firstSnapshot = self::validateState($first, $searchRef, $generation, 1, $nowTs);
        $firstCreated = $first['store']['created_at'];
        $target = $firstSnapshot['pages_count'];
        if ($target < 0 || $target > self::MAX_PAGES) {
            return self::receipt(false, 'cohort_invalid', 0, 0, 0);
        }
        if (!self::rejectionsSafeOutsideAndromeda($firstSnapshot['rejected'])) {
            return self::receipt(false, 'cohort_rejected_rows', 0, 0, 0);
        }

        try {
            $firstDecision = AnyTourAndromedaPaginationV1::nextTarget(
                1,
                $target,
                count($firstSnapshot['offers']),
                (string)($first['status'] ?? ''),
                count($firstSnapshot['rejected']),
                max(1, $target)
            );
        } catch (RuntimeException $error) {
            if ($error->getMessage() === 'andromeda_pages_invalid') {
                return self::receipt(false, 'cohort_invalid', 0, 0, 0);
            }
            throw $error;
        }
        if (($firstDecision['terminal'] ?? false) === true) {
            $states = [];
            $snapshots = [];
            $target = 0;
        } else {
            $states = [1 => $first];
            $snapshots = [1 => $firstSnapshot];
            $target = $firstDecision['target'];
        }

        for ($page = 2; $page <= $target; ++$page) {
            $path = $directory . '/' . $searchRef . '-' . $firstCreated . '-' . $page . '.json';
            $state = self::readState($path, true);
            if ($state === null) return self::receipt(false, 'cohort_incomplete', 0, 0, 0);
            $snapshot = self::validateState($state, $searchRef, $generation, $page, $nowTs);
            if (!self::rejectionsSafeOutsideAndromeda($snapshot['rejected'])) {
                return self::receipt(false, 'cohort_rejected_rows', 0, 0, 0);
            }
            try {
                $decision = AnyTourAndromedaPaginationV1::nextTarget(
                    $page,
                    $snapshot['pages_count'],
                    count($snapshot['offers']),
                    (string)($state['status'] ?? ''),
                    count($snapshot['rejected']),
                    $target
                );
            } catch (RuntimeException $error) {
                if ($error->getMessage() === 'andromeda_pages_invalid') {
                    return self::receipt(false, 'cohort_invalid', 0, 0, 0);
                }
                throw $error;
            }
            if (($decision['terminal'] ?? false) === true) {
                $target = $decision['target'];
                break;
            }
            $states[$page] = $state;
            $snapshots[$page] = $snapshot;
            $target = $decision['target'];
        }

        $offers = [];
        $seen = [];
        foreach ($snapshots as $page => $snapshot) {
            foreach ($snapshot['offers'] as $offer) {
                if (!is_array($offer)) return self::receipt(false, 'cohort_invalid', 0, 0, 0);
                $offerRef = $offer['offer_ref'] ?? null;
                if (!is_string($offerRef) || !preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef)) {
                    return self::receipt(false, 'cohort_invalid', 0, 0, 0);
                }
                if (isset($seen[$offerRef])) {
                    return self::receipt(false, 'duplicate_offer_identity', 0, count($offers), 0);
                }
                $seen[$offerRef] = true;
                $offers[] = ['page' => $page, 'state' => $states[$page], 'offer' => $offer];
                if (count($offers) > self::MAX_OFFERS) {
                    return self::receipt(false, 'too_many_offers', 0, count($offers), 0);
                }
            }
        }

        $owned = [];
        foreach ($offers as $row) {
            if (self::andromedaOwnsOperator((string)($row['offer']['operator'] ?? ''))) $owned[] = $row;
        }

        // A complete supplier cohort containing only operators owned by direct ANEX or
        // Tourvisor is an authoritative empty Andromeda-owned cohort for this exact scope.
        if ($owned === []) {
            $digest = hash('sha256', json_encode([
                'search_ref' => $searchRef, 'generation' => $generation,
                'pages' => $target, 'owned' => [],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (self::alreadyPublished($directory, $searchRef, $firstCreated, $generation, $digest)) {
                return self::receipt(false, 'already_published', 0, 0, count($offers));
            }
            $result = AnyTourIntOfferSnapshotProducerV1::produce('andromeda', $request['params'], [
                'complete' => true, 'authoritative_empty' => true, 'offers' => [],
            ], $now, $ingest);
            if (($result['published'] ?? null) === true) {
                self::saveCheckpoint($directory, $searchRef, $firstCreated, $generation, $digest, $nowTs,
                    (int)($result['readyOfferCount'] ?? 0), $save);
            }
            return self::producerReceipt($result, count($owned), count($offers));
        }

        $current = $mappingReader(array_column($owned, 'offer'));
        if (!is_array($current)) throw new RuntimeException('ANDROMEDA_ANYTOUR_MAPPING_RECEIPT');
        $mapped = [];
        $legacyIds = [];
        foreach ($owned as $row) {
            $offer = $row['offer'];
            $local = $offer['local_hotel_id'] ?? null;
            $namespace = $offer['supplier_namespace'] ?? null;
            $external = $offer['external_hotel_id'] ?? null;
            if (!is_int($local) || $local < 1 || !is_string($namespace) || $namespace === ''
                || (!is_string($external) && !is_int($external))) continue;
            $key = json_encode([$namespace, (string)$external], JSON_THROW_ON_ERROR);
            if (($current[$key] ?? null) !== $local) continue;
            $mapped[] = $row;
            $legacyIds[$local] = $local;
        }
        if ($mapped === []) {
            return self::receipt(false, 'no_current_mapped_offers', 0, count($owned), count($offers));
        }

        $canonical = $canonicalResolver(array_values($legacyIds));
        if (!is_array($canonical)) throw new RuntimeException('ANDROMEDA_ANYTOUR_CANONICAL_RECEIPT');
        $childAges = self::childAges($request['params']['childs'] ?? null);
        $entries = [];
        foreach ($mapped as $row) {
            $page = $row['page'];
            $state = $row['state'];
            $raw = $row['offer'];
            $local = $raw['local_hotel_id'];
            $entry = self::entryFromOffer(
                $raw,
                $searchRef,
                $generation,
                $page,
                $state['store']['created_at'],
                $childAges,
                $canonical[$local] ?? null,
                $surchargeReader($state, $firstCreated, $raw, $current)
            );
            if ($entry === null) {
                return self::receipt(false, 'offer_contract_incomplete', 0, count($owned), count($offers));
            }
            $entries[] = $entry;
        }

        $digestRows = [];
        foreach ($entries as $entry) {
            $digestRows[] = [
                'anytour_hotel_id' => $entry['anytour_hotel_id'],
                'identity' => $entry['current']['identity'],
                'page' => $entry['current']['page'],
                'priced' => $entry['priced_money']['search_price_with_surcharge'] ?? null,
                'verified_final' => $entry['verified_quote']['final_price'] ?? null,
            ];
        }
        $digest = hash('sha256', json_encode([
            'search_ref' => $searchRef, 'generation' => $generation,
            'pages' => $target, 'offers' => $digestRows,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (self::alreadyPublished($directory, $searchRef, $firstCreated, $generation, $digest)) {
            return self::receipt(false, 'already_published', count($entries), count($owned), count($offers));
        }

        $result = AnyTourIntOfferSnapshotProducerV1::produce('andromeda', $request['params'], [
            'complete' => true,
            'authoritative_empty' => false,
            'offers' => $entries,
        ], $now, $ingest);
        if (($result['published'] ?? null) === true) {
            self::saveCheckpoint($directory, $searchRef, $firstCreated, $generation, $digest, $nowTs,
                (int)($result['readyOfferCount'] ?? 0), $save);
        }
        return self::producerReceipt($result, count($owned), count($offers));
    }

    private static function entryFromOffer(
        array $raw,
        string $searchRef,
        int $generation,
        int $page,
        int $issuedAt,
        array $childAges,
        mixed $anytourHotelId,
        ?array $pricing
    ): ?array {
        try {
            if ($anytourHotelId !== null && (!is_int($anytourHotelId) || $anytourHotelId < 1)) return null;
            $local = $raw['local_hotel_id'] ?? null;
            $external = $raw['external_hotel_id'] ?? null;
            $namespace = $raw['supplier_namespace'] ?? null;
            $operator = $raw['operator'] ?? null;
            $offerRef = $raw['offer_ref'] ?? null;
            $checkin = $raw['check_in'] ?? null;
            $nights = $raw['nights'] ?? null;
            $adults = $raw['adults'] ?? null;
            $children = $raw['children'] ?? null;
            $mealRaw = $raw['meal']['raw_label'] ?? null;
            $roomRaw = $raw['room_raw'] ?? null;
            $placementRaw = $raw['placement_raw'] ?? null;
            $price = $raw['price'] ?? null;
            if (!is_int($local) || $local < 1 || !is_string($namespace) || $namespace === ''
                || (!is_string($external) && !is_int($external)) || !is_string($operator) || trim($operator) === ''
                || !is_string($offerRef) || !is_string($checkin) || !is_int($nights)
                || !is_int($adults) || !is_int($children) || count($childAges) !== $children
                || !is_string($mealRaw) || !is_string($roomRaw) || trim($roomRaw) === ''
                || !is_string($placementRaw) || !is_array($price)
                || !is_string($price['amount'] ?? null) || !is_string($price['currency'] ?? null)) return null;
            if (!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $price['amount'])
                || !preg_match('/[1-9]/', $price['amount'])) return null;

            $meal = AnyTourThreeProviderMealFamily::normalize($mealRaw);
            $roomPlacement = AnyTourThreeProviderRoomPlacement::normalize(
                'andromeda', $roomRaw, trim($placementRaw) === '' ? null : $placementRaw
            );
            $surcharge = null;
            $verifiedQuote = null;
            if ($pricing !== null) {
                if (($pricing['state'] ?? null) === 'verified'
                    && array_key_exists('verified_quote', $pricing)
                    && is_array($pricing['verified_quote'])
                    && ($pricing['fact'] ?? null) === null) {
                    $verifiedQuote = $pricing['verified_quote'];
                } elseif (($pricing['state'] ?? null) === 'estimated'
                    && is_array($pricing['fact'] ?? null)
                    && ($pricing['verified_quote'] ?? null) === null) {
                    $surcharge = $pricing['fact'];
                } elseif (($pricing['provider'] ?? null) === 'andromeda'
                    && ($pricing['state'] ?? null) === 'estimated') {
                    // Backward-compatible source-only fixture path.
                    $surcharge = $pricing;
                } else {
                    return null;
                }
            }
            $additional = [];
            if ($surcharge !== null) {
                $additional = self::additionalFromSurcharge($surcharge, $price);
                if ($additional === null) return null;
            }
            $offer = AnyTourThreeProviderOfferContract::fromSearch([
                'provider' => 'andromeda',
                'operator' => $operator,
                'local_hotel_id' => $local,
                'provider_hotel_ref' => $namespace . ':' . (string)$external,
                'search_ref' => $searchRef,
                'offer_ref' => $offerRef,
                'checkin' => $checkin,
                'nights' => $nights,
                'adults' => $adults,
                'children' => $children,
                'child_ages' => $childAges,
                'meal' => [
                    'raw' => $meal['raw'], 'family' => $meal['family'], 'qualifiers' => $meal['qualifiers'],
                ],
                'room' => [
                    'raw' => $roomPlacement['room']['raw'],
                    'normalized' => $roomPlacement['room']['normalized'],
                ],
                'placement' => $roomPlacement['placement'] === null ? null : [
                    'raw' => $roomPlacement['placement']['raw'],
                    'normalized' => $roomPlacement['placement']['normalized'],
                ],
                'availability' => [
                    'hotel' => null, 'flight_outbound_economy' => null, 'flight_return_economy' => null,
                ],
                'search_price' => [
                    'amount' => $price['amount'], 'currency' => $price['currency'], 'source' => 'andromeda_search',
                ],
                'fuel_charge_reported' => null,
                'additional_prices_reported' => $additional,
                'observed_at' => gmdate('Y-m-d\TH:i:s\Z', $issuedAt),
            ]);
            $retained = AnyTourThreeProviderOfferContext::retain(
                $offer, $generation, $page, $issuedAt, self::CONTEXT_TTL
            );
            $current = array_intersect_key($retained, array_flip([
                'provider', 'operator', 'local_hotel_id', 'identity', 'generation', 'page',
            ]));
            $priced = null;
            if ($surcharge !== null) {
                $priced = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate(
                    $offer['money'], $adults, $children
                );
                if (($priced['search_price_with_surcharge'] ?? null) !== ($surcharge['search_price_with_surcharge'] ?? null)) {
                    throw new DomainException('ANDROMEDA_ANYTOUR_PROTECTED_PRICE_MISMATCH');
                }
            }
            $entry = [
                'anytour_hotel_id' => $anytourHotelId,
                'offer' => $offer,
                'retained' => $retained,
                'current' => $current,
                'priced_money' => $priced,
            ];
            if ($verifiedQuote !== null) $entry['verified_quote'] = $verifiedQuote;
            return $entry;
        } catch (DomainException $error) {
            if ($error->getMessage() === 'ANDROMEDA_ANYTOUR_PROTECTED_PRICE_MISMATCH') throw $error;
            return null;
        } catch (Throwable $error) {
            return null;
        }
    }

    private static function additionalFromSurcharge(array $fact, array $price): ?array
    {
        if (($fact['schema_version'] ?? null) !== 1 || ($fact['provider'] ?? null) !== 'andromeda'
            || ($fact['state'] ?? null) !== 'estimated' || ($fact['surcharge_scope'] ?? null) !== 'party'
            || ($fact['arithmetic_applied'] ?? null) !== true || ($fact['final_price_verified'] ?? null) !== false
            || ($fact['search_price'] ?? null) !== ['amount' => $price['amount'], 'currency' => $price['currency']]) {
            return null;
        }
        $party = $fact['party_surcharge'] ?? null;
        $total = $fact['search_price_with_surcharge'] ?? null;
        if (!is_array($party) || !is_array($total)
            || !is_string($party['amount'] ?? null) || !is_string($party['currency'] ?? null)
            || !is_string($party['source'] ?? null)
            || !in_array($party['source'], ['andromeda_get_flights_transport', 'andromeda_get_flights_transport_converted'], true)
            || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $party['amount'])
            || $party['currency'] !== $price['currency']
            || ($total['currency'] ?? null) !== $price['currency']
            || ($total['source'] ?? null) !== 'derived_search_estimate') return null;
        return [[
            'kind' => 'party_transport_surcharge',
            'amount' => $party['amount'],
            'currency' => $party['currency'],
            'source' => $party['source'],
        ]];
    }

    private static function childAges(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 3) {
            throw new InvalidArgumentException('ANDROMEDA_ANYTOUR_CHILD_AGES');
        }
        $out = [];
        foreach ($value as $age) {
            if (is_bool($age) || (!is_int($age) && !is_string($age))
                || !preg_match('/\A(?:0|[1-9][0-9]?)\z/D', (string)$age)) {
                throw new InvalidArgumentException('ANDROMEDA_ANYTOUR_CHILD_AGES');
            }
            $number = (int)$age;
            if ($number < 0 || $number > 17) throw new InvalidArgumentException('ANDROMEDA_ANYTOUR_CHILD_AGES');
            $out[] = $number;
        }
        return $out;
    }

    private static function rejectionsSafeOutsideAndromeda(array $rejected): bool
    {
        $seen = [];
        foreach ($rejected as $row) {
            if (!is_array($row)) return false;
            $keys = array_keys($row);
            sort($keys);
            if ($keys !== ['index', 'missing_field', 'ownership_class', 'reason']) return false;
            $index = $row['index'] ?? null;
            if (!is_int($index) || $index < 0 || $index >= 2000 || isset($seen[$index])) return false;
            $seen[$index] = true;
            if (($row['reason'] ?? null) !== 'MISSING_FIELD'
                || !is_string($row['missing_field'] ?? null)
                || !in_array($row['missing_field'], self::REJECTION_REQUIRED_FIELDS, true)
                || ($row['ownership_class'] ?? null) !== 'excluded_direct_or_tv') return false;
        }
        return true;
    }

    private static function andromedaOwnsOperator(string $raw): bool
    {
        $value = str_replace(['Ё', 'ё'], 'е', trim($raw));
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
        if ($compact === '') return false;
        foreach (['anex', 'анекс', 'pegas', 'пегас', 'coral', 'корал', 'sunmar', 'санмар'] as $ownedElsewhere) {
            if (str_contains($compact, $ownedElsewhere)) return false;
        }
        return true;
    }

    private static function validateState(array $state, string $ref, int $generation, int $page, int $now): array
    {
        if (!in_array($state['status'] ?? null, ['complete', 'partial'], true)
            || ($state['search_ref'] ?? null) !== $ref || ($state['generation'] ?? null) !== $generation
            || !is_array($state['store'] ?? null)) {
            throw new DomainException('ANDROMEDA_ANYTOUR_COHORT_INVALID');
        }
        $store = $state['store'];
        if (($store['version'] ?? null) !== 1 || ($store['search_ref'] ?? null) !== $ref
            || ($store['generation'] ?? null) !== $generation || !is_int($store['created_at'] ?? null)
            || !is_int($store['expires_at'] ?? null) || $store['created_at'] > $now
            || $now >= $store['expires_at'] || $store['expires_at'] !== $store['created_at'] + self::CONTEXT_TTL
            || !is_array($store['snapshot'] ?? null)) {
            throw new DomainException('ANDROMEDA_ANYTOUR_COHORT_INVALID');
        }
        $snapshot = $store['snapshot'];
        if (($snapshot['provider'] ?? null) !== 'andromeda' || ($snapshot['search_ref'] ?? null) !== $ref
            || ($snapshot['generation'] ?? null) !== $generation || ($snapshot['page'] ?? null) !== $page
            || !is_int($snapshot['pages_count'] ?? null) || !is_array($snapshot['offers'] ?? null)
            || !array_is_list($snapshot['offers']) || !is_array($snapshot['rejected'] ?? null)
            || !array_is_list($snapshot['rejected']) || ($snapshot['selection_enabled'] ?? null) !== false) {
            throw new DomainException('ANDROMEDA_ANYTOUR_COHORT_INVALID');
        }
        return $snapshot;
    }

    private static function readState(string $path, bool $optional): ?array
    {
        if (is_link($path)) throw new DomainException('ANDROMEDA_ANYTOUR_COHORT_INVALID');
        if (!file_exists($path)) return $optional ? null : throw new DomainException('ANDROMEDA_ANYTOUR_COHORT_INVALID');
        if (!is_file($path) || filesize($path) < 2 || filesize($path) > 3000000) {
            throw new DomainException('ANDROMEDA_ANYTOUR_COHORT_INVALID');
        }
        $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new DomainException('ANDROMEDA_ANYTOUR_COHORT_INVALID');
        return $value;
    }

    private static function checkpointPath(string $directory, string $ref, int $created): string
    {
        return $directory . '/' . $ref . '-' . $created . '-anytour-offer-autosave-v1.json';
    }

    private static function alreadyPublished(string $directory, string $ref, int $created, int $generation, string $digest): bool
    {
        $path = self::checkpointPath($directory, $ref, $created);
        if (!file_exists($path)) return false;
        $value = self::readState($path, false);
        if (($value['version'] ?? null) !== self::CHECKPOINT_VERSION || ($value['provider'] ?? null) !== 'andromeda'
            || ($value['search_ref'] ?? null) !== $ref || ($value['generation'] ?? null) !== $generation
            || !is_string($value['cohort_digest'] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/D', $value['cohort_digest'])
            || !is_int($value['published_at'] ?? null) || !is_int($value['ready_offer_count'] ?? null)) {
            throw new DomainException('ANDROMEDA_ANYTOUR_CHECKPOINT_INVALID');
        }
        return hash_equals($value['cohort_digest'], $digest);
    }

    private static function saveCheckpoint(
        string $directory,
        string $ref,
        int $created,
        int $generation,
        string $digest,
        int $publishedAt,
        int $readyCount,
        callable $save
    ): void {
        $path = self::checkpointPath($directory, $ref, $created);
        $value = [
            'version' => self::CHECKPOINT_VERSION,
            'provider' => 'andromeda',
            'search_ref' => $ref,
            'generation' => $generation,
            'cohort_digest' => $digest,
            'published_at' => $publishedAt,
            'ready_offer_count' => $readyCount,
        ];
        if ($save($path, $value) !== true) throw new RuntimeException('ANDROMEDA_ANYTOUR_CHECKPOINT_WRITE');
        $read = self::readState($path, false);
        if ($read !== $value) throw new RuntimeException('ANDROMEDA_ANYTOUR_CHECKPOINT_READBACK');
    }

    private static function producerReceipt(array $result, int $owned, int $received): array
    {
        return [
            'source' => 'andromeda-anytour-offer-autosave-v1',
            'published' => ($result['published'] ?? false) === true,
            'reason' => $result['reason'] ?? null,
            'readyOfferCount' => (int)($result['readyOfferCount'] ?? 0),
            'ownedOfferCount' => $owned,
            'receivedOfferCount' => $received,
            'selectionAuthority' => false,
        ];
    }

    private static function receipt(bool $published, string $reason, int $ready, int $owned, int $received): array
    {
        return [
            'source' => 'andromeda-anytour-offer-autosave-v1',
            'published' => $published,
            'reason' => $reason,
            'readyOfferCount' => $ready,
            'ownedOfferCount' => $owned,
            'receivedOfferCount' => $received,
            'selectionAuthority' => false,
        ];
    }
}

function anytour_andromeda_anytour_offer_canonical_targets(PDO $db, array $legacyIds): array
{
    $ids = [];
    foreach ($legacyIds as $id) if (is_int($id) && $id > 0) $ids[$id] = $id;
    if ($ids === []) return [];
    $out = [];
    foreach (array_chunk(array_values($ids), 400) as $chunk) {
        $query = $db->prepare("SELECT s.external_key,s.anytour_hotel_id FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id WHERE s.namespace='legacy_catalog' AND h.is_active=1 AND s.external_key IN (" . implode(',', array_fill(0, count($chunk), '?')) . ') ORDER BY s.external_key,s.anytour_hotel_id');
        $query->execute(array_map('strval', $chunk));
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $legacy = (int)$row['external_key'];
            $own = (int)$row['anytour_hotel_id'];
            if ($legacy < 1 || $own < 1) continue;
            if (!array_key_exists($legacy, $out)) $out[$legacy] = $own;
            elseif ($out[$legacy] !== $own) $out[$legacy] = null;
        }
    }
    return $out;
}

/** Best-effort runtime adapter. Search response must survive persistence failure. */
function anytour_andromeda_anytour_offer_autosave_runtime(
    array $request,
    PDO $db,
    array $saved,
    string $directory,
    string $searchRef,
    int $generation
): array {
    try {
        $localIngest = getenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE');
        $candidates = [];
        if (is_string($localIngest) && $localIngest !== '') $candidates[] = $localIngest;
        $docroot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if (is_string($docroot) && $docroot !== '') {
            $candidates[] = rtrim($docroot, '/') . '/_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php';
        }
        $candidates[] = dirname(__DIR__, 2) . '/v2/data/anytour-offer-snapshot-ingest-v1.php';
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) { require_once $candidate; break; }
        }
        if (!class_exists('AnyTourOfferSnapshotIngestV1')) {
            return ['published' => false, 'reason' => 'local_ingest_unavailable'];
        }
        $reader = __DIR__ . '/andromeda-saved-package-runtime.php';
        if (is_file($reader) && !is_link($reader)) require_once $reader;
        if (!function_exists('anytour_andromeda_read_saved_pricing')
            || !function_exists('anytour_andromeda_search3_current_mappings')
            || !function_exists('anytour_andromeda_search3_save')) {
            return ['published' => false, 'reason' => 'runtime_dependency_unavailable'];
        }
        $country = (int)($request['params']['countryId'] ?? 0);
        if ($country < 1) return ['published' => false, 'reason' => 'country_invalid'];
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowTs = $now->getTimestamp();
        $result = AnyTourAndromedaOfferAutosaveV1::consume(
            $request,
            $directory,
            $searchRef,
            $generation,
            $now,
            static fn(array $offers): array => anytour_andromeda_search3_current_mappings($db, $country, $offers),
            static fn(array $legacyIds): array => anytour_andromeda_anytour_offer_canonical_targets($db, $legacyIds),
            static function(array $state, int $created, array $offer, array $current) use ($directory, $searchRef, $generation, $nowTs): ?array {
                $allows = static function(array $candidate) use ($current): bool {
                    $key = json_encode([$candidate['supplier_namespace'] ?? null, (string)($candidate['external_hotel_id'] ?? '')]);
                    return is_int($candidate['local_hotel_id'] ?? null)
                        && ($current[$key] ?? null) === $candidate['local_hotel_id'];
                };
                $context = [
                    'provider' => 'andromeda', 'search_ref' => $searchRef, 'generation' => $generation,
                    'page' => $state['store']['snapshot']['page'], 'offer_ref' => $offer['offer_ref'],
                ];
                return anytour_andromeda_read_saved_pricing(
                    $directory, $state['store'], $created, $context, $allows, $nowTs
                );
            },
            static fn(string $path, array $value): bool => anytour_andromeda_search3_save($path, $value),
            static function(string $provider, array $search, array $rows, DateTimeImmutable $at) use ($db): array {
                return AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db, $provider, $search, $rows, $at);
            }
        );
        if (($result['published'] ?? false) === true) {
            error_log('ANDROMEDA_ANYTOUR_AUTOSAVE_OK offers=' . (int)($result['readyOfferCount'] ?? 0));
        }
        return $result;
    } catch (Throwable $error) {
        error_log('ANDROMEDA_ANYTOUR_AUTOSAVE_FAILED ' . preg_replace('/[^A-Z0-9_:-]+/i', '_', substr($error->getMessage(), 0, 120)));
        return ['published' => false, 'reason' => 'autosave_failed'];
    }
}
