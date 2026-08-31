<?php
/** Ordered active V2 browser assets. Keep order identical to the historical index.php includes. */
function v2_bundle_manifest(): array
{
    return [
        'css' => [
            'design-system-v1.css','app.css','enhancements.css','room-details.css','selected-tour-ux.css','design-v1.css','hotel-details-design.css','tour-design-v1.css','search-states-design.css','anytour-brand.css','results-experience-v1.css','sales-leader-ui-v1.css','conversion-confidence-v1.css','checkout-experience-v1.css','site-header-v2.css','header-current-site.css','primary-meal-ux-v1.css','mobile-results-filters-v1.css','search-progress-ux-v1.css','search-dirty-ux-v1.css','mobile-search-summary-v1.css','search-filters-ux-v1.css','hotel-autocomplete-v1.css','product-shell-v1.css','search-shell-grid-v1.css','br3-control-consistency-v1.css','site-footer-v1.css','results-layout-guard-v1.css','current-price-calendar-v1.css','search-header-layout-guard-v1.css','search-footer-rhythm-v1.css','search-header-shared-shell-v1.css','ds2-search-intro-v1.css','ds2-search-tablet-filters-v1.css','ds2-selected-tour-convergence-v1.css',
        ],
        'js' => [
            'header-current-site.js','runtime-retry-policy.js','runtime-v3.js','analytics-v4.js','results-renderer-v5.js','sales-leader-ui-v1.js','conversion-confidence-v1.js','compare-refresh-guard-v1.js','search-continue-v6.js','hotel-actions-v3.js','room-details-v3.js','selected-tour-return-v1.js','tour-controller-v4.js','flight-empty-recovery-v1.js','selected-tour-description-v1.js','checkout-experience-v1.js','lead-search-context.js','lead-ui-race-guard-v1.js','lead-form-guard-v1.js','flight-price-sync-v1.js','unpriced-flight-price-reset-v1.js','price-confidence-v1.js','catalog-local-routing-v1.js','country-matrix-routing-v1.js','catalogs-v2.js','hotel-autocomplete-v1.js','url-primary-catalog-sync-v1.js','search-filters-ux-v1.js','search-lifecycle-v6.js','results-filter-autorefresh-v1.js','passive-price-observer-v1.js','current-price-calendar-v1.js','mobile-results-filters-v1.js','primary-meal-ux-v1.js','search-progress-ux-v1.js','search-complete-recovery-v1.js','search-dirty-ux-v1.js','mobile-search-summary-v1.js','accessibility.js',
        ],
    ];
}
