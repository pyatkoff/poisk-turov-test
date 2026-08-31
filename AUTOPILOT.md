# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records whether the refactor-first phase is active, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — TECHNICAL REFACTOR PASS

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is technical consolidation before further UX/visual work.

## Ordered work

1. Keep a single architecture/source of truth in `ARCHITECTURE.md`, `TEST_MATRIX.md` and the one-concept → one-implementation rule.
2. Complete the inventory/dependency map for active, compatibility and dead files.
3. Complete the GitHub Actions audit and classify checks into PR FAST / PR BROWSER / POST DEPLOY / SCHEDULED-LIVE. Remove duplication only after equivalent coverage is proven.
4. Prepare safe ownership for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without changing user behavior.
5. Consolidate the shared template layer to one header, one footer, one navigation and one design system.
6. Only after technical consolidation resume UX and visual work.

## Current resume point

Previous visual work remains preserved as regression evidence, but does not outrank this phase. Continue from the existing CI inventory/audit and dependency-map artifacts. Keep narrow PRs and merge SAFE/MEDIUM changes only after relevant green checks.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika or goals, the external lead contract, Tourvisor contract or neighboring projects. Do not refactor for style alone and do not invent defects.

## Execution policy

At the start of each run inspect fresh `main`, open PRs and recent CI. Pick one highest-value independent technical-refactor slice and finish it. If blocked, record/defer the blocker and continue another independent safe slice.
