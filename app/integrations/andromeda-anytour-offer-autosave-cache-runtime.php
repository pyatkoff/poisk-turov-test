<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-anytour-offer-autosave.php';
require_once __DIR__ . '/andromeda-saved-pricing-evidence.php';
require_once __DIR__ . '/andromeda-surcharge-cache-autosave.php';

/**
 * Best-effort background autosave runtime with exact-pricing-first group-cache reuse.
 * This is supplier-free: it reads only the current PRICE cohort, existing exact
 * pricing sidecars and the private strict-group evidence store.
 */
function anytour_andromeda_anytour_offer_autosave_cache_runtime(
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
            $candidates[] = rtrim($docroot, '/')
                . '/_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php';
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
        if (!function_exists('anytour_andromeda_read_saved_pricing_with_evidence')
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
            static fn(array $offers): array =>
                anytour_andromeda_search3_current_mappings($db, $country, $offers),
            static fn(array $legacyIds): array =>
                anytour_andromeda_anytour_offer_canonical_targets($db, $legacyIds),
            static function(array $state, int $created, array $offer, array $current) use (
                $directory,
                $searchRef,
                $generation,
                $nowTs,
                $request
            ): ?array {
                $allows = static function(array $candidate) use ($current): bool {
                    $key = json_encode([
                        $candidate['supplier_namespace'] ?? null,
                        (string)($candidate['external_hotel_id'] ?? ''),
                    ]);
                    return is_int($candidate['local_hotel_id'] ?? null)
                        && ($current[$key] ?? null) === $candidate['local_hotel_id'];
                };
                $context = [
                    'provider' => 'andromeda',
                    'search_ref' => $searchRef,
                    'generation' => $generation,
                    'page' => $state['store']['snapshot']['page'],
                    'offer_ref' => $offer['offer_ref'],
                ];
                $read = anytour_andromeda_read_saved_pricing_with_evidence(
                    $directory,
                    $state['store'],
                    $created,
                    $context,
                    $allows,
                    $nowTs
                );
                $exact = is_array($read) && is_array($read['pricing'] ?? null)
                    ? $read['pricing'] : null;
                $meta = is_array($read) && is_array($read['evidence_meta'] ?? null)
                    ? $read['evidence_meta'] : null;
                return AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
                    $exact,
                    $offer,
                    $request['params'],
                    $directory,
                    $nowTs,
                    $meta,
                    static fn(string $path, array $value): bool =>
                        anytour_andromeda_search3_save($path, $value)
                );
            },
            static fn(string $path, array $value): bool =>
                anytour_andromeda_search3_save($path, $value),
            static function(string $provider, array $search, array $rows, DateTimeImmutable $at) use ($db): array {
                return AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot(
                    $db,
                    $provider,
                    $search,
                    $rows,
                    $at
                );
            }
        );
        if (($result['published'] ?? false) === true) {
            error_log('ANDROMEDA_ANYTOUR_CACHE_AUTOSAVE_OK offers='
                . (int)($result['readyOfferCount'] ?? 0));
        }
        return $result;
    } catch (Throwable $error) {
        error_log('ANDROMEDA_ANYTOUR_CACHE_AUTOSAVE_FAILED '
            . preg_replace('/[^A-Z0-9_:-]+/i', '_', substr($error->getMessage(), 0, 120)));
        return ['published' => false, 'reason' => 'autosave_failed'];
    }
}
