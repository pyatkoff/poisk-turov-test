# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0 / SITE-WIDE VISUAL UNIFICATION

The owner's latest explicit direction is Design System 1.0 first. Whole-site visual coherence is the active roadmap priority after emergency overrides. Do not substitute search-only engineering quality for public-site product quality.

Canonical priority after emergency overrides:

`ux_visual → technical_refactor → content_seo → cosmetic_cleanup`

Emergency/product safety still overrides this phase in the order defined by `AGENTS.md`: production broken → lead loss → incorrect data → broken visual/user journey. Only a new explicit owner direction may change `OWNER_PRIORITY.json`.

## Design System 1.0 objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one AnyTour product.
2. Keep one coherent header/navigation and one canonical footer while preserving the AnyTour logo and verified destinations.
3. Consolidate shared typography, spacing/grid, buttons, cards, breadcrumbs and responsive behavior into shared tokens/primitives.
4. Use the mature search experience as the visual-quality reference without making editorial pages unnecessarily dense.
5. Fix confirmed spacing, wrapping, overflow, duplicated shell, hierarchy and responsive defects before cosmetic flourishes.
6. Preserve existing search/recovery/results/comparison/flight/price/fuel/lead regressions and external contracts.

## Current progress

- Shared PHP footer ownership is consolidated across standalone/search consumers.
- Search navigation order/labels are aligned with the shared public header while preserving search behavior.
- Editorial CTA callout background regression on `/hot/` and `/rb/` is fixed and verified through CI/live visual workflow.
- Representative country related-destination cards now balance correctly at 561–768px via PR #369; PR gates were green before merge.
- Current production crawl confirms all target public routes are reachable; visual verification remains driven by the dedicated five-width browser workflows because text crawls cannot prove layout quality.
- Owner-priority drift introduced by #372 is corrected back to the latest explicit Design System 1.0 direction in the current branch.

## Exact next work order

1. Verify #369 post-merge production/live behavior and preserve the 375/430/768/1024/1440 visual gate.
2. Continue page-by-page audit of `/rb/`, `/hot/`, `/contacts/`, `/country/` and representative country pages for spacing, wrapping, hierarchy and shared-shell drift.
3. Move remaining weak editorial primitives onto shared Design System tokens/components where safe.
4. Recheck `/` and `/poisk-turov/` together so header/footer/navigation alignment remains coherent across the strongest and weakest surfaces.
5. Continue independent safe visual slices per run; merge/deploy only after relevant checks are green and verify live behavior after release.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified social/app destinations and mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Legal/payment migration remains deferred. PR #254 remains deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs, fresh CI/deploy state and the live site where accessible. Visual work must validate 375/430/768/1024/1440 when relevant. If one page/task is blocked, record/defer it and continue another independent safe Design System slice.
