# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and boundaries; this file records current architecture, audit findings and work order.

## Current product phase

**Phase: technical refactor before the next UX/visual iteration of V2.**

The active product is `v2/`. By explicit project decision, automatic visual runs are temporarily paused while there is no live traffic. The current objective is to reduce proven technical debt and unstable CSS/runtime ownership without changing product behavior, Yandex Metrika/goals or the lead-sending mechanism. After this focused pass, autopilot returns to mobile-first UX and visual work.

## Active architecture reconstructed from main

### Server/page composition
- `v2/index.php` owns initial server-rendered page state, header/navigation, search markup, active CSS/JS order and public V2 config.
- `v2/form-defaults.php` owns initial search defaults.
- `v2/assets.php` owns asset URL/version handling.
- `v2/analytics-config.php` exposes only the configured public Metrika counter id.
- `v2/privacy-config.php` owns the privacy URL.

### Tourvisor/search backend
- `v2/api-v2.php` is the active V2 API gateway and talks directly to Tourvisor.
- Search is asynchronous: start -> status polling -> results, with continue support.
- Catalog hydration is handled separately and cached where possible.

### Active client search ownership
- `catalogs-v2.js`: Tourvisor catalogs and child-age controls.
- `search-filters-ux-v1.js`: primary search form behavior and additional-filter behavior. Its presentation CSS is now owned declaratively by `search-filters-ux-v1.css`; runtime style injection was removed during the focused refactor pass.
- `primary-meal-ux-v1.js`: promotes meal selection into primary controls.
- `search-lifecycle-v6.js`: single owner of search request state, validation, searchId/generation, dirty invalidation, polling and result loading.
- `search-progress-ux-v1.js`: search progress presentation only.
- `results-renderer-v5.js`: result data/rendering and sort order.
- `mobile-results-filters-v1.js`: client-side result filtering on mobile; draft state applies only on explicit action.
- `search-dirty-ux-v1.js`: dirty/stale presentation only.
- `mobile-search-summary-v1.js`: compact mobile search context.
- `accessibility.js`: accessibility decoration/helper behavior.

### Tour selection / price / flights
- `tour-controller-v4.js`: selected-tour flow.
- `hotel-actions-v3.js`: hotel actions/details integration.
- `room-details-v3.js`: room details.
- `flight-price-sync-v1.js`: selected flight and displayed/submitted price synchronization.

### Lead path
- `lead-search-context.js`: search context for lead payload.
- `lead-form-guard-v1.js`: lead form behavior/guarding.
- `lead-adapter-v2.php` and `lead-price-v1.php`: active server lead adapter/price handling.

**Protected contract:** do not change the lead-sending mechanism/external contract without explicit user approval.

### Analytics
Active page loads `analytics-v4.js`.

Metrika goal identifiers emitted by the active analytics layer are:
- `V2_SEARCH_STARTED`
- `V2_SEARCH_COMPLETE`
- `V2_SEARCH_ERROR`
- `V2_SEARCH_CONTINUED`
- `V2_TOUR_SELECTED`
- `V2_FLIGHT_SELECTED`
- `V2_LEAD_STARTED`
- `V2_LEAD_SUBMITTED`
- `V2_LEAD_ERROR`
- `V2_SORT_CHANGED`
- `V2_HOTEL_OPEN`

These are existing code contracts and are **read-only for autopilot** unless the user explicitly authorizes analytics/Metrika changes.

Historical analytics generations may still exist in the repository, but they are not active entrypoints. Because analytics is protected, the technical refactor must not modify or remove analytics code without explicit approval.

## Existing quality/deployment infrastructure

- Pushes affecting `v2/**` deploy V2 only through GitHub Actions.
- Predeploy validation syntax-checks active JS/PHP and verifies active module markers.
- Production verification checks page/API/lead endpoint availability and active asset markers.
- Deployment includes a live Tourvisor search smoke and search-continue duplicate check.
- Repository contains focused validators for search lifecycle, results, flights, price sync, lead behavior and UX contracts.
- `visual-v2-baseline.yml` provides a live Chromium visual/DOM audit across 375, 430, 768, 1024 and 1440 px widths, but its automatic push trigger is temporarily disabled; it remains available through `workflow_dispatch` for deliberate/manual verification.

## Current refactor findings

### A6.1 — Runtime CSS ownership
`search-filters-ux-v1.js` previously injected a large `<style>` block after page load. This made the JS module a late cascade owner and could override static fixes unexpectedly.

**Done:** extracted those rules to `search-filters-ux-v1.css`, loaded it declaratively from `v2/index.php`, removed runtime style creation and moved the reset-button spacing from inline style to a CSS class. The focused validator now guards this separation.

### A6.2 — Proven inactive generations
Reference/entrypoint analysis identified older search-continuation generations that are not loaded by the active V2 entrypoint. Cleanup is proceeding in small commits; active search lifecycle/result contracts remain the verification boundary.

Rule: do not delete files merely because a newer numbered generation exists. Remove only when active entrypoint/reference analysis and checks prove the older generation inactive.

### A6.3 — CSS/visual layer complexity
The active page still composes multiple historical and feature-specific CSS layers. Continue reducing ambiguous ownership incrementally, but do not combine refactor with product/UX changes.

## Deferred visual finding

The latest mobile evidence showed the primary meal control (`Питание`) in an unexpected visual position. This is **explicitly deferred** while technical refactor is in progress and there is no live traffic. Do not spend the refactor pass redesigning or reordering the form.

Automatic visual baseline execution is also deferred/manual-only for this phase. Re-enable and extend it when returning to UX/visual work.

## Autopilot queue

### A6 — TECH DEBT: focused whole-project refactor pass
Status: `IN PROGRESS`

- Complete active-entrypoint/reference analysis across V2.
- Remove only proven inactive generations in small safe changes.
- Reduce runtime CSS/DOM presentation ownership where it can be separated without behavior changes.
- Strengthen focused validators around each refactored boundary.
- Preserve active search behavior, Tourvisor data contracts, analytics configuration/goals and lead transport.

DONE evidence: focused validators + active V2 contract + production/deploy checks for affected active files.

### A1 — VISUAL / UX: baseline and regression harness
Status: `DEFERRED`

- Harness exists and remains manually runnable.
- Automatic push execution is paused during technical refactor.
- Resume after A6, then review the deferred mobile primary-control ordering and other concrete screenshots before expanding coverage.

### A2 — UX: end-to-end search journey audit
Status: `QUEUED`

After A6/A1 resume, audit the full tourist journey: search intent -> form -> waiting -> comparing results -> hotel/tour understanding -> flights/price -> lead. Prioritize mobile.

### A3 — VISUAL: consolidate unstable cascade areas
Status: `QUEUED`

Use the ownership information produced by A6 to consolidate the highest-risk visual layers incrementally. Do not perform a wholesale redesign.

### A4 — PRODUCT/UX: conversion readiness before live traffic
Status: `QUEUED`

Verify CTA hierarchy, trust/information hierarchy, price clarity, selected-tour path and lead entry. Do not change Metrika goals or lead transport.

### A5 — BUG/DATA: live Tourvisor correctness pass
Status: `QUEUED`

Recheck real searches, catalog dependencies, continued results, rooms, flights, selected flight price and stale-search invalidation against production responses.

### A7 — SEO FOUNDATION: prepare the search as a site platform
Status: `QUEUED`

Audit URL/state strategy, server-rendered content boundaries, performance, metadata/schema opportunities and future country/resort/hotel landing-page integration. Produce incremental architecture work rather than a parallel rewrite.

### A8 — LIVE TRAFFIC FEEDBACK LOOP
Status: `WAITING FOR TRAFFIC`

When live traffic is enabled, prioritize real production errors, search behavior, UX drop-offs and lead evidence. Analytics identifiers/configuration remain protected.

## Recurring autopilot maintenance

- During A6: verify behavior/contracts for every active-file refactor; do not use the deferred visual finding as scope for redesign.
- On return to UX/visual work: re-enable deliberate visual verification for affected states/viewports before increasing traffic.
- Approximately weekly during active development: reread the whole repository and refresh this file if architecture/priorities changed.
- After major milestones: architecture + UX + visual audit before moving to the next phase.

## Blocked/deferred

A blocked item does not stop independent work. Record it here and move to the next safe queue item.

Deferred by project decision:
- automatic Visual V2 baseline runs during A6;
- mobile `Питание` ordering/visual issue until the UX/visual phase resumes.
