-- Additive metadata for conservative cross-scope DB-first reuse.
-- Does not alter existing offers, refresh state, identity mappings, or supplier data.
CREATE TABLE IF NOT EXISTS anytour_offer_scopes (
    scope_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    scope_version TINYINT UNSIGNED NOT NULL,
    family_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    params_json LONGTEXT NOT NULL,
    params_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    KEY ix_anytour_offer_scope_family_seen (family_sha256, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
