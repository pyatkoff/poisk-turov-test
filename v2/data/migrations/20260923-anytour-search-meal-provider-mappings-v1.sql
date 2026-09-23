-- SEARCH meal provider mappings: independent global provider->canonical-plan relation.
-- Additive source contract only. Requires existing anytour_meal_plans.
-- No hotel-scoped V2 table is required and no rows are seeded automatically.
CREATE TABLE anytour_search_meal_provider_mappings_v1 (
    provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_key VARBINARY(128) NOT NULL,
    external_id VARBINARY(128) NOT NULL,
    meal_plan_id BIGINT UNSIGNED NULL,
    state ENUM('pending','accepted','rejected','conflict') NOT NULL,
    evidence_ref VARCHAR(255) NOT NULL,
    evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewed_by VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (provider,scope_key,external_id),
    KEY ix_search_meal_plan (provider,scope_key,meal_plan_id),
    CONSTRAINT fk_search_meal_plan FOREIGN KEY (meal_plan_id) REFERENCES anytour_meal_plans(id),
    CONSTRAINT ck_search_meal_provider CHECK (provider IN ('tourvisor','anex','andromeda')),
    CONSTRAINT ck_search_meal_state CHECK ((state='accepted' AND meal_plan_id IS NOT NULL) OR (state<>'accepted' AND meal_plan_id IS NULL)),
    CONSTRAINT ck_search_meal_keys CHECK (OCTET_LENGTH(scope_key)>0 AND OCTET_LENGTH(external_id)>0 AND CHAR_LENGTH(evidence_ref)>0 AND CHAR_LENGTH(reviewed_by)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
