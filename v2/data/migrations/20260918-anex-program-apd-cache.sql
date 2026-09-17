-- INT-owned direct-ANEX program registry and AdditionalPricesDaily rate cache.
-- Additive only: no supplier I/O, no backfill, no changes to anytour_offers.
CREATE TABLE IF NOT EXISTS anytour_anex_programs (
    supplier_program_id BIGINT UNSIGNED NOT NULL,
    departure_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    supplier_currency_id BIGINT UNSIGNED NOT NULL,
    flight_class VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    first_departure_date DATE NOT NULL,
    last_departure_date DATE NOT NULL,
    observation_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (supplier_program_id, departure_id, country_id, supplier_currency_id),
    KEY ix_anex_program_country_flight_seen (departure_id, country_id, flight_class, last_seen_at),
    KEY ix_anex_program_country_departure (country_id, last_departure_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS anytour_anex_program_contexts (
    supplier_program_id BIGINT UNSIGNED NOT NULL,
    departure_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    supplier_currency_id BIGINT UNSIGNED NOT NULL,
    date_beg DATE NOT NULL,
    nights TINYINT UNSIGNED NOT NULL,
    flight_class VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    observation_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (supplier_program_id, departure_id, country_id, supplier_currency_id, date_beg, nights),
    KEY ix_anex_context_prewarm (departure_id, country_id, flight_class, date_beg, last_seen_at),
    KEY ix_anex_context_program_seen (supplier_program_id, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS anytour_anex_apd_rates (
    supplier_program_id BIGINT UNSIGNED NOT NULL,
    date_beg DATE NOT NULL,
    nights TINYINT UNSIGNED NOT NULL,
    supplier_currency_id BIGINT UNSIGNED NOT NULL,
    context_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    apd_state VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    total_count INT UNSIGNED NOT NULL,
    row_count SMALLINT UNSIGNED NOT NULL,
    price_adult DECIMAL(14,4) NULL,
    price_child DECIMAL(14,4) NULL,
    cashrate DECIMAL(18,8) NULL,
    price_converted_adult DECIMAL(14,4) NULL,
    price_converted_child DECIMAL(14,4) NULL,
    observed_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    refresh_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (supplier_program_id, date_beg, nights, supplier_currency_id),
    UNIQUE KEY uq_anex_apd_context_digest (context_sha256),
    KEY ix_anex_apd_freshness (apd_state, expires_at),
    KEY ix_anex_apd_date (date_beg, supplier_program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
