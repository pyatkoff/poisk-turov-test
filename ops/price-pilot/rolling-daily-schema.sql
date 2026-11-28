CREATE TABLE IF NOT EXISTS tour_price_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    price_date DATE NOT NULL,
    departure_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    region_id INT UNSIGNED DEFAULT NULL,
    hotel_id INT UNSIGNED NOT NULL,
    departure_year SMALLINT UNSIGNED NOT NULL,
    departure_month TINYINT UNSIGNED NOT NULL,
    nights TINYINT UNSIGNED NOT NULL,
    adults TINYINT UNSIGNED NOT NULL,
    children_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    child_ages_signature VARCHAR(40) NOT NULL DEFAULT '',
    meal_id INT UNSIGNED DEFAULT NULL,
    currency CHAR(8) NOT NULL DEFAULT 'RUB',
    min_price DECIMAL(12,2) NOT NULL,
    median_price DECIMAL(12,2) NOT NULL,
    max_price DECIMAL(12,2) NOT NULL,
    observation_count INT UNSIGNED NOT NULL,
    calculated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_price_daily_segment (
        price_date, departure_id, hotel_id, departure_year, departure_month,
        nights, adults, children_count, child_ages_signature, meal_id, currency
    ),
    KEY idx_price_daily_hotel (hotel_id, price_date),
    KEY idx_price_daily_destination_month (country_id, region_id, departure_year, departure_month, price_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
