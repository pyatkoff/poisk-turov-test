# AnyTour technical refactor autopilot

Work autonomously only inside this repository.

Before changing anything, read `AGENTS.md`, `AUTOPILOT.md`, and `AUTOPILOT_STATE.json`. If `ARCHITECTURE.md`, `TEST_MATRIX.md`, or a dependency map already exist, read them too.

## Goal

Perform one small, complete, low-risk technical-refactor slice that makes the project easier and safer for future autonomous development without changing user-facing behavior.

## Current phase order

1. Establish and maintain a single architecture/source of truth (`ARCHITECTURE.md`, `TEST_MATRIX.md`, dependency/inventory documentation).
2. Inventory active, compatibility, deprecated, and dead candidates. Do not delete code until usage is proven.
3. Audit GitHub Actions and tests. Classify checks into PR FAST, PR BROWSER, POST DEPLOY, and SCHEDULED/LIVE. Do not remove a guard until equivalent or better behavioral coverage exists.
4. Prepare gradual modular boundaries for shared, search, results, tour, checkout, integrations, site, seo, tests, scripts, and templates. Avoid a large rewrite.
5. Consolidate shared template infrastructure toward one header, footer, navigation, mobile navigation, and design system only after the architecture/test groundwork is clear.
6. UX and visual work comes after technical consolidation unless a production regression requires immediate attention.

## Hard boundaries

- Do not modify Yandex Metrika configuration, goals/events, or analytics external contracts.
- Do not modify the external lead-sending contract or field mapping.
- Do not modify the Tourvisor external contract.
- Do not modify neighboring projects or server/platform architecture.
- Do not invent a defect just to produce a commit.
- Do not refactor only for style.
- Prefer one concept -> one canonical implementation.

## Risk model

SAFE: docs, inventories, tests, isolated tooling/guards, proven dead-code cleanup.

MEDIUM: search/results/UI/recovery/template behavior; requires focused tests plus broader relevant regression.

HIGH: Metrika, lead contract, Tourvisor contract, schema/data, server architecture, cross-project changes. Do not perform HIGH-risk changes in this automation.

## Execution rules

Do exactly one coherent slice per run. First inspect the current implementation and relevant tests. Prefer improving documentation, test structure, dependency visibility, or isolated technical infrastructure in this phase.

Run the narrowest relevant checks after edits. If the change can affect several areas, run the broader existing regression checks that are practical in CI. Do not change production code merely because a text-based guard is inconvenient; improve coverage deliberately.

Do not run `git commit`, `git push`, create pull requests, or modify git remotes. The GitHub workflow handles that after you finish.

At the end, leave the working tree containing only the intended changes and report: what you inspected, root cause/architecture finding, files changed, tests run/results, remaining risk, and the next recommended slice.
