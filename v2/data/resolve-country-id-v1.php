<?php
/**
 * Resolve synchronized Tourvisor country IDs from the local AnyTour catalog.
 * Reusable read-only CLI; safe to keep after temporary production diagnostics are removed.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function country_resolver_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return trim(substr($arg, strlen($name) + 3));
        }
    }
    return null;
}

function country_resolver_names(array $argv): array
{
    $raw = country_resolver_arg($argv, 'names') ?? country_resolver_arg($argv, 'name') ?? '';
    $parts = preg_split('/\s*,\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_unique(array_filter(array_map('trim', $parts))));
}

$names = country_resolver_names($argv);
if ($names === []) {
    fwrite(STDERR, "Usage: php v2/data/resolve-country-id-v1.php --name='Шри-Ланка'\n");
    fwrite(STDERR, "   or: php v2/data/resolve-country-id-v1.php --names='Шри-Ланка,Мальдивы'\n");
    exit(2);
}

$pdo = v2_data_db();
$stmt = $pdo->prepare("SELECT id,name,slug,synced_at FROM catalog_countries WHERE is_active=1 AND LOWER(name)=LOWER(:name) ORDER BY id ASC");
$results = [];
foreach ($names as $name) {
    $stmt->execute(['name' => $name]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) {
        $results[] = ['query' => $name, 'status' => count($rows) === 0 ? 'not_found' : 'ambiguous', 'matches' => $rows];
        continue;
    }
    $row = $rows[0];
    $results[] = [
        'query' => $name,
        'status' => 'ok',
        'country_id' => (int)$row['id'],
        'country_name' => (string)$row['name'],
        'country_slug' => (string)$row['slug'],
        'synced_at' => (string)$row['synced_at'],
    ];
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
foreach ($results as $result) {
    if (($result['status'] ?? '') !== 'ok') exit(1);
}
