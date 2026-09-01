<?php
/** Resolve a resort name against the synchronized Tourvisor catalog without guessing IDs. */
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

$countryRaw = resolver_arg($argv, 'country');
$name = resolver_arg($argv, 'name');
$countryId = filter_var($countryRaw, FILTER_VALIDATE_INT);

if ($countryId === false || (int)$countryId <= 0 || $name === null || $name === '') {
    fwrite(STDERR, "Usage: php v2/data/resolve-resort-id-v1.php --country=4 --name=Кемер\n");
    exit(2);
}

$pdo = v2_data_db();
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
    'country_id' => (int)$countryId,
    'name_exact' => $name,
    'name_slug' => v2_data_slug($name),
    'name_rank' => $name,
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    fwrite(STDERR, "RESORT_NOT_FOUND country=" . (int)$countryId . " name=" . $name . "\n");
    exit(3);
}

if (count($rows) !== 1) {
    fwrite(STDERR, "RESORT_AMBIGUOUS country=" . (int)$countryId . " name=" . $name . " matches=" . count($rows) . "\n");
    foreach ($rows as $row) {
        fwrite(STDERR, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }
    exit(4);
}

$result = $rows[0];
$result['country_id'] = (int)$result['country_id'];
$result['region_id'] = (int)$result['region_id'];
$result['subregion_id'] = (int)$result['subregion_id'];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
