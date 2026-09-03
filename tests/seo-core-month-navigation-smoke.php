<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/site-page-shell-v1.php';
require_once __DIR__.'/../v2/seo-core-month-navigation-v1.php';
$links=v2_seo_core_month_links_for_parent('/country/turkey/kemer/');
if(count($links)!==12)exit(1);
if(($links[0]['href']??'')!=='/country/turkey/kemer/january/')exit(2);
if(($links[11]['href']??'')!=='/country/turkey/kemer/december/')exit(3);
$html=v2_seo_render_core_month_navigation('/country/turkey/kemer/');
if(substr_count($html,'class="sp-secondary"')!==12)exit(4);
if(v2_seo_core_month_links_for_parent('/country/unknown/')!==[])exit(5);
echo "SEO_CORE_MONTH_NAVIGATION_OK links=12\n";
