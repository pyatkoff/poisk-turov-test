<?php
declare(strict_types=1);
require_once __DIR__.'/seo-hotel-review-pilot-package-v1.php';

/**
 * Build an operational, review-only summary for the controlled 3x3 hotel pilot.
 *
 * The report consumes an already validated pilot package. It never discovers,
 * ranks or publishes hotels and cannot grant launch/indexation permissions.
 */
function v2_seo_hotel_review_pilot_report(array $package, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    if (($package['state']??'')!=='review_only_manifest_bound_pilot_package') {
        throw new InvalidArgumentException('Expected validated review-only pilot package');
    }
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag){
        if (($package[$flag]??true)!==false) throw new InvalidArgumentException('Pilot package launch boundary violated: '.$flag);
    }
    if (($package['publication_candidates']??null)!==[]) throw new InvalidArgumentException('Pilot package cannot expose publication candidates');

    $manifest=is_array($package['manifest']??null)?$package['manifest']:[];
    $slice=is_array($package['slice']??null)?$package['slice']:[];
    if (($manifest['integrity_ok']??false)!==true || ($manifest['family_quality_floor']??0)!==100) {
        throw new InvalidArgumentException('Pilot report requires intact 100/100 scoped manifest');
    }
    if (($slice['manifest_bound']??false)!==true || ($slice['total']??0)!==9) {
        throw new InvalidArgumentException('Pilot report requires manifest-bound 3x3 slice');
    }

    $rows=is_array($slice['review_items']??null)?$slice['review_items']:[];
    if (count($rows)!==9) throw new InvalidArgumentException('Pilot report requires exactly nine review rows');
    $countries=[]; $expires=[]; $paths=[];
    foreach($rows as $row){
        if(!is_array($row)) throw new InvalidArgumentException('Invalid pilot row');
        $path=(string)($row['path']??'');
        $countryId=(int)($row['country_id']??0);
        $hotelId=(int)($row['hotel_id']??0);
        $expiry=(int)($row['evidence_expires_epoch']??0);
        if($path===''||$countryId<=0||$hotelId<=0||$expiry<=0) throw new InvalidArgumentException('Pilot row identity/evidence incomplete');
        if(isset($paths[$path])) throw new InvalidArgumentException('Duplicate pilot path');
        $paths[$path]=true; $expires[]=$expiry;
        $countries[$countryId]=($countries[$countryId]??0)+1;
    }
    ksort($countries,SORT_NUMERIC);
    if($countries!==[1=>3,4=>3,8=>3]) throw new InvalidArgumentException('Pilot report country balance mismatch');

    $validUntil=min($expires);
    $fresh=$validUntil>$nowEpoch && ($manifest['hotel_evidence_fresh']??false)===true;
    $remaining=max(0,$validUntil-$nowEpoch);
    $manifestSha=(string)($manifest['manifest_sha256']??'');
    $evidenceSha=(string)($manifest['hotel_evidence_sha256']??'');
    $reviewSha=(string)($manifest['review_contract_sha256']??'');
    if($manifestSha===''||$evidenceSha===''||$reviewSha==='') throw new InvalidArgumentException('Pilot report requires manifest/evidence/review fingerprints');

    $reportPayload=[
        'paths'=>array_keys($paths),
        'countries'=>$countries,
        'manifest_sha256'=>$manifestSha,
        'hotel_evidence_sha256'=>$evidenceSha,
        'review_contract_sha256'=>$reviewSha,
        'evidence_valid_until_epoch'=>$validUntil,
    ];
    sort($reportPayload['paths'],SORT_STRING);

    return [
        'state'=>$fresh?'fresh_review_only_3x3':'stale_review_only_3x3',
        'generated_at_epoch'=>$nowEpoch,
        'hotel_count'=>9,
        'country_count'=>3,
        'country_counts'=>$countries,
        'evidence_fresh'=>$fresh,
        'evidence_valid_until_epoch'=>$validUntil,
        'evidence_remaining_seconds'=>$remaining,
        'manifest_sha256'=>$manifestSha,
        'hotel_evidence_sha256'=>$evidenceSha,
        'review_contract_sha256'=>$reviewSha,
        'report_sha256'=>hash('sha256',json_encode($reportPayload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
    ];
}
