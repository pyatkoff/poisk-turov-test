<?php
require_once __DIR__ . '/seo-review-catalog-union-v1.php';
require_once __DIR__ . '/seo-launch-manifest-v1.php';
require_once __DIR__ . '/seo-hotel-review-launch-slice-v1.php';
require_once __DIR__ . '/seo-hotel-launch-pilot-v1.php';

/**
 * Project a unified review catalog to the explicit controlled 3x3 pilot plus its
 * required country parents. This prevents unrelated review hotels without fresh
 * evidence from diluting or blocking the small controlled evidence package.
 */
function v2_seo_hotel_review_pilot_catalog(array $unionCatalog, array $pilotSpec): array
{
    $registry=is_array($unionCatalog['registry']??null)?$unionCatalog['registry']:[];
    $reports=is_array($unionCatalog['reports']??null)?$unionCatalog['reports']:[];
    $graph=is_array($unionCatalog['graph']??null)?$unionCatalog['graph']:[];
    $selected=[];

    foreach (($pilotSpec['countries']??[]) as $bucket) {
        if (!is_array($bucket)) throw new InvalidArgumentException('Hotel review pilot bucket must be an array');
        $countryId=(int)($bucket['country_id']??0);
        foreach ((array)($bucket['paths']??[]) as $path) {
            $path=(string)$path;
            $entry=$registry[$path]??null;
            if (!is_array($entry) || ($entry['type']??'')!=='hotel_tours') throw new InvalidArgumentException('Hotel review pilot path missing from unified review registry: '.$path);
            $state=is_array($entry['page']['search_state']??null)?$entry['page']['search_state']:[];
            if ((int)($state['country']??0)!==$countryId) throw new InvalidArgumentException('Hotel review pilot country identity mismatch: '.$path);
            if (($reports[$path]['status']??'')!=='review') throw new InvalidArgumentException('Hotel review pilot hotel must remain review: '.$path);
            $parent=(string)($graph[$path]['parent']??'');
            $parentEntry=$registry[$parent]??null;
            if (!is_array($parentEntry) || ($parentEntry['type']??'')!=='country') throw new InvalidArgumentException('Hotel review pilot country parent missing: '.$path);
            $parentState=is_array($parentEntry['page']['search_state']??null)?$parentEntry['page']['search_state']:[];
            if ((int)($parentState['country']??0)!==$countryId) throw new InvalidArgumentException('Hotel review pilot parent country mismatch: '.$path);
            $selected[$parent]=true;
            $selected[$path]=true;
        }
    }

    $projected=['registry'=>[],'reports'=>[],'graph'=>[],'publication_candidates'=>[]];
    foreach (array_keys($selected) as $path) {
        if (!isset($registry[$path],$reports[$path],$graph[$path])) throw new InvalidArgumentException('Hotel review pilot projection parity failure: '.$path);
        $projected['registry'][$path]=$registry[$path];
        $projected['reports'][$path]=$reports[$path];
        $projected['graph'][$path]=$graph[$path];
    }
    ksort($projected['registry'],SORT_STRING); ksort($projected['reports'],SORT_STRING); ksort($projected['graph'],SORT_STRING);
    return $projected;
}

/** Build one deterministic, review-only Turkey/Maldives/Egypt 3x3 package. */
function v2_seo_hotel_review_pilot_package(array $families, array $evidence, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $union=v2_seo_review_catalog_union($families);
    $spec=v2_seo_hotel_launch_pilot_spec();
    $catalog=v2_seo_hotel_review_pilot_catalog($union,$spec);
    $manifest=v2_seo_launch_manifest($catalog,$evidence,$nowEpoch);
    if (($manifest['integrity_ok']??false)!==true) throw new InvalidArgumentException('Hotel review pilot manifest integrity failed');
    if (($manifest['family_quality_floor']??0)!==100 || ($manifest['hotel_evidence_fresh']??false)!==true) throw new InvalidArgumentException('Hotel review pilot requires fresh 100/100 scoped evidence');

    $readiness=v2_seo_page_launch_readiness($catalog,$evidence,$nowEpoch);
    $hotelRows=array_values(array_filter($readiness,static fn(array $row):bool=>($row['type']??'')==='hotel_tours'));
    if (count($hotelRows)!==9) throw new InvalidArgumentException('Hotel review pilot must contain exactly nine hotel readiness rows');

    $slice=v2_seo_hotel_review_launch_slice($hotelRows,$spec['countries'],[4,8,1],3,9,$nowEpoch,$manifest);
    if (($slice['total']??0)!==9) throw new InvalidArgumentException('Hotel review pilot slice must contain exactly nine hotels');

    return [
        'state'=>'review_only_manifest_bound_pilot_package',
        'validated_at_epoch'=>$nowEpoch,
        'registry_count'=>count($catalog['registry']),
        'hotel_count'=>9,
        'country_count'=>3,
        'manifest'=>$manifest,
        'slice'=>$slice,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
    ];
}
