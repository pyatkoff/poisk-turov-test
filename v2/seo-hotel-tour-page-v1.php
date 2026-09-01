<?php
require_once __DIR__ . '/site-page-shell-v1.php';
require_once __DIR__ . '/seo-page-contract-v1.php';
require_once __DIR__ . '/seo-offer-snapshot-v1.php';

/**
 * Render a review-only "Туры в отель" SEO page.
 *
 * This runtime intentionally does not participate in publication manifests,
 * sitemap emission or global indexation. Hotel identity must be verified before
 * it reaches this renderer; live offer data is read only from fresh snapshots.
 */
function v2_seo_render_hotel_tours_review(array $record): void
{
    if (($record['type'] ?? '') !== 'hotel_tours' || ($record['status'] ?? '') !== 'review') {
        throw new InvalidArgumentException('SEO hotel tours runtime accepts review hotel_tours records only');
    }

    $path = v2_seo_stable_internal_href($record['path'] ?? '');
    if ($path === null || !str_ends_with($path, '/')) {
        throw new InvalidArgumentException('SEO hotel tours runtime requires a clean trailing-slash path');
    }

    $rawPage = is_array($record['data'] ?? null) ? $record['data'] : [];
    $hotelName = trim((string)($rawPage['name'] ?? ''));
    $countryId = (int)($rawPage['search_state']['country'] ?? 0);
    $hotelId = (int)($rawPage['search_state']['hotel'] ?? 0);
    if ($hotelName === '' || $countryId <= 0 || $hotelId <= 0) {
        throw new InvalidArgumentException('SEO hotel tours runtime requires verified hotel name, country and hotel IDs');
    }

    $page = v2_seo_page_contract($rawPage);
    $context = sp_context($path, $page['title'], $page['description']);
    $context['robots'] = v2_seo_robots_content(false);

    sp_head($context);
    sp_header($context);
    sp_breadcrumbs($page['breadcrumbs']);
    sp_hero($page['eyebrow'] ?: 'AnyTour · туры в отель', $page['h1'], $page['intro']);

    echo '<main class="sp-main sp-hotel-tour-page">';
    foreach ($page['sections'] as $section) {
        $id = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($section['id'] ?? ''));
        echo '<section class="sp-card"'.($id !== '' ? ' id="'.sp_e($id).'"' : '').'><h2>'.sp_e($section['title']).'</h2>';
        foreach ($section['paragraphs'] as $paragraph) echo '<p>'.sp_e($paragraph).'</p>';
        echo '</section>';
    }

    $offers = v2_seo_hotel_snapshot_offers($countryId, $hotelId, 6);
    if ($offers) {
        echo '<section class="sp-card sp-offer-snapshot"><h2>Актуальные туры в '.sp_e($hotelName).'</h2>';
        echo '<p>Предложения взяты из свежих ценовых наблюдений AnyTour. Стоимость и доступность перепроверяются в поиске перед заявкой.</p>';
        echo '<div class="sp-offer-list">';
        foreach ($offers as $offer) {
            $departure = trim((string)($offer['departureName'] ?? ''));
            $date = v2_seo_offer_date_label((string)($offer['departureDate'] ?? ''));
            $nights = (int)($offer['nights'] ?? 0);
            $price = v2_seo_offer_price_label((float)($offer['price'] ?? 0), (string)($offer['currency'] ?? 'RUB'));
            $searchState = $page['search_state'];
            $departureId = (int)($offer['departureId'] ?? 0);
            if ($departureId > 0) $searchState['from'] = $departureId;
            $href = v2_seo_search_handoff_url('/poisk-turov/', $searchState);

            echo '<article class="sp-offer-item">';
            echo '<h3>'.sp_e($hotelName).'</h3><p>';
            if ($departure !== '') echo 'Вылет из '.sp_e($departure).' · ';
            echo sp_e($date).' · '.sp_e((string)$nights).' ночей</p>';
            echo '<p><strong>от '.sp_e($price).'</strong></p>';
            echo '<div class="sp-actions"><a class="sp-secondary" href="'.sp_e($href).'">Посмотреть тур</a></div>';
            echo '</article>';
        }
        echo '</div></section>';
    }

    $links = [];
    foreach ($page['related'] as $link) {
        if (!is_array($link)) continue;
        $href = v2_seo_stable_internal_href($link['href'] ?? '');
        $label = trim((string)($link['label'] ?? ''));
        if ($href !== null && $label !== '') $links[$href] = $label;
    }
    if ($links) {
        echo '<section class="sp-card"><h2>'.sp_e($page['related_title'] ?: 'Связанные страницы').'</h2><div class="sp-actions">';
        foreach ($links as $href => $label) echo '<a class="sp-secondary" href="'.sp_e($href).'">'.sp_e($label).'</a>';
        echo '</div></section>';
    }

    echo '<section class="sp-card sp-search-callout"><h2>Подобрать тур в '.sp_e($hotelName).'</h2>';
    echo '<p>Проверьте актуальные даты, продолжительность, состав пакета, стоимость и доступность перед заявкой.</p>';
    echo '<div class="sp-actions"><a class="sp-primary" href="'.sp_e(v2_seo_search_handoff_url('/poisk-turov/', $page['search_state'])).'">Найти туры в этот отель</a></div></section>';
    echo '</main>';
    sp_end($context);
}
