<?php
require_once __DIR__ . '/../v2/seo-ds2-reference-pages-v1.php';

function ds2_reference_quality_fail(string $message): void
{
    fwrite(STDERR, "SEO_DS2_REFERENCE_QUALITY_FAIL:$message\n");
    exit(1);
}

$pages = v2_seo_ds2_reference_pages();
$contract = v2_seo_ds2_reference_quality_contract();
$viewports = v2_seo_ds2_reference_viewports();

if (($contract['contract_version'] ?? '') !== 'seo2-ds2-reference-quality-v1') ds2_reference_quality_fail('version');
if ($viewports !== [375, 430, 768, 1024, 1440]) ds2_reference_quality_fail('viewports');
if (($contract['responsive_acceptance']['mobile'] ?? null) !== [375, 430]) ds2_reference_quality_fail('mobile_breakpoints');
if (($contract['responsive_acceptance']['tablet'] ?? null) !== [768]) ds2_reference_quality_fail('tablet_breakpoints');
if (($contract['responsive_acceptance']['desktop'] ?? null) !== [1024, 1440]) ds2_reference_quality_fail('desktop_breakpoints');
if (($contract['responsive_acceptance']['horizontal_overflow_allowed'] ?? true) !== false) ds2_reference_quality_fail('overflow_boundary');
if (($contract['responsive_acceptance']['primary_cta_min_height_px'] ?? 0) < 48) ds2_reference_quality_fail('cta_hitarea');

$dimensions = $contract['blocking_dimensions'] ?? [];
foreach (['identity_integrity','editorial_depth','search_handoff_integrity','responsive_layout','commercial_hierarchy','internal_navigation','fresh_offer_evidence','publication_boundary'] as $dimension) {
    if (!in_array($dimension, $dimensions, true)) ds2_reference_quality_fail('dimension_' . $dimension);
}

$destination = $contract['destination'] ?? [];
if (($destination['required_type'] ?? '') !== ($pages['destination']['type'] ?? '')) ds2_reference_quality_fail('destination_type');
if (($destination['required_renderer'] ?? '') !== ($pages['destination']['renderer'] ?? '')) ds2_reference_quality_fail('destination_renderer');
if (($destination['requires_search_handoff'] ?? false) !== true || ($destination['requires_editorial_sections'] ?? false) !== true || ($destination['requires_fresh_offer_evidence_for_offer_claims'] ?? false) !== true) ds2_reference_quality_fail('destination_quality');

$hotel = $contract['hotel_tours'] ?? [];
if (($hotel['required_type'] ?? '') !== ($pages['hotel_tours']['type'] ?? '')) ds2_reference_quality_fail('hotel_type');
if (($hotel['required_renderer'] ?? '') !== ($pages['hotel_tours']['renderer'] ?? '')) ds2_reference_quality_fail('hotel_renderer');
if (($hotel['required_status'] ?? '') !== 'review') ds2_reference_quality_fail('hotel_review_state');
if (($hotel['requires_verified_country_hotel_identity'] ?? false) !== true) ds2_reference_quality_fail('hotel_identity');
foreach (['publication_candidate_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $boundary) {
    if (($hotel[$boundary] ?? true) !== false) ds2_reference_quality_fail('hotel_boundary_' . $boundary);
}
if (($hotel['separate_user_launch_approval_required'] ?? false) !== true) ds2_reference_quality_fail('hotel_approval_boundary');
if (($pages['hotel_tours']['publication_state'] ?? '') !== 'review_noindex_requires_launch_approval') ds2_reference_quality_fail('hotel_reference_state');

echo "SEO_DS2_REFERENCE_QUALITY_OK viewports=5 boundaries=5 dimensions=8\n";
