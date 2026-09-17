-- AnyTour offer-store v2: preserve the previous completed snapshot while a new refresh is running.
-- Requires the empty/verified v1 store installed by LOCAL #2693 P1 before any customer offers existed.
-- This migration does not copy or synthesize offers and performs no supplier I/O.
ALTER TABLE anytour_offers
    DROP INDEX uq_anytour_offer_identity,
    ADD UNIQUE KEY uq_anytour_offer_refresh_identity
        (provider, scope_sha256, last_refresh_token, identity_sha256);

UPDATE anytour_offer_store_control
SET schema_version = 2
WHERE singleton_id = 1 AND schema_version = 1;
