<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-evidence-freshness-chain-v1.php';
function chain_fail(string $x): never { fwrite(STDERR,"SEO_EVIDENCE_CHAIN_FAIL:$x\n"); exit(1); }
$now=1788372000;$sha=str_repeat('a',64);
$manifest=['integrity_ok'=>true,'family_quality_floor'=>100,'quality_by_type'=>['country'=>['quality_score'=>100],'resort'=>['quality_score'=>100],'hotel_tours'=>['quality_score'=>100]]];
$identity=['integrity_ok'=>true,'source'=>'live_http_collector','observed_at_epoch'=>$now-60,'identity_registry_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false];
$render=['state'=>'review_only_ds2_render_evidence_ready','expected_capture_count'=>10,'observed_capture_count'=>10,'evidence_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false,'rows'=>[]];
for($i=0;$i<10;$i++)$render['rows'][]=['captured_at_epoch'=>$now-60,'fresh'=>true];
$pilot=['state'=>'review_only_hotel_pilot_evidence_ready','expected_hotel_count'=>9,'observed_hotel_count'=>9,'dossier_sha256'=>$sha,'publication_candidates'=>[],'indexation_allowed'=>false,'rows'=>[]];
for($i=0;$i<9;$i++)$pilot['rows'][]=['captured_at_epoch'=>$now-60,'fresh'=>true];
$r=v2_seo_evidence_freshness_chain($manifest,$identity,$render,$pilot,$now);
if(($r['state']??'')!=='fresh_evidence_chain_ready_for_expansion_review'||($r['expansion_review_allowed']??false)!==true)chain_fail('ready');
$stale=$render;$stale['rows'][0]['captured_at_epoch']=$now-86401;
if((v2_seo_evidence_freshness_chain($manifest,$identity,$stale,$pilot,$now)['state']??'')!=='fresh_evidence_chain_blocked')chain_fail('stale');
$bad=$pilot;$bad['publication_candidates']=['/hotel/x/'];
if((v2_seo_evidence_freshness_chain($manifest,$identity,$render,$bad,$now)['state']??'')!=='fresh_evidence_chain_blocked')chain_fail('publication_boundary');
echo "SEO_EVIDENCE_CHAIN_OK fresh=1 stale_blocks=1 hotel_tours_indexation=0\n";
