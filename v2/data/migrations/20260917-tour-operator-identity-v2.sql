-- Tourvisor passive identity observation v2.
-- One-shot migration. Apply only through the guarded MATCH migration operation.
-- The existing table is append/enrichment evidence, not mapping authority.

ALTER TABLE tour_operator_identity_observations
    MODIFY tour_id VARCHAR(220) NULL,
    MODIFY operator_link VARCHAR(2048) NULL,
    MODIFY operator_link_host VARCHAR(255) NULL,
    MODIFY operator_link_path VARCHAR(1200) NULL,
    MODIFY operator_link_query VARCHAR(1200) NULL,
    ADD COLUMN native_id_type VARCHAR(40) NULL AFTER operator_link_query,
    ADD COLUMN native_id_value VARCHAR(255) NULL AFTER native_id_type,
    ADD COLUMN native_id_conflict TINYINT(1) NOT NULL DEFAULT 0 AFTER native_id_value,
    ADD UNIQUE KEY uq_operator_identity_hotel_operator (hotel_id,operator_id),
    ADD KEY idx_operator_identity_native (operator_id,native_id_type,native_id_value,last_seen_at);
