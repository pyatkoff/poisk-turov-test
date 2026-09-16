-- Additive provider-neutral customer-offer store for the independent AnyTour catalogue.
-- Requires 20260916-anytour-canonical-catalog.sql first. This migration does not copy data,
-- call suppliers, or alter existing catalogue/mapping/search tables.
CREATE TABLE IF NOT EXISTS anytour_offer_store_control (
    singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    schema_version INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO anytour_offer_store_control (singleton_id, schema_version) VALUES (1, 1);

CREATE TABLE IF NOT EXISTS anytour_offer_refreshes (
    refresh_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    started_at DATETIME NOT NULL,
    lease_expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    KEY ix_anytour_offer_refresh_scope (provider, scope_sha256, started_at),
    KEY ix_anytour_offer_refresh_status (status, lease_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS anytour_offer_scope_state (
    provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    active_refresh_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    latest_complete_refresh_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (provider, scope_sha256),
    KEY ix_anytour_offer_scope_active (active_refresh_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS anytour_offers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    legacy_hotel_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    search_ref_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    offer_ref_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider_hotel_ref_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    identity_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    operator_json LONGTEXT NOT NULL,
    operator_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    checkin DATE NOT NULL,
    nights TINYINT UNSIGNED NOT NULL,
    adults TINYINT UNSIGNED NOT NULL,
    children TINYINT UNSIGNED NOT NULL,
    child_ages_json VARCHAR(96) NOT NULL,
    party_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    meal_json LONGTEXT NOT NULL,
    room_json LONGTEXT NOT NULL,
    placement_json LONGTEXT NOT NULL,
    display_price DECIMAL(14,2) NOT NULL,
    currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    final_price_ready TINYINT UNSIGNED NOT NULL,
    final_price_verified TINYINT UNSIGNED NOT NULL,
    payload_json LONGTEXT NOT NULL,
    payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    observed_at DATETIME NOT NULL,
    source_context_expires_at DATETIME NOT NULL,
    last_refresh_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_anytour_offer_identity (provider, scope_sha256, identity_sha256),
    KEY ix_anytour_offer_scope_price (scope_sha256, is_active, expires_at, display_price),
    KEY ix_anytour_offer_hotel (anytour_hotel_id, is_active, expires_at),
    KEY ix_anytour_offer_provider_refresh (provider, scope_sha256, last_refresh_token, is_active),
    CONSTRAINT fk_anytour_offer_hotel FOREIGN KEY (anytour_hotel_id)
        REFERENCES anytour_hotels (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
