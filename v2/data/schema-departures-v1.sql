CREATE TABLE IF NOT EXISTS catalog_departures (
    id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    name_genitive VARCHAR(180) DEFAULT NULL,
    slug VARCHAR(200) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    synced_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_catalog_departures_slug (slug),
    KEY idx_catalog_departures_active_name (is_active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
