<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-hotel-cohort-v1.php';

$rows=[];
for($i=1;$i<=520;$i++){
    $countryId=[1,4,8][($i-1)%3];
    $countrySlug=[1=>'egypt',4=>'turkey',8=>'maldives'][$countryId];
    $countryName=[1=>'Египет',4=>'Турция',8=>'Мальдивы'][$countryId];
    $hotelId=10000+$i;
    $rows[]=[
        'hotel_id'=>$hotelId,'country_id'=>$countryId,'hotel_name'=>'Hotel '.$i,
        'hotel_slug'=>'hotel-'.$i.'-'.$hotelId,'country_slug'=>$countrySlug,'country_name'=>$countryName,
        'observation_count'=>600-$i,'last_observed_at'=>'2026-09-03 12:00:00',
    ];
}
$result=v2_seo_core_hotel_cohort_records($rows,500);
if(($result['state']??'')!=='review_only_core_hotel_cohort_ready')exit(1);
if(($result['count']??0)!==500||count($result['records']??[])!==500)exit(2);
if(($result['publication_candidates']??null)!==[])exit(3);
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','search_contract_changes','tourvisor_contract_changes'] as $flag)if(($result[$flag]??true)!==false)exit(4);
if(($result['explicit_user_indexation_approval_required']??false)!==true)exit(5);
$first=$result['records'][0]??[];
if(($first['type']??'')!=='hotel_tours'||($first['status']??'')!=='review')exit(6);
if(($first['data']['search_state']??[])!==['country'=>1,'hotel'=>10001])exit(7);
if(($first['path']??'')!=='/country/egypt/hotel/hotel-1-10001/')exit(8);
if(($first['cohort_evidence']['selection_basis']??'')!=='fresh_first_party_inventory_observation_coverage')exit(9);
$bad=$rows;
$bad[0]['hotel_slug']='broken-slug';
$badResult=v2_seo_core_hotel_cohort_records($bad,500);
if(($badResult['count']??0)!==500)exit(10); // invalid row skipped, next valid row fills cohort
foreach($badResult['records'] as $record)if(($record['data']['search_state']['hotel']??0)===10001)exit(11);
echo "SEO_CORE_HOTEL_COHORT_OK records=500 review=1 publication=0\n";
