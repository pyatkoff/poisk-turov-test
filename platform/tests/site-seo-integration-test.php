<?php

declare(strict_types=1);

use AnyTour\Platform\ContentRepository;
use AnyTour\Platform\Database;
use AnyTour\Platform\EntityRepository;
use AnyTour\Platform\IntegratedCountryPage;
use AnyTour\Platform\PageAssembler;
use AnyTour\Platform\Seo\MetadataResolver;
use AnyTour\Platform\Seo\SeoEligibilityPolicy;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/EntityRepository.php';
require_once dirname(__DIR__) . '/src/ContentRepository.php';
require_once dirname(__DIR__) . '/src/PageAssembler.php';
require_once dirname(__DIR__) . '/src/IntegratedCountryPage.php';
require_once dirname(__DIR__) . '/seo/MetadataResolver.php';
require_once dirname(__DIR__) . '/seo/SeoEligibilityPolicy.php';

$pdo = Database::connectFromEnvironment();
$pageTypes = require dirname(__DIR__) . '/seo/page-types.php';
$metadataTemplates = require dirname(__DIR__) . '/seo/metadata-templates.php';

$integrated = new IntegratedCountryPage(
    new PageAssembler(new EntityRepository($pdo), new ContentRepository($pdo)),
    new MetadataResolver(),
    new SeoEligibilityPolicy($pageTypes),
    $pageTypes,
    $metadataTemplates,
);

$page = $integrated->build('country:turkey', 90, true);

if (($page['entity']['slug'] ?? null) !== 'turkey') {
    throw new RuntimeException('Country entity was not assembled.');
}
if (($page['seo']['metadata']['canonical'] ?? null) !== '/country/turkey/') {
    throw new RuntimeException('Canonical was not generated from SEO contract.');
}
if (($page['seo']['eligible'] ?? false) !== true) {
    throw new RuntimeException('Reference Turkey page must pass SEO eligibility.');
}
if (($page['seo']['default_index_status'] ?? null) !== 'noindex') {
    throw new RuntimeException('Reference page must stay noindex until explicit publication.');
}
if (($page['search_url'] ?? null) !== '/poisk-turov/?country=4') {
    throw new RuntimeException('Search handoff must use the confirmed Tourvisor country id.');
}

$stmt = $pdo->prepare('SELECT index_status, canonical_path, quality_score FROM at_seo_pages WHERE route_key = :route_key LIMIT 1');
$stmt->execute(['route_key' => 'country:turkey']);
$registry = $stmt->fetch();
if (!$registry || $registry['index_status'] !== 'noindex' || $registry['canonical_path'] !== '/country/turkey/' || (int) $registry['quality_score'] !== 90) {
    throw new RuntimeException('Turkey SEO registry row is invalid.');
}

fwrite(STDOUT, "SITE_SEO_INTEGRATION_OK\n");
