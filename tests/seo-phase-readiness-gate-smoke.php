<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-phase-readiness-gate-v1.php';
require_once __DIR__.'/../v2/seo-evidence-freshness-chain-v1.php';
function phase_fail(string $x): never { fwrite(STDERR,"SEO_PHASE_READINESS_GATE_FAIL:$x\n"); exit(1); }

$now=1788372000;$sha=str_repeat('a',64);
$manifest=['integrity_ok'=>true,'family_quality_floor'=>100,'quality_by_type'=>['country'=>['quality_score'=>100],'resort'=>['quality_score'=>100],'hotel_tours'=>['quality_score'=>100]]];
$identity=['integrity_ok'=>true,'source'=>'live_http_collector','observed_at_epoch'=>$now-60,'identity_registry_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false];
$render=['state'=>'review_only_ds2_render_evidence_ready','expected_capture_count'=>10,'observed_capture_count'=>10,'evidence_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false,'rows'=>[]];
for($i=0;$i<10;$i++)$render['rows'][]=['captured_at_epoch'=>$now-60,'fresh'=>true];
$pilot=['state'=>'review_only_hotel_pilot_evidence_ready','expected_hotel_count'=>9,'observed_hotel_count'=>9,'dossier_sha256'=>$sha,'publication_candidates'=>[],'indexation_allowed'=>false,'rows'=>[]];
for($i=0;$i<9;$i++)$pilot['rows'][]=['captured_at_epoch'=>$now-60,'fresh'=>true];

$r=v2_seo_phase_readiness_gate($manifest,$identity,$render,$pilot);
if(($r['state']??'')!=='phase_4_review_ready_expansion_review_may_begin'||($r['expansion_review_allowed']??false)!==true)phase_fail('ready_state');
foreach(['publication_allowed','hotel_tours_publication_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','hotel_tours_canonical_launch_allowed','hotel_tours_route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)phase_fail('boundary_'.$flag);
if(($r['hotel_tours_publication_candidates']??null)!==[])phase_fail('hotel_candidates');

$chain=v2_seo_evidence_freshness_chain($manifest,$identity,$render,$pilot,$now);
if(($chain['state']??'')!=='fresh_evidence_chain_ready_for_expansion_review'||($chain['expansion_review_allowed']??false)!==true)phase_fail('fresh_chain');
$stale=$render;$stale['rows'][0]['captured_at_epoch']=$now-86401;
if((v2_seo_evidence_freshness_chain($manifest,$identity,$stale,$pilot,$now)['state']??'')!=='fresh_evidence_chain_blocked')phase_fail('stale_render_not_blocked');
$noHash=$identity;unset($noHash['identity_registry_sha256']);
if((v2_seo_evidence_freshness_chain($manifest,$noHash,$render,$pilot,$now)['state']??'')!=='fresh_evidence_chain_blocked')phase_fail('missing_hash_not_blocked');
$bad=$pilot;$bad['publication_candidates']=['/forbidden/'];
if((v2_seo_evidence_freshness_chain($manifest,$identity,$render,$bad,$now)['state']??'')!=='fresh_evidence_chain_blocked')phase_fail('publication_boundary');

echo "SEO_PHASE_READINESS_GATE_OK stages=4 freshness_chain=1 hotel_tours_indexation=0\n";
