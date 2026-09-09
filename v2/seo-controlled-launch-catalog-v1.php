<?php
require_once __DIR__ . '/seo-content-pilot-turkey-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-egypt-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-v1.php';
require_once __DIR__ . '/seo-content-pilot-seasonal-september-v1.php';
require_once __DIR__ . '/seo-core-month-content-v1.php';

/** Exact controlled production SEO catalog. hotel_tours remain deliberately absent. */
function v2_seo_controlled_launch_catalog(): array
{
    $records=[
        v2_seo_content_pilot_turkey(),v2_seo_content_pilot_kemer(),v2_seo_content_pilot_antalya(),v2_seo_content_pilot_side(),v2_seo_content_pilot_belek(),v2_seo_content_pilot_alanya(),v2_seo_content_pilot_egypt(),v2_seo_content_pilot_maldives(),
    ];
    $special=['/country/turkey/antalya/september/'=>v2_seo_content_pilot_antalya_september(),'/country/maldives/september/'=>v2_seo_content_pilot_maldives_september()];
    foreach(v2_seo_core_month_content_records() as $record){$path=(string)($record['path']??'');$records[]=$special[$path]??$record;unset($special[$path]);}
    foreach($special as $record)$records[]=$record;
    $registeredPaths=[];foreach($records as $record){$path=(string)($record['path']??'');if($path!=='')$registeredPaths[$path]=true;}
    $relations=[
        '/country/turkey/kemer/'=>['parent'=>'/country/turkey/','related'=>['/country/turkey/antalya/','/country/turkey/side/']],
        '/country/turkey/antalya/'=>['parent'=>'/country/turkey/','related'=>['/country/turkey/kemer/','/country/turkey/belek/']],
        '/country/turkey/side/'=>['parent'=>'/country/turkey/','related'=>['/country/turkey/belek/','/country/turkey/alanya/']],
        '/country/turkey/belek/'=>['parent'=>'/country/turkey/','related'=>['/country/turkey/antalya/','/country/turkey/side/']],
        '/country/turkey/alanya/'=>['parent'=>'/country/turkey/','related'=>['/country/turkey/side/','/country/turkey/antalya/']],
    ];
    foreach($records as $record){
        if(($record['type']??'')!=='seasonal')continue;
        $path=(string)($record['path']??'');$parent=rtrim(dirname(rtrim($path,'/')),'/').'/';$related=[];
        foreach((array)($record['data']['related']??[]) as $link){if(!is_array($link))continue;$href=(string)($link['href']??'');if(isset($registeredPaths[$href]))$related[]=$href;}
        $relations[$path]=['parent'=>$parent,'related'=>array_values(array_unique($related))];
    }
    return v2_seo_content_catalog($records,$relations);
}
