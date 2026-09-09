<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-evidence-freshness-chain-v1.php';
function freshness_contract_fail(string $x): never { fwrite(STDERR,"SEO_FRESHNESS_CONTRACT_FAIL:$x\n"); exit(1); }
$now=1788372000;$sha=str_repeat('c',64);
$manifest=['integrity_ok'=>true,'family_quality_floor'=>100,'quality_by_type'=>['country'=>['quality_score'=>100],'resort'=>['quality_score'=>100],'hotel_tours'=>['quality_score'=>100]]];
$identity=['integrity_ok'=>true,'source'=>'live_http_collector','observed_at_epoch'=>$now-1,'identity_registry_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false];
$render=['state'=>'review_only_ds2_render_evidence_ready','expected_capture_count'=>10,'observed_capture_count'=>10,'evidence_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false,'rows'=>array_fill(0,10,['captured_at_epoch'=>$now-1,'fresh'=>true])];
$pilot=['state'=>'review_only_hotel_pilot_evidence_ready','expected_hotel_count'=>9,'observed_hotel_count'=>9,'dossier_sha256'=>$sha,'publication_candidates'=>[],'indexation_allowed'=>false,'rows'=>array_fill(0,9,['captured_at_epoch'=>$now-1,'fresh'=>true])];
$r=v2_seo_evidence_freshness_chain($manifest,$identity,$render,$pilot,$now);
foreach(['publication_allowed','hotel_tours_publication_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','hotel_tours_canonical_launch_allowed','hotel_tours_route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)freshness_contract_fail($flag);
if(($r['hotel_tours_publication_candidates']??null)!==[]||($r['separate_user_hotel_indexation_approval_required']??false)!==true)freshness_contract_fail('hotel_boundary');
echo "SEO_FRESHNESS_CONTRACT_OK hotel_tours=review_noindex_out_of_sitemap\n";
