<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_sold_details_accept.php';
function t(bool $ok,string $m): void { if(!$ok) throw new RuntimeException($m); }
$base=['distance_m'=>100.0,'place_match'=>true,'andromeda_bridge'=>false,'geo_strong'=>true,'score'=>0.90,'name'=>['critical_ok'=>true,'shared'=>2,'score'=>0.70]];
t(hmasda_candidate_safe($base,0.20),'direct 250 should pass');
$x=$base;$x['distance_m']=300.0;t(!hmasda_candidate_safe($x,0.20),'non-bridge 300m must fail');
$x=$base;$x['distance_m']=null;$x['andromeda_bridge']=true;$x['name']['score']=0.80;t(hmasda_candidate_safe($x,0.20),'bridge+place+strong name without coords should pass');
$x['name']['score']=0.74;t(!hmasda_candidate_safe($x,0.20),'bridge weak name must fail');
$x=$base;$x['distance_m']=900.0;$x['andromeda_bridge']=true;$x['name']['score']=0.80;t(hmasda_candidate_safe($x,0.20),'bridge+place <=1km should pass');
$x['distance_m']=1001.0;t(!hmasda_candidate_safe($x,0.20),'bridge >1km must fail');
$x=$base;$x['distance_m']=6000.0;$x['andromeda_bridge']=true;$x['name']['score']=0.90;t(!hmasda_candidate_safe($x,0.20),'>5km must fail');
$x=$base;$x['name']['critical_ok']=false;t(!hmasda_candidate_safe($x,0.20),'qualifier conflict must fail');
$x=$base;$x['name']['shared']=1;t(!hmasda_candidate_safe($x,0.20),'one shared token must fail');
$x=$base;t(!hmasda_candidate_safe($x,0.05),'small margin must fail');
$variants=hmasda_target_names('ALPHA HOTEL (EX. BETA BEACH)',['GAMMA RESORT']);t(in_array('BETA BEACH',$variants,true),'former name variant missing');t(in_array('GAMMA RESORT',$variants,true),'alias variant missing');
echo "hotel-match-anex-sold-details-accept-test: OK\n";
