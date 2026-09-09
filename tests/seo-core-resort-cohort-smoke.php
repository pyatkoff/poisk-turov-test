<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-resort-cohort-v1.php';

$rows=[];
for($i=1;$i<=100;$i++){
    $countrySlug=$i%2===0?'egypt':'maldives';
    $countryId=$countrySlug==='egypt'?101:808;
    $countryName=$countrySlug==='egypt'?'Египет':'Мальдивы';
    $rows[]=[
        'region_id'=>2000+$i,'country_id'=>$countryId,'region_name'=>'Курорт '.$i,
        'region_slug'=>'resort-'.$i,'country_slug'=>$countrySlug,'country_name'=>$countryName,
        'observation_count'=>1000-$i,'hotel_count'=>20,'last_observed_at'=>'2026-09-03 18:00:00',
    ];
}
$r=v2_seo_core_resort_cohort_records($rows,80);
if(($r['state']??'')!=='core_resort_cohort_ready')exit(1);
if(($r['count']??0)!==80||count($r['records']??[])!==80)exit(2);
if(($r['country_scope']??[])!==['egypt','maldives'])exit(3);
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed','search_contract_changes','tourvisor_contract_changes'] as $flag)if(($r[$flag]??true)!==false)exit(4);
$first=$r['records'][0]??[];
if(($first['type']??'')!=='resort'||($first['status']??'')!=='approved')exit(5);
if(($first['data']['search_state']??[])!==['country'=>808,'region'=>2001])exit(6);
if(($first['path']??'')!=='/country/maldives/resort-1/')exit(7);
$related=$first['data']['related']??[];
if(count($related)!==14)exit(8); // country + resort parent + 12 month routes
$hrefs=array_column($related,'href');
foreach(['/country/maldives/','/country/maldives/resort-1/','/country/maldives/resort-1/january/','/country/maldives/resort-1/december/'] as $href)if(!in_array($href,$hrefs,true))exit(9);
if(count($first['data']['sections']??[])!==3)exit(10);
$bad=$rows;$bad[0]['region_slug']='bad slug';
$badR=v2_seo_core_resort_cohort_records($bad,80);
if(($badR['count']??0)!==80)exit(11);
foreach($badR['records'] as $record)if(($record['data']['search_state']['region']??0)===2001)exit(12);
$badCountry=$rows;$badCountry[0]['country_slug']='unknown-country';
$badCountryR=v2_seo_core_resort_cohort_records($badCountry,80);
foreach($badCountryR['records'] as $record)if(($record['data']['search_state']['region']??0)===2001)exit(13);
echo "SEO_CORE_RESORT_COHORT_OK records=80 country_scope=egypt,maldives catalog_country_ids=dynamic month_links=12 publication=0\n";
