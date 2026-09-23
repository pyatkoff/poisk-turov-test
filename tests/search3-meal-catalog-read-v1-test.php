<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../v2/data/search3-meal-catalog-read-v1.php');
if (!is_string($source)) throw new RuntimeException('meal read source unavailable');
$checks = 0;
$check = static function (bool $ok, string $name) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('SEARCH3_MEAL_READ_TEST:' . $name);
};

$check(str_contains($source, "catalogue('tourvisor', 'global')"), 'exact Tourvisor global authority');
$check(str_contains($source, "'source' => AnyTourSearchMealCatalogV1::SOURCE"), 'source contract');
$check(str_contains($source, "'nativeIds' => array_keys(\$ids)"), 'native IDs exposed');
$check(str_contains($source, "'id' => \$id") && str_contains($source, "'code' => \$code") && str_contains($source, "'nameRu' => trim(\$name)"), 'canonical plan identity exposed');
$check(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i', preg_replace('/\/\*.*?\*\/|\/\/.*$/ms', '', $source)), 'endpoint contains no SQL mutation');
$check(!str_contains($source, '$_POST') && !str_contains($source, '$_REQUEST'), 'no mutable request payload');
$check(str_contains($source, "'available' => (\$catalogue['available'] ?? false) === true"), 'authority availability preserved');
$check(str_contains($source, "'revision' =>"), 'revision preserved');

echo "SEARCH3_MEAL_CATALOG_READ_OK checks={$checks} writes=0 provider=tourvisor scope=global\n";
