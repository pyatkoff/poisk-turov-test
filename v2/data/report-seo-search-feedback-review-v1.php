<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-search-feedback-evidence-v1.php';
require_once __DIR__.'/../seo-search-feedback-policy-v1.php';

function v2_seo_search_feedback_review_cli_fail(string $message, int $code=2): never
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_REVIEW_CLI_FAIL:$message\n");
    exit($code);
}

$options=getopt('', ['feedback:','policy:','now-epoch::','require-review-ready']);
$feedbackPath=trim((string)($options['feedback']??''));
$policyPath=trim((string)($options['policy']??''));
if($feedbackPath==='')v2_seo_search_feedback_review_cli_fail('missing_feedback_file');
if($policyPath==='')v2_seo_search_feedback_review_cli_fail('missing_policy_file');

$readJson=static function(string $path,string $label):array{
    $raw=@file_get_contents($path);
    if($raw===false||trim($raw)==='')v2_seo_search_feedback_review_cli_fail('empty_'.$label.'_file');
    try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException $e){v2_seo_search_feedback_review_cli_fail('invalid_'.$label.'_json');}
    if(!is_array($decoded))v2_seo_search_feedback_review_cli_fail($label.'_must_be_object_or_array');
    return $decoded;
};

$feedbackInput=$readJson($feedbackPath,'feedback');
$policy=$readJson($policyPath,'policy');
$rows=array_is_list($feedbackInput)?$feedbackInput:($feedbackInput['rows']??null);
if(!is_array($rows))v2_seo_search_feedback_review_cli_fail('feedback_rows_must_be_array');
$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0)v2_seo_search_feedback_review_cli_fail('invalid_now_epoch');

$intake=v2_seo_search_feedback_intake($rows,(int)$now);
$review=v2_seo_search_feedback_review($intake,$policy,(int)$now);
$output=[
    'state'=>$review['state']??'search_feedback_review_blocked',
    'domain'=>$review['domain']??'anytoour.ru',
    'launch_scope'=>$review['launch_scope']??'turkey_country_resort_v1',
    'observed_count'=>$review['observed_count']??0,
    'missing_count'=>$review['missing_count']??0,
    'recommendations'=>$review['recommendations']??[],
    'missing'=>$review['missing']??[],
    'policy'=>$review['policy']??[],
    'feedback_intake_sha256'=>$review['feedback_intake_sha256']??'',
    'review_sha256'=>$review['review_sha256']??'',
    'errors'=>$review['errors']??[],
    'recommendation_semantics'=>'review_only_no_execution',
    'explicit_user_approval_required'=>true,
    'automatic_execution_allowed'=>false,
    'automatic_deindex_allowed'=>false,
    'publication_candidates'=>[],
    'publication_scope_expanded'=>false,
    'publication_allowed'=>false,
    'indexation_change_allowed'=>false,
    'sitemap_change_allowed'=>false,
    'canonical_change_allowed'=>false,
    'route_change_allowed'=>false,
    'hotel_tours_indexation_allowed'=>false,
    'search_contract_changes'=>false,
    'tourvisor_contract_changes'=>false,
    'pricing_contract_changes'=>false,
    'lead_contract_changes'=>false,
    'metrika_contract_changes'=>false,
];
echo json_encode($output,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(array_key_exists('require-review-ready',$options)&&($output['state']??'')!=='search_feedback_review_ready')exit(3);
