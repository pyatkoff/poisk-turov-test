<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-seasonal-preview-launch-dossier-v1.php';
require_once __DIR__.'/../v2/seo-seasonal-identity-v1.php';

function seasonal_dossier_fail(string $message): never
{
    fwrite(STDERR,"SEO_SEASONAL_LAUNCH_DOSSIER_FAIL:$message\n");
    exit(1);
}

$snapshotFile=__DIR__.'/../v2/data/evidence/seo-seasonal-manual-serp-2026-09-03.json';
$raw=file_get_contents($snapshotFile);
if($raw===false)seasonal_dossier_fail('serp_snapshot_missing');
try{$snapshot=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){seasonal_dossier_fail('serp_snapshot_invalid');}
$rows=is_array($snapshot['rows']??null)?$snapshot['rows']:[];
$observed=(int)($snapshot['observed_at_epoch']??0);
$now=$observed+60;

$hold=v2_seo_seasonal_preview_launch_dossier($rows,[],$now);
if(($hold['state']??'')!=='review_only_seasonal_launch_dossier_hold')seasonal_dossier_fail('missing_identity_state');
if(($hold['go_review_count']??-1)!==0||($hold['hold_count']??0)!==2)seasonal_dossier_fail('missing_identity_counts');
foreach($hold['rows'] as $row){
    if(($row['dimensions']['technical']['status']??'')!=='confirmed')seasonal_dossier_fail('technical_not_ready');
    if(($row['dimensions']['content']['status']??'')!=='confirmed')seasonal_dossier_fail('content_not_ready');
    if(($row['dimensions']['demand']['status']??'')!=='confirmed')seasonal_dossier_fail('demand_not_ready');
    if(($row['dimensions']['uniqueness']['status']??'')!=='confirmed')seasonal_dossier_fail('uniqueness_not_ready');
    if(($row['dimensions']['identity']['status']??'')!=='unknown')seasonal_dossier_fail('identity_fabricated');
    if(($row['dimensions']['commercial_inventory']['status']??'')!=='unknown')seasonal_dossier_fail('inventory_fabricated');
    foreach(['identity','commercial_inventory'] as $blocked)if(!in_array($blocked,$row['blocked_dimensions']??[],true))seasonal_dossier_fail('missing_block_'.$blocked);
}

$checked=$now-30;$expires=$now+3600;$freshness=$expires-$checked;
$identityRows=[
    [
        'page_type'=>'resort_month','page_key'=>'resort_month:1:4:20:2026-09','country_id'=>4,'region_id'=>20,'departure_id'=>1,
        'departure_year'=>2026,'departure_month'=>9,'offer_count'=>12,'freshness_seconds'=>$freshness,
        'evidence_checked_at_epoch'=>$checked,'expires_at_epoch'=>$expires,'observed_at'=>gmdate('c',$checked),'expires_at'=>gmdate('c',$expires),
    ],
    [
        'page_type'=>'month','page_key'=>'month:1:8:2026-09','country_id'=>8,'region_id'=>null,'departure_id'=>1,
        'departure_year'=>2026,'departure_month'=>9,'offer_count'=>9,'freshness_seconds'=>$freshness,
        'evidence_checked_at_epoch'=>$checked,'expires_at_epoch'=>$expires,'observed_at'=>gmdate('c',$checked),'expires_at'=>gmdate('c',$expires),
    ],
];
$identity=v2_seo_seasonal_identity_inventory($identityRows,$now);
if(($identity['identity_count']??0)!==2||($identity['blocked_count']??-1)!==0||($identity['evidence_clock_valid']??false)!==true)seasonal_dossier_fail('identity_fixture');

$go=v2_seo_seasonal_preview_launch_dossier($rows,$identity,$now);
if(($go['state']??'')!=='review_only_seasonal_launch_dossier_go_review'||($go['all_go_review']??false)!==true)seasonal_dossier_fail('go_state');
if(($go['go_review_count']??0)!==2||($go['hold_count']??-1)!==0)seasonal_dossier_fail('go_counts');
foreach($go['rows'] as $row){
    if(($row['decision']??'')!=='GO_REVIEW'||($row['blocked_dimensions']??null)!==[])seasonal_dossier_fail('row_go');
    if(($row['dimensions']['identity']['status']??'')!=='confirmed')seasonal_dossier_fail('identity_not_confirmed');
    if(($row['dimensions']['commercial_inventory']['status']??'')!=='confirmed')seasonal_dossier_fail('inventory_not_confirmed');
    if((int)($row['dimensions']['commercial_inventory']['offer_count']??0)<=0)seasonal_dossier_fail('inventory_count');
    if(($row['publication_candidate']??true)!==false)seasonal_dossier_fail('row_publication_candidate');
}
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','automatic_execution_allowed','go_review_is_publication_approval'] as $flag){
    if(($go[$flag]??true)!==false)seasonal_dossier_fail('boundary_'.$flag);
}
if(($go['publication_candidates']??null)!==[])seasonal_dossier_fail('publication_candidates');

$staleIdentityRows=$identityRows;
foreach($staleIdentityRows as &$row){
    $row['evidence_checked_at_epoch']=$now-8000;
    $row['expires_at_epoch']=$now-10;
    $row['freshness_seconds']=7990;
    $row['observed_at']=gmdate('c',$now-8000);
    $row['expires_at']=gmdate('c',$now-10);
}
unset($row);
$staleIdentity=v2_seo_seasonal_identity_inventory($staleIdentityRows,$now);
$staleHold=v2_seo_seasonal_preview_launch_dossier($rows,$staleIdentity,$now);
if(($staleHold['all_go_review']??true)!==false||($staleHold['hold_count']??0)!==2)seasonal_dossier_fail('stale_identity_not_hold');

$staleSerp=v2_seo_seasonal_preview_launch_dossier($rows,$identity,$observed+(86400*31)+1);
if(($staleSerp['all_go_review']??true)!==false||($staleSerp['hold_count']??0)!==2)seasonal_dossier_fail('stale_serp_not_hold');

echo "SEO_SEASONAL_LAUNCH_DOSSIER_OK previews=2 realSerp=2 structural=2 identityGate=1 inventoryGate=1 publication=0\n";
