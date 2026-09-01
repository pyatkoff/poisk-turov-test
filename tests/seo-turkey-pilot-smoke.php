<?php
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-catalog-v1.php';

$catalog = v2_seo_content_pilot_turkey_catalog();
$expected = ['/country/turkey/', '/country/turkey/kemer/'];

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
$kemerReport = $catalog['reports']['/country/turkey/kemer/'] ?? [];
if (($turkeyReport['status'] ?? '') !== 'approved') {
    fwrite(STDERR, "Turkey editorial candidate must be approved\n");
    exit(1);
}
if (($kemerReport['status'] ?? '') !== 'review') {
    fwrite(STDERR, "Kemer must remain review-only until resort prefill is verified\n");
    exit(1);
}
if (($catalog['registry']['/country/turkey/']['page']['search_state']['country'] ?? null) !== 4) {
    fwrite(STDERR, "Turkey search prefill country id is invalid\n");
    exit(1);
}
if (($catalog['graph']['/country/turkey/kemer/']['parent'] ?? null) !== '/country/turkey/') {
    fwrite(STDERR, "Kemer parent relation is invalid\n");
    exit(1);
}
if (($catalog['publication_candidates'] ?? []) !== ['/country/turkey/']) {
    fwrite(STDERR, "Only Turkey may be a publication candidate\n");
    exit(1);
}

echo "SEO Turkey/Kemer pilot smoke OK turkeyApproved=1 kemerReview=1 countryPrefill=4\n";
