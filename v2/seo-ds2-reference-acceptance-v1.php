<?php
declare(strict_types=1);
require_once __DIR__.'/seo-ds2-reference-pages-v1.php';

/**
 * Machine-readable review dossier for the two canonical DS2 SEO references.
 * It describes what must be visually/content-reviewed on desktop/mobile and
 * keeps hotel_tours permanently outside publication/indexation in this phase.
 * It does not render or mount routes and does not alter Search/Tourvisor.
 */
function v2_seo_ds2_reference_acceptance_dossier(): array
{
    $pages=v2_seo_ds2_reference_pages();
    $quality=v2_seo_ds2_reference_quality_contract();
    $viewports=v2_seo_ds2_reference_viewports();
    $mobile=array_values(array_filter($viewports,static fn(int $w):bool=>$w<=430));
    $desktop=array_values(array_filter($viewports,static fn(int $w):bool=>$w>=1024));

    return [
        'state'=>'review_only_ds2_reference_acceptance',
        'design_system'=>'AnyTour Design System 2.0',
        'reference_pages'=>$pages,
        'viewport_matrix'=>[
            'mobile'=>$mobile,
            'tablet'=>[768],
            'desktop'=>$desktop,
        ],
        'blocking_dimensions'=>$quality['blocking_dimensions'],
        'acceptance_checks'=>[
            'shared'=>[
                'single_site_header_footer_shell',
                'breadcrumb_clamp_without_horizontal_overflow',
                'primary_action_height_at_least_48px',
                'search_handoff_uses_existing_poisk_turov_contract',
                'editorial_sections_have_clear_hierarchy',
                'related_navigation_is_present_and_non_competing',
            ],
            'destination'=>[
                'identity_and_orientation_copy_above_editorial_body',
                'commercial_search_handoff_is_prominent',
                'offer_claims_render_only_from_fresh_evidence',
                'desktop_offer_grid_collapses_cleanly_on_mobile',
            ],
            'hotel_tours'=>[
                'verified_hotel_identity_is_primary',
                'single_hotel_search_action_is_primary_commercial_cta',
                'offer_claims_render_only_from_fresh_evidence',
                'mobile_hotel_offer_cards_stack_without_overflow',
                'review_noindex_boundary_remains_intact',
            ],
        ],
        'fact_policy'=>$quality['fact_policy'],
        'search_contract_mutation_allowed'=>false,
        'tourvisor_contract_mutation_allowed'=>false,
        'publication_allowed'=>false,
        'hotel_tours_publication_candidate_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'hotel_tours_canonical_launch_allowed'=>false,
        'hotel_tours_route_launch_allowed'=>false,
        'separate_user_hotel_indexation_approval_required'=>true,
    ];
}
