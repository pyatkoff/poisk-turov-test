# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` remains the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records whether the former refactor-first phase is active, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## North-star — ANYTOUR 2.0 → SITE 9.0/10

The project is not finished when the shared shell or Design System 1.0 is finished. The standing product objective is to turn the current search foundation into a coherent AnyTour 2.0 travel product and raise the whole production site to a real, evidence-based 9.0/10 level.

Design System 1.0 is the current stage inside this roadmap, not the final destination.

Emergency priority always overrides roadmap order:

`production_broken → lead_loss → incorrect_data → broken_user_journey → AnyTour_2_0_roadmap → technical_refactor → cosmetic_cleanup`

## AnyTour 2.0 roadmap

### Stage 1 — Foundation and architecture
Keep one understandable source of truth for shared shell, runtime state and product primitives. Remove confirmed duplication and historical drift only where doing so improves reliability, performance or future delivery. Preserve the mature search core while simplifying its surroundings.

### Stage 2 — Design 2.0 / unified product
Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/`, representative country/resort pages, results and selected-tour surfaces feel like one premium product. Establish one visual language for typography, grid, spacing, buttons, cards, breadcrumbs, imagery, states and navigation. Validate 375/430/768/1024/1440.

### Stage 3 — Search Experience 2.0
Deliver a simple first search step with advanced controls only when useful; fast local-first catalogs where safe; strong dates/duration/tourists UX; hotel-first discovery; reliable recovery; minimal unnecessary actions. Preserve the Tourvisor search contract and search lifecycle.

### Stage 4 — Results 2.0
Improve result hierarchy, cards, filters, sorting, hotel-to-tour grouping, flexible-date savings, flight/meal/room/operator/fuel/total-price clarity and mobile filtering. Add evidence-based relevance explanations such as “Почему подходит вам” only when supported by real data.

### Stage 5 — Hotel / Tour 2.0
Create a complete selected-hotel/tour experience: gallery, rating, location, features, meals, rooms, flights, operator, tour variants, total price, strong CTA and a purpose-built mobile composition rather than a compressed desktop layout.

### Stage 6 — Price Intelligence
Build on `tour_price_observations` and daily aggregates to support comparable-tour price context, good-price signals, current low-price calendar and later price watch/history. Tourvisor remains the source of current bookable tour data; AnyTour owns the accumulated analytical layer. Never invent historical claims without sufficient observations.

### Stage 7 — Conversion 2.0
Strengthen the path Search → Results → Tour → Lead. Develop the scenarios “Найти самому” and “Помочь выбрать”, manager assistance, trust/proof, “Нашли дешевле?”, saved/recoverable intent and later personalization where evidence supports it. Preserve the external lead-sending contract unless the owner explicitly approves a change.

### Stage 8 — Data Platform
Continue the local catalog/data foundation: departures, countries, regions, subregions, hotels, aliases/search indexes, availability relations where needed, price observations/aggregates, hot-tour snapshots and SEO offer snapshots. Localize Tourvisor catalogs only when the local model preserves required availability semantics and has a safe fallback.

### Stage 9 — SEO Platform
Build scalable destination architecture around real data and useful intent: country → resort → month → holiday type/party/meal/stars → hotel. Add internal linking, canonical/schema/sitemap foundations and live offer/price snapshots. Do not create thin or fabricated SEO pages.

### Stage 10 — Merchandising / discovery
Turn hot tours, early booking, popular destinations, seasonal collections and month/departure-city collections into coherent live storefronts driven by real data and the same AnyTour 2.0 visual system.

### Stage 11 — Mobile 2.0
Perform a dedicated mobile product pass across home → search → results → filters → calendar → hotel/tour → lead at 375/430, including loading/empty/error/populated states and sticky/fixed interactions. Mobile quality is not considered complete merely because desktop CSS is responsive.

### Stage 12 — Performance and reliability
Reduce avoidable request fan-out, use safe local catalogs/caching/lazy loading, protect first render, preserve fallback behavior, improve production observability and keep regression coverage focused on real user and revenue paths.

### Stage 13 — Final 9.0 audit
Re-score the whole production product from evidence across visual quality, UX, mobile, search, results, selected tour, conversion, SEO foundation, performance, accessibility, reliability and maintainability. Continue fixing the weakest dimensions until the whole-site score is credibly at least 9.0/10; do not infer the site score from the stronger search-only score.

## Current active stage — Design 2.0 / Design System foundation

The immediate work remains site-wide visual unification because it is the current prerequisite for the larger AnyTour 2.0 roadmap.

1. Confirm the merged homepage slice on production, including live smoke and five-width evidence.
2. Audit the complete production journey home → country/destination → hot/search → results → selected tour → lead at 375/430/768/1024/1440.
3. Fix confirmed hierarchy, spacing, wrapping, overflow and shell inconsistencies, starting with representative country/destination pages and weak editorial surfaces.
4. Continue adoption of shared primitives where legacy page-specific surfaces visibly diverge.
5. Move from shell consistency into the deeper Design 2.0 treatment of search/results/hotel/mobile surfaces without destabilizing their working behavior.
6. Keep the next roadmap stage explicit so completing Design System work automatically advances to Search Experience 2.0 rather than ending the autopilot.

## Latest material progress

- Homepage discovery now follows the quick search directly: country/destination, hot tours, early booking and full search are reachable before explanatory benefit content.
- Homepage route-specific surfaces consume shared brand/surface/radius/spacing/focus tokens through a narrow alignment layer.
- The five-width standalone content/navigation sweeps are green across core public pages with stable shared header/footer geometry and no horizontal overflow.
- Production catalog sync confirms 71/71 departure rows have `name_genitive`; the canonical departure schema and migration are aligned.
- Local catalog work provides a foundation for departures, destinations and hotels while preserving Tourvisor fallback/search contracts.
- Search form parameters, Tourvisor, Metrika/analytics and lead contracts were not changed.

## 9.0 quality gate

A stage is not DONE because code merged. Relevant evidence should include implementation, narrow automated checks, real functional verification, production verification and visual/responsive evidence for user-facing work. The 9.0 target is whole-product quality, not a commit count or isolated Lighthouse/CI score.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- unresolved legal/payment content.

Preserve verified social/app destinations and the AnyTour logo. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe. Ignore stale/conflicting priority restoration work such as PR #433 unless the owner explicitly changes direction.

## Execution policy

At the start of each run inspect current `main`, open PRs, fresh CI/deploy results, production behavior and the machine-readable resume point. Work in narrow independent slices. SAFE changes may merge after narrow relevant checks; MEDIUM user-facing changes require focused regression evidence plus relevant broader CI before release. If a stage is blocked, record/defer the blocker and continue another independent item that advances AnyTour 2.0. After completing a stage, advance to the next roadmap stage automatically unless an emergency priority overrides it.
