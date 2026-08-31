# AnyTour CI Workflow Audit

Status: verified audit companion to `TEST_MATRIX.md`.

This file records evidence from the actual workflow definitions on `main`. `TEST_MATRIX.md` remains the canonical tier/coverage policy. No workflow should be deleted or consolidated from filename similarity alone.

## Audit rules

For every workflow, record: trigger, path scope, proposed tier, protected behavior, assertion style, overlap, and disposition. A workflow may be marked `CONSOLIDATE-CANDIDATE` only when the overlap is confirmed from its implementation; removal still requires equivalent replacement coverage.

## Verified batch 1

| Workflow | Trigger / scope | Tier | What it protects | Assertion style | Audit disposition |
| --- | --- | --- | --- | --- | --- |
| `validate-v2-pr.yml` | `pull_request` to `main`; `v2/**` | PR FAST | PHP syntax, active JS syntax, active asset closure, search URL hydration, lazy catalogs, flight autoload, product shell, search-route SEO semantics | mixed: real syntax/render checks plus brittle `src.includes()`/`grep` implementation-string contracts | KEEP; split source-text contracts into reusable diagnostics before any consolidation |
| `visual-v2-pr.yml` | `pull_request` to `main` for `v2/**`; manual | PR BROWSER | five-width search-form/results interaction and responsive visual regression | Playwright behavior + screenshots; local assets injected over a remote shell | KEEP, but modernization candidate: target still points at legacy `anytour.online/poisk-turov-test/v2/` rather than canonical `anytoour.ru/poisk-turov/` |
| `deploy-anytoour.yml` | push to `main` for `v2/**`; manual | POST DEPLOY / RELEASE | standalone release validation, PHP/JS checks, release activation and production smoke | mixed release assertions + runtime smoke | KEEP as production release owner; duplicated pre-deploy validation should later call shared scripts rather than diverging from PR FAST |
| `visual-v2-post-deploy.yml` | successful `Deploy V2 only` workflow run; manual; workflow-file-only push/PR | POST DEPLOY | five-width production V2 visual/DOM regression | Playwright behavior + screenshots | KEEP; legacy URL/workflow-name coupling must be reconciled with current canonical deploy before consolidation |
| `audit-v2-live-traffic.yml` | manual only | SCHEDULED / LIVE (currently disabled) | paid/public traffic analysis entrypoint | disabled stub only | COMPATIBILITY/DORMANT while pre-traffic; do not treat a successful run as traffic evidence |
| `audit-v2-recent-browser.yml` | manual only | SCHEDULED / LIVE (currently disabled) | recent-browser traffic analysis entrypoint | disabled stub only | COMPATIBILITY/DORMANT while pre-traffic; implementation is currently identical to `audit-v2-live-traffic.yml` except name/text |

## Verified batch 2 — search/results/recovery/comparison

| Workflow | Trigger / scope | Tier | What it protects | Assertion style | Audit disposition |
| --- | --- | --- | --- | --- | --- |
| `validate-v2-compare.yml` | PR to `main` when comparison JS/CSS changes; manual | PR BROWSER | compare tray, two-item enablement, modal, remove, max-three guard, search-reset behavior at 375/1440 | Playwright behavior over fixture results, but against legacy remote V2 shell with local JS/CSS injection | KEEP; strong behavioral coverage, but share browser harness/setup later |
| `validate-compare-refresh-guard.yml` | PR to `main` for renderer/comparison/recovery assets; manual | PR BROWSER | compare membership and sort survive rerender, stale modal closes when result set shrinks, retry does not resurrect stale selection | Playwright behavior at 375/768/1440 against legacy remote V2 shell | KEEP; distinct lifecycle coverage from base compare workflow, not a duplicate |
| `validate-results-decision-finality.yml` | PR to `main` for `conversion-confidence-v1.js`; manual | PR BROWSER | decision badges only appear for completed result sets and reset during continue-search | Playwright behavior at 375/768/1440 against legacy remote V2 shell | KEEP; narrow, valuable business-state guard; candidate to share browser harness only |
| `validate-search-continue-ux.yml` | PR to `main` for continue/progress JS; manual | PR FAST | result-only retry diagnostic plus ownership of continue-search progress events/messages | mixed: JS syntax + deterministic Node diagnostic + extensive exact `grep` source-string assertions | KEEP; strongest candidate in this family to migrate implementation-string contracts into diagnostics |
| `validate-second-tour-state-isolation.yml` | PR to `main` for selected-tour/room/flight-price assets; manual | PR BROWSER | first selected tour state cannot leak into second selection; pending price/fuel/lead messaging and return target remain coherent | isolated local Playwright DOM harness at 375/768/1440; no remote dependency | KEEP; high-value cross-module behavioral regression, not duplicate of selected-tour return |
| `validate-selected-tour-return.yml` | PR to `main` for selected-tour return module/manifest; manual | PR BROWSER | return from selected tour/lead preserves sort/comparison state and restores focus across rerenders/fallbacks | isolated local Playwright DOM harness at 375/768/1440 | KEEP; focused accessibility/state coverage; partial overlap with second-tour workflow is intentional defense in depth |

## Verified batch 3 — pending / unpriced flight price state

| Workflow | Trigger / scope | Tier | What it protects | Assertion style | Audit disposition |
| --- | --- | --- | --- | --- | --- |
| `validate-pending-flight-confidence.yml` | PR to `main` for `price-confidence-v1.js`, `unpriced-flight-price-reset-v1.js`; manual | PR BROWSER | base → priced → pending selected-flight confidence copy, pending state must not regress to pre-selection copy, no horizontal overflow at 375/768/1440 | isolated local Playwright DOM harness with branch-local module | KEEP; strong refactor-safe behavioral coverage, candidate only for shared Playwright bootstrap |
| `validate-pending-flight-label.yml` | PR to `main` for `unpriced-flight-price-reset-v1.js` | PR FAST | explicit `Цена уточняется` label, pending-label normalization invocation, protection of already-priced labels | exact Node `src.includes()` implementation-string assertions | KEEP temporarily; REPLACE-AFTER-COVERAGE candidate because behavior is not yet tested independently of implementation text |
| `validate-unpriced-flight-price-reset.yml` | PR to `main` for `unpriced-flight-price-reset-v1.js` and bundle manifest; manual | PR BROWSER | selecting an unpriced flight after a priced one resets stale price, refreshes/falls back fuel correctly, emits `pricePending`, updates lead/confidence copy and avoids overflow at 375/768/1440 | Playwright behavior using local branch guard over legacy remote V2 shell | KEEP; high-value state-transition guard, modernize shared shell/harness later without weakening assertions |

## Verified batch 4 — primary meal responsive/visibility

| Workflow | Trigger / scope | Tier | What it protects | Assertion style | Audit disposition |
| --- | --- | --- | --- | --- | --- |
| `visual-v2-meal-visibility.yml` | PR to `main` for primary-meal JS/CSS plus several shared/layout CSS files, bundle manifest or `v2/index.php`; manual | PR BROWSER | all primary meal choices remain visible, wrapped and non-scrolling with no document overflow across 375/430/768/901/1024/1100/1101/1200/1440 | builds branch-local CSS bundle, injects it into the legacy V2 compatibility shell and checks computed geometry/overflow in Playwright | KEEP; broad meal-layout owner and strong behavioral coverage; candidate for shared browser bootstrap/harness only |
| `validate-v2-primary-meal-responsive.yml` | PR to `main` for primary-meal JS/CSS or bundle manifest | PR BROWSER | at 1024px the meal field expands to the intermediate row, wraps without horizontal scrolling, every option is visible and the final `Без питания` choice is present; uploads screenshot evidence | same branch-local CSS bundle injection over legacy V2 shell, Playwright computed geometry plus one text/content expectation and artifact capture | CONSOLIDATE-CANDIDATE, not deletion-ready: its 1024 visibility/wrap verdict is already a subset of `visual-v2-meal-visibility.yml`, but full-row width, final-choice identity and screenshot evidence are not yet owned by the broader workflow |

### Meal workflows have proven structural overlap, but not yet equivalent coverage

Both meal workflows build the CSS bundle from `bundle-manifest-v1.php`, install Playwright 1.55 Chromium, inject branch-local CSS over `https://anytour.online/poisk-turov-test/v2/`, then assert `.meal-quick` geometry at 1024px. The broader visibility workflow also covers eight additional widths and has a wider path trigger surface.

However, `validate-v2-primary-meal-responsive.yml` still owns three pieces not proved by the broader workflow: the `main-meal` field-width expectation at 1024px, identity/visibility of the last `Без питания` option, and retained screenshot evidence.

Disposition: keep both for now. A safe future consolidation is to add those three missing assertions/evidence to `visual-v2-meal-visibility.yml`, verify the resulting workflow on all current trigger paths, and only then remove the narrower workflow in a separate PR. This is the first workflow family in the exhaustive audit with concrete, implementation-verified consolidation potential rather than filename-level similarity.

## Verified batch 5 — lead guards and recovery

| Workflow | Trigger / scope | Tier | What it protects | Assertion style | Audit disposition |
| --- | --- | --- | --- | --- | --- |
| `validate-lead-form-guard-v1.yml` | push to `main` only when `v2/lead-form-guard-v1.js` or the workflow changes | POST-MERGE FAST today; intended PR FAST contract | basic JS syntax plus presence of phone-length/custom-validity/version implementation markers | `node --check` plus exact `grep` source-string assertions | GAP / REPLACE-AFTER-COVERAGE: it does not protect PRs at all and its semantic checks are implementation-text coupled; keep until equivalent behavioral diagnostic exists, then move that diagnostic to PR FAST |
| `validate-lead-idempotency-v1.yml` | PR, push to `main`, manual; scoped to idempotency PHP + its diagnostic | PR FAST | deterministic idempotency fingerprint semantics without changing the external lead contract | PHP lint + `scripts/ci/lead/validate-idempotency.php` behavioral diagnostic | KEEP; already follows the preferred one-concept → one-diagnostic pattern and is a model for lead-family consolidation |
| `validate-lead-price-v1.yml` | PR, push to `main`, manual; scoped to lead-price PHP + its diagnostic | PR FAST | lead price derivation/normalization semantics | PHP lint + `scripts/ci/lead/validate-price.php` behavioral diagnostic | KEEP; already refactor-safe and independently executable |
| `validate-lead-recovery.yml` | PR to `main`; form/race guard changes; manual | PR BROWSER | error → retry → success and duplicate-success UX, retained form values, success panel/back action across 375/768/1440 | isolated local Playwright DOM harness loading branch-local form guard | KEEP; high-value observable behavior, not duplicate of form syntax guard |
| `validate-lead-search-context.yml` | push to `main` only for `v2/lead-search-context.js`; manual | POST-MERGE FAST today; intended PR FAST contract | child ages, lifecycle coupling and adapter target are present in lead search-context module | JS syntax + exact `grep` source-string assertions | GAP / REPLACE-AFTER-COVERAGE: no PR protection and all semantic verdicts are source-text coupled; add a deterministic context diagnostic before changing/removing this workflow |
| `validate-lead-ui-race-guard.yml` | PR to `main` for race guard/bundle manifest | PR FAST | stale lead UI events are blocked during tour changes while current-tour events pass | deterministic local Node/vm behavioral execution | KEEP; compact, refactor-safe behavioral guard; consider moving its inline script to `scripts/ci/lead/` only to centralize implementation, not to weaken coverage |

### Lead-family audit proves a lifecycle gap, not a duplicate-workflow deletion opportunity

The six lead workflows do not contain a safe whole-workflow duplicate. The important finding is trigger asymmetry: `validate-lead-form-guard-v1.yml` and `validate-lead-search-context.yml` run only after code lands on `main`, while the idempotency, price, recovery and UI-race contracts already protect pull requests.

The two push-only workflows are also the weakest refactor guards because their semantic assertions are exact source strings. Changing them directly to PR checks would improve timing but would preserve brittle false-positive behavior. Safer order: first extract deterministic diagnostics under `scripts/ci/lead/` that exercise observable form validation and search-context payload/lifecycle behavior; then wire those diagnostics into PR FAST with the same path scope; only after green equivalent coverage retire the source-string checks or post-merge-only workflows.

Disposition: no lead workflow is deletion-ready in this batch. `validate-lead-idempotency-v1.yml`, `validate-lead-price-v1.yml` and `validate-lead-ui-race-guard.yml` demonstrate the target architecture: one concept → one independently executable diagnostic, with workflows reduced to trigger/tier orchestration.

## Confirmed findings

### PR FAST has refactor-hostile implementation-string guards

`validate-v2-pr.yml` performs useful real checks (PHP lint, active JS syntax, asset closure and server-rendered SEO semantics), but several important contracts are asserted by exact JavaScript/PHP source strings. Examples include child-composition hydration, lazy catalog loading, C5 flight autoload and product-shell markup. These guards can fail when behavior is preserved but code is reorganized.

Disposition: retain them now. During technical refactor, migrate each high-value source-text contract to a deterministic behavioral diagnostic first; only then remove the corresponding `src.includes()`/`grep` assertion.

### Pending-flight label is another proven source-text-only guard

`validate-pending-flight-label.yml` verifies three useful contracts, but all three are coupled to exact source text in `unpriced-flight-price-reset-v1.js`. The neighboring `validate-pending-flight-confidence.yml` and `validate-unpriced-flight-price-reset.yml` already demonstrate the preferred pattern: execute the branch-local module and assert observable DOM/event state.

Disposition: do not delete the label workflow yet. First add a deterministic local diagnostic that proves pending labels are normalized while already-priced labels remain unchanged; then replace the source-string assertions or fold the diagnostic into an existing pending/unpriced workflow with equivalent path coverage.

### Pending/unpriced browser checks overlap by state boundary, not by verdict

`validate-pending-flight-confidence.yml` owns user-facing confidence copy for base/priced/pending states. `validate-unpriced-flight-price-reset.yml` owns the cross-selection stale-price reset plus fuel/event/lead context after moving from a priced to an unpriced flight. Their shared pending-state assertions are intentional boundary overlap.

Disposition: KEEP both. Consolidation should target Playwright install/bootstrap and, later, a canonical-route harness—not removal of either behavioral contract.

### Search-continue workflow proves the same problem in a narrower family

`validate-search-continue-ux.yml` already runs `diagnostics/search_results_recovery_test.cjs`, which is the preferred refactor-safe pattern, but then repeats several guarantees as exact `grep` checks for event names, dataset assignments and Russian UI strings.

Disposition: do not remove those guards yet. Extend the deterministic recovery diagnostic until it proves the same contracts, then collapse the duplicated source-text assertions. This is the first concrete candidate for `scripts/ci/`/diagnostic consolidation because the behavioral diagnostic already exists.

### Browser PR validation still depends on a legacy host/path

`visual-v2-pr.yml`, `validate-v2-compare.yml`, `validate-compare-refresh-guard.yml` and `validate-results-decision-finality.yml` all exercise local branch assets on top of `https://anytour.online/poisk-turov-test/v2/`.

Risk: several independent guards can all stay green against a compatibility shell while the canonical `anytoour.ru/poisk-turov/` outer route evolves separately.

Disposition: KEEP until a canonical-route harness proves equivalent behavior. Do not replace URLs one workflow at a time; first extract a shared browser-harness contract so all of these workflows move together.

### Comparison workflows overlap by module, not by behavior

`validate-v2-compare.yml` protects direct compare interaction and reset semantics. `validate-compare-refresh-guard.yml` protects state persistence across sort/progressive rerender and stale-selection cleanup during recovered/final result refreshes.

Disposition: both remain ACTIVE. They are not deletion candidates. Consolidation should mean shared setup/fixtures/helper code, not loss of either behavior suite.

### Selected-tour workflows intentionally overlap at the boundary

`validate-selected-tour-return.yml` is narrowly about return/focus/sort/comparison preservation. `validate-second-tour-state-isolation.yml` spans selected-tour, room, flight-price, pending-price and lead-message state to prove that the first tour cannot contaminate the second.

Disposition: KEEP both. Their shared return assertion is useful boundary overlap; factor common local Playwright bootstrap later, but preserve both behavioral contracts.

### The two traffic-audit workflows are currently proven duplicate stubs

`audit-v2-live-traffic.yml` and `audit-v2-recent-browser.yml` both contain only `workflow_dispatch` and a single pre-traffic informational job. There is no active traffic-analysis implementation in either workflow today.

Disposition: mark both DORMANT/COMPATIBILITY. Consolidating them to one explicit pre-traffic gate is SAFE only if external references/manual operator expectations are checked first. Until traffic is deliberately enabled, neither workflow is evidence of production conversion behavior.

### Release validation is duplicated across lifecycle stages

`deploy-anytoour.yml` repeats syntax/asset/release assertions that overlap with PR validation. The duplication currently provides defense in depth, but long inline snippets can drift.

Disposition: do not remove release checks. Extract common deterministic checks into `scripts/ci/` later and call the same script from PR FAST and release workflows; keep deployment-specific production smoke only in POST DEPLOY.

### Legacy naming is now architecture debt

Current post-deploy visual workflow listens for `Deploy V2 only`, while the canonical production workflow audited here is `Deploy anytoour.ru`; its test URL also remains the old `anytour.online/.../v2/` compatibility surface.

Disposition: map the actual active `deploy.yml` vs `deploy-anytoour.yml` relationship before modifying triggers. This is an ACTIVE/COMPATIBILITY dependency question, not safe dead-code deletion.

### Lead checks are split between PR protection and after-merge detection

`validate-lead-form-guard-v1.yml` and `validate-lead-search-context.yml` currently detect regressions only after push to `main`. By contrast, idempotency, price, recovery and UI-race guards run on pull requests and already use deterministic behavioral execution in three of four cases.

Disposition: treat the push-only pair as CI lifecycle gaps, not as dead checks. Build equivalent `scripts/ci/lead/` diagnostics first, add PR FAST coverage, then remove the brittle grep assertions/post-merge-only dependence after proven parity. This preserves the external lead contract while making technical refactoring safer.

## Inventory families still to verify

The repository contains additional workflow families that must be audited before any deletion:

- rooms/flights/price: remaining live/price workflows beyond the verified room/flight companion and pending/unpriced batch;
- mobile/UI: focused visual workflows beyond the already documented duration/sticky and primary-meal batches;
- SEO/content: SEO foundation/page graph/stable paths/publishability/publication manifest/content catalog/primitives plus standalone content/navigation/handoff;
- live journey/content/catalog/search/tour workflows;
- visual production/root/results/baseline/selected-tour/sticky/footer workflows;
- runtime/audit/measurement/deploy/feed/security/autopilot-state workflows.

These families remain authoritative guards until their trigger/path/behavior overlap is verified in later audit batches.

## Next consolidation order

1. Finish exhaustive trigger/path/assertion inventory without modifying workflows.
2. Extract repeated non-browser syntax/asset/render checks into reusable `scripts/ci/` commands.
3. Convert the highest-cost `src.includes()`/`grep` guards to behavioral diagnostics, with lead-form/search-context and `validate-pending-flight-label.yml` now explicit low-risk candidates alongside search-continue.
4. Extract shared Playwright bootstrap/fixture helpers for comparison/results/selected-tour/pending-flight/browser-layout workflows without weakening coverage.
5. Reconcile canonical `anytoour.ru/poisk-turov/` browser coverage with legacy V2 compatibility harnesses.
6. Only after equivalent coverage is green, consolidate superseded workflows one family at a time.

Protected boundaries remain unchanged: no Metrika/goals, lead external contract, Tourvisor external contract, neighboring project or production behavior changes are part of this audit.