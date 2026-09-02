<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/seo-page-launch-readiness-v1.php';
require_once dirname(__DIR__) . '/seo-launch-manifest-v1.php';

function seo2_readiness_cli_fail(string $message, int $code = 2): never
{
    fwrite(STDERR, "SEO_LAUNCH_READINESS_REPORT_FAIL:$message\n");
    exit($code);
}

$options = getopt('', ['catalog-file:', 'catalog-function:', 'evidence-file::', 'require-ready::', 'require-family-floor::']);
$catalogFile = trim((string)($options['catalog-file'] ?? ''));
$catalogFunction = trim((string)($options['catalog-function'] ?? ''));
$evidenceFile = trim((string)($options['evidence-file'] ?? ''));
$requireReady = array_key_exists('require-ready', $options)
    ? max(0, (int)($options['require-ready'] === false ? 1 : $options['require-ready']))
    : 0;
$requireFamilyFloor = array_key_exists('require-family-floor', $options)
    ? max(0, min(100, (int)($options['require-family-floor'] === false ? 100 : $options['require-family-floor'])))
    : 0;

if ($catalogFile === '' || $catalogFunction === '') {
    seo2_readiness_cli_fail('usage: --catalog-file=<path> --catalog-function=<function> [--evidence-file=<json>] [--require-ready=<n>] [--require-family-floor=<0-100>]');
}

$repoRoot = dirname(__DIR__, 2);
$resolvedCatalog = realpath($repoRoot . '/' . ltrim($catalogFile, '/'));
if ($resolvedCatalog === false || !str_starts_with($resolvedCatalog, $repoRoot . DIRECTORY_SEPARATOR)) {
    seo2_readiness_cli_fail('catalog file must resolve inside the repository');
}
require_once $resolvedCatalog;
if (!function_exists($catalogFunction)) seo2_readiness_cli_fail('catalog function does not exist');

$catalog = $catalogFunction();
if (!is_array($catalog)) seo2_readiness_cli_fail('catalog function must return an array');

$evidence = [];
if ($evidenceFile !== '') {
    $resolvedEvidence = realpath($evidenceFile);
    if ($resolvedEvidence === false || !is_file($resolvedEvidence)) seo2_readiness_cli_fail('evidence file not found');
    $raw = file_get_contents($resolvedEvidence);
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) seo2_readiness_cli_fail('evidence file must contain a JSON array');
    $evidence = $decoded;
} else {
    $stdinIsTty = function_exists('stream_isatty') ? stream_isatty(STDIN) : true;
    if (!$stdinIsTty) {
        $raw = stream_get_contents(STDIN);
        if (trim((string)$raw) !== '') {
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) seo2_readiness_cli_fail('stdin evidence must contain a JSON array');
            $evidence = $decoded;
        }
    }
}

$nowEpoch = time();
$rows = v2_seo_page_launch_readiness($catalog, $evidence, $nowEpoch);
$summary = v2_seo_page_launch_readiness_summary($rows);
$manifest = v2_seo_launch_manifest($catalog, $evidence, $nowEpoch);
$ready = array_values(array_filter($rows, static fn(array $row): bool => ($row['ready_for_launch_review'] ?? false) === true));
$blocked = array_values(array_filter($rows, static fn(array $row): bool => ($row['ready_for_launch_review'] ?? false) !== true));

$output = [
    'state' => 'review_only_launch_readiness_report',
    'generated_at' => gmdate('c', $nowEpoch),
    'summary' => $summary,
    'quality_score' => (int)($manifest['quality_score'] ?? 0),
    'family_quality_floor' => (int)($manifest['family_quality_floor'] ?? 0),
    'quality_by_type' => $manifest['quality_by_type'] ?? [],
    'integrity_ok' => (bool)($manifest['integrity_ok'] ?? false),
    'manifest_state' => (string)($manifest['state'] ?? ''),
    'manifest_sha256' => (string)($manifest['manifest_sha256'] ?? ''),
    'hotel_evidence_sha256' => (string)($manifest['hotel_evidence_sha256'] ?? ''),
    'review_contract_sha256' => (string)($manifest['review_contract_sha256'] ?? ''),
    'hotel_evidence_valid_until_epoch' => (int)($manifest['hotel_evidence_valid_until_epoch'] ?? 0),
    'hotel_evidence_remaining_seconds' => (int)($manifest['hotel_evidence_remaining_seconds'] ?? 0),
    'hotel_evidence_fresh' => (bool)($manifest['hotel_evidence_fresh'] ?? false),
    'ready_count' => count($ready),
    'blocked_count' => count($blocked),
    'ready' => $ready,
    'blocked' => $blocked,
    'publication_allowed' => false,
    'hotel_tours_publication_allowed' => false,
    'hotel_tours_indexation_allowed' => false,
];

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

if ($requireReady > 0 && count($ready) < $requireReady) {
    seo2_readiness_cli_fail('ready page count is below required threshold', 3);
}
if ($requireFamilyFloor > 0 && (int)($manifest['family_quality_floor'] ?? 0) < $requireFamilyFloor) {
    seo2_readiness_cli_fail('family quality floor is below required threshold', 4);
}
