<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-surcharge-cache-autosave.php';

/**
 * Compose exact retained Andromeda pricing with the strict transport-group cache.
 *
 * Exact pricing is always returned unchanged. Only a live, already-validated exact
 * estimated sidecar may seed the cache, and only after its immutable timing and
 * provenance are re-read from that same sidecar. When exact pricing is absent,
 * the cache bridge may return an estimate-only rebased fact.
 *
 * This helper is supplier-free. Cache failures are best-effort and never suppress
 * a valid exact result or become selection/booking authority.
 */
function anytour_andromeda_pricing_with_surcharge_cache(
    ?array $exactPricing,
    string $directory,
    int $created,
    array $context,
    array $offer,
    array $request,
    int $now,
    callable $write
): ?array {
    try {
        $seed = null;
        if ($exactPricing !== null
            && ($exactPricing['state'] ?? null) === 'estimated'
            && is_array($exactPricing['fact'] ?? null)
            && array_key_exists('verified_quote', $exactPricing)
            && $exactPricing['verified_quote'] === null) {
            $seed = anytour_andromeda_surcharge_cache_seed_meta(
                $exactPricing,
                $directory,
                $created,
                $context,
                $now
            );
        }

        return AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
            $exactPricing,
            $offer,
            $request,
            $directory,
            $now,
            $seed,
            $seed === null ? null : $write
        );
    } catch (Throwable $ignored) {
        return $exactPricing;
    }
}

/**
 * Re-read only the allowlisted seed metadata from the exact per-offer sidecar.
 * The exact reader has already established pricing authority; this function may
 * only decide whether that exact estimate is safe to share inside its strict group.
 *
 * @return array<string,mixed>|null
 */
function anytour_andromeda_surcharge_cache_seed_meta(
    array $exactPricing,
    string $directory,
    int $created,
    array $context,
    int $now
): ?array {
    try {
        $ref = $context['search_ref'] ?? null;
        $page = $context['page'] ?? null;
        $offerRef = $context['offer_ref'] ?? null;
        $generation = $context['generation'] ?? null;
        if (($context['provider'] ?? null) !== 'andromeda'
            || !is_string($ref) || preg_match('/^[a-f0-9]{64}$/D', $ref) !== 1
            || !is_int($page) || $page < 1
            || !is_string($offerRef) || preg_match('/^offer_[a-f0-9]{64}$/D', $offerRef) !== 1
            || !is_int($generation) || $generation < 1
            || $created < 1 || $now < 1
            || !is_dir($directory) || is_link($directory)
            || basename(rtrim($directory, '/')) !== 'searches') {
            return null;
        }

        $runtime = __DIR__ . '/andromeda-saved-package-runtime.php';
        if (!is_file($runtime) || is_link($runtime)) return null;
        $implementation = hash_file('sha256', $runtime);
        if (!is_string($implementation) || preg_match('/^[a-f0-9]{64}$/D', $implementation) !== 1) return null;

        $path = rtrim($directory, '/') . '/' . $ref . '-' . $created . '-' . $page
            . '-' . $offerRef . '-surcharge-v1.json';
        if (is_link($path) || !is_file($path)) return null;
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > 16384) return null;
        $record = json_decode((string)file_get_contents($path), true, 20, JSON_THROW_ON_ERROR);
        if (!is_array($record)
            || ($record['version'] ?? null) !== 1
            || ($record['status'] ?? null) !== 'complete'
            || ($record['implementation_sha256'] ?? null) !== $implementation
            || ($record['context'] ?? null) !== $context
            || ($record['snapshot_created_at'] ?? null) !== $created
            || ($record['fact'] ?? null) !== ($exactPricing['fact'] ?? null)) {
            return null;
        }

        $source = $record['source'] ?? null;
        $observedAt = $record['observed_at'] ?? null;
        $expiresAt = $record['expires_at'] ?? null;
        if (!is_string($source) || preg_match('/^[a-f0-9]{40}$/D', $source) !== 1
            || !is_int($observedAt) || !is_int($expiresAt)
            || $observedAt < 1 || $expiresAt <= $observedAt
            || $expiresAt > $observedAt + 300
            || $now < $observedAt || $now >= $expiresAt) {
            return null;
        }

        return [
            'source_sha' => $source,
            'source_search_ref' => $ref,
            'source_offer_ref' => $offerRef,
            'observed_at' => $observedAt,
            'expires_at' => $expiresAt,
        ];
    } catch (Throwable $ignored) {
        return null;
    }
}
