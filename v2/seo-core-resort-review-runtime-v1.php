<?php
declare(strict_types=1);
require_once __DIR__.'/seo-resort-page-v1.php';
require_once __DIR__.'/seo-seasonal-page-v1.php';
require_once __DIR__.'/seo-core-month-content-v1.php';

function v2_seo_generated_resort_review_registry(): array
{
    $file=__DIR__.'/data/generated/seo-core-resort-review-registry-v1.php';
    if(!is_file($file))return [];
    $rows=require $file;
    return is_array($rows)?$rows:[];
}

function v2_seo_generated_resort_review_record(string $path): array
{
    $rows=v2_seo_generated_resort_review_registry();
    if(!isset($rows[$path])||!is_array($rows[$path]))throw new OutOfBoundsException('Unknown generated resort review route');
    $record=$rows[$path];
    $record['status']='review';
    $record['publication_allowed']=false;$record['indexation_allowed']=false;$record['sitemap_allowed']=false;$record['route_launch_allowed']=false;
    return $record;
}

function v2_seo_generated_resort_month_review_record(string $basePath,int $monthNo,?int $nowEpoch=null): array
{
    if($monthNo<1||$monthNo>12)throw new InvalidArgumentException('Invalid resort month');
    $base=v2_seo_generated_resort_review_record($basePath);$data=is_array($base['data']??null)?$base['data']:[];
    $name=trim((string)($data['name']??''));$baseH1=trim((string)($data['h1']??''));$search=(array)($data['search_state']??[]);
    $countryId=(int)($search['country']??0);$regionId=(int)($search['region']??0);if($name===''||$baseH1===''||$countryId<=0||$regionId<=0)throw new RuntimeException('Incomplete generated resort identity');
    $labels=v2_seo_month_labels();$month=$labels[$monthNo];$year=v2_seo_core_month_target_year($monthNo,$nowEpoch);$slugs=[1=>'january',2=>'february',3=>'march',4=>'april',5=>'may',6=>'june',7=>'july',8=>'august',9=>'september',10=>'october',11=>'november',12=>'december'];
    $path=rtrim($basePath,'/').'/'.$slugs[$monthNo].'/';$breadcrumbs=(array)($data['breadcrumbs']??[]);if($breadcrumbs){$last=array_pop($breadcrumbs);if(is_array($last)){$last['href']=rtrim($basePath,'/').'/';$breadcrumbs[]=$last;}}$breadcrumbs[]=['label'=>mb_convert_case($month['nom'],MB_CASE_TITLE,'UTF-8')];
    $related=[];foreach($slugs as $n=>$slug){if($n===$monthNo)continue;$related[]=['label'=>$baseH1.' в '.$labels[$n]['prep'],'href'=>rtrim($basePath,'/').'/'.$slug.'/'];}
    $h1=$baseH1.' в '.$month['prep'];$period=sprintf('%04d-%02d',$year,$monthNo);
    return ['id'=>'seasonal.generated.review.'.$countryId.'.'.$regionId.'.'.$monthNo.'.v1','status'=>'review','path'=>$path,'type'=>'seasonal','data'=>[
        'name'=>$name,'title'=>$h1.' — подбор тура | AnyTour','description'=>$h1.': сравните даты, отели и актуальные варианты поездки в AnyTour.','h1'=>$h1,'eyebrow'=>'AnyTour · отдых по месяцам','intro'=>'Страница помогает перейти от выбора '.$name.' к поездке на ближайший '.$month['nom'].'. Меняющиеся цены, наличие и параметры тура берутся только из актуального поиска и свежих наблюдений.','breadcrumbs'=>$breadcrumbs,
        'sections'=>[
            ['id'=>'planning','title'=>'Как планировать поездку в '.$month['prep'],'paragraphs'=>['Определите город вылета, даты, продолжительность и состав туристов, затем сравните доступные отели и конкретные предложения.','Коммерческие параметры не фиксируются в постоянном тексте и перепроверяются перед заявкой.']],
            ['id'=>'hotel-choice','title'=>'Как выбрать отель','paragraphs'=>['Сравнивайте размещение, питание, продолжительность и состав пакета по конкретным предложениям.','После предварительного выбора переходите в поиск AnyTour для проверки актуальной стоимости и доступности.']],
            ['id'=>'month-check','title'=>'Что проверить перед заявкой','paragraphs'=>['Проверьте даты, город вылета, количество ночей и состав включённых услуг.','Свежие ценовые наблюдения используются как динамический ориентир и не заменяют финальную проверку тура.']],
        ],'related_title'=>'Другие месяцы для этого курорта','related'=>$related,'internal_links'=>[['title'=>'Подбор тура','links'=>[['label'=>'Поиск туров AnyTour','href'=>'/poisk-turov/'],['label'=>$baseH1,'href'=>rtrim($basePath,'/').'/']]]],'search_state'=>['country'=>$countryId,'region'=>$regionId],'seasonal_identity'=>['page_key'=>'resort_month:1:'.$countryId.':'.$regionId.':'.$period,'country_id'=>$countryId,'region_id'=>$regionId,'year'=>$year,'month'=>$monthNo]],
        'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_launch_allowed'=>false];
}
