<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-review-page-v1.php';

function seasonal_preview_fail(string $code): void { fwrite(STDERR,"SEO_SEASONAL_PREVIEW_FAIL:$code\n"); exit(1); }

$catalog=v2_seo_seasonal_preview_catalog();
$antalya=$catalog['antalya-september']??null;
if(!is_array($antalya)) seasonal_preview_fail('missing_antalya');
if(($antalya['path']??'')!=='/_preview/seo2/seasonal/antalya-september/') seasonal_preview_fail('antalya_preview_path');
if(($antalya['parent_path']??'')!=='/country/turkey/antalya/') seasonal_preview_fail('antalya_parent');
if(($antalya['search_state']??null)!==['country'=>4,'region'=>20]) seasonal_preview_fail('antalya_search_identity');

$maldives=$catalog['maldives-september']??null;
if(!is_array($maldives)) seasonal_preview_fail('missing_maldives');
if(($maldives['path']??'')!=='/_preview/seo2/seasonal/maldives-september/') seasonal_preview_fail('maldives_preview_path');
if(($maldives['parent_path']??'')!=='/country/maldives/') seasonal_preview_fail('maldives_parent');
if(($maldives['search_state']??null)!==['country'=>8]) seasonal_preview_fail('maldives_search_identity');

$_SERVER['DOCUMENT_ROOT']='/tmp/seo2-seasonal-preview-empty-root';
$_SERVER['HTTP_HOST']='anytoour.ru';
@mkdir($_SERVER['DOCUMENT_ROOT'],0777,true);

$_SERVER['SCRIPT_NAME']='/_preview/seo2/seasonal/antalya-september/index.php';
ob_start();
v2_seo_render_seasonal_preview('antalya-september',strtotime('2026-09-02T11:30:00Z'));
$html=ob_get_clean();
if(substr_count($html,'<h1')!==1) seasonal_preview_fail('antalya_h1');
if(!str_contains($html,'<meta name="robots" content="noindex,follow">')) seasonal_preview_fail('antalya_robots');
if(str_contains($html,'rel="canonical"')) seasonal_preview_fail('antalya_canonical_present');
if(!str_contains($html,'Анталья в сентябре')) seasonal_preview_fail('antalya_identity_copy');
foreach(['25,3 °C','31,2 °C','19,6 °C','16,7 мм','1,71 дня'] as $fact) if(!str_contains($html,$fact)) seasonal_preview_fail('antalya_missing_fact');
if(!str_contains($html,'/poisk-turov/?country=4&amp;region=20')) seasonal_preview_fail('antalya_handoff');
if(!str_contains($html,'Официальная климатическая статистика MGM')) seasonal_preview_fail('antalya_source_note');
if(!str_contains($html,'не включена в sitemap')) seasonal_preview_fail('antalya_review_notice');

$_SERVER['SCRIPT_NAME']='/_preview/seo2/seasonal/maldives-september/index.php';
ob_start();
v2_seo_render_seasonal_preview('maldives-september',strtotime('2026-09-02T11:30:00Z'));
$html=ob_get_clean();
if(substr_count($html,'<h1')!==1) seasonal_preview_fail('maldives_h1');
if(!str_contains($html,'<meta name="robots" content="noindex,follow">')) seasonal_preview_fail('maldives_robots');
if(str_contains($html,'rel="canonical"')) seasonal_preview_fail('maldives_canonical_present');
if(!str_contains($html,'Мальдивы в сентябре')) seasonal_preview_fail('maldives_identity_copy');
foreach(['25–32 °C','с середины мая до ноября','7–9 часов'] as $fact) if(!str_contains($html,$fact)) seasonal_preview_fail('maldives_missing_fact');
if(!str_contains($html,'/poisk-turov/?country=8')) seasonal_preview_fail('maldives_handoff');
if(str_contains($html,'/poisk-turov/?country=8&amp;region=')) seasonal_preview_fail('maldives_region_leak');
if(!str_contains($html,'Официальная климатическая страница Maldives Meteorological Service')) seasonal_preview_fail('maldives_source_note');
if(!str_contains($html,'не включена в sitemap')) seasonal_preview_fail('maldives_review_notice');
if(str_contains($html,'publication_allowed=true')||str_contains($html,'indexation_allowed=true')) seasonal_preview_fail('launch_leak');

foreach(['antalya-september','maldives-september'] as $key){
    $source=file_get_contents(dirname(__DIR__).'/v2/_preview/seo2/seasonal/'.$key.'/index.php');
    if($source===false||!str_contains($source,"v2_seo_render_seasonal_preview('".$key."')")) seasonal_preview_fail($key.'_route_renderer');
}

echo "SEO_SEASONAL_PREVIEW_OK previews=2\n";
