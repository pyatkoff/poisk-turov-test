<?php
/**
 * Normalize several explicit seasonal review plans into one controlled dataset.
 * This remains planning metadata only: no routes, copy, feeds or publication.
 */
function v2_seo_seasonal_review_dataset(array $plans, array $requiredFamilies, ?int $nowEpoch = null, int $maxItems = 12): array
{
    $nowEpoch ??= time();
    if ($maxItems < 1 || $maxItems > 50) throw new InvalidArgumentException('Seasonal review dataset maxItems must be 1-50');
    $required=[];
    foreach($requiredFamilies as $value){
        $family=trim(strtolower((string)$value));
        if($family===''||isset($required[$family])) throw new InvalidArgumentException('Seasonal dataset families must be non-empty and unique');
        $required[$family]=true;
    }
    if($required===[]||$plans===[]) throw new InvalidArgumentException('Seasonal review dataset requires explicit families and plans');

    $seenFamilies=[];$seenCountries=[];$seenKeys=[];$items=[];$validUntil=[];
    foreach($plans as $plan){
        if(!is_array($plan)||($plan['state']??'')!=='review_only_seasonal_plan') throw new InvalidArgumentException('Seasonal dataset requires review-only plans');
        if(($plan['publication_allowed']??true)!==false||($plan['feed_publish_allowed']??true)!==false||($plan['copy_allowed']??true)!==false||($plan['publication_candidates']??null)!==[]) {
            throw new InvalidArgumentException('Seasonal plan crossed publication boundary');
        }
        $family=trim(strtolower((string)($plan['family']??'')));
        $countryId=(int)($plan['country_id']??0);
        $expires=(int)($plan['evidence_valid_until_epoch']??0);
        if($family===''||!isset($required[$family])||isset($seenFamilies[$family])) throw new InvalidArgumentException('Seasonal dataset family mismatch or duplicate');
        if($countryId<=0||isset($seenCountries[$countryId])) throw new InvalidArgumentException('Seasonal dataset country identity mismatch or duplicate');
        if($expires<=$nowEpoch) throw new InvalidArgumentException('Seasonal dataset plan evidence is stale');
        $rows=is_array($plan['items']??null)?$plan['items']:[];
        if($rows===[]||(int)($plan['item_count']??0)!==count($rows)) throw new InvalidArgumentException('Seasonal dataset plan item count is invalid');
        $seenFamilies[$family]=true;$seenCountries[$countryId]=true;$validUntil[]=$expires;
        foreach($rows as $row){
            if(!is_array($row)||($row['state']??'')!=='review_only_seasonal_plan_item') throw new InvalidArgumentException('Seasonal dataset item state is invalid');
            $key=trim((string)($row['page_key']??''));
            $pageType=(string)($row['page_type']??'');
            $parent=trim((string)($row['parent_path']??''));
            $itemExpires=(int)($row['expires_at_epoch']??0);
            $checkedAt=(int)($row['evidence_checked_at_epoch']??0);
            if($key===''||isset($seenKeys[$key])) throw new InvalidArgumentException('Seasonal dataset page key is empty or duplicate');
            if(!in_array($pageType,['month','resort_month'],true)) throw new InvalidArgumentException('Seasonal dataset page type is invalid');
            if((int)($row['country_id']??0)!==$countryId||$parent==='') throw new InvalidArgumentException('Seasonal dataset item identity is invalid');
            if($itemExpires<=$nowEpoch||$checkedAt<=0||$checkedAt>$nowEpoch+5) throw new InvalidArgumentException('Seasonal dataset item evidence is stale');
            if(($row['publication_allowed']??true)!==false||($row['copy_allowed']??true)!==false) throw new InvalidArgumentException('Seasonal dataset item crossed publication boundary');
            $seenKeys[$key]=true;$validUntil[]=$itemExpires;
            $items[]=[
                'state'=>'review_only_seasonal_dataset_item','family'=>$family,'page_key'=>$key,'page_type'=>$pageType,
                'country_id'=>$countryId,'region_id'=>$row['region_id']??null,'departure_id'=>(int)($row['departure_id']??0),
                'year'=>(int)($row['year']??0),'month'=>(int)($row['month']??0),'parent_path'=>$parent,
                'evidence_checked_at_epoch'=>$checkedAt,'expires_at_epoch'=>$itemExpires,
                'publication_allowed'=>false,'copy_allowed'=>false,
            ];
        }
    }
    if(array_diff_key($required,$seenFamilies)!==[]||array_diff_key($seenFamilies,$required)!==[]) throw new InvalidArgumentException('Seasonal dataset does not cover every required family exactly once');
    if(count($items)>$maxItems) throw new InvalidArgumentException('Seasonal review dataset exceeds controlled item limit');
    usort($items,static fn(array $a,array $b):int=>[$a['family'],$a['page_key']]<=>[$b['family'],$b['page_key']]);
    return [
        'state'=>'review_only_seasonal_dataset','family_count'=>count($seenFamilies),'item_count'=>count($items),
        'evidence_valid_until_epoch'=>min($validUntil),'publication_candidates'=>[],
        'publication_allowed'=>false,'feed_publish_allowed'=>false,'copy_allowed'=>false,'items'=>$items,
    ];
}
