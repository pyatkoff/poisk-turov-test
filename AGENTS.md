# poisk-turov-test — Autopilot Rules

## Product mission

### Primary goal
Build a modern, visually polished and highly convenient tour search product with strong UX that converts visitors into qualified leads and sales.

### Long-term goal
Use the tour search product as the foundation for a large SEO-driven travel website. Decisions made now should avoid unnecessarily blocking future SEO architecture, performance, landing pages, destination/hotel pages, internal linking and scalable content.

## Autonomy

Autonomy level: **C — high autonomy**.

The agent may independently:
- propose and implement substantial product improvements when they clearly support the product mission;
- improve search UX and site design;
- create branches, commits and pull requests;
- merge and release/deploy changes when the available project workflow permits it;
- fix bugs discovered during development or verification;
- add tests, diagnostics and observability inside this project;
- refactor code when justified by reliability, maintainability, performance or future development;
- reprioritize work when production evidence shows a more important problem.

Do not stop after every intermediate step to ask whether to continue. Continue through the roadmap until a genuine blocker is reached.

## Hard boundaries

This repository/project is isolated from neighboring projects.

Never modify files, configuration, deployment or behavior in other project folders/repositories as part of this project's autonomy.

Without explicit user approval, do **not**:
- change Yandex Metrica configuration;
- change goals/events configured in Metrica;
- change the existing lead-sending mechanism or its external contract.

The lead flow may be tested and bugs around its invocation/integration may be diagnosed, but changing the mechanism itself requires explicit approval.

## Priority order

When choosing the next task, use this order:

1. Production is broken or unavailable.
2. Leads, sales or money can be lost.
3. Incorrect user/business data or materially incorrect search results.
4. Broken visual layout, responsive regressions or severe UX problems.
5. Agreed product roadmap and conversion improvements.
6. Performance and SEO foundation work that prevents future debt.
7. Cosmetic cleanup and technical refactoring.

A higher-priority production finding may interrupt roadmap work. After resolving it, return to the roadmap.

## Lightweight Autopilot kernel

Autopilot and Autopilot 2.0 use the same execution contract documented in `.autopilot/README.md`.

Every delegated implementation slice should declare `goal`, `risk`, `owns_paths`, `depends_on`, `verify` and `done_when`. `owns_paths` is a hard writer boundary: do not silently expand a task into neighboring files or functional areas.

Autopilot normally runs one ready writer task. Autopilot 2.0 may run up to 3 independent writers when their ownership does not overlap. Dependent work is released progressively as its prerequisites are accepted; unrelated lanes do not wait for the slowest task.

Verification is task/risk scoped rather than repository-wide by default:
- **SAFE** — none/syntax/smallest targeted smoke; browser only when genuinely user-visible;
- **MEDIUM** — focused deterministic checks plus one relevant browser/user-flow check;
- **HIGH** — explicit authorization where required, targeted regression, independent review where useful and production smoke for protected live contracts.

For ordinary visual work, default to 375 + 1440 evidence. Add 430/768/1024 only when the change affects those breakpoint/layout transitions. Broad five-width sweeps, scheduled/live audits and whole-project rereads are maintenance/release evidence, not automatic blockers for every small slice.

Each delegated task should emit a compact outcome receipt with changed files, attempts, required verification, remaining risk and a typed failure class when blocked. Classify failures before retrying; do not rerun implementation for missing verification wiring or external-service failures.

Prefer existing checks and one primary verification owner per behavior. Do not add a workflow for a selector/string/version assertion when an existing fast owner can carry it.

## UX and visual quality

UX is a primary product requirement, not final-stage polish.

For user-facing changes:
- inspect the result visually, not only through code/tests;
- verify the viewports relevant to the actual change under the verification budget above;
- check loading, empty, error, long-content and populated states when relevant;
- check interaction states: buttons, forms, filters, dialogs, cards, navigation and sticky/fixed elements;
- watch for overflow, clipping, overlap, unexpected wrapping, layout shifts and inconsistent spacing;
- preserve a coherent visual system across pages/components;
- prefer simpler, clearer user flows over adding controls or explanations.

Because visual regressions have occurred frequently, visual/responsive stability remains a high-priority regression area, but unrelated expensive visual sweeps must not dominate the development loop.

## Whole-project reread

Do not work indefinitely from only recently touched files. Whole-project technical/architectural rereads belong to scheduled maintenance or major-milestone review rather than the blocking loop for normal slices.

Look for duplicated implementations, temporary solutions that became permanent, dead code, inconsistent data flows, fragile coupling, weak error handling/logging, performance/security issues, SEO-hostile choices and opportunities to simplify future development.

Do not refactor merely for stylistic preference. Prioritize changes with concrete product, reliability or development value.

## Refactor cadence

- Continuously: small local cleanup around code already being changed when safe and inside task ownership.
- Scheduled/night maintenance: broader technical health and whole-project reread.
- After a major milestone: architectural review before the next major phase when useful.

Refactoring never outranks production failures, lead loss, data correctness, visual breakage or severe UX problems.

## Definition of DONE

CI green alone is not DONE, but DONE also does not require every repository-wide check for every task.

A feature/fix is DONE when its task contract is satisfied and its risk-appropriate evidence is green. Production/live evidence is required when the task changes or protects a production-critical contract; otherwise it may be collected by release/scheduled verification instead of blocking a SAFE slice.

If required production evidence cannot yet exist, mark the item as `DONE / awaiting production evidence` and continue with independent work.

## Live traffic

Before intentionally increasing live advertising traffic, prioritize visual stability and the core conversion path. Once traffic is running, real user behavior, production errors, search quality and lead outcomes become key feedback signals for prioritization.

Do not change Metrica/goals to optimize measurement without explicit approval.

## Blockers and stopping rules

Stop and ask the user only when:
- an action is materially irreversible or risky;
- required access/secrets are missing;
- there are multiple materially different product choices with no clear winner;
- the required change crosses the project boundary;
- an explicitly forbidden area must be changed.

When one item is blocked, record/defer it and continue other independent roadmap work whenever possible.

## Working categories

Maintain work under these categories:
- `PRODUCT` — features and conversion improvements;
- `UX` — flow clarity, usability and interaction quality;
- `VISUAL` — UI/responsive consistency and regressions;
- `BUG` — functional/production defects;
- `TECH DEBT` — architecture, tests, reliability and maintainability;
- `SEO FOUNDATION` — architecture/performance/indexability work needed for the future large site.

## Codex execution protocol

Codex is an implementation lane, not the product owner. Before changing code, read `AGENTS.md`, `AUTOPILOT.md`, `AUTOPILOT_STATE.json`, `.autopilot/README.md` and the relevant code/tests for the assigned task.

For every assigned task:
- follow the task contract and `owns_paths` exactly;
- keep the diff narrowly scoped and avoid unrelated cleanup;
- prefer extending an existing test/workflow over creating another overlapping one;
- run only the verification required by the task/risk first; do not expand to broad CI without a concrete risk reason;
- for search/result/tour changes, preserve restored-search behavior and stale/async response protections;
- for lead-related work, test invocation and UX without changing the external lead contract unless explicitly authorized;
- never change Metrika/goals, neighboring projects, server-wide configuration or production secrets;
- never weaken, skip or delete a failing relevant guard merely to make CI green;
- emit a compact outcome: changed files, checks/results, attempts, failure class if any, and remaining risk.

Parallel Codex lanes require non-overlapping `owns_paths`. Prefer at most 3 active writers. Worktrees are optional and should be used when parallel/risky isolation is useful, not as mandatory overhead for every SAFE task.

Risk classes:
- **SAFE** — isolated copy/CSS/test/docs/local guard changes with no business-data or lead impact.
- **MEDIUM** — search state, Tourvisor mapping, results rendering, rooms/flights, restored-state behavior and material UI behavior.
- **HIGH** — lead transport, pricing contract, production deploy logic, credentials/secrets, Metrika/analytics contract or irreversible platform/data changes.

## Reporting

Report outcomes rather than commit activity: what became DONE, what is blocked, important production/UX findings and what is next. Do not interrupt autonomous work merely to request permission to continue.

## Core principle

Optimize for a product that is reliable, beautiful, fast, understandable and commercially effective — and for an autonomous loop that spends more time improving the product than re-proving unrelated behavior.
