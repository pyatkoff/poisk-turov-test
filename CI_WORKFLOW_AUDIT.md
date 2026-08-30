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

## Confirmed findings

### PR FAST has refactor-hostile implementation-string guards

`validate-v2-pr.yml` performs useful real checks (PHP lint, active JS syntax, asset closure and server-rendered SEO semantics), but several important contracts are asserted by exact JavaScript/PHP source strings. Examples include child-composition hydration, lazy catalog loading, C5 flight autoload and product-shell markup. These guards can fail when behavior is preserved but code is reorganized.

Disposition: retain them now. During technical refactor, migrate each high-value source-text contract to a deterministic behavioral diagnostic first; only then remove the corresponding `src.includes()`/`grep` assertion.

### Browser PR validation still depends on a legacy host/path

`visual-v2-pr.yml` runs Playwright against `https://anytour.online/poisk-turov-test/v2/` and intercepts local `v2` JS/CSS. This was a pragmatic historical harness, but the canonical public search is now `https://anytoour.ru/poisk-turov/`.

Risk: a PR browser guard can stay green against a compatibility shell while the canonical route evolves separately.

Disposition: KEEP until a replacement harness proves equivalent search-form/results/tour coverage on the canonical route. Modernization is a separate MEDIUM CI slice, not a blind URL replacement.

### The two traffic-audit workflows are currently proven duplicate stubs

`audit-v2-live-traffic.yml` and `audit-v2-recent-browser.yml` both contain only `workflow_dispatch` and a single pre-traffic informational job. There is no active traffic-analysis implementation in either workflow today.

Disposition: mark both DORMANT/COMPATIBILITY. Consolidating them to one explicit pre-traffic gate is SAFE only if external references/manual operator expectations are checked first. Until traffic is deliberately enabled, neither workflow is evidence of production conversion behavior.

### Release validation is duplicated across lifecycle stages

`deploy-anytoour.yml` repeats syntax/asset/release assertions that overlap with PR validation. The duplication currently provides defense in depth, but long inline snippets can drift.

Disposition: do not remove release checks. Extract common deterministic checks into `scripts/ci/` later and call the same script from PR FAST and release workflows; keep deployment-specific production smoke only in POST DEPLOY.

### Legacy naming is now architecture debt

Current post-deploy visual workflow listens for `Deploy V2 only`, while the canonical production workflow audited here is `Deploy anytoour.ru`; its test URL also remains the old `anytour.online/.../v2/` compatibility surface.

Disposition: map the actual active `deploy.yml` vs `deploy-anytoour.yml` relationship before modifying triggers. This is an ACTIVE/COMPATIBILITY dependency question, not safe dead-code deletion.

## Inventory families still to verify

The repository contains additional workflow families that must be audited before any deletion:

- search/results/recovery/comparison: `validate-v2-compare`, `validate-compare-refresh-guard`, `validate-results-decision-finality`, `validate-search-continue-ux`, `validate-second-tour-state-isolation`, `validate-selected-tour-return`;
- rooms/flights/price: `validate-room-recovery`, flight autoload/empty/fuel/keyboard/pending/unpriced/live workflows;
- lead: form/idempotency/price/recovery/search-context/UI-race guards;
- mobile/UI: duration/sticky/meal and focused visual workflows;
- SEO/content: SEO foundation/page graph/stable paths/publishability/publication manifest/content catalog/primitives plus standalone content/navigation/handoff;
- live journey/content/catalog/search/tour workflows;
- visual production/root/results/baseline/selected-tour/meal/sticky/footer workflows;
- runtime/audit/measurement/deploy/feed/security/autopilot-state workflows.

These families remain authoritative guards until their trigger/path/behavior overlap is verified in later audit batches.

## Next consolidation order

1. Finish exhaustive trigger/path/assertion inventory without modifying workflows.
2. Extract repeated non-browser syntax/asset/render checks into reusable `scripts/ci/` commands.
3. Convert the highest-cost `src.includes()`/`grep` guards to behavioral diagnostics.
4. Reconcile canonical `anytoour.ru/poisk-turov/` browser coverage with legacy V2 compatibility harnesses.
5. Only after equivalent coverage is green, consolidate superseded workflows one family at a time.

Protected boundaries remain unchanged: no Metrika/goals, lead external contract, Tourvisor external contract, neighboring project or production behavior changes are part of this audit.
