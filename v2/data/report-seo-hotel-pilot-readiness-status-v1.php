<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/seo-hotel-pilot-readiness-status-v1.php';

function v2_seo_hotel_status_cli_fail(string $message,int $code=2): never
{
    fwrite(STDERR,"SEO_HOTEL_PILOT_STATUS_CLI_FAIL:$message\n");
    exit($code);
}

function v2_seo_hotel_status_cli_json(?string $file,bool $required=false): array
{
    $file=trim((string)$file);
    if($file===''){
        if($required)v2_seo_hotel_status_cli_fail('missing_required_input');
        return [];
    }
    $raw=@file_get_contents($file);
    if($raw===false||trim($raw)==='')v2_seo_hotel_status_cli_fail('unreadable_or_empty:'.$file);
    try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){v2_seo_hotel_status_cli_fail('invalid_json:'.$file);}
    if(!is_array($decoded))v2_seo_hotel_status_cli_fail('input_must_be_object:'.$file);
    return $decoded;
}

$options=getopt('',['live-evidence:','opportunity-evidence::','signals::','policy::','now-epoch::','require-status']);
$live=v2_seo_hotel_status_cli_json($options['live-evidence']??null,true);
$opportunity=v2_seo_hotel_status_cli_json($options['opportunity-evidence']??null,false);
$signals=v2_seo_hotel_status_cli_json($options['signals']??null,false);
$policy=v2_seo_hotel_status_cli_json($options['policy']??null,false);
$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0)v2_seo_hotel_status_cli_fail('invalid_now_epoch');

$result=v2_seo_hotel_pilot_readiness_status($live,$opportunity,$signals,$policy,(int)$now);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(isset($options['require-status'])&&($result['state']??'')!=='review_only_hotel_pilot_status_complete')exit(3);
exit(0);
