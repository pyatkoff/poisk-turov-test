<?php
/**
 * V2 runtime bundle.
 * Search/results visuals are loaded separately from ds2-search.css so legacy checkout CSS cannot break or swallow the DS2 layer.
 */
function v2_bundle_manifest(): array
{
    return [
        'css' => [
            'design-system-v2.css',
            'checkout-experience-v1.css',
            'site-footer-v1.css',
            'anytour-checkout-brand.css',
        ],
        'js' => [
            'runtime-retry-policy.js','runtime-v3.js','analytics-v4.js','direct-offer-retargeting-v1.js','results-renderer-v5.js','search-continue-v6.js','hotel-actions-v3.js','room-details-v3.js','tour-controller-v4.js','selected-tour-description-v1.js','checkout-experience-v1.js','lead-search-context.js','lead-form-guard-v1.js','flight-price-sync-v1.js','unpriced-flight-price-reset-v1.js','price-confidence-v1.js','catalogs-v2.js','url-primary-catalog-sync-v1.js','search-filters-ux-v1.js','search-lifecycle-v6.js','results-depth-v1.js','results-local-filters-v1.js','results-filter-autorefresh-v1.js','mobile-results-filters-v1.js','search-progress-ux-v1.js','search-dirty-ux-v1.js','mobile-search-summary-v1.js','accessibility.js','ds2-results-filters.js','search-redesign-v2.js',
        ],
    ];
}
