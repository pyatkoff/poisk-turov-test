# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md` owns architecture/source-of-truth rules, `TEST_MATRIX.md` owns CI/test policy, `DEPENDENCY_MAP.md` owns implementation generations and deprecation evidence, `CI_WORKFLOW_AUDIT.md` owns workflow inventory evidence, and `CODEX_QUEUE.md` owns prepared Codex execution slices.

## Current phase — TECHNICAL REFACTOR PASS

The owner's newest explicit direction supersedes the previous Design System-first resume point.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey → technical refactor/source-of-truth consolidation → UX/visual → content/SEO → cosmetic cleanup`

The technical phase must complete the highest-value architecture/inventory/CI/structure work before autonomous visual roadmap work resumes. A successful visual release or visual score change must not silently switch the phase back to Design System-first.

## Technical refactor goals

1. Keep one canonical architecture and source of truth: `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md`, `CI_WORKFLOW_AUDIT.md`, plus the rule `one concept → one implementation`.
2. Complete inventory/dependency mapping of ACTIVE, COMPATIBILITY, DEPRECATED and DEAD-CANDIDATE files before moving or deleting implementations.
3. Complete GitHub Actions audit into `PR FAST`, `PR BROWSER`, `POST DEPLOY`, `SCHEDULED/LIVE`; remove duplication only after equivalent behavioral coverage is proven.
4. Prepare a safe directory structure for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without changing user-visible behavior or external contracts.
5. Then consolidate the shared template layer toward one header, one footer, one navigation and one design system.
6. Resume broader UX/visual work after technical consolidation, except when a confirmed production/UX regression preempts refactor work.

## Current evidence and resume point

- `CI_WORKFLOW_AUDIT.md` already classifies core V2, comparison/results/recovery, selected-tour and pending/unpriced flight workflows. Source-string-only guards are explicitly retained until behavioral diagnostics replace them.
- `DEPENDENCY_MAP.md` is the canonical implementation-generation/deprecation map. No candidate is deleted solely from filename/version similarity.
- The latest production release includes the mobile iOS layout fix and its post-deploy migrated-content/live-user-journey checks are green.
- Open Design System PRs #345/#346/#347/#349 are not automatic priority work during this phase; reassess them only if they materially support consolidation or fix a confirmed regression.
- PRs #248/#249/#254 remain excluded from automatic merge without fresh scope-specific review.

## Exact next work order

1. Finish exhaustive GitHub Actions trigger/path/assertion inventory and classify every remaining workflow family.
2. Finish non-manifest/PHP/deploy-consumer dependency inventory so ACTIVE/COMPATIBILITY/DEPRECATED/DEAD-CANDIDATE labels have evidence.
3. Extract repeated deterministic CI/bootstrap infrastructure only where inventory proves equivalent inputs/consumers; preserve independent behavioral verdicts.
4. Replace highest-cost exact `src.includes()`/`grep` guards with deterministic behavioral diagnostics before removing those source-text assertions.
5. Prepare the allowlisted nested asset/path strategy and target directory ownership map before physically moving runtime modules.
6. Consolidate shared template primitives only after dependency and regression coverage is sufficient for safe migration.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified social/app destinations and mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Legal/payment migration remains deferred.

## Execution policy

Work in narrow, independent PR-sized slices. Before each slice inspect current `main`, open PRs and fresh CI. Prefer documentation/inventory/tests before runtime relocation. Never refactor for style or invent a defect. Do not delete a guard or implementation until equivalent coverage/consumer evidence is green. If one technical item is blocked, record the blocker and continue another independent technical slice.
