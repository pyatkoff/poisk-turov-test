<?php
$css = file_get_contents(__DIR__ . '/../v2/seo-editorial-reference-v1.css');
$editorialCss = file_get_contents(__DIR__ . '/../v2/editorial-ds2-convergence-v1.css');
$shell = file_get_contents(__DIR__ . '/../v2/site-page-shell-v1.php');
$resort = file_get_contents(__DIR__ . '/../v2/seo-resort-page-v1.php');
$hotel = file_get_contents(__DIR__ . '/../v2/seo-hotel-tour-page-v1.php');
if ($css === false || trim($css) === '' || $editorialCss === false || $shell === false || $resort === false || $hotel === false) exit(1);
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
];
foreach ($required as $token) {
    if (strpos($css, $token) === false) {
        fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:$token\n");
        exit(2);
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
        exit(3);
    }
}
if (strpos($shell, "string \$actionHref='',string \$actionLabel=''") === false || strpos($shell, 'sp-hero-actions') === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:hero_optional_action\n");
    exit(4);
}
if (strpos($resort, "'Подобрать тур в ' . \$resortName") === false || strpos($resort, "v2_seo_search_handoff_url('/poisk-turov/', \$page['search_state'])") === false || strpos($resort, 'sp-offer-meta') === false || strpos($resort, 'sp-offer-price') === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:resort_offer_hierarchy\n");
    exit(5);
}
if (strpos($hotel, "'Найти туры в этот отель'") === false || strpos($hotel, "v2_seo_search_handoff_url('/poisk-turov/', \$page['search_state'])") === false || strpos($hotel, 'sp-offer-meta') === false || strpos($hotel, 'sp-offer-price') === false || strpos($hotel, 'sp-offer-item sp-offer-item--hotel') === false || strpos($hotel, "echo '<h3>'.sp_e(\$hotelName).'</h3>'") !== false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:hotel_offer_focus\n");
    exit(6);
}
echo "SEO_REFERENCE_VISUAL_OK desktop=3 tablet=2 mobile=1 leadCard=1 balancedTail=1 heroCta=1 breadcrumbClamp=1 offerHierarchy=1 hotelOfferFocus=1 densityPass=1 lightRelatedNav=1 flatRelatedAccent=1\n";
