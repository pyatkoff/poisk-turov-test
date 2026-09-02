<?php
declare(strict_types=1);
function chain_cli_fail(string $x): never { fwrite(STDERR,"SEO_EVIDENCE_CHAIN_CLI_FAIL:$x\n"); exit(1); }
$now=1788372000;$sha=str_repeat('b',64);
$p=['manifest'=>['integrity_ok'=>true,'family_quality_floor'=>100,'quality_by_type'=>['country'=>['quality_score'=>100],'resort'=>['quality_score'=>100],'hotel_tours'=>['quality_score'=>100]]],'production_identity'=>['integrity_ok'=>true,'source'=>'live_http_collector','observed_at_epoch'=>$now-60,'identity_registry_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false],'ds2_render_evidence'=>['state'=>'review_only_ds2_render_evidence_ready','expected_capture_count'=>10,'observed_capture_count'=>10,'evidence_sha256'=>$sha,'hotel_tours_indexation_allowed'=>false,'rows'=>[]],'hotel_pilot_evidence'=>['state'=>'review_only_hotel_pilot_evidence_ready','expected_hotel_count'=>9,'observed_hotel_count'=>9,'dossier_sha256'=>$sha,'publication_candidates'=>[],'indexation_allowed'=>false,'rows'=>[]]];
for($i=0;$i<10;$i++)$p['ds2_render_evidence']['rows'][]=['captured_at_epoch'=>$now-60,'fresh'=>true];
for($i=0;$i<9;$i++)$p['hotel_pilot_evidence']['rows'][]=['captured_at_epoch'=>$now-60,'fresh'=>true];
$f=sys_get_temp_dir().'/seo-chain-cli.json';file_put_contents($f,json_encode($p,JSON_THROW_ON_ERROR));$cli=escapeshellarg(__DIR__.'/../v2/data/report-seo-evidence-freshness-chain-v1.php');
exec('php '.$cli.' --input='.escapeshellarg($f).' --now-epoch='.$now.' --require-ready 2>&1',$out,$status);if($status!==0)chain_cli_fail('ready');
$p['production_identity']['observed_at_epoch']=$now-86401;file_put_contents($f,json_encode($p,JSON_THROW_ON_ERROR));$out=[];exec('php '.$cli.' --input='.escapeshellarg($f).' --now-epoch='.$now.' --require-ready 2>&1',$out,$status);if($status!==2)chain_cli_fail('stale_exit');
@unlink($f);echo "SEO_EVIDENCE_CHAIN_CLI_OK ready=1 stale_exit=2 publication=0\n";
