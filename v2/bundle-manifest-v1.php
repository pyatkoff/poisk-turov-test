<?php
/**
 * DS2 preview bundle.
 * Keep runtime/business JS intact, but isolate the redesign from legacy visual CSS.
 * Old skins were fighting the approved layout and are intentionally not loaded here.
 */
function v2_bundle_manifest(): array
{
    return [
        'css' => [
            /* Minimal base needed by existing controls/runtime. */
            'design-system-v2.css',

            /* Current approved DS2 screens only. */
            'results-experience-v1.css',
            'checkout-experience-v1.css',
            'site-footer-v1.css',
            'anytour-checkout-brand.css',
            'approved-anytour-redesign-v1.css',
            'ds2-first-screen-fix.css',
            'ds2-results-layout-fix.css',
        ],
        'js' => [
            'header-current-site.js','runtime-retry-policy.js','runtime-v3.js','analytics-v4.js','direct-offer-retargeting-v1.js','results-renderer-v5.js','conversion-confidence-v1.js','search-continue-v6.js','hotel-actions-v3.js','room-details-v3.js','tour-controller-v4.js','selected-tour-description-v1.js','checkout-experience-v1.js','lead-search-context.js','lead-form-guard-v1.js','flight-price-sync-v1.js','unpriced-flight-price-reset-v1.js','price-confidence-v1.js','catalogs-v2.js','url-primary-catalog-sync-v1.js','search-filters-ux-v1.js','search-lifecycle-v6.js','results-depth-v1.js','results-local-filters-v1.js','results-filter-autorefresh-v1.js','mobile-results-filters-v1.js','search-progress-ux-v1.js','search-dirty-ux-v1.js','mobile-search-summary-v1.js','accessibility.js','search-redesign-v2.js',
        ],
    ];
}
