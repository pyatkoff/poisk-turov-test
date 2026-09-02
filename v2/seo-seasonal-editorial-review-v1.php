<?php
declare(strict_types=1);

/**
 * Bind verified seasonal editorial evidence to explicit fresh review-dataset items.
 * This produces evidence metadata only: no copy, routes, sitemap entries or publication.
 */
function v2_seo_seasonal_editorial_review_bundle(array $dataset, array $verifiedEvidence, array $pageKeys, ?int $nowEpoch = null, int $maxItems = 6): array
{
    $nowEpoch ??= time();
    if ($maxItems < 1 || $maxItems > 12) throw new InvalidArgumentException('Seasonal editorial review maxItems must be 1-12');
    if (($dataset['state'] ?? '') !== 'review_only_seasonal_dataset') throw new InvalidArgumentException('Seasonal editorial review requires review-only dataset');
    if (($dataset['publication_allowed'] ?? true) !== false || ($dataset['feed_publish_allowed'] ?? true) !== false || ($dataset['copy_allowed'] ?? true) !== false || ($dataset['publication_candidates'] ?? null) !== []) {
        throw new InvalidArgumentException('Seasonal editorial dataset crossed publication boundary');
    }
    $datasetExpires=(int)($dataset['evidence_valid_until_epoch'] ?? 0);
    if ($datasetExpires <= $nowEpoch) throw new InvalidArgumentException('Seasonal editorial dataset evidence is stale');

    if (($verifiedEvidence['state'] ?? '') !== 'review_ready' || ($verifiedEvidence['review_ready'] ?? false) !== true) {
        throw new InvalidArgumentException('Seasonal editorial review requires verified review-ready evidence');
    }
    if (($verifiedEvidence['publication_allowed'] ?? true) !== false || ($verifiedEvidence['copy_allowed_without_evidence'] ?? true) !== false || ($verifiedEvidence['hotel_tours_publication_allowed'] ?? true) !== false) {
        throw new InvalidArgumentException('Seasonal editorial evidence crossed publication boundary');
    }

    $requested=[];
    foreach ($pageKeys as $value) {
        $key=trim((string)$value);
        if ($key==='' || isset($requested[$key])) throw new InvalidArgumentException('Seasonal editorial page keys must be non-empty and unique');
        $requested[$key]=true;
    }
    if ($requested===[] || count($requested)>$maxItems) throw new InvalidArgumentException('Seasonal editorial review requires a controlled explicit page slice');

    $datasetByKey=[];
    foreach (($dataset['items'] ?? []) as $item) {
        if (!is_array($item) || ($item['state'] ?? '') !== 'review_only_seasonal_dataset_item') continue;
        $key=trim((string)($item['page_key'] ?? ''));
        if ($key!=='' && isset($requested[$key])) $datasetByKey[$key]=$item;
    }
    if (count($datasetByKey)!==count($requested)) throw new InvalidArgumentException('Seasonal editorial page key is absent from fresh review dataset');

    $claimsByKey=[];
    foreach (($verifiedEvidence['claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        $key=trim((string)($claim['page_key'] ?? ''));
        if (!isset($requested[$key])) continue;
        $scope=is_array($claim['geography_scope'] ?? null)?$claim['geography_scope']:[];
        if ($scope===[]) throw new InvalidArgumentException('Seasonal editorial claim lacks verified geography scope');
        $item=$datasetByKey[$key];
        $countryId=(int)($item['country_id'] ?? 0);
        $pageType=(string)($item['page_type'] ?? '');
        if ((int)($scope['country_id'] ?? 0)!==$countryId) throw new InvalidArgumentException('Seasonal editorial claim country scope mismatch');
        if ($pageType==='month') {
            if (($scope['level'] ?? '')!=='country' || ($scope['region_id'] ?? null)!==null) throw new InvalidArgumentException('Seasonal country-month claim geography mismatch');
        } elseif ($pageType==='resort_month') {
            if (($scope['level'] ?? '')!=='resort' || (int)($scope['region_id'] ?? 0)!==(int)($item['region_id'] ?? 0)) throw new InvalidArgumentException('Seasonal resort-month claim geography mismatch');
        } else throw new InvalidArgumentException('Seasonal editorial dataset page type is invalid');
        $claimsByKey[$key][]=$claim;
    }

    $items=[];
    foreach (array_keys($requested) as $key) {
        $claims=$claimsByKey[$key] ?? [];
        if ($claims===[]) throw new InvalidArgumentException('Seasonal editorial page has no verified claims');
        $item=$datasetByKey[$key];
        $itemExpires=(int)($item['expires_at_epoch'] ?? 0);
        if ($itemExpires <= $nowEpoch) throw new InvalidArgumentException('Seasonal editorial item evidence is stale');
        $items[]=[
            'state'=>'review_only_seasonal_editorial_item',
            'page_key'=>$key,
            'page_type'=>(string)$item['page_type'],
            'country_id'=>(int)$item['country_id'],
            'region_id'=>$item['region_id'] ?? null,
            'parent_path'=>(string)($item['parent_path'] ?? ''),
            'claims'=>$claims,
            'claim_count'=>count($claims),
            'evidence_valid_until_epoch'=>min($datasetExpires,$itemExpires),
            'publication_allowed'=>false,
            'indexation_allowed'=>false,
            'sitemap_allowed'=>false,
            'copy_generation_allowed'=>false,
        ];
    }
    usort($items,static fn(array $a,array $b):int=>$a['page_key']<=>$b['page_key']);
    return [
        'state'=>'review_only_seasonal_editorial_bundle',
        'item_count'=>count($items),
        'evidence_valid_until_epoch'=>min(array_map(static fn(array $v):int=>(int)$v['evidence_valid_until_epoch'],$items)),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'copy_generation_allowed'=>false,
        'items'=>$items,
    ];
}
