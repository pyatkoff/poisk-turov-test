<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-review-page-v1.php';

function seasonal_preview_fail(string $code): void { fwrite(STDERR,"SEO_SEASONAL_PREVIEW_FAIL:$code\n"); exit(1); }

$catalog=v2_seo_seasonal_preview_catalog();
$expected=[
    'antalya-september'=>['path'=>'/_preview/seo2/seasonal/antalya-september/','parent'=>'/country/turkey/antalya/','search'=>['country'=>4,'region'=>20]],
    'maldives-september'=>['path'=>'/_preview/seo2/seasonal/maldives-september/','parent'=>'/country/maldives/','search'=>['country'=>8]],
    'antalya-october'=>['path'=>'/_preview/seo2/seasonal/antalya-october/','parent'=>'/country/turkey/antalya/','search'=>['country'=>4,'region'=>20]],
    'maldives-october'=>['path'=>'/_preview/seo2/seasonal/maldives-october/','parent'=>'/country/maldives/','search'=>['country'=>8]],
];
if(array_keys($catalog)!==array_keys($expected)) seasonal_preview_fail('catalog_keys');
foreach($expected as $key=>$want){
    $row=$catalog[$key]??null;if(!is_array($row))seasonal_preview_fail('missing_'.$key);
    if(($row['path']??'')!==$want['path'])seasonal_preview_fail($key.'_path');
    if(($row['parent_path']??'')!==$want['parent'])seasonal_preview_fail($key.'_parent');
    if(($row['search_state']??null)!==$want['search'])seasonal_preview_fail($key.'_search_identity');
}

$integrity=v2_seo_seasonal_preview_integrity(dirname(__DIR__).'/v2');
if(($integrity['state']??'')!=='review_ready'||($integrity['review_ready']??false)!==true) seasonal_preview_fail('integrity_not_ready');
if(($integrity['preview_count']??0)!==4||($integrity['blocked']??null)!==[]) seasonal_preview_fail('integrity_counts');
if(($integrity['publication_candidates']??null)!==[]) seasonal_preview_fail('integrity_publication_candidates');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_allowed','route_launch_allowed'] as $flag){
    if(($integrity[$flag]??true)!==false) seasonal_preview_fail('integrity_boundary_'.$flag);
}
if(v2_seo_seasonal_preview_headers()!==['X-Robots-Tag: noindex, follow']) seasonal_preview_fail('x_robots_contract');
$missingRoutes=v2_seo_seasonal_preview_integrity('/tmp/seo2-seasonal-preview-missing-routes');
if(($missingRoutes['state']??'')!=='blocked'||($missingRoutes['review_ready']??true)!==false) seasonal_preview_fail('missing_routes_not_blocked');
$missingCodes=array_column($missingRoutes['blocked']??[],'code');
if(!in_array('missing_physical_preview_route',$missingCodes,true)) seasonal_preview_fail('missing_route_code');

$_SERVER['DOCUMENT_ROOT']='/tmp/seo2-seasonal-preview-empty-root';
$_SERVER['HTTP_HOST']='anytoour.ru';
@mkdir($_SERVER['DOCUMENT_ROOT'],0777,true);

$renderCases=[
    'antalya-september'=>['at'=>'2026-09-03T12:00:00Z','copy'=>'Туры в Анталью в сентябре','facts'=>['25,3 °C','31,2 °C','19,6 °C','16,7 мм','1,71 дня'],'handoff'=>'/poisk-turov/?country=4&amp;region=20'],
    'maldives-september'=>['at'=>'2026-09-03T12:00:00Z','copy'=>'Мальдивы в сентябре','facts'=>['25–32 °C','с середины мая до ноября','7–9 часов'],'handoff'=>'/poisk-turov/?country=8'],
    'antalya-october'=>['at'=>'2026-09-03T12:00:00Z','copy'=>'Туры в Анталью в октябре','facts'=>['20,6 °C','26,6 °C','15,4 °C','70,6 мм','5,38 дня'],'handoff'=>'/poisk-turov/?country=4&amp;region=20'],
    'maldives-october'=>['at'=>'2026-09-03T12:00:00Z','copy'=>'Мальдивы в октябре','facts'=>['25–32 °C','с середины мая до ноября','Октябрь попадает'],'handoff'=>'/poisk-turov/?country=8'],
];
foreach($renderCases as $key=>$case){
    $_SERVER['SCRIPT_NAME']='/_preview/seo2/seasonal/'.$key.'/index.php';
    ob_start();v2_seo_render_seasonal_preview($key,strtotime($case['at']));$html=ob_get_clean();
    if(substr_count($html,'<h1')!==1)seasonal_preview_fail($key.'_h1');
    if(!str_contains($html,'<meta name="robots" content="noindex,follow">'))seasonal_preview_fail($key.'_robots');
    if(str_contains($html,'rel="canonical"'))seasonal_preview_fail($key.'_canonical_present');
    if(!str_contains($html,$case['copy']))seasonal_preview_fail($key.'_identity_copy');
    foreach($case['facts'] as $fact)if(!str_contains($html,$fact))seasonal_preview_fail($key.'_missing_fact');
    if(!str_contains($html,$case['handoff']))seasonal_preview_fail($key.'_handoff');
    if(!str_contains($html,'не включена в sitemap'))seasonal_preview_fail($key.'_review_notice');
    if(str_starts_with($key,'maldives-')&&str_contains($html,'/poisk-turov/?country=8&amp;region='))seasonal_preview_fail($key.'_region_leak');
}

foreach(array_keys($expected) as $key){
    $source=file_get_contents(dirname(__DIR__).'/v2/_preview/seo2/seasonal/'.$key.'/index.php');
    if($source===false||!str_contains($source,"v2_seo_render_seasonal_preview('".$key."')")) seasonal_preview_fail($key.'_route_renderer');
}

echo "SEO_SEASONAL_PREVIEW_OK previews=4 october=2 integrity=review_ready xrobots=noindex publication=0\n";
