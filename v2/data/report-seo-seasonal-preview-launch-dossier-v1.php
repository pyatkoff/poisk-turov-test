<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/seo-seasonal-preview-launch-dossier-v1.php';

function seasonal_dossier_cli_fail(string $message,int $code=2): never
{
    fwrite(STDERR,"SEO_SEASONAL_LAUNCH_DOSSIER_CLI_FAIL:$message\n");
    exit($code);
}

function seasonal_dossier_cli_json(string $file,bool $required): array
{
    $file=trim($file);
    if($file===''){
        if($required)seasonal_dossier_cli_fail('missing_required_input');
        return [];
    }
    $raw=@file_get_contents($file);
    if($raw===false||trim($raw)==='')seasonal_dossier_cli_fail('unreadable_or_empty:'.$file);
    try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){seasonal_dossier_cli_fail('invalid_json:'.$file);}
    if(!is_array($decoded))seasonal_dossier_cli_fail('input_must_be_object:'.$file);
    return $decoded;
}

$options=getopt('',['serp-evidence:','identity-evidence::','now-epoch::','require-go-review']);
$serpSnapshot=seasonal_dossier_cli_json((string)($options['serp-evidence']??''),true);
$serpRows=is_array($serpSnapshot['rows']??null)?$serpSnapshot['rows']:(array_is_list($serpSnapshot)?$serpSnapshot:null);
if(!is_array($serpRows))seasonal_dossier_cli_fail('serp_rows_missing');
$identity=seasonal_dossier_cli_json((string)($options['identity-evidence']??''),false);
$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0)seasonal_dossier_cli_fail('invalid_now_epoch');

$result=v2_seo_seasonal_preview_launch_dossier($serpRows,$identity,(int)$now);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(isset($options['require-go-review'])&&($result['all_go_review']??false)!==true)exit(3);
exit(0);
