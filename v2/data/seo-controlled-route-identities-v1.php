<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-core-month-content-v1.php';
function v2_seo_controlled_route_identities(): array
{
    $rows=[
        'country:country=4'=>'/country/turkey/','country:country=1'=>'/country/egypt/','country:country=8'=>'/country/maldives/',
        'resort:country=4:region=19'=>'/country/turkey/alanya/','resort:country=4:region=20'=>'/country/turkey/antalya/','resort:country=4:region=21'=>'/country/turkey/belek/','resort:country=4:region=22'=>'/country/turkey/kemer/','resort:country=4:region=23'=>'/country/turkey/side/',
    ];
    foreach(v2_seo_core_month_content_records() as $record){
        $identity=$record['data']['seasonal_identity']??[];$country=(int)($identity['country_id']??0);$region=$identity['region_id']??null;$year=(int)($identity['year']??0);$month=(int)($identity['month']??0);$path=(string)($record['path']??'');
        if($country<=0||$year<=0||$month<1||$month>12||$path==='')continue;$period=sprintf('%04d-%02d',$year,$month);
        $key=$region===null?'country_month:country='.$country.':period='.$period:'resort_month:country='.$country.':region='.(int)$region.':period='.$period;
        if(isset($rows[$key])&&$rows[$key]!==$path)throw new RuntimeException('Controlled route identity collision: '.$key);$rows[$key]=$path;
    }
    return $rows;
}
