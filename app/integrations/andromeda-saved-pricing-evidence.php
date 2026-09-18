<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-saved-package-runtime.php';

/**
 * Server-only autosave reader which preserves the existing pricing envelope and, for
 * a validated estimated sidecar only, exposes the original bounded cache provenance.
 * No raw claim, supplier payload or private session data is returned.
 */
function anytour_andromeda_read_saved_pricing_with_evidence(
    string $directory,
    array $storeState,
    int $created,
    array $context,
    callable $mappingAllows,
    int $now
): ?array {
    $pricing = anytour_andromeda_read_saved_pricing(
        $directory,
        $storeState,
        $created,
        $context,
        $mappingAllows,
        $now
    );
    if ($pricing === null) return null;
    if (($pricing['state'] ?? null) !== 'estimated') {
        return ['pricing' => $pricing, 'evidence_meta' => null];
    }
    try {
        $ref = $context['search_ref'] ?? null;
        $page = $context['page'] ?? null;
        $offerRef = $context['offer_ref'] ?? null;
        if (!is_string($ref) || preg_match('/^[a-f0-9]{64}$/D', $ref) !== 1
            || !is_int($page) || $page < 1
            || !is_string($offerRef) || preg_match('/^offer_[a-f0-9]{64}$/D', $offerRef) !== 1
            || !is_dir($directory) || is_link($directory) || basename($directory) !== 'searches'
            || $created < 1 || $now < 1) {
            return ['pricing' => $pricing, 'evidence_meta' => null];
        }

        // The durable surcharge sidecar stores the resolver's canonical context,
        // which deliberately includes hotel_scope/operator_ref/local_id in addition
        // to the caller's minimal immutable selection tuple. Re-resolve it with the
        // same retained store and CURRENT mapping predicate instead of comparing the
        // sidecar to the smaller caller context.
        $store = new AnyTourAndromedaOfferStore($storeState, true);
        $resolved = AnyTourAndromedaSelectedOffer::resolve($store, $context, $mappingAllows, $now);
        $canonicalContext = $resolved['context'] ?? null;
        if (!is_array($canonicalContext)) {
            return ['pricing' => $pricing, 'evidence_meta' => null];
        }

        $path = $directory . '/' . $ref . '-' . $created . '-' . $page
            . '-' . $offerRef . '-surcharge-v1.json';
        if (is_link($path) || !is_file($path)) {
            return ['pricing' => $pricing, 'evidence_meta' => null];
        }
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > 16384) {
            return ['pricing' => $pricing, 'evidence_meta' => null];
        }
        $record = json_decode((string)file_get_contents($path), true, 20, JSON_THROW_ON_ERROR);
        if (!is_array($record)
            || ($record['status'] ?? null) !== 'complete'
            || ($record['context'] ?? null) !== $canonicalContext
            || ($record['snapshot_created_at'] ?? null) !== $created
            || ($record['fact'] ?? null) !== ($pricing['fact'] ?? null)
            || !is_string($record['source'] ?? null)
            || preg_match('/^[a-f0-9]{40}$/D', $record['source']) !== 1
            || !is_int($record['observed_at'] ?? null)
            || !is_int($record['expires_at'] ?? null)
            || $record['observed_at'] < 1
            || $record['expires_at'] <= $record['observed_at']
            || $record['expires_at'] > $record['observed_at'] + 300
            || $now < $record['observed_at']
            || $now >= $record['expires_at']) {
            return ['pricing' => $pricing, 'evidence_meta' => null];
        }
        return [
            'pricing' => $pricing,
            'evidence_meta' => [
                'source_sha' => $record['source'],
                'source_search_ref' => $ref,
                'source_offer_ref' => $offerRef,
                'observed_at' => $record['observed_at'],
                'expires_at' => $record['expires_at'],
            ],
        ];
    } catch (Throwable $ignored) {
        // Exact pricing remains authoritative even when cache metadata cannot be exposed.
        return ['pricing' => $pricing, 'evidence_meta' => null];
    }
}
