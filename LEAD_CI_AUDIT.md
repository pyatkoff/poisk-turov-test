# Lead CI Audit

Status: verified lead-family inventory companion to `CI_WORKFLOW_AUDIT.md` and `TEST_MATRIX.md`.

This document records the current lead-specific workflow contracts on `main` without changing runtime behavior or the protected external lead-sending contract. It exists as a narrow audit slice so the lead family can be consolidated later only after equivalent coverage is proven.

Tier mapping follows the canonical four buckets from `TEST_MATRIX.md`: cheap deterministic lead-contract checks belong in **PR FAST**, browser-visible recovery belongs in **PR BROWSER**, production lead bridge/invocation verification belongs in **POST DEPLOY**, and no lead-specific workflow audited here currently belongs in **SCHEDULED / LIVE**. A workflow may currently trigger only after merge while still being classified by its intended ownership tier; those trigger gaps are recorded explicitly below rather than silently treating post-merge validation as adequate PR protection.

## Verified lead workflows

| Workflow | Current trigger / scope | Target tier | Protected behavior | Assertion style | Disposition |
| --- | --- | --- | --- | --- | --- |
| `validate-lead-form-guard-v1.yml` | push to `main` when `v2/lead-form-guard-v1.js` changes | PR FAST | lead form validation guard is syntactically valid and still owns minimum input/custom-validity behavior | JS syntax + exact source-string `grep` checks | KEEP; move behavior to deterministic diagnostic before removing text assertions; current push-only trigger leaves no pre-merge protection |
| `validate-lead-idempotency-v1.yml` | push to `main` when `v2/lead-idempotency-v1.php` changes; manual | PR FAST | identical lead payloads fingerprint identically; changed flight/price/comment produce distinct idempotency keys | PHP lint + deterministic executable contract | KEEP; strong refactor-safe diagnostic, but current push-only trigger should eventually become PR protection |
| `validate-lead-price-v1.yml` | push to `main` when `v2/lead-price-v1.php` changes; manual | PR FAST | base/selected price and delta semantics passed into lead context | PHP lint + deterministic executable contract | KEEP; strong refactor-safe diagnostic, but current push-only trigger should eventually become PR protection |
| `validate-lead-search-context.yml` | push to `main` when `v2/lead-search-context.js` changes; manual | PR FAST | lead context retains child ages/lifecycle integration and routes through the canonical V2 lead adapter | JS syntax + exact source-string `grep` checks | KEEP; refactor-hostile and currently post-merge only; replace source checks with behavioral context diagnostic before consolidation |
| `validate-lead-ui-race-guard.yml` | PR to `main` for `lead-ui-race-guard-v1.js` or bundle manifest | PR FAST | stale lead UI events from a previous tour are blocked while current-tour events pass | deterministic Node/vm behavioral diagnostic | KEEP; high-value, fast, refactor-safe guard |
| `validate-lead-recovery.yml` | PR to `main` for lead form/race guard; manual | PR BROWSER | error→retry keeps entered data, duplicate and normal success render correct terminal state, controls hide appropriately across 375/768/1440 | isolated local Playwright behavioral harness | KEEP; distinct lead UX/recovery coverage, not a duplicate of race/idempotency guards |

## Confirmed findings

### Four lead contracts are only checked after merge

`validate-lead-form-guard-v1`, `validate-lead-idempotency-v1`, `validate-lead-price-v1` and `validate-lead-search-context` are all cheap deterministic checks whose natural ownership is PR FAST, but their current automatic trigger is `push` to `main` rather than `pull_request`.

This means the repository has the checks, but a breaking change to those files can merge before the corresponding guard runs. Do not simply duplicate all four as new workflows: when the lead family is consolidated, move the same executable contracts behind a shared PR FAST entrypoint while preserving post-merge/release defense where useful.

### Lead idempotency and price checks are already good consolidation primitives

Both PHP workflows execute the contract rather than inspect implementation text. They are suitable candidates to move under `scripts/ci/lead/` and call from a common fast workflow without weakening behavior.

They intentionally protect different semantics: idempotency guards duplicate suppression/fingerprinting, while price guards selected-vs-base price representation. They are not duplicates.

### Lead form and search-context guards remain refactor-hostile

`validate-lead-form-guard-v1` asserts literal values and `setCustomValidity`/`version:1` source strings. `validate-lead-search-context` asserts literal references to `childAges`, `V2SearchLifecycle` and `lead-adapter-v2.php`.

These checks protect valuable contracts, but they can fail after a behavior-preserving module extraction or rename. Before changing them, build deterministic diagnostics that exercise the same public behavior/context object. Only then remove the `grep` assertions.

### Recovery and race guards overlap at a boundary, not as duplicates

The race guard proves stale/current tour event isolation without a browser. The recovery workflow proves visible retry/success/duplicate-success behavior in an isolated browser harness at three widths. The overlap is intentional: stale events must not corrupt the UI that recovery later validates.

Keep both behavioral contracts. Consolidation should factor shared fixture/bootstrap code, not collapse the scenarios.

## Safe consolidation order for the lead family

1. Extract the existing executable PHP price/idempotency checks into `scripts/ci/lead/` without changing assertions.
2. Add equivalent behavioral diagnostics for form validation and search-context construction before touching their source-string guards.
3. Route all cheap deterministic lead checks through PR FAST with complete path ownership.
4. Keep `validate-lead-recovery` as PR BROWSER and factor only its reusable browser bootstrap/fixture if that reduces duplication.
5. Keep production lead bridge/invocation smoke in POST DEPLOY; do not alter endpoint, field mapping, Metrika, or any external lead contract during CI consolidation.

No workflow is approved for deletion by this audit alone.
