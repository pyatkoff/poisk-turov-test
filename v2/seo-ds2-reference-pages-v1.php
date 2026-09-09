<?php

/**
 * Stable reference pair for SEO2 Design System 2.0 convergence.
 *
 * This contract only selects representative pages for visual/content QA. It does
 * not alter routing, robots, canonical, sitemap or publication state.
 */
function v2_seo_ds2_reference_pages(): array
{
    return [
        'destination' => [
            'path' => '/country/turkey/kemer/',
            'type' => 'resort',
            'renderer' => 'v2_seo_render_resort',
            'purpose' => 'reference_destination_editorial_and_offer_layout',
            'publication_state' => 'existing_destination_reference_only',
        ],
        'hotel_tours' => [
            'path' => '/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/',
            'type' => 'hotel_tours',
            'renderer' => 'v2_seo_render_hotel_tour_review',
            'country_id' => 8,
            'hotel_id' => 65108,
            'purpose' => 'reference_hotel_tour_editorial_and_offer_layout',
            'publication_state' => 'review_noindex_requires_launch_approval',
        ],
    ];
}

function v2_seo_ds2_reference_viewports(): array
{
    return [375, 430, 768, 1024, 1440];
}

/**
 * Explicit content/layout anatomy for the two reference pages.
 * These are review expectations only and do not add route or publication behavior.
 */
function v2_seo_ds2_reference_anatomy(): array
{
    return [
        'shared' => [
            'zones' => ['site_header', 'breadcrumbs', 'hero', 'primary_search_handoff', 'editorial_body', 'related_navigation', 'site_footer'],
            'mobile_order' => ['site_header', 'breadcrumbs', 'hero', 'primary_search_handoff', 'editorial_body', 'related_navigation', 'site_footer'],
            'desktop_primary_column_max_px' => 920,
            'minimum_editorial_sections' => 2,
            'primary_action_min_height_px' => 48,
        ],
        'destination' => [
            'zones' => ['destination_identity', 'search_handoff', 'editorial_sections', 'fresh_offer_surface', 'related_destinations'],
            'hero_priority' => ['h1', 'orientation_copy', 'search_handoff'],
            'offer_claim_policy' => 'fresh_evidence_only',
        ],
        'hotel_tours' => [
            'zones' => ['verified_hotel_identity', 'search_handoff', 'fresh_offer_surface', 'editorial_sections', 'related_navigation'],
            'hero_priority' => ['verified_hotel_identity', 'search_handoff'],
            'offer_claim_policy' => 'fresh_evidence_only',
            'publication_boundary_visible_in_review_contract' => true,
        ],
    ];
}

/**
 * Factual-content boundary for DS2 SEO references.
 * Volatile facts must come from current evidence; the contract never invents them.
 */
function v2_seo_ds2_reference_fact_policy(): array
{
    return [
        'volatile_claims_require_fresh_evidence' => true,
        'fail_closed_when_evidence_missing_or_stale' => true,
        'prohibited_without_evidence' => [
            'price',
            'availability',
            'discount',
            'rating',
            'hotel_attribute',
            'region_mapping',
            'atoll_mapping',
        ],
        'search_contract_mutation_allowed' => false,
        'tourvisor_contract_mutation_allowed' => false,
        'pricing_logic_mutation_allowed' => false,
        'lead_flow_mutation_allowed' => false,
        'analytics_contract_mutation_allowed' => false,
    ];
}

/**
 * Review-only quality contract for the DS2 reference pair.
 *
 * These are acceptance dimensions, not publication switches. In particular the
 * hotel-tour reference remains noindex/out-of-sitemap and cannot become a
 * publication candidate through this contract.
 */
function v2_seo_ds2_reference_quality_contract(): array
{
    return [
        'contract_version' => 'seo2-ds2-reference-quality-v2',
        'blocking_dimensions' => [
            'identity_integrity',
            'editorial_depth',
            'search_handoff_integrity',
            'responsive_layout',
            'commercial_hierarchy',
            'internal_navigation',
            'fresh_offer_evidence',
            'factual_content_boundary',
            'publication_boundary',
        ],
        'responsive_acceptance' => [
            'mobile' => [375, 430],
            'tablet' => [768],
            'desktop' => [1024, 1440],
            'horizontal_overflow_allowed' => false,
            'primary_cta_min_height_px' => 48,
        ],
        'destination' => [
            'required_type' => 'resort',
            'required_renderer' => 'v2_seo_render_resort',
            'requires_search_handoff' => true,
            'requires_editorial_sections' => true,
            'requires_related_navigation' => true,
            'requires_fresh_offer_evidence_for_offer_claims' => true,
            'required_anatomy_zones' => v2_seo_ds2_reference_anatomy()['destination']['zones'],
        ],
        'hotel_tours' => [
            'required_type' => 'hotel_tours',
            'required_status' => 'review',
            'required_renderer' => 'v2_seo_render_hotel_tour_review',
            'requires_verified_country_hotel_identity' => true,
            'requires_search_handoff' => true,
            'requires_editorial_sections' => true,
            'requires_fresh_offer_evidence_for_offer_claims' => true,
            'required_anatomy_zones' => v2_seo_ds2_reference_anatomy()['hotel_tours']['zones'],
            'publication_candidate_allowed' => false,
            'indexation_allowed' => false,
            'sitemap_allowed' => false,
            'canonical_launch_allowed' => false,
            'route_launch_allowed' => false,
            'separate_user_launch_approval_required' => true,
        ],
        'fact_policy' => v2_seo_ds2_reference_fact_policy(),
    ];
}
