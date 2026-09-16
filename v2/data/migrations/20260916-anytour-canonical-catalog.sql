-- Explicit additive migration only. Never run inside the seed transaction.
-- No existing source catalogue, mapping or runtime table is altered.
CREATE TABLE IF NOT EXISTS anytour_catalog_control (
    singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    schema_version INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO anytour_catalog_control (singleton_id, schema_version) VALUES (1, 1);

CREATE TABLE IF NOT EXISTS anytour_hotels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_json LONGTEXT NOT NULL,
    profile_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS anytour_hotel_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    external_key VARBINARY(128) NOT NULL,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    acquired_via VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_json LONGTEXT NOT NULL,
    source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    UNIQUE KEY uq_anytour_hotel_source (namespace, external_key),
    KEY ix_anytour_hotel_source_target (anytour_hotel_id),
    CONSTRAINT fk_anytour_hotel_source_target FOREIGN KEY (anytour_hotel_id)
        REFERENCES anytour_hotels (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
