<?php
require_once __DIR__ . '/../v2/seo-seasonal-evidence-v1.php';

function seasonal_fail(string $message): void { fwrite(STDERR, "SEO_SEASONAL_EVIDENCE_FAIL:$message\n"); exit(1); }

$now = strtotime('2026-09-02T09:30:00Z');
$fresh = [
    'page_type' => 'month',
    'page_key' => 'month:1:4:2026-10',
    'dimensions' => ['departureId'=>1,'countryId'=>4,'year'=>2026,'month'=>10],
    'offer_count' => 6,
    'observed_at' => '2026-09-02T08:45:00Z',
    'expires_at' => '2026-09-02T15:30:00Z',
];
$row = v2_seo_seasonal_evidence_from_snapshot($fresh);
$assessment = v2_seo_seasonal_evidence_assess($row, $now);
if (($assessment['usable'] ?? false) !== true) seasonal_fail('fresh_month_blocked');
if (($assessment['state'] ?? '') !== 'fresh_operational_evidence') seasonal_fail('fresh_state');
if (($assessment['freshness_seconds'] ?? 0) !== 21600) seasonal_fail('freshness_seconds');
if (!in_array('climate', $assessment['forbidden_claims'] ?? [], true)) seasonal_fail('climate_not_forbidden');
if (!in_array('best_time_to_travel', $assessment['forbidden_claims'] ?? [], true)) seasonal_fail('best_time_not_forbidden');
if (!in_array('candidate_selection', $assessment['allowed_uses'] ?? [], true)) seasonal_fail('candidate_selection_missing');

$stale = $fresh;
$stale['expires_at'] = '2026-09-02T09:29:59Z';
$assessment = v2_seo_seasonal_evidence_assess(v2_seo_seasonal_evidence_from_snapshot($stale), $now);
if (($assessment['usable'] ?? true) !== false || !in_array('stale_evidence', $assessment['errors'] ?? [], true)) seasonal_fail('stale_not_blocked');

$wrongKey = $fresh;
$wrongKey['page_key'] = 'month:1:4:2026-11';
$assessment = v2_seo_seasonal_evidence_assess(v2_seo_seasonal_evidence_from_snapshot($wrongKey), $now);
if (($assessment['usable'] ?? true) !== false || !in_array('page_key_mismatch', $assessment['errors'] ?? [], true)) seasonal_fail('wrong_key_not_blocked');

$resort = [
    'page_type' => 'resort_month',
    'page_key' => 'resort_month:1:4:77:2026-10',
    'dimensions' => ['departureId'=>1,'countryId'=>4,'regionId'=>77,'year'=>2026,'month'=>10],
    'offers' => [['hotelId'=>1]],
    'observed_at' => '2026-09-02T08:45:00Z',
    'expires_at' => '2026-09-02T15:30:00Z',
];
$assessment = v2_seo_seasonal_evidence_assess(v2_seo_seasonal_evidence_from_snapshot($resort), $now);
if (($assessment['usable'] ?? false) !== true || ($assessment['region_id'] ?? 0) !== 77) seasonal_fail('resort_month');

$unsupported = $fresh;
$unsupported['page_type'] = 'hotel';
$assessment = v2_seo_seasonal_evidence_assess(v2_seo_seasonal_evidence_from_snapshot($unsupported), $now);
if (($assessment['usable'] ?? true) !== false || !in_array('invalid_scope', $assessment['errors'] ?? [], true)) seasonal_fail('unsupported_snapshot_type');

echo "SEO_SEASONAL_EVIDENCE_OK fresh=1 staleBlocked=1 scopeFence=1 claimFence=1\n";
