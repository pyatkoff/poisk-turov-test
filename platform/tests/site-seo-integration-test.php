<?php

declare(strict_types=1);

use AnyTour\Platform\ContentRepository;
use AnyTour\Platform\CountryHtmlRenderer;
use AnyTour\Platform\Database;
use AnyTour\Platform\EntityRepository;
use AnyTour\Platform\IntegratedCountryPage;
use AnyTour\Platform\IntegratedResortPage;
use AnyTour\Platform\PageAssembler;
use AnyTour\Platform\ResortHtmlRenderer;
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
require_once $root . '/src/IntegratedResortPage.php';
require_once $root . '/src/RouteResolver.php';
require_once $root . '/src/CountryHtmlRenderer.php';
require_once $root . '/src/ResortHtmlRenderer.php';
require_once $root . '/seo/MetadataResolver.php';
require_once $root . '/seo/SeoEligibilityPolicy.php';

$pdo = Database::connectFromEnvironment();
$pageTypes = require $root . '/seo/page-types.php';
$metadataTemplates = require $root . '/seo/metadata-templates.php';
$assembler = new PageAssembler(new EntityRepository($pdo), new ContentRepository($pdo));
$seoPages = new SeoPageRepository($pdo);
$metadata = new MetadataResolver();
$eligibility = new SeoEligibilityPolicy($pageTypes);
$resolver = new RouteResolver();

$countryRoute = $resolver->resolve('/country/turkey/?utm_source=ci');
if ($countryRoute !== ['page_type' => 'country', 'route_key' => 'country:turkey']) {
    throw new RuntimeException('Country route was not resolved.');
}
$resortRoute = $resolver->resolve('/country/turkey/antalya/?utm_source=ci');
if ($resortRoute !== ['page_type' => 'resort', 'route_key' => 'resort:turkey:antalya']) {
    throw new RuntimeException('Resort route was not resolved.');
}
if ($resolver->resolve('/country/turkey/antalya/hotel/') !== null) {
    throw new RuntimeException('Resort resolver must not swallow hotel routes.');
}

$countryPage = (new IntegratedCountryPage($assembler, $seoPages, $metadata, $eligibility, $pageTypes, $metadataTemplates))
    ->build($countryRoute['route_key'], true);
if (($countryPage['seo']['metadata']['h1'] ?? null) !== 'Туры в Турцию') {
    throw new RuntimeException('Country grammatical form was not applied to H1.');
}
if (($countryPage['seo']['eligible'] ?? false) !== true || ($countryPage['seo']['robots'] ?? null) !== 'noindex,follow') {
    throw new RuntimeException('Reference Turkey page SEO state is invalid.');
}
if (($countryPage['search_url'] ?? null) !== '/poisk-turov/?country=4') {
    throw new RuntimeException('Country search handoff must use confirmed Tourvisor country id.');
}
$countryHtml = (new CountryHtmlRenderer())->render($countryPage);
foreach (['<h1>Туры в Турцию</h1>', 'href="https://anytoour.ru/country/turkey/"', 'Найти туры в Турцию', 'Анталья', 'Белек', 'Кемер', 'Сиде'] as $needle) {
    if (!str_contains($countryHtml, $needle)) {
        throw new RuntimeException('Rendered Turkey page is missing: ' . $needle);
    }
}

$resortPage = (new IntegratedResortPage($assembler, $seoPages, $metadata, $eligibility, $pageTypes, $metadataTemplates))
    ->build($resortRoute['route_key'], true);
if (($resortPage['entity']['slug'] ?? null) !== 'antalya' || ($resortPage['country']['slug'] ?? null) !== 'turkey') {
    throw new RuntimeException('Antalya resort hierarchy was not assembled.');
}
if (($resortPage['seo']['metadata']['canonical'] ?? null) !== '/country/turkey/antalya/') {
    throw new RuntimeException('Antalya canonical is invalid.');
}
if (($resortPage['seo']['metadata']['h1'] ?? null) !== 'Туры в Анталью') {
    throw new RuntimeException('Resort grammatical form was not applied to H1.');
}
if (($resortPage['seo']['eligible'] ?? false) !== true || ($resortPage['seo']['robots'] ?? null) !== 'noindex,follow') {
    throw new RuntimeException('Reference Antalya page SEO state is invalid.');
}
if (($resortPage['search_intent']['tourvisor_resort_id'] ?? null) !== null) {
    throw new RuntimeException('Tourvisor resort id must not be guessed in the reference slice.');
}
if (($resortPage['search_url'] ?? null) !== '/poisk-turov/?country=4') {
    throw new RuntimeException('Resort search handoff must only use verified country hydration for now.');
}

$resortHtml = (new ResortHtmlRenderer())->render($resortPage);
foreach (['<h1>Туры в Анталью</h1>', 'name="robots" content="noindex,follow"', 'href="https://anytoour.ru/country/turkey/antalya/"', 'href="/country/turkey/"', 'href="/poisk-turov/?country=4"', 'Смотреть туры в Турцию', 'Все курорты Турции', 'Что важно знать об Анталье', 'Туры в Анталью'] as $needle) {
    if (!str_contains($resortHtml, $needle)) {
        throw new RuntimeException('Rendered Antalya page is missing: ' . $needle);
    }
}

fwrite(STDOUT, "SITE_SEO_COUNTRY_RESORT_RUNTIME_OK\n");
