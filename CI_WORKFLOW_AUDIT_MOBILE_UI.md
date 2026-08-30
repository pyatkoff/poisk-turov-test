# AnyTour CI Workflow Audit — Mobile / UI Batch

Status: verified companion to `CI_WORKFLOW_AUDIT.md` and `TEST_MATRIX.md`.

Scope: mobile-focused pull-request workflows inspected directly on `main` during the technical refactor pass. This batch is documentation-only: no workflow triggers, runtime behavior, CSS/JS, analytics, lead, Tourvisor, deploy, or neighboring-project behavior is changed.

## Verified workflows

| Workflow | Trigger / scope | Tier | What it protects | Assertion style | Audit disposition |
| --- | --- | --- | --- | --- | --- |
| `validate-mobile-duration-cascade.yml` | PR to `main` when `v2/results-layout-guard-v1.css` or the workflow changes | PR BROWSER | mobile 375/430 duration-choice layout remains grid-based, does not internally overflow, choices fit their grid, and the page has no horizontal overflow | Playwright against canonical `https://anytoour.ru/poisk-turov/`, then branch-local CSS injection and computed-layout assertions | KEEP; refactor-safe behavioral guard. Candidate only for shared Playwright install/bootstrap, not deletion |
| `validate-mobile-sticky-boundary.yml` | PR to `main` for mobile summary/progress JS/CSS, bundle manifest or workflow; manual | PR BROWSER | mobile sticky search CTA exists when appropriate, stays compact, yields to the inline submit at the form boundary, remains hidden below the form, and does not create horizontal overflow | mixed: one exact CSS `grep`, then branch bundle build and Playwright behavior at 375/430 against legacy V2 compatibility shell with local bundle interception | KEEP; behavioral verdict is distinct. Replace the exact CSS-string precondition only after equivalent computed stacking behavior is covered |

## Findings

### Both mobile workflows are PR BROWSER, not PR FAST

Although `validate-mobile-duration-cascade.yml` is narrow, its verdict depends on Chromium layout and computed geometry on the canonical production search shell. `validate-mobile-sticky-boundary.yml` also requires Chromium plus built branch bundles and cross-scroll-position state checks.

Disposition: classify both as **PR BROWSER**. Do not move them into PR FAST merely because their path scopes are narrow.

### Duration cascade is already a strong refactor-safe behavioral anchor

The duration workflow loads the canonical `/poisk-turov/` route, injects the branch-local `results-layout-guard-v1.css`, and asserts observable computed layout rather than implementation strings. It protects only the 375/430 duration-choice contract.

Disposition: KEEP. Its overlap with broader visual suites is intentional: broad screenshots can miss the exact internal-overflow and per-choice-width contract this workflow asserts. Consolidation should target Playwright installation/bootstrap only.

### Sticky boundary has one brittle source-text assertion before good behavioral coverage

`validate-mobile-sticky-boundary.yml` first requires an exact selector/declaration string from `v2/search-progress-ux-v1.css` using `grep`. The workflow then performs stronger behavior checks: it builds branch bundles, intercepts bundle requests, loads the compatibility V2 shell and verifies sticky/inline CTA visibility at the initial position, form boundary and below-form position.

Disposition: KEEP the workflow. Mark the exact `grep` step as **REPLACE-AFTER-COVERAGE**. Before removing it, add a computed stacking/offset assertion that proves the progress/status layer remains below the mobile summary when both are visible.

### The two workflows are not duplicates

`validate-mobile-duration-cascade.yml` owns duration-choice grid geometry and horizontal-overflow safety. `validate-mobile-sticky-boundary.yml` owns sticky CTA lifecycle and the mobile summary/progress stacking boundary. They share viewport sizes but no equivalent behavioral verdict.

Disposition: no workflow deletion candidate in this batch.

### Canonical-vs-compatibility host split is now explicit evidence

The duration workflow already tests `https://anytoour.ru/poisk-turov/`, while the sticky-boundary workflow still uses `https://anytour.online/poisk-turov-test/v2/` with branch bundle interception. This mirrors the broader CI audit finding that browser guards are split between canonical and compatibility shells.

Disposition: do not change the sticky URL in isolation. First establish a shared browser-harness contract and prove equivalent branch-asset injection on the canonical route; then migrate the legacy-host family together.

## Consolidation candidates from this batch

No workflow is safe to delete.

Safe infrastructure-only candidates after the exhaustive inventory is complete:

1. shared Playwright version/install/bootstrap helper for PR BROWSER workflows;
2. shared viewport declaration/helper for 375/430 mobile checks;
3. a reusable branch-bundle build/injection helper for workflows that currently inline the same setup;
4. replacement of exact CSS source-string guards with computed DOM/layout diagnostics before any source-text assertion is removed.

These changes must preserve the separate duration-grid and sticky-boundary verdicts.

## Remaining mobile/UI inventory

This batch closes the explicitly identified `duration` and `sticky` workflows. The broader mobile/UI family is not yet exhaustive: meal-focused and visual production/root/results/selected-tour/sticky/footer workflows still require trigger/path/assertion inspection before any duplicate claim or deletion.

## Protected boundaries

No Metrika/goals, analytics contract, external lead contract or field mapping, Tourvisor contract, neighboring project, workflow trigger, or production user behavior is changed by this audit.
