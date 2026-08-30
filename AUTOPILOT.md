# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth are:

- `OWNER_PRIORITY.json` — explicit owner-priority lock;
- `ARCHITECTURE.md` — canonical architecture and ownership boundaries;
- `TEST_MATRIX.md` — canonical CI/test policy and required coverage;
- `DEPENDENCY_MAP.md` — ACTIVE / COMPATIBILITY / DEPRECATED / DEAD inventory and dependency evidence;
- `CI_WORKFLOW_AUDIT.md` — exhaustive GitHub Actions classification;
- `CODEX_QUEUE.md` — narrow execution slices;
- `PRODUCT_ROADMAP.md` — product/UX roadmap after the technical refactor phase.

## Current phase — TECHNICAL REFACTOR PASS

The owner's latest explicit direction is technical-refactor-first. Successful visual releases, autonomous state refreshes, score changes or unrelated PRs must not replace this phase. Only a new explicit owner direction may change `OWNER_PRIORITY.json`.

Canonical priority after emergency overrides:

`technical_refactor → ux_visual → content_seo → cosmetic_cleanup`

Production breakage, lead loss, incorrect data and broken user journeys may preempt temporarily under `AGENTS.md`.

## Refactor objectives

1. Keep one canonical architecture/source of truth and enforce `one concept → one implementation`.
2. Complete repository inventory/dependency mapping for ACTIVE, COMPATIBILITY, DEPRECATED and DEAD candidates before moving or deleting files.
3. Complete GitHub Actions audit into `PR FAST`, `PR BROWSER`, `POST DEPLOY`, `SCHEDULED/LIVE`; remove duplication only after equivalent coverage is proven.
4. Prepare safe ownership structure for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without user-visible behavior changes.
5. Consolidate shared template ownership to one header, one footer, one navigation and one design system only after dependency and CI evidence make migrations safe.
6. Return to UX/visual work after technical consolidation, except when emergency/user-journey regressions preempt the phase.

## Exact next work order

1. Finish exhaustive `CI_WORKFLOW_AUDIT.md`, including remaining meal/visual/live/content/catalog/search/tour workflow families and explicit trigger/tier/disposition for every workflow.
2. Finish `DEPENDENCY_MAP.md` for non-manifest JS/CSS, PHP endpoints/helpers and deploy consumers; distinguish ACTIVE/COMPATIBILITY from deletion candidates with concrete references.
3. Define the directory ownership migration plan and prerequisites, including loader/path restrictions; do not physically move runtime assets until loaders/tests prove compatibility.
4. Extract only proven duplicated CI/bootstrap infrastructure while preserving distinct behavioral assertions.
5. Migrate one low-risk implementation family at a time toward canonical ownership, with regression evidence before deleting old paths.
6. Consolidate shared template layer only after its consumers and compatibility obligations are fully mapped.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Do not redesign or replace the AnyTour logo. Preserve verified social/app destinations. Legal/payment migration remains deferred. PRs #248/#249/#254 are not automatic merge candidates without fresh scope-specific review.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs and fresh CI, then choose the highest-value independent technical slice. Do not refactor for style or invent defects. Do not remove a guard or implementation until equivalent consumers/coverage are proven. If one technical item is blocked, record/defer it and continue another independent safe technical slice.
