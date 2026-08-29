<?php

declare(strict_types=1);

use AnyTour\Platform\Seo\SeoEligibilityPolicy;

require_once dirname(__DIR__) . '/seo/SeoEligibilityPolicy.php';

$pageTypes = require dirname(__DIR__) . '/seo/page-types.php';
$policy = new SeoEligibilityPolicy($pageTypes);

$ready = $policy->evaluate([
    'page_type' => 'country',
    'roles' => ['country'],
    'blocks' => ['hero', 'live_tours', 'popular_resorts'],
    'quality_score' => 90,
    'inventory_available' => true,
    'canonical_unique' => true,
    'http_ok' => true,
    'schema_ok' => true,
    'breadcrumbs_ok' => true,
    'internal_links_ok' => true,
]);

if ($ready['eligible'] !== true) {
    throw new RuntimeException('A fully ready country page must be eligible.');
}

$thin = $policy->evaluate([
    'page_type' => 'country',
    'roles' => ['country'],
    'blocks' => ['hero'],
    'quality_score' => 95,
    'inventory_available' => true,
    'canonical_unique' => true,
    'http_ok' => true,
    'schema_ok' => true,
    'breadcrumbs_ok' => true,
    'internal_links_ok' => true,
]);

if ($thin['eligible'] !== false || $thin['checks']['blocks'] !== 'fail') {
    throw new RuntimeException('Thin country page must remain non-indexable.');
}

fwrite(STDOUT, "SEO_POLICY_OK\n");
