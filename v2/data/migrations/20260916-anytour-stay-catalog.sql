-- Additive, explicit installation AFTER the independent hotel schema (#2666).
-- Never execute at HTTP/runtime startup. DDL is not a rollbackable transaction.
-- Deliberately refuse an existing/partial installation; inspect it instead of replaying.
CREATE TABLE anytour_meal_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    name_ru VARCHAR(255) NOT NULL,
    family_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    qualifiers_json LONGTEXT NOT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT ck_anytour_meal_json CHECK (JSON_VALID(qualifiers_json)),
    CONSTRAINT ck_anytour_meal_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- These are AnyTour display categories, NOT supplier-code mappings or hotel concepts.
INSERT INTO anytour_meal_plans (code,name_ru,family_code,qualifiers_json) VALUES
('no-meals','Без питания','no-meals','{}'),
('breakfast','Завтраки','breakfast','{}'),
('half-board','Полупансион','half-board','{}'),
('half-board-plus','Полупансион плюс','half-board','{"variant":"plus"}'),
('full-board','Полный пансион','full-board','{}'),
('full-board-plus','Полный пансион плюс','full-board','{"variant":"plus"}'),
('all-inclusive','Всё включено','all-inclusive','{}'),
('ultra-all-inclusive','Ультра всё включено','all-inclusive','{"variant":"ultra"}'),
('soft-all-inclusive','Мягкое всё включено','all-inclusive','{"variant":"soft"}'),
('alcohol-free-all-inclusive','Всё включено без алкоголя','all-inclusive','{"variant":"alcohol-free"}');

CREATE TABLE anytour_room_categories (
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    name_ru VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
INSERT INTO anytour_room_categories (code,name_ru) VALUES
('standard','Стандарт'),('economy','Эконом'),('promo','Промо'),
('superior','Улучшенный'),('family','Семейный'),('deluxe','Делюкс'),
('junior-suite','Полулюкс'),('suite','Люкс'),('villa','Вилла'),('apartment','Апартаменты');

CREATE TABLE anytour_hotel_rooms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    local_key VARBINARY(128) NOT NULL,
    name_ru VARCHAR(255) NOT NULL,
    category_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    facts_json LONGTEXT NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_anytour_room_key (anytour_hotel_id,local_key),
    UNIQUE KEY uq_anytour_room_hotel_id (anytour_hotel_id,id),
    CONSTRAINT fk_anytour_room_hotel FOREIGN KEY (anytour_hotel_id) REFERENCES anytour_hotels(id),
    CONSTRAINT fk_anytour_room_category FOREIGN KEY (category_code) REFERENCES anytour_room_categories(code),
    CONSTRAINT ck_anytour_room_json CHECK (JSON_VALID(facts_json)),
    CONSTRAINT ck_anytour_room_active CHECK (is_active IN (0,1)),
    CONSTRAINT ck_anytour_room_revision CHECK (revision>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE anytour_stay_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    external_hotel_key VARBINARY(128) NOT NULL,
    operator_key VARBINARY(128) NOT NULL,
    kind ENUM('room','meal') NOT NULL,
    key_kind ENUM('code','label') NOT NULL,
    external_key VARBINARY(512) NOT NULL,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NULL,
    meal_id BIGINT UNSIGNED NULL,
    state ENUM('pending','accepted','rejected','conflict') NOT NULL,
    evidence_ref VARCHAR(255) NOT NULL,
    evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewed_by VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_anytour_stay_source (namespace,external_hotel_key,operator_key,kind,key_kind,external_key),
    CONSTRAINT fk_anytour_stay_source FOREIGN KEY (namespace,external_hotel_key)
        REFERENCES anytour_hotel_sources(namespace,external_key),
    CONSTRAINT fk_anytour_stay_hotel FOREIGN KEY (anytour_hotel_id) REFERENCES anytour_hotels(id),
    CONSTRAINT fk_anytour_stay_room FOREIGN KEY (anytour_hotel_id,room_id)
        REFERENCES anytour_hotel_rooms(anytour_hotel_id,id),
    CONSTRAINT fk_anytour_stay_meal FOREIGN KEY (meal_id) REFERENCES anytour_meal_plans(id),
    CONSTRAINT ck_anytour_stay_target CHECK (
        (state='accepted' AND ((kind='room' AND room_id IS NOT NULL AND meal_id IS NULL)
            OR (kind='meal' AND meal_id IS NOT NULL AND room_id IS NULL)))
        OR (state<>'accepted' AND room_id IS NULL AND meal_id IS NULL)),
    CONSTRAINT ck_anytour_stay_keys CHECK (
        OCTET_LENGTH(operator_key)>0 AND OCTET_LENGTH(external_key)>0
        AND CHAR_LENGTH(evidence_ref)>0 AND CHAR_LENGTH(reviewed_by)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
