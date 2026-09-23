-- SEARCH hotel meal memberships: reviewed hotel-concept->global-plan relation.
-- Additive source contract only. Requires anytour_meal_plans and hotel-stay V2 meal concepts.
-- Equal labels/local keys never imply membership and no rows are seeded automatically.
CREATE TABLE anytour_search_meal_memberships_v1 (
    anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    meal_concept_id BIGINT UNSIGNED NOT NULL,
    meal_concept_revision BIGINT UNSIGNED NOT NULL,
    meal_plan_id BIGINT UNSIGNED NULL,
    state ENUM('pending','accepted','rejected','conflict') NOT NULL,
    evidence_ref VARCHAR(255) NOT NULL,
    evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewed_by VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (anytour_hotel_id,meal_concept_id),
    CONSTRAINT fk_search_meal_membership_concept FOREIGN KEY (anytour_hotel_id,meal_concept_id) REFERENCES anytour_hotel_meal_concepts_v2(anytour_hotel_id,id),
    CONSTRAINT fk_search_meal_membership_plan FOREIGN KEY (meal_plan_id) REFERENCES anytour_meal_plans(id),
    CONSTRAINT ck_search_meal_membership_state CHECK ((state='accepted' AND meal_plan_id IS NOT NULL) OR (state<>'accepted' AND meal_plan_id IS NULL)),
    CONSTRAINT ck_search_meal_membership_evidence CHECK (CHAR_LENGTH(evidence_ref)>0 AND CHAR_LENGTH(reviewed_by)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
