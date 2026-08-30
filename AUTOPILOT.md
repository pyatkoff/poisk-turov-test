# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth remain `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md`, `CI_WORKFLOW_AUDIT.md`, `CODEX_QUEUE.md` and `PRODUCT_ROADMAP.md`.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction is site-wide visual unification. Search engineering remains mature and protected, but whole-site product coherence is the active product priority.

Canonical priority after emergency overrides:

`design_system_site_wide_visual_unification → supporting_technical_consolidation → content_seo → cosmetic_cleanup`

Emergency/product safety still overrides this phase in the order defined by `AGENTS.md`: production broken → lead loss → incorrect data → broken visual/user journey. Successful unrelated releases or autonomous state refreshes must not replace the owner phase. Only a new explicit owner direction may change `OWNER_PRIORITY.json`.

## Design System 1.0 objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one product.
2. Keep one coherent header/navigation, one footer, shared typography, spacing/grid, buttons, cards, breadcrumbs and responsive rules.
3. Use the mature search experience as the quality reference without making editorial pages unnecessarily dense.
4. Fix confirmed overflow, wrapping, crooked spacing, duplicated shell and hierarchy problems before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 where relevant.
6. Preserve mature search/recovery/results/comparison/flight/price/fuel/lead behavior and all protected external contracts.

## Current resume point

Recent production-verified slices already improved the homepage, `/hot/`, `/rb/`, `/country/` and representative country-page responsive grids, plus shared editorial primitives and mobile iOS shell regressions.

Next work order:

1. Verify the latest country-page balanced-three release is fully production/live green.
2. Audit the remaining visual discontinuity between the shared public-site shell and `/poisk-turov/`, especially header/nav geometry and mobile/desktop parity, without replacing mature search behavior.
3. Re-audit `/contacts/`, `/how-to-buy/`, `/rb/`, `/hot/`, `/country/` and representative country pages at 375/430/768/1024/1440 for confirmed spacing/wrapping/overflow issues.
4. Consolidate only proven duplicated visual primitives into the shared Design System layer.
5. Continue through multiple independent safe visual tasks while preserving all search and lead regressions.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified destinations and mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Legal/payment migration remains deferred. PRs #248/#249/#254 are not automatic merge candidates without fresh scope-specific review.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs, fresh CI/deploy and live production evidence. Do not invent visual defects merely to produce commits. If one visual item is blocked, record/defer it and continue another safe page/component. Deploy only after relevant checks are green, then verify production/live behavior.
