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

## UX and visual quality

UX is a primary product requirement, not final-stage polish.

For user-facing changes:
- inspect the result visually, not only through code/tests;
- verify relevant desktop and mobile viewport sizes;
- verify important intermediate responsive widths where layout can collapse;
- check loading, empty, error, long-content and populated states when relevant;
- check interaction states: buttons, forms, filters, dialogs, cards, navigation and sticky/fixed elements;
- watch for overflow, clipping, overlap, unexpected wrapping, layout shifts and inconsistent spacing;
- preserve a coherent visual system across pages/components;
- prefer simpler, clearer user flows over adding controls or explanations.

Because visual regressions have occurred frequently, visual/responsive stability is a high-priority regression area before and during live traffic.

## Whole-project reread

Do not work indefinitely from only recently touched files.

During active development, perform a whole-project technical/architectural reread approximately weekly and after major milestones. Reconstruct how the current project actually works from the repository, compare it with the roadmap and look for:
- duplicated implementations;
- temporary solutions that became permanent;
- dead code;
- inconsistent data flows;
- oversized or mixed-responsibility modules;
- fragile coupling;
- missing tests;
- weak error handling/logging;
- performance problems;
- security issues;
- SEO-hostile architectural choices;
- opportunities to simplify future development.

Do not refactor merely for stylistic preference. Prioritize changes with concrete product, reliability or development value.

## Refactor cadence

- Continuously: small local cleanup around code already being changed when safe.
- Approximately weekly during active development: technical health check + whole-project reread.
- Approximately every 2 weeks when enough changes accumulated: focused refactor pass.
- After a major milestone: architectural review before the next phase.

Refactoring never outranks production failures, lead loss, data correctness, visual breakage or severe UX problems.

## Definition of DONE

CI green alone is not DONE.

A feature/fix is DONE when applicable evidence includes:
1. implementation completed;
2. automated tests/checks passed;
3. real functional verification performed;
4. production behavior checked after release;
5. relevant logs/search responses/leads confirm expected behavior;
6. user-facing work received visual/responsive verification.

If real production evidence cannot yet exist, mark the item as `DONE / awaiting production evidence` and continue with other work rather than blocking the roadmap.

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

Maintain work mentally or in project tracking under these categories:
- `PRODUCT` — features and conversion improvements;
- `UX` — flow clarity, usability and interaction quality;
- `VISUAL` — UI/responsive consistency and regressions;
- `BUG` — functional/production defects;
- `TECH DEBT` — architecture, tests, reliability and maintainability;
- `SEO FOUNDATION` — architecture/performance/indexability work needed for the future large site.

## Reporting

During initial active development, provide approximately hourly summaries when work is actively progressing. Reports should focus on outcomes, not commit activity:
- what materially became DONE;
- what is being verified now;
- important production/UX/visual findings;
- what became the next priority;
- deferred blockers requiring user input.

Do not interrupt autonomous work merely to request permission to continue.

Later, reporting frequency may be reduced as the product stabilizes.

## Core principle

Optimize for a product that is reliable, beautiful, fast, understandable and commercially effective — not for number of commits or number of features shipped.
