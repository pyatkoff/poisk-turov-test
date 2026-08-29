-- AnyTour SEO Platform v1: page registry / redirects / SEO state
-- Target: MariaDB/MySQL, utf8mb4

CREATE TABLE IF NOT EXISTS at_seo_pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_type VARCHAR(60) NOT NULL,
  route_key VARCHAR(255) NOT NULL,
  url_path VARCHAR(500) NOT NULL,
  primary_entity_type VARCHAR(40) NULL,
  primary_entity_id BIGINT UNSIGNED NULL,
  context_json JSON NULL,
  template_code VARCHAR(80) NOT NULL,
  index_status ENUM('draft','noindex','index','redirect','gone') NOT NULL DEFAULT 'draft',
  canonical_path VARCHAR(500) NULL,
  title_template VARCHAR(500) NULL,
  description_template TEXT NULL,
  h1_template VARCHAR(500) NULL,
  manual_title VARCHAR(500) NULL,
  manual_description TEXT NULL,
  manual_h1 VARCHAR(500) NULL,
  manual_canonical_path VARCHAR(500) NULL,
  schema_json JSON NULL,
  quality_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  quality_json JSON NULL,
  priority_score DECIMAL(10,4) NOT NULL DEFAULT 0,
  last_inventory_seen_at DATETIME NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_url_path (url_path),
  UNIQUE KEY uq_seo_route_key (route_key),
  KEY idx_seo_type_status (page_type, index_status),
  KEY idx_seo_entity (primary_entity_type, primary_entity_id),
  KEY idx_seo_index_priority (index_status, priority_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_seo_page_entities (
  seo_page_id BIGINT UNSIGNED NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(60) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (seo_page_id, entity_type, entity_id, role),
  KEY idx_page_entity_lookup (entity_type, entity_id, role),
  CONSTRAINT fk_page_entity_page FOREIGN KEY (seo_page_id) REFERENCES at_seo_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_seo_redirects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_path VARCHAR(500) NOT NULL,
  to_path VARCHAR(500) NULL,
  http_status SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  reason VARCHAR(255) NULL,
  preserve_query TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirect_from (from_path),
  KEY idx_redirect_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_seo_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_page_id BIGINT UNSIGNED NOT NULL,
  to_page_id BIGINT UNSIGNED NOT NULL,
  link_type VARCHAR(60) NOT NULL,
  anchor_text VARCHAR(500) NULL,
  weight DECIMAL(10,4) NOT NULL DEFAULT 0,
  source ENUM('generated','manual') NOT NULL DEFAULT 'generated',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_link (from_page_id, to_page_id, link_type),
  KEY idx_link_from (from_page_id, active, weight),
  KEY idx_link_to (to_page_id, active),
  CONSTRAINT fk_link_from FOREIGN KEY (from_page_id) REFERENCES at_seo_pages(id) ON DELETE CASCADE,
  CONSTRAINT fk_link_to FOREIGN KEY (to_page_id) REFERENCES at_seo_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS at_seo_checks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seo_page_id BIGINT UNSIGNED NOT NULL,
  check_code VARCHAR(100) NOT NULL,
  status ENUM('pass','warn','fail') NOT NULL,
  score SMALLINT NOT NULL DEFAULT 0,
  details_json JSON NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_check_page_time (seo_page_id, checked_at),
  KEY idx_check_status (status, check_code),
  CONSTRAINT fk_check_page FOREIGN KEY (seo_page_id) REFERENCES at_seo_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
