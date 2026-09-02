<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-search-feedback-evidence-v1.php';

/**
 * CLI boundary for real search-performance exports covering the exact eight
 * country/resort URLs in the controlled anytoour.ru SEO launch. This command
 * validates evidence only; it never invents missing metrics and never changes
 * indexation/publication.
 */
function v2_seo_search_feedback_cli_fail(string $message, int $code=2): never
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_CLI_FAIL:$message\n");
    exit($code);
}

$options=getopt('', ['input::','now-epoch::','require-ready']);
$inputPath=trim((string)($options['input']??''));
$raw=$inputPath!=='' ? @file_get_contents($inputPath) : file_get_contents('php://stdin');
if($raw===false||trim($raw)==='') v2_seo_search_feedback_cli_fail('empty_input');

try {
    $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    v2_seo_search_feedback_cli_fail('invalid_json');
}
if(!is_array($decoded)) v2_seo_search_feedback_cli_fail('input_must_be_array');
$rows=array_is_list($decoded)?$decoded:($decoded['rows']??null);
if(!is_array($rows)) v2_seo_search_feedback_cli_fail('rows_must_be_array');

$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0) v2_seo_search_feedback_cli_fail('invalid_now_epoch');

$report=v2_seo_search_feedback_intake($rows,(int)$now);
$output=[
    'state'=>$report['state']??'search_feedback_intake_blocked',
    'domain'=>$report['domain']??'anytoour.ru',
    'launch_scope'=>$report['launch_scope']??'controlled_country_resort_v2',
    'launched_path_count'=>$report['launched_path_count']??8,
    'observed_count'=>$report['observed_count']??0,
    'observed_paths'=>$report['observed_paths']??[],
    'missing_paths'=>$report['missing_paths']??[],
    'missing_feedback_semantics'=>$report['missing_feedback_semantics']??'unknown_not_zero',
    'rows'=>$report['rows']??[],
    'feedback_intake_sha256'=>$report['feedback_intake_sha256']??'',
    'errors'=>$report['errors']??[],
    'recommendation_state'=>$report['recommendation_state']??'requires_explicit_feedback_policy',
    'automatic_recommendation_allowed'=>false,
    'automatic_deindex_allowed'=>false,
    'publication_candidates'=>[],
    'publication_allowed'=>false,
    'indexation_change_allowed'=>false,
    'sitemap_change_allowed'=>false,
    'canonical_change_allowed'=>false,
    'route_change_allowed'=>false,
    'hotel_tours_indexation_allowed'=>false,
];
echo json_encode($output,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";

if(array_key_exists('require-ready',$options)&&($output['state']??'')!=='search_feedback_intake_ready') exit(3);
