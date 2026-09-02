<?php
require_once __DIR__ . '/../v2/seo-hotel-launch-readiness-v1.php';

function readiness_fail(string $message): void
{
    fwrite(STDERR, "SEO_HOTEL_READINESS_FAIL:$message\n");
    exit(1);
}

$parent = [
    'id' => 'country.test.v1',
    'status' => 'review',
    'path' => '/country/test/',
    'type' => 'country',
    'data' => [
        'name' => 'Тестовая страна',
        'h1' => 'Туры в тестовую страну',
        'description' => 'Редакционная страница тестовой страны с полезной информацией и переходом к поиску туров AnyTour.',
        'intro' => 'Редакционное введение тестовой страны достаточно подробное для прохождения структурной проверки качества страницы.',
        'breadcrumbs' => [['label'=>'Главная','href'=>'/'],['label'=>'Тестовая страна']],
        'sections' => [
            ['title'=>'Как выбрать','paragraphs'=>['Сравните варианты отдыха.']],
            ['title'=>'Когда ехать','paragraphs'=>['Проверьте сезонность.']],
        ],
        'related' => [['label'=>'Туры в Test Hotel','href'=>'/country/test/hotel/test-hotel-123/']],
        'internal_links' => [['title'=>'Подбор тура','links'=>[['label'=>'Поиск','href'=>'/poisk-turov/']]]],
        'search_state' => ['country'=>77],
    ],
];

$hotel = [
    'id' => 'hotel_tours.test.test-hotel-123.v1',
    'status' => 'review',
    'path' => '/country/test/hotel/test-hotel-123/',
    'type' => 'hotel_tours',
    'data' => [
        'name' => 'Test Hotel',
        'title' => 'Туры в Test Hotel — цены и подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Test Hotel: сравните даты, продолжительность и параметры поездки, а актуальные предложения проверьте в поиске AnyTour.',
        'h1' => 'Туры в Test Hotel',
        'intro' => 'Эта страница помогает сравнить пакетные туры именно в Test Hotel по датам, продолжительности и основным параметрам поездки перед переходом к актуальному поиску.',
        'breadcrumbs' => [['label'=>'Главная','href'=>'/'],['label'=>'Тестовая страна','href'=>'/country/test/'],['label'=>'Туры в Test Hotel']],
        'sections' => [
            ['title'=>'Как сравнивать туры','paragraphs'=>['Сравнивайте одинаковые параметры поездки.']],
            ['title'=>'Параметры пакета','paragraphs'=>['Проверяйте состав конкретного предложения.']],
            ['title'=>'Актуальный поиск','paragraphs'=>['Перепроверяйте цену и доступность перед заявкой.']],
        ],
        'related' => [['label'=>'Тестовая страна','href'=>'/country/test/']],
        'internal_links' => [['title'=>'Подбор тура','links'=>[['label'=>'Поиск','href'=>'/poisk-turov/']]]],
        'search_state' => ['country'=>77,'hotel'=>123],
    ],
];

$catalog = v2_seo_content_catalog([$parent, $hotel], [
    '/country/test/hotel/test-hotel-123/' => ['parent'=>'/country/test/'],
]);
$now = 2000000000;
$evidence = [[
    'country_id'=>77,
    'hotel_id'=>123,
    'hotel_slug'=>'test-hotel-123',
    'evidence_epoch'=>$now - 60,
    'freshness_seconds'=>600,
]];

$report = v2_seo_hotel_launch_readiness($catalog, $evidence, $now);
if (count($report)!==1) readiness_fail('record_count');
if (($report[0]['score']??0)!==100) readiness_fail('expected_100');
if (($report[0]['ready_for_launch_review']??false)!==true) readiness_fail('expected_ready');

$stale = $evidence;
$stale[0]['evidence_epoch'] = $now - 1200;
$staleReport = v2_seo_hotel_launch_readiness($catalog, $stale, $now);
if (($staleReport[0]['ready_for_launch_review']??true)!==false) readiness_fail('stale_ready');
if (!in_array('fresh_identity_evidence_required',$staleReport[0]['errors']??[],true)) readiness_fail('stale_error');

$wrongCountry = $evidence;
$wrongCountry[0]['country_id'] = 8;
$wrongCountryReport = v2_seo_hotel_launch_readiness($catalog, $wrongCountry, $now);
if (($wrongCountryReport[0]['score']??100)!==90) readiness_fail('wrong_country_score');

$thinHotel = $hotel;
$thinHotel['data']['sections'] = array_slice($thinHotel['data']['sections'], 0, 2);
$thinCatalog = v2_seo_content_catalog([$parent, $thinHotel], [
    '/country/test/hotel/test-hotel-123/' => ['parent'=>'/country/test/'],
]);
$thinReport = v2_seo_hotel_launch_readiness($thinCatalog, $evidence, $now);
if (($thinReport[0]['ready_for_launch_review']??true)!==false) readiness_fail('thin_ready');
if (!in_array('editorial_depth',$thinReport[0]['errors']??[],true)) readiness_fail('thin_error');

if (v2_seo_content_candidate_paths($catalog)!==[]) readiness_fail('review_became_publication_candidate');

echo "SEO_HOTEL_LAUNCH_READINESS_OK score=100 stale=blocked thin=blocked publicationCandidates=0\n";
