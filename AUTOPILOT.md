# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and boundaries; `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current product phase

**Phase: residual pre-traffic hardening.**

The active product is `v2/`. The major technical refactor, end-to-end UX audit, visual cascade consolidation, conversion-readiness pass, live Tourvisor correctness pass and initial SEO-foundation work are complete. Real advertising traffic has not yet been launched, so the live-traffic feedback loop remains waiting while safe pre-traffic hardening continues.

Current task: **A9 — residual V2 generation and CI cleanup**.

The objective is to reduce stale generations and redundant CI without weakening active search, data, UX, analytics or lead contracts. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected and must not be changed without explicit approval.

## Active architecture

### Page and configuration
- `v2/index.php` owns server-rendered page composition, active CSS/JS order and public V2 config.
- `v2/form-defaults.php` owns initial search defaults.
- `v2/assets.php` + `v2/asset-version-v1.php` own content-based asset versioning.
- `v2/analytics-config.php` exposes the existing configured Metrika counter id; it is read-only for autopilot.
- `v2/privacy-config.php` owns the privacy URL.

### Tourvisor/search backend
- `v2/api-v2.php` is the active V2 Tourvisor gateway.
- `v2/catalog-cache-v1.php` provides catalog TTL caching and is required by the active API.
- Search flow is asynchronous: start -> status -> results, with explicit continuation.

### Active client search
- `runtime-retry-policy.js`: operation-specific retry policy; non-idempotent search start/continue are deliberately not automatically retried.
- `runtime-v3.js`: shared V2 runtime/state helpers.
- `catalogs-v2.js`: Tourvisor catalogs and child-age controls.
- `search-filters-ux-v1.js`: primary/advanced search form behavior.
- `primary-meal-ux-v1.js`: primary meal control placement.
- `search-lifecycle-v6.js`: search request state, validation, generation/searchId ownership, polling and dirty invalidation.
- `search-progress-ux-v1.js`: waiting/progress/error recovery presentation.
- `results-renderer-v5.js`: result normalization, rendering and sorting.
- `search-continue-v6.js`: explicit additional-results continuation.
- `mobile-results-filters-v1.js`: client-side mobile result filtering with draft outcome preview.
- `search-dirty-ux-v1.js`: stale-result presentation and interaction lockout.
- `mobile-search-summary-v1.js`: compact mobile search context.
- `accessibility.js`: accessibility helper behavior.

### Tour selection / flights / price
- `tour-controller-v4.js`: selected-tour flow and stale-response generation guards.
- `hotel-actions-v3.js`: hotel action/detail behavior.
- `room-details-v3.js`: lazy progressive disclosure for detailed room information.
- `selected-tour-description-v1.js`: selected-tour description presentation.
- `flight-price-sync-v1.js`: selected flight and displayed/submitted price synchronization.

### Lead path — protected transport contract
- `lead-search-context.js`: search context included with the lead.
- `lead-form-guard-v1.js`: lead-entry UX, requirements, dedup/success presentation.
- `lead-adapter-v2.php`, `lead-price-v1.php`, `lead-idempotency-v1.php`: active server lead support.

The mechanism/external contract that actually sends the lead must not be changed without explicit user approval. Historical/external-callable lead files are not removed merely because `index.php` does not reference them.

### Analytics — protected
The active page loads `analytics-v4.js`. Existing identifiers/configuration are read-only for autopilot. Historical analytics files are not retired without separate contract evidence.

## Quality and deployment infrastructure

- GitHub Actions jobs now run on `[self-hosted, Linux, X64]`.
- The temporary write-capable self-hosted migration workflow has been removed after migration completion; no `ubuntu-latest` jobs remain.
- Pushes affecting `v2/**` deploy only the V2 directory through `deploy.yml`.
- `validate-v2-active.yml` is the consolidated static/contract validator. It currently covers:
  - all active JS syntax and required active modules;
  - results sorting and partial-data handling;
  - retry-policy semantics;
  - advanced search-filter UX ownership/contracts;
  - hotel/room contracts;
  - flight-price synchronization invariants;
  - retired generation/static-asset guards;
  - stale-response generation guards;
  - active PHP syntax;
  - content-based asset-version semantics;
  - catalog-cache hit/TTL/parameter isolation;
  - active dependency closure and forbidden cross-project dependencies;
  - deployment isolation.
- Dedicated live workflows remain for checks that are materially different from static contract validation: catalog consistency, result detail, rooms, tours, tour selection, flights and flight pricing.
- Lead and analytics validators remain separate because their contracts are protected and commercially sensitive.
- `validate-v2-user-journey.yml`, SEO validation and the manual visual baseline remain separate end-to-end/specialized checks.
- `visual-v2-baseline.yml` remains manually runnable; automatic push execution stays deferred while there is no live advertising traffic.

## Completed product milestones

### A6 — TECH DEBT: focused whole-project refactor
Status: `DONE`

Historical result/search/tour generations were reduced, ownership was clarified and active contracts were preserved.

### A2 — UX: end-to-end search journey audit
Status: `DONE`

Material fixes included primary meal ordering, stale-result handling, search retry/error clarity, mobile filter outcome preview, lead completion clarity and lazy room-detail disclosure.

### A3 — VISUAL: consolidate unstable cascade areas
Status: `DONE`

Highest-risk visual ownership/cascade areas were incrementally consolidated without a wholesale redesign.

### A4 — PRODUCT/UX: conversion readiness before live traffic
Status: `DONE`

CTA hierarchy, selected-tour path, price/flight clarity and lead entry were audited and hardened within protected contracts.

### A5 — BUG/DATA: live Tourvisor correctness pass
Status: `DONE`

Real search, continuation, tour selection, rooms, flights and price behavior were validated against production. A later isolated `search_continue` failure was diagnostically repeated successfully (HTTP 200, results 25 -> 100, duplicate hotel ids 0) and classified as transient; no unsafe retry was added to the non-idempotent continuation action.

### A7 — SEO FOUNDATION
Status: `DONE`

Initial URL/state, metadata/indexability, architecture and future landing-page integration work was completed incrementally without creating a parallel product.

## Current task

### A9 — HARDENING: residual V2 generation and CI cleanup
Status: `IN PROGRESS`

Completed so far:
- removed 17 proven inactive JS generations/helpers;
- removed 8 unused local static assets;
- removed 3 obsolete V2 smoke/deploy marker files that had no remaining readers;
- removed 14 stale, redundant or completed migration workflows in total;
- consolidated unique generation, asset-version, hotel/room, flight-price, results, retry-policy, dependency-isolation, catalog-cache and search-filter UX checks into the active V2 contract instead of dropping coverage;
- completed migration of Actions jobs to the self-hosted runner and removed the temporary mass-rewrite/push workflow afterward.

Protected or externally callable residuals such as `api.php`, `phone-config.php`, historical analytics and lead adapters remain untouched unless separate evidence proves retirement is safe.

Current verification state: the first new self-hosted consolidation block passed in active-contract run `33056151427`. Final marker-cleanup head `cd342950305359267034550e20478502b234e5f6` has active-contract, deploy and live checks queued behind the self-hosted runner; they are pending evidence, not failures. Exact resume state is recorded in `AUTOPILOT_STATE.json`.

## Deferred / waiting

### A1 — VISUAL / UX: baseline and regression harness
Status: `DEFERRED`

Harness exists and remains manually runnable. Automatic push execution remains paused by project decision while there is no live advertising traffic.

### A8 — LIVE TRAFFIC FEEDBACK LOOP
Status: `WAITING FOR TRAFFIC`

When advertising traffic begins, real production errors, search behavior, UX drop-offs and lead evidence become the primary feedback loop. Do not change Metrika/goals to improve measurement without explicit approval.

## Next work order

1. Collect active-contract, deploy and live evidence for the current pending V2 head; diagnose any failure before further V2 changes.
2. Continue A9 only where retirement/consolidation is demonstrably safe and does not weaken specialized live, lead, analytics, SEO, journey or visual coverage.
3. Audit residual externally callable endpoints by evidence rather than by repository-reference absence; keep them when external use cannot be ruled out.
4. Once A9 has no further justified safe cleanup and all pending evidence is green, close A9 and remain ready for A8/live traffic.

## Hard boundaries carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deployment is V2 scope only.
- Do not modify neighboring projects.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
