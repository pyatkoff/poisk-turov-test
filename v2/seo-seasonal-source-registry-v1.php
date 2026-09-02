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
                'path_prefixes' => ['/veridegerlendirme/', '/eng/monthly-climate.aspx'],
                'allowed_claim_types' => ['climate_temperature', 'climate_precipitation', 'sea_temperature'],
                'review_only' => true,
            ]],
        ],
        8 => [
            'country' => 'Maldives',
            'sources' => [[
                'source_id' => 'mv-mms-climate',
                'source_class' => 'official_meteorological',
                'hosts' => ['www.meteorology.gov.mv', 'meteorology.gov.mv'],
                'path_prefixes' => ['/climate', '/downloads/', '/news'],
                'allowed_claim_types' => ['climate_temperature', 'climate_precipitation', 'daylight'],
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
    if (!is_array($country)) return ['state'=>'blocked','code'=>'unknown_country_source_registry','allowed_hosts'=>[],'path_prefixes'=>[]];

    foreach (($country['sources'] ?? []) as $source) {
        if (($source['source_id'] ?? '') !== $sourceId) continue;
        if (($source['review_only'] ?? false) !== true) return ['state'=>'blocked','code'=>'source_not_review_only','allowed_hosts'=>[],'path_prefixes'=>[]];
        if (!in_array($claimType, $source['allowed_claim_types'] ?? [], true)) return ['state'=>'blocked','code'=>'claim_type_not_allowed_for_source','allowed_hosts'=>[],'path_prefixes'=>[]];
        $paths = array_values(array_filter(array_map(static fn($v)=>trim((string)$v), $source['path_prefixes'] ?? []), static fn($v)=>$v!=='' && str_starts_with($v,'/')));
        if ($paths === []) return ['state'=>'blocked','code'=>'missing_source_path_policy','allowed_hosts'=>[],'path_prefixes'=>[]];
        return [
            'state'=>'review_ready',
            'source_class'=>(string)($source['source_class'] ?? ''),
            'allowed_hosts'=>array_values($source['hosts'] ?? []),
            'path_prefixes'=>$paths,
            'publication_allowed'=>false,
            'copy_allowed_without_evidence'=>false,
        ];
    }
    return ['state'=>'blocked','code'=>'unverified_country_source','allowed_hosts'=>[],'path_prefixes'=>[]];
}
