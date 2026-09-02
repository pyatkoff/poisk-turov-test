<?php
require_once __DIR__ . '/../v2/seo-page-launch-readiness-v1.php';

function unified_ready_fail(string $message): void
{
    fwrite(STDERR, "SEO_UNIFIED_READINESS_FAIL:$message\n");
    exit(1);
}

function page_data(string $name, array $state, bool $deep = true): array
{
    return [
        'name' => $name,
        'title' => 'Туры в ' . $name . ' — подбор и актуальный поиск | AnyTour',
        'h1' => 'Туры в ' . $name,
        'description' => 'Редакционная страница AnyTour помогает сравнить варианты поездки, проверить параметры направления и перейти к актуальному поиску туров перед заявкой.',
        'intro' => $deep
            ? 'Страница помогает разобраться в направлении и перейти к актуальному поиску туров без выдуманных цен, рейтингов или неподтверждённых характеристик.'
            : 'Коротко.',
        'breadcrumbs' => [['label' => 'Главная', 'href' => '/'], ['label' => $name]],
        'sections' => $deep ? [
            ['title' => 'Как выбирать', 'paragraphs' => ['Сравните параметры поездки и формат отдыха.']],
            ['title' => 'Что проверить', 'paragraphs' => ['Перед заявкой перепроверьте актуальные условия в поиске AnyTour.']],
            ['title' => 'Как продолжить', 'paragraphs' => ['Используйте поиск для проверки доступных вариантов.']],
        ] : [['title' => 'Как выбирать', 'paragraphs' => ['Сравните варианты.']]],
        'related' => [],
        'internal_links' => [['title' => 'Поиск', 'links' => [['label' => 'Поиск туров', 'href' => '/poisk-turov/']]]],
        'search_state' => $state,
    ];
}

$countryPath = '/country/testland/';
$resortPath = '/country/testland/resort/test-resort/';
$hotelPath = '/country/testland/hotel/test-hotel-3003/';
$records = [
    ['id'=>'country.testland','status'=>'review','path'=>$countryPath,'type'=>'country','data'=>page_data('Testland',['country'=>9])],
    ['id'=>'resort.testland.test','status'=>'review','path'=>$resortPath,'type'=>'resort','data'=>page_data('Test Resort',['country'=>9,'region'=>77])],
    ['id'=>'hotel_tours.testland.3003','status'=>'review','path'=>$hotelPath,'type'=>'hotel_tours','data'=>page_data('Test Hotel 3003',['country'=>9,'hotel'=>3003])],
];
$catalog = v2_seo_content_catalog($records, [
    $resortPath => ['parent'=>$countryPath],
    $hotelPath => ['parent'=>$countryPath],
]);
$now = 1800000000;
$evidence = [[
    'country_id'=>9,
    'hotel_id'=>3003,
    'hotel_slug'=>'test-hotel-3003',
    'evidence_epoch'=>$now,
    'freshness_seconds'=>600,
]];
$rows = v2_seo_page_launch_readiness($catalog, $evidence, $now);
$byType=[];
foreach($rows as $row) $byType[$row['type']]=$row;
foreach(['country','resort','hotel_tours'] as $type){
    if (($byType[$type]['ready_for_launch_review'] ?? false)!==true) unified_ready_fail($type.'_not_ready');
    if (($byType[$type]['score'] ?? 0)!==100) unified_ready_fail($type.'_score');
}
$summary=v2_seo_page_launch_readiness_summary($rows);
if(count($summary)!==3) unified_ready_fail('summary_types');

$thinRecords=$records;
$thinRecords[1]['data']=page_data('Test Resort',['country'=>9,'region'=>77],false);
$thinCatalog=v2_seo_content_catalog($thinRecords,[$resortPath=>['parent'=>$countryPath],$hotelPath=>['parent'=>$countryPath]]);
$thinRows=v2_seo_page_launch_readiness($thinCatalog,$evidence,$now);
$thinResort=null;
foreach($thinRows as $row) if(($row['type']??'')==='resort') $thinResort=$row;
if(!is_array($thinResort)||($thinResort['ready_for_launch_review']??true)!==false) unified_ready_fail('thin_resort_allowed');
if(!in_array('editorial_depth',$thinResort['errors']??[],true)) unified_ready_fail('thin_resort_reason');

echo "SEO_UNIFIED_READINESS_OK country=100 resort=100 hotel=100 thinResortBlocked=1\n";
