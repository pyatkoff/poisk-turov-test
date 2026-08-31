CREATE TABLE IF NOT EXISTS catalog_departure_countries_direct (
    departure_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    synced_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (departure_id, country_id),
    KEY idx_country_active (country_id, is_active),
    KEY idx_departure_active (departure_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_departure_countries_charter LIKE catalog_departure_countries_direct;
CREATE TABLE IF NOT EXISTS catalog_departure_countries_direct_charter LIKE catalog_departure_countries_direct;
