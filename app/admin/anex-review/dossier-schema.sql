-- Apply only through an approved CLI migration. No changes to mapping/staging/catalog tables.
CREATE TABLE IF NOT EXISTS anex_review_dossier_batches (
    artifact_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    source_sha CHAR(40) CHARACTER SET ascii NOT NULL,
    source_digest CHAR(64) CHARACTER SET ascii NOT NULL,
    checkpoint_digest CHAR(64) CHARACTER SET ascii NOT NULL,
    row_count INT UNSIGNED NOT NULL,
    stored_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS anex_review_dossiers (
    artifact_id BIGINT UNSIGNED NOT NULL,
    anex_hotel_id INT UNSIGNED NOT NULL,
    row_digest CHAR(64) CHARACTER SET ascii NOT NULL,
    evidence_digest CHAR(64) CHARACTER SET ascii NOT NULL,
    status VARCHAR(32) NOT NULL,
    row_json MEDIUMTEXT NOT NULL,
    evidence_json MEDIUMTEXT NOT NULL,
    PRIMARY KEY (artifact_id, anex_hotel_id),
    KEY dossier_hotel (anex_hotel_id, artifact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
