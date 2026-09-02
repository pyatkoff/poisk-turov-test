<?php
$css = file_get_contents(__DIR__ . '/../v2/seo-editorial-reference-v1.css');
if ($css === false || trim($css) === '') exit(1);
$required = [
    '.sp-editorial-section:first-child{grid-column:1/-1',
    '.sp-editorial-grid>.sp-editorial-section:last-child:nth-child(even){grid-column:1/-1',
    '.sp-offer-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))',
    '@media(max-width:1024px){.sp-offer-list{grid-template-columns:repeat(2,minmax(0,1fr))',
    '@media(max-width:560px)',
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
echo "SEO_REFERENCE_VISUAL_OK desktop=3 tablet=2 mobile=1 leadCard=1 balancedTail=1\n";
