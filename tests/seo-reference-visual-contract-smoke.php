<?php
$css = file_get_contents(__DIR__ . '/../v2/seo-editorial-reference-v1.css');
$shell = file_get_contents(__DIR__ . '/../v2/site-page-shell-v1.php');
$resort = file_get_contents(__DIR__ . '/../v2/seo-resort-page-v1.php');
$hotel = file_get_contents(__DIR__ . '/../v2/seo-hotel-tour-page-v1.php');
if ($css === false || trim($css) === '' || $shell === false || $resort === false || $hotel === false) exit(1);
$required = [
    '.sp-hero-actions{display:flex;align-items:center',
    '.sp-hero-actions .sp-primary{min-height:48px',
    '.sp-editorial-section:first-child{grid-column:1/-1',
    '.sp-editorial-grid>.sp-editorial-section:last-child:nth-child(even){grid-column:1/-1',
    '.sp-offer-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))',
    '@media(max-width:1024px){.sp-offer-list{grid-template-columns:repeat(2,minmax(0,1fr))',
    '@media(max-width:560px)',
    '.sp-hero-actions .sp-primary{width:100%',
    '.sp-offer-list{grid-template-columns:1fr',
    '.sp-related-card .sp-secondary',
    '.sp-seo-editorial-page .sp-search-callout',
];
foreach ($required as $token) {
    if (strpos($css, $token) === false) {
        fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:$token\n");
        exit(2);
    }
}
if (strpos($shell, "string \$actionHref='',string \$actionLabel=''") === false || strpos($shell, 'sp-hero-actions') === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:hero_optional_action\n");
    exit(3);
}
if (strpos($resort, "'Подобрать тур в ' . \$resortName") === false || strpos($resort, "v2_seo_search_handoff_url('/poisk-turov/', \$page['search_state'])") === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:resort_hero_handoff\n");
    exit(4);
}
if (strpos($hotel, "'Найти туры в этот отель'") === false || strpos($hotel, "v2_seo_search_handoff_url('/poisk-turov/', \$page['search_state'])") === false) {
    fwrite(STDERR, "SEO_REFERENCE_VISUAL_FAIL:hotel_hero_handoff\n");
    exit(5);
}
echo "SEO_REFERENCE_VISUAL_OK desktop=3 tablet=2 mobile=1 leadCard=1 balancedTail=1 heroCta=1\n";
