-- Additive P2 schema. Apply through an approved CLI migration, never a web GET.
-- Existing observations/staging/mappings/decisions and catalog_hotels are not altered.
CREATE TABLE IF NOT EXISTS anex_review_state (
    anex_hotel_id INT UNSIGNED NOT NULL PRIMARY KEY,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
    deferred_at DATETIME NULL,
    deferred_by VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anex_review_pair_exclusions (
    anex_hotel_id INT UNSIGNED NOT NULL,
    catalog_hotel_id INT UNSIGNED NOT NULL,
    decided_by VARCHAR(255) NOT NULL,
    decided_at DATETIME NOT NULL,
    evidence_digest CHAR(64) CHARACTER SET ascii NOT NULL,
    PRIMARY KEY (anex_hotel_id, catalog_hotel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anex_review_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    request_digest CHAR(64) CHARACTER SET ascii NOT NULL,
    anex_hotel_id INT UNSIGNED NOT NULL,
    catalog_hotel_id INT UNSIGNED NULL,
    action VARCHAR(24) NOT NULL,
    actor VARCHAR(255) NOT NULL,
    evidence_digest CHAR(64) CHARACTER SET ascii NOT NULL,
    before_json MEDIUMTEXT NOT NULL,
    result_json MEDIUMTEXT NOT NULL,
    decided_at DATETIME NOT NULL,
    KEY idx_anex_review_history (anex_hotel_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
