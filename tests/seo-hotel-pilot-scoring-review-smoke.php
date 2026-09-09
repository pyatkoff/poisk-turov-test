<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-pilot-scoring-review-v1.php';
function pilot_scoring_fail(string $m):void{fwrite(STDERR,"SEO_HOTEL_PILOT_SCORING_FAIL:$m\n");exit(1);}
$items=[];$packets=[];
foreach([1,4,8] as $countryId){
    for($i=1;$i<=3;$i++){
        $hotelId=$countryId*1000+$i;
        $path="/country/c{$countryId}/hotel/h{$hotelId}/";
        $items[]=['path'=>$path,'country_id'=>$countryId,'hotel_id'=>$hotelId,'score'=>100];
        $packets[$path]=[
            'state'=>'opportunity_evidence_review_ready','page_key'=>"hotel:$countryId:$hotelId",'path'=>$path,'query_cluster'=>"hotel $hotelId tours",
            'evidence_fresh'=>true,'evidence_confirmed'=>true,'uniqueness_distinct'=>true,'scoring_policy_pending'=>true,
            'packet_sha256'=>hash('sha256',$path),
            'demand'=>['metrics'=>['impressions'=>100+$i]],
            'uniqueness'=>['decision'=>'distinct','overlap_ratio'=>0.1],
        ];
    }
}
$policy=[
    'policy_id'=>'fixture-only','version'=>'1','source_ref'=>'test-fixture-only','approved_at_epoch'=>1800000000,
    'dimensions'=>[
        'demand'=>['weight'=>60,'rules'=>[['field'=>'metrics.impressions','operator'=>'gte','value'=>100,'score'=>80]]],
        'uniqueness'=>['weight'=>40,'rules'=>[['field'=>'decision','operator'=>'eq','value'=>'distinct','score'=>100]]],
    ],
];
$r=v2_seo_hotel_pilot_scoring_review($items,$packets,$policy);
if(($r['state']??'')!=='review_only_pilot_scored'||($r['scored_count']??0)!==9||($r['score_summary']??[])!==['count'=>9,'min'=>88,'avg'=>88,'max'=>88])pilot_scoring_fail('scored');
if(($r['country_counts']??[])!==[1=>3,4=>3,8=>3])pilot_scoring_fail('balance');
if(!preg_match('/^[a-f0-9]{64}$/',(string)($r['policy_sha256']??''))||!preg_match('/^[a-f0-9]{64}$/',(string)($r['scoring_review_sha256']??'')))pilot_scoring_fail('fingerprints');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)pilot_scoring_fail('boundary_'.$flag);
if(($r['publication_candidates']??null)!==[]||($r['explicit_user_indexation_approval_required']??false)!==true)pilot_scoring_fail('approval_boundary');

$broken=$packets;$first=array_key_first($broken);$broken[$first]['evidence_fresh']=false;$broken[$first]['state']='opportunity_evidence_incomplete';
$r=v2_seo_hotel_pilot_scoring_review($items,$broken,$policy);
if(($r['state']??'')!=='review_only_pilot_scoring_blocked'||!in_array('pilot_evidence_incomplete',$r['errors']??[],true))pilot_scoring_fail('evidence_gate');

$badPolicy=$policy;$badPolicy['dimensions']['demand']['weight']=50;
$r=v2_seo_hotel_pilot_scoring_review($items,$packets,$badPolicy);
if(($r['state']??'')!=='review_only_pilot_scoring_blocked'||!in_array('invalid_scoring_policy',$r['errors']??[],true))pilot_scoring_fail('policy_gate');

echo "SEO_HOTEL_PILOT_SCORING_OK hotels=9 balanced=1 explicitPolicy=1 publication=0 indexation=0\n";
