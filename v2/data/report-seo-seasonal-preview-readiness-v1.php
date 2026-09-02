<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/seo-seasonal-preview-evidence-readiness-v1.php';

function seasonal_preview_report_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return trim(substr($arg, strlen($name) + 3));
        }
    }
    return null;
}

$evidenceFile = seasonal_preview_report_arg($argv, 'evidence-file') ?? '';
$nowRaw = seasonal_preview_report_arg($argv, 'now-epoch');
$requireReadyRaw = seasonal_preview_report_arg($argv, 'require-ready');
$requireReady = $requireReadyRaw === null ? false : !in_array(strtolower($requireReadyRaw), ['0', 'false', 'no'], true);

if ($evidenceFile === '' || !is_file($evidenceFile)) {
    fwrite(STDERR, "Usage: php v2/data/report-seo-seasonal-preview-readiness-v1.php --evidence-file=/path/identities.json [--now-epoch=...] [--require-ready=1]\n");
    exit(2);
}

$raw = file_get_contents($evidenceFile);
if ($raw === false) {
    fwrite(STDERR, "SEO_SEASONAL_PREVIEW_READINESS_FAIL:evidence_unreadable\n");
    exit(2);
}
try {
    $inventory = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, "SEO_SEASONAL_PREVIEW_READINESS_FAIL:evidence_invalid_json\n");
    exit(2);
}
if (!is_array($inventory)) {
    fwrite(STDERR, "SEO_SEASONAL_PREVIEW_READINESS_FAIL:evidence_not_object\n");
    exit(2);
}

$nowEpoch = time();
if ($nowRaw !== null) {
    if (!preg_match('/^[0-9]{10,12}$/', $nowRaw)) {
        fwrite(STDERR, "SEO_SEASONAL_PREVIEW_READINESS_FAIL:invalid_now_epoch\n");
        exit(2);
    }
    $nowEpoch = (int) $nowRaw;
}

$report = v2_seo_seasonal_preview_evidence_readiness($inventory, $nowEpoch);
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";

if ($requireReady && ($report['all_review_ready'] ?? false) !== true) {
    fwrite(STDERR, "SEO_SEASONAL_PREVIEW_READINESS_FAIL:not_all_review_ready ready=" . (int) ($report['ready_count'] ?? 0) . " total=" . (int) ($report['preview_count'] ?? 0) . "\n");
    exit(3);
}
