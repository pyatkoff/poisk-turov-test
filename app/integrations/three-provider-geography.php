<?php
declare(strict_types=1);

/**
 * Provider-neutral P2 semantics for region / resort / subregion evidence.
 *
 * Supplier geography IDs and labels remain provider-scoped observations. They
 * are never translated into local geography or forwarded to a supplier by this
 * generic boundary. A provider-specific dictionary/filter mapping must be
 * verified separately before upstream filtering is allowed.
 */
final class AnyTourThreeProviderGeography
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const LEVELS = ['region', 'resort', 'subregion'];
    private const MAX_ID_LENGTH = 64;
    private const MAX_LABEL_LENGTH = 160;

    public static function fromEvidence(
        string $provider,
        ?array $rawSupplierGeography,
        ?array $currentLocalGeography,
        bool $currentLocalIdentity
    ): array {
        self::assertProvider($provider);

        $rawProvided = $rawSupplierGeography !== null;
        $raw = self::normalizeSupplierGeography($rawSupplierGeography);

        if ($currentLocalGeography !== null && !$currentLocalIdentity) {
            throw new InvalidArgumentException('local geography requires current accepted identity');
        }
        $localProvided = $currentLocalGeography !== null;
        $local = self::normalizeLocalGeography($currentLocalGeography);

        $canonical = [];
        foreach (self::LEVELS as $level) {
            $value = $local[$level] ?? null;
            $known = $currentLocalIdentity && $localProvided && $value !== null;
            $canonical[$level] = [
                'status' => $known ? 'verified' : 'unknown',
                'value' => $known ? $value : null,
                'source' => $known ? 'current_local_identity' : null,
            ];
        }

        $upstream = [];
        foreach (self::LEVELS as $level) {
            $upstream[$level] = [
                'status' => 'unknown',
                'allowed' => false,
                'requires_verified_provider_mapping' => true,
            ];
        }

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'raw_supplier_geography' => $raw,
            'raw_supplier_geography_provided' => $rawProvided,
            'canonical_geography' => $canonical,
            'filter_semantics' => [
                'status' => 'unknown',
                'upstream_search_filter' => $upstream,
            ],
            'current_local_identity' => $currentLocalIdentity,
            'provider_specific_mapping_required' => true,
            'supplier_geography_equivalence_verified' => false,
            'raw_supplier_numeric_id_universal' => false,
            'cross_provider_equivalence_verified' => false,
            'identity_proof_from_geography' => false,
            'current_adapter_audit' => [
                'generic_region_resort_subregion_forwarding' => $provider === 'anex'
                    ? 'not_implemented'
                    : 'not_verified',
            ],
        ];
    }

    private static function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('unknown provider');
        }
    }

    private static function normalizeSupplierGeography(?array $geography): array
    {
        if ($geography === null) {
            return ['region' => null, 'resort' => null, 'subregion' => null];
        }
        self::assertExactLevelKeys($geography, 'supplier geography');

        $result = [];
        foreach (self::LEVELS as $level) {
            $entry = $geography[$level];
            if ($entry === null) {
                $result[$level] = null;
                continue;
            }
            if (!is_array($entry) || array_keys($entry) !== ['id', 'label']) {
                throw new InvalidArgumentException('invalid supplier geography level');
            }
            $id = self::optionalString($entry['id'], self::MAX_ID_LENGTH, 'supplier geography id');
            $label = self::optionalString($entry['label'], self::MAX_LABEL_LENGTH, 'supplier geography label');
            if ($id === null && $label === null) {
                throw new InvalidArgumentException('empty supplier geography level');
            }
            $result[$level] = ['id' => $id, 'label' => $label];
        }
        return $result;
    }

    private static function normalizeLocalGeography(?array $geography): array
    {
        if ($geography === null) {
            return ['region' => null, 'resort' => null, 'subregion' => null];
        }
        self::assertExactLevelKeys($geography, 'local geography');

        $result = [];
        foreach (self::LEVELS as $level) {
            $result[$level] = self::optionalString(
                $geography[$level], self::MAX_LABEL_LENGTH, 'local geography value'
            );
        }
        return $result;
    }

    private static function assertExactLevelKeys(array $value, string $label): void
    {
        if (array_keys($value) !== self::LEVELS) {
            throw new InvalidArgumentException('invalid '.$label.' keys');
        }
    }

    private static function optionalString($value, int $maxLength, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('invalid '.$label);
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('invalid '.$label);
        }
        return $value;
    }
}
