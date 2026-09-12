<?php
declare(strict_types=1);

/**
 * Provider-neutral P2 semantics for hotel type evidence.
 *
 * Hotel types are a local Search3/catalog facet unless a provider-specific
 * upstream contract is separately verified. Supplier type labels/codes remain
 * bounded observations only: they are not converted into local hotel types,
 * forwarded upstream, or used as hotel-identity evidence.
 */
final class AnyTourThreeProviderHotelTypes
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const MAX_ITEMS = 50;
    private const MAX_ITEM_LENGTH = 64;

    public static function fromEvidence(
        string $provider,
        ?array $rawSupplierTypes,
        ?array $currentLocalTypes,
        bool $currentLocalIdentity
    ): array {
        self::assertProvider($provider);

        $rawProvided = $rawSupplierTypes !== null;
        $rawSupplierTypes = self::normalizeList($rawSupplierTypes, 'supplier hotel type observation');

        if ($currentLocalTypes !== null && !$currentLocalIdentity) {
            throw new InvalidArgumentException('local hotel types require current accepted identity');
        }
        $localProvided = $currentLocalTypes !== null;
        $currentLocalTypes = self::normalizeList($currentLocalTypes, 'local hotel type');
        $canonicalKnown = $currentLocalIdentity && $localProvided;

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'raw_supplier_types' => $rawSupplierTypes,
            'raw_supplier_types_provided' => $rawProvided,
            'canonical_types' => [
                'status' => $canonicalKnown ? 'verified' : 'unknown',
                'values' => $canonicalKnown ? $currentLocalTypes : null,
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
            'supplier_type_equivalence_verified' => false,
            'raw_supplier_numeric_id_universal' => false,
            'cross_provider_equivalence_verified' => false,
            'identity_proof_from_types' => false,
        ];
    }

    private static function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('unknown provider');
        }
    }

    private static function normalizeList(?array $items, string $label): array
    {
        if ($items === null) {
            return [];
        }
        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('too many '.$label.' items');
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException('invalid '.$label);
            }
            $item = trim($item);
            if ($item === '' || strlen($item) > self::MAX_ITEM_LENGTH || preg_match('/[\x00-\x1F\x7F]/', $item)) {
                throw new InvalidArgumentException('invalid '.$label);
            }
            if (!in_array($item, $result, true)) {
                $result[] = $item;
            }
        }
        return $result;
    }
}
