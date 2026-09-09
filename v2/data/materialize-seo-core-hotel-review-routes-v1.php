<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/seo-core-hotel-cohort-source-v1.php';
require_once dirname(__DIR__).'/seo-core-hotel-cohort-v1.php';

function hotel_materialize_arg(array $argv,string $name): ?string{
    foreach($argv as $arg)if(str_starts_with($arg,'--'.$name.'='))return substr($arg,strlen($name)+3);
    return null;
}
function hotel_materialize_atomic(string $path,string $content): void{
    $dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Cannot create '.$dir);
    $tmp=$path.'.tmp.'.bin2hex(random_bytes(4));
    if(file_put_contents($tmp,$content,LOCK_EX)===false)throw new RuntimeException('Cannot write '.$tmp);
    chmod($tmp,0644);if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Cannot replace '.$path);}
}

$root=rtrim(hotel_materialize_arg($argv,'root')??dirname(__DIR__),'/');
$limit=(int)(hotel_materialize_arg($argv,'limit')??500);$limit=max(1,min(500,$limit));
$requireCount=(int)(hotel_materialize_arg($argv,'require-count')??0);$requireCount=max(0,min(500,$requireCount));
$dryRun=in_array('--dry-run',$argv,true);
if(!$dryRun){fwrite(STDERR,"SEO_CORE_HOTEL_MATERIALIZE_FAIL route_launch_locked explicit_user_approval_required\n");exit(5);}
if(!is_dir($root)||!is_file($root.'/seo-core-hotel-review-runtime-v1.php')){fwrite(STDERR,"SEO_CORE_HOTEL_MATERIALIZE_FAIL invalid_root\n");exit(2);}

$pdo=v2_data_db();$rows=v2_seo_core_hotel_cohort_source_rows($pdo,$limit);$cohort=v2_seo_core_hotel_cohort_records($rows,$limit);$records=$cohort['records']??[];
if(!is_array($records)||($cohort['state']??'')!=='review_only_core_hotel_cohort_ready'){fwrite(STDERR,"SEO_CORE_HOTEL_MATERIALIZE_FAIL invalid_cohort\n");exit(3);}
if($requireCount>0&&count($records)<$requireCount){fwrite(STDERR,"SEO_CORE_HOTEL_MATERIALIZE_FAIL count=".count($records)." required={$requireCount}\n");exit(4);}

$generatedDir=$root.'/data/generated';$registryFile=$generatedDir.'/seo-core-hotel-review-registry-v1.php';$manifestFile=$generatedDir.'/seo-core-hotel-review-routes-v1.json';
$previous=[];
if(is_file($manifestFile)){$decoded=json_decode((string)file_get_contents($manifestFile),true);if(is_array($decoded)&&is_array($decoded['generated_routes']??null))$previous=$decoded['generated_routes'];}
$registry=[];$current=[];$created=0;$preserved=0;$updated=0;
$routeBody="<?php\ndeclare(strict_types=1);\n/* SEO_CORE_HOTEL_GENERATED_V1 */\nrequire_once dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/seo-core-hotel-review-runtime-v1.php';\nv2_seo_render_core_hotel_review_route();\n";
foreach($records as $record){
    if(!is_array($record))continue;$path=(string)($record['path']??'');
    if(!preg_match('#^/country/(egypt|turkey|maldives)/hotel/[a-z0-9-]+-[0-9]+/$#',$path))continue;
    $registry[$path]=$record;$routeFile=$root.'/'.ltrim($path,'/').'index.php';$current[]=$path;
    if(is_file($routeFile)){
        $existing=(string)file_get_contents($routeFile);
        if(!str_contains($existing,'SEO_CORE_HOTEL_GENERATED_V1')){$preserved++;continue;}
        if($existing===$routeBody)continue;$updated++;
    }else{$created++;}
}
ksort($registry,SORT_STRING);sort($current,SORT_STRING);
$removed=0;
foreach($previous as $oldPath){
    $oldPath=(string)$oldPath;if(in_array($oldPath,$current,true)||!preg_match('#^/country/(egypt|turkey|maldives)/hotel/[a-z0-9-]+-[0-9]+/$#',$oldPath))continue;
    $routeFile=$root.'/'.ltrim($oldPath,'/').'index.php';
    if(is_file($routeFile)&&str_contains((string)file_get_contents($routeFile),'SEO_CORE_HOTEL_GENERATED_V1'))$removed++;
}

echo 'SEO_CORE_HOTEL_MATERIALIZE_DRY_RUN_OK count='.count($registry).' would_create='.$created.' would_update='.$updated.' curated_preserved='.$preserved.' would_remove='.$removed.' dry_run=1 publication=0 indexation=0 sitemap=0 route_launch=0'."\n";
