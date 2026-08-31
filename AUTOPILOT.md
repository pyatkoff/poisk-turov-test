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

PR #610 then converged the remaining shared editorial/country first screen onto AnyTour Design System 2.0. `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and country pages now consume the same light, compact visual language as the homepage and tour-search intro instead of the older heavy dark hero. The change is shared-shell CSS only: route content, search/results state, Tourvisor requests, analytics, lead contract, logo and destinations are unchanged. PR validation passed standalone navigation/content, V2 pull-request/startup/branch checks, security, visual standalone content, V2 baseline, V2 PR visual and selected-tour visual suites. Production deployment for merge `713698916d5240c4dbec6c67730dcc5ace3789ed` passed public-page verification, unchanged lead-bridge verification and live search smoke.

Next: correct mobile result filters so Apply updates the canonical search form and starts one fresh full Tourvisor search rather than treating the currently rendered result subset as authoritative. Preserve the existing mobile draft + `Показать` interaction, map rating/stars/price to canonical fields, resolve the all-inclusive meal through the loaded Tourvisor meal catalog rather than a guessed ID, and keep reset/applied state coherent. After that, visually tighten the desktop filter rail at 1024/1440 and re-evaluate the stale sales-leader PR on fresh main.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

## Decision rule

Prefer narrow shared-shell or search-UX changes with broad user benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe slice.
