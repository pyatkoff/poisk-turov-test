<?php
require_once __DIR__ . '/../v2/seo-resort-family-integrity-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-antalya-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-kemer-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-belek-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-side-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-alanya-v1.php';

function resort_family_fail(string $message): void
{
    fwrite(STDERR, "SEO_RESORT_FAMILY_INTEGRITY_FAIL:$message\n");
    exit(1);
}

$country=v2_seo_content_pilot_turkey();
$resorts=[
    v2_seo_content_pilot_antalya(),
    v2_seo_content_pilot_kemer(),
    v2_seo_content_pilot_belek(),
    v2_seo_content_pilot_side(),
    v2_seo_content_pilot_alanya(),
];
$report=v2_seo_resort_family_integrity($country,$resorts);
if(($report['state']??'')!=='diagnostic_only_no_publication_mutation') resort_family_fail('state');
if(($report['country_id']??0)!==4) resort_family_fail('country');
if(($report['country_path']??'')!=='/country/turkey/') resort_family_fail('parent');
if(($report['resort_count']??0)!==5) resort_family_fail('count');
if(($report['unique_paths']??0)!==5 || ($report['unique_region_identities']??0)!==5) resort_family_fail('identity_uniqueness');
if(($report['ready']??0)!==5 || ($report['blocked']??-1)!==0) resort_family_fail('readiness');
foreach(($report['rows']??[]) as $row){
    if(($row['score']??0)!==100 || ($row['ready_for_launch_review']??false)!==true) resort_family_fail('score');
}

$badCountry=$resorts[0];
$badCountry['data']['search_state']['country']=8;
try {
    v2_seo_resort_family_integrity($country,[$badCountry]);
    resort_family_fail('wrong_country_allowed');
} catch (InvalidArgumentException $e) {}

$badRegion=$resorts[1];
$badRegion['data']['search_state']['region']=$resorts[0]['data']['search_state']['region'];
try {
    v2_seo_resort_family_integrity($country,[$resorts[0],$badRegion]);
    resort_family_fail('duplicate_region_allowed');
} catch (InvalidArgumentException $e) {}

$thin=$resorts[0];
$thin['data']['intro']='Коротко.';
$thin['data']['sections']=[['title'=>'Коротко','paragraphs'=>['Коротко.']]];
$thinReport=v2_seo_resort_family_integrity($country,[$thin]);
if(($thinReport['ready']??-1)!==0 || ($thinReport['blocked']??0)!==1) resort_family_fail('thin_not_blocked');
if(($thinReport['error_counts']['editorial_depth']??0)!==1) resort_family_fail('thin_reason');

echo "SEO_RESORT_FAMILY_INTEGRITY_OK country=4 resorts=5 ready=5 uniqueRegion=1 thinBlocked=1 diagnosticOnly=1\n";
