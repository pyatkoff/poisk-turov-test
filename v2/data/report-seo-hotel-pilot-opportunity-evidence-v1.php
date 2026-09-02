<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-hotel-pilot-opportunity-evidence-intake-v1.php';

/**
 * Review-only CLI for externally collected demand + uniqueness evidence.
 * Reads JSON from --input=<file> or STDIN and never scores or publishes pages.
 */
function v2_seo_hotel_pilot_evidence_cli_fail(string $message, int $code=2): never
{
    fwrite(STDERR, "SEO_HOTEL_PILOT_EVIDENCE_CLI_FAIL:$message\n");
    exit($code);
}

$options=getopt('', ['input::','now-epoch::','require-ready']);
$inputPath=trim((string)($options['input']??''));
$raw=$inputPath!=='' ? @file_get_contents($inputPath) : file_get_contents('php://stdin');
if($raw===false || trim($raw)==='') v2_seo_hotel_pilot_evidence_cli_fail('empty_input');
try {
    $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    v2_seo_hotel_pilot_evidence_cli_fail('invalid_json');
}
if(!is_array($decoded)) v2_seo_hotel_pilot_evidence_cli_fail('input_must_be_array');
$rows=array_is_list($decoded) ? $decoded : ($decoded['rows']??null);
if(!is_array($rows)) v2_seo_hotel_pilot_evidence_cli_fail('rows_must_be_array');
$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null ? time() : filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false || $now<=0) v2_seo_hotel_pilot_evidence_cli_fail('invalid_now_epoch');

$report=v2_seo_hotel_pilot_opportunity_evidence_intake($rows,(int)$now);
$output=[
    'state'=>$report['state']??'review_only_pilot_evidence_intake_blocked',
    'row_count'=>$report['row_count']??0,
    'expected_count'=>$report['expected_count']??9,
    'ready_count'=>$report['ready_count']??0,
    'blocked_count'=>$report['blocked_count']??9,
    'intake_sha256'=>$report['intake_sha256']??'',
    'rows'=>$report['rows']??[],
    'errors'=>$report['errors']??[],
    'publication_candidates'=>[],
    'publication_allowed'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'canonical_launch_allowed'=>false,
    'route_launch_allowed'=>false,
    'explicit_user_indexation_approval_required'=>true,
];
echo json_encode($output,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(array_key_exists('require-ready',$options) && ($output['state']??'')!=='review_only_pilot_evidence_intake_ready') exit(3);
