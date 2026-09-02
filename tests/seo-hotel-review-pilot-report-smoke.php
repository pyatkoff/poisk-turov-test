<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-review-pilot-report-v1.php';
function report_fail(string $m):void{fwrite(STDERR,"SEO_HOTEL_PILOT_REPORT_FAIL:$m\n");exit(1);}
$now=1800000000;
$rows=[];
foreach([1,4,8] as $countryId){
    for($i=1;$i<=3;$i++){
        $hotelId=$countryId*1000+$i;
        $rows[]=[
            'path'=>'/country/test-'.$countryId.'/hotel/test-'.$hotelId.'/',
            'country_id'=>$countryId,
            'hotel_id'=>$hotelId,
            'score'=>100,
            'evidence_epoch'=>$now-60,
            'evidence_expires_epoch'=>$now+600,
        ];
    }
}
$sha=str_repeat('a',64); $evidenceSha=str_repeat('b',64); $reviewSha=str_repeat('c',64);
$package=[
    'state'=>'review_only_manifest_bound_pilot_package',
    'manifest'=>[
        'integrity_ok'=>true,
        'family_quality_floor'=>100,
        'hotel_evidence_fresh'=>true,
        'manifest_sha256'=>$sha,
        'hotel_evidence_sha256'=>$evidenceSha,
        'review_contract_sha256'=>$reviewSha,
    ],
    'slice'=>['manifest_bound'=>true,'total'=>9,'review_items'=>$rows],
    'publication_candidates'=>[],
    'publication_allowed'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'canonical_launch_allowed'=>false,
    'route_launch_allowed'=>false,
];
$r=v2_seo_hotel_review_pilot_report($package,$now);
if(($r['state']??'')!=='fresh_review_only_3x3'||($r['hotel_count']??0)!==9||($r['country_counts']??[])!==[1=>3,4=>3,8=>3]) report_fail('fresh_counts');
if(($r['evidence_remaining_seconds']??0)!==600||!preg_match('/^[a-f0-9]{64}$/',(string)($r['report_sha256']??''))) report_fail('fingerprint_ttl');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag){if(($r[$flag]??true)!==false) report_fail('boundary_'.$flag);}
if(($r['publication_candidates']??null)!==[]) report_fail('publication_candidates');

$stale=$package; foreach($stale['slice']['review_items'] as &$row){$row['evidence_expires_epoch']=$now;} unset($row);
$r=v2_seo_hotel_review_pilot_report($stale,$now);
if(($r['state']??'')!=='stale_review_only_3x3'||($r['evidence_fresh']??true)!==false||($r['evidence_remaining_seconds']??-1)!==0) report_fail('stale');

$unsafe=$package; $unsafe['indexation_allowed']=true;
try{v2_seo_hotel_review_pilot_report($unsafe,$now);report_fail('unsafe_allowed');}catch(InvalidArgumentException $e){}
$unbalanced=$package; $unbalanced['slice']['review_items'][0]['country_id']=4;
try{v2_seo_hotel_review_pilot_report($unbalanced,$now);report_fail('unbalanced_allowed');}catch(InvalidArgumentException $e){}

echo "SEO_HOTEL_PILOT_REPORT_OK hotels=9 countries=3 freshness=1 fingerprints=1 publication=0 indexation=0\n";
