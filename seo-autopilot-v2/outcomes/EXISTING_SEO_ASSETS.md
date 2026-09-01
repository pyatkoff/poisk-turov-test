# Existing SEO assets to adopt

SEO Autopilot 2.0 must reuse the existing SEO foundation instead of rebuilding it.

## Already implemented in the repository

### Page/content contracts
- `v2/seo-page-primitives-v1.php` — server-rendered breadcrumbs, editorial sections, related links and search handoff primitives.
- `v2/seo-page-contract-v1.php` — normalized SEO page contract for title, description, H1, intro, sections, related/internal links and search state.
- `v2/seo-page-types-v1.php` — country, resort and seasonal page adapters.
- `v2/seo-page-registry-v1.php` — curated clean-path registry; request/search state does not define page identity.
- `v2/seo-publishability-v1.php` — structural publication quality gate.
- `v2/seo-page-graph-v1.php` — registered parent/related-page graph for stable internal linking.
- `v2/seo-content-catalog-v1.php` — controlled editorial source with draft/review/approved states and publication candidates.
- `v2/seo-publication-manifest-v1.php` — route-independent manifest for approved + publishable editorial records.

### Offer/price data foundation
- `v2/data/build-seo-offer-snapshots-v1.php` — materializes SEO/feed snapshots from first-party price observations for country, resort, month, resort+month and hotel dimensions.
- `v2/data/build-yandex-country-feeds-v1.php` — consumes fresh snapshots for Yandex country feeds.
- Existing collection/feed workflows already order price observations -> SEO snapshots -> feeds.

### Existing production SEO boundary
- Compatibility/legacy search route canonical work already exists and must be preserved rather than reimplemented.
- Search/Tourvisor/pricing/lead/Metrika mechanics remain protected from autonomous SEO changes.

## What SEO2 should do next

1. Verify which of the assets above are actually wired into current production and which are dormant foundation code.
2. Build a live/repo inventory of public country, resort, hotel and thematic routes.
3. Reuse the existing registry/content-catalog/publishability model as the publication pipeline.
4. Reuse SEO offer snapshots for factual price/availability blocks; never mix volatile price facts into static editorial copy.
5. Add only the missing public route/canonical/sitemap/schema/template integration needed for approved page families.
6. Publish in small controlled batches and measure indexation/landing quality before scale.

## Editorial SEO content is a first-class lane

SEO2 is responsible not only for technical metadata but also for useful page copy. Content should be authored per approved search intent and page family, not generated as generic keyword filler.

Recommended stable editorial structure for destination pages:
- distinct Title, Description and H1;
- short useful intro answering the page intent;
- 2–5 destination-specific sections chosen from: who the destination suits, resorts/areas, season/weather, beaches or sightseeing, family/romantic/active-trip fit, practical selection advice;
- curated links to relevant country/resort/hotel/seasonal pages;
- a clear handoff to the tour search with stable approved prefill parameters;
- optional factual current-offer block sourced separately from fresh SEO offer snapshots.

Content rules:
- do not auto-inflect place names when morphology is uncertain;
- do not invent prices, discounts, ratings, availability, guarantees or service claims;
- avoid city-swapping/template-spinning where only the destination name changes;
- every page must contain facts and advice that are meaningfully specific to that entity and intent;
- static text must remain valid when current tour prices change;
- price/offer/date facts belong to snapshot-driven components with freshness metadata;
- pages that lack enough unique intent/content/data stay draft and are not publication candidates.

## Immediate execution order

`SEO_INVENTORY` -> repo/live parity -> identify dormant vs production foundation -> choose first approved page family -> write real editorial records -> pass publishability gate -> wire public route/canonical/sitemap for a small pilot -> verify live -> expand only with evidence.
