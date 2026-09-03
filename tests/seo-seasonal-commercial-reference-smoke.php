<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-seasonal-review-content-v1.php';
require_once __DIR__.'/../v2/seo-seasonal-intent-v1.php';
require_once __DIR__.'/../v2/seo-seasonal-offer-snapshot-v1.php';
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

$offers=[
    ['id'=>'cheap','price'=>100000,'departureDate'=>'2026-09-10'],
    ['id'=>'plain-near','price'=>101000,'departureDate'=>'2026-09-11'],
    ['id'=>'promo-strong','price'=>108000,'departureDate'=>'2026-09-12','priceIntelligence'=>['showPromoDrop'=>true,'historicalDropPercent'=>18]],
    ['id'=>'promo-recent','price'=>106000,'departureDate'=>'2026-09-13','priceIntelligence'=>['showPromoDrop'=>true,'historicalDropPercent'=>9]],
    ['id'=>'promo-too-expensive','price'=>113000,'departureDate'=>'2026-09-14','priceIntelligence'=>['showPromoDrop'=>true,'historicalDropPercent'=>30]],
    ['id'=>'plain','price'=>104000,'departureDate'=>'2026-09-15'],
];
$ranked=v2_seo_rank_seasonal_offer_cards($offers,4);
$ids=array_column($ranked,'id');
if($ids!==['cheap','promo-strong','promo-recent','plain-near'])seasonal_commercial_fail('promo_ranking_'.implode(',',$ids));
if(($ranked[0]['price']??0)!==100000)seasonal_commercial_fail('cheapest_not_retained');
if(in_array('promo-too-expensive',$ids,true))seasonal_commercial_fail('price_guard');

echo "SEO_SEASONAL_COMMERCIAL_REFERENCE_OK h1=1 country=4 region=20 publication=0 indexation=0 promoSelection=1 cheapestRetained=1 priceGuard=12pct\n";
