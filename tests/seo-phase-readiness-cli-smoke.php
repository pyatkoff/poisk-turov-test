<?php
declare(strict_types=1);
function cli_fail(string $x): never { fwrite(STDERR,"SEO_PHASE_READINESS_CLI_FAIL:$x\n"); exit(1); }
$ready=[
    'manifest'=>[
        'integrity_ok'=>true,'family_quality_floor'=>100,
        'quality_by_type'=>['country'=>['quality_score'=>100],'resort'=>['quality_score'=>100],'hotel_tours'=>['quality_score'=>100]],
    ],
    'production_identity'=>['integrity_ok'=>true,'source'=>'live_http_collector','hotel_tours_indexation_allowed'=>false],
    'ds2_render_evidence'=>['state'=>'review_only_ds2_render_evidence_ready','expected_capture_count'=>10,'observed_capture_count'=>10,'hotel_tours_indexation_allowed'=>false],
    'hotel_pilot_evidence'=>['state'=>'review_only_hotel_pilot_evidence_ready','expected_hotel_count'=>9,'observed_hotel_count'=>9,'publication_candidates'=>[],'indexation_allowed'=>false],
];
$dir=sys_get_temp_dir();$ok=$dir.'/seo-phase-ready.json';$bad=$dir.'/seo-phase-blocked.json';
file_put_contents($ok,json_encode($ready,JSON_THROW_ON_ERROR));
$blocked=$ready;$blocked['production_identity']['integrity_ok']=false;file_put_contents($bad,json_encode($blocked,JSON_THROW_ON_ERROR));
$cli=escapeshellarg(__DIR__.'/../v2/data/report-seo-phase-readiness-v1.php');
exec('php '.$cli.' --input='.escapeshellarg($ok).' --require-phase4 2>&1',$out,$status);
if($status!==0) cli_fail('ready_status');
$decoded=json_decode(implode("\n",$out),true);
if(($decoded['state']??'')!=='phase_4_review_ready_expansion_review_may_begin') cli_fail('ready_state');
$out=[];exec('php '.$cli.' --input='.escapeshellarg($bad).' --require-phase4 2>&1',$out,$status);
if($status!==2) cli_fail('blocked_status');
$decoded=json_decode(implode("\n",$out),true);
if(($decoded['blocked_stage']??'')!=='production_identity') cli_fail('blocked_stage');
@unlink($ok);@unlink($bad);
echo "SEO_PHASE_READINESS_CLI_OK ready=0 blocked_exit=2 hotel_tours_indexation=0\n";
