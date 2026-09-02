<?php
require_once __DIR__ . '/../v2/seo-page-launch-readiness-v1.php';
require_once __DIR__ . '/../v2/seo-launch-manifest-v1.php';
require_once __DIR__ . '/../v2/seo-hotel-review-launch-slice-v1.php';

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

$manifest=v2_seo_launch_manifest($catalog,$evidence,$now);
if(($manifest['integrity_ok']??false)!==true||($manifest['review_ready']??false)!==true||($manifest['quality_score']??0)!==100) unified_ready_fail('manifest_ready');
if(($manifest['registry_count']??0)!==3||($manifest['readiness_row_count']??0)!==3||($manifest['ready_count']??0)!==3||($manifest['blocked_count']??-1)!==0) unified_ready_fail('manifest_counts');
if(($manifest['hotel_tours_review_ready_count']??0)!==1||($manifest['hotel_tours_publication_candidate_count']??-1)!==0) unified_ready_fail('manifest_hotel_counts');
if(($manifest['hotel_tours_publication_allowed']??true)!==false||($manifest['hotel_tours_indexation_allowed']??true)!==false||($manifest['publication_allowed']??true)!==false) unified_ready_fail('manifest_publication_boundary');
if(($manifest['hotel_evidence_valid_until_epoch']??0)!==($now+600)) unified_ready_fail('manifest_evidence_clock');
if(!preg_match('/^[a-f0-9]{64}$/',(string)($manifest['manifest_sha256']??''))) unified_ready_fail('manifest_fingerprint');

$reviewRows=[];
foreach([[4,4004,'turkey'],[8,8008,'maldives'],[1,1001,'egypt']] as [$countryId,$hotelId,$slug]){
    $reviewRows[]=['path'=>'/country/'.$slug.'/hotel/test-'.$hotelId.'/','country_id'=>$countryId,'hotel_id'=>$hotelId,'evidence_epoch'=>$now,'evidence_expires_epoch'=>$now+600,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]];
}
$reviewSlice=v2_seo_hotel_review_launch_slice($reviewRows,[
    ['country_id'=>4,'paths'=>['/country/turkey/hotel/test-4004/']],
    ['country_id'=>8,'paths'=>['/country/maldives/hotel/test-8008/']],
    ['country_id'=>1,'paths'=>['/country/egypt/hotel/test-1001/']],
],[4,8,1],1,3,$now);
if(($reviewSlice['state']??'')!=='review_only_requires_separate_indexation_approval'||($reviewSlice['total']??0)!==3) unified_ready_fail('review_slice_state');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag) if(($reviewSlice[$flag]??true)!==false) unified_ready_fail('review_slice_'.$flag);
if(($reviewSlice['publication_candidates']??null)!==[]||($reviewSlice['explicit_user_indexation_approval_required']??false)!==true) unified_ready_fail('review_slice_approval_boundary');
if(!preg_match('/^[a-f0-9]{64}$/',(string)($reviewSlice['evidence_manifest_sha256']??''))) unified_ready_fail('review_slice_fingerprint');

$unsafe=$catalog;
$unsafe['publication_candidates'][]=$hotelPath;
$unsafeManifest=v2_seo_launch_manifest($unsafe,$evidence,$now);
if(($unsafeManifest['integrity_ok']??true)!==false||!in_array('hotel_tours_publication_candidate_leak',$unsafeManifest['errors']??[],true)) unified_ready_fail('manifest_hotel_candidate_leak');

$invalid=$catalog;
$invalid['registry'][$hotelPath]['page']['search_state']['hotel']=0;
$invalidManifest=v2_seo_launch_manifest($invalid,$evidence,$now);
if(($invalidManifest['integrity_ok']??true)!==false||!in_array('duplicate_or_invalid_search_identity',$invalidManifest['errors']??[],true)) unified_ready_fail('manifest_invalid_identity_not_blocked');

$thinRecords=$records;
$thinRecords[1]['data']=page_data('Test Resort',['country'=>9,'region'=>77],false);
$thinCatalog=v2_seo_content_catalog($thinRecords,[$resortPath=>['parent'=>$countryPath],$hotelPath=>['parent'=>$countryPath]]);
$thinRows=v2_seo_page_launch_readiness($thinCatalog,$evidence,$now);
$thinResort=null;
foreach($thinRows as $row) if(($row['type']??'')==='resort') $thinResort=$row;
if(!is_array($thinResort)||($thinResort['ready_for_launch_review']??true)!==false) unified_ready_fail('thin_resort_allowed');
if(!in_array('editorial_depth',$thinResort['errors']??[],true)) unified_ready_fail('thin_resort_reason');
$thinManifest=v2_seo_launch_manifest($thinCatalog,$evidence,$now);
if(($thinManifest['integrity_ok']??false)!==true||($thinManifest['review_ready']??true)!==false||($thinManifest['quality_score']??100)>=100||($thinManifest['blocked_by_type']['resort']??0)!==1) unified_ready_fail('manifest_quality_block');

echo "SEO_UNIFIED_READINESS_OK country=100 resort=100 hotel=100 manifest=100 hotelBoundary=1 reviewSliceBoundary=1 thinResortBlocked=1\n";
