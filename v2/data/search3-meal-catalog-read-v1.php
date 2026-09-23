<?php
/** Read-only canonical Search3 meal plans and reviewed Tourvisor native IDs. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/anytour-search-meal-catalog-v1.php';

function search3_meal_catalog_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $catalogue = (new AnyTourSearchMealCatalogV1(v2_data_db()))->catalogue('tourvisor', 'global');
    $plans = [];
    foreach (($catalogue['plans'] ?? []) as $plan) {
        $id = $plan['id'] ?? null;
        $code = $plan['code'] ?? null;
        $name = $plan['nameRu'] ?? null;
        $native = $plan['nativeIds'] ?? null;
        if (!is_int($id) || $id < 1 || !is_string($code) || !preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/D', $code)
            || !is_string($name) || trim($name) === '' || strlen($name) > 255 || !is_array($native)) {
            throw new RuntimeException('SEARCH_MEAL_PUBLIC_SHAPE');
        }
        $ids = [];
        foreach ($native as $value) {
            if (!is_string($value) || trim($value) === '' || strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new RuntimeException('SEARCH_MEAL_PUBLIC_NATIVE');
            }
            $ids[$value] = true;
        }
        $plans[] = ['id' => $id, 'code' => $code, 'nameRu' => trim($name), 'nativeIds' => array_keys($ids)];
    }
    search3_meal_catalog_out([
        'ok' => true,
        'source' => AnyTourSearchMealCatalogV1::SOURCE,
        'provider' => 'tourvisor',
        'scopeKey' => 'global',
        'available' => ($catalogue['available'] ?? false) === true,
        'revision' => is_string($catalogue['revision'] ?? null) ? $catalogue['revision'] : null,
        'plans' => $plans,
    ]);
} catch (Throwable $e) {
    error_log('search3-meal-catalog-read-v1: ' . $e->getMessage());
    search3_meal_catalog_out(['ok' => false, 'error' => 'Meal catalogue is temporarily unavailable'], 503);
}
