<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-phase-readiness-gate-v1.php';

$inputPath=null;$requirePhase4=false;
foreach(array_slice($argv,1) as $arg){
    if(str_starts_with($arg,'--input='))$inputPath=substr($arg,8);
    elseif($arg==='--require-phase4')$requirePhase4=true;
    else { fwrite(STDERR,"Unknown argument: $arg\n"); exit(64); }
}

$raw='';
if($inputPath!==null){
    $raw=(string)@file_get_contents($inputPath);
    if($raw===''){ fwrite(STDERR,"SEO_PHASE_READINESS_INPUT_FAIL: cannot read input\n"); exit(65); }
}else{
    $raw=(string)stream_get_contents(STDIN);
}

try{
    $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    fwrite(STDERR,"SEO_PHASE_READINESS_INPUT_FAIL: invalid JSON\n");
    exit(65);
}
if(!is_array($payload)){ fwrite(STDERR,"SEO_PHASE_READINESS_INPUT_FAIL: object required\n"); exit(65); }

foreach(['manifest','production_identity','ds2_render_evidence','hotel_pilot_evidence'] as $key){
    if(!is_array($payload[$key]??null)){
        fwrite(STDERR,"SEO_PHASE_READINESS_INPUT_FAIL: missing $key\n");
        exit(65);
    }
}

$result=v2_seo_phase_readiness_gate(
    $payload['manifest'],
    $payload['production_identity'],
    $payload['ds2_render_evidence'],
    $payload['hotel_pilot_evidence']
);
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if($requirePhase4 && (($result['expansion_review_allowed']??false)!==true)) exit(2);
exit(0);
