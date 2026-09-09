<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-phase5-expansion-review-gate-v1.php';
function phase5_fail(string $x): never { fwrite(STDERR,"SEO_PHASE5_EXPANSION_GATE_FAIL:$x\n"); exit(1); }
$chain=[
 'state'=>'fresh_evidence_chain_ready_for_expansion_review','expansion_review_allowed'=>true,
 'publication_allowed'=>false,'hotel_tours_publication_allowed'=>false,'hotel_tours_indexation_allowed'=>false,'hotel_tours_sitemap_allowed'=>false,
 'hotel_tours_canonical_launch_allowed'=>false,'hotel_tours_route_launch_allowed'=>false,'hotel_tours_publication_candidates'=>[],
];
$candidate=['kind'=>'seasonal','review_ready'=>true,'fresh_evidence'=>true,'source_ref'=>'fixture://seasonal/review','publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_launch_allowed'=>false];
$r=v2_seo_phase5_expansion_review_gate($chain,$candidate);
if(($r['state']??'')!=='phase5_expansion_review_ready'||($r['review_allowed']??false)!==true)phase5_fail('ready');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_publication_allowed','hotel_tours_indexation_allowed'] as $flag)if(($r[$flag]??true)!==false)phase5_fail($flag);
$stale=$candidate;$stale['fresh_evidence']=false;if((v2_seo_phase5_expansion_review_gate($chain,$stale)['state']??'')!=='phase5_expansion_review_blocked')phase5_fail('stale');
$badChain=$chain;$badChain['state']='fresh_evidence_chain_blocked';if((v2_seo_phase5_expansion_review_gate($badChain,$candidate)['state']??'')!=='phase5_expansion_review_blocked')phase5_fail('upstream');
$hotel=$candidate;$hotel['kind']='hotel_tours';if((v2_seo_phase5_expansion_review_gate($chain,$hotel)['state']??'')!=='phase5_expansion_review_blocked')phase5_fail('hotel_scope');
echo "SEO_PHASE5_EXPANSION_GATE_OK kinds=resort,seasonal,data,feed hotel_tours=excluded publication=0\n";
