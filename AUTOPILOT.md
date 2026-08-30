# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth remain `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md`, `CI_WORKFLOW_AUDIT.md`, `CODEX_QUEUE.md` and `PRODUCT_ROADMAP.md`.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction makes site-wide visual unification the active product priority. Search-only engineering quality must not be treated as the whole-site visual score: the public site is still materially less coherent than the mature search flow.

Canonical priority after emergency overrides:

`design_system_site_unification → ux_visual → content_seo → technical_refactor → cosmetic_cleanup`

Production breakage, lead loss, incorrect data and broken user journeys may preempt temporarily under `AGENTS.md`.

## Design System 1.0 objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one AnyTour product.
2. Establish one shared header/navigation, one footer and one coherent token/typography/grid/spacing/button/card/breadcrumb/responsive system.
3. Remove duplicated shell implementations and confirmed crooked spacing, wrapping, overflow and hierarchy inconsistencies before cosmetic flourishes.
4. Use the mature search experience as the interaction-quality reference without making editorial pages unnecessarily dense.
5. Validate user-facing changes at 375/430/768/1024/1440 and inspect real visual evidence when available.
6. Preserve existing search/recovery/results/comparison/flight/price/fuel/lead behavior and all protected external contracts.

## Exact next work order

1. Finish migration of the search route from its legacy duplicated header to canonical `site-header-v2.php` and shared header styling.
2. Audit and align homepage and editorial shells against shared design tokens/primitives.
3. Audit `/country/` and representative country pages for the same shell, breadcrumbs, cards, spacing and responsive hierarchy.
4. Audit `/hot/`, `/rb/`, `/contacts/` and `/how-to-buy/`, fixing confirmed layout/visual inconsistencies in small safe slices.
5. Run relevant visual/browser regressions at 375/430/768/1024/1440 after each material shared-shell change.
6. Deploy only after relevant checks are green, then verify production/live behavior where accessible.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Do not redesign or replace the AnyTour logo. Preserve verified social/app destinations. Legal/payment migration remains deferred. PR #254 remains deferred unless a fresh scope-specific review proves its separate DB/platform architecture safe. Preserve mature search/recovery/results/comparison/flight/price/fuel/lead behavior.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs and fresh CI, then choose the highest-value independent design-system slice. Do not refactor for style or invent defects. If one page or migration is blocked, record the blocker and continue another safe public-page unification slice.
