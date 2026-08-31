# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — TECHNICAL REFACTOR PASS

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize behavior-preserving technical consolidation before further UX/visual work. GitHub `main` remains the source of truth. Work only inside `pyatkoff/poisk-turov-test`.

## Ordered work

1. Enforce `ARCHITECTURE.md` and `TEST_MATRIX.md` as canonical source-of-truth documents, including `one concept → one implementation`.
2. Complete an evidence-backed repository inventory/dependency map and classify implementations as `CANONICAL`, `COMPATIBILITY`, `DEPRECATED` or `DEAD-CANDIDATE`; do not delete based on age or naming.
3. Complete the GitHub Actions audit and classify checks as `PR FAST`, `PR BROWSER`, `POST DEPLOY` or `SCHEDULED-LIVE`; remove duplication only after equivalent-or-stronger replacement coverage is proven.
4. Prepare incremental ownership under `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without mass moves or user-visible behavior changes.
5. Consolidate the shared template layer only after dependency and coverage proof: one header, one footer, one navigation and one canonical AnyTour Design System 2.0.
6. Resume UX/visual development after technical consolidation or when a higher-priority production/lead/data/critical-journey issue interrupts the pass.

## Current resume point

`ARCHITECTURE.md` already defines the target ownership zones and canonical implementation registry. `TEST_MATRIX.md` already defines the four CI tiers and requires proven equivalent coverage before workflow removal. The active slice realigns `OWNER_PRIORITY.json`, `AUTOPILOT_STATE.json`, `TECHNICAL_REFACTOR_LOCK.json` and the security owner-direction guard with this explicit refactor-pass direction.

Next: continue the exhaustive repository inventory/dependency map, then the workflow-by-workflow CI audit. Prefer documentation, dependency evidence and reusable CI helpers before physical file moves. Keep existing checks until replacement evidence is recorded.

## Mandatory protections

Do not modify Yandex Metrika configuration/goals, the external lead-sending contract/field mapping, Tourvisor external contract, server/platform architecture outside the allowed repository scope, or neighboring projects. Preserve the existing AnyTour logo and canonical AnyTour Design System 2.0. Do not reintroduce Design System 1.0.

## Decision rule

For each refactor slice: inspect consumers/dependencies → declare canonical owner → add/confirm coverage → make the smallest behavior-preserving change → run focused CI → merge only after relevant checks are green. If blocked, record/defer the blocker and continue an independent safe slice.
