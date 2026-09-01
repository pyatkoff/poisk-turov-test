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
    if (($report['status'] ?? '') !== 'review') {
        fwrite(STDERR, "Pilot must remain review-only: {$path}\n");
        exit(1);
    }
}

if (($catalog['graph']['/country/turkey/kemer/']['parent'] ?? null) !== '/country/turkey/') {
    fwrite(STDERR, "Kemer parent relation is invalid\n");
    exit(1);
}

if (($catalog['publication_candidates'] ?? []) !== []) {
    fwrite(STDERR, "Review-only pilot must not become a publication candidate\n");
    exit(1);
}

echo "SEO Turkey/Kemer pilot smoke OK\n";
