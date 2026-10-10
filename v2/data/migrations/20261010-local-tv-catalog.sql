-- Additive LOCAL replacement. No old IDs, MATCH decisions or offer FKs are changed.
-- Install separately, before opting the existing daily collector into scope=local.
CREATE TABLE IF NOT EXISTS local_tv_hotels (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    pending_since DATETIME NOT NULL,
    state VARCHAR(24) NOT NULL DEFAULT 'pending',
    discovery_json LONGTEXT NOT NULL,
    content_json LONGTEXT NOT NULL,
    manual_json LONGTEXT NOT NULL,
    source_json LONGTEXT DEFAULT NULL,
    source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    source_fetched_at DATETIME DEFAULT NULL,
    source_absent_json LONGTEXT NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    next_attempt_at DATETIME DEFAULT NULL,
    last_error VARCHAR(1000) DEFAULT NULL,
    KEY ix_local_tv_backlog (state, next_attempt_at, pending_since, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS local_tv_legacy_links (
    old_local_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    tv_id INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    snapshot_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    migration_issues_json LONGTEXT NOT NULL,
    migrated_at DATETIME NOT NULL,
    KEY ix_local_tv_old_reference (tv_id),
    CONSTRAINT fk_local_tv_link FOREIGN KEY (tv_id)
        REFERENCES local_tv_hotels(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
