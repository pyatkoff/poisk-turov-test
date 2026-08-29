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

$pdo = Database::connectFromEnvironment();
$pageTypes = require $root . '/seo/page-types.php';
$metadataTemplates = require $root . '/seo/metadata-templates.php';

$integrated = new IntegratedCountryPage(
    new PageAssembler(new EntityRepository($pdo), new ContentRepository($pdo)),
    new SeoPageRepository($pdo),
    new MetadataResolver(),
    new SeoEligibilityPolicy($pageTypes),
    $pageTypes,
    $metadataTemplates,
);

$route = (new RouteResolver())->resolve('/country/turkey/?utm_source=ci');
if ($route !== ['page_type' => 'country', 'route_key' => 'country:turkey']) {
    throw new RuntimeException('Country route was not resolved.');
}
if ((new RouteResolver())->resolve('/country/turkey/hotel/') !== null) {
    throw new RuntimeException('Country resolver must not swallow deeper routes.');
}

$page = $integrated->build($route['route_key'], true);
if (($page['entity']['slug'] ?? null) !== 'turkey') {
    throw new RuntimeException('Country entity was not assembled.');
}
if (($page['seo']['metadata']['canonical'] ?? null) !== '/country/turkey/') {
    throw new RuntimeException('Canonical was not generated from SEO contract.');
}
if (($page['seo']['metadata']['h1'] ?? null) !== 'Туры в Турцию') {
    throw new RuntimeException('Country grammatical form was not applied to H1.');
}
if (($page['seo']['eligible'] ?? false) !== true) {
    throw new RuntimeException('Reference Turkey page must pass SEO eligibility.');
}
if (($page['seo']['index_status'] ?? null) !== 'noindex' || ($page['seo']['robots'] ?? null) !== 'noindex,follow') {
    throw new RuntimeException('Registry noindex must control runtime robots.');
}
if (($page['search_url'] ?? null) !== '/poisk-turov/?country=4') {
    throw new RuntimeException('Search handoff must use the confirmed Tourvisor country id.');
}

$html = (new CountryHtmlRenderer())->render($page);
foreach (['<h1>Туры в Турцию</h1>', 'name="robots" content="noindex,follow"', 'href="https://anytoour.ru/country/turkey/"', 'href="/poisk-turov/?country=4"', 'Найти туры в Турцию', 'Популярные курорты Турции', 'Анталья', 'Белек', 'Кемер', 'Сиде'] as $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException('Rendered Turkey page is missing: ' . $needle);
    }
}

fwrite(STDOUT, "SITE_SEO_RENDER_RUNTIME_OK\n");
