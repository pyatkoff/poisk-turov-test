<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-second-wave-country-launch-dossier-v1.php';
function second_wave_launch_fail(string $m): never { fwrite(STDERR,"SEO_SECOND_WAVE_LAUNCH_DOSSIER_FAIL:$m\n"); exit(1); }
$now=1788385200;
$d=v2_seo_second_wave_country_launch_dossier($now);
if(($d['state']??'')!=='second_wave_country_prelaunch_authorized') second_wave_launch_fail('state');
$expected=['/country/egypt/','/country/maldives/']; sort($expected,SORT_STRING);
$paths=(array)($d['paths']??[]); sort($paths,SORT_STRING); if($paths!==$expected) second_wave_launch_fail('paths');
if(count($d['rows']??[])!==2) second_wave_launch_fail('row_count');
foreach((array)$d['rows'] as $row){
    if(($row['page_type']??'')!=='country') second_wave_launch_fail('page_type');
    if(($row['decision']??'')!=='GO') second_wave_launch_fail('decision');
    if(($row['technical_quality_score']??0)!==100) second_wave_launch_fail('quality');
    if(($row['numeric_demand_score']??'not-null')!==null) second_wave_launch_fail('invented_numeric_score');
    if(($row['numeric_score_intentionally_not_invented']??false)!==true) second_wave_launch_fail('numeric_policy');
}
if(($d['decision_policy']??'')!=='categorical_evidence_complete_v1') second_wave_launch_fail('policy');
if(($d['requires_fresh_live_production_identity_before_merge']??false)!==true) second_wave_launch_fail('live_identity_gate');
if(($d['publication_candidates']??null)!==[]) second_wave_launch_fail('publication_candidates');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_approved','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed'] as $flag){
    if(($d[$flag]??true)!==false) second_wave_launch_fail('boundary_'.$flag);
}
foreach(['search_contract_changes','tourvisor_contract_changes','pricing_contract_changes','lead_contract_changes','metrika_contract_changes'] as $flag){
    if(($d[$flag]??true)!==false) second_wave_launch_fail('contract_'.$flag);
}
echo "SEO_SECOND_WAVE_COUNTRY_LAUNCH_DOSSIER_OK paths=2 decision=GO numericDemandInvented=0 publication=0 hotelTours=0\n";
