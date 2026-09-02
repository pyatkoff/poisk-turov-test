<?php
declare(strict_types=1);

/**
 * Build a small explicit feed review plan from already family-bound evidence.
 *
 * The caller must name exact tour identities. This function never auto-selects
 * cheapest offers and deliberately drops price/availability payloads from the
 * plan so a later review cannot replay volatile commercial facts as fresh data.
 */
function v2_seo_feed_review_plan(array $binding, array $selectors, ?int $nowEpoch = null, int $maxItems = 12): array
{
    $nowEpoch ??= time();
    if ($maxItems < 1 || $maxItems > 12) throw new InvalidArgumentException('Feed review plan maxItems must be 1-12');
    if (($binding['state'] ?? '') !== 'review_only_feed_family_binding') throw new InvalidArgumentException('Feed review plan requires family-bound review evidence');
    if (($binding['feed_publish_allowed'] ?? true) !== false || ($binding['publication_allowed'] ?? true) !== false || ($binding['publication_candidates'] ?? null) !== []) {
        throw new InvalidArgumentException('Feed review plan rejects publication-enabled binding');
    }
    if ($selectors === [] || count($selectors) > $maxItems) throw new InvalidArgumentException('Feed review plan requires a small explicit selector set');

    $available=[];
    foreach (($binding['bound'] ?? []) as $index=>$item) {
        if (!is_array($item)) throw new InvalidArgumentException('Feed review plan requires valid bound items');
        if (($item['state'] ?? '') !== 'review_only_family_bound_feed_evidence' || ($item['feed_publish_allowed'] ?? true) !== false || ($item['publication_allowed'] ?? true) !== false) {
            throw new InvalidArgumentException('Feed review plan rejects non-review bound item');
        }
        $countryId=(int)($item['country_id']??0); $hotelId=(int)($item['hotel_id']??0); $tourId=trim((string)($item['tour_id']??''));
        if ($countryId<=0||$hotelId<=0||$tourId==='') throw new InvalidArgumentException('Feed review plan requires exact bound identities');
        $identity=$countryId.'|'.$hotelId.'|'.$tourId;
        if (isset($available[$identity])) throw new InvalidArgumentException('Feed review plan rejects duplicate bound identity');
        $available[$identity]=$item;
    }

    $planned=[]; $seen=[]; $validUntil=null;
    foreach ($selectors as $selectorIndex=>$selector) {
        if (!is_array($selector)) throw new InvalidArgumentException('Feed review plan selectors must be arrays');
        $countryId=(int)($selector['country_id']??0); $hotelId=(int)($selector['hotel_id']??0); $tourId=trim((string)($selector['tour_id']??''));
        if ($countryId<=0||$hotelId<=0||$tourId==='') throw new InvalidArgumentException('Feed review plan selector requires country_id, hotel_id and tour_id');
        $identity=$countryId.'|'.$hotelId.'|'.$tourId;
        if (isset($seen[$identity])) throw new InvalidArgumentException('Feed review plan rejects duplicate selector');
        $seen[$identity]=true;
        if (!isset($available[$identity])) throw new InvalidArgumentException('Feed review plan selector is not present in verified family binding');
        $item=$available[$identity];
        $expires=(int)($item['expires_at_epoch']??0);
        if ($expires <= $nowEpoch) throw new InvalidArgumentException('Feed review plan rejects expired selected evidence');
        $validUntil=$validUntil===null?$expires:min($validUntil,$expires);
        $planned[]=[
            'state'=>'review_only_feed_plan_item',
            'family_key'=>(string)($item['family_key']??''),
            'hotel_path'=>(string)($item['hotel_path']??''),
            'country_id'=>$countryId,
            'hotel_id'=>$hotelId,
            'tour_id'=>$tourId,
            'source_page_key'=>(string)($item['source_page_key']??''),
            'expires_at_epoch'=>$expires,
            'feed_publish_allowed'=>false,
            'publication_allowed'=>false,
        ];
    }

    return [
        'state'=>'review_only_feed_plan',
        'selection_mode'=>'explicit_exact_tour_identity',
        'item_count'=>count($planned),
        'evidence_valid_until_epoch'=>$validUntil,
        'feed_publish_allowed'=>false,
        'publication_allowed'=>false,
        'copy_allowed'=>false,
        'route_creation_allowed'=>false,
        'publication_candidates'=>[],
        'items'=>$planned,
    ];
}
