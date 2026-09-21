-- Independent AnyTour destination identities. Explicit install only; never runtime DDL.
CREATE TABLE anytour_destinations_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('country','region','subregion') NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  name_ru VARCHAR(255) NOT NULL,
  slug VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_anytour_destination_slug (kind,parent_id,slug),
  KEY ix_anytour_destination_parent (parent_id,kind,is_active),
  CONSTRAINT fk_anytour_destination_parent FOREIGN KEY (parent_id) REFERENCES anytour_destinations_v1(id),
  CONSTRAINT ck_anytour_destination_active CHECK (is_active IN (0,1)),
  CONSTRAINT ck_anytour_destination_revision CHECK (revision>0),
  CONSTRAINT ck_anytour_destination_parent CHECK ((kind='country' AND parent_id IS NULL) OR (kind<>'country' AND parent_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE anytour_destination_sources_v1 (
  provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  kind ENUM('country','region','subregion') NOT NULL,
  external_id VARBINARY(128) NOT NULL,
  anytour_destination_id BIGINT UNSIGNED NULL,
  state ENUM('pending','accepted','rejected','conflict') NOT NULL,
  evidence_ref VARCHAR(255) NOT NULL,
  evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  reviewed_by VARCHAR(128) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (provider,kind,external_id),
  KEY ix_anytour_destination_reverse (anytour_destination_id,provider,kind,state),
  CONSTRAINT fk_anytour_destination_source_target FOREIGN KEY (anytour_destination_id) REFERENCES anytour_destinations_v1(id),
  CONSTRAINT ck_anytour_destination_provider CHECK (provider IN ('tourvisor','anex','samo','andromeda')),
  CONSTRAINT ck_anytour_destination_source_state CHECK ((state='accepted' AND anytour_destination_id IS NOT NULL) OR (state<>'accepted' AND anytour_destination_id IS NULL)),
  CONSTRAINT ck_anytour_destination_source_evidence CHECK (OCTET_LENGTH(external_id)>0 AND CHAR_LENGTH(evidence_ref)>0 AND CHAR_LENGTH(evidence_sha256)=64 AND CHAR_LENGTH(reviewed_by)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
