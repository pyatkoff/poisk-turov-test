<?php
declare(strict_types=1);
require_once __DIR__.'/site-page-shell-v1.php';
require_once __DIR__.'/seo-page-contract-v1.php';
require_once __DIR__.'/seo-seasonal-offer-snapshot-v1.php';
require_once __DIR__.'/seo-price-calendar-v1.php';

/** Render an approved/review seasonal page on its final clean URL. */
function v2_seo_render_seasonal(array $record): void
{
    if(($record['type']??'')!=='seasonal')throw new InvalidArgumentException('SEO seasonal runtime accepts seasonal records only');
    $status=(string)($record['status']??'');
    if(!in_array($status,['review','approved'],true))throw new InvalidArgumentException('SEO seasonal runtime requires review or approved status');
    $path=v2_seo_stable_internal_href($record['path']??'');
    if($path===null||!str_ends_with($path,'/'))throw new InvalidArgumentException('SEO seasonal runtime requires clean trailing-slash path');
    $raw=is_array($record['data']??null)?$record['data']:[];
    $identity=is_array($raw['seasonal_identity']??null)?$raw['seasonal_identity']:[];
    $pageKey=trim((string)($identity['page_key']??''));
    if($pageKey==='')throw new InvalidArgumentException('SEO seasonal runtime requires exact seasonal page key');
    $page=v2_seo_page_contract($raw);
    $context=sp_context($path,$page['title'],$page['description']);
    if($status!=='approved')$context['robots']=v2_seo_robots_content(false);

    sp_head($context);sp_header($context);sp_breadcrumbs($page['breadcrumbs']);
    sp_hero($page['eyebrow']?:'AnyTour · сезон',$page['h1'],$page['intro'],v2_seo_search_handoff_url('/poisk-turov/',$page['search_state']),'Подобрать тур');
    echo '<main class="sp-main sp-seo-editorial-page sp-seasonal-page">';
    echo '<div class="sp-editorial-grid">';
    foreach($page['sections'] as $section){
        $id=preg_replace('/[^a-zA-Z0-9_-]+/','-',(string)($section['id']??''));
        echo '<section class="sp-card sp-editorial-section"'.($id!==''?' id="'.sp_e($id).'"':'').'><h2>'.sp_e($section['title']).'</h2>';
        foreach($section['paragraphs'] as $paragraph)echo '<p>'.sp_e($paragraph).'</p>';
        echo '</section>';
    }
    echo '</div>';

    $offers=v2_seo_seasonal_snapshot_offers($pageKey,6);
    if($offers){
        echo '<section class="sp-card sp-offer-snapshot"><h2>Свежие предложения на выбранный месяц</h2><p>Блок построен из свежих ценовых наблюдений AnyTour. Стоимость и доступность перепроверяются в поиске перед заявкой.</p><div class="sp-offer-list">';
        foreach($offers as $offer){
            $hotel=trim((string)($offer['hotelName']??''))?:'Отель';
            $departure=trim((string)($offer['departureName']??''));
            $date=v2_seo_offer_date_label((string)($offer['departureDate']??''));
            $nights=(int)($offer['nights']??0);
            $priceMarkup=v2_seo_offer_price_markup($offer);
            $searchState=$page['search_state'];$departureId=(int)($offer['departureId']??0);if($departureId>0)$searchState['from']=$departureId;
            $href=v2_seo_search_handoff_url('/poisk-turov/',$searchState);
            echo '<article class="sp-offer-item"><h3>'.sp_e($hotel).'</h3><div class="sp-offer-meta">';
            if($departure!=='')echo '<span class="sp-offer-fact">Вылет из '.sp_e($departure).'</span>';
            echo '<span class="sp-offer-fact">'.sp_e($date).'</span><span class="sp-offer-fact">'.sp_e((string)$nights).' ночей</span></div><div class="sp-offer-bottom">'.$priceMarkup.'<a class="sp-secondary sp-offer-action" href="'.sp_e($href).'">Посмотреть туры</a></div></article>';
        }
        echo '</div></section>';
    }

    $countryId=(int)($identity['country_id']??($page['search_state']['country']??0));
    $regionId=(int)($identity['region_id']??($page['search_state']['region']??0));
    $year=(int)($identity['year']??0);
    $month=(int)($identity['month']??0);
    $monthFrom=null;$monthTo=null;
    if($year>=2020&&$year<=2100&&$month>=1&&$month<=12){
        try{
            $monthStart=v2_price_calendar_date(sprintf('%04d-%02d-01',$year,$month));
            $monthFrom=$monthStart->format('Y-m-d');
            $monthTo=$monthStart->modify('last day of this month')->format('Y-m-d');
        }catch(Throwable){$monthFrom=null;$monthTo=null;}
    }
    $calendarOffers=($countryId>0&&$monthFrom!==null&&$monthTo!==null)?v2_seo_seasonal_snapshot_offers($pageKey,12):[];
    $priceCalendar=$calendarOffers?v2_seo_price_calendar($calendarOffers,$countryId,$regionId,14,$monthFrom,$monthTo):[];
    echo v2_seo_render_price_calendar($priceCalendar,$page['search_state'],'Цены по датам вылета в выбранном месяце');

    $links=[];foreach($page['related'] as $link){if(!is_array($link))continue;$href=v2_seo_stable_internal_href($link['href']??'');$label=trim((string)($link['label']??''));if($href!==null&&$label!=='')$links[$href]=$label;}
    if($links){echo '<section class="sp-card sp-related-card"><h2>'.sp_e($page['related_title']?:'Другие направления').'</h2><div class="sp-actions">';foreach($links as $href=>$label)echo '<a class="sp-secondary" href="'.sp_e($href).'">'.sp_e($label).'</a>';echo '</div></section>';}
    echo '<section class="sp-card sp-search-callout"><h2>Проверить актуальный тур</h2><p>Даты, состав пакета, стоимость и доступность не фиксируются в SEO-тексте и берутся из актуального поиска.</p><div class="sp-actions"><a class="sp-primary" href="'.sp_e(v2_seo_search_handoff_url('/poisk-turov/',$page['search_state'])).'">Перейти к поиску туров</a></div></section>';
    echo '</main>';sp_end($context);
}
