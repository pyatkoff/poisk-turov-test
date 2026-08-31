# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` is the independent phase lock, and `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth are `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md` and `CI_WORKFLOW_AUDIT.md`.

## Current phase — TECHNICAL REFACTOR PASS

The owner's latest explicit direction is technical-refactor-first. Autonomous visual/design-system work does not supersede this phase. Production breakage, lead loss, incorrect data or a broken user journey may preempt temporarily under `AGENTS.md`.

Canonical priority after emergency overrides:

`technical_refactor → ux_visual → content_seo → cosmetic_cleanup`

## Refactor objectives

1. Keep one canonical architecture/source of truth and enforce `one concept → one implementation`.
2. Complete inventory/dependency mapping for ACTIVE, COMPATIBILITY, DEPRECATED and DEAD candidates before moving or deleting files.
3. Complete the GitHub Actions audit into `PR FAST`, `PR BROWSER`, `POST DEPLOY`, `SCHEDULED-LIVE`; remove duplication only after equivalent coverage is proven.
4. Prepare safe ownership structure for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without changing user-visible behavior.
5. Consolidate shared template ownership to one header, one footer, one navigation and one design system after dependency/CI evidence makes migrations safe.
6. Return to UX/visual work after technical consolidation, except when emergency correctness work preempts the phase.

## Exact next work order

1. Finish exhaustive `CI_WORKFLOW_AUDIT.md`, including remaining meal/visual/live/content/catalog/search/tour/mobile workflow families with explicit trigger, tier, protected contract and disposition for every workflow.
2. Finish `DEPENDENCY_MAP.md` for non-manifest JS/CSS, PHP endpoints/helpers and deploy consumers; distinguish ACTIVE/COMPATIBILITY from deletion candidates with concrete references.
3. Define the directory ownership migration plan and prerequisites, including current loader/subdirectory restrictions; do not physically move runtime assets until compatibility is proven.
4. Extract only proven duplicated CI/bootstrap infrastructure while preserving distinct behavioral assertions.
5. Migrate one low-risk implementation family at a time toward canonical ownership, with regression evidence before deleting old paths.
6. Consolidate the shared template layer only after consumers and compatibility obligations are fully mapped.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve mature search/recovery/results/comparison/flight/price/fuel/lead behavior while this technical pass proceeds. Do not refactor merely for style and do not invent defects. Existing visual/product PRs unrelated to this phase remain deferred unless they fix a higher-priority confirmed production/user-journey problem.

## Execution policy

At the start of each run inspect current `main`, open PRs and fresh CI. Work in narrow independent PR-sized slices. Read `AGENTS.md`, this file and `AUTOPILOT_STATE.json` before editing. If a task is blocked, record/defer it and continue the next independent technical slice. SAFE/MEDIUM changes may merge autonomously only after relevant checks are green.
