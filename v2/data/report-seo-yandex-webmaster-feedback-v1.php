<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-yandex-webmaster-feedback-v1.php';

function v2_seo_yandex_webmaster_cli_fail(string $message, int $code=2): never
{
    fwrite(STDERR,"SEO_YANDEX_WEBMASTER_FEEDBACK_CLI_FAIL:$message\n");
    exit($code);
}

$options=getopt('', ['input:','collected-at::','require-clean']);
$path=trim((string)($options['input']??''));
if($path===''||!is_file($path))v2_seo_yandex_webmaster_cli_fail('input_missing');
$raw=file_get_contents($path);
if($raw===false||trim($raw)==='')v2_seo_yandex_webmaster_cli_fail('input_empty');
try{$payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){v2_seo_yandex_webmaster_cli_fail('input_invalid_json');}
if(!is_array($payload))v2_seo_yandex_webmaster_cli_fail('input_must_be_object');
$collectedRaw=$options['collected-at']??null;
$collected=$collectedRaw===null?time():filter_var($collectedRaw,FILTER_VALIDATE_INT);
if($collected===false||$collected<=0)v2_seo_yandex_webmaster_cli_fail('collected_at_invalid');
$result=v2_seo_yandex_webmaster_feedback($payload,(int)$collected);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(array_key_exists('require-clean',$options)&&($result['state']??'')!=='yandex_webmaster_feedback_ready')exit(3);
