<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-ds2-reference-acceptance-v1.php';
function ds2_live_source_fail(string $x): never { fwrite(STDERR,"SEO_DS2_LIVE_SOURCE_FAIL:$x\n"); exit(1); }
$snapshot=(string)file_get_contents(__DIR__.'/../v2/seo-offer-snapshot-v1.php');
$resort=(string)file_get_contents(__DIR__.'/../v2/seo-resort-page-v1.php');
$hotel=(string)file_get_contents(__DIR__.'/../v2/seo-hotel-tour-page-v1.php');
if(substr_count($snapshot,'s.expires_at>=NOW()')<2)ds2_live_source_fail('snapshot_expiry_gate');
if(!str_contains($resort,'v2_seo_resort_snapshot_offers('))ds2_live_source_fail('resort_snapshot_source');
if(!str_contains($hotel,'v2_seo_hotel_snapshot_offers('))ds2_live_source_fail('hotel_snapshot_source');
if(!str_contains($hotel,"\$context['robots'] = v2_seo_robots_content(false);"))ds2_live_source_fail('hotel_noindex_runtime');
$d=v2_seo_ds2_reference_acceptance_dossier();
if(($d['reference_pages']['hotel_tours']['publication_state']??'')!=='review_noindex_requires_launch_approval')ds2_live_source_fail('hotel_review_state');
foreach(['hotel_tours_publication_candidate_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','hotel_tours_canonical_launch_allowed','hotel_tours_route_launch_allowed'] as $flag)if(($d[$flag]??true)!==false)ds2_live_source_fail($flag);
if(($d['separate_user_hotel_indexation_approval_required']??false)!==true)ds2_live_source_fail('approval');
echo "SEO_DS2_LIVE_SOURCE_OK snapshots=expires_at_now hotel_tours=review_noindex\n";
