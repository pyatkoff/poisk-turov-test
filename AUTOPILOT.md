# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records the explicit technical-refactor phase lock, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — TECHNICAL REFACTOR PASS

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is technical consolidation before further UX/visual work.

## Ordered work

1. Fix the architecture/source-of-truth layer: `ARCHITECTURE.md`, `TEST_MATRIX.md`, and the rule `one concept → one implementation`.
2. Complete the inventory/dependency map across active, compatibility and dead files.
3. Complete the GitHub Actions audit and classify checks as PR FAST / PR BROWSER / POST DEPLOY / SCHEDULED-LIVE. Remove duplicates only after equivalent coverage is demonstrated.
4. Prepare safe directory ownership for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` without changing user-visible behavior.
5. Consolidate the shared template layer to one header, one footer, one navigation and one design system.
6. Only after technical consolidation, resume UX and visual work.

## Current resume point

The highest-value independent work is the exhaustive CI workflow inventory/classification, followed by the dependency map. Preserve already documented overlap evidence and do not delete workflows merely because names or assertions look similar. Any consolidation must first move unique checks into the retained workflow and demonstrate equivalent coverage.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, the external lead contract or field mapping, Tourvisor contract, or neighboring projects. Do not use technical refactoring as a pretext for style-only cleanup and do not invent defects.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI and these source-of-truth files. Choose one highest-value independent technical slice and carry it through a narrow PR and relevant CI. SAFE/MEDIUM changes may be merged autonomously after green evidence. If blocked, record/defer the blocker and move to another independent technical-refactor task.
