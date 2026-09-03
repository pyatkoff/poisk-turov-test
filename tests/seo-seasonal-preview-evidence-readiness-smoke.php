<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-preview-evidence-readiness-v1.php';

function seasonal_preview_evidence_fail(string $code): void
{
    fwrite(STDERR, "SEO_SEASONAL_PREVIEW_EVIDENCE_FAIL:$code\n");
    exit(1);
}

$now = 1788436800;
$checked = 1788436200;
$valid = 1788445200;
$identity = static function(string $pageKey,string $pageType,int $countryId,?int $regionId,int $month) use($checked,$valid): array {
    return [
        'state' => 'fresh_review_identity',
        'page_key' => $pageKey,
        'page_type' => $pageType,
        'country_id' => $countryId,
        'region_id' => $regionId,
        'departure_id' => 1,
        'year' => 2026,
        'month' => $month,
        'evidence_checked_at_epoch' => $checked,
        'expires_at_epoch' => $valid,
        'freshness_seconds' => $valid - $checked,
        'publication_allowed' => false,
        'copy_allowed' => false,
    ];
};
$inventory = [
    'state' => 'review_only_seasonal_identity_inventory',
    'identity_count' => 4,
    'blocked_count' => 0,
    'evidence_checked_at_epoch' => $checked,
    'evidence_valid_until_epoch' => $valid,
    'evidence_clock_valid' => true,
    'publication_candidates' => [],
    'publication_allowed' => false,
    'copy_allowed' => false,
    'identities' => [
        $identity('resort_month:1:4:20:2026-09','resort_month',4,20,9),
        $identity('month:1:8:2026-09','month',8,null,9),
        $identity('resort_month:1:4:20:2026-10','resort_month',4,20,10),
        $identity('month:1:8:2026-10','month',8,null,10),
    ],
    'blocked' => [],
];

$report = v2_seo_seasonal_preview_evidence_readiness($inventory, $now);
if (($report['state'] ?? '') !== 'review_ready_with_fresh_identity_evidence') seasonal_preview_evidence_fail('ready_state');
if (($report['preview_count'] ?? 0) !== 4 || ($report['ready_count'] ?? 0) !== 4) seasonal_preview_evidence_fail('ready_count');
if (($report['all_review_ready'] ?? false) !== true || ($report['blocked'] ?? null) !== []) seasonal_preview_evidence_fail('ready_boundary');
foreach ($report['pages'] as $page) {
    if (($page['score'] ?? 0) !== 100 || ($page['structural_score'] ?? 0) !== 100 || ($page['fresh_identity_ready'] ?? false) !== true) seasonal_preview_evidence_fail('page_score');
}
foreach (['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag) {
    if (($report[$flag] ?? true) !== false) seasonal_preview_evidence_fail('launch_flag_' . $flag);
}
if (($report['publication_candidates'] ?? null) !== [] || ($report['explicit_launch_approval_required'] ?? false) !== true) seasonal_preview_evidence_fail('publication_boundary');

$missing = $inventory;
array_pop($missing['identities']);
$missing['identity_count'] = 3;
$report = v2_seo_seasonal_preview_evidence_readiness($missing, $now);
if (($report['state'] ?? '') !== 'blocked' || ($report['ready_count'] ?? 4) !== 3) seasonal_preview_evidence_fail('missing_identity');
if (!in_array('missing_fresh_exact_identity', array_column($report['blocked'] ?? [], 'code'), true)) seasonal_preview_evidence_fail('missing_identity_code');

$stale = $inventory;
$stale['evidence_valid_until_epoch'] = $now;
foreach($stale['identities'] as &$row)$row['expires_at_epoch']=$now;
unset($row);
$report = v2_seo_seasonal_preview_evidence_readiness($stale, $now);
if (($report['state'] ?? '') !== 'blocked') seasonal_preview_evidence_fail('stale_not_blocked');

$tampered = $inventory;
$tampered['identities'][0]['country_id'] = 8;
$report = v2_seo_seasonal_preview_evidence_readiness($tampered, $now);
if (($report['state'] ?? '') !== 'blocked') seasonal_preview_evidence_fail('tampered_not_blocked');
if (!in_array('invalid_or_stale_identity_record', array_column($report['blocked'] ?? [], 'code'), true)) seasonal_preview_evidence_fail('tampered_code');

echo "SEO_SEASONAL_PREVIEW_EVIDENCE_READINESS_OK previews=4 score=100 fresh=4 publication=0\n";
