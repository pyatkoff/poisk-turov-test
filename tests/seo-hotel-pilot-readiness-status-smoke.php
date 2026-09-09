<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-hotel-pilot-readiness-status-v1.php';

function hotel_status_fail(string $message): never
{
    fwrite(STDERR,"SEO_HOTEL_PILOT_STATUS_FAIL:$message\n");
    exit(1);
}

$now=1788391200;
$spec=v2_seo_hotel_launch_pilot_spec();
$checks=[];$dossierRows=[];$packets=[];$signals=[];
foreach($spec['countries'] as $bucket){
    $countryId=(int)$bucket['country_id'];
    foreach($bucket['paths'] as $path){
        if(!preg_match('/-(\d+)\/$/',(string)$path,$m))hotel_status_fail('hotel_id_parse');
        $hotelId=(int)$m[1];
        $checks[]=[
            'path'=>$path,'country_id'=>$countryId,'hotel_id'=>$hotelId,
            'identity_verified'=>true,'fresh_offer_evidence'=>true,'review_status_ok'=>true,
            'noindex_ok'=>true,'out_of_sitemap_ok'=>true,
        ];
        $dossierRows[]=[
            'path'=>$path,'country_id'=>$countryId,'quality_score'=>100,
            'captured_at_epoch'=>$now-60,'source_ref'=>'fixture://live/'.$hotelId,'fresh'=>true,
        ];
        $pageKey='hotel.'.$countryId.'.'.$hotelId;
        $cluster='туры в отель '.$hotelId;
        $packets[$path]=[
            'state'=>'opportunity_evidence_review_ready','path'=>$path,'page_key'=>$pageKey,
            'query_cluster'=>$cluster,'packet_sha256'=>hash('sha256',$path),
            'demand'=>[
                'state'=>'demand_evidence_valid','status'=>'confirmed','fresh'=>true,
                'page_key'=>$pageKey,'query_cluster'=>$cluster,'source_class'=>'manual_serp_review',
                'source_ref'=>'fixture://serp/'.$hotelId,'observed_at_epoch'=>$now-120,
                'metrics'=>[],'serp_intent'=>'commercial','errors'=>[],
            ],
            'uniqueness'=>[
                'state'=>'uniqueness_evidence_valid','status'=>'confirmed','fresh'=>true,
                'page_key'=>$pageKey,'query_cluster'=>$cluster,'page_path'=>$path,
                'source_class'=>'manual_serp_review','source_ref'=>'fixture://unique/'.$hotelId,
                'observed_at_epoch'=>$now-120,'decision'=>'distinct','competing_paths'=>[],
                'overlap_ratio'=>null,'errors'=>[],
            ],
        ];
        $signals[$path]=[
            'content'=>['status'=>'confirmed','source'=>'fixture://content/'.$hotelId,'observed_at_epoch'=>$now-120],
        ];
    }
}
$live=[
    'observed_at_epoch'=>$now-60,
    'checks'=>$checks,
    'dossier'=>[
        'state'=>'review_only_hotel_pilot_evidence_ready','rows'=>$dossierRows,
        'dossier_sha256'=>hash('sha256','fixture-live-dossier'),
    ],
];
$intake=[
    'state'=>'review_only_pilot_evidence_intake_ready','packets_by_path'=>$packets,
    'intake_sha256'=>hash('sha256','fixture-opportunity-intake'),
];
$policy=[
    'policy_id'=>'fixture-hotel-opportunity','version'=>'1','source_ref'=>'fixture://approved-policy',
    'approved_at_epoch'=>$now-300,
    'dimensions'=>[
        'demand'=>['weight'=>50,'rules'=>[['field'=>'metrics.impressions','operator'=>'gte','value'=>0,'score'=>100]]],
        'uniqueness'=>['weight'=>50,'rules'=>[['field'=>'decision','operator'=>'eq','value'=>'distinct','score'=>100]]],
    ],
];

$missing=v2_seo_hotel_pilot_readiness_status($live,[],[],[],$now);
if(($missing['state']??'')!=='review_only_hotel_pilot_status_complete')hotel_status_fail('missing_structural_state');
if(($missing['evidence_complete_count']??-1)!==0||($missing['review_ready_count']??-1)!==0)hotel_status_fail('missing_counts');
if(($missing['state_counts']['evidence_incomplete']??0)!==9)hotel_status_fail('missing_evidence_state');
foreach($missing['rows'] as $row){
    if(($row['dimensions']['technical']['status']??'')!=='confirmed')hotel_status_fail('technical_not_confirmed');
    if(($row['dimensions']['identity']['status']??'')!=='confirmed')hotel_status_fail('identity_not_confirmed');
    if(($row['dimensions']['demand']['status']??'')!=='unknown')hotel_status_fail('demand_fabricated');
    if(($row['dimensions']['content']['status']??'')!=='unknown')hotel_status_fail('content_fabricated');
}

$pending=v2_seo_hotel_pilot_readiness_status($live,$intake,$signals,[],$now);
if(($pending['evidence_complete_count']??0)!==9||($pending['policy_pending_count']??0)!==9)hotel_status_fail('policy_pending_counts');
if(($pending['state_counts']['evidence_complete_policy_pending']??0)!==9)hotel_status_fail('policy_pending_state');
if(($pending['review_ready_count']??-1)!==0)hotel_status_fail('policy_pending_review_ready');

$ready=v2_seo_hotel_pilot_readiness_status($live,$intake,$signals,$policy,$now);
if(($ready['scoring_policy_state']??'')!=='valid'||($ready['review_ready_count']??0)!==9)hotel_status_fail('valid_policy_ready');
if(($ready['state_counts']['review_ready_scoring_policy_valid']??0)!==9)hotel_status_fail('valid_policy_state');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','automatic_execution_allowed'] as $flag){
    if(($ready[$flag]??true)!==false)hotel_status_fail('safety_'.$flag);
}
if(($ready['publication_candidates']??null)!==[])hotel_status_fail('publication_candidates');
if(($ready['explicit_user_indexation_approval_required']??false)!==true)hotel_status_fail('approval_boundary');

$conflict=$intake;
$firstPath=array_key_first($conflict['packets_by_path']);
$conflict['packets_by_path'][$firstPath]['uniqueness']['decision']='merge';
$conflicted=v2_seo_hotel_pilot_readiness_status($live,$conflict,$signals,$policy,$now);
if(($conflicted['state_counts']['evidence_conflict']??0)!==1||($conflicted['review_ready_count']??0)!==8)hotel_status_fail('uniqueness_conflict');

$staleSignals=$signals;
$staleSignals[$firstPath]['content']['observed_at_epoch']=$now-(86400*32);
$stale=v2_seo_hotel_pilot_readiness_status($live,$intake,$staleSignals,$policy,$now);
if(($stale['state_counts']['evidence_incomplete']??0)!==1||($stale['review_ready_count']??0)!==8)hotel_status_fail('stale_content');

$brokenLive=$live;
array_pop($brokenLive['checks']);
$broken=v2_seo_hotel_pilot_readiness_status($brokenLive,$intake,$signals,$policy,$now);
if(($broken['state']??'')!=='review_only_hotel_pilot_status_blocked')hotel_status_fail('missing_live_check_not_blocked');

$invalidPolicy=$policy;
unset($invalidPolicy['source_ref']);
$invalid=v2_seo_hotel_pilot_readiness_status($live,$intake,$signals,$invalidPolicy,$now);
if(($invalid['scoring_policy_state']??'')!=='invalid'||($invalid['state_counts']['evidence_complete_policy_invalid']??0)!==9)hotel_status_fail('invalid_policy');

echo "SEO_HOTEL_PILOT_STATUS_OK hotels=9 missing=unknown policy=pending conflicts=blocked publication=0\n";
