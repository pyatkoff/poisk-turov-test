<?php
/**
 * Build an explicit review-only seasonal plan from already verified evidence.
 *
 * No automatic candidate selection is performed: a caller must name exact
 * page_keys. The result is planning metadata only and cannot create routes,
 * copy, feeds, sitemap entries or publication candidates.
 */
function v2_seo_seasonal_review_plan(array $coverage, array $binding, array $requestedPageKeys, ?int $nowEpoch = null, int $maxItems = 12): array
{
    $nowEpoch ??= time();
    if ($maxItems < 1 || $maxItems > 50) throw new InvalidArgumentException('Seasonal review plan maxItems must be 1-50');
    if (($coverage['state'] ?? '') !== 'review_ready' || ($coverage['review_ready'] ?? false) !== true) {
        throw new InvalidArgumentException('Seasonal review plan requires review-ready coverage');
    }
    if (($coverage['publication_allowed'] ?? true) !== false || ($coverage['feed_publish_allowed'] ?? true) !== false || ($coverage['copy_allowed'] ?? true) !== false) {
        throw new InvalidArgumentException('Seasonal coverage crossed publication boundary');
    }
    $clock=is_array($coverage['checks']['evidence_clock']??null)?$coverage['checks']['evidence_clock']:[];
    $coverageValidUntil=(int)($clock['valid_until_epoch']??0);
    if (($clock['pass']??false)!==true || $coverageValidUntil<=$nowEpoch) throw new InvalidArgumentException('Seasonal coverage evidence is stale');

    if (($binding['state'] ?? '') !== 'review_only_seasonal_family_binding') throw new InvalidArgumentException('Seasonal review plan requires family binding');
    if (($binding['publication_allowed'] ?? true) !== false || ($binding['copy_allowed'] ?? true) !== false || ($binding['publication_candidates'] ?? null) !== []) {
        throw new InvalidArgumentException('Seasonal family binding crossed publication boundary');
    }
    $bindingValidUntil=(int)($binding['evidence_valid_until_epoch']??0);
    if ($bindingValidUntil<=$nowEpoch) throw new InvalidArgumentException('Seasonal family binding evidence is stale');
    $countryId=(int)($binding['country_id']??0);
    if ($countryId<=0 || (int)($coverage['country_id']??0)!==$countryId) throw new InvalidArgumentException('Seasonal coverage/binding country mismatch');

    if ($requestedPageKeys===[] || count($requestedPageKeys)>$maxItems) throw new InvalidArgumentException('Seasonal review plan requires a small explicit page-key set');
    $requested=[];
    foreach($requestedPageKeys as $value){
        $key=trim((string)$value);
        if($key===''||isset($requested[$key])) throw new InvalidArgumentException('Seasonal review plan page keys must be non-empty and unique');
        $requested[$key]=true;
    }
    $available=[];
    foreach(($binding['bound']??[]) as $row){
        if(!is_array($row))continue;
        $key=trim((string)($row['page_key']??''));
        if($key!==''&&isset($available[$key])) throw new InvalidArgumentException('Seasonal binding contains duplicate page key');
        if($key!=='')$available[$key]=$row;
    }

    $items=[];
    foreach(array_keys($requested) as $key){
        if(!isset($available[$key])) throw new InvalidArgumentException('Requested seasonal identity is not family-bound: '.$key);
        $row=$available[$key];
        $expires=(int)($row['expires_at_epoch']??0);
        if($expires<=$nowEpoch) throw new InvalidArgumentException('Requested seasonal identity expired: '.$key);
        if(($row['publication_allowed']??true)!==false||($row['copy_allowed']??true)!==false) throw new InvalidArgumentException('Requested seasonal identity crossed publication boundary: '.$key);
        $items[]=[
            'state'=>'review_only_seasonal_plan_item',
            'page_key'=>$key,
            'page_type'=>(string)($row['page_type']??''),
            'country_id'=>$countryId,
            'region_id'=>$row['region_id']??null,
            'departure_id'=>(int)($row['departure_id']??0),
            'year'=>(int)($row['year']??0),
            'month'=>(int)($row['month']??0),
            'parent_path'=>(string)($row['parent_path']??''),
            'expires_at_epoch'=>$expires,
            'publication_allowed'=>false,
            'copy_allowed'=>false,
        ];
    }
    usort($items,static fn(array $a,array $b):int=>$a['page_key']<=>$b['page_key']);
    return [
        'state'=>'review_only_seasonal_plan',
        'country_id'=>$countryId,
        'item_count'=>count($items),
        'evidence_valid_until_epoch'=>min($coverageValidUntil,$bindingValidUntil),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'feed_publish_allowed'=>false,
        'copy_allowed'=>false,
        'items'=>$items,
    ];
}
