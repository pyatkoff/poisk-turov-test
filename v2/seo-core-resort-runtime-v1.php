<?php
declare(strict_types=1);
require_once __DIR__.'/seo-resort-page-v1.php';
require_once __DIR__.'/seo-seasonal-page-v1.php';
require_once __DIR__.'/seo-core-month-content-v1.php';

function v2_seo_generated_resort_registry(): array
{
    $file=__DIR__.'/data/generated/seo-core-resort-registry-v1.php';
    if(!is_file($file))return [];
    $rows=require $file;
    return is_array($rows)?$rows:[];
}

function v2_seo_generated_resort_record(string $path): array
{
    $path=trim($path);
    $rows=v2_seo_generated_resort_registry();
    if(!isset($rows[$path])||!is_array($rows[$path]))throw new OutOfBoundsException('Unknown generated resort route');
    return $rows[$path];
}

function v2_seo_generated_resort_month_record(string $basePath,int $monthNo,?int $nowEpoch=null): array
{
    if($monthNo<1||$monthNo>12)throw new InvalidArgumentException('Invalid resort month');
    $base=v2_seo_generated_resort_record($basePath);
    $data=is_array($base['data']??null)?$base['data']:[];
    $name=trim((string)($data['name']??''));$baseH1=trim((string)($data['h1']??''));
    $search=is_array($data['search_state']??null)?$data['search_state']:[];$countryId=(int)($search['country']??0);$regionId=(int)($search['region']??0);
    if($name===''||$baseH1===''||$countryId<=0||$regionId<=0)throw new RuntimeException('Incomplete generated resort identity');
    $labels=v2_seo_month_labels();$month=$labels[$monthNo];$targetYear=v2_seo_core_month_target_year($monthNo,$nowEpoch);
    $slugs=[1=>'january',2=>'february',3=>'march',4=>'april',5=>'may',6=>'june',7=>'july',8=>'august',9=>'september',10=>'october',11=>'november',12=>'december'];
    $path=rtrim($basePath,'/').'/'.$slugs[$monthNo].'/';
    $breadcrumbs=is_array($data['breadcrumbs']??null)?$data['breadcrumbs']:[];
    if($breadcrumbs!==[]){$last=array_pop($breadcrumbs);if(is_array($last)){$last['href']=rtrim($basePath,'/').'/';$breadcrumbs[]=$last;}}
    $breadcrumbs[]=['label'=>mb_convert_case($month['nom'],MB_CASE_TITLE,'UTF-8')];
    $related=[];foreach($slugs as $peerNo=>$peerSlug){if($peerNo===$monthNo)continue;$peer=$labels[$peerNo];$related[]=['label'=>$baseH1.' в '.$peer['prep'],'href'=>rtrim($basePath,'/').'/'.$peerSlug.'/'];}
    $h1=$baseH1.' в '.$month['prep'];$period=sprintf('%04d-%02d',$targetYear,$monthNo);
    return [
        'id'=>'seasonal.generated.'.$countryId.'.'.$regionId.'.'.$monthNo.'.v1','status'=>'approved','path'=>$path,'type'=>'seasonal',
        'data'=>[
            'name'=>$name,'title'=>$h1.' — подбор тура | AnyTour','description'=>$h1.': сравните даты, отели и актуальные варианты поездки в AnyTour.','h1'=>$h1,
            'eyebrow'=>'AnyTour · отдых по месяцам','intro'=>'Страница помогает перейти от выбора '.$name.' к поездке на ближайший '.$month['nom'].'. Меняющиеся цены, наличие и параметры тура берутся только из актуального поиска и свежих наблюдений.',
            'breadcrumbs'=>$breadcrumbs,
            'sections'=>[
                ['id'=>'planning','title'=>'Как планировать поездку в '.$month['prep'],'paragraphs'=>['Определите город вылета, даты, продолжительность и состав туристов, затем сравните доступные отели и конкретные предложения.','Коммерческие параметры не фиксируются в постоянном тексте и перепроверяются перед заявкой.']],
                ['id'=>'hotel-choice','title'=>'Как выбрать отель','paragraphs'=>['Сравнивайте размещение, питание, продолжительность и состав пакета по конкретным предложениям.','После предварительного выбора переходите в поиск AnyTour для проверки актуальной стоимости и доступности.']],
                ['id'=>'month-check','title'=>'Что проверить перед заявкой','paragraphs'=>['Проверьте даты, город вылета, количество ночей и состав включённых услуг.','Свежие ценовые наблюдения используются как динамический ориентир и не заменяют финальную проверку тура.']],
            ],
            'related_title'=>'Другие месяцы для этого курорта','related'=>$related,
            'internal_links'=>[['title'=>'Подбор тура','links'=>[['label'=>'Поиск туров AnyTour','href'=>'/poisk-turov/'],['label'=>$baseH1,'href'=>rtrim($basePath,'/').'/']]]],
            'search_state'=>['country'=>$countryId,'region'=>$regionId],
            'seasonal_identity'=>['page_key'=>'resort_month:1:'.$countryId.':'.$regionId.':'.$period,'country_id'=>$countryId,'region_id'=>$regionId,'year'=>$targetYear,'month'=>$monthNo],
        ],
        'publication_allowed'=>true,'indexation_allowed'=>true,'sitemap_allowed'=>true,'route_launch_allowed'=>true,
    ];
}
