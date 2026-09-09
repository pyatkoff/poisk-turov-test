<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-evidence-freshness-chain-v1.php';
$input=null;$requireReady=false;$now=null;
foreach(array_slice($argv,1) as $arg){
    if(str_starts_with($arg,'--input='))$input=substr($arg,8);
    elseif(str_starts_with($arg,'--now-epoch='))$now=(int)substr($arg,12);
    elseif($arg==='--require-ready')$requireReady=true;
    else { fwrite(STDERR,"Unknown argument: $arg\n"); exit(64); }
}
$raw=$input!==null?(string)@file_get_contents($input):(string)stream_get_contents(STDIN);
try{$p=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){fwrite(STDERR,"SEO_EVIDENCE_CHAIN_INPUT_FAIL: invalid JSON\n");exit(65);}
if(!is_array($p))exit(65);
foreach(['manifest','production_identity','ds2_render_evidence','hotel_pilot_evidence'] as $k)if(!is_array($p[$k]??null)){fwrite(STDERR,"SEO_EVIDENCE_CHAIN_INPUT_FAIL: missing $k\n");exit(65);}
$r=v2_seo_evidence_freshness_chain($p['manifest'],$p['production_identity'],$p['ds2_render_evidence'],$p['hotel_pilot_evidence'],$now);
echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if($requireReady&&(($r['expansion_review_allowed']??false)!==true))exit(2);
