<?php
/**
 * Score an explicit review-only seasonal plan using factual selected evidence.
 * This never selects candidates and never enables routes, copy, feeds or publication.
 */
function v2_seo_seasonal_selected_readiness(array $plan, array $policy, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $errors=[];
    if (($plan['state']??'') !== 'review_only_seasonal_plan') $errors[]='invalid_plan_state';
    if (($plan['publication_allowed']??true)!==false || ($plan['feed_publish_allowed']??true)!==false || ($plan['copy_allowed']??true)!==false || ($plan['publication_candidates']??null)!==[]) {
        $errors[]='publication_boundary_crossed';
    }
    $expectedItems=(int)($policy['expected_items']??0);
    $minOffers=(int)($policy['min_offer_count_per_item']??0);
    $minFreshness=(int)($policy['min_freshness_seconds_per_item']??0);
    if($expectedItems<1||$expectedItems>50) $errors[]='invalid_expected_items';
    if($minOffers<1) $errors[]='invalid_min_offer_count';
    if($minFreshness<1) $errors[]='invalid_min_freshness';
    $validUntil=(int)($plan['evidence_valid_until_epoch']??0);
    if($validUntil<=$nowEpoch) $errors[]='plan_evidence_expired';

    $items=is_array($plan['items']??null)?$plan['items']:[];
    if($expectedItems>0&&count($items)!==$expectedItems) $errors[]='unexpected_item_count';
    $seen=[];$minObservedOffers=null;$minObservedFreshness=null;
    foreach($items as $index=>$item){
        if(!is_array($item)){ $errors[]='invalid_item'; continue; }
        $key=trim((string)($item['page_key']??''));
        $offers=(int)($item['offer_count']??0);
        $freshness=(int)($item['freshness_seconds']??0);
        $checked=(int)($item['evidence_checked_at_epoch']??0);
        $expires=(int)($item['expires_at_epoch']??0);
        if(($item['state']??'')!=='review_only_seasonal_plan_item') $errors[]='invalid_item_state';
        if($key===''||isset($seen[$key])) $errors[]='duplicate_or_missing_page_key'; else $seen[$key]=true;
        if(($item['publication_allowed']??true)!==false||($item['copy_allowed']??true)!==false) $errors[]='item_publication_boundary_crossed';
        if($checked<=0||$checked>$nowEpoch+5||$expires<=$nowEpoch||$freshness<=0) $errors[]='item_evidence_stale_or_invalid';
        if($minOffers>0&&$offers<$minOffers) $errors[]='item_offer_depth_below_policy';
        if($minFreshness>0&&$freshness<$minFreshness) $errors[]='item_freshness_below_policy';
        $minObservedOffers=$minObservedOffers===null?$offers:min($minObservedOffers,$offers);
        $minObservedFreshness=$minObservedFreshness===null?$freshness:min($minObservedFreshness,$freshness);
    }
    $errors=array_values(array_unique($errors));
    $checks=[
        'plan_boundary'=>!in_array('publication_boundary_crossed',$errors,true),
        'item_count'=>!in_array('unexpected_item_count',$errors,true)&&!in_array('invalid_expected_items',$errors,true),
        'offer_depth'=>!in_array('item_offer_depth_below_policy',$errors,true)&&!in_array('invalid_min_offer_count',$errors,true),
        'freshness'=>!in_array('item_freshness_below_policy',$errors,true)&&!in_array('invalid_min_freshness',$errors,true)&&!in_array('item_evidence_stale_or_invalid',$errors,true)&&!in_array('plan_evidence_expired',$errors,true),
        'identity_integrity'=>!in_array('duplicate_or_missing_page_key',$errors,true)&&!in_array('invalid_item',$errors,true)&&!in_array('invalid_item_state',$errors,true),
    ];
    $score=0;foreach($checks as $pass)if($pass)$score+=20;
    $ready=$errors===[];
    return [
        'state'=>$ready?'selected_review_ready':'selected_review_blocked',
        'review_ready'=>$ready,
        'score'=>$score,
        'item_count'=>count($items),
        'min_observed_offer_count'=>$minObservedOffers??0,
        'min_observed_freshness_seconds'=>$minObservedFreshness??0,
        'evidence_valid_until_epoch'=>$validUntil,
        'checks'=>$checks,
        'errors'=>$errors,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'feed_publish_allowed'=>false,
        'copy_allowed'=>false,
    ];
}
