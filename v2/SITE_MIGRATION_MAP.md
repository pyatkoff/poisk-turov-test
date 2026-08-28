# AnyTour → anytoour.ru migration map

This file is the required migration checklist for the current public site. A legacy route must not be silently dropped: it must become either a new first-class page on `anytoour.ru`, an intentional redirect, or an explicitly retired route with rationale.

## Status legend

- `LIVE_NEW` — new-domain page is implemented and verified.
- `BUILDING` — implementation is in progress.
- `LEGACY_ONLY` — current page exists only in the old site/navigation and still needs migration.
- `REDIRECT_LATER` — keep old route until the replacement is live, then add a reviewed 301.

## Product routes

| Current / intended route | New route | Status | Notes |
| --- | --- | --- | --- |
| `/poisk-turov-test/v2/` | `/poisk-turov/` | BUILDING | Full Tourvisor search, results, tour detail and lead flow. |
| current site home | `/` | BUILDING | New full homepage with compact search that hands parameters to `/poisk-turov/`. |
| `/country/` | `/country/` | LEGACY_ONLY | Country directory, new design + SEO landing architecture. |
| `/country/turkey/` | `/country/turkey/` | LEGACY_ONLY | Turkey landing; later resort children. |
| `/country/egypt/` | `/country/egypt/` | LEGACY_ONLY | Egypt landing. |
| `/country/tailand/` | reviewed canonical route | LEGACY_ONLY | Preserve old typo route with redirect after canonical naming is chosen. |
| `/country/oae/` | reviewed canonical route | LEGACY_ONLY | Preserve old route with redirect after canonical naming is chosen. |
| `/country/russia/` | `/country/russia/` | LEGACY_ONLY | Russia landing. |
| `/country/tunis/` | `/country/tunis/` | LEGACY_ONLY | Tunisia landing. |
| `/country/vetnam/` | reviewed canonical route | LEGACY_ONLY | Preserve old typo route with redirect after canonical naming is chosen. |
| `/country/dominikana/` | reviewed canonical route | LEGACY_ONLY | Dominican Republic landing. |
| `/country/cyprus/` | `/country/cyprus/` | LEGACY_ONLY | Cyprus landing. |
| `/country/cuba/` | `/country/cuba/` | LEGACY_ONLY | Cuba landing. |
| `/country/maldives/` | `/country/maldives/` | LEGACY_ONLY | Maldives landing. |
| `/country/mexico/` | `/country/mexico/` | LEGACY_ONLY | Mexico landing. |
| `/country/sri-lanka/` | `/country/sri-lanka/` | LEGACY_ONLY | Sri Lanka landing. |
| `/country/tanzania/` | `/country/tanzania/` | LEGACY_ONLY | Tanzania landing. |
| `/hot/` | `/hot/` | LEGACY_ONLY | Hot tours / fast-deal landing. |
| `/rb/` | `/rb/` | LEGACY_ONLY | Early booking. |
| `/contacts/` | `/contacts/` | LEGACY_ONLY | Contacts + lead entry points. |
| `/how-to-buy/` | `/how-to-buy/` | LEGACY_ONLY | Buying / online booking explanation. |
| `/personal/` | `/personal/` or reviewed external target | LEGACY_ONLY | Preserve working personal-account destination; do not break auth. |

## Legal / trust routes

| Current route | New route | Status | Notes |
| --- | --- | --- | --- |
| `/politika-konfidentsialnosti/` | `/politika-konfidentsialnosti/` | LEGACY_ONLY | Must be migrated before switching lead-form privacy links to the new domain. |

## SEO expansion after parity

These are new pages, not replacements for existing ones: resort pages, hotel pages, departure-city pages, country × departure intersections, seasonal pages and curated hotel/tour collections. They come **after** parity-critical current pages above are accounted for.

## Migration rules

1. Do not remove or repoint a legacy route until its new destination is live and checked.
2. Preserve query/attribution parameters through redirects where applicable.
3. Keep search/lead/Metrika behavior unchanged during content-page migration.
4. New pages receive unique title/H1/description/canonical and are added to sitemap only when index-ready.
5. Old misspelled public routes are treated as compatibility URLs, never silently discarded.
6. Before opening indexing, crawl the old public site/sitemap and append any route family missing from this inventory.
