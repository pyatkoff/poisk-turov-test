<?php
declare(strict_types=1);

/**
 * Provider-neutral P2 semantics for hotel stars/category evidence.
 *
 * Supplier labels are observations only. They must never be parsed into a
 * canonical local category or used as hotel-identity evidence. A canonical
 * category is accepted only when it is supplied by the caller together with an
 * explicitly current accepted local identity.
 */
final class AnyTourThreeProviderHotelCategory
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];

    public static function fromEvidence(
        string $provider,
        ?string $rawSupplierLabel,
        ?int $currentLocalCategory,
        bool $currentLocalIdentity
    ): array {
        self::assertProvider($provider);
        $rawSupplierLabel = self::normalizeLabel($rawSupplierLabel);

        if ($currentLocalCategory !== null && ($currentLocalCategory < 1 || $currentLocalCategory > 5)) {
            throw new InvalidArgumentException('invalid local hotel category');
        }
        if ($currentLocalCategory !== null && !$currentLocalIdentity) {
            throw new InvalidArgumentException('local category requires current accepted identity');
        }

        $canonicalKnown = $currentLocalIdentity && $currentLocalCategory !== null;

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'raw_supplier_label' => $rawSupplierLabel,
            'raw_supplier_label_observed' => $rawSupplierLabel !== null,
            'canonical_category' => [
                'status' => $canonicalKnown ? 'verified' : 'unknown',
                'value' => $canonicalKnown ? $currentLocalCategory : null,
                'source' => $canonicalKnown ? 'current_local_identity' : null,
            ],
            'current_local_identity' => $currentLocalIdentity,
            'supplier_label_equivalence_verified' => false,
            'raw_supplier_numeric_id_universal' => false,
            'cross_provider_equivalence_verified' => false,
            'identity_proof_from_category' => false,
        ];
    }

    private static function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('unknown provider');
        }
    }

    private static function normalizeLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        if (strlen($label) > 32 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
            throw new InvalidArgumentException('invalid supplier category label');
        }
        return $label;
    }
}
