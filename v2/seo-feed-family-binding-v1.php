<?php
/**
 * Bind fresh review-only feed evidence to verified review-only hotel-tour catalogs.
 *
 * This is deliberately not a feed generator. Unknown hotel IDs, country drift,
 * expired evidence and any publication-state leakage are rejected before future
 * feed planning can consume an item.
 */
function v2_seo_feed_family_binding(array $families, array $feedReports, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $hotelMap = [];
    $familyKeys = [];

    foreach ($families as $family) {
        if (!is_array($family)) throw new InvalidArgumentException('Feed binding requires hotel family arrays');
        $key = strtolower(trim((string)($family['key'] ?? '')));
        $countryId = (int)($family['country_id'] ?? 0);
        $catalog = is_array($family['catalog'] ?? null) ? $family['catalog'] : [];
        if ($key === '' || isset($familyKeys[$key])) throw new InvalidArgumentException('Feed binding requires unique family keys');
        if ($countryId <= 0) throw new InvalidArgumentException('Feed binding requires positive country IDs');
        $familyKeys[$key] = true;
        if (($catalog['publication_candidates'] ?? null) !== []) {
            throw new InvalidArgumentException('Feed binding rejects hotel catalogs with publication candidates');
        }

        $registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
        $reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
        foreach ($registry as $path => $entry) {
            if (($entry['type'] ?? '') !== 'hotel_tours') continue;
            $path = (string)$path;
            $report = is_array($reports[$path] ?? null) ? $reports[$path] : [];
            if (($report['status'] ?? '') !== 'review') throw new InvalidArgumentException('Feed binding hotel must remain review-only: '.$path);
            $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
            $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
            $pageCountry = (int)($state['country'] ?? 0);
            $hotelId = (int)($state['hotel'] ?? 0);
            if ($pageCountry !== $countryId || $hotelId <= 0 || !str_ends_with(rtrim($path,'/'),'-'.$hotelId)) {
                throw new InvalidArgumentException('Feed binding hotel identity mismatch: '.$path);
            }
            $identity = $countryId.':'.$hotelId;
            if (isset($hotelMap[$identity])) throw new InvalidArgumentException('Feed binding duplicate hotel identity: '.$identity);
            $hotelMap[$identity] = ['family_key'=>$key,'hotel_path'=>$path,'country_id'=>$countryId,'hotel_id'=>$hotelId];
        }
    }

    $bound = [];
    $blocked = [];
    $seenTourKeys = [];
    foreach ($feedReports as $reportIndex => $feedReport) {
        if (!is_array($feedReport)) {
            $blocked[]=['report_index'=>$reportIndex,'errors'=>['invalid_feed_report']];
            continue;
        }
        if (($feedReport['state'] ?? '') !== 'review_only_feed_evidence' || ($feedReport['feed_publish_allowed'] ?? true) !== false) {
            $blocked[]=['report_index'=>$reportIndex,'errors'=>['feed_report_not_review_only']];
            continue;
        }
        foreach (($feedReport['items'] ?? []) as $itemIndex => $item) {
            if (!is_array($item)) {
                $blocked[]=['report_index'=>$reportIndex,'item_index'=>$itemIndex,'errors'=>['invalid_feed_item']];
                continue;
            }
            $errors=[];
            $countryId=(int)($item['country_id']??0);
            $hotelId=(int)($item['hotel_id']??0);
            $tourId=trim((string)($item['tour_id']??''));
            $expires=(int)($item['expires_at_epoch']??0);
            $identity=$countryId.':'.$hotelId;
            $tourKey=$identity.':'.$tourId;
            if (($item['state']??'')!=='fresh_feed_evidence') $errors[]='item_not_fresh_feed_evidence';
            if (($item['feed_publish_allowed']??true)!==false) $errors[]='item_publication_boundary_crossed';
            if ($countryId<=0||$hotelId<=0||$tourId==='') $errors[]='invalid_offer_identity';
            if ($expires<=$nowEpoch) $errors[]='feed_evidence_expired';
            if (!isset($hotelMap[$identity])) $errors[]='hotel_identity_not_in_verified_family';
            if (isset($seenTourKeys[$tourKey])) $errors[]='duplicate_tour_identity';
            $seenTourKeys[$tourKey]=true;
            $errors=array_values(array_unique($errors));
            if ($errors!==[]) {
                $blocked[]=['report_index'=>$reportIndex,'item_index'=>$itemIndex,'country_id'=>$countryId,'hotel_id'=>$hotelId,'tour_id'=>$tourId,'errors'=>$errors];
                continue;
            }
            $hotel=$hotelMap[$identity];
            $bound[]=[
                'state'=>'review_only_family_bound_feed_evidence',
                'family_key'=>$hotel['family_key'],
                'hotel_path'=>$hotel['hotel_path'],
                'country_id'=>$countryId,
                'hotel_id'=>$hotelId,
                'tour_id'=>$tourId,
                'source_page_key'=>(string)($item['source_page_key']??''),
                'expires_at_epoch'=>$expires,
                'feed_publish_allowed'=>false,
                'publication_allowed'=>false,
            ];
        }
    }
    usort($bound,static fn(array $a,array $b):int=>[$a['family_key'],$a['hotel_id'],$a['tour_id']]<=>[$b['family_key'],$b['hotel_id'],$b['tour_id']]);
    return [
        'state'=>'review_only_feed_family_binding',
        'verified_hotel_identity_count'=>count($hotelMap),
        'bound_count'=>count($bound),
        'blocked_count'=>count($blocked),
        'feed_publish_allowed'=>false,
        'publication_allowed'=>false,
        'publication_candidates'=>[],
        'bound'=>$bound,
        'blocked'=>$blocked,
    ];
}
