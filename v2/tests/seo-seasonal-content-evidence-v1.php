<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/seo-seasonal-content-evidence-v1.php';

$now = strtotime('2026-09-02T12:00:00Z');
$base = [[
    'page_key' => 'month:1:4:2026-09',
    'claim_key' => 'air-temperature-reference',
    'type' => 'climate_temperature',
    'value' => 'source-backed-value',
    'source_class' => 'official_meteorological',
    'source_id' => 'fixture:met-office-record',
    'observed_at' => '2026-08-01T00:00:00Z',
]];

$ok = v2_seo_seasonal_content_evidence($base, $now);
if (($ok['state'] ?? '') !== 'review_ready' || ($ok['publication_allowed'] ?? true) !== false || ($ok['copy_allowed_without_evidence'] ?? true) !== false) {
    fwrite(STDERR, "valid evidence contract failed\n"); exit(1);
}

$unsupported = $base;
$unsupported[0]['type'] = 'best_time_to_visit';
if ((v2_seo_seasonal_content_evidence($unsupported, $now)['state'] ?? '') !== 'blocked') {
    fwrite(STDERR, "unsupported marketing claim was accepted\n"); exit(1);
}

$untrusted = $base;
$untrusted[0]['source_class'] = 'blog';
if ((v2_seo_seasonal_content_evidence($untrusted, $now)['state'] ?? '') !== 'blocked') {
    fwrite(STDERR, "untrusted source was accepted\n"); exit(1);
}

$volatile = $base;
$volatile[0]['type'] = 'entry_requirement';
if ((v2_seo_seasonal_content_evidence($volatile, $now)['state'] ?? '') !== 'blocked') {
    fwrite(STDERR, "volatile evidence without expiry was accepted\n"); exit(1);
}

$expired = $base;
$expired[0]['valid_until'] = '2026-09-01T00:00:00Z';
if ((v2_seo_seasonal_content_evidence($expired, $now)['state'] ?? '') !== 'blocked') {
    fwrite(STDERR, "expired evidence was accepted\n"); exit(1);
}

$duplicate = [$base[0], $base[0]];
if ((v2_seo_seasonal_content_evidence($duplicate, $now)['state'] ?? '') !== 'blocked') {
    fwrite(STDERR, "duplicate claim identity was accepted\n"); exit(1);
}

echo "seasonal content evidence contract: ok\n";
