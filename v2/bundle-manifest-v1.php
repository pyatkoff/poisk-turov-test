<?php
/**
 * V2 runtime bundle.
 * Visual CSS is intentionally not bundled: DS2 is the single canonical style layer.
 * Legacy JS that rebuilds or decorates the search/results UI is intentionally excluded;
 * DS2 owns presentation while the remaining modules keep runtime/data behavior only.
 */
function v2_bundle_manifest(): array
{
    return [
        'css' => [],
        'js' => [
            'runtime-retry-policy.js','runtime-v3.js','analytics-v4.js','direct-offer-retargeting-v1.js','results-renderer-v5.js','search-continue-v6.js','hotel-actions-v3.js','room-details-v3.js','tour-controller-v4.js','lead-search-context.js','lead-form-guard-v1.js','flight-price-sync-v1.js','unpriced-flight-price-reset-v1.js','price-confidence-v1.js','catalogs-v2.js','url-primary-catalog-sync-v1.js','search-lifecycle-v6.js','results-depth-v1.js','results-local-filters-v1.js','results-filter-autorefresh-v1.js','search-progress-ux-v1.js','search-dirty-ux-v1.js','accessibility.js','ds2-results-filters.js','search-redesign-v2.js',
        ],
    ];
}
