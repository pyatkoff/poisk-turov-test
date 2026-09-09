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
| `/country/` | `/country/` | LIVE_NEW | Country directory is live in the new shell. |
| `/country/turkey/` | `/country/turkey/` | LIVE_NEW | Local Turkey landing; CTA hands off authoritative Tourvisor country `4`. |
| `/country/egypt/` | `/country/egypt/` | LIVE_NEW | Local Egypt landing; CTA hands off authoritative Tourvisor country `1`. |
| `/country/tailand/` | `/country/tailand/` | LIVE_NEW | Compatibility spelling preserved locally; CTA hands off authoritative Tourvisor country `2`. Canonical rename may be reviewed later. |
| `/country/oae/` | `/country/oae/` | LIVE_NEW | Compatibility route preserved locally; CTA hands off authoritative Tourvisor country `9`. Canonical rename may be reviewed later. |
| `/country/russia/` | `/country/russia/` | LIVE_NEW | Local Russia landing; CTA hands off authoritative Tourvisor country `47`. |
| `/country/tunis/` | `/country/tunis/` | LIVE_NEW | Local Tunisia landing is implemented in the shared country shell; until an authoritative Tourvisor country id is mapped, CTA falls back to general search. |
| `/country/vetnam/` | `/country/vetnam/` | LIVE_NEW | Compatibility spelling is implemented locally; canonical rename may be reviewed later. Until an authoritative Tourvisor country id is mapped, CTA falls back to general search. |
| `/country/dominikana/` | `/country/dominikana/` | LIVE_NEW | Compatibility route is implemented locally; canonical naming may be reviewed later. Until an authoritative Tourvisor country id is mapped, CTA falls back to general search. |
| `/country/cyprus/` | `/country/cyprus/` | LIVE_NEW | Local Cyprus landing is implemented in the shared country shell; CTA falls back to general search until authoritative Tourvisor mapping is added. |
| `/country/cuba/` | `/country/cuba/` | LIVE_NEW | Local Cuba landing is implemented in the shared country shell; CTA falls back to general search until authoritative Tourvisor mapping is added. |
| `/country/maldives/` | `/country/maldives/` | LIVE_NEW | Local Maldives landing is implemented in the shared country shell; CTA falls back to general search until authoritative Tourvisor mapping is added. |
| `/country/mexico/` | `/country/mexico/` | LIVE_NEW | Local Mexico landing is implemented in the shared country shell; CTA falls back to general search until authoritative Tourvisor mapping is added. |
| `/country/sri-lanka/` | `/country/sri-lanka/` | LIVE_NEW | Local Sri Lanka landing is implemented in the shared country shell; CTA falls back to general search until authoritative Tourvisor mapping is added. |
| `/country/tanzania/` | `/country/tanzania/` | LIVE_NEW | Local Tanzania landing is implemented in the shared country shell; CTA falls back to general search until authoritative Tourvisor mapping is added. |
| `/hot/` | `/hot/` | LIVE_NEW | New fast-deal landing is live; live Tourvisor-powered deal blocks are a follow-up enhancement. |
| `/rb/` | `/rb/` | LIVE_NEW | Early-booking landing is live and included in standalone content smoke. |
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
