# Departures catalog v1

`catalog_departures` stores Tourvisor departure cities for first-party lookup and SEO/product copy.

Fields:
- `id` — Tourvisor departure id.
- `name` — canonical city name used as safe fallback.
- `name_genitive` — optional genitive form for phrases such as `туры из Калининграда`.
- `slug` — stable URL-friendly city slug.
- `is_active` — current catalog availability flag.
- `synced_at`, `updated_at` — freshness metadata.

The sync never invents grammatical forms. It stores Tourvisor-provided genitive variants when present and otherwise leaves `name_genitive` null; callers must fall back to `name`.
