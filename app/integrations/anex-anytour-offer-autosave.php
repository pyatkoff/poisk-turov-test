<?php
declare(strict_types=1);

/**
 * INT-owned postprocessor for terminal direct-ANEX AdditionalPricesDaily batches.
 *
 * It performs no supplier transport, mapping acceptance, or price formula of its own.
 * Existing ANEX APD application + provider-neutral protected money facts are required
 * to agree exactly before an offer can enter the AnyTour snapshot producer.
 */
final class AnyTourAnexOfferAutosaveV1
{
    private const MAX_ACCUMULATED_OFFERS = 60;

    public static function applicable(array $state): bool
    {
        return is_array($state['params'] ?? null)
            && is_int($state['generation'] ?? null)
            && $state['generation'] > 0
            && is_array($state['gateway']['saved_offers'] ?? null)
            && is_array($state['gateway']['saved_offers']['offers'] ?? null)
            && is_array($state['gateway']['saved_offers']['search'] ?? null);
    }

    /**
     * Consume one terminal APD batch and, when the whole requested batch is ready,
     * publish the union of all complete autosave batches observed in this search session.
     *
     * @param callable(array,array):array $applyAdditional existing anytour_anex_search3_additional_application
     * @param callable(string,string):?int $supplierResolver current accepted ANEX->legacy resolver
     * @param callable(string,array,array,DateTimeImmutable):array $ingest LOCAL snapshot ingestor
     */
    public static function consume(
        PDO $db,
        array $plan,
        array &$state,
        array $contextResults,
        DateTimeImmutable $now,
        callable $applyAdditional,
        callable $supplierResolver,
        callable $ingest
    ): array {
        if (!self::applicable($state)) {
            return self::receipt(false, 'not_applicable', 0, 0);
        }
        $offers = $plan['offers'] ?? null;
        if (!is_array($offers) || !array_is_list($offers) || $offers === [] || count($offers) > 6) {
            throw new InvalidArgumentException('ANEX_ANYTOUR_AUTOSAVE_PLAN');
        }

        // A browser-visible APD batch is atomic for autosave: one unknown/deferred
        // context means this batch cannot advance the active AnyTour snapshot.
        foreach ($offers as $item) {
            $digest = is_array($item) ? ($item['context_digest'] ?? null) : null;
            $result = is_string($digest) ? ($contextResults[$digest] ?? null) : null;
            if (!is_array($result)
                || ($result['status'] ?? null) !== 'complete'
                || !is_array($result['evidence'] ?? null)) {
                return self::receipt(false, 'batch_not_terminal', 0, 0);
            }
        }

        $saved = $state['gateway']['saved_offers'];
        $searchRef = $saved['search_ref'] ?? null;
        if (!is_string($searchRef) || !preg_match('/\A[a-f0-9]{32}\z/D', $searchRef)
            || !is_int($saved['created_at'] ?? null) || !is_int($saved['expires_at'] ?? null)
            || $saved['expires_at'] !== $saved['created_at'] + 900
            || $now->getTimestamp() < $saved['created_at'] || $now->getTimestamp() >= $saved['expires_at']) {
            return self::receipt(false, 'search_context_expired', 0, 0);
        }

        $auto = $state['anytour_offer_autosave'] ?? null;
        if (!is_array($auto)
            || ($auto['search_ref'] ?? null) !== $searchRef
            || ($auto['generation'] ?? null) !== $state['generation']) {
            $auto = [
                'search_ref' => $searchRef,
                'generation' => $state['generation'],
                'offers' => [],
                'last_published_digest' => null,
            ];
        }

        foreach ($offers as $item) {
            $offerRef = $item['offer_ref'] ?? null;
            $localId = $item['local_hotel_id'] ?? null;
            $digest = $item['context_digest'] ?? null;
            if (!is_string($offerRef) || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $offerRef)
                || !is_int($localId) || $localId < 1
                || !is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D', $digest)) {
                throw new InvalidArgumentException('ANEX_ANYTOUR_AUTOSAVE_PLAN');
            }
            // Only canonical AnyTour-bridge-eligible hotels enter the autosave set.
            if (self::ownHotelId($db, $localId) === null) continue;
            $auto['offers'][$offerRef] = ['local_hotel_id' => $localId, 'context_digest' => $digest];
        }
        if (count($auto['offers']) > self::MAX_ACCUMULATED_OFFERS) {
            $auto['offers'] = array_slice($auto['offers'], -self::MAX_ACCUMULATED_OFFERS, null, true);
        }
        $state['anytour_offer_autosave'] = $auto;
        if ($auto['offers'] === []) return self::receipt(false, 'no_canonical_bridge', 0, 0);

        self::loadIntContracts();
        $entries = [];
        foreach ($auto['offers'] as $offerRef => $item) {
            $entry = $saved['offers'][$offerRef] ?? null;
            $offer = is_array($entry) ? ($entry['offer'] ?? null) : null;
            $digest = $item['context_digest'] ?? null;
            $attempt = is_string($digest) ? ($state['additional_prices'][$digest] ?? null) : null;
            if (!is_array($entry) || !is_array($offer)
                || ($offer['offer_key'] ?? null) !== $offerRef
                || !is_array($attempt)
                || ($attempt['status'] ?? null) !== 'complete'
                || !is_array($attempt['evidence'] ?? null)) {
                return self::receipt(false, 'accumulator_not_terminal', 0, count($auto['offers']));
            }

            $legacyId = $item['local_hotel_id'];
            $external = $offer['hotel']['external_id'] ?? null;
            if (!is_string($external) || ($offer['hotel']['local_id'] ?? null) !== $legacyId) {
                return self::receipt(false, 'supplier_identity_changed', 0, count($auto['offers']));
            }
            $currentLegacy = $supplierResolver('anex_online', $external);
            if ($currentLegacy !== $legacyId) {
                return self::receipt(false, 'supplier_identity_changed', 0, count($auto['offers']));
            }
            $ownId = self::ownHotelId($db, $legacyId);
            if ($ownId === null) {
                return self::receipt(false, 'canonical_bridge_changed', 0, count($auto['offers']));
            }

            $application = $applyAdditional($attempt['evidence'], $offer);
            $additionalFacts = self::additionalFacts($application, $offer);
            $customerSearch = $application['search_price'] ?? null;
            if ($additionalFacts === null
                || !is_array($customerSearch)
                || ($customerSearch['source'] ?? null) !== 'direct_anex_search'
                || !is_string($customerSearch['amount'] ?? null)
                || !is_string($customerSearch['currency'] ?? null)) {
                return self::receipt(false, 'final_price_not_ready', 0, count($auto['offers']));
            }
            $customerSearchPrice = [
                'amount' => $customerSearch['amount'],
                'currency' => $customerSearch['currency'],
            ];

            $observed = $entry['observed_at'] ?? null;
            if (!is_int($observed) || $observed < $saved['created_at'] || $observed > $now->getTimestamp()) {
                return self::receipt(false, 'offer_context_invalid', 0, count($auto['offers']));
            }
            $offerContract = AnyTourThreeProviderAnexOffer::fromPage([
                'schema_version' => 1,
                'provider' => 'anex',
                'supplier_namespace' => 'anex_online',
                'search' => $saved['search'],
                'offers' => [$offer],
            ], 0, [
                'supplier_namespace' => 'anex_online',
                'external_id' => $external,
                'local_id' => $legacyId,
            ], $searchRef, gmdate('Y-m-d\TH:i:s\Z', $observed), $additionalFacts, $customerSearchPrice);

            $retained = AnyTourThreeProviderOfferContext::retain(
                $offerContract,
                $state['generation'],
                1,
                $saved['created_at'],
                900
            );
            $current = array_intersect_key($retained, array_flip([
                'provider', 'operator', 'local_hotel_id', 'identity', 'generation', 'page',
            ]));
            $party = $offerContract['party'] ?? null;
            if (!is_array($party)) return self::receipt(false, 'offer_party_invalid', 0, count($auto['offers']));
            $priced = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate(
                $offerContract['money'],
                (int)$party['adults'],
                (int)$party['children']
            );
            $existingFinal = $application['search_plus_additional']['amount'] ?? null;
            if (!is_string($existingFinal)
                || ($application['search_plus_additional']['currency'] ?? null) !== 'RUB'
                || ($priced['search_price_with_surcharge']['amount'] ?? null) !== $existingFinal
                || ($priced['search_price_with_surcharge']['currency'] ?? null) !== 'RUB') {
                return self::receipt(false, 'protected_price_mismatch', 0, count($auto['offers']));
            }
            $entries[] = [
                'anytour_hotel_id' => $ownId,
                'offer' => $offerContract,
                'retained' => $retained,
                'current' => $current,
                'priced_money' => $priced,
            ];
        }

        $publishDigest = hash('sha256', json_encode(array_map(static function (array $entry): array {
            return [
                'hotel' => $entry['anytour_hotel_id'],
                'identity' => $entry['current']['identity'],
                'price' => $entry['priced_money']['search_price_with_surcharge']['amount'] ?? null,
            ];
        }, $entries), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (($auto['last_published_digest'] ?? null) === $publishDigest) {
            return self::receipt(false, 'already_published', count($entries), count($auto['offers']));
        }

        $result = AnyTourIntOfferSnapshotProducerV1::produce('anex', $state['params'], [
            'complete' => true,
            'authoritative_empty' => false,
            'offers' => $entries,
        ], $now, $ingest);
        if (($result['published'] ?? null) === true) {
            $state['anytour_offer_autosave']['last_published_digest'] = $publishDigest;
        }
        return [
            'source' => 'anex-anytour-offer-autosave-v1',
            'published' => ($result['published'] ?? false) === true,
            'reason' => $result['reason'] ?? null,
            'readyOfferCount' => (int)($result['readyOfferCount'] ?? 0),
            'accumulatedOfferCount' => count($auto['offers']),
            'selectionAuthority' => false,
        ];
    }

    private static function additionalFacts(array $application, array $offer): ?array
    {
        if (($application['application_state'] ?? null) !== 'applied'
            || !is_array($application['search_plus_additional'] ?? null)
            || ($application['search_plus_additional']['currency'] ?? null) !== 'RUB'
            || !is_string($application['search_plus_additional']['amount'] ?? null)
            || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $application['search_plus_additional']['amount'])
            || !is_array($application['rates'] ?? null)
            || !is_array($application['rates']['adult'] ?? null)) return null;
        $adult = $application['rates']['adult'];
        if (($adult['currency'] ?? null) !== 'RUB' || !is_string($adult['amount'] ?? null)) return null;
        $facts = [['kind' => 'fuel_adult', 'amount' => $adult['amount'], 'currency' => 'RUB', 'source' => 'anex_additional']];
        $children = $offer['children'] ?? null;
        if (!is_int($children) || $children < 0) return null;
        if ($children > 0) {
            $child = $application['rates']['child'] ?? null;
            if (!is_array($child) || ($child['currency'] ?? null) !== 'RUB' || !is_string($child['amount'] ?? null)) return null;
            $facts[] = ['kind' => 'fuel_child', 'amount' => $child['amount'], 'currency' => 'RUB', 'source' => 'anex_additional'];
        }
        return $facts;
    }

    private static function ownHotelId(PDO $db, int $legacyId): ?int
    {
        $q = $db->prepare("SELECT s.anytour_hotel_id FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id WHERE s.namespace='legacy_catalog' AND s.external_key=:legacy AND h.is_active=1 LIMIT 2");
        $q->execute(['legacy' => (string)$legacyId]);
        $rows = $q->fetchAll(PDO::FETCH_COLUMN);
        if (count($rows) !== 1) return null;
        $own = (int)$rows[0];
        return $own > 0 ? $own : null;
    }

    private static function loadIntContracts(): void
    {
        require_once __DIR__ . '/three-provider-anex-offer.php';
        require_once __DIR__ . '/three-provider-offer-context.php';
        require_once __DIR__ . '/three-provider-money-facts.php';
        require_once __DIR__ . '/anytour-offer-snapshot-producer.php';
    }

    private static function receipt(bool $published, string $reason, int $ready, int $accumulated): array
    {
        return [
            'source' => 'anex-anytour-offer-autosave-v1',
            'published' => $published,
            'reason' => $reason,
            'readyOfferCount' => $ready,
            'accumulatedOfferCount' => $accumulated,
            'selectionAuthority' => false,
        ];
    }
}

/**
 * Best-effort runtime adapter. Persistence failure must never turn a valid supplier
 * response into a user-visible search failure.
 */
function anytour_anex_anytour_offer_autosave_runtime(array $plan, array &$state, array $contextResults): array
{
    if (!AnyTourAnexOfferAutosaveV1::applicable($state)) {
        return ['published' => false, 'reason' => 'not_applicable'];
    }
    try {
        if (!function_exists('v2_data_db') || !class_exists('AnyTourAnexSearchMappingRegistry')
            || !function_exists('anytour_anex_search3_additional_application')) {
            return ['published' => false, 'reason' => 'runtime_dependency_unavailable'];
        }
        $localIngest = getenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE');
        $candidates = [];
        if (is_string($localIngest) && $localIngest !== '') $candidates[] = $localIngest;
        $docroot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if (is_string($docroot) && $docroot !== '') {
            $candidates[] = rtrim($docroot, '/') . '/_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php';
        }
        $candidates[] = dirname(__DIR__, 2) . '/v2/data/anytour-offer-snapshot-ingest-v1.php';
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                require_once $candidate;
                break;
            }
        }
        if (!class_exists('AnyTourOfferSnapshotIngestV1')) {
            return ['published' => false, 'reason' => 'local_ingest_unavailable'];
        }

        $db = v2_data_db();
        if (!$db instanceof PDO) return ['published' => false, 'reason' => 'db_unavailable'];
        $resolver = AnyTourAnexSearchMappingRegistry::fromPdo($db)->previewResolver();
        $result = AnyTourAnexOfferAutosaveV1::consume(
            $db,
            $plan,
            $state,
            $contextResults,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            static function (array $evidence, array $offer): array {
                return anytour_anex_search3_additional_application($evidence, $offer);
            },
            $resolver,
            static function (string $provider, array $search, array $rows, DateTimeImmutable $at) use ($db): array {
                return AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db, $provider, $search, $rows, $at);
            }
        );
        if (($result['published'] ?? false) === true) {
            error_log('ANEX_ANYTOUR_AUTOSAVE_OK offers=' . (int)($result['readyOfferCount'] ?? 0));
        }
        return $result;
    } catch (Throwable $error) {
        error_log('ANEX_ANYTOUR_AUTOSAVE_FAILED ' . preg_replace('/[^A-Z0-9_:-]+/i', '_', substr($error->getMessage(), 0, 120)));
        return ['published' => false, 'reason' => 'autosave_failed'];
    }
}
