<?php
declare(strict_types=1);

/**
 * Provider-neutral P2 semantics for departure/country filter identity.
 *
 * Local catalog IDs are never supplier IDs. A supplier filter becomes usable
 * only after a provider-specific mapping is verified for the current local
 * departure/country context.
 *
 * Current source proves two distinct provider contracts:
 * - direct ANEX: authoritative local names -> exact unique supplier departure,
 *   then exact unique country dictionary scoped by that supplier departure;
 * - Andromeda: authoritative local departure name -> exact unique saved
 *   TOWNFROM dictionary, while local country is pinned to the installed saved
 *   catalog and its supplier STATEINC.
 */
final class AnyTourThreeProviderDepartureCountry
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const MAX_SUPPLIER_ID_LENGTH = 64;
    private const MAX_NAME_LENGTH = 160;

    public static function fromEvidence(
        string $provider,
        ?array $currentLocal,
        ?array $supplierMapping
    ): array {
        self::assertProvider($provider);
        $local = self::normalizeLocal($currentLocal);
        $mapping = self::normalizeMapping($provider, $supplierMapping, $local !== null);
        $mappingVerified = $mapping !== null;

        $canonical = [
            'departure' => self::localFact($local['departure'] ?? null),
            'country' => self::localFact($local['country'] ?? null),
        ];
        $supplier = [
            'departure' => self::supplierFact($mapping['departure_id'] ?? null, $mappingVerified),
            'country' => self::supplierFact($mapping['country_id'] ?? null, $mappingVerified),
        ];

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'canonical_local' => $canonical,
            'supplier_mapping' => $mapping,
            'filter_semantics' => [
                'departure' => [
                    'status' => $mappingVerified ? 'verified' : 'unknown',
                    'allowed' => $mappingVerified,
                    'supplier_id' => $supplier['departure']['id'],
                ],
                'country' => [
                    'status' => $mappingVerified ? 'verified' : 'unknown',
                    'allowed' => $mappingVerified,
                    'supplier_id' => $supplier['country']['id'],
                ],
            ],
            'provider_specific_mapping_required' => true,
            'local_numeric_id_is_supplier_id' => false,
            'raw_supplier_numeric_id_universal' => false,
            'cross_provider_equivalence_verified' => false,
            'hotel_identity_proof_from_departure_country' => false,
            'current_adapter_audit' => self::adapterAudit($provider),
        ];
    }

    private static function localFact(?array $value): array
    {
        return $value === null
            ? ['status' => 'unknown', 'id' => null, 'name' => null, 'source' => null]
            : ['status' => 'verified', 'id' => $value['id'], 'name' => $value['name'], 'source' => 'current_local_catalog'];
    }

    private static function supplierFact(?string $id, bool $verified): array
    {
        return ['status' => $verified ? 'verified' : 'unknown', 'id' => $verified ? $id : null];
    }

    private static function adapterAudit(string $provider): array
    {
        if ($provider === 'anex') {
            return [
                'departure_mapping' => 'verified_exact_unique_dictionary_name',
                'country_mapping' => 'verified_departure_scoped_exact_unique_dictionary_name',
                'local_form_id_forwarded_as_supplier_id' => false,
            ];
        }
        if ($provider === 'andromeda') {
            return [
                'departure_mapping' => 'verified_exact_unique_saved_dictionary_name',
                'country_mapping' => 'verified_saved_catalog_local_country_pin',
                'local_form_id_forwarded_as_supplier_id' => false,
            ];
        }
        return [
            'departure_mapping' => 'not_verified_in_this_boundary',
            'country_mapping' => 'not_verified_in_this_boundary',
            'local_form_id_forwarded_as_supplier_id' => false,
        ];
    }

    private static function normalizeLocal(?array $local): ?array
    {
        if ($local === null) return null;
        if (array_keys($local) !== ['departure', 'country']) {
            throw new InvalidArgumentException('invalid local departure/country keys');
        }
        $departure = self::normalizeLocalPlace($local['departure'], 'departure');
        $country = self::normalizeLocalPlace($local['country'], 'country');
        return ['departure' => $departure, 'country' => $country];
    }

    private static function normalizeLocalPlace($place, string $label): array
    {
        if (!is_array($place) || array_keys($place) !== ['id', 'name']) {
            throw new InvalidArgumentException('invalid local '.$label);
        }
        if (!is_int($place['id']) || $place['id'] < 1 || $place['id'] > 2147483647) {
            throw new InvalidArgumentException('invalid local '.$label.' id');
        }
        $name = self::requiredString($place['name'], self::MAX_NAME_LENGTH, 'local '.$label.' name');
        return ['id' => $place['id'], 'name' => $name];
    }

    private static function normalizeMapping(string $provider, ?array $mapping, bool $hasLocal): ?array
    {
        if ($mapping === null) return null;
        if (!$hasLocal) throw new InvalidArgumentException('supplier mapping requires current local catalog context');

        if ($provider === 'anex') {
            return self::normalizeAnexMapping($mapping);
        }
        if ($provider === 'andromeda') {
            return self::normalizeAndromedaMapping($mapping);
        }
        throw new InvalidArgumentException('provider mapping contract not verified');
    }

    private static function normalizeAnexMapping(array $mapping): array
    {
        $expected = [
            'departure_id', 'country_id', 'method', 'local_names_authoritative',
            'exact_unique_match', 'country_scoped_by_departure',
        ];
        if (array_keys($mapping) !== $expected) {
            throw new InvalidArgumentException('invalid supplier mapping keys');
        }
        $departureId = self::requiredString($mapping['departure_id'], self::MAX_SUPPLIER_ID_LENGTH, 'supplier departure id');
        $countryId = self::requiredString($mapping['country_id'], self::MAX_SUPPLIER_ID_LENGTH, 'supplier country id');
        if ($mapping['method'] !== 'exact_unique_dictionary_name'
            || $mapping['local_names_authoritative'] !== true
            || $mapping['exact_unique_match'] !== true
            || $mapping['country_scoped_by_departure'] !== true) {
            throw new InvalidArgumentException('unverified supplier mapping');
        }
        return [
            'departure_id' => $departureId,
            'country_id' => $countryId,
            'method' => 'exact_unique_dictionary_name',
            'local_names_authoritative' => true,
            'exact_unique_match' => true,
            'country_scoped_by_departure' => true,
        ];
    }

    private static function normalizeAndromedaMapping(array $mapping): array
    {
        $expected = [
            'departure_id', 'country_id', 'method', 'local_names_authoritative',
            'exact_unique_departure_match', 'country_pinned_to_local_catalog',
        ];
        if (array_keys($mapping) !== $expected) {
            throw new InvalidArgumentException('invalid supplier mapping keys');
        }
        $departureId = self::requiredString($mapping['departure_id'], self::MAX_SUPPLIER_ID_LENGTH, 'supplier departure id');
        $countryId = self::requiredString($mapping['country_id'], self::MAX_SUPPLIER_ID_LENGTH, 'supplier country id');
        if ($mapping['method'] !== 'saved_catalog_country_pin_with_exact_departure_dictionary'
            || $mapping['local_names_authoritative'] !== true
            || $mapping['exact_unique_departure_match'] !== true
            || $mapping['country_pinned_to_local_catalog'] !== true) {
            throw new InvalidArgumentException('unverified supplier mapping');
        }
        return [
            'departure_id' => $departureId,
            'country_id' => $countryId,
            'method' => 'saved_catalog_country_pin_with_exact_departure_dictionary',
            'local_names_authoritative' => true,
            'exact_unique_departure_match' => true,
            'country_pinned_to_local_catalog' => true,
        ];
    }

    private static function requiredString($value, int $maxLength, string $label): string
    {
        if (!is_string($value)) throw new InvalidArgumentException('invalid '.$label);
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('invalid '.$label);
        }
        return $value;
    }

    private static function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) throw new InvalidArgumentException('unknown provider');
    }
}
