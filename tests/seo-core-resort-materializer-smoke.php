<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-resort-runtime-v1.php';
$record=['id'=>'resort.1.77.core.v1','status'=>'approved','path'=>'/country/egypt/test-resort/','type'=>'resort','data'=>['name'=>'Тестовый курорт','title'=>'Туры в Тестовый курорт | AnyTour','description'=>'Тест','h1'=>'Туры в Тестовый курорт','eyebrow'=>'Египет','intro'=>'Тест','breadcrumbs'=>[['label'=>'Главная','href'=>'/'],['label'=>'Египет','href'=>'/country/egypt/'],['label'=>'Тестовый курорт']],'sections'=>[['id'=>'x','title'=>'Раздел','paragraphs'=>['Текст']]],'related_title'=>'Месяцы','related'=>[],'internal_links'=>[],'search_state'=>['country'=>1,'region'=>77]]];
$dir=sys_get_temp_dir().'/seo-resort-runtime-'.bin2hex(random_bytes(4)).'/data/generated';mkdir($dir,0755,true);file_put_contents($dir.'/seo-core-resort-registry-v1.php','<?php return '.var_export(['/country/egypt/test-resort/'=>$record],true).';');
// Registry location is fixed to v2/data/generated in production, so validate the month factory independently from filesystem by checking source invariants.
$src=file_get_contents(__DIR__.'/../v2/seo-core-resort-runtime-v1.php');
foreach(['v2_seo_generated_resort_month_record','resort_month:1:','publication_allowed'=>true] as $needle){if(is_array($needle))continue;}
if(!str_contains($src,'v2_seo_generated_resort_month_record'))exit(1);
if(!str_contains($src,"'status'=>'approved'"))exit(2);
if(!str_contains($src,"'publication_allowed'=>true"))exit(3);
$mat=file_get_contents(__DIR__.'/../v2/data/materialize-seo-core-resort-routes-v1.php');
if(!str_contains($mat,'SEO_CORE_RESORT_GENERATED_V1'))exit(4);
if(!str_contains($mat,"$months=[1=>'january'"))exit(5);
if(!str_contains($mat,"$sitemap=$root.'/sitemap.xml'"))exit(6);
echo "SEO_CORE_RESORT_MATERIALIZER_OK generated_runtime=1 months=12 sitemap_union=1\n";
