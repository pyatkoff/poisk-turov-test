<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-seasonal-review-content-v1.php';
require_once __DIR__.'/../v2/seo-seasonal-intent-v1.php';
function seasonal_commercial_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_COMMERCIAL_REFERENCE_FAIL:$m\n");exit(1);}
$records=v2_seo_seasonal_review_content_prototypes();
$r=$records['antalya-september']??null;
if(!is_array($r))seasonal_commercial_fail('missing_record');
if(($r['page_key']??'')!=='resort_month:1:4:20:2026-09'||($r['country_id']??0)!==4||($r['region_id']??0)!==20)seasonal_commercial_fail('identity');
if(($r['h1']??'')!=='Туры в Анталью в сентябре'||!str_starts_with((string)($r['title']??''),'Туры в Анталью в сентябре'))seasonal_commercial_fail('commercial_heading');
if(!str_contains((string)($r['intro']??''),'актуальной выдачей поиска AnyTour'))seasonal_commercial_fail('search_handoff_copy');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_creation_allowed'] as $flag){if(($r[$flag]??true)!==false)seasonal_commercial_fail('boundary_'.$flag);}
$intent=v2_seo_seasonal_intent_contract([
    'page_key'=>$r['page_key'],
    'page_role'=>'commercial_tour_landing',
    'search_intent'=>'commercial_transactional',
    'path'=>'/_preview/seo2/seasonal/antalya-september/',
    'search_state'=>['country'=>4,'region'=>20],
    'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_allowed'=>false,'route_launch_allowed'=>false,'publication_candidates'=>[],
]);
if(($intent['state']??'')!=='review_intent_ready'||($intent['review_ready']??false)!==true)seasonal_commercial_fail('intent');
if(($intent['publication_allowed']??true)!==false||($intent['indexation_allowed']??true)!==false)seasonal_commercial_fail('intent_boundary');
echo "SEO_SEASONAL_COMMERCIAL_REFERENCE_OK h1=1 country=4 region=20 publication=0 indexation=0\n";
