<?php
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-catalog-v1.php';

$catalog = v2_seo_content_pilot_turkey_catalog();
$expected = [
    '/country/turkey/',
    '/country/turkey/kemer/',
    '/country/turkey/antalya/',
    '/country/turkey/side/',
    '/country/turkey/belek/',
    '/country/turkey/alanya/',
];

foreach ($expected as $path) {
    if (!isset($catalog['registry'][$path])) {
        fwrite(STDERR, "Missing registry path: {$path}\n");
        exit(1);
    }
    $report = $catalog['reports'][$path] ?? null;
    if (!is_array($report) || $report['publishable'] !== true) {
        fwrite(STDERR, "Pilot is not structurally publishable: {$path}\n");
        exit(1);
    }
}

$turkeyReport = $catalog['reports']['/country/turkey/'] ?? [];
if (($turkeyReport['status'] ?? '') !== 'approved') {
    fwrite(STDERR, "Turkey editorial candidate must be approved\n");
    exit(1);
}
if (($catalog['registry']['/country/turkey/']['page']['search_state']['country'] ?? null) !== 4) {
    fwrite(STDERR, "Turkey search prefill country id is invalid\n");
    exit(1);
}

$verifiedRegions = [
    '/country/turkey/alanya/' => 19,
    '/country/turkey/antalya/' => 20,
    '/country/turkey/belek/' => 21,
    '/country/turkey/kemer/' => 22,
    '/country/turkey/side/' => 23,
];
foreach ($verifiedRegions as $path => $regionId) {
    $report = $catalog['reports'][$path] ?? [];
    $expectedStatus = $path === '/country/turkey/kemer/' ? 'approved' : 'review';
    if (($report['status'] ?? '') !== $expectedStatus) {
        fwrite(STDERR, "Unexpected resort editorial status for {$path}\n");
        exit(1);
    }
    $state = $catalog['registry'][$path]['page']['search_state'] ?? [];
    if (($state['country'] ?? null) !== 4 || ($state['region'] ?? null) !== $regionId) {
        fwrite(STDERR, "Invalid verified search prefill for {$path}\n");
        exit(1);
    }
    if (($catalog['graph'][$path]['parent'] ?? null) !== '/country/turkey/') {
        fwrite(STDERR, "Invalid Turkey parent relation for {$path}\n");
        exit(1);
    }
}

$expectedCandidates = ['/country/turkey/', '/country/turkey/kemer/'];
if (($catalog['publication_candidates'] ?? []) !== $expectedCandidates) {
    fwrite(STDERR, "Turkey and Kemer must be the only publication candidates\n");
    exit(1);
}

echo "SEO Turkey pilot smoke OK country=4 regions=19,20,21,22,23 kemerApproved=1\n";
