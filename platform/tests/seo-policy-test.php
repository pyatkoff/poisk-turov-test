<?php

declare(strict_types=1);

use AnyTour\Platform\Seo\MetadataResolver;
use AnyTour\Platform\Seo\SeoEligibilityPolicy;

require_once dirname(__DIR__) . '/seo/SeoEligibilityPolicy.php';
require_once dirname(__DIR__) . '/seo/MetadataResolver.php';

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

$metadataTemplates = require dirname(__DIR__) . '/seo/metadata-templates.php';
$resolver = new MetadataResolver();
$metadata = $resolver->resolve($metadataTemplates['country'], [
    'country_name' => 'Турцию',
    'country_name_prepositional' => 'Турции',
    'country_slug' => 'turkey',
    'year' => 2026,
], [
    'h1' => 'Туры в Турцию',
]);

if ($metadata['canonical'] !== '/country/turkey/') {
    throw new RuntimeException('Country canonical generation failed.');
}
if ($metadata['h1'] !== 'Туры в Турцию') {
    throw new RuntimeException('Manual metadata override must win over generated value.');
}
if (!str_contains($metadata['title'], '2026')) {
    throw new RuntimeException('Metadata variables were not interpolated.');
}

fwrite(STDOUT, "SEO_POLICY_OK\n");
