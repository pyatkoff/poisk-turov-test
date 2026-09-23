<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-surcharge-evidence.php';
require_once __DIR__ . '/andromeda-surcharge-evidence-store.php';
require_once __DIR__ . '/operator-program-fuel-registry.php';
require_once __DIR__ . '/operator-program-fuel-fx-evidence.php';
require_once __DIR__ . '/operator-fuel-rule-store.php';

/**
 * Supplier-free pricing bridge used by the Andromeda AnyTour autosave path.
 *
 * Exact per-offer pricing is always authoritative. A valid exact estimated fact may
 * seed the strict transport-group cache when the caller supplies the original
 * bounded timing/provenance. Only when exact pricing is absent may a cached group
 * surcharge be rebased to the target offer's own search PRICE.
 *
 * Cached evidence is estimate-only and can never create a verified/final price or
 * selection/booking authority.
 */
final class AnyTourAndromedaSurchargeCacheAutosaveV1
{
    /**
     * @param array<string,mixed>|null $exactPricing Existing exact per-offer pricing envelope.
     * @param array<string,mixed> $offer Normalized Andromeda PRICE offer.
     * @param array<string,mixed> $request Search request or params block used by the strict group key.
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
            if ($fact !== null) {
                if (($fact['final_price_verified'] ?? null) !== false) return null;
                // Exact strict transport-group reuse remains more specific than program reuse.
                $fact = array_replace($fact, ['final_price_verified' => false]);
                return ['state' => 'estimated', 'fact' => $fact, 'verified_quote' => null];
            }

            $party = self::partyFromRequest($request);
            $program = AnyTourOperatorProgramFuelRegistryV1::priceForOffer(
                $directory, $offer, $party, $now
            );
            if ($program !== null) {
                return ['state'=>'program_fuel','program_fuel'=>$program];
            }

            // Broad operator+direction evidence is the final listing fallback. Reuse
            // only a fresh, already-retained supplier FX receipt; this does not infer
            // a fuel rate or create direction evidence. Missing/stale/ambiguous FX
            // still fails closed for non-RUB rules and the owner-policy fallback.
            $offerRef = $offer['offer_ref'] ?? null;
            $operator = $offer['operator'] ?? null;
            if (is_string($offerRef) && preg_match('/^offer_[a-f0-9]{64}$/D', $offerRef) === 1
                && is_string($operator)) {
                $targetDirection = AnyTourOperatorFuelRuleEvidenceV1::directionFromSearch(
                    $operator,
                    $request
                );
                $exchange = AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection(
                    $directory,
                    $operator,
                    $targetDirection,
                    $now
                );
                $direction = AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget(
                    $directory,
                    [
                        'provider'=>'andromeda',
                        'operator'=>$operator,
                        'search_params'=>$request,
                        'party'=>$party,
                        'offer_ref_digest'=>hash('sha256', $offerRef),
                    ],
                    $now,
                    $exchange
                );
                if ($direction !== null) return $direction;
            }
            return null;
        } catch (Throwable $ignored) {
            return null;
        }
    }

    private static function partyFromRequest(array $request): array
    {
        $adults=$request['adults']??null;
        $ages=$request['childs']??[];
        if(is_string($adults)&&preg_match('/^[1-9]$/D',$adults)===1)$adults=(int)$adults;
        if(!is_int($adults)||$adults<1||$adults>9||!is_array($ages)||!array_is_list($ages)||count($ages)>9){
            throw new InvalidArgumentException('ANDROMEDA_PROGRAM_FUEL_PARTY');
        }
        $out=[];
        foreach($ages as $age){
            if(is_string($age)&&preg_match('/^(?:0|[1-9][0-9]?)$/D',$age)===1)$age=(int)$age;
            if(!is_int($age)||$age<0||$age>17)throw new InvalidArgumentException('ANDROMEDA_PROGRAM_FUEL_PARTY');
            $out[]=$age;
        }
        sort($out,SORT_NUMERIC);
        return ['adults'=>$adults,'children'=>count($out),'child_ages'=>$out];
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
            $evidence = AnyTourAndromedaSurchargeEvidenceV1::capture(
                $offer,
                $request,
                $fact,
                $observedAt,
                $expiresAt
            );
            if ($evidence === null) return;
            AnyTourAndromedaSurchargeEvidenceStoreV1::save(
                $directory,
                $evidence,
                [
                    'source_sha' => $sourceSha,
                    'source_search_ref' => $searchRef,
                    'source_offer_ref' => $offerRef,
                ],
                $write
            );
        } catch (Throwable $ignored) {
            // Autosave is best-effort. Cache I/O/provenance conflicts never suppress
            // an otherwise valid exact per-offer pricing result.
        }
    }
}
