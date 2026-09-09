<?php
/** Read-only CLI wrapper around policy-driven seasonal coverage readiness. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/seo-seasonal-coverage-readiness-v1.php';

function coverage_cli_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return trim(substr($arg, strlen($name)+3));
    return null;
}

$file = coverage_cli_arg($argv, 'file');
$raw = $file !== null ? @file_get_contents($file) : stream_get_contents(STDIN);
if ($raw === false || trim($raw) === '') { fwrite(STDERR,"SEO_SEASONAL_COVERAGE_CLI_FAIL:empty_input\n"); exit(2); }
try { $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
catch (Throwable $e) { fwrite(STDERR,"SEO_SEASONAL_COVERAGE_CLI_FAIL:invalid_json\n"); exit(2); }
if (!is_array($data)) { fwrite(STDERR,"SEO_SEASONAL_COVERAGE_CLI_FAIL:invalid_payload\n"); exit(2); }

$policy = [
    'country_id'=>(int)(coverage_cli_arg($argv,'country') ?? '0'),
    'min_month_identities'=>(int)(coverage_cli_arg($argv,'min-month-identities') ?? '0'),
    'min_resort_month_identities'=>(int)(coverage_cli_arg($argv,'min-resort-month-identities') ?? '-1'),
    'min_freshness_seconds'=>(int)(coverage_cli_arg($argv,'min-freshness-seconds') ?? '0'),
    'min_offers_per_snapshot'=>(int)(coverage_cli_arg($argv,'min-offers-per-snapshot') ?? '0'),
];
$out = v2_seo_seasonal_coverage_assess($data, $policy);
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if ((coverage_cli_arg($argv,'require-review-ready') ?? '0') === '1' && ($out['review_ready'] ?? false) !== true) {
    fwrite(STDERR,"SEO_SEASONAL_COVERAGE_CLI_FAIL:review_blocked\n");
    exit(3);
}
