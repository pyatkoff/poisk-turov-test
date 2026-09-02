<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-ds2-reference-acceptance-v1.php';

$d=v2_seo_ds2_reference_acceptance_dossier();
function fail_ds2(string $x): never { fwrite(STDERR,"SEO_DS2_REFERENCE_ACCEPTANCE_FAIL:$x\n"); exit(1); }
if(($d['state']??'')!=='review_only_ds2_reference_acceptance') fail_ds2('state');
if(($d['design_system']??'')!=='AnyTour Design System 2.0') fail_ds2('design_system');
if(($d['viewport_matrix']['mobile']??[])!==[375,430]) fail_ds2('mobile');
if(($d['viewport_matrix']['desktop']??[])!==[1024,1440]) fail_ds2('desktop');
if(($d['reference_pages']['destination']['path']??'')!=='/country/turkey/kemer/') fail_ds2('destination_reference');
if(($d['reference_pages']['hotel_tours']['path']??'')!=='/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/') fail_ds2('hotel_reference');
foreach(['identity_integrity','responsive_layout','fresh_offer_evidence','publication_boundary'] as $dimension){
    if(!in_array($dimension,$d['blocking_dimensions']??[],true)) fail_ds2('dimension_'.$dimension);
}
foreach(['search_contract_mutation_allowed','tourvisor_contract_mutation_allowed','publication_allowed','hotel_tours_publication_candidate_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','hotel_tours_canonical_launch_allowed','hotel_tours_route_launch_allowed'] as $key){
    if(($d[$key]??true)!==false) fail_ds2('boundary_'.$key);
}
if(($d['separate_user_hotel_indexation_approval_required']??false)!==true) fail_ds2('approval_boundary');
if(!in_array('search_handoff_uses_existing_poisk_turov_contract',$d['acceptance_checks']['shared']??[],true)) fail_ds2('search_handoff');
if(!in_array('review_noindex_boundary_remains_intact',$d['acceptance_checks']['hotel_tours']??[],true)) fail_ds2('hotel_noindex');
echo "SEO_DS2_REFERENCE_ACCEPTANCE_OK mobile=375,430 desktop=1024,1440 hotel_tours=noindex_review_only\n";
