<?php
$css = file_get_contents(__DIR__ . '/../v2/seo-editorial-reference-v1.css');
$promoCss = file_get_contents(__DIR__ . '/../v2/seo-price-promo-v1.css');
$editorialCss = file_get_contents(__DIR__ . '/../v2/editorial-ds2-convergence-v1.css');
$shell = file_get_contents(__DIR__ . '/../v2/site-page-shell-v1.php');
$offerSnapshot = file_get_contents(__DIR__ . '/../v2/seo-offer-snapshot-v1.php');
$country = file_get_contents(__DIR__ . '/../v2/country-page-v1.php');
$resort = file_get_contents(__DIR__ . '/../v2/seo-resort-page-v1.php');
$seasonal = file_get_contents(__DIR__ . '/../v2/seo-seasonal-page-v1.php');
$hotel = file_get_contents(__DIR__ . '/../v2/seo-hotel-tour-page-v1.php');
if ($css === false || trim($css) === '' || $promoCss === false || trim($promoCss) === '' || $editorialCss === false || $shell === false || $offerSnapshot === false || $country === false || $resort === false || $seasonal === false || $hotel === false) exit(1);
$required = [
    '.sp-hero-actions{display:flex;align-items:center',
    '.sp-hero-actions .sp-primary{min-height:48px',
    '.sp-editorial-section:first-child{grid-column:1/-1',
    '.sp-editorial-grid>.sp-editorial-section:last-child:nth-child(even){grid-column:1/-1',
    '.sp-offer-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))',
    '.sp-offer-meta{display:flex;flex-wrap:wrap',
    '.sp-offer-bottom{display:flex;align-items:flex-end;justify-content:space-between',
    '.sp-offer-price strong{color:var(--at-ink);font-size:24px',
    '.sp-offer-action{width:auto!important;min-width:128px',
    '@media(max-width:1024px){.sp-offer-list{grid-template-columns:repeat(2,minmax(0,1fr))',
    '@media(max-width:560px)',
    '.sp-hero-actions .sp-primary{width:100%',
    '.sp-offer-list{grid-template-columns:1fr',
    '.sp-offer-bottom{align-items:stretch;flex-direction:column',
    '.sp-offer-action{width:100%!important;min-width:0',
    '.sp-related-card .sp-secondary',
    '.sp-seo-editorial-page .sp-search-callout',
    '@media(min-width:769px){.sp-seo-editorial-page{gap:18px}',
    '.sp-seo-editorial-page .sp-search-callout{display:grid;grid-template-columns:minmax(0,1fr) auto',
    '.sp-seo-editorial-page .sp-search-callout .sp-actions{grid-column:2;grid-row:1/3',
    '.sp-related-card{border:0;border-top:1px solid var(--at-line);border-radius:0;background:transparent;box-shadow:none!important',
    '.sp-related-card .sp-secondary{min-height:38px;background:#fff;border-color:var(--at-line)',
    '@media(min-width:900px){.sp-hero--hotel-tour .sp-wrap{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,360px)',
    '.sp-hero--hotel-tour .sp-hero-actions{grid-column:2;grid-row:1/4;display:grid',
    '.sp-hero--hotel-tour .sp-hero-actions .sp-primary{width:100%;justify-content:center;min-height:52px',
];
foreach ($required as $token) {
    if (strpos($css, $token) === false) {
        fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:$token\n");
        exit(2);
    }
}
$promoTokens = [
    '.sp-offer-price--promo',
    '.sp-offer-discount-badge',
    'background:var(--at-accent)',
    '.sp-offer-price-values del',
    '.sp-offer-price-note',
];
foreach ($promoTokens as $token) {
    if (strpos($promoCss, $token) === false) {
        fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:promo:$token\n");
        exit(3);
    }
}
$editorialTokens = [
    '.sp-breadcrumbs .sp-wrap{display:flex;align-items:center;gap:6px;overflow:hidden;white-space:nowrap}',
    '.sp-breadcrumbs [aria-current="page"]{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
    '.sp-related-card:before{display:none!important}',
];
foreach ($editorialTokens as $token) {
    if (strpos($editorialCss, $token) === false) {
        fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:editorial:$token\n");
        exit(4);
    }
}
if (strpos($shell, "string \$actionHref='',string \$actionLabel='',string \$modifier=''") === false || strpos($shell, "sp-hero--'.preg_replace") === false || strpos($shell, 'sp-hero-actions') === false || strpos($shell, "sp_inline_css('seo-price-promo-v1.css')") === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:hero_or_promo_css\n");
    exit(5);
}
if (strpos($offerSnapshot, 'function v2_seo_enrich_offer_prices') === false || strpos($offerSnapshot, 'function v2_seo_offer_price_markup') === false || strpos($offerSnapshot, "'showPromoDrop'") === false || strpos($offerSnapshot, 'sp-offer-discount-badge') === false || strpos($offerSnapshot, '<del title=') === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:price_promo_markup\n");
    exit(6);
}
foreach (['country' => $country, 'resort' => $resort, 'seasonal' => $seasonal] as $name => $source) {
    if (strpos($source, 'v2_seo_offer_price_markup($offer)') === false) {
        fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:{$name}_price_promo\n");
        exit(7);
    }
}
if (strpos($resort, "'Подобрать тур в ' . \$resortName") === false || strpos($resort, "v2_seo_search_handoff_url('/poisk-turov/', \$page['search_state'])") === false || strpos($resort, 'sp-offer-meta') === false || strpos($resort, 'sp-offer-price') === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:resort_offer_hierarchy\n");
    exit(8);
}
if (strpos($hotel, "'Найти туры в этот отель'") === false || strpos($hotel, "'hotel-tour'") === false || strpos($hotel, "v2_seo_search_handoff_url('/poisk-turov/', \$page['search_state'])") === false || strpos($hotel, 'sp-offer-meta') === false || strpos($hotel, 'sp-offer-price') === false || strpos($hotel, 'sp-offer-item sp-offer-item--hotel') === false || strpos($hotel, "echo '<h3>'.sp_e(\$hotelName).'</h3>'") !== false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:hotel_offer_focus\n");
    exit(9);
}
echo "SEO_REFERENCE_VISUAL_OK desktop=3 tablet=2 mobile=1 leadCard=1 balancedTail=1 heroCta=1 breadcrumbClamp=1 offerHierarchy=1 hotelOfferFocus=1 densityPass=1 lightRelatedNav=1 flatRelatedAccent=1 hotelCommercialHero=1 pricePromo=1\n";
