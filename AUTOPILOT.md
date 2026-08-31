# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize the search user journey and user-facing visual layer. Treat the public site as one product across homepage → country/destination → hot/search → results → selected tour → lead; do not use search-only engineering quality as the whole-site score.

## Ordered work

1. Preserve and improve the mature search flow: full-search filters, results, hotel/tour cards and selected-tour UX.
2. Converge shared header/navigation/footer and broader site visuals using the canonical owner-selected design system without replacing the AnyTour logo.
3. Keep `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages visually coherent through shared shell primitives rather than route-local overrides.
4. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/results/comparison/flight/price/fuel/lead regressions.
6. Keep technical refactor, unresolved legal/payment content and PR #254 deferred until separately authorized/proven safe.

## Material progress / current resume point

PR #606 merged a design-system-neutral shared site-coherence layer into `main`. Homepage and the existing editorial shell now share responsive geometry hardening for `min-width`/overflow, long-copy wrapping, balanced content grids, section rhythm, breadcrumbs and narrow-screen CTA wrapping. Country cards also use equal-height flex geometry so actions align despite uneven descriptions. The change intentionally does not touch search/results/recovery/comparison/flight/price/fuel/lead behavior or external contracts.

PR validation was green across standalone routes/navigation/content/home-search handoff, V2 pull-request validation, startup/branch bundles, security, visual standalone content, V2 baseline, V2 PR visuals and selected-tour visuals. Production deployment for merge `737a8b9b1f117a645068cb025461d5fcae7475da` must be verified before counting the visual score improvement as live.

Next: verify the deployment and live responsive behavior where accessible, then audit homepage/search shell parity and representative country pages for remaining route-specific hierarchy/spacing issues. Continue with full-search filter correctness and desktop/mobile consistency without weakening the strong search journey.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

## Decision rule

Prefer narrow shared-shell changes with broad visual benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe slice.
