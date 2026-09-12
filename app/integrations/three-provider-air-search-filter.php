<?php
declare(strict_types=1);

/**
 * Provider-neutral P2 semantics for air-related search/catalog filters.
 *
 * Important boundary: a provider exposing a catalog/discovery capability does not
 * prove that the same field may be forwarded into its tour-search request. This
 * class deliberately keeps those two facts separate and fails closed for upstream
 * search filtering until a provider-specific contract is verified.
 */
final class AnyTourThreeProviderAirSearchFilter
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const FAMILIES = ['arrival_airport', 'direct_flight', 'charter'];
    private const STATUSES = ['verified', 'local_only', 'unsupported', 'unknown'];

    public static function matrix(): array
    {
        $rows = [];
        foreach (self::PROVIDERS as $provider) {
            foreach (self::FAMILIES as $family) {
                $rows[] = self::fact($provider, $family);
            }
        }

        return [
            'schema_version' => 1,
            'statuses' => self::STATUSES,
            'rows' => $rows,
        ];
    }

    public static function forProvider(string $provider): array
    {
        self::assertProvider($provider);
        $rows = [];
        foreach (self::FAMILIES as $family) {
            $rows[$family] = self::fact($provider, $family);
        }
        return $rows;
    }

    public static function fact(string $provider, string $family): array
    {
        self::assertProvider($provider);
        self::assertFamily($family);

        $catalogStatus = 'unknown';
        $catalogEvidence = null;
        if ($provider === 'tourvisor') {
            $catalogStatus = 'verified';
            if ($family === 'arrival_airport') {
                $catalogEvidence = 'existing_tourvisor_arrivals_catalog';
            } elseif ($family === 'direct_flight') {
                $catalogEvidence = 'existing_tourvisor_catalog_onlyDirect';
            } else {
                $catalogEvidence = 'existing_tourvisor_catalog_onlyCharter';
            }
        }

        return [
            'provider' => $provider,
            'family' => $family,
            'catalog_discovery' => [
                'status' => $catalogStatus,
                'evidence' => $catalogEvidence,
                'scope' => $catalogStatus === 'verified' ? 'catalog_only' : 'not_verified',
            ],
            'upstream_search_filter' => [
                'status' => 'unknown',
                'allowed' => false,
                'evidence' => null,
            ],
            'raw_numeric_id_universal' => false,
            'cross_provider_equivalence_verified' => false,
        ];
    }

    public static function allowsUpstreamSearchFilter(string $provider, string $family): bool
    {
        $fact = self::fact($provider, $family);
        return $fact['upstream_search_filter']['status'] === 'verified'
            && $fact['upstream_search_filter']['allowed'] === true;
    }

    private static function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('unknown provider');
        }
    }

    private static function assertFamily(string $family): void
    {
        if (!in_array($family, self::FAMILIES, true)) {
            throw new InvalidArgumentException('unknown air filter family');
        }
    }
}
