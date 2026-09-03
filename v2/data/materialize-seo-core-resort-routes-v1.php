<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/seo-core-resort-cohort-source-v1.php';
require_once dirname(__DIR__).'/seo-core-resort-cohort-v1.php';

function resort_mat_arg(array $argv,string $name): ?string{foreach($argv as $arg)if(str_starts_with($arg,'--'.$name.'='))return substr($arg,strlen($name)+3);return null;}
function resort_mat_atomic(string $path,string $content): void{$dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('mkdir '.$dir);$tmp=$path.'.tmp.'.bin2hex(random_bytes(4));if(file_put_contents($tmp,$content,LOCK_EX)===false)throw new RuntimeException('write '.$tmp);chmod($tmp,0644);if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('rename '.$path);}}

$root=rtrim(resort_mat_arg($argv,'root')??dirname(__DIR__),'/');
$limit=max(1,min(200,(int)(resort_mat_arg($argv,'limit')??80)));
$requireCount=max(0,min(200,(int)(resort_mat_arg($argv,'require-count')??0)));
$apply=in_array('--apply',$argv,true);
if(!is_dir($root)||!is_file($root.'/seo-core-resort-runtime-v1.php')){fwrite(STDERR,"SEO_CORE_RESORT_MATERIALIZE_FAIL invalid_root\n");exit(2);}
$rows=v2_seo_core_resort_cohort_source_rows(v2_data_db(),$limit);$cohort=v2_seo_core_resort_cohort_records($rows,$limit);$records=$cohort['records']??[];
if(($cohort['state']??'')!=='core_resort_cohort_ready'||!is_array($records)){fwrite(STDERR,"SEO_CORE_RESORT_MATERIALIZE_FAIL invalid_cohort\n");exit(3);}
if($requireCount>0&&count($records)<$requireCount){fwrite(STDERR,"SEO_CORE_RESORT_MATERIALIZE_FAIL count=".count($records)." required={$requireCount}\n");exit(4);}
$months=[1=>'january',2=>'february',3=>'march',4=>'april',5=>'may',6=>'june',7=>'july',8=>'august',9=>'september',10=>'october',11=>'november',12=>'december'];
$registry=[];$routes=[];$routeBodies=[];
foreach($records as $record){
    if(!is_array($record))continue;$base=(string)($record['path']??'');
    if(!preg_match('#^/country/(egypt|maldives)/[a-z0-9-]+/$#',$base))continue;
    $registry[$base]=$record;$routes[]=$base;
    $routeBodies[$base]="<?php declare(strict_types=1); /* SEO_CORE_RESORT_GENERATED_V1 */ require_once dirname(dirname(dirname(__DIR__))).'/seo-core-resort-runtime-v1.php'; v2_seo_render_resort(v2_seo_generated_resort_record('".$base."'));\n";
    foreach($months as $monthNo=>$slug){$path=$base.$slug.'/';$routes[]=$path;$routeBodies[$path]="<?php declare(strict_types=1); /* SEO_CORE_RESORT_GENERATED_V1 */ require_once dirname(dirname(dirname(dirname(__DIR__)))).'/seo-core-resort-runtime-v1.php'; v2_seo_render_seasonal(v2_seo_generated_resort_month_record('".$base."',{$monthNo}));\n";}
}
ksort($registry,SORT_STRING);sort($routes,SORT_STRING);
$generatedDir=$root.'/data/generated';$registryFile=$generatedDir.'/seo-core-resort-registry-v1.php';$manifestFile=$generatedDir.'/seo-core-resort-routes-v1.json';$previous=[];
if(is_file($manifestFile)){$j=json_decode((string)file_get_contents($manifestFile),true);if(is_array($j)&&is_array($j['generated_routes']??null))$previous=$j['generated_routes'];}
$created=0;$updated=0;$preserved=0;$removed=0;
foreach($routes as $path){$file=$root.'/'.ltrim($path,'/').'index.php';$body=$routeBodies[$path];if(is_file($file)){if(!str_contains((string)file_get_contents($file),'SEO_CORE_RESORT_GENERATED_V1')){$preserved++;continue;}if((string)file_get_contents($file)!==$body)$updated++;}else $created++;}
foreach($previous as $path){$path=(string)$path;if(in_array($path,$routes,true))continue;$file=$root.'/'.ltrim($path,'/').'index.php';if(is_file($file)&&str_contains((string)file_get_contents($file),'SEO_CORE_RESORT_GENERATED_V1'))$removed++;}
if(!$apply){echo 'SEO_CORE_RESORT_MATERIALIZE_DRY_RUN_OK resorts='.count($registry).' routes='.count($routes).' would_create='.$created.' would_update='.$updated.' curated_preserved='.$preserved.' would_remove='.$removed."\n";exit(0);}
if(!is_dir($generatedDir)&&!mkdir($generatedDir,0755,true)&&!is_dir($generatedDir))throw new RuntimeException('generated dir');
resort_mat_atomic($registryFile,"<?php\ndeclare(strict_types=1);\nreturn ".var_export($registry,true).";\n");
foreach($routes as $path){$file=$root.'/'.ltrim($path,'/').'index.php';if(is_file($file)&&!str_contains((string)file_get_contents($file),'SEO_CORE_RESORT_GENERATED_V1'))continue;resort_mat_atomic($file,$routeBodies[$path]);}
foreach($previous as $path){$path=(string)$path;if(in_array($path,$routes,true))continue;$file=$root.'/'.ltrim($path,'/').'index.php';if(is_file($file)&&str_contains((string)file_get_contents($file),'SEO_CORE_RESORT_GENERATED_V1'))@unlink($file);}
$manifest=['state'=>'core_resort_routes_materialized','generated_resorts'=>count($registry),'generated_routes'=>$routes,'generated_route_count'=>count($routes),'publication_allowed'=>true,'indexation_allowed'=>true,'sitemap_allowed'=>true,'route_launch_allowed'=>true,'generated_at'=>gmdate('c')];
resort_mat_atomic($manifestFile,json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
$sitemap=$root.'/sitemap.xml';$urls=[];
if(is_file($sitemap)&&preg_match_all('#<loc>https://anytoour\.ru([^<]+)</loc>#',(string)file_get_contents($sitemap),$m))foreach($m[1] as $path)$urls[(string)$path]=true;
foreach($routes as $path)$urls[$path]=true;ksort($urls,SORT_STRING);
$xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";foreach(array_keys($urls) as $path)$xml.='  <url><loc>https://anytoour.ru'.htmlspecialchars($path,ENT_XML1|ENT_QUOTES,'UTF-8')."</loc></url>\n";$xml.="</urlset>\n";resort_mat_atomic($sitemap,$xml);
echo 'SEO_CORE_RESORT_MATERIALIZE_OK resorts='.count($registry).' routes='.count($routes).' created='.$created.' updated='.$updated.' curated_preserved='.$preserved.' removed='.$removed.' sitemap='.count($urls).' publication=1 indexation=1 route_launch=1'."\n";
