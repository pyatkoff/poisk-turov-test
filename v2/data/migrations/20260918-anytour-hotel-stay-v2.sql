-- LOCAL V2: authoritative room/meal concepts exist only inside one AnyTour hotel.
-- Additive source contract only. Existing global stay tables remain transitional compatibility.
-- Never execute automatically at HTTP/runtime startup.
CREATE TABLE anytour_hotel_room_concepts_v2 (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    local_key VARBINARY(128) NOT NULL,
    name_ru VARCHAR(255) NOT NULL,
    facts_json LONGTEXT NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_anytour_hotel_room_concept_v2_key (anytour_hotel_id, local_key),
    UNIQUE KEY uq_anytour_hotel_room_concept_v2_pair (anytour_hotel_id, id),
    CONSTRAINT fk_anytour_hotel_room_concept_v2_hotel FOREIGN KEY (anytour_hotel_id)
        REFERENCES anytour_hotels(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_anytour_hotel_room_concept_v2_json CHECK (JSON_VALID(facts_json)),
    CONSTRAINT ck_anytour_hotel_room_concept_v2_active CHECK (is_active IN (0,1)),
    CONSTRAINT ck_anytour_hotel_room_concept_v2_revision CHECK (revision > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE anytour_hotel_meal_concepts_v2 (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    local_key VARBINARY(128) NOT NULL,
    name_ru VARCHAR(255) NOT NULL,
    facts_json LONGTEXT NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_anytour_hotel_meal_concept_v2_key (anytour_hotel_id, local_key),
    UNIQUE KEY uq_anytour_hotel_meal_concept_v2_pair (anytour_hotel_id, id),
    CONSTRAINT fk_anytour_hotel_meal_concept_v2_hotel FOREIGN KEY (anytour_hotel_id)
        REFERENCES anytour_hotels(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_anytour_hotel_meal_concept_v2_json CHECK (JSON_VALID(facts_json)),
    CONSTRAINT ck_anytour_hotel_meal_concept_v2_active CHECK (is_active IN (0,1)),
    CONSTRAINT ck_anytour_hotel_meal_concept_v2_revision CHECK (revision > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE anytour_hotel_stay_mappings_v2 (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    external_hotel_key VARBINARY(128) NOT NULL,
    operator_key VARBINARY(128) NOT NULL,
    kind ENUM('room','meal') NOT NULL,
    key_kind ENUM('code','label') NOT NULL,
    external_key VARBINARY(512) NOT NULL,
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    room_concept_id BIGINT UNSIGNED NULL,
    meal_concept_id BIGINT UNSIGNED NULL,
    state ENUM('pending','accepted','rejected','conflict') NOT NULL,
    evidence_ref VARCHAR(255) NOT NULL,
    evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewed_by VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_anytour_hotel_stay_mapping_v2_source
        (namespace, external_hotel_key, operator_key, kind, key_kind, external_key),
    CONSTRAINT fk_anytour_hotel_stay_mapping_v2_source
        FOREIGN KEY (namespace, external_hotel_key)
        REFERENCES anytour_hotel_sources(namespace, external_key)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_anytour_hotel_stay_mapping_v2_hotel
        FOREIGN KEY (anytour_hotel_id) REFERENCES anytour_hotels(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_anytour_hotel_stay_mapping_v2_room
        FOREIGN KEY (anytour_hotel_id, room_concept_id)
        REFERENCES anytour_hotel_room_concepts_v2(anytour_hotel_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_anytour_hotel_stay_mapping_v2_meal
        FOREIGN KEY (anytour_hotel_id, meal_concept_id)
        REFERENCES anytour_hotel_meal_concepts_v2(anytour_hotel_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_anytour_hotel_stay_mapping_v2_target CHECK (
        (state='accepted' AND (
            (kind='room' AND room_concept_id IS NOT NULL AND meal_concept_id IS NULL)
            OR
            (kind='meal' AND meal_concept_id IS NOT NULL AND room_concept_id IS NULL)
        ))
        OR
        (state<>'accepted' AND room_concept_id IS NULL AND meal_concept_id IS NULL)
    ),
    CONSTRAINT ck_anytour_hotel_stay_mapping_v2_nonempty CHECK (
        OCTET_LENGTH(operator_key)>0
        AND OCTET_LENGTH(external_key)>0
        AND CHAR_LENGTH(evidence_ref)>0
        AND CHAR_LENGTH(reviewed_by)>0
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
