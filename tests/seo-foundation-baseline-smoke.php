<?php
declare(strict_types=1);

function baseline_fail(string $code): never
{
    fwrite(STDERR, "SEO_FOUNDATION_BASELINE_FAIL:$code\n");
    exit(1);
}

$root = dirname(__DIR__);
$file = $root . '/docs/seo-foundation-baseline.json';
$raw = file_get_contents($file);
if ($raw === false) baseline_fail('baseline_missing');

try {
    $baseline = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    baseline_fail('baseline_json_invalid');
}
if (!is_array($baseline)) baseline_fail('baseline_not_object');

if (($baseline['schema_version'] ?? null) !== 1) baseline_fail('schema_version');
if (($baseline['artifact'] ?? '') !== 'anytour_seo_foundation_source_inventory') baseline_fail('artifact');
if (($baseline['status'] ?? '') !== 'baseline_only') baseline_fail('status');

$scope = $baseline['scope'] ?? null;
if (!is_array($scope)) baseline_fail('scope');
foreach ([
    'evidence_kind' => 'repository_source_inventory',
    'live_validation' => 'separate_workflow_required',
    'publication_side_effects' => false,
    'traffic_metrics_measured' => false,
    'conversion_metrics_measured' => false,
    'ranking_metrics_measured' => false,
] as $key => $expected) {
    if (($scope[$key] ?? null) !== $expected) baseline_fail('scope_' . $key);
}

$inventory = $baseline['inventory'] ?? null;
if (!is_array($inventory)) baseline_fail('inventory');
$requiredAreas = ['publication', 'canonical', 'indexability', 'sitemap', 'internal_links', 'schema', 'performance'];
foreach ($requiredAreas as $area) {
    $entry = $inventory[$area] ?? null;
    if (!is_array($entry)) baseline_fail('inventory_' . $area);
    $owners = $entry['owner'] ?? null;
    if (!is_array($owners) || $owners === []) baseline_fail('owners_' . $area);
    foreach ($owners as $owner) {
        if (!is_string($owner) || $owner === '' || !is_file($root . '/' . $owner)) {
            baseline_fail('owner_missing_' . $area);
        }
    }
    $ciOwner = $entry['ci_owner'] ?? '';
    if (!is_string($ciOwner) || $ciOwner === '') baseline_fail('ci_owner_' . $area);
    if (str_starts_with($ciOwner, '.github/') && !is_file($root . '/' . $ciOwner)) {
        baseline_fail('ci_owner_missing_' . $area);
    }
}

$snapshot = $inventory['sitemap']['source_snapshot'] ?? null;
if (!is_array($snapshot)) baseline_fail('sitemap_snapshot');
$xml = file_get_contents($root . '/v2/sitemap.xml');
if ($xml === false) baseline_fail('sitemap_missing');
preg_match_all('~<loc>https://anytoour\\.ru([^<]+)</loc>~', $xml, $matches);
$paths = $matches[1] ?? [];
$months = array_fill_keys(['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'], true);
$counts = ['country_url_count' => 0, 'resort_url_count' => 0, 'seasonal_url_count' => 0, 'hotel_tours_url_count' => 0];
foreach ($paths as $path) {
    $parts = array_values(array_filter(explode('/', $path), static fn(string $part): bool => $part !== ''));
    if (str_contains($path, '/hotel/')) {
        $counts['hotel_tours_url_count']++;
    } elseif (isset($months[end($parts) ?: ''])) {
        $counts['seasonal_url_count']++;
    } elseif (($parts[0] ?? '') === 'country' && count($parts) === 2) {
        $counts['country_url_count']++;
    } elseif (($parts[0] ?? '') === 'country' && count($parts) === 3) {
        $counts['resort_url_count']++;
    }
}
$counts['static_url_count'] = count($paths);
$counts['duplicates'] = count($paths) - count(array_unique($paths));
foreach ($counts as $key => $actual) {
    if (($snapshot[$key] ?? null) !== $actual) baseline_fail('sitemap_' . $key);
}
if (str_contains($xml, '/hotel/') || str_contains($xml, '/poisk-turov/')) baseline_fail('sitemap_protected_route_leak');

$gaps = $baseline['known_gaps'] ?? null;
if (!is_array($gaps) || count($gaps) < 4) baseline_fail('known_gaps');
$priorities = [];
foreach ($gaps as $gap) {
    if (!is_array($gap)) baseline_fail('gap_shape');
    foreach (['priority', 'id', 'finding', 'next_decision', 'evidence_required', 'change_authorization'] as $key) {
        if (!is_string($gap[$key] ?? null) || trim($gap[$key]) === '') baseline_fail('gap_' . $key);
    }
    $priorities[$gap['priority']] = true;
}
if (isset($priorities['P0']) || !isset($priorities['P1'], $priorities['P2'], $priorities['P3'])) baseline_fail('gap_priorities');

$validation = $baseline['validation'] ?? null;
if (!is_array($validation) || ($validation['ci_owner'] ?? '') !== '.github/workflows/validate-v2-seo-publication-manifest.yml') {
    baseline_fail('validation_owner');
}

echo 'SEO_FOUNDATION_BASELINE_OK sitemap=' . count($paths) . ' country=' . $counts['country_url_count'] . ' resort=' . $counts['resort_url_count'] . ' seasonal=' . $counts['seasonal_url_count'] . " gaps=" . count($gaps) . "\n";
