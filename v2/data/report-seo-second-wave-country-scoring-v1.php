<?php
declare(strict_types=1);

require_once __DIR__.'/../seo-second-wave-country-scoring-review-v1.php';
require_once __DIR__.'/../seo-opportunity-evidence-packet-v1.php';

function v2_seo_second_wave_scoring_cli_fail(string $message, int $code=2): never
{
    fwrite(STDERR,"SEO_SECOND_WAVE_SCORING_CLI_FAIL:$message\n");
    exit($code);
}

$options=getopt('',[
    'identity:',
    'demand:',
    'uniqueness:',
    'policy:',
    'now-epoch::',
    'require-scored',
]);

$paths=[];
foreach(['identity','demand','uniqueness','policy'] as $name){
    $path=trim((string)($options[$name]??''));
    if($path==='')v2_seo_second_wave_scoring_cli_fail('missing_'.$name.'_file');
    $paths[$name]=$path;
}

$readJson=static function(string $path,string $label):array{
    $raw=@file_get_contents($path);
    if($raw===false||trim($raw)==='')v2_seo_second_wave_scoring_cli_fail('empty_'.$label.'_file');
    try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
    catch(JsonException){v2_seo_second_wave_scoring_cli_fail('invalid_'.$label.'_json');}
    if(!is_array($decoded))v2_seo_second_wave_scoring_cli_fail($label.'_must_be_object_or_array');
    return $decoded;
};

$identity=$readJson($paths['identity'],'identity');
$demandInput=$readJson($paths['demand'],'demand');
$uniquenessInput=$readJson($paths['uniqueness'],'uniqueness');
$policy=$readJson($paths['policy'],'policy');

$nowRaw=$options['now-epoch']??null;
$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0)v2_seo_second_wave_scoring_cli_fail('invalid_now_epoch');

$normalizeRows=static function(array $input,string $label):array{
    $rows=array_is_list($input)?$input:($input['rows']??null);
    if(!is_array($rows))v2_seo_second_wave_scoring_cli_fail($label.'_rows_must_be_array');
    $byKey=[];
    foreach($rows as $i=>$row){
        if(!is_array($row))v2_seo_second_wave_scoring_cli_fail($label.'_row_not_object_'.$i);
        $key=trim((string)($row['page_key']??''));
        if($key==='')v2_seo_second_wave_scoring_cli_fail($label.'_missing_page_key_'.$i);
        if(isset($byKey[$key]))v2_seo_second_wave_scoring_cli_fail($label.'_duplicate_page_key_'.$key);
        $byKey[$key]=$row;
    }
    ksort($byKey,SORT_STRING);
    return $byKey;
};

$demandByKey=$normalizeRows($demandInput,'demand');
$uniquenessByKey=$normalizeRows($uniquenessInput,'uniqueness');
$specs=v2_seo_second_wave_country_specs();
$expectedKeys=[];
foreach($specs as $spec)$expectedKeys[]=(string)$spec['page_key'];
sort($expectedKeys,SORT_STRING);
if(array_keys($demandByKey)!==$expectedKeys)v2_seo_second_wave_scoring_cli_fail('demand_page_key_set_mismatch');
if(array_keys($uniquenessByKey)!==$expectedKeys)v2_seo_second_wave_scoring_cli_fail('uniqueness_page_key_set_mismatch');

$packets=[];
foreach($specs as $spec){
    $page=[
        'page_key'=>(string)$spec['page_key'],
        'path'=>(string)$spec['path'],
        'query_cluster'=>(string)$spec['query_cluster'],
    ];
    $packets[$page['path']]=v2_seo_opportunity_evidence_packet(
        $page,
        $demandByKey[$page['page_key']],
        $uniquenessByKey[$page['page_key']],
        (int)$now
    );
}

$review=v2_seo_second_wave_country_scoring_review($identity,$packets,$policy,(int)$now);
$output=[
    'state'=>$review['state']??'second_wave_country_scoring_blocked',
    'domain'=>$review['domain']??'anytoour.ru',
    'country_count'=>$review['country_count']??2,
    'scored_count'=>$review['scored_count']??0,
    'score_summary'=>$review['score_summary']??['count'=>0,'min'=>null,'avg'=>null,'max'=>null],
    'rows'=>$review['rows']??[],
    'base_review_sha256'=>$review['base_review_sha256']??'',
    'identity_sha256'=>$review['identity_sha256']??'',
    'identity_remaining_seconds'=>$review['identity_remaining_seconds']??0,
    'policy_sha256'=>$review['policy_sha256']??'',
    'scoring_review_sha256'=>$review['scoring_review_sha256']??'',
    'errors'=>$review['errors']??[],
    'launch_decision_state'=>$review['launch_decision_state']??'requires_separate_launch_dossier',
    'input_semantics'=>'external_evidence_only_no_defaults',
    'scoring_semantics'=>'review_only_no_launch_execution',
    'explicit_user_launch_approval_required'=>true,
    'publication_candidates'=>[],
    'publication_scope_expanded'=>false,
    'publication_allowed'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'canonical_launch_allowed'=>false,
    'route_launch_allowed'=>false,
    'hotel_tours_indexation_allowed'=>false,
    'hotel_tours_sitemap_allowed'=>false,
    'search_contract_changes'=>false,
    'tourvisor_contract_changes'=>false,
    'pricing_contract_changes'=>false,
    'lead_contract_changes'=>false,
    'metrika_contract_changes'=>false,
];

echo json_encode($output,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(array_key_exists('require-scored',$options)&&($output['state']??'')!=='second_wave_country_scored_review_only')exit(3);
