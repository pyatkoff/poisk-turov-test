<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/seo-feed-review-plan-v1.php';

function seo_feed_plan_cli_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return null;
}

function seo_feed_plan_cli_flag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true) || seo_feed_plan_cli_arg($argv, $name) === '1';
}

function seo_feed_plan_cli_json_file(?string $path, string $label): array
{
    if ($path === null || trim($path) === '' || !is_file($path)) {
        throw new InvalidArgumentException("Missing {$label} JSON file");
    }
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException("Unable to read {$label} JSON file");
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new InvalidArgumentException("{$label} JSON must decode to an array");
    return $decoded;
}

try {
    $binding = seo_feed_plan_cli_json_file(seo_feed_plan_cli_arg($argv, 'binding'), 'binding');
    $selectorDoc = seo_feed_plan_cli_json_file(seo_feed_plan_cli_arg($argv, 'selectors'), 'selectors');
    $selectors = array_is_list($selectorDoc) ? $selectorDoc : ($selectorDoc['selectors'] ?? null);
    if (!is_array($selectors)) throw new InvalidArgumentException('Selectors JSON must be a list or contain selectors[]');

    $maxItems = filter_var(seo_feed_plan_cli_arg($argv, 'max-items') ?? '12', FILTER_VALIDATE_INT);
    if ($maxItems === false) throw new InvalidArgumentException('Invalid --max-items');
    $now = filter_var(seo_feed_plan_cli_arg($argv, 'now-epoch') ?? (string)time(), FILTER_VALIDATE_INT);
    if ($now === false || (int)$now <= 0) throw new InvalidArgumentException('Invalid --now-epoch');

    $plan = v2_seo_feed_review_plan($binding, $selectors, (int)$now, (int)$maxItems);
    $out = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    echo $out;

    if (seo_feed_plan_cli_flag($argv, 'require-review-ready')) {
        if (($plan['state'] ?? '') !== 'review_only_feed_plan'
            || ($plan['item_count'] ?? 0) < 1
            || ($plan['publication_candidates'] ?? null) !== []
            || ($plan['feed_publish_allowed'] ?? true) !== false
            || ($plan['publication_allowed'] ?? true) !== false
            || ($plan['copy_allowed'] ?? true) !== false
            || ($plan['route_creation_allowed'] ?? true) !== false) {
            fwrite(STDERR, "SEO_FEED_REVIEW_PLAN_CLI_BLOCKED\n");
            exit(2);
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'SEO_FEED_REVIEW_PLAN_CLI_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
