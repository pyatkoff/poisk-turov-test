<?php
declare(strict_types=1);

function turkey_country_resort_links_fail(string $message): void
{
    fwrite(STDERR, "SEO_TURKEY_COUNTRY_RESORT_LINKS_FAIL:$message\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir() . '/seo-turkey-country-links-empty-root';
$_SERVER['HTTP_HOST'] = 'anytoour.ru';
$_SERVER['REQUEST_URI'] = '/country/turkey/';
$_SERVER['SCRIPT_NAME'] = '/country/turkey/index.php';

require_once __DIR__ . '/../v2/country-page-v1.php';

$resorts = ['Анталья', 'Аланья', 'Кемер', 'Сиде', 'Белек'];
$expected = [
    'Анталья' => '/country/turkey/antalya/',
    'Аланья' => '/country/turkey/alanya/',
    'Кемер' => '/country/turkey/kemer/',
    'Сиде' => '/country/turkey/side/',
    'Белек' => '/country/turkey/belek/',
];
$candidates = [];
foreach ($expected as $label => $href) $candidates[] = ['label' => $label, 'href' => $href];

if (cp_country_resort_links('/country/turkey/', $resorts, $candidates) !== $expected) {
    turkey_country_resort_links_fail('allowlisted_link_map');
}
if (cp_country_resort_links('/country/egypt/', $resorts, $candidates) !== []) {
    turkey_country_resort_links_fail('non_turkey_link_map');
}
if (cp_country_resort_links('/country/turkey/', $resorts, [
    ['label' => 'Анталья', 'href' => '/country/turkey/antalya/?country=4'],
    ['label' => 'Несуществующий', 'href' => '/country/turkey/antalya/'],
    ['label' => ['Анталья'], 'href' => '/country/turkey/antalya/'],
    ['label' => 'Анталья', 'href' => ['/country/turkey/antalya/']],
    'malformed',
]) !== []) {
    turkey_country_resort_links_fail('unsafe_candidate_accepted');
}
$allowed = v2_seo_turkey_launch_paths();
if (count($allowed) !== 6 || array_values(array_diff(array_values($expected), $allowed)) !== []) {
    turkey_country_resort_links_fail('launch_allowlist_drift');
}

$context = sp_context('/country/turkey/', 'Туры в Турцию', 'Test');
if (!str_starts_with((string)($context['robots'] ?? ''), 'index,follow')) {
    turkey_country_resort_links_fail('turkey_country_not_indexable');
}

ob_start();
include __DIR__ . '/../v2/country/turkey/index.php';
$html = (string)ob_get_clean();
if (substr_count($html, 'data-country-resort-link') !== 5) {
    turkey_country_resort_links_fail('ssr_link_count');
}
if (!str_contains($html, '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">')) {
    turkey_country_resort_links_fail('rendered_indexability');
}
foreach ($expected as $label => $href) {
    $needle = '<a class="sp-resort-chip" data-country-resort-link href="' . $href . '" aria-label="Туры в ' . $label . '">' . $label . '</a>';
    if (substr_count($html, $needle) !== 1) turkey_country_resort_links_fail('rendered_link:' . $href);
}
if (preg_match_all('#<a[^>]+data-country-resort-link[^>]+href="([^"]+)"#', $html, $matches) !== 5) {
    turkey_country_resort_links_fail('rendered_link_parse');
}
foreach ($matches[1] as $href) {
    if (!in_array($href, $expected, true) || str_contains($href, '?') || str_contains($href, '#')) {
        turkey_country_resort_links_fail('rendered_href_boundary');
    }
}
$css = file_get_contents(__DIR__ . '/../v2/shared-content-primitives-v1.css');
if ($css === false || !str_contains($css, '.sp-country-intent--hero .sp-resort-chip{min-height:34px;background:#fff;color:var(--at-text);font-size:12px;text-decoration:none}')) {
    turkey_country_resort_links_fail('chip_visual_contract');
}

echo "SEO_TURKEY_COUNTRY_RESORT_LINKS_OK links=5 allowlisted=1 ssr=1 indexed=1 ds2Chip=1\n";
