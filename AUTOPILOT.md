# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current phase — TECHNICAL REFACTOR PASS

The owner's latest explicit direction is technical-refactor-first after emergency overrides.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey / severe responsive regression → technical refactor pass → UX / visual improvements → content / SEO → cosmetic cleanup`

A visual fix or successful Design System release must not switch the autonomous resume point away from this technical phase. Visual work may interrupt only when it satisfies a higher-priority production/user-journey condition from `AGENTS.md`.

## Technical sources of truth

- `ARCHITECTURE.md` — canonical architecture and one concept → one implementation rules.
- `TEST_MATRIX.md` — canonical test policy and required coverage layers.
- `DEPENDENCY_MAP.md` — active / compatibility / deprecation-candidate implementation inventory.
- `CI_WORKFLOW_AUDIT.md` and focused CI audit companions — GitHub Actions inventory and evidence.
- `CODEX_QUEUE.md` — prepared Codex execution slices.
- `PRODUCT_ROADMAP.md` — product roadmap; it does not override this owner-directed technical phase.

When these documents disagree, first correct the stale source rather than silently following whichever file was edited most recently.

## Current technical goals

1. Keep architecture and source-of-truth documentation internally consistent.
2. Complete repository inventory / dependency mapping for active, compatibility and dead/deprecation-candidate files.
3. Complete GitHub Actions audit and classify checks into `PR FAST`, `PR BROWSER`, `POST DEPLOY`, and `SCHEDULED-LIVE`.
4. Remove or combine CI only after equivalent coverage is demonstrated.
5. Prepare safe target directories `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without changing user behavior.
6. Consolidate shared templates only after dependency/CI evidence is sufficient: one header, one footer, one navigation, one design system.
7. Resume UX/visual optimization after technical consolidation, except for higher-priority production or user-journey regressions.

## Current evidence and resume point

- Architecture/source-of-truth documents and dependency mapping already exist and are being incrementally hardened.
- Room/flight recovery and pending/unpriced flight-price CI families have been partially audited.
- Several workflow families still require explicit layer classification and duplication analysis before any consolidation.
- `v2/assets.php` path restrictions remain a known blocker for physically moving assets into nested target directories; solve loader safety with allowlisting/tests before physical relocation.
- PRs #248/#249/#254 remain excluded from automatic merge without fresh scope-specific review because they are not part of the current independent technical slice.

## Exact next work order

1. Finish exhaustive GitHub Actions inventory and assign every active workflow to `PR FAST`, `PR BROWSER`, `POST DEPLOY`, or `SCHEDULED-LIVE`.
2. Mark duplicate candidates only where equivalent behavior/contract coverage is proven; otherwise retain them and record why.
3. Complete dependency map for non-manifest JS/CSS, PHP endpoints/helpers, templates and deploy consumers.
4. Add the safe relative-path asset loader contract and tests needed before nested directory migration.
5. Create/migrate the target directory structure in behavior-preserving slices.
6. Consolidate shared template layer after consumers and regressions are mapped.
7. Return to UX/visual work after the technical phase is complete.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified social/app destinations and mature search behavior. Legal/payment migration remains deferred. PR #254 stays deferred unless a fresh architecture review proves it safe.

## Execution policy

Work in narrow, independent PR-sized slices. At every run inspect current `main`, open PRs and fresh CI before choosing work. Prefer docs/tests/inventory changes while evidence is incomplete; do not refactor runtime merely for style. SAFE/MEDIUM changes may merge autonomously only after relevant checks are green. If a task is blocked, record/defer it and continue another independent technical slice.
