<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/seo-inventory-opportunity-engine-v1.php';

function opp_fail(string $message): never
{
    fwrite(STDERR,"SEO_INVENTORY_OPPORTUNITY_SMOKE_FAILED {$message}\n");
    exit(1);
}

$base=[
    'country_id'=>4,'country_name'=>'Турция','country_slug'=>'turkey',
    'region_id'=>null,'region_name'=>null,'region_slug'=>null,
    'departure_year'=>null,'departure_month'=>null,
    'observations_total'=>100,'observations_1d'=>10,'observations_3d'=>25,'observations_7d'=>55,'observations_30d'=>90,
    'hotels_total'=>30,'hotels_30d'=>28,'departures_total'=>6,'departures_30d'=>5,
    'min_price_30d'=>'84500.00','max_price_30d'=>'290000.00',
    'oldest_observed_at'=>'2026-08-01 10:00:00','newest_observed_at'=>'2026-09-03 10:00:00','history_depth_days'=>33,
];
$turkey=$base+['candidate_type'=>'country'];
$egypt=array_replace($base,[
    'candidate_type'=>'country','country_id'=>8,'country_name'=>'Египет','country_slug'=>'egypt',
    'observations_total'=>110,'observations_30d'=>70,'hotels_total'=>35,'hotels_30d'=>25,
]);
$antalya=array_replace($base,[
    'candidate_type'=>'resort','region_id'=>17,'region_name'=>'Анталья','region_slug'=>'antalya',
    'observations_total'=>75,'observations_30d'=>60,'hotels_total'=>18,'hotels_30d'=>16,
]);
$maldivesOctober=array_replace($base,[
    'candidate_type'=>'country_month','country_id'=>9,'country_name'=>'Мальдивы','country_slug'=>'maldives',
    'departure_year'=>2026,'departure_month'=>10,
    'observations_total'=>50,'observations_1d'=>0,'observations_3d'=>0,'observations_7d'=>15,'observations_30d'=>45,
    'hotels_total'=>20,'hotels_30d'=>18,'departures_total'=>3,'departures_30d'=>3,
    'oldest_observed_at'=>'2026-08-15 10:00:00','newest_observed_at'=>'2026-08-31 10:00:00','history_depth_days'=>16,
]);

$rows=[$egypt,$maldivesOctober,$antalya,$turkey];
$controlled=['/country/turkey/','/country/turkey/antalya/'];
$report=v2_seo_inventory_opportunity_report($rows,50,$controlled);
if(($report['state']??'')!=='review_only_inventory_opportunity_report') opp_fail('state');
if(($report['observed_identity_count']??0)!==4||($report['reported_candidate_count']??0)!==4) opp_fail('observed_rows_not_preserved');
if(($report['publication_candidates']??null)!==[]) opp_fail('publication_candidate_leak');
foreach(['publication_allowed','automatic_execution_allowed','hotel_tours_indexation_allowed'] as $flag){
    if(($report[$flag]??true)!==false) opp_fail('unsafe_'.$flag);
}
if(($report['ranking_semantics']??'')!=='inventory_components_only_no_combined_score') opp_fail('ranking_semantics');
if(($report['candidate_generation_semantics']??'')!=='observed_database_groups_only_no_cartesian_generation') opp_fail('cartesian_semantics');

$candidates=$report['candidates']??[];
if(count($candidates)!==4) opp_fail('cartesian_candidate_growth');
$byKey=[];
foreach($candidates as $candidate){
    $byKey[$candidate['identity_key']]=$candidate;
    if(!array_key_exists('opportunity_score',$candidate)||$candidate['opportunity_score']!==null) opp_fail('invented_opportunity_score');
    $demand=$candidate['demand']??[];
    if(($demand['status']??'')!=='unknown') opp_fail('demand_not_unknown');
    foreach(['impressions','clicks','avg_position'] as $metric){
        if(!array_key_exists($metric,$demand)||$demand[$metric]!==null) opp_fail('demand_zero_imputation_'.$metric);
    }
    if(($candidate['publication_allowed']??true)!==false||($candidate['route_launch_allowed']??true)!==false) opp_fail('candidate_launch_boundary');
}

$turkeyKey='country:country=4';
if(($byKey[$turkeyKey]['inventory_rank']??0)!==1) opp_fail('country_ranking');
if(($byKey[$turkeyKey]['review_path']??'')!=='/country/turkey/') opp_fail('country_path');
if(($byKey[$turkeyKey]['path_exists_in_controlled_registry']??false)!==true) opp_fail('controlled_path_detection');
$seasonalKey='country_month:country=9:period=2026-10';
if(($byKey[$seasonalKey]['review_path']??'')!=='/country/maldives/october/') opp_fail('seasonal_review_path');
if(($byKey[$seasonalKey]['path_period_semantics']??'')!=='yearless_route_requires_period_review') opp_fail('seasonal_period_boundary');
if(($byKey[$seasonalKey]['inventory']['fresh_observation_within_3d']??true)!==false) opp_fail('stale_3d_semantics');
if(($byKey[$seasonalKey]['inventory']['observations_30d']??0)!==45) opp_fail('stale_30d_observation_lost');

$reverse=v2_seo_inventory_opportunity_report(array_reverse($rows),50,$controlled);
if(($reverse['evidence_sha256']??'')!==($report['evidence_sha256']??'')) opp_fail('nondeterministic_fingerprint');

$duplicate=$rows;
$duplicate[]=$turkey;
$dupeReport=v2_seo_inventory_opportunity_report($duplicate,50,$controlled);
if(($dupeReport['blocked_count']??0)!==1) opp_fail('duplicate_not_blocked');
if(($dupeReport['reported_candidate_count']??0)!==3) opp_fail('duplicate_not_fail_closed');
if(!in_array('duplicate_observed_identity',$dupeReport['blocked'][0]['errors']??[],true)) opp_fail('duplicate_error_missing');

$bad=$turkey;
$bad['observations_3d']=5;
$bad['observations_1d']=10;
$badResult=v2_seo_inventory_normalize_row($bad);
if(($badResult['ok']??true)!==false||!in_array('observation_windows_inconsistent',$badResult['errors']??[],true)) opp_fail('window_integrity');

echo "SEO_INVENTORY_OPPORTUNITY_SMOKE_OK candidates=4 demand=unknown publication_candidates=0\n";
