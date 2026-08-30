# AnyTour Test Matrix

Status: canonical test/CI ownership map for `pyatkoff/poisk-turov-test`.

This document defines what each test tier is for. The existing workflow set is intentionally not deleted or renamed by this documentation-only slice. Consolidation comes later, one proven-equivalent replacement at a time.

Verified workflow-by-workflow evidence is maintained in `CI_WORKFLOW_AUDIT.md`. This file remains the canonical policy/source-of-truth for test tiers and protected coverage.

## Test tiers

### PR FAST

Purpose: cheap deterministic guards on every relevant PR.

Typical coverage:
- PHP syntax/lint and runtime render smoke;
- JavaScript syntax for active modules;
- active asset manifest/closure checks;
- contract diagnostics that run without a browser;
- security/source-scope guards;
- SEO/canonical render checks that do not require production;
- protected lifecycle invariants such as stale-search isolation, recovery, price/flight state and lead invocation contracts when represented by deterministic diagnostics.

Target runtime: minutes, not tens of minutes.

### PR BROWSER

Purpose: browser-backed behavior and responsive evidence before merge when user-visible/search behavior can change.

Typical coverage:
- Playwright search form/results/tour interactions;
- progressive refresh/recovery/comparison/sorting behavior;
- mobile filters/date/guest/duration cascades;
- shared shell/navigation behavior;
- visual captures at 375 / 430 / 768 / 1024 / 1440 where relevant;
- page errors, overflow, clipping and duplicate-render guards.

These checks should validate behavior, not exact implementation strings, whenever possible.

### POST DEPLOY

Purpose: prove the merged release actually works on `anytoour.ru` and that deployment did not break protected integrations.

Typical coverage:
- public route availability/rendering;
- live search smoke;
- unchanged lead bridge/invocation smoke;
- canonical domain/route expectations;
- representative responsive/shared-shell visual checks;
- release/runtime target sanity.

A successful deploy job alone is not sufficient if the release affects a protected user journey.

### SCHEDULED / LIVE

Purpose: detect production drift, intermittent failures and real runtime regressions that are inappropriate or too expensive for every PR.

Typical coverage:
- live traffic/runtime audits;
- recent-browser/runtime anomaly audits;
- synthetic search/tour checks;
- DOM/performance measurements;
- content/SEO publication audits;
- production visual sweeps.

These checks must distinguish "no paid traffic" from a production defect.

## Protected behavioral areas

The following must retain explicit regression ownership during CI consolidation:

| Area | Minimum expected coverage |
| --- | --- |
| Search lifecycle | PR FAST contract diagnostics + PR BROWSER for behavior changes |
| Final-results recovery/stale responses | PR FAST diagnostics + browser coverage when touched |
| Results renderer/sort/filter | PR FAST syntax/contracts + PR BROWSER |
| Comparison state | PR FAST diagnostics + PR BROWSER |
| Selected tour/return/focus | browser journey coverage |
| Rooms/flights | deterministic contract checks + browser/live smoke as appropriate |
| Price confidence/fallback/sync | focused diagnostics + relevant browser/live smoke |
| Lead UX/invocation | protected contract checks + post-deploy smoke; external contract is read-only |
| Tourvisor integration | contract/smoke coverage; external contract is read-only |
| Shared header/footer/navigation | PR BROWSER responsive + POST DEPLOY representative routes |
| SEO canonical/sitemap/indexability | PR FAST render/contract + scheduled publication audit |
| Security/scope | PR FAST |

## Current workflow families observed on main

The repository currently has many narrow workflows. Known families include:

- runtime/live audits (`audit-anytoour-runtime`, `audit-v2-live-traffic`, `audit-v2-recent-browser`);
- release/deploy (`deploy-anytoour`, legacy `deploy`, feed deploy);
- PR validation (`validate-v2-pr`, security guards, PHP/runtime/source-contract guards);
- focused search/results/flight/price/lead/mobile validation workflows;
- Playwright/visual workflows for V2, standalone pages and production;
- production/live content/search/tour validation;
- measurements such as results DOM/performance.

The workflow audit must enumerate every workflow from `.github/workflows/`, record trigger/path filters, runtime dependencies, behavior protected, overlap and proposed tier. Do not infer dead/duplicate status solely from a similar filename. The verified audit companion is `CI_WORKFLOW_AUDIT.md`.

## Consolidation rules

1. Never delete a guard until its protected behavior is covered by the replacement.
2. Prefer reusable scripts under `scripts/ci/` over repeating long inline shell/Node/PHP setup across workflows.
3. Prefer behavioral assertions over `grep`/`src.includes()` implementation-text assertions. Source-text guards may remain temporarily when they protect a high-risk contract and no behavioral equivalent exists yet.
4. Keep expensive browser/live checks out of PR FAST unless the risk justifies them.
5. Path filters must include every module that can change the protected behavior; otherwise the guard is only apparently present.
6. A workflow that is obsolete because a route/implementation was intentionally replaced should first be marked/documented as superseded, then removed in a separate SAFE PR with replacement evidence.
7. CI consolidation must not change Metrika/goals, lead external contract, Tourvisor external contract or production behavior.

## Required evidence before merging refactor slices

- Documentation/test-inventory only: diff review + relevant doc/source-of-truth guards.
- SAFE code organization with no runtime behavior change: syntax/contracts + dependency/asset closure checks.
- MEDIUM search/results/UI behavior: focused tests + broader relevant PR BROWSER.
- Shared shell visual changes: responsive browser evidence at relevant required widths.
- HIGH external-contract/platform changes: explicit review; not autonomous.

## Audit backlog

Continue the exhaustive workflow inventory in `CI_WORKFLOW_AUDIT.md`. For every workflow, capture:

`workflow → trigger → paths → tier → behavior protected → implementation/source-text assertions → overlap → keep/consolidate/supersede candidate → replacement evidence required`.

Until that inventory is complete, existing workflows remain authoritative guards and must not be removed simply to reduce workflow count.
