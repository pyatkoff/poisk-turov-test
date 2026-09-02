<?php
/** Normalize exact fresh month/resort-month identities for review-only planning. */
function v2_seo_seasonal_identity_inventory(array $rows, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $items = [];
    $blocked = [];
    $checkedEpochs=[];
    $validUntilEpochs=[];
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
        $checkedEpoch=(int)($row['evidence_checked_at_epoch']??0);
        $expiresEpoch=(int)($row['expires_at_epoch']??0);
        if (!in_array($pageType,['month','resort_month'],true)) $errors[]='invalid_page_type';
        if ($countryId<=0) $errors[]='invalid_country_identity';
        if ($departureId<=0) $errors[]='invalid_departure_identity';
        if ($year<2020||$year>2100||$month<1||$month>12) $errors[]='invalid_period';
        if ($pageType==='resort_month'&&($regionId===null||$regionId<=0)) $errors[]='invalid_region_identity';
        if ($pageType==='month'&&$regionId!==null&&$regionId>0) $errors[]='unexpected_region_identity';
        if ($offerCount<=0) $errors[]='empty_evidence';
        if ($freshness<=0) $errors[]='stale_evidence';
        if ($checkedEpoch<=0||$expiresEpoch<=$checkedEpoch) $errors[]='invalid_evidence_clock';
        elseif ($checkedEpoch>$nowEpoch+5) $errors[]='evidence_clock_from_future';
        elseif ($expiresEpoch<=$nowEpoch) $errors[]='evidence_expired';
        elseif (abs(($expiresEpoch-$checkedEpoch)-$freshness)>5) $errors[]='freshness_clock_mismatch';
        $expected=$pageType==='resort_month'
            ? sprintf('resort_month:%d:%d:%d:%04d-%02d',$departureId,$countryId,(int)$regionId,$year,$month)
            : sprintf('month:%d:%d:%04d-%02d',$departureId,$countryId,$year,$month);
        if ((string)($row['page_key']??'')!==$expected) $errors[]='page_key_mismatch';
        if ($errors!==[]) { $blocked[]=['index'=>$index,'page_key'=>(string)($row['page_key']??''),'errors'=>array_values(array_unique($errors))]; continue; }
        $checkedEpochs[]=$checkedEpoch;
        $validUntilEpochs[]=$expiresEpoch;
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
            'evidence_checked_at_epoch'=>$checkedEpoch,
            'expires_at_epoch'=>$expiresEpoch,
            'freshness_seconds'=>$freshness,
            'publication_allowed'=>false,
            'copy_allowed'=>false,
        ];
    }
    ksort($items,SORT_STRING);
    $checkedAt=$checkedEpochs===[]?0:max($checkedEpochs);
    $validUntil=$validUntilEpochs===[]?0:min($validUntilEpochs);
    $clockValid=$checkedEpochs!==[]&&(max($checkedEpochs)-min($checkedEpochs))<=5&&$validUntil>$nowEpoch;
    return [
        'state'=>'review_only_seasonal_identity_inventory',
        'identity_count'=>count($items),
        'blocked_count'=>count($blocked),
        'evidence_checked_at_epoch'=>$checkedAt,
        'evidence_valid_until_epoch'=>$validUntil,
        'evidence_clock_valid'=>$clockValid,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'copy_allowed'=>false,
        'identities'=>array_values($items),
        'blocked'=>$blocked,
    ];
}
