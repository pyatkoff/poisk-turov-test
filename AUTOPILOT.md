# poisk-turov-test — Autopilot State

Updated: 2026-08-30 11:48 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md` is the canonical architecture source of truth, `TEST_MATRIX.md` owns CI/test policy, `DEPENDENCY_MAP.md` owns implementation-generation/deprecation status, `CODEX_QUEUE.md` owns prepared Codex execution slices, and `PRODUCT_ROADMAP.md` owns the broader product roadmap.

## Current phase — TECHNICAL REFACTOR PASS

The owner's current explicit direction is technical consolidation before further UX/visual expansion.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey → technical refactor/source-of-truth/CI/dependency cleanup → UX/responsive/visual → content/SEO → cosmetic cleanup`

This priority is owner-locked. Autonomous state refreshes, visual score updates, successful Design System releases or newly found cosmetic opportunities must not switch the project back to visual-first. Only a new explicit owner instruction or a higher-priority production/lead/data/journey defect may change the active phase.

## Technical refactor goals

1. Keep one architecture/source of truth and enforce **one concept → one implementation**.
2. Complete evidence-backed inventory/dependency classification: `ACTIVE`, `COMPATIBILITY`, `DEPRECATED`, `DEAD-CANDIDATE`.
3. Complete GitHub Actions inventory and classify checks as `PR FAST`, `PR BROWSER`, `POST DEPLOY`, `SCHEDULED/LIVE`; remove/consolidate only after equivalent behavior coverage is proven green.
4. Prepare controlled ownership structure for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without changing user behavior.
5. Only after that, consolidate shared template ownership toward one header, one footer, one navigation and one design system.
6. Resume broader UX/visual work after the technical checkpoint.

## Current evidence and resume point

- `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md` and `CODEX_QUEUE.md` exist as canonical technical references.
- PR #316 moved lead idempotency/price deterministic contracts into reusable `scripts/ci/lead/` diagnostics without changing the external lead contract.
- PR #323 established the dependency/deprecation map and hardened removal gates. Historical generations remain non-removable until consumer/deploy/CI proof exists.
- PR #329 extended room/flight CI inventory: room recovery, flight autoload-race and flight empty-recovery are independent branch-local Playwright behavior guards. Their repeated setup is a consolidation candidate; their behavioral verdicts are not duplicates.
- Recent Design System PRs #325/#326/#330/#332/#334 are production-green and remain regression baselines only. They do not change the active technical-refactor priority.
- Full `/poisk-turov/` shared-header component replacement remains deferred until an atomic migration has equivalent browser coverage.

## Exact next work order

1. Finish remaining CI inventory, especially pending/unpriced/price-sync and unmapped search/results/mobile/live families.
2. Complete dependency inventory for non-manifest JS/CSS, PHP endpoints/helpers and deploy consumers; upgrade candidate labels only with evidence.
3. Extract repeated deterministic CI/bootstrap infrastructure into `scripts/ci/` without merging distinct assertions or weakening triggers.
4. Refactor asset resolution to support controlled allowlisted relative subdirectories, with tests and unchanged public URLs/load order/cache behavior.
5. Migrate one low-risk module family into the target ownership structure as proof before wider moves.
6. Consolidate shared template ownership only after dependency/CI coverage makes each move atomic and reversible.
7. Resume UX/visual expansion after the technical checkpoint; retain 375/430/768/1024/1440 production visual evidence as a guardrail.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or events;
- analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve verified social/app destinations and the AnyTour logo. Legal/payment migration and PR #254 remain deferred. PRs #248/#249/#254 must not be auto-merged as part of this refactor pass without a fresh, scope-specific reassessment.

## Execution policy

Work in narrow, independent PR-sized slices. Read current `main`, open PRs and fresh CI before choosing work. Prefer behavior-backed diagnostics over source-string guards. Do not delete a workflow/file merely because another check touches the same asset. Do not refactor for style or invent defects. SAFE changes may merge after relevant green checks; MEDIUM changes require focused regression plus relevant broader evidence. If a slice is blocked, record it and continue the next independent technical task.
