<?php
/** Read-only CLI validator for monthly SEO seasonal evidence. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/seo-seasonal-evidence-v1.php';

function seasonal_cli_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name)+3);
    return null;
}

$file = seasonal_cli_arg($argv, 'file');
$requireUsable = (int)(seasonal_cli_arg($argv, 'require-usable') ?? '0');
$raw = $file !== null ? @file_get_contents($file) : stream_get_contents(STDIN);
if ($raw === false || trim($raw) === '') { fwrite(STDERR, "SEO_SEASONAL_EVIDENCE_CLI_FAIL:empty_input\n"); exit(2); }
try { $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
catch (Throwable $e) { fwrite(STDERR, "SEO_SEASONAL_EVIDENCE_CLI_FAIL:invalid_json\n"); exit(2); }

if (!is_array($decoded)) { fwrite(STDERR, "SEO_SEASONAL_EVIDENCE_CLI_FAIL:invalid_payload\n"); exit(2); }
$items = array_is_list($decoded) ? $decoded : [$decoded];
$rows = [];
$usable = 0;
foreach ($items as $item) {
    if (!is_array($item)) {
        $rows[] = ['state'=>'blocked','usable'=>false,'errors'=>['invalid_item']];
        continue;
    }
    $row = isset($item['page_type']) ? v2_seo_seasonal_evidence_from_snapshot($item) : $item;
    $assessment = v2_seo_seasonal_evidence_assess($row);
    if (($assessment['usable'] ?? false) === true) $usable++;
    $rows[] = $assessment;
}

$out = [
    'state' => 'review_only_seasonal_evidence_report',
    'total' => count($rows),
    'usable' => $usable,
    'blocked' => count($rows)-$usable,
    'rows' => $rows,
];
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";
if ($requireUsable > 0 && $usable < $requireUsable) {
    fwrite(STDERR, "SEO_SEASONAL_EVIDENCE_CLI_FAIL:require_usable expected={$requireUsable} actual={$usable}\n");
    exit(3);
}
