# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth are:

- `ARCHITECTURE.md` — canonical architecture and ownership boundaries;
- `TEST_MATRIX.md` — CI/test policy and required coverage;
- `DEPENDENCY_MAP.md` — active/compatibility/deprecated/dead dependency inventory;
- `CI_WORKFLOW_AUDIT.md` — exhaustive GitHub Actions classification;
- `CODEX_QUEUE.md` — narrow execution slices;
- `PRODUCT_ROADMAP.md` — product/UX roadmap after technical consolidation.

## Current phase — TECHNICAL REFACTOR PASS

The owner's latest explicit direction is technical-refactor-first. Autonomous visual/design-system progress must not replace this owner lock.

Canonical priority after emergency overrides:

`technical_refactor → ux_visual → content_seo → cosmetic_cleanup`

Emergency/product safety still overrides this phase in the order defined by `AGENTS.md`: production broken → lead loss → incorrect data → broken visual/user journey. Only a new explicit owner direction may change `OWNER_PRIORITY.json`.

## Refactor objectives

1. Keep one canonical architecture/source of truth and enforce `one concept → one implementation`.
2. Complete inventory/dependency mapping for ACTIVE, COMPATIBILITY, DEPRECATED and DEAD candidates before moving or deleting files.
3. Complete GitHub Actions audit into `PR FAST`, `PR BROWSER`, `POST DEPLOY`, `SCHEDULED/LIVE`; remove duplication only after equivalent coverage is proven.
4. Prepare safe ownership structure for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without user-visible behavior changes.
5. Consolidate shared template ownership to one header, one footer, one navigation and one design system after dependency/CI evidence makes migrations safe.
6. Return to UX/visual work after technical consolidation, except when production/user-journey regressions preempt the phase.

## Exact next work order

1. Finish exhaustive `CI_WORKFLOW_AUDIT.md`, including remaining meal/visual/live workflow families and explicit trigger/tier/disposition for every workflow.
2. Finish `DEPENDENCY_MAP.md` for non-manifest JS/CSS, PHP endpoints/helpers and deploy consumers; distinguish ACTIVE/COMPATIBILITY from deletion candidates with concrete references.
3. Define the directory ownership migration plan and prerequisites, including the current `v2_asset()` subdirectory restriction; do not physically move runtime assets until loader/tests prove compatibility.
4. Extract only proven duplicated CI/bootstrap infrastructure while preserving distinct behavioral assertions.
5. Migrate one low-risk implementation family at a time toward canonical ownership, with regression evidence before deletion of old paths.
6. Consolidate shared template layer only after its consumers and compatibility obligations are fully mapped.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified social/app destinations and mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Legal/payment migration remains deferred. PRs #248/#249/#254 remain excluded from automatic merge without fresh scope-specific review.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs and fresh CI, then choose the highest-value independent technical slice. Do not refactor for style or invent defects. Do not delete a guard or implementation until equivalent consumers/coverage are proven. If one technical item is blocked, record the blocker and continue another independent safe slice.
