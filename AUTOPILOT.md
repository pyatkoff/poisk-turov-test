# poisk-turov-test — Autopilot State

Updated: 2026-08-30 09:44 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point, `ARCHITECTURE.md` is the canonical architecture source of truth, `TEST_MATRIX.md` owns CI/test policy, `CODEX_QUEUE.md` owns prepared Codex execution slices, and `PRODUCT_ROADMAP.md` owns product/brand roadmap.

## Current phase — TECHNICAL REFACTOR PASS

The owner-approved current phase is **technical refactor first, then UX/visual**, with the normal emergency overrides preserved:

`production broken → lead loss → incorrect data → broken user journey → technical refactor → UX/visual → content/SEO → cosmetic cleanup`

Do not switch the planned phase back to Design System/visual-first merely because a visual PR was merged or production screenshots improved. A phase change requires an explicit owner decision or a higher-priority production/lead/data/journey defect.

Recent Design System releases #325/#326 are valid production improvements and remain part of the green baseline, but they do not change the current execution priority.

## Refactor goals

1. Finish exhaustive CI/workflow inventory and classify every guard into `PR FAST`, `PR BROWSER`, `POST DEPLOY`, or `SCHEDULED/LIVE`.
2. Build the evidence-backed dependency generations map (`ACTIVE`, `COMPATIBILITY`, `DEPRECATED`, `DEAD-CANDIDATE`).
3. Extract duplicated deterministic CI/bootstrap logic into reusable `scripts/ci/` helpers without weakening behavioral coverage.
4. Make code ownership/folder boundaries safer for Codex and parallel development; do not mass-move runtime files.
5. Reduce order-dependent/historical layers only after consumer mapping and equivalent tests.
6. Then resume shared-shell, UX and visual unification from the already-green production baseline.

## Codex execution order

Use `CODEX_QUEUE.md` as the prepared execution queue.

- C1 / QA-INFRA — complete CI inventory.
- C2 / ARCHITECTURE — dependency generations map.
- C3 / QA-INFRA — reusable live-search bootstrap after C1.
- C4 / STRUCTURE — nested asset ownership only after C1/C2.
- Full shared search-header migration remains deferred until an atomic safe path exists.

Parallel Codex lanes must not edit overlapping ownership zones unless coordinated.

## Production baseline that must not regress

The recent shared editorial rhythm/content-token work is production-green. Preserve the current public site, search, results, comparison, selected-tour, room/flight/price/fuel and lead behavior while refactoring infrastructure around it.

Required responsive evidence for future user-facing work remains 375 / 430 / 768 / 1024 / 1440.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or events;
- analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Do not redesign/replace the AnyTour logo. Legal/payment migration and PR #254 remain deferred unless freshly reassessed and explicitly proven safe.

## Execution policy

Work in narrow material slices. Prefer behavior-preserving extraction over rewrites. Never delete a workflow/guard until equivalent coverage is green. Never delete a historical implementation because of its name/version alone. If one refactor slice is blocked, record it and continue the next independent SAFE task.

A visual/product improvement may still be made immediately when it fixes a confirmed production/broken-journey defect; otherwise visual polish waits behind the current technical refactor pass.
