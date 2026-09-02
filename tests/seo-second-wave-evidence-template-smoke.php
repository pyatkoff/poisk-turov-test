<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-second-wave-country-review-v1.php';

function second_wave_template_fail(string $message): never
{
    fwrite(STDERR,"SEO_SECOND_WAVE_EVIDENCE_TEMPLATE_FAIL:$message\n");
    exit(1);
}

$template=__DIR__.'/../v2/data/report-seo-second-wave-evidence-template-v1.php';
$lines=[];$code=0;
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($template).' 2>&1',$lines,$code);
if($code!==0)second_wave_template_fail('exit_'.$code.'_'.implode('|',$lines));
try{$payload=json_decode(implode("\n",$lines),true,512,JSON_THROW_ON_ERROR);}catch(JsonException){second_wave_template_fail('json');}
if(($payload['state']??'')!=='second_wave_external_evidence_collection_template')second_wave_template_fail('state');
if(($payload['scope']??'')!=='egypt_maldives_country_review_v1')second_wave_template_fail('scope');
if(($payload['scoring_policy_required']??false)!==true||($payload['scoring_policy_template_provided']??true)!==false)second_wave_template_fail('policy_boundary');
if(($payload['missing_evidence_semantics']??'')!=='unknown_not_zero')second_wave_template_fail('missing_semantics');
$demand=$payload['demand_file_payload']['rows']??null;
$uniqueness=$payload['uniqueness_file_payload']['rows']??null;
if(!is_array($demand)||!is_array($uniqueness)||count($demand)!==2||count($uniqueness)!==2)second_wave_template_fail('row_count');
$specs=v2_seo_second_wave_country_specs();
foreach($specs as $i=>$spec){
    $d=$demand[$i]??[];$u=$uniqueness[$i]??[];
    foreach([['page_key',(string)$spec['page_key']],['query_cluster',(string)$spec['query_cluster']]] as [$field,$expected]){
        if(($d[$field]??null)!==$expected||($u[$field]??null)!==$expected)second_wave_template_fail('identity_'.$field.'_'.$i);
    }
    if(($u['page_path']??null)!==(string)$spec['path'])second_wave_template_fail('path_'.$i);
    $dObservedNull=array_key_exists('observed_at_epoch',$d)&&$d['observed_at_epoch']===null;
    if(($d['source_class']??null)!==''||($d['source_ref']??null)!==''||($d['status']??null)!=='unknown'||!$dObservedNull||($d['serp_intent']??null)!=='')second_wave_template_fail('demand_blank_'.$i);
    foreach(['impressions','clicks','avg_position','monthly_searches'] as $metric){
        if(!array_key_exists($metric,$d['metrics']??[])||$d['metrics'][$metric]!==null)second_wave_template_fail('metric_not_null_'.$metric.'_'.$i);
    }
    $uObservedNull=array_key_exists('observed_at_epoch',$u)&&$u['observed_at_epoch']===null;
    $uOverlapNull=array_key_exists('overlap_ratio',$u)&&$u['overlap_ratio']===null;
    if(($u['source_class']??null)!==''||($u['source_ref']??null)!==''||($u['status']??null)!=='unknown'||!$uObservedNull||($u['decision']??null)!=='unknown'||!$uOverlapNull)second_wave_template_fail('uniqueness_blank_'.$i);
    if(($u['competing_paths']??null)!==[])second_wave_template_fail('competing_paths_'.$i);
}
foreach(['automatic_scoring_allowed','automatic_launch_allowed','publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed'] as $flag){
    if(($payload[$flag]??true)!==false)second_wave_template_fail('boundary_'.$flag);
}
if(($payload['publication_candidates']??null)!==[])second_wave_template_fail('publication_candidates');

echo "SEO_SECOND_WAVE_EVIDENCE_TEMPLATE_OK rows=2 metrics=null uniqueness=unknown policyDefaults=0 publication=0 hotelTours=0\n";
