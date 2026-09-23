-- Append-only canonical offer price observations for future Search3 price history.
-- Source-only migration: runtime activation is separate and must be explicitly controlled.
-- Requires the canonical AnyTour hotel catalogue first.

CREATE TABLE IF NOT EXISTS anytour_offer_price_observations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    observation_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    provider_local_hotel_id BIGINT UNSIGNED NOT NULL,
    scope_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    search_ref_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    offer_ref_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider_hotel_ref_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    consumer_segment_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    departure_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    checkin DATE NOT NULL,
    nights TINYINT UNSIGNED NOT NULL,
    adults TINYINT UNSIGNED NOT NULL,
    children TINYINT UNSIGNED NOT NULL,
    child_ages_json VARCHAR(96) NOT NULL,
    operator_json LONGTEXT NOT NULL,
    operator_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    meal_json LONGTEXT NOT NULL,
    meal_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    room_json LONGTEXT NOT NULL,
    room_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    display_price DECIMAL(14,2) NOT NULL,
    currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    final_price_ready TINYINT UNSIGNED NOT NULL,
    final_price_verified TINYINT UNSIGNED NOT NULL,
    observed_at DATETIME NOT NULL,
    recorded_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_anytour_offer_price_observation (observation_sha256),
    KEY ix_anytour_offer_price_exact_offer (provider, offer_ref_digest, observed_at),
    KEY ix_anytour_offer_price_consumer (consumer_segment_sha256, observed_at),
    KEY ix_anytour_offer_price_hotel (anytour_hotel_id, checkin, observed_at),
    KEY ix_anytour_offer_price_scope (scope_sha256, observed_at),
    CONSTRAINT fk_anytour_offer_price_hotel FOREIGN KEY (anytour_hotel_id)
        REFERENCES anytour_hotels (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
