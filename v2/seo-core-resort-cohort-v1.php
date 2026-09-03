<?php
declare(strict_types=1);

function v2_seo_core_resort_month_links(string $countrySlug,string $countryName,string $regionSlug,string $regionName): array
{
    $months=[
        'january'=>'Январь','february'=>'Февраль','march'=>'Март','april'=>'Апрель',
        'may'=>'Май','june'=>'Июнь','july'=>'Июль','august'=>'Август',
        'september'=>'Сентябрь','october'=>'Октябрь','november'=>'Ноябрь','december'=>'Декабрь',
    ];
    $links=[
        ['label'=>'Все туры в '.$countryName,'href'=>'/country/'.$countrySlug.'/'],
        ['label'=>'Туры в '.$regionName,'href'=>'/country/'.$countrySlug.'/'.$regionSlug.'/'],
    ];
    foreach($months as $slug=>$label){
        $links[]=['label'=>$regionName.' — '.$label,'href'=>'/country/'.$countrySlug.'/'.$regionSlug.'/'.$slug.'/'];
    }
    return $links;
}

/**
 * Build scalable resort + resort-month content records from verified first-party
 * catalog region identities. No geography, weather, rating or price facts are invented.
 */
function v2_seo_core_resort_cohort_records(array $rows,int $limit=80): array
{
    $limit=max(1,min(200,$limit));
    $records=[];$seen=[];
    foreach($rows as $row){
        if(count($records)>=$limit)break;
        if(!is_array($row))continue;
        $regionId=(int)($row['region_id']??0);
        $countryId=(int)($row['country_id']??0);
        $regionName=trim((string)($row['region_name']??''));
        $regionSlug=trim((string)($row['region_slug']??''));
        $countrySlug=trim((string)($row['country_slug']??''));
        $countryName=trim((string)($row['country_name']??''));
        $observationCount=max(0,(int)($row['observation_count']??0));
        $hotelCount=max(0,(int)($row['hotel_count']??0));
        $lastObserved=trim((string)($row['last_observed_at']??''));
        if(!in_array($countryId,[1,8],true)||$regionId<=0||$regionName===''||$countryName==='')continue;
        if(!preg_match('/^[a-z0-9-]+$/',$regionSlug)||!preg_match('/^[a-z0-9-]+$/',$countrySlug))continue;
        if($observationCount<=0||$hotelCount<=0||$lastObserved==='')continue;
        $identity=$countryId.':'.$regionId;
        if(isset($seen[$identity]))continue;
        $seen[$identity]=true;
        $basePath='/country/'.$countrySlug.'/'.$regionSlug.'/';
        $related=v2_seo_core_resort_month_links($countrySlug,$countryName,$regionSlug,$regionName);
        $records[]=[
            'id'=>'resort.'.$countryId.'.'.$regionId.'.core.v1',
            'status'=>'approved','path'=>$basePath,'type'=>'resort',
            'data'=>[
                'name'=>$regionName,
                'title'=>'Туры в '.$regionName.' — отдых в '.$countryName.' и подбор тура | AnyTour',
                'description'=>'Подберите тур в '.$regionName.': сравните даты, отели и варианты поездки, а также туры по месяцам в AnyTour.',
                'h1'=>'Туры в '.$regionName,
                'eyebrow'=>$countryName.' · курорты AnyTour',
                'intro'=>$regionName.' — одно из направлений '.$countryName.' в каталоге AnyTour. На странице можно перейти к актуальному поиску туров, сравнить поездку по месяцам и выбрать подходящий отель без фиксации меняющихся цен и наличия в постоянном тексте.',
                'breadcrumbs'=>[
                    ['label'=>'Главная','href'=>'/'],
                    ['label'=>$countryName,'href'=>'/country/'.$countrySlug.'/'],
                    ['label'=>$regionName],
                ],
                'sections'=>[
                    ['id'=>'choose-tour','title'=>'Как выбрать тур в '.$regionName,'paragraphs'=>[
                        'Сначала определите город вылета, даты и продолжительность поездки. Затем сравните доступные отели и состав конкретных предложений в поиске AnyTour.',
                        'Стоимость и наличие меняются, поэтому актуальные коммерческие параметры нужно проверять непосредственно перед заявкой.'
                    ]],
                    ['id'=>'choose-month','title'=>'Туры в '.$regionName.' по месяцам','paragraphs'=>[
                        'Если даты ещё не выбраны, используйте месячные страницы направления. Они связывают курорт с календарём поездки и помогают перейти к поиску на нужный период.',
                        'Месячная навигация остаётся постоянной, а цены и доступность подставляются только из актуальных данных.'
                    ]],
                    ['id'=>'choose-hotel','title'=>'Как сравнивать отели','paragraphs'=>[
                        'Сравнивайте не только название отеля, но и конкретные параметры предложения: даты, количество ночей, размещение, питание и состав тура.',
                        'Финальный выбор делайте по актуальной выдаче, потому что набор доступных вариантов может меняться.'
                    ]],
                ],
                'related_title'=>'Туры в '.$regionName.' по месяцам',
                'related'=>$related,
                'internal_links'=>[[
                    'title'=>'Подбор тура',
                    'links'=>array_merge([['label'=>'Поиск туров AnyTour','href'=>'/poisk-turov/']],$related),
                ]],
                'search_state'=>['country'=>$countryId,'region'=>$regionId],
            ],
            'source'=>[
                'type'=>'first_party_catalog_plus_price_observations',
                'observation_count'=>$observationCount,
                'hotel_count'=>$hotelCount,
                'last_observed_at'=>$lastObserved,
            ],
        ];
    }
    return [
        'state'=>'core_resort_cohort_ready',
        'records'=>$records,'count'=>count($records),'limit'=>$limit,
        'country_scope'=>[1,8],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'route_launch_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
    ];
}
