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
| `/poisk-turov-test/v2/` | `/poisk-turov/` | LIVE_NEW | Full Tourvisor search, results, tour detail and lead flow; live deploy/search/lead checks are green. |
| current site home | `/` | LIVE_NEW | New full homepage with compact search that hands parameters to `/poisk-turov/`. |
| `/country/` | `/country/` | LIVE_NEW | Country directory is live in the new shell; individual country pages remain legacy until migrated. |
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
| `/hot/` | `/hot/` | LIVE_NEW | New fast-deal landing is live; live Tourvisor-powered deal blocks are a follow-up enhancement. |
| `/rb/` | `/rb/` | LEGACY_ONLY | Early booking. |
| `/contacts/` | `/contacts/` | LIVE_NEW | Contacts page is live and passes production content smoke. |
| `/how-to-buy/` | `/how-to-buy/` | LIVE_NEW | Buying / online booking explanation is live and passes production content smoke. |
| `/personal/` | `/personal/` or reviewed external target | LEGACY_ONLY | Preserve working personal-account destination; do not break auth. |

## Legal / trust routes

| Current route | New route | Status | Notes |
| --- | --- | --- | --- |
| `/politika-konfidentsialnosti/` | `/politika-konfidentsialnosti/` | LEGACY_ONLY | Must be migrated after legal details are reconciled; current legacy sources contain conflicting company-address data. |
| `/personal-data/` | `/personal-data/` | LEGACY_ONLY | Keep legacy until legal text is verified. |
| `/payment/` | `/payment/` | LEGACY_ONLY | Keep legacy until payment/legal content is verified. |

## SEO expansion after parity

These are new pages, not replacements for existing ones: resort pages, hotel pages, departure-city pages, country × departure intersections, seasonal pages and curated hotel/tour collections. They come **after** parity-critical current pages above are accounted for.

## Migration rules

1. Do not remove or repoint a legacy route until its new destination is live and checked.
2. Preserve query/attribution parameters through redirects where applicable.
3. Keep search/lead/Metrika behavior unchanged during content-page migration.
4. New pages receive unique title/H1/description/canonical and are added to sitemap only when index-ready.
5. Old misspelled public routes are treated as compatibility URLs, never silently discarded.
6. Before opening indexing, crawl the old public site/sitemap and append any route family missing from this inventory.
