<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-production-identity-collector-v1.php';
function fail_identity_gate(string $code): never { fwrite(STDERR,"SEO_PRODUCTION_IDENTITY_CORE_GATE_FAIL:$code\n"); exit(1); }
$expected=v2_seo_production_identity_expected_rows();
$hotels=array_values(array_filter($expected,static fn(array $row):bool=>($row['type']??'')==='hotel_tours'));
if(count($hotels)!==1)fail_identity_gate('hotel_scope');
$hotel=$hotels[0];
if(($hotel['robots_prefix']??'')!=='noindex,follow')fail_identity_gate('hotel_noindex');
if(($hotel['sitemap_member']??true)!==false)fail_identity_gate('hotel_sitemap');
foreach($expected as $row){
    if(($row['type']??'')==='hotel_tours')continue;
    if(($row['sitemap_member']??false)!==true)fail_identity_gate('launched_scope_sitemap');
}
echo "SEO_PRODUCTION_IDENTITY_CORE_GATE_OK expected=".count($expected)." hotelTours=review_noindex_out_of_sitemap\n";
