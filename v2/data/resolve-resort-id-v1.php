<?php
/** Resolve resort names against the synchronized Tourvisor catalog without guessing IDs. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function resolver_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return trim(substr($arg, strlen($name) + 3));
        }
    }
    return null;
}

function resolver_flag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

function resolver_names(array $argv): array
{
    $single = resolver_arg($argv, 'name');
    $batch = resolver_arg($argv, 'names');
    $raw = $single !== null && $single !== '' ? [$single] : explode(',', (string)$batch);
    $names = [];
    foreach ($raw as $name) {
        $name = trim($name);
        if ($name !== '') $names[] = $name;
    }
    return array_values(array_unique($names));
}

function resolver_lookup(PDO $pdo, int $countryId, string $name): array
{
    $sql = "SELECT
                s.id AS subregion_id,
                s.name AS subregion_name,
                s.slug AS subregion_slug,
                r.id AS region_id,
                r.name AS region_name,
                r.slug AS region_slug,
                r.country_id
            FROM catalog_subregions s
            INNER JOIN catalog_regions r ON r.id = s.region_id
            WHERE r.country_id = :country_id
              AND s.is_active = 1
              AND r.is_active = 1
              AND (s.name = :name_exact OR s.slug = :name_slug)
            ORDER BY (s.name = :name_rank) DESC, s.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'country_id' => $countryId,
        'name_exact' => $name,
        'name_slug' => v2_data_slug($name),
        'name_rank' => $name,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        throw new RuntimeException("RESORT_NOT_FOUND country={$countryId} name={$name}", 3);
    }
    if (count($rows) !== 1) {
        throw new RuntimeException("RESORT_AMBIGUOUS country={$countryId} name={$name} matches=" . count($rows), 4);
    }

    $result = $rows[0];
    $result['country_id'] = (int)$result['country_id'];
    $result['region_id'] = (int)$result['region_id'];
    $result['subregion_id'] = (int)$result['subregion_id'];
    return $result;
}

function resolver_list(PDO $pdo, int $countryId): array
{
    $sql = "SELECT
                s.id AS subregion_id,
                s.name AS subregion_name,
                s.slug AS subregion_slug,
                r.id AS region_id,
                r.name AS region_name,
                r.slug AS region_slug,
                r.country_id
            FROM catalog_subregions s
            INNER JOIN catalog_regions r ON r.id = s.region_id
            WHERE r.country_id = :country_id
              AND s.is_active = 1
              AND r.is_active = 1
            ORDER BY r.name ASC, s.name ASC, s.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['country_id' => $countryId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['country_id'] = (int)$row['country_id'];
        $row['region_id'] = (int)$row['region_id'];
        $row['subregion_id'] = (int)$row['subregion_id'];
    }
    unset($row);
    return $rows;
}

$countryRaw = resolver_arg($argv, 'country');
$countryId = filter_var($countryRaw, FILTER_VALIDATE_INT);
$listMode = resolver_flag($argv, 'list');
$names = resolver_names($argv);

if ($countryId === false || (int)$countryId <= 0 || (!$listMode && $names === [])) {
    fwrite(STDERR, "Usage: php v2/data/resolve-resort-id-v1.php --country=4 --name=Кемер\n");
    fwrite(STDERR, "   or: php v2/data/resolve-resort-id-v1.php --country=4 --names=Кемер,Анталья,Сиде,Белек,Аланья\n");
    fwrite(STDERR, "   or: php v2/data/resolve-resort-id-v1.php --country=4 --list\n");
    exit(2);
}

$pdo = v2_data_db();
if ($listMode) {
    echo json_encode(resolver_list($pdo, (int)$countryId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

$results = [];
try {
    foreach ($names as $name) {
        $results[] = resolver_lookup($pdo, (int)$countryId, $name);
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(in_array($e->getCode(), [3, 4], true) ? $e->getCode() : 1);
}

$output = count($results) === 1 ? $results[0] : $results;
echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
