<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-second-wave-country-review-v1.php';

function second_wave_fail(string $message): void
{
    fwrite(STDERR,"SEO_SECOND_WAVE_COUNTRY_REVIEW_FAIL:$message\n");
    exit(1);
}

$now=1788369060;
$review=v2_seo_second_wave_country_review($now);
if(($review['state']??'')!=='second_wave_country_review_ready') second_wave_fail('state');
if(($review['domain']??'')!=='anytoour.ru') second_wave_fail('domain');
if(count($review['rows']??[])!==2) second_wave_fail('row_count');

$expected=['/country/egypt/','/country/maldives/'];
$actual=[];
foreach($review['rows'] as $row){
    $actual[]=(string)($row['path']??'');
    if(($row['page_type']??'')!=='country') second_wave_fail('page_type');
    if((int)($row['technical_quality_score']??0)!==100||($row['technical_review_ready']??false)!==true) second_wave_fail('quality');
    if(($row['opportunity_evidence']['state']??'')!=='opportunity_evidence_review_ready') second_wave_fail('evidence');
    if(($row['opportunity_evidence']['demand']['metrics']??null)!==[]) second_wave_fail('fabricated_metrics');
    if(($row['review_decision']??'')!=='HOLD') second_wave_fail('decision');
    if(($row['production_identity_state']??'')!=='requires_live_verifier') second_wave_fail('identity_gate');
    if(($row['resort_layer']['state']??'')!=='HOLD'||($row['resort_layer']['route_creation_allowed']??true)!==false) second_wave_fail('resort_boundary');
}
sort($actual,SORT_STRING); sort($expected,SORT_STRING);
if($actual!==$expected) second_wave_fail('paths');

foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed'] as $flag){
    if(($review[$flag]??true)!==false) second_wave_fail('boundary_'.$flag);
}
if(($review['publication_candidates']??null)!==[]||($review['publication_scope_expanded']??true)!==false) second_wave_fail('publication_scope');
foreach(['search_contract_changes','tourvisor_contract_changes','pricing_contract_changes','lead_contract_changes','metrika_contract_changes'] as $flag){
    if(($review[$flag]??true)!==false) second_wave_fail('contract_'.$flag);
}

$stale=v2_seo_second_wave_country_review($now+32*86400);
if(($stale['state']??'')!=='second_wave_country_review_blocked') second_wave_fail('stale_not_blocked');

echo "SEO_SECOND_WAVE_COUNTRY_REVIEW_OK countries=2 quality=100 publication=0 resortRoutes=0 hotelTours=0\n";
