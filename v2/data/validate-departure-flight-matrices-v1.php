<?php
/** Validate persisted direct/charter departure-country matrices without Tourvisor calls. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';

$pdo = v2_data_db();
$kinds = ['direct', 'charter', 'direct_charter'];
$stats = [];
try {
    foreach ($kinds as $kind) {
        $table = 'catalog_departure_countries_' . $kind;
        $row = $pdo->query("SELECT COUNT(*) pairs, COUNT(DISTINCT departure_id) cities FROM {$table} WHERE is_active=1")->fetch(PDO::FETCH_ASSOC);
        $stats[$kind] = ['pairs'=>(int)($row['pairs'] ?? 0), 'cities'=>(int)($row['cities'] ?? 0)];
        if ($stats[$kind]['pairs'] <= 0 || $stats[$kind]['cities'] <= 0) throw new RuntimeException("{$kind} matrix is empty");
    }
    printf("DEPARTURE_FLIGHT_MATRICES_VALIDATION_OK direct_cities=%d direct_pairs=%d charter_cities=%d charter_pairs=%d direct_charter_cities=%d direct_charter_pairs=%d\n",
        $stats['direct']['cities'], $stats['direct']['pairs'],
        $stats['charter']['cities'], $stats['charter']['pairs'],
        $stats['direct_charter']['cities'], $stats['direct_charter']['pairs']
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'DEPARTURE_FLIGHT_MATRICES_VALIDATION_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
