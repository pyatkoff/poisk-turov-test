<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-phase5-inventory-opportunity-review-v1.php';
function p5inv_fail(string $x): never { fwrite(STDERR,"SEO_PHASE5_INVENTORY_REVIEW_FAIL:$x\n"); exit(1); }
$chain=[
 'state'=>'fresh_evidence_chain_ready_for_expansion_review','expansion_review_allowed'=>true,
 'publication_allowed'=>false,'hotel_tours_publication_allowed'=>false,'hotel_tours_indexation_allowed'=>false,'hotel_tours_sitemap_allowed'=>false,
 'hotel_tours_canonical_launch_allowed'=>false,'hotel_tours_route_launch_allowed'=>false,'hotel_tours_publication_candidates'=>[],
];
$base=[
 'state'=>'review_only_inventory_candidate','country_id'=>4,'country_name'=>'Турция','country_slug'=>'turciya','region_id'=>20,'region_name'=>'Анталья','region_slug'=>'antalya',
 'departure_year'=>null,'departure_month'=>null,'period_key'=>null,'catalog_path_hint'=>'/country/turciya/antalya/','route_mapping_state'=>'controlled_identity_registry_match',
 'path_exists_in_controlled_registry'=>true,'inventory_rank'=>1,
 'inventory'=>['fresh_observation_within_3d'=>true,'observations_30d'=>90,'distinct_hotels_30d'=>25,'distinct_departures_30d'=>7],
 'demand'=>['status'=>'unknown','reason'=>'not_joined_no_zero_imputation','impressions'=>null,'clicks'=>null,'avg_position'=>null],
 'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_launch_allowed'=>false,
];
$resort=$base+['candidate_type'=>'resort','identity_key'=>'resort:country=4:region=20','review_path'=>'/country/turkey/antalya/'];
$seasonal=array_replace($base,['candidate_type'=>'resort_month','identity_key'=>'resort_month:country=4:region=20:period=2026-10','departure_year'=>2026,'departure_month'=>10,'period_key'=>'2026-10','review_path'=>'/country/turkey/antalya/october/']);
$unmapped=array_replace($seasonal,['identity_key'=>'resort_month:country=4:region=999:period=2026-10','review_path'=>null,'route_mapping_state'=>'unmapped_review_identity','path_exists_in_controlled_registry'=>false]);
$country=array_replace($base,['candidate_type'=>'country','identity_key'=>'country:country=4','review_path'=>'/country/turkey/']);
$report=[
 'state'=>'review_only_inventory_opportunity_report','publication_candidates'=>[],'publication_allowed'=>false,'automatic_execution_allowed'=>false,'hotel_tours_indexation_allowed'=>false,
 'route_semantics'=>'only_explicit_identity_registry_bindings_are_routes_catalog_slugs_are_hints_only','candidate_generation_semantics'=>'observed_database_groups_only_no_cartesian_generation',
 'evidence_sha256'=>str_repeat('a',64),'candidates'=>[$resort,$seasonal,$unmapped,$country],
];
$r=v2_seo_phase5_inventory_opportunity_review($chain,$report);
if(($r['state']??'')!=='phase5_inventory_opportunity_review_ready')p5inv_fail('state');
if(($r['review_candidate_count']??0)!==2)p5inv_fail('review_count');
if(($r['blocked_candidate_count']??0)!==1)p5inv_fail('blocked_count');
foreach($r['review_candidates'] as $row){
 if(($row['review_allowed']??false)!==true)p5inv_fail('review_not_allowed');
 if(($row['demand']['status']??'')!=='unknown')p5inv_fail('demand_fabricated');
 if(($row['publication_allowed']??true)!==false||($row['indexation_allowed']??true)!==false||($row['route_launch_allowed']??true)!==false)p5inv_fail('unsafe_review_row');
}
if(($r['blocked_candidates'][0]['errors'][0]??'')==='')p5inv_fail('blocked_errors_missing');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_publication_allowed','hotel_tours_indexation_allowed'] as $flag)if(($r[$flag]??true)!==false)p5inv_fail($flag);
$stale=$report;$stale['candidates'][0]['inventory']['fresh_observation_within_3d']=false;$s=v2_seo_phase5_inventory_opportunity_review($chain,$stale);if(($s['review_candidate_count']??0)!==1)p5inv_fail('stale_candidate_not_blocked');
$bad=$report;$bad['publication_allowed']=true;if((v2_seo_phase5_inventory_opportunity_review($chain,$bad)['state']??'')!=='phase5_inventory_opportunity_review_blocked')p5inv_fail('report_boundary');
$badChain=$chain;$badChain['state']='fresh_evidence_chain_blocked';$empty=$report;$empty['candidates']=[];$e=v2_seo_phase5_inventory_opportunity_review($badChain,$empty);if(($e['state']??'')!=='phase5_inventory_opportunity_review_blocked')p5inv_fail('empty_candidates_bypass_upstream');
if(!in_array('upstream_evidence_chain_not_ready',$e['errors']??[],true))p5inv_fail('upstream_error_missing');
$leakChain=$chain;$leakChain['hotel_tours_indexation_allowed']=true;if((v2_seo_phase5_inventory_opportunity_review($leakChain,$empty)['state']??'')!=='phase5_inventory_opportunity_review_blocked')p5inv_fail('hotel_upstream_boundary');
echo "SEO_PHASE5_INVENTORY_REVIEW_OK review=2 blocked=1 upstream_failclosed=1 country=ignored publication=0 hotel_tours=0\n";
