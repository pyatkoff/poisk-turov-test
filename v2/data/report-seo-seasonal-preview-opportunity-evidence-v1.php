<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/seo-seasonal-preview-opportunity-evidence-v1.php';

function seasonal_serp_cli_fail(string $message,int $code=2): never
{
    fwrite(STDERR,"SEO_SEASONAL_SERP_EVIDENCE_CLI_FAIL:$message\n");
    exit($code);
}

$options=getopt('',['input:','now-epoch::','require-ready']);
$file=trim((string)($options['input']??''));
if($file==='')seasonal_serp_cli_fail('missing_input');
$raw=@file_get_contents($file);
if($raw===false||trim($raw)==='')seasonal_serp_cli_fail('unreadable_or_empty_input');
try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){seasonal_serp_cli_fail('invalid_json');}
if(!is_array($decoded))seasonal_serp_cli_fail('input_must_be_object');
$rows=is_array($decoded['rows']??null)?$decoded['rows']:(array_is_list($decoded)?$decoded:null);
if(!is_array($rows))seasonal_serp_cli_fail('rows_must_be_array');
$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0)seasonal_serp_cli_fail('invalid_now_epoch');
$result=v2_seo_seasonal_preview_opportunity_evidence($rows,(int)$now);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(isset($options['require-ready'])&&($result['state']??'')!=='review_only_seasonal_serp_evidence_ready')exit(3);
exit(0);
