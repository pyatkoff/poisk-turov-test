<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require __DIR__.'/../scripts/diagnostics/hotel_match_current_tv_anex_observations.php';
$checks=0;
function ok($v,$m){global $checks;if(!$v)throw new RuntimeException($m);$checks++;}
$r=hmto_direct_key(['operator_name'=>'ANEX TOUR','operator_link'=>'https://agent.anextour.ru/search/tour?STATEINC=4&HOTELLIST=8121']);
ok($r['status']==='direct_key'&&$r['anex_hotel_id']===8121&&$r['params']===['hotellist'],'hotellist');
$r=hmto_direct_key(['operator_name'=>'','operator_link_host'=>'www.anextour.ru','operator_link_query'=>'hotelCode=5844','operator_link_path'=>'/hotel/x']);
ok($r['status']==='direct_key'&&$r['anex_hotel_id']===5844,'hotelcode');
$r=hmto_direct_key(['operator_name'=>'ANEX','operator_link'=>'https://agent.anextour.ru/search/tour?HOTELLIST=8121,9153']);
ok($r['status']==='ambiguous_direct_key'&&count($r['hotel_ids'])===2,'multi');
$r=hmto_direct_key(['operator_name'=>'ANEX','operator_link'=>'https://agent.anextour.ru/search/tour?HOTELLIST=8121&hotelCode=8121']);
ok($r['status']==='direct_key'&&count($r['params'])===2,'two params same');
$r=hmto_direct_key(['operator_name'=>'ANEX','operator_link'=>'https://agent.anextour.ru/search/tour?HOTELLIST=8121','operator_link_host'=>'evil.example']);
ok($r['status']==='link_metadata_conflict','metadata');
$g=hmto_name_guard(['APERION BEACH HOTEL'],['APERION BEACH (EX. SEA PARADISE)']);
ok($g['exact_name']===true&&$g['common_tokens']===2&&!$g['qualifier_conflict'],'former generic');
$g=hmto_name_guard(['SUNRISE DIAMOND BEACH GRAND SELECT'],['SUNRISE DIAMOND BEACH RESORT']);
ok($g['qualifier_conflict']===true,'qualifier preserve');
$base=['manual_status'=>null,'pair_excluded'=>false,'accepted_local'=>null,'existing_mapping'=>false,'target_local'=>100,'target_country'=>4,'observed_country'=>4,'source_country'=>4,'target_occupied'=>false,'coordinate_conflict'=>false,'direct_geo'=>false,'qualifier_conflict'=>false,'source_name_present'=>true,'exact_name'=>true,'common_tokens'=>2];
ok(hmto_classify($base)==='candidate_anex','candidate');
$x=$base;$x['coordinate_conflict']=true;ok(hmto_classify($x)==='direct_conflict','geo conflict');
$x=$base;$x['source_country']=null;ok(hmto_classify($x)==='needs_country_evidence','country missing');
$x=$base;$x['target_occupied']=true;ok(hmto_classify($x)==='provider_occupancy_hold','occupied');
$x=$base;$x['pair_excluded']=true;ok(hmto_classify($x)==='pair_excluded','exclusion');
$x=$base;$x['exact_name']=false;$x['common_tokens']=1;$x['direct_geo']=false;ok(hmto_classify($x)==='needs_name_evidence','weak');
$x['direct_geo']=true;ok(hmto_classify($x)==='candidate_anex','weak plus geo');
echo "hotel_match_current_tv_anex_observations: {$checks} checks passed\n";
