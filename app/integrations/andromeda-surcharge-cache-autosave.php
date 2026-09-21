<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-surcharge-evidence.php';
require_once __DIR__ . '/andromeda-surcharge-evidence-store.php';

/**
 * Supplier-free pricing bridge used by the Andromeda AnyTour autosave path.
 *
 * Exact per-offer pricing is always authoritative. A valid exact estimated fact may
 * seed the existing evidence store with its strict transport group and, only when
 * explicitly classified as one fixed program-level party surcharge, a separate
 * cross-night program scope. Only when exact pricing is absent may cached evidence
 * be rebased to the target offer's own search PRICE.
 *
 * Cached evidence is estimate-only and can never create a verified/final price or
 * selection/booking authority.
 */
final class AnyTourAndromedaSurchargeCacheAutosaveV1
{
    /**
     * @param array<string,mixed>|null $exactPricing Existing exact per-offer pricing envelope.
     * @param array<string,mixed> $offer Normalized Andromeda PRICE offer.
     * @param array<string,mixed> $request Search request or params block used by evidence keys.
     * @param array<string,mixed>|null $seedMeta Original sidecar timing/provenance for optional persistence.
     * @param callable(string,array):bool|null $write Existing atomic private writer.
     * @return array<string,mixed>|null
     */
    public static function resolve(
        ?array $exactPricing,
        array $offer,
        array $request,
        string $directory,
        int $now,
        ?array $seedMeta = null,
        ?callable $write = null
    ): ?array {
        if ($exactPricing !== null) {
            // Never let group evidence override, repair or reinterpret an exact sidecar.
            // Downstream exact-pricing validation remains authoritative/fail-closed.
            if (($exactPricing['state'] ?? null) === 'estimated'
                && is_array($exactPricing['fact'] ?? null)
                && ($exactPricing['verified_quote'] ?? null) === null
                && $seedMeta !== null && $write !== null) {
                self::seedBestEffort(
                    $directory,
                    $offer,
                    $request,
                    $exactPricing['fact'],
                    $seedMeta,
                    $now,
                    $write
                );
            }
            return $exactPricing;
        }

        try {
            if ($now < 1) return null;
            $fact = AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied(
                $directory,
                $offer,
                $request,
                $now
            );
            if ($fact === null || ($fact['final_price_verified'] ?? null) !== false) return null;
            // Make the non-final cache boundary explicit in the returned fact as well.
            $fact = array_replace($fact, ['final_price_verified' => false]);
            return ['state' => 'estimated', 'fact' => $fact, 'verified_quote' => null];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    /** @param callable(string,array):bool $write */
    private static function seedBestEffort(
        string $directory,
        array $offer,
        array $request,
        array $fact,
        array $meta,
        int $now,
        callable $write
    ): void {
        try {
            $sourceSha = $meta['source_sha'] ?? null;
            $searchRef = $meta['source_search_ref'] ?? null;
            $offerRef = $meta['source_offer_ref'] ?? null;
            $observedAt = $meta['observed_at'] ?? null;
            $expiresAt = $meta['expires_at'] ?? null;
            if (!is_string($sourceSha) || preg_match('/^[a-f0-9]{40}$/D', $sourceSha) !== 1
                || !is_string($searchRef) || preg_match('/^[a-f0-9]{64}$/D', $searchRef) !== 1
                || !is_string($offerRef) || preg_match('/^offer_[a-f0-9]{64}$/D', $offerRef) !== 1
                || !is_int($observedAt) || !is_int($expiresAt)
                || $observedAt < 1 || $expiresAt <= $observedAt || $expiresAt > $observedAt + 300
                || $now < $observedAt || $now >= $expiresAt) {
                return;
            }
            $provenance = [
                'source_sha' => $sourceSha,
                'source_search_ref' => $searchRef,
                'source_offer_ref' => $offerRef,
            ];

            // Preserve the established strict cache exactly. It remains the primary
            // read path and still includes nights.
            $strict = AnyTourAndromedaSurchargeEvidenceV1::capture(
                $offer,
                $request,
                $fact,
                $observedAt,
                $expiresAt
            );
            if ($strict === null) return;
            AnyTourAndromedaSurchargeEvidenceStoreV1::save(
                $directory,
                $strict,
                $provenance,
                $write
            );

            // A second entry in the SAME evidence store is permitted only for the
            // explicit fixed-program fact class. Choice-dependent fallback facts do
            // not qualify, so removing nights cannot broaden their applicability.
            $programFixed = AnyTourAndromedaSurchargeEvidenceV1::captureProgramFixed(
                $offer,
                $request,
                $fact,
                $observedAt,
                $expiresAt
            );
            if ($programFixed !== null) {
                AnyTourAndromedaSurchargeEvidenceStoreV1::save(
                    $directory,
                    $programFixed,
                    $provenance,
                    $write
                );
            }
        } catch (Throwable $ignored) {
            // Autosave is best-effort. Cache I/O/provenance conflicts never suppress
            // an otherwise valid exact per-offer pricing result.
        }
    }
}
