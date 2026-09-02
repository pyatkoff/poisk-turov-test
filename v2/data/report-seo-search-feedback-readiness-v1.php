<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-search-feedback-readiness-v1.php';

function v2_seo_search_feedback_readiness_cli_fail(string $message, int $code=2): never
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_READINESS_CLI_FAIL:$message\n");
    exit($code);
}

$options=getopt('', ['feedback::','policy::','now-epoch::','require-review-ready']);
$feedbackPath=trim((string)($options['feedback']??''));
$policyPath=trim((string)($options['policy']??''));
$rows=[];$policy=[];

if($feedbackPath!==''){
    $raw=@file_get_contents($feedbackPath);
    if($raw===false||trim($raw)==='')v2_seo_search_feedback_readiness_cli_fail('feedback_empty');
    try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){v2_seo_search_feedback_readiness_cli_fail('feedback_invalid_json');}
    if(!is_array($decoded))v2_seo_search_feedback_readiness_cli_fail('feedback_must_be_array');
    $rows=array_is_list($decoded)?$decoded:($decoded['rows']??null);
    if(!is_array($rows))v2_seo_search_feedback_readiness_cli_fail('feedback_rows_must_be_array');
}
if($policyPath!==''){
    $raw=@file_get_contents($policyPath);
    if($raw===false||trim($raw)==='')v2_seo_search_feedback_readiness_cli_fail('policy_empty');
    try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){v2_seo_search_feedback_readiness_cli_fail('policy_invalid_json');}
    if(!is_array($decoded))v2_seo_search_feedback_readiness_cli_fail('policy_must_be_object');
    $policy=$decoded;
}
$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0)v2_seo_search_feedback_readiness_cli_fail('invalid_now_epoch');

$result=v2_seo_search_feedback_readiness($rows,$policy,(int)$now);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(array_key_exists('require-review-ready',$options)&&($result['review_ready']??false)!==true)exit(3);
