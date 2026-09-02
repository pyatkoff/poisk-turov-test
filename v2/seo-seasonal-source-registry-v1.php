<?php
declare(strict_types=1);

/** Review-only allowlist for seasonal editorial evidence. */
function v2_seo_seasonal_source_registry(): array
{
    return [
        4 => [
            'country' => 'Turkey',
            'sources' => [[
                'source_id' => 'tr-mgm-climate',
                'source_class' => 'official_meteorological',
                'hosts' => ['www.mgm.gov.tr', 'mgm.gov.tr'],
                'path_prefixes' => ['/veridegerlendirme/il-ve-ilceler-istatistik.aspx'],
                'allowed_claim_types' => ['climate_temperature', 'climate_precipitation', 'sea_temperature'],
                // Only Antalya has been explicitly verified for this first source slice.
                // This prevents Antalya normals from being reused as Turkey-wide facts.
                'verified_geographies' => [[
                    'level' => 'resort',
                    'country_id' => 4,
                    'region_id' => 20,
                    'required_query' => ['m' => 'ANTALYA'],
                ]],
                'review_only' => true,
            ]],
        ],
        8 => [
            'country' => 'Maldives',
            'sources' => [[
                'source_id' => 'mv-mms-climate',
                'source_class' => 'official_meteorological',
                'hosts' => ['www.meteorology.gov.mv', 'meteorology.gov.mv'],
                'path_prefixes' => ['/climate', '/downloads/'],
                'allowed_claim_types' => ['climate_temperature', 'climate_precipitation', 'daylight'],
                // The MMS climate/outlook source is accepted only for country-level
                // Maldives claims until a resort/atoll source is separately verified.
                'verified_geographies' => [[
                    'level' => 'country',
                    'country_id' => 8,
                ]],
                'review_only' => true,
            ]],
        ],
        1 => [
            'country' => 'Egypt',
            // Fail closed until a suitable official HTTPS climate-normal/month
            // source is verified for the first seasonal content prototype.
            'sources' => [],
        ],
    ];
}

function v2_seo_seasonal_source_policy(int $countryId, string $sourceId, string $claimType): array
{
    $registry = v2_seo_seasonal_source_registry();
    $country = $registry[$countryId] ?? null;
    if (!is_array($country)) return ['state'=>'blocked','code'=>'unknown_country_source_registry','allowed_hosts'=>[],'path_prefixes'=>[],'verified_geographies'=>[]];

    foreach (($country['sources'] ?? []) as $source) {
        if (($source['source_id'] ?? '') !== $sourceId) continue;
        if (($source['review_only'] ?? false) !== true) return ['state'=>'blocked','code'=>'source_not_review_only','allowed_hosts'=>[],'path_prefixes'=>[],'verified_geographies'=>[]];
        if (!in_array($claimType, $source['allowed_claim_types'] ?? [], true)) return ['state'=>'blocked','code'=>'claim_type_not_allowed_for_source','allowed_hosts'=>[],'path_prefixes'=>[],'verified_geographies'=>[]];
        $paths = array_values(array_filter(array_map(static fn($v)=>trim((string)$v), $source['path_prefixes'] ?? []), static fn($v)=>$v!=='' && str_starts_with($v,'/')));
        $geographies = array_values(array_filter($source['verified_geographies'] ?? [], static fn($v)=>is_array($v)));
        if ($paths === []) return ['state'=>'blocked','code'=>'missing_source_path_policy','allowed_hosts'=>[],'path_prefixes'=>[],'verified_geographies'=>[]];
        if ($geographies === []) return ['state'=>'blocked','code'=>'missing_source_geography_policy','allowed_hosts'=>[],'path_prefixes'=>[],'verified_geographies'=>[]];
        return [
            'state'=>'review_ready',
            'source_class'=>(string)($source['source_class'] ?? ''),
            'allowed_hosts'=>array_values($source['hosts'] ?? []),
            'path_prefixes'=>$paths,
            'verified_geographies'=>$geographies,
            'publication_allowed'=>false,
            'copy_allowed_without_evidence'=>false,
        ];
    }
    return ['state'=>'blocked','code'=>'unverified_country_source','allowed_hosts'=>[],'path_prefixes'=>[],'verified_geographies'=>[]];
}
