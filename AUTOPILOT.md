# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize the search user journey and user-facing visual layer. Treat the public site as one product across homepage → country/destination → hot/search → results → selected tour → lead; do not use search-only engineering quality as the whole-site score.

## Ordered work

1. Preserve and improve the mature search flow: full-search filters, results, hotel/tour cards and selected-tour UX.
2. Converge shared header/navigation/footer and broader site visuals using AnyTour Design System 2.0 without replacing the AnyTour logo.
3. Keep `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and country pages visually coherent through shared shell primitives rather than route-local overrides.
4. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/results/comparison/flight/price/fuel/lead regressions.
6. Keep technical refactor, unresolved legal/payment content and PR #254 deferred until separately authorized/proven safe.

## Material progress / current resume point

PR #606 is verified in production. Its shared site-coherence layer hardened homepage/editorial geometry, min-width/overflow handling, long-copy wrapping, balanced grids, section rhythm, breadcrumbs and narrow-screen CTA behavior. Production deploy checks passed public-page verification, the unchanged lead bridge and live search smoke.

PR #610 converged the remaining shared editorial/country first screen onto AnyTour Design System 2.0. `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and country pages consume the same light, compact visual language as the homepage and tour-search intro. PR validation passed standalone navigation/content, V2 pull-request/startup/branch checks, security and visual suites; production deployment passed public-page verification, unchanged lead-bridge verification and live search smoke.

PR #612 fixed a correctness bug in mobile result filters. Applying/removing mobile filters writes only touched controls into the canonical search form and starts one fresh search through `V2SearchLifecycle`; stars, rating, max price and meal mapping remain tied to canonical values. Production V2 and standalone verification passed.

PR #614 tightened the desktop AnyTour Design System 2.0 advanced-filter rail without changing search semantics. Desktop/tablet hierarchy, spacing, wrapping, control sizing and overflow behavior are stable and production-verified.

PR #617 rebuilt the factual `Лидер продаж` signal on fresh main. It includes all 500 source rows, matches conservatively by Tourvisor result country + normalized hotel name, exposes only the customer-facing badge, and does not reorder results or alter price/search semantics. All PR regressions passed and both production deployment paths completed successfully.

PR #618 converged selected tour → flight choice → lead form on AnyTour Design System 2.0 through a final responsive visual layer rather than changing product behavior. It resolves historical CSS conflicts, long-value wrapping/min-width issues and inconsistent price/facts/flight/lead geometry across 375/430/768/1024/1440. The initial CI run correctly rejected the changed bundle contract; the bundle manifest validator was updated to assert the new final DS2 layer and all 21 PR checks then passed, including selected-tour and whole-V2 visual suites. V2 production deployment passed Verify V2 and Live search smoke; standalone deployment passed public-page and unchanged lead-bridge verification and is the final production gate for this slice.

Next: complete the production/live visual verification for PR #618 if still running, then move to the lowest-scoring public route: homepage section hierarchy/discovery flow. Audit the homepage at 375/430/768/1024/1440, make only confirmed DS2 hierarchy/spacing/wrapping fixes, then continue `/hot/` and the editorial routes. Keep search behavior, contracts and already-green selected-tour regressions unchanged.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

## Decision rule

Prefer narrow shared-shell or search-UX changes with broad user benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe slice.
