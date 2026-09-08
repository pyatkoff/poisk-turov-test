<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-launch-slice-v1.php';
require_once __DIR__.'/../v2/seo-core-month-content-v1.php';

$errors=[];
$catalogPaths=v2_seo_controlled_launch_paths();
$launchPaths=v2_seo_controlled_indexable_paths();
$monthRecords=v2_seo_core_month_content_records(strtotime('2026-09-03T12:00:00Z'));
$expectedCatalogBase=8;
$expectedHubs=2;
$expectedMonths=96;
$expectedCatalog=$expectedCatalogBase+$expectedMonths;
$expectedPublic=$expectedHubs+$expectedCatalog;

if(count($monthRecords)!==$expectedMonths)$errors[]='core month matrix must contain exactly 96 records';
if(count($catalogPaths)!==$expectedCatalog)$errors[]='controlled catalog SEO cohort must preserve exactly 104 base+month paths';
if(count($launchPaths)!==$expectedPublic)$errors[]='controlled public SEO cohort must be exactly 106 hubs+base+month paths';
if(count($launchPaths)!==count(array_unique($launchPaths)))$errors[]='controlled public SEO cohort contains duplicate paths';

$monthPaths=[];
foreach($monthRecords as $record){
    if(!is_array($record)){ $errors[]='core month record is not an array'; continue; }
    $path=(string)($record['path']??'');
    if($path===''){ $errors[]='core month record has empty path'; continue; }
    $monthPaths[$path]=true;
    if(($record['status']??null)!=='approved')$errors[]="$path must be approved as a core month page";
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag){
        if(($record[$flag]??null)!==true)$errors[]="$path must keep $flag=true";
    }
}

$missing=array_values(array_diff(array_keys($monthPaths),$launchPaths));
if($missing!==[])$errors[]='approved core month paths missing from controlled launch: '.implode(',',array_slice($missing,0,10));
foreach($launchPaths as $path){
    if(str_contains($path,'/hotel/'))$errors[]='hotel_tours must never enter controlled public baseline: '.$path;
    if($path==='/poisk-turov/')$errors[]='search route must never enter controlled public baseline';
}
foreach(['/','/country/'] as $hub)if(!in_array($hub,$launchPaths,true))$errors[]='core hub missing from controlled public cohort: '.$hub;

if($errors!==[]){
    fwrite(STDERR,"SEO_CONTROLLED_GROWTH_GUARD_FAIL\n");
    foreach($errors as $error)fwrite(STDERR,"- $error\n");
    exit(1);
}

echo "SEO_CONTROLLED_GROWTH_GUARD_OK public_paths=106 hubs=2 catalog_base=8 core_months=96 hotel_tours=0\n";
