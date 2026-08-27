# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and boundaries; `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current product phase

**Phase: product UX/visual development before and during live traffic.**

The active product is `v2/`. The major technical refactor, correctness hardening, SEO foundation and residual pre-traffic cleanup are complete. The next development phase is product-led: make the full AnyTour experience visually coherent, modern, fast and conversion-oriented, with UX and visual quality as first-class priorities.

Current primary task: **B1 — Visual foundation (`IN PROGRESS`)**.

Parallel waiting task: **A8 — live traffic feedback loop (`WAITING FOR TRAFFIC`)**. Once advertising traffic starts, live production evidence must immediately influence prioritization without stopping safe B-series product work.

The user has explicitly approved redesign work across the whole site surface within this project, not only the search widget. Existing Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected and must not be changed without explicit approval.

## Product quality priorities

1. Production breakage, lead loss and incorrect data remain highest severity.
2. UX is a primary product priority, not secondary polish.
3. Visual coherence and responsive stability are high-priority product requirements.
4. Regularly inspect the running product at 375, 430, 768, 1024 and 1440 px widths when user-facing visual changes are made.
5. Prefer improving the complete user journey over adding isolated features.
6. Do not add another CSS override layer merely to compensate for an existing override layer; consolidate toward a coherent design system.
7. Preserve protected analytics and lead transport contracts while freely improving their presentation/entry UX.

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

The mechanism/external contract that actually sends the lead must not be changed without explicit user approval. Presentation, placement, CTA hierarchy and form UX may be improved while preserving transport semantics.

### Analytics — protected
The active page loads `analytics-v4.js`. Existing identifiers/configuration are read-only for autopilot. Historical analytics files are not retired without separate contract evidence.

## B-series product roadmap

### B1 — VISUAL FOUNDATION
Status: `IN PROGRESS`

Objective: make the full AnyTour V2 surface feel like one modern travel product rather than a legacy site shell containing a newer search widget.

Scope:
- redesign global header/navigation within this project;
- preserve logo/phone/navigation contracts while modernizing layout and responsive behavior;
- create compact branded first-screen/hero and trust cues;
- establish product-shell tokens for container widths, spacing, typography, radii, surfaces, shadows, buttons and chips;
- eliminate the strongest visual conflict between legacy header styles and V2 cards/forms;
- visually verify 375/430/768/1024/1440 before merge.

Current implementation branch: `ux/b1-visual-foundation`, PR #3.

### B2 — SEARCH EXPERIENCE 2.0
Status: `QUEUED`

Objective: turn the working search form into a fast, intuitive travel-search composer.

Scope:
- improve date selection toward a proper travel calendar/range experience;
- retain effective quick-night presets and guest steppers;
- reduce mobile vertical friction before the primary CTA;
- improve hierarchy of stars/meal/secondary filters;
- evolve advanced filters toward an intentional sheet/panel experience;
- reduce or eliminate initial JS-driven layout shift by rendering closer to the final component structure server-side;
- preserve search lifecycle ownership and Tourvisor request semantics.

### B3 — RESULTS EXPERIENCE 2.0
Status: `QUEUED`

Objective: make results decision-oriented instead of data-dump oriented.

Scope:
- redesign hotel cards around the reasons a tourist would choose an option;
- strengthen photo prominence and price hierarchy;
- surface useful facts such as rating, meal, sea distance, nights and direct-flight status when reliable data is available;
- visually separate hotel choice from specific tour variant choice;
- improve quick result filtering after search, especially mobile chips/sheet behavior;
- keep sorting and result data ownership in the active renderer.

### B4 — TOUR / CHECKOUT EXPERIENCE 2.0
Status: `QUEUED`

Objective: transform the selected-tour screen from a technical fact list into a clear travel checkout summary.

Scope:
- restructure selected tour into hotel, trip, room/meal, flight and total-price sections;
- improve flight presentation into an understandable route/timeline while preserving exact data;
- keep price/flight synchronization intact;
- shorten the cognitive path from tour choice to conversion.

### B5 — TRUST & CONVERSION UX
Status: `QUEUED`

Objective: increase confidence and make manager assistance feel timely rather than intrusive.

Scope:
- add appropriate trust signals and reassurance near high-intent decisions;
- improve empty/error/recovery states;
- introduce stronger conversion surfaces without altering the lead transport contract;
- improve CTA copy/hierarchy based on user intent and stage.

### B6 — VISUAL REGRESSION BASELINE
Status: `QUEUED`

Objective: lock the redesigned product into a repeatable visual safety net.

Scope:
- run and refine screenshot coverage at 375, 430, 768, 1024 and 1440;
- cover initial search, opened filters, children, results and selected-tour states where practical;
- promote the visual harness from deferred/manual-only usage once the new visual foundation is stable enough for meaningful baselines.

### B7 — PERFORMANCE & VISUAL STABILITY
Status: `QUEUED`

Objective: reduce visual instability and client overhead after the redesign.

Scope:
- consolidate CSS ownership and repeated tokens;
- reduce unnecessary cascade/override complexity;
- reduce hydration/layout shifts;
- optimize image loading and lazy behavior;
- maintain responsive stability across relevant viewports.

### B8 — LIVE PRODUCT OPTIMIZATION
Status: `WAITING FOR TRAFFIC`

Objective: use real user behavior to drive the next iteration after advertising starts.

Scope:
- inspect real searches, errors, result interactions, tour selections and lead behavior;
- prioritize observed mobile drop-offs and conversion friction;
- feed evidence back into B2-B5 continuously;
- never change Metrika goals/configuration merely to make reporting easier without explicit approval.

## Completed A-series milestones

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

Real search, continuation, tour selection, rooms, flights and price behavior were validated against production. A later isolated `search_continue` failure was diagnostically repeated successfully and classified as transient; no unsafe retry was added to the non-idempotent continuation action.

### A7 — SEO FOUNDATION
Status: `DONE`

Initial URL/state, metadata/indexability, architecture and future landing-page integration work was completed incrementally without creating a parallel product.

### A9 — HARDENING: residual V2 generation and CI cleanup
Status: `DONE`

Residual inactive generations, static assets, markers and redundant workflows were removed with contract coverage preserved. Protected or potentially externally callable residuals such as `api.php`, `phone-config.php`, historical analytics and lead adapters remain intentionally untouched because retirement has not been independently proven.

## Deferred / parallel waiting

### A1 — VISUAL / UX: baseline and regression harness
Status: `DEFERRED -> superseded by B6 once redesign stabilizes`

The existing harness remains available. B6 owns the decision to establish the new baseline after the redesign is stable enough that screenshots represent a durable visual contract.

### A8 — LIVE TRAFFIC FEEDBACK LOOP
Status: `WAITING FOR TRAFFIC`

A8 remains the operational live-traffic safety loop. B8 is the product-optimization continuation of the same evidence once traffic exists.

## Next work order

1. Complete B1 on the isolated branch: header, first screen, core design tokens and viewport validation.
2. Merge/deploy B1 only after active contract + relevant live checks + visual inspection are green.
3. Advance to B2 search experience, then B3 results and B4 checkout/selected-tour.
4. Implement B5 trust/conversion surfaces while preserving lead transport and analytics contracts.
5. Establish B6 visual baselines after the redesigned flow is visually stable.
6. Perform B7 CSS/performance/CLS consolidation after behavior and design settle.
7. Activate A8/B8 immediately when real advertising traffic starts and reprioritize from evidence.

## Hard boundaries carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deployment is V2 scope only unless explicitly extending the site surface within this repository/project.
- Do not modify neighboring projects.
- Design and UX across the site's surfaces inside this project may be improved proactively.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
