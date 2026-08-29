-- AnyTour Site Platform v1: core content/entity schema
-- Target: MariaDB/MySQL, utf8mb4

CREATE TABLE IF NOT EXISTS at_entities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_key VARCHAR(255) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  name VARCHAR(255) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  sort_order INT NOT NULL DEFAULT 0,
  data_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entity_key (entity_key),
  KEY idx_entity_parent (parent_id),
  KEY idx_entity_type_slug_parent (entity_type, slug, parent_id),
  KEY idx_entity_type_status (entity_type, status),
  CONSTRAINT fk_entity_parent FOREIGN KEY (parent_id) REFERENCES at_entities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_entity_external_ids (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL,
  external_id VARCHAR(190) NOT NULL,
  external_type VARCHAR(80) NULL,
  data_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_external (provider, external_type, external_id),
  UNIQUE KEY uq_entity_provider_type (entity_id, provider, external_type),
  CONSTRAINT fk_external_entity FOREIGN KEY (entity_id) REFERENCES at_entities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_entity_relations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_entity_id BIGINT UNSIGNED NOT NULL,
  to_entity_id BIGINT UNSIGNED NOT NULL,
  relation_type VARCHAR(60) NOT NULL,
  weight DECIMAL(10,4) NOT NULL DEFAULT 0,
  data_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entity_relation (from_entity_id, to_entity_id, relation_type),
  KEY idx_relation_from_type (from_entity_id, relation_type),
  KEY idx_relation_to_type (to_entity_id, relation_type),
  CONSTRAINT fk_relation_from FOREIGN KEY (from_entity_id) REFERENCES at_entities(id) ON DELETE CASCADE,
  CONSTRAINT fk_relation_to FOREIGN KEY (to_entity_id) REFERENCES at_entities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_page_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(190) NOT NULL,
  version INT NOT NULL DEFAULT 1,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  config_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_template_code_version (code, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_content_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_type VARCHAR(40) NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  block_type VARCHAR(60) NOT NULL,
  block_key VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  source ENUM('generated','manual') NOT NULL DEFAULT 'generated',
  content_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_owner_block_key (owner_type, owner_id, block_key),
  KEY idx_owner_blocks (owner_type, owner_id, enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_page_overrides (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_type VARCHAR(40) NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  field_key VARCHAR(100) NOT NULL,
  value_text MEDIUMTEXT NULL,
  value_json JSON NULL,
  updated_by VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_owner_override (owner_type, owner_id, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
