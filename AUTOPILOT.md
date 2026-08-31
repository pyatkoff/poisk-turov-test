# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize the search user journey and user-facing visual layer. Treat the public site as one product across homepage → country/destination → hot/search → results → selected tour → lead; do not use search-only engineering quality as the whole-site score.

## Ordered work

1. Preserve and improve the mature search flow: full-search filters, results, hotel/tour cards and selected-tour UX.
2. Converge shared header/navigation/footer and broader site visuals using AnyTour Design System 2.0 without replacing the AnyTour logo.
3. Keep `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages visually coherent through shared shell primitives rather than route-local overrides.
4. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/results/comparison/flight/price/fuel/lead regressions.
6. Keep technical refactor, unresolved legal/payment content and PR #254 deferred until separately authorized/proven safe.

## Material progress / current resume point

PR #606 is verified in production. Its shared site-coherence layer hardened homepage/editorial geometry, min-width/overflow handling, long-copy wrapping, balanced grids, section rhythm, breadcrumbs and narrow-screen CTA behavior. Production deploy checks passed public-page verification, the unchanged lead bridge and live search smoke.

PR #610 converged the remaining shared editorial/country first screen onto AnyTour Design System 2.0. `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and country pages now consume the same light, compact visual language as the homepage and tour-search intro instead of the older heavy dark hero. PR validation passed standalone navigation/content, V2 pull-request/startup/branch checks, security, visual standalone content, V2 baseline, V2 PR visual and selected-tour visual suites. Production deployment passed public-page verification, unchanged lead-bridge verification and live search smoke.

PR #612 fixed a correctness bug in mobile result filters. The mobile sheet no longer hides/shows only the currently rendered cards and presents that subset as the filtered search. Applying/removing mobile filters now writes only the touched controls into the canonical search form and starts one fresh search through `V2SearchLifecycle`. Stars, rating and max price map to existing canonical fields. `Всё включено` is resolved from the Tourvisor meal catalog by label instead of a guessed ID. The draft + `Показать` interaction is preserved, and reset/applied state remains tied to canonical form values. PR checks passed V2 pull request, startup/branch bundles, security, standalone validation, V2 baseline, PR visual and selected-tour visual suites. Production V2 deployment passed Verify V2 + Live search smoke; full anytoour deployment also passed public-page verification, unchanged lead bridge and live search smoke.

PR #614 tightened the desktop AnyTour Design System 2.0 advanced-filter rail without changing search semantics. At desktop widths the advanced-filter summary, field grid, direct/charter controls, service section and reset action now share stable DS2 spacing, hierarchy, wrapping and control geometry; 821–1120 uses a three-column grid and wider desktop uses four columns, with min-width/overflow guards on long labels and values. All eight PR checks completed successfully, including V2 and standalone visual suites. Both the V2-only and full anytoour production deploys passed verification; the full deploy also passed public-page checks, the unchanged lead bridge and live-search smoke.

The stale sales-leader PR #604 was reviewed and closed rather than merged across the newer DS2/mobile/desktop filter work. Tourvisor's current official search-result schema confirms each returned hotel exposes both `country` and `name`, so conservative country + normalized-name matching remains a valid implementation path. A fresh-main rebuild has been started separately; it must not reorder the price-driven result list or expose the upstream source brand.

Next: finish the fresh-main AnyTour `Лидер продаж` badge rebuild, preserving the current DS2/filter stack. Validate matcher accuracy, API enrichment, stale-badge cleanup and responsive card visuals before merge. After that, continue results/card and selected-tour visual convergence under DS2.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

## Decision rule

Prefer narrow shared-shell or search-UX changes with broad user benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe slice.