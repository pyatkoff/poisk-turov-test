<?php
/** Normalize exact fresh month/resort-month identities for review-only planning. */
function v2_seo_seasonal_identity_inventory(array $rows): array
{
    $items = [];
    $blocked = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) { $blocked[]=['index'=>$index,'errors'=>['invalid_row']]; continue; }
        $errors=[];
        $pageType=(string)($row['page_type']??'');
        $countryId=(int)($row['country_id']??0);
        $regionId=isset($row['region_id'])?(int)$row['region_id']:null;
        $departureId=(int)($row['departure_id']??0);
        $year=(int)($row['departure_year']??0);
        $month=(int)($row['departure_month']??0);
        $offerCount=(int)($row['offer_count']??0);
        $freshness=(int)($row['freshness_seconds']??0);
        if (!in_array($pageType,['month','resort_month'],true)) $errors[]='invalid_page_type';
        if ($countryId<=0) $errors[]='invalid_country_identity';
        if ($departureId<=0) $errors[]='invalid_departure_identity';
        if ($year<2020||$year>2100||$month<1||$month>12) $errors[]='invalid_period';
        if ($pageType==='resort_month'&&($regionId===null||$regionId<=0)) $errors[]='invalid_region_identity';
        if ($pageType==='month'&&$regionId!==null&&$regionId>0) $errors[]='unexpected_region_identity';
        if ($offerCount<=0) $errors[]='empty_evidence';
        if ($freshness<=0) $errors[]='stale_evidence';
        $expected=$pageType==='resort_month'
            ? sprintf('resort_month:%d:%d:%d:%04d-%02d',$departureId,$countryId,(int)$regionId,$year,$month)
            : sprintf('month:%d:%d:%04d-%02d',$departureId,$countryId,$year,$month);
        if ((string)($row['page_key']??'')!==$expected) $errors[]='page_key_mismatch';
        if ($errors!==[]) { $blocked[]=['index'=>$index,'page_key'=>(string)($row['page_key']??''),'errors'=>array_values(array_unique($errors))]; continue; }
        $items[$expected]=[
            'state'=>'fresh_review_identity',
            'page_key'=>$expected,
            'page_type'=>$pageType,
            'country_id'=>$countryId,
            'region_id'=>$regionId,
            'departure_id'=>$departureId,
            'year'=>$year,
            'month'=>$month,
            'offer_count'=>$offerCount,
            'observed_at'=>(string)($row['observed_at']??''),
            'expires_at'=>(string)($row['expires_at']??''),
            'freshness_seconds'=>$freshness,
            'publication_allowed'=>false,
            'copy_allowed'=>false,
        ];
    }
    ksort($items,SORT_STRING);
    return [
        'state'=>'review_only_seasonal_identity_inventory',
        'identity_count'=>count($items),
        'blocked_count'=>count($blocked),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'copy_allowed'=>false,
        'identities'=>array_values($items),
        'blocked'=>$blocked,
    ];
}
