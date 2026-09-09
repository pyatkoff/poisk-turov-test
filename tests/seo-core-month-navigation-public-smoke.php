<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/site-page-shell-v1.php';
require_once __DIR__.'/../v2/seo-core-month-navigation-v1.php';

foreach(['/country/turkey/','/country/egypt/','/country/maldives/','/country/turkey/kemer/'] as $parent){
    $links=v2_seo_core_month_links_for_parent($parent);
    if(count($links)!==12)exit(1);
    if(($links[0]['label']??'')!=='Январь'||($links[11]['label']??'')!=='Декабрь')exit(2);
    $html=v2_seo_render_core_month_navigation($parent);
    if(substr_count($html,'class="sp-secondary"')!==12)exit(3);
}
if(v2_seo_core_month_links_for_parent('/country/oae/')!==[])exit(4);
$countrySource=(string)file_get_contents(__DIR__.'/../v2/country-page-v1.php');
$resortSource=(string)file_get_contents(__DIR__.'/../v2/seo-resort-page-v1.php');
if(!str_contains($countrySource,'v2_seo_render_core_month_navigation($countryPath'))exit(5);
if(!str_contains($resortSource,'v2_seo_render_core_month_navigation($path'))exit(6);
echo "SEO_CORE_MONTH_NAV_PUBLIC_OK parents=8 links_per_parent=12\n";
