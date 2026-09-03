<?php
declare(strict_types=1);

require_once __DIR__.'/seo-core-month-matrix-v1.php';

function v2_seo_month_labels(): array
{
    return [
        1=>['nom'=>'январь','prep'=>'январе'],2=>['nom'=>'февраль','prep'=>'феврале'],3=>['nom'=>'март','prep'=>'марте'],
        4=>['nom'=>'апрель','prep'=>'апреле'],5=>['nom'=>'май','prep'=>'мае'],6=>['nom'=>'июнь','prep'=>'июне'],
        7=>['nom'=>'июль','prep'=>'июле'],8=>['nom'=>'август','prep'=>'августе'],9=>['nom'=>'сентябрь','prep'=>'сентябре'],
        10=>['nom'=>'октябрь','prep'=>'октябре'],11=>['nom'=>'ноябрь','prep'=>'ноябре'],12=>['nom'=>'декабрь','prep'=>'декабре'],
    ];
}

/** Evergreen /month/ URLs always target the nearest non-past occurrence. */
function v2_seo_core_month_target_year(int $month,?int $nowEpoch=null): int
{
    if($month<1||$month>12)throw new InvalidArgumentException('Invalid month');
    $nowEpoch??=time();
    $year=(int)gmdate('Y',$nowEpoch);
    $currentMonth=(int)gmdate('n',$nowEpoch);
    return $month<$currentMonth?$year+1:$year;
}

/**
 * Builds useful review-ready editorial records for the complete 96-page core
 * month matrix. Evergreen copy is deliberately conservative: changing weather,
 * prices, availability and departure facts remain snapshot/search driven.
 */
function v2_seo_core_month_content_records(?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $matrix=v2_seo_core_month_matrix();
    $families=v2_seo_seasonal_family_registry();
    $labels=v2_seo_month_labels();
    $records=[];

    foreach($matrix['rows'] as $row){
        $family=$families[$row['country_key']]??null;
        if(!is_array($family))throw new RuntimeException('Missing month family');
        $parent=$family['country'];
        if($row['family']==='resort_month'){
            $parent=null;
            foreach($family['resorts'] as $candidate){
                if((int)($candidate['data']['search_state']['region']??0)===(int)$row['region_id']){$parent=$candidate;break;}
            }
            if(!is_array($parent))throw new RuntimeException('Missing resort parent');
        }
        $data=is_array($parent['data']??null)?$parent['data']:[];
        $baseH1=trim((string)($data['h1']??''));
        $name=trim((string)($data['name']??''));
        $monthNo=(int)$row['month'];
        $month=$labels[$monthNo]??null;
        if($baseH1===''||$name===''||!is_array($month))throw new RuntimeException('Incomplete month content parent');
        $targetYear=v2_seo_core_month_target_year($monthNo,$nowEpoch);
        $h1=$baseH1.' в '.$month['prep'];
        $searchState=['country'=>(int)$row['country_id']];
        if($row['region_id']!==null)$searchState['region']=(int)$row['region_id'];
        $parentPath=rtrim((string)$parent['path'],'/').'/';
        $breadcrumbs=is_array($data['breadcrumbs']??null)?$data['breadcrumbs']:[];
        if($breadcrumbs!==[]){
            $last=array_pop($breadcrumbs);
            if(is_array($last)&&trim((string)($last['label']??''))!=='')$last['href']=$parentPath;
            $breadcrumbs[]=$last;
        }
        $breadcrumbs[]=['label'=>mb_convert_case($month['nom'],MB_CASE_TITLE,'UTF-8')];

        $related=[];
        foreach($matrix['rows'] as $peer){
            if($peer['family']!==$row['family']||(int)$peer['country_id']!==(int)$row['country_id']||$peer['region_id']!==$row['region_id'])continue;
            if((int)$peer['month']===$monthNo)continue;
            $peerMonth=$labels[(int)$peer['month']];
            $related[]=['label'=>$baseH1.' в '.$peerMonth['prep'],'href'=>$peer['path']];
        }

        $period=sprintf('%04d-%02d',$targetYear,$monthNo);
        $records[]=[
            'id'=>'seasonal.'.str_replace('/','.',trim($row['path'],'/')).'.v1',
            'status'=>'review',
            'path'=>$row['path'],
            'type'=>'seasonal',
            'data'=>[
                'name'=>$name,
                'title'=>$h1.' — подбор тура | AnyTour',
                'description'=>$h1.': сравните подходящие отели и варианты отдыха. Актуальные даты, цены, состав тура и доступность проверяйте в поиске AnyTour.',
                'h1'=>$h1,
                'eyebrow'=>'AnyTour · отдых по месяцам',
                'intro'=>'Страница помогает спланировать поездку на ближайший '.$month['nom'].' и перейти от выбора направления к конкретным предложениям. Условия поездки зависят от дат и отеля, поэтому меняющиеся цены, наличие и параметры тура не фиксируются в постоянном SEO-тексте.',
                'breadcrumbs'=>$breadcrumbs,
                'sections'=>[
                    ['id'=>'planning','title'=>'Как планировать поездку в '.$month['prep'],'paragraphs'=>[
                        'Сначала определите город вылета, продолжительность отдыха и состав туристов. Затем сравните подходящие районы или отели и уже после этого проверяйте конкретные предложения на нужные даты.',
                        'Для месяца особенно важно смотреть фактические даты вылета и состав пакета. Эти параметры меняются и должны поступать из актуального поиска, а не из зафиксированного текста страницы.'
                    ]],
                    ['id'=>'hotel-choice','title'=>'Как выбрать подходящий отель','paragraphs'=>[
                        'Сравнивайте расположение, формат отдыха, питание и условия конкретного отеля. При одинаковом направлении разные отели могут заметно отличаться по сценарию поездки.',
                        'После предварительного выбора откройте поиск AnyTour, чтобы проверить доступные варианты, продолжительность, состав тура и актуальную стоимость.'
                    ]],
                    ['id'=>'month-check','title'=>'Что перепроверить перед заявкой','paragraphs'=>[
                        'Перед заявкой перепроверьте даты, город вылета, количество ночей, размещение и состав включённых услуг. Доступность и цена относятся к конкретному предложению и могут измениться.',
                        'Если для страницы доступны свежие ценовые наблюдения, они показываются отдельным динамическим блоком и не подменяют финальную проверку тура.'
                    ]],
                ],
                'related_title'=>'Другие месяцы для этого направления',
                'related'=>array_slice($related,0,11),
                'internal_links'=>[['title'=>'Подбор тура','links'=>[['label'=>'Поиск туров AnyTour','href'=>'/poisk-turov/'],['label'=>$baseH1,'href'=>$parentPath]]]],
                'search_state'=>$searchState,
                'seasonal_identity'=>[
                    'page_key'=>($row['family']==='country_month'?'month:1:':'resort_month:1:').(int)$row['country_id'].($row['region_id']!==null?':'.(int)$row['region_id']:'').':'.$period,
                    'country_id'=>(int)$row['country_id'],'region_id'=>$row['region_id'],'year'=>$targetYear,'month'=>$monthNo,
                ],
            ],
            'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_launch_allowed'=>false,
        ];
    }
    return $records;
}
