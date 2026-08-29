<?php

declare(strict_types=1);

use AnyTour\Platform\ContentRepository;
use AnyTour\Platform\CountryHtmlRenderer;
use AnyTour\Platform\Database;
use AnyTour\Platform\EntityRepository;
use AnyTour\Platform\IntegratedCountryPage;
use AnyTour\Platform\PageAssembler;
use AnyTour\Platform\RouteResolver;
use AnyTour\Platform\SeoPageRepository;
use AnyTour\Platform\Seo\MetadataResolver;
use AnyTour\Platform\Seo\SeoEligibilityPolicy;

$root = dirname(__DIR__);
require_once $root . '/src/Database.php';
require_once $root . '/src/EntityRepository.php';
require_once $root . '/src/ContentRepository.php';
require_once $root . '/src/PageAssembler.php';
require_once $root . '/src/SeoPageRepository.php';
require_once $root . '/src/IntegratedCountryPage.php';
require_once $root . '/src/RouteResolver.php';
require_once $root . '/src/CountryHtmlRenderer.php';
require_once $root . '/seo/MetadataResolver.php';
require_once $root . '/seo/SeoEligibilityPolicy.php';

$path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$route = (new RouteResolver())->resolve($path);
if ($route === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    return;
}

try {
    $pdo = Database::connectFromEnvironment();
    $pageTypes = require $root . '/seo/page-types.php';
    $metadataTemplates = require $root . '/seo/metadata-templates.php';
    $page = (new IntegratedCountryPage(
        new PageAssembler(new EntityRepository($pdo), new ContentRepository($pdo)),
        new SeoPageRepository($pdo),
        new MetadataResolver(),
        new SeoEligibilityPolicy($pageTypes),
        $pageTypes,
        $metadataTemplates,
    ))->build($route['route_key'], true);

    if (($page['seo']['registry']['index_status'] ?? '') === 'redirect') {
        http_response_code(500);
        echo 'Redirect registry handling is not enabled in preview.';
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: ' . (($page['seo']['robots'] ?? '') === 'index,follow' ? 'index, follow' : 'noindex, follow'));
    echo (new CountryHtmlRenderer())->render($page);
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Page unavailable\n";
}
