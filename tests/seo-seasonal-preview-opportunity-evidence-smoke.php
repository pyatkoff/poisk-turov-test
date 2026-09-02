<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-seasonal-preview-opportunity-evidence-v1.php';

function seasonal_serp_fail(string $message): never
{
    fwrite(STDERR,"SEO_SEASONAL_SERP_EVIDENCE_FAIL:$message\n");
    exit(1);
}

$file=__DIR__.'/../v2/data/evidence/seo-seasonal-manual-serp-2026-09-03.json';
$raw=file_get_contents($file);
if($raw===false)seasonal_serp_fail('snapshot_missing');
try{$snapshot=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){seasonal_serp_fail('snapshot_json');}
$rows=is_array($snapshot['rows']??null)?$snapshot['rows']:[];
$observed=(int)($snapshot['observed_at_epoch']??0);
if($observed!==1788391016||count($rows)!==2)seasonal_serp_fail('snapshot_identity');
foreach($rows as $row){
    if(($row['demand']['source_class']??'')!=='manual_serp_review')seasonal_serp_fail('demand_source');
    if(($row['uniqueness']['source_class']??'')!=='manual_serp_review')seasonal_serp_fail('uniqueness_source');
    if(($row['demand']['status']??'')!=='confirmed'||($row['demand']['serp_intent']??'')!=='commercial')seasonal_serp_fail('demand_intent');
    if(($row['uniqueness']['status']??'')!=='confirmed'||($row['uniqueness']['decision']??'')!=='distinct')seasonal_serp_fail('uniqueness_decision');
    if(isset($row['demand']['metrics'])&&$row['demand']['metrics']!==[])seasonal_serp_fail('fabricated_metrics');
    if(!str_contains((string)($row['demand']['source_ref']??''),'https://'))seasonal_serp_fail('source_ref');
}

$now=$observed+60;
$result=v2_seo_seasonal_preview_opportunity_evidence($rows,$now);
if(($result['state']??'')!=='review_only_seasonal_serp_evidence_ready'||($result['ready_count']??0)!==2)seasonal_serp_fail('ready');
foreach($result['rows'] as $row){
    if(($row['evidence_fresh']??false)!==true||($row['evidence_confirmed']??false)!==true||($row['uniqueness_distinct']??false)!==true)seasonal_serp_fail('row_evidence');
    if(($row['scoring_policy_pending']??false)!==true)seasonal_serp_fail('policy_pending');
}
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','automatic_execution_allowed'] as $flag){
    if(($result[$flag]??true)!==false)seasonal_serp_fail('boundary_'.$flag);
}
if(($result['publication_candidates']??null)!==[])seasonal_serp_fail('publication_candidates');

$stale=v2_seo_seasonal_preview_opportunity_evidence($rows,$observed+(86400*31)+1);
if(($stale['state']??'')!=='review_only_seasonal_serp_evidence_blocked'||($stale['ready_count']??-1)!==0)seasonal_serp_fail('stale_not_blocked');

$bad=$rows;$bad[0]['path']='/country/turkey/antalya/';
$blocked=v2_seo_seasonal_preview_opportunity_evidence($bad,$now);
if(($blocked['state']??'')!=='review_only_seasonal_serp_evidence_blocked'||!in_array('path_mismatch:antalya-september',$blocked['errors']??[],true))seasonal_serp_fail('path_binding');

$tmp=tempnam(sys_get_temp_dir(),'seasonal-serp-');file_put_contents($tmp,json_encode($snapshot,JSON_THROW_ON_ERROR));
$cli=__DIR__.'/../v2/data/report-seo-seasonal-preview-opportunity-evidence-v1.php';
$out=[];$code=0;
exec('php '.escapeshellarg($cli).' --input='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-ready 2>&1',$out,$code);
@unlink($tmp);
if($code!==0)seasonal_serp_fail('cli_exit_'.$code);
$cliResult=json_decode(implode("\n",$out),true);
if(!is_array($cliResult)||($cliResult['ready_count']??0)!==2)seasonal_serp_fail('cli_ready');

echo "SEO_SEASONAL_SERP_EVIDENCE_OK previews=2 commercialIntent=2 distinct=2 fabricatedMetrics=0 publication=0\n";
