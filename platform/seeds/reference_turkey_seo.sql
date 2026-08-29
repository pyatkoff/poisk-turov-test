-- SEO registry entries for the Turkey reference vertical slice.
-- Starts noindex by design; eligibility is evaluated separately.

SET @turkey_id := (SELECT id FROM at_entities WHERE entity_key = 'country:turkey' LIMIT 1);
SET @antalya_id := (SELECT id FROM at_entities WHERE entity_key = 'resort:turkey:antalya' LIMIT 1);

INSERT INTO at_seo_pages (
  page_type, route_key, url_path, primary_entity_type, primary_entity_id,
  template_code, index_status, canonical_path, quality_score, priority_score
)
VALUES
  (
    'country', 'country:turkey', '/country/turkey/', 'country', @turkey_id,
    'country', 'noindex', '/country/turkey/', 90, 100
  ),
  (
    'resort', 'resort:turkey:antalya', '/country/turkey/antalya/', 'resort', @antalya_id,
    'resort', 'noindex', '/country/turkey/antalya/', 90, 95
  )
ON DUPLICATE KEY UPDATE
  primary_entity_id = VALUES(primary_entity_id),
  template_code = VALUES(template_code),
  index_status = VALUES(index_status),
  canonical_path = VALUES(canonical_path),
  quality_score = VALUES(quality_score),
  priority_score = VALUES(priority_score);

SET @turkey_page_id := (SELECT id FROM at_seo_pages WHERE route_key = 'country:turkey' LIMIT 1);
SET @antalya_page_id := (SELECT id FROM at_seo_pages WHERE route_key = 'resort:turkey:antalya' LIMIT 1);

INSERT INTO at_seo_page_entities (seo_page_id, entity_type, entity_id, role, sort_order)
VALUES
  (@turkey_page_id, 'country', @turkey_id, 'country', 10),
  (@antalya_page_id, 'country', @turkey_id, 'country', 10),
  (@antalya_page_id, 'resort', @antalya_id, 'resort', 20)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);
