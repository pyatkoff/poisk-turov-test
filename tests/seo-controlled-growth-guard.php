<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-launch-slice-v1.php';
require_once __DIR__.'/../v2/seo-core-month-content-v1.php';

$errors=[];
$launchPaths=v2_seo_controlled_launch_paths();
$baselineLimit=10;

if(count($launchPaths)>$baselineLimit){
    $errors[]='controlled public SEO cohort expanded beyond the evidence-reviewed 10-path baseline';
}

$monthRecords=v2_seo_core_month_content_records(strtotime('2026-09-03T12:00:00Z'));
if(count($monthRecords)!==96){
    $errors[]='core month review matrix must contain exactly 96 records';
}

$monthPaths=[];
foreach($monthRecords as $record){
    if(!is_array($record)){
        $errors[]='core month record is not an array';
        continue;
    }
    $path=(string)($record['path']??'');
    if($path===''){
        $errors[]='core month record has empty path';
        continue;
    }
    $monthPaths[$path]=true;
    if(($record['status']??null)!=='review')$errors[]="$path must remain review-only";
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag){
        if(($record[$flag]??null)!==false)$errors[]="$path must keep $flag=false";
    }
}

$leaked=array_values(array_intersect($launchPaths,array_keys($monthPaths)));
if($leaked!==[]){
    $errors[]='review-only core month paths leaked into controlled launch: '.implode(',',$leaked);
}

if($errors!==[]){
    fwrite(STDERR,"SEO_CONTROLLED_GROWTH_GUARD_FAIL\n");
    foreach($errors as $error)fwrite(STDERR,"- $error\n");
    exit(1);
}

echo 'SEO_CONTROLLED_GROWTH_GUARD_OK public_paths='.count($launchPaths).' core_month_review='.count($monthRecords)."\n";
