<?php
declare(strict_types=1);

function v2_seo_core_hotel_month_links(string $countrySlug,string $countryName): array
{
    $months=[
        'january'=>'Январь','february'=>'Февраль','march'=>'Март','april'=>'Апрель',
        'may'=>'Май','june'=>'Июнь','july'=>'Июль','august'=>'Август',
        'september'=>'Сентябрь','october'=>'Октябрь','november'=>'Ноябрь','december'=>'Декабрь',
    ];
    $links=[['label'=>'Все туры в '.$countryName,'href'=>'/country/'.$countrySlug.'/']];
    foreach($months as $slug=>$label){
        $links[]=['label'=>$countryName.' — '.$label,'href'=>'/country/'.$countrySlug.'/'.$slug.'/'];
    }
    return $links;
}

/**
 * Build review-only hotel_tours records from first-party catalog/inventory rows.
 * Input order is the explicit cohort order; this function does not invent a
 * popularity/rating score and does not publish anything.
 */
function v2_seo_core_hotel_cohort_records(array $rows,int $limit=500): array
{
    $limit=max(1,min(500,$limit));
    $records=[];$seenHotels=[];$seenPaths=[];
    foreach($rows as $row){
        if(count($records)>=$limit)break;
        if(!is_array($row))continue;
        $hotelId=(int)($row['hotel_id']??0);
        $countryId=(int)($row['country_id']??0);
        $name=trim((string)($row['hotel_name']??''));
        $hotelSlug=trim((string)($row['hotel_slug']??''));
        $countrySlug=trim((string)($row['country_slug']??''));
        $countryName=trim((string)($row['country_name']??''));
        if($hotelId<=0||$countryId<=0||$name===''||$countryName==='')continue;
        if(!in_array($countryId,[1,4,8],true))continue;
        if(!preg_match('/^[a-z0-9-]+$/',$hotelSlug)||!preg_match('/^[a-z0-9-]+$/',$countrySlug))continue;

        $routeHotelSlug=str_ends_with($hotelSlug,'-'.$hotelId)?$hotelSlug:rtrim($hotelSlug,'-').'-'.$hotelId;
        if(!preg_match('/^[a-z0-9-]+-[0-9]+$/',$routeHotelSlug))continue;
        $path='/country/'.$countrySlug.'/hotel/'.$routeHotelSlug.'/';
        if(isset($seenHotels[$hotelId])||isset($seenPaths[$path]))continue;
        $seenHotels[$hotelId]=true;$seenPaths[$path]=true;
        $observationCount=max(0,(int)($row['observation_count']??0));
        $lastObserved=trim((string)($row['last_observed_at']??''));
        if($observationCount<=0||$lastObserved==='')continue;

        $related=v2_seo_core_hotel_month_links($countrySlug,$countryName);
        $records[]=[
            'id'=>'hotel.'.$countryId.'.'.$hotelId.'.core-review.v1',
            'status'=>'review','path'=>$path,'type'=>'hotel_tours',
            'data'=>[
                'name'=>$name,
                'title'=>'Туры в '.$name.' — подбор поездки | AnyTour',
                'description'=>'Подберите тур в '.$name.': актуальные даты, цены и предложения, а также туры в '.$countryName.' по месяцам в AnyTour.',
                'h1'=>'Туры в '.$name,
                'eyebrow'=>$countryName.' · отели AnyTour',
                'intro'=>'Страница отеля помогает перейти от выбора '.$name.' к актуальным предложениям и сравнить поездку по месяцам. Даты, стоимость, доступность и состав тура меняются, поэтому коммерческие параметры показываются только из свежих данных и перепроверяются в поиске AnyTour.',
                'breadcrumbs'=>[
                    ['label'=>'Главная','href'=>'/'],
                    ['label'=>$countryName,'href'=>'/country/'.$countrySlug.'/'],
                    ['label'=>$name],
                ],
                'sections'=>[
                    ['id'=>'choose-tour','title'=>'Как подобрать тур в '.$name,'paragraphs'=>[
                        'Начните с города вылета, желаемых дат и продолжительности поездки. Затем сравните доступные варианты размещения и состав конкретного турпакета.',
                        'Стоимость и наличие не фиксируются в постоянном тексте страницы: перед заявкой их нужно проверить по актуальной выдаче.'
                    ]],
                    ['id'=>'choose-month','title'=>'Когда ехать в '.$name,'paragraphs'=>[
                        'Если даты поездки ещё не определены, перейдите к турам в '.$countryName.' по месяцам. Так проще сравнить доступные периоды и затем вернуться к поиску предложений именно в '.$name.'.',
                        'Месячные страницы используются как постоянная навигация по календарю поездки и не фиксируют меняющиеся цены или наличие в тексте.'
                    ]],
                    ['id'=>'check-before-request','title'=>'Что проверить перед заявкой','paragraphs'=>[
                        'Проверьте даты, количество ночей, состав туристов, размещение и включённые услуги. Если на странице есть ценовые предложения, они относятся к свежему наблюдению и могут измениться.',
                        'Финальным источником коммерческих параметров остаётся поиск AnyTour.'
                    ]],
                ],
                'related_title'=>'Туры в '.$countryName.' по месяцам',
                'related'=>$related,
                'internal_links'=>[[
                    'title'=>'Подбор тура',
                    'links'=>array_merge([['label'=>'Поиск туров AnyTour','href'=>'/poisk-turov/']],$related),
                ]],
                'search_state'=>['country'=>$countryId,'hotel'=>$hotelId],
            ],
            'cohort_evidence'=>[
                'observation_count'=>$observationCount,
                'last_observed_at'=>$lastObserved,
                'selection_basis'=>'fresh_first_party_inventory_observation_coverage',
            ],
            'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,
            'canonical_launch_allowed'=>false,'route_launch_allowed'=>false,
            'explicit_user_indexation_approval_required'=>true,
        ];
    }
    return [
        'state'=>'review_only_core_hotel_cohort_ready',
        'records'=>$records,'count'=>count($records),'limit'=>$limit,
        'publication_candidates'=>[],
        'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
        'search_contract_changes'=>false,'tourvisor_contract_changes'=>false,
    ];
}
