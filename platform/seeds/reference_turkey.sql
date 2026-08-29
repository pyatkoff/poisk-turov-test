-- Reference vertical slice for Site Platform development.
-- Safe to run repeatedly after 20260829_001_site_core.sql.

INSERT INTO at_entities (entity_key, entity_type, slug, name, parent_id, status, sort_order, data_json)
VALUES ('country:turkey', 'country', 'turkey', 'Турция', NULL, 'active', 10, JSON_OBJECT('name_prepositional', 'Турции'))
ON DUPLICATE KEY UPDATE name = VALUES(name), status = VALUES(status), sort_order = VALUES(sort_order), data_json = VALUES(data_json);

SET @turkey_id := (SELECT id FROM at_entities WHERE entity_key = 'country:turkey' LIMIT 1);

INSERT INTO at_entity_external_ids (entity_id, provider, external_id, external_type, data_json)
VALUES (@turkey_id, 'tourvisor', '4', 'country', NULL)
ON DUPLICATE KEY UPDATE entity_id = VALUES(entity_id), external_id = VALUES(external_id);

INSERT INTO at_entities (entity_key, entity_type, slug, name, parent_id, status, sort_order, data_json)
VALUES
  ('resort:turkey:antalya', 'resort', 'antalya', 'Анталья', @turkey_id, 'active', 10, JSON_OBJECT()),
  ('resort:turkey:belek', 'resort', 'belek', 'Белек', @turkey_id, 'active', 20, JSON_OBJECT()),
  ('resort:turkey:kemer', 'resort', 'kemer', 'Кемер', @turkey_id, 'active', 30, JSON_OBJECT()),
  ('resort:turkey:side', 'resort', 'side', 'Сиде', @turkey_id, 'active', 40, JSON_OBJECT())
ON DUPLICATE KEY UPDATE name = VALUES(name), parent_id = VALUES(parent_id), status = VALUES(status), sort_order = VALUES(sort_order);

INSERT INTO at_content_blocks (owner_type, owner_id, block_type, block_key, sort_order, enabled, source, content_json)
VALUES
  ('entity', @turkey_id, 'hero', 'hero', 10, 1, 'generated', JSON_OBJECT('eyebrow', 'Туры в Турцию', 'search_intent', JSON_OBJECT('country', 'turkey'))),
  ('entity', @turkey_id, 'resort_grid', 'popular_resorts', 20, 1, 'generated', JSON_OBJECT('title', 'Популярные курорты Турции')),
  ('entity', @turkey_id, 'live_tours', 'live_tours', 30, 1, 'generated', JSON_OBJECT('title', 'Актуальные туры в Турцию'))
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), enabled = VALUES(enabled), source = VALUES(source), content_json = VALUES(content_json);
