# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md` and `CI_WORKFLOW_AUDIT.md` remain technical sources of truth while the active product phase is site-wide visual unification.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's newest explicit direction supersedes the temporary technical-refactor-first resume point.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey → Design System 1.0 / site-wide visual unification → supporting technical consolidation → content/SEO → cosmetic cleanup`

Do not confuse the mature tour-search engineering quality with whole-site coherence. Whole-site baseline is approximately **6.5/10**; current estimate remains approximately **6.7/10** until broader country/editorial continuity is production-verified.

## Design System 1.0 goals

1. One coherent product across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
2. Shared tokens/primitives for shell width, responsive gutters, typography, spacing, buttons, cards, breadcrumbs and responsive behavior.
3. One coherent header/navigation and footer, preserving the existing AnyTour logo and verified destinations.
4. Migrate weak/editorial pages onto the shared shell without making them unnecessarily dense.
5. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy defects before cosmetic flourishes.
6. Preserve search/recovery/results/comparison/flight/price/fuel/lead behavior and protected external contracts.

## Current evidence and resume point

- PR #348 mobile iOS layout hotfix is merged and production-green.
- PR #349 shared editorial primitive consolidation is merged after a green PR suite.
- `/hot/` was re-audited previously and had no confirmed material hierarchy defect.
- Current active slice moves `/rb/` onto the shared numbered-step hierarchy and gives representative country pages a dedicated balanced three-item related-destination grid.
- Older country/rb visual PRs #345/#346/#347 are superseded by the current-main consolidated slice and should not be merged independently.
- PRs #248/#249/#254 remain excluded from automatic merge without fresh scope-specific review; legal/payment migration remains deferred.

## Exact next work order

1. Finish CI and five-width visual verification for the current `/rb/` + representative country-page slice; merge/deploy only if green and verify live.
2. Audit `/country/` catalog final-row balance and hierarchy on current main, implementing only evidence-backed changes.
3. Reassess homepage → country → hot/search continuity and shared header/footer rhythm at 375/430/768/1024/1440.
4. Audit remaining standalone/editorial pages for duplicated spacing/button/card/breadcrumb rules and fold safe improvements into shared primitives.
5. Keep mature search/recovery/results/comparison/flight/price/fuel/lead regressions green throughout.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified social/app destinations and mature search behavior. Legal/payment migration remains deferred. PR #254 stays deferred unless a fresh architecture review proves it safe.

## Execution policy

Work in narrow, independent PR-sized slices. Inspect current `main`, open PRs and fresh CI before choosing work. For user-facing changes validate 375/430/768/1024/1440 and inspect visual evidence. Deploy only after relevant checks are green, then verify live behavior. If one page/task is blocked, record/defer it and continue another safe independent Design System slice.
