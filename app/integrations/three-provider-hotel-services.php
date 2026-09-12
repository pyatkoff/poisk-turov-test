<?php
declare(strict_types=1);

/**
 * Provider-neutral P2 semantics for hotel service/amenity evidence.
 *
 * Hotel services are a local Search3/catalog facet unless a provider-specific
 * upstream contract is separately verified. Supplier labels/codes are retained
 * only as bounded observations: they are not converted into local services,
 * forwarded upstream, or used as hotel-identity evidence.
 */
final class AnyTourThreeProviderHotelServices
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const MAX_ITEMS = 100;
    private const MAX_ITEM_LENGTH = 64;

    public static function fromEvidence(
        string $provider,
        ?array $rawSupplierServices,
        ?array $currentLocalServices,
        bool $currentLocalIdentity
    ): array {
        self::assertProvider($provider);

        $rawProvided = $rawSupplierServices !== null;
        $rawSupplierServices = self::normalizeList($rawSupplierServices, 'supplier service observation');

        if ($currentLocalServices !== null && !$currentLocalIdentity) {
            throw new InvalidArgumentException('local services require current accepted identity');
        }
        $localProvided = $currentLocalServices !== null;
        $currentLocalServices = self::normalizeList($currentLocalServices, 'local hotel service');
        $canonicalKnown = $currentLocalIdentity && $localProvided;

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'raw_supplier_services' => $rawSupplierServices,
            'raw_supplier_services_provided' => $rawProvided,
            'canonical_services' => [
                'status' => $canonicalKnown ? 'verified' : 'unknown',
                'values' => $canonicalKnown ? $currentLocalServices : null,
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
            'supplier_service_equivalence_verified' => false,
            'raw_supplier_numeric_id_universal' => false,
            'cross_provider_equivalence_verified' => false,
            'identity_proof_from_services' => false,
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
            $result[$item] = true;
        }
        return array_keys($result);
    }
}
