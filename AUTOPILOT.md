# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and boundaries; this file records current architecture, audit findings and work order.

## Current product phase

**Phase: pre-live visual/UX stabilization of V2.**

The active product is `v2/`. The immediate objective is to make the search visually stable, easy to understand and trustworthy across mobile and desktop before deliberately increasing live advertising traffic.

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
- `search-filters-ux-v1.js`: primary search form UX and additional search-filter presentation.
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

Historical `analytics.js` / `analytics-v3.js` files exist but are not the active analytics entrypoint in `v2/index.php`.

## Existing quality/deployment infrastructure

- Pushes affecting `v2/**` deploy V2 only through GitHub Actions.
- Predeploy validation syntax-checks active JS/PHP and verifies active module markers.
- Production verification checks page/API/lead endpoint availability and active asset markers.
- Deployment includes a live Tourvisor search smoke and search-continue duplicate check.
- Repository contains focused validators for analytics, search lifecycle, results, flights, price sync, lead behavior and UX contracts.
- `visual-v2-baseline.yml` now provides a live Chromium visual/DOM audit across 375, 430, 768, 1024 and 1440 px widths, capturing initial form, opened filters and child controls while checking overflow, bad initial visibility and browser errors.

This is useful regression coverage, but it does not replace human review of the captured visual evidence.

## First audit findings

### P0/P1 — visual stability gap
The project had strong code/contract smoke coverage but no true browser visual regression gate. Given repeated real-world visual breakage, this was the largest quality-process gap.

A first browser audit harness is now implemented. It captures repeatable screenshots and hard-fails on important measurable layout/initial-state defects. Next step is to inspect the first artifact set and convert concrete findings into fixes/baselines.

### P1 — CSS/visual layer complexity
The active page currently composes many CSS layers (`app`, `enhancements`, room/selected-tour/design/hotel/tour/search-state/brand/results/checkout/header and several UX-specific stylesheets). This makes cascade regressions likely even though recent work correctly started extracting JS-injected styles into explicit CSS.

Direction: stabilize first; then consolidate tokens/ownership incrementally without mixing refactor and behavior changes.

### P1 — UX architecture is moving in the right direction
State ownership is explicitly documented and recent commits have reduced hidden dynamic UX loading and JS style injection. Preserve this architecture: one owner for search state, one owner for result data, presentation modules consume events rather than reaching into internals.

### P1 — initial/live UX needs systematic state review
Visual verification must cover more than the initial form. Critical states are:
- initial load/catalog loading;
- child controls;
- additional filters open/closed;
- validation errors;
- active search/progress;
- partial/complete results;
- mobile result filters;
- dirty search after parameter changes;
- empty/error state;
- hotel details;
- selected tour;
- flight selection and price update;
- lead form success/error/duplicate-safe behavior.

### P2 — legacy/parallel implementation debt
`v2/` still contains older/parallel generations such as `analytics.js`, `analytics-v3.js`, older runtime/action files and multiple historical CSS/header layers. Do not delete them blindly. During the focused refactor pass, prove which are inactive through entrypoint/reference analysis and tests, then remove or archive only when safe.

### P2 — SEO foundation is not yet the immediate bottleneck
The long-term product needs SEO-friendly architecture, but the immediate bottleneck is search UX/visual stability. SEO work should currently focus on avoiding architectural dead ends rather than building large content systems before the search experience is stable.

## Autopilot queue

### A1 — VISUAL / UX: baseline and regression harness
Status: `IN PROGRESS`

- Browser audit harness: implemented.
- Viewports: 375 / 430 / 768 / 1024 / 1440.
- Captured states: initial, additional filters open, children controls.
- Automated checks: HTTP status, horizontal overflow, incorrect initial results/status/selected-tour visibility, child-control visibility and page errors.
- Current run: first live baseline execution started from commit `227194d7b8a8171122604c06f9cb2d1bca20fef8`.
- Next: inspect first report/screenshots, fix concrete regressions, then extend coverage to results/selected-tour/lead states.

DONE evidence: screenshots/visual checks + functional regressions + production verification.

### A2 — UX: end-to-end search journey audit
Status: `QUEUED`

Audit the full user journey as a tourist rather than module-by-module:
search intent -> form -> waiting -> comparing results -> hotel/tour understanding -> flights/price -> lead.

Reduce confusion, unnecessary decisions and dead ends. Prioritize mobile.

### A3 — VISUAL: consolidate unstable cascade areas
Status: `QUEUED`

After A1 establishes a safety net, identify competing selectors/tokens and consolidate the highest-risk visual layers. Do not perform a wholesale redesign/refactor in one change.

### A4 — PRODUCT/UX: conversion readiness before live traffic
Status: `QUEUED`

Verify that the primary CTA hierarchy, trust/information hierarchy, price clarity, selected-tour path and lead entry are obvious and coherent. Do not change Metrika goals or lead transport.

### A5 — BUG/DATA: live Tourvisor correctness pass
Status: `QUEUED`

Recheck real searches, catalog dependencies, continued results, rooms, flights, selected flight price and stale-search invalidation against production responses.

### A6 — TECH DEBT: focused whole-project refactor pass
Status: `QUEUED`

Once visual/UX stability is guarded, perform reference/ownership analysis across all V2 files, remove proven dead generations, reduce CSS/runtime duplication and strengthen tests around risky boundaries.

### A7 — SEO FOUNDATION: prepare the search as a site platform
Status: `QUEUED`

Audit URL/state strategy, server-rendered content boundaries, performance, metadata/schema opportunities and future country/resort/hotel landing-page integration. Produce incremental architecture work rather than a parallel rewrite.

### A8 — LIVE TRAFFIC FEEDBACK LOOP
Status: `WAITING FOR TRAFFIC`

When the user enables live traffic, prioritize real production errors, search behavior, UX drop-offs and lead evidence. Analytics identifiers/configuration remain protected.

## Recurring autopilot maintenance

- On every user-facing change: visual verification of affected states/viewports.
- Approximately weekly during active development: reread the whole repository and refresh this file if architecture/priorities changed.
- Approximately every 2 weeks if warranted: focused refactor pass.
- After major milestones: architecture + UX + visual audit before moving to the next phase.

## Blocked/deferred

A blocked item does not stop independent work. Record it here and move to the next safe queue item.

Currently: none.
