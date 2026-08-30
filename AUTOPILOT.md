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
- Search navigation order/labels and responsive header geometry are visually aligned with the public shared header while preserving the mature search DOM/runtime.
- Editorial CTA callout background regression on `/hot/` and `/rb/` is fixed and verified through CI/live visual workflow.
- Representative country related-destination cards balance correctly at 561–768px via PR #369.
- PR #374 removed page-level negative spacing exceptions so editorial top-level cards/sections now use the canonical `--at-content-gap`; deploy, five-width standalone content/navigation/root visuals, live user journey and V2 search/selected-tour/recovery checks are green.
- Fresh five-width artifacts for `/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/`, Turkey, Tunisia and Maldives show no new confirmed overflow, broken CTA or duplicated shell after #374.
- The cross-surface visual gate now needs to protect the compatibility boundary explicitly: `/poisk-turov/` intentionally keeps the mature `.at-site-header` DOM, but its measured height/logo geometry must match `.at-global-header` at 375/430/768/1024/1440 and search-header CSS/index changes must trigger that gate.

## Exact next work order

1. Land and verify the cross-surface header-geometry regression gate for `/poisk-turov/` versus the shared public shell.
2. Continue page-by-page five-width audit of `/rb/`, `/hot/`, `/contacts/`, `/country/` and representative country pages for confirmed spacing, wrapping, hierarchy and shared-shell drift.
3. Move remaining weak editorial primitives onto shared Design System tokens/components where safe.
4. Recheck homepage → country/destination → hot/search → results → selected tour → lead as one visual journey while preserving mature search behavior.
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