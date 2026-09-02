<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-review-content-v1.php';
$now=strtotime('2026-09-02T11:30:00Z');
$records=v2_seo_seasonal_review_content_prototypes();
if(array_keys($records)!==['antalya-september','maldives-september']) exit(1);

$tr=v2_seo_seasonal_render_review_content($records['antalya-september'],$now);
if(($tr['state']??'')!=='rendered_review_only_seasonal_content'||($tr['page_key']??'')!=='resort_month:1:4:20:2026-09') exit(2);
if(($tr['publication_candidates']??null)!==[]||($tr['publication_allowed']??true)!==false||($tr['indexation_allowed']??true)!==false||($tr['sitemap_allowed']??true)!==false||($tr['route_creation_allowed']??true)!==false||($tr['requires_fresh_identity_rebind']??false)!==true) exit(3);
$text=implode("\n",array_column($tr['sections'],'text'));
foreach(['25,3 °C','31,2 °C','19,6 °C','16,7 мм','1,71 дня'] as $fact) if(!str_contains($text,$fact)) exit(4);
if(str_contains($text,'{{')) exit(5);
foreach($tr['claims'] as $claim) if(($claim['geography_scope']['region_id']??0)!==20) exit(6);

$mv=v2_seo_seasonal_render_review_content($records['maldives-september'],$now);
if(($mv['state']??'')!=='rendered_review_only_seasonal_content'||($mv['page_key']??'')!=='month:1:8:2026-09') exit(7);
$text=implode("\n",array_column($mv['sections'],'text'));
foreach(['25–32 °C','с середины мая до ноября','7–9 часов'] as $fact) if(!str_contains($text,$fact)) exit(8);
foreach($mv['claims'] as $claim) if(($claim['geography_scope']['level']??'')!=='country'||($claim['geography_scope']['country_id']??0)!==8) exit(9);

$bad=$records['antalya-september']; $bad['claims'][0]['source_url']='https://www.mgm.gov.tr/eng/forecast-cities.aspx?m=ANTALYA';
if((v2_seo_seasonal_render_review_content($bad,$now)['state']??'')!=='blocked') exit(10);
$bad=$records['antalya-september']; $bad['page_key']='month:1:4:2026-09';
// The authored record page_key itself is metadata, but every claim remains resort-scoped;
// publication remains impossible and the fresh editorial binder must later require exact key parity.
if((v2_seo_seasonal_render_review_content($bad,$now)['publication_allowed']??true)!==false) exit(11);
$bad=$records['maldives-september']; $bad['sections'][0]['claim_keys']=['missing'];
try { v2_seo_seasonal_render_review_content($bad,$now); exit(12); } catch (InvalidArgumentException $e) {}
$bad=$records['maldives-september']; $bad['indexation_allowed']=true;
try { v2_seo_seasonal_render_review_content($bad,$now); exit(13); } catch (InvalidArgumentException $e) {}

echo "SEO_SEASONAL_REVIEW_CONTENT_OK\n";
