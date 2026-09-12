<?php
declare(strict_types=1);

/**
 * Provider-neutral P2 semantics for customer/hotel rating evidence.
 *
 * Rating is a local Search3/catalog facet unless a provider-specific upstream
 * contract is separately verified. Supplier values are retained only as raw
 * observations: they are not converted into the local 0..5 scale, sent back to
 * a supplier, or used as hotel-identity evidence.
 */
final class AnyTourThreeProviderHotelRating
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];

    public static function fromEvidence(
        string $provider,
        ?string $rawSupplierRating,
        ?float $currentLocalRating,
        bool $currentLocalIdentity
    ): array {
        self::assertProvider($provider);
        $rawSupplierRating = self::normalizeObservation($rawSupplierRating);

        if ($currentLocalRating !== null) {
            if (!is_finite($currentLocalRating) || $currentLocalRating <= 0.0 || $currentLocalRating > 5.0) {
                throw new InvalidArgumentException('invalid local hotel rating');
            }
            if (!$currentLocalIdentity) {
                throw new InvalidArgumentException('local rating requires current accepted identity');
            }
        }

        $canonicalKnown = $currentLocalIdentity && $currentLocalRating !== null;

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'raw_supplier_rating' => $rawSupplierRating,
            'raw_supplier_rating_observed' => $rawSupplierRating !== null,
            'canonical_rating' => [
                'status' => $canonicalKnown ? 'verified' : 'unknown',
                'value' => $canonicalKnown ? $currentLocalRating : null,
                'source' => $canonicalKnown ? 'current_local_identity' : null,
            ],
            'filter_semantics' => [
                'status' => 'local_only',
                'upstream_search_filter' => [
                    'status' => 'local_only',
                    'allowed' => false,
                ],
            ],
            'current_local_identity' => $currentLocalIdentity,
            'supplier_rating_equivalence_verified' => false,
            'raw_supplier_numeric_value_universal' => false,
            'cross_provider_equivalence_verified' => false,
            'identity_proof_from_rating' => false,
        ];
    }

    private static function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('unknown provider');
        }
    }

    private static function normalizeObservation(?string $rating): ?string
    {
        if ($rating === null) {
            return null;
        }
        $rating = trim($rating);
        if ($rating === '') {
            return null;
        }
        if (strlen($rating) > 32 || preg_match('/[\x00-\x1F\x7F]/', $rating)) {
            throw new InvalidArgumentException('invalid supplier rating observation');
        }
        return $rating;
    }
}
