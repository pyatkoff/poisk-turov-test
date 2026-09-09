<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-phase5-seasonal-review-v1.php';
function phase5_season_fail(string $x): never { fwrite(STDERR,"SEO_PHASE5_SEASONAL_FAIL:$x\n"); exit(1); }
$now=1800000000;
$chain=['state'=>'fresh_evidence_chain_ready_for_expansion_review','expansion_review_allowed'=>true,'publication_allowed'=>false,'hotel_tours_publication_allowed'=>false,'hotel_tours_indexation_allowed'=>false,'hotel_tours_sitemap_allowed'=>false,'hotel_tours_canonical_launch_allowed'=>false,'hotel_tours_route_launch_allowed'=>false,'hotel_tours_publication_candidates'=>[]];
$page=['page_key'=>'resort_month:1:4:20:2026-09','page_role'=>'commercial_tour_landing','search_intent'=>'commercial_transactional','path'=>'/_preview/seo2/seasonal/antalya-september/','search_state'=>['country'=>4,'region'=>20],'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_allowed'=>false,'route_launch_allowed'=>false,'publication_candidates'=>[]];
$signals=[];foreach(['demand','uniqueness','content','technical','commercial_inventory'] as $key)$signals[$key]=['status'=>'confirmed','score'=>90,'observed_at_epoch'=>$now-60,'source'=>'fixture:'.$key];
$r=v2_seo_phase5_seasonal_review($chain,$page,$signals,$now);
if(($r['state']??'')!=='phase5_seasonal_review_ready'||($r['review_allowed']??false)!==true)phase5_season_fail('ready');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_publication_allowed','hotel_tours_indexation_allowed'] as $flag)if(($r[$flag]??true)!==false)phase5_season_fail($flag);
$missing=$signals;unset($missing['demand']);if((v2_seo_phase5_seasonal_review($chain,$page,$missing,$now)['state']??'')!=='phase5_seasonal_review_blocked')phase5_season_fail('missing_demand');
$blockedChain=$chain;$blockedChain['state']='fresh_evidence_chain_blocked';if((v2_seo_phase5_seasonal_review($blockedChain,$page,$signals,$now)['state']??'')!=='phase5_seasonal_review_blocked')phase5_season_fail('upstream');
$stale=$signals;$stale['commercial_inventory']['observed_at_epoch']=$now-(86400*3)-1;if((v2_seo_phase5_seasonal_review($chain,$page,$stale,$now)['state']??'')!=='phase5_seasonal_review_blocked')phase5_season_fail('stale_inventory');
echo "SEO_PHASE5_SEASONAL_OK review=1 launch=0 hotel_tours=excluded\n";
