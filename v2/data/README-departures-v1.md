# Departures catalog v1

`catalog_departures` stores Tourvisor departure cities for first-party lookup and SEO/product copy.

Fields:
- `id` — Tourvisor departure id.
- `name` — canonical city name used as safe fallback.
- `name_genitive` — optional genitive form for phrases such as `туры из Калининграда`.
- `slug` — stable URL-friendly city slug.
- `is_active` — whether Tourvisor currently exposes at least one destination country for this departure city.
- `synced_at`, `updated_at` — freshness metadata.

## Active-city rule

Tourvisor `/departures` is a directory and can contain cities that are valid identifiers but currently have no package-tour destinations. The sync therefore does not expose every directory row to the search form.

For every Russian departure returned by `/departures?departureCountryId=1`, `sync-departures-v1.php` checks `/countries?departureId=<id>`. A city is active only when that catalog request returns at least one valid country. These are catalog requests, not `/tours/search` starts, so the availability refresh does not consume the paid/daily search-start quota.

The whole availability set is resolved before the DB transaction starts. If Tourvisor connectivity fails midway or the result would contain zero active cities, the sync aborts and keeps the previous production catalog instead of blanking the search form.

`departures-v1.php` serves only `is_active = 1` rows. `catalogs-v2.js` already prefers that local endpoint, so a successful sync automatically reduces the visible `Вылет из` selector to currently useful cities while the complete reference set remains stored in the database.

The sync never invents grammatical forms. It stores Tourvisor-provided genitive variants when present and otherwise leaves `name_genitive` null; callers must fall back to `name`.
