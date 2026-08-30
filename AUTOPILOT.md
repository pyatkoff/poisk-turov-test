# poisk-turov-test — Autopilot State

Updated: 2026-08-30 11:03 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point, `ARCHITECTURE.md` remains the canonical architecture source of truth, `TEST_MATRIX.md` owns CI/test policy, and `PRODUCT_ROADMAP.md` owns the broader product roadmap.

## Current phase — DESIGN SYSTEM 1.0 / SITE-WIDE VISUAL UNIFICATION

The owner's current explicit direction makes **AnyTour Design System 1.0 and whole-site visual unification Priority #1**. The mature tour-search flow is the quality reference, not the score of the entire public site. Do not use search-only engineering maturity to inflate the whole-site coherence score.

Priority order:

`production broken → lead loss → incorrect data → broken user journey → Design System/site-wide visual coherence → supporting technical refactor → content/SEO → cosmetic cleanup`

Technical refactor remains useful when it directly makes Design System migration safer, but it is no longer the planned phase ahead of UX/visual work.

## Current Design System baseline

- PRs #325/#326 established shared editorial rhythm and canonical content-gap/readable-measure tokens.
- PR #330 adds a shared final content primitive layer for homepage + shell-based editorial pages: section heading/copy geometry, card/surface treatment and primary/secondary action geometry now have one cross-page source of truth.
- Search/results/comparison/selected-tour/room/flight/price/fuel/lead behavior remains protected and unchanged by this Design System slice.
- Required user-facing evidence remains **375 / 430 / 768 / 1024 / 1440**.

Whole-site coherence starts from the owner's current approximately **6.5/10** baseline. Search itself remains materially stronger. Raise the site-wide score only after representative production visual evidence.

## Exact next work order

1. Verify PR #330 production deployment plus post-deploy five-width visual evidence.
2. Audit one shared header/navigation across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages; fix confirmed wrapping/geometry/active-state drift.
3. Audit the shared footer/community block across those page families; fix density, spacing, wrapping or overflow inconsistencies before cosmetic additions.
4. Continue shared typography, grid/section spacing, cards, buttons and breadcrumbs wherever page-specific layers still drift from the Design System.
5. Re-audit representative country/hot pages against homepage and mature search language.
6. Validate the full visual journey: homepage → destination/hot → search → results → selected tour → lead.
7. Use technical refactor slices only where they remove a concrete blocker or after the visual coherence gate is materially stronger.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or events;
- analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve verified social/app destinations. Do not redesign or replace the AnyTour logo. Legal/payment migration and PR #254 remain deferred. Full `/poisk-turov/` shared-header component replacement also remains deferred until an atomic swap has equivalent browser coverage; compatibility-layer visual alignment is acceptable meanwhile.

## Execution policy

Work through multiple independent SAFE visual/UX tasks per session while time allows. Fix confirmed spacing, wrapping, overflow, duplicate-shell, hierarchy and responsive problems before decorative flourishes. For each user-facing slice, run focused checks, preserve the mature search/lead regression suite, validate 375/430/768/1024/1440, deploy only after relevant checks are green, and inspect post-deploy live evidence before treating the slice as DONE.
