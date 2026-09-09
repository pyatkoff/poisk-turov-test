<?php
/** Create state table for hotel/resort/month Tourvisor collection. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';
$pdo = v2_data_db();
$pdo->exec("CREATE TABLE IF NOT EXISTS tour_matrix_collection_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  criterion ENUM('hotel_batch','resort','month') NOT NULL,
  target_key VARCHAR(255) NOT NULL,
  departure_id INT UNSIGNED NOT NULL,
  country_id INT UNSIGNED NOT NULL,
  region_id INT UNSIGNED DEFAULT NULL,
  hotel_ids_json JSON DEFAULT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  nights_from TINYINT UNSIGNED NOT NULL DEFAULT 5,
  nights_to TINYINT UNSIGNED NOT NULL DEFAULT 14,
  search_id BIGINT UNSIGNED DEFAULT NULL,
  status ENUM('started','success','empty','timeout','failure') NOT NULL DEFAULT 'started',
  rows_received INT UNSIGNED NOT NULL DEFAULT 0,
  observations_written INT UNSIGNED NOT NULL DEFAULT 0,
  error_text VARCHAR(1000) DEFAULT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_matrix_target (criterion,target_key,started_at),
  KEY idx_matrix_pair (departure_id,country_id,criterion,started_at),
  KEY idx_matrix_status (status,started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "MATRIX_COLLECTION_SCHEMA_OK\n";
