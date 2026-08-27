# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and boundaries; this file records current architecture, audit findings and work order.

## Current product phase

**Phase: UX and visual iteration after technical refactor.**

The active product is `v2/`. The focused technical refactor pass is complete and verified. Automatic visual runs remain temporarily paused while there is no live traffic; the visual workflow remains available manually. The current objective is a mobile-first end-to-end UX audit of the search journey without changing Yandex Metrika/goals or the lead-sending mechanism.

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
- `search-filters-ux-v1.js`: primary search form behavior and additional-filter behavior. Its presentation CSS is owned declaratively by `search-filters-ux-v1.css`.
- `primary-meal-ux-v1.js`: promotes meal selection into primary controls. Its DOM placement is deliberately deferred until `DOMContentLoaded` so it is appended after the six primary fields arranged by `search-filters-ux-v1.js`; this prevents the previous ordering race that could place `Питание` first on mobile.
- `search-lifecycle-v6.js`: single owner of search request state, validation, searchId/generation, dirty invalidation, polling and result loading. When parameters change after results exist, it preserves the rendered cards long enough for the stale-results UX layer to mark and disable them; when no cards exist it shows the explicit dirty empty state.
- `search-progress-ux-v1.js`: search progress presentation and recoverable search-error presentation. Non-validation start/status failures now render a clear retry state whose button reuses the existing lifecycle `submit()` path with the current form parameters.
- `results-renderer-v5.js`: result data/rendering and sort order.
- `mobile-results-filters-v1.js`: client-side result filtering on mobile; draft state applies only on explicit action. The apply action now previews the number of matching rendered hotels while the user changes category, rating, meal and maximum price, so restrictive combinations are visible before application.
- `search-dirty-ux-v1.js`: dirty/stale presentation only; stale cards are visually muted and non-interactive until results are refreshed.
- `mobile-search-summary-v1.js`: compact mobile search context.
- `accessibility.js`: accessibility decoration/helper behavior.

### Tour selection / price / flights
- `tour-controller-v4.js`: selected-tour flow.
- `hotel-actions-v3.js`: hotel actions/details integration.
- `room-details-v3.js`: room details. Detailed room content is now progressive disclosure: the compact selected-room control is rendered immediately, while the rooms API, gallery and long description load only after the tourist expands `Подробнее о номере`.
- `flight-price-sync-v1.js`: selected flight and displayed/submitted price synchronization.

### Lead path
- `lead-search-context.js`: search context for lead payload.
- `lead-form-guard-v1.js`: lead form behavior/guarding, explicit field requirements, selection summary, pre-submit contact-purpose note and post-submit success/deduplication confirmation.
- `lead-adapter-v2.php` and `lead-price-v1.php`: active server lead adapter/price handling.

**Protected contract:** do not change the lead-sending mechanism/external contract without explicit user approval.

### Analytics
Active page loads `analytics-v4.js`.

Metrika goal identifiers emitted by the active analytics layer are existing read-only contracts for autopilot. Do not change analytics/Metrika configuration or goal identifiers without explicit user approval.

## Existing quality/deployment infrastructure

- Pushes affecting `v2/**` deploy V2 only through GitHub Actions.
- Predeploy validation syntax-checks active JS/PHP and verifies active module markers.
- Production verification checks page/API/lead endpoint availability and active asset markers.
- Deployment includes a live Tourvisor search smoke and search-continue duplicate check.
- Repository contains focused validators for search lifecycle, results, flights, price sync, lead behavior and UX contracts.
- `visual-v2-baseline.yml` provides a live Chromium visual/DOM audit across 375, 430, 768, 1024 and 1440 px widths, but its automatic push trigger is temporarily disabled; it remains available through `workflow_dispatch` for deliberate/manual verification.

## Completed technical refactor milestone

### A6 — focused whole-project refactor pass
Status: `DONE`

- Removed runtime CSS injection from the primary search filters module and moved presentation ownership into static CSS.
- Removed only proven inactive historical generations of `results-renderer`, `search-continue`, `search-lifecycle` and `tour-controller`.
- Preserved active search behavior, Tourvisor data contracts, analytics configuration/goals and lead transport.
- Final active-contract, isolation, live and deploy checks passed on the refactor milestone.

## Current UX findings

### A2.1 — Primary meal ordering race
The primary meal module previously appended `Питание` into `.main-fields` immediately, while `search-filters-ux-v1.js` registered its six-field layout arrangement for `DOMContentLoaded`. Because the six primary fields were appended after the meal field, the meal control could become visually first despite being tagged as `primary-step-7`.

**Fixed:** `primary-meal-ux-v1.js` now registers its placement on `DOMContentLoaded` after the search-filter layout listener, preserving the intended order: the six core trip parameters first, then meal selection. No meal values, search payload, analytics or lead behavior changed.

### A2.2 — Dirty-search stale-results conflict
`search-dirty-ux-v1.js` already provided a safe stale-results mode: old cards are visibly marked as previous-search results, muted and made non-interactive, with a prominent `Обновить результаты` action. However, `search-lifecycle-v6.js` cleared the results DOM before emitting the dirty event, so the stale-results UX could not activate when cards were present.

**Fixed:** dirty invalidation still cancels polling, advances generation, clears `searchId`, hides result tools/selected tour and invalidates runtime state, but now preserves already-rendered hotel cards for the stale UX layer to mark and disable. If no hotel cards are present, the existing explicit dirty empty state is shown instead. Search payload, Tourvisor contracts, analytics and lead transport are unchanged.

### A2.3 — Search error recovery clarity
Search start/status failures previously collapsed the rich progress UI back to plain status text. The primary search button was re-enabled, but the failure state did not provide an obvious recovery action, especially on mobile after the loading/progress state.

**Fixed:** non-validation search errors now render a dedicated accessible error state with concise context and a `Повторить поиск` action. Retry calls the existing `V2SearchLifecycle.submit()` path, preserving the form values and all existing search/API contracts. The mobile layout makes the retry action full width. Search parameters, Tourvisor API behavior, analytics and lead transport are unchanged.

Verification for A2.3: active V2 contract, V2 isolation and live Tourvisor validation passed on commit `6a8ef1eb3e1d9bdd2e2c89cba757ad8813cdeb67`. Deliberate visual confirmation remains deferred to the manual visual pass while automatic visual execution is paused by project decision.

### A2.4 — Mobile result-filter outcome preview
The mobile result-filter sheet previously let the user draft category, rating, meal and maximum-price conditions but the main apply action stayed as a generic `Показать`. A restrictive combination could therefore produce an empty result only after closing/applying the sheet, making mobile filtering less predictable.

**Fixed:** the existing client-side filter matcher can now evaluate either active or draft criteria. While the filter sheet is open, its primary action updates immediately to `Показать N` as category/rating/meal selections or the maximum-price input change, and exposes the same count in its accessible label. Applying still happens only on explicit action and only filters the already-rendered result set; server search parameters, Tourvisor API, analytics and lead transport are unchanged.

Verification for A2.4: active V2 contract, V2 isolation and live Tourvisor validation passed on commit `d7bb2494f230143323406a54cebcab0f120d2be2`. Deliberate visual confirmation remains deferred to the manual visual pass while automatic visual execution is paused by project decision.

### A2.5 — Lead entry and completion clarity
The lead form previously remained visually editable after a confirmed write, leaving the tourist without a clear completion state or obvious way back to the offers. Before submission, the required phone also lacked a direct explanation of why the contact was needed.

**Fixed:** confirmed new and deduplicated leads now become a dedicated success state with the stored selection context, lead number when available and a `Вернуться к предложениям` action. The editable fields disappear only after confirmed success; errors preserve entered data and the retry action. Before submission, a neutral note explains that the selected tour is sent to the manager and the supplied phone is used to contact the tourist about that request. Lead payload, deduplication, consent, transport and analytics are unchanged.

Verification for A2.5: focused lead guard, active V2 contract, V2 isolation, live Tourvisor validation and production deploy/live search smoke passed on the final lead UX code (`8ce16c768b0eefd98a55d72a32585018d49933e2`).

### A2.6 — Selected-room content blocking the conversion path
`room-details-v3.js` previously fetched the room API immediately after tour selection and inserted the full room gallery/description before the flight block. With up to ten room photos this could make the mobile selected-tour page substantially longer before the tourist reached the higher-priority flight/price verification and lead path.

**Fixed:** the selected room now renders as a compact `Подробнее о номере` disclosure. The rooms API call, image gallery and long descriptive content are deferred until explicit expansion; collapse/expand does not refetch already-loaded room data, and retry remains available on room API failure. Tour selection, flight/price state, Tourvisor contracts, lead payload and analytics are unchanged.

Verification for A2.6: active V2 contract, V2 isolation and live Tourvisor validation passed on final code commit `dbb42147bffef22f475cd73d2769c4045ecebb93`; production deploy verification was still running when this state note was written.

## Autopilot queue

### A6 — TECH DEBT: focused whole-project refactor pass
Status: `DONE`

### A1 — VISUAL / UX: baseline and regression harness
Status: `DEFERRED`

- Harness exists and remains manually runnable.
- Automatic push execution remains paused while there is no live traffic.
- Use deliberate visual verification for user-facing changes and re-enable broader automatic coverage when visual iteration stabilizes.

### A2 — UX: end-to-end search journey audit
Status: `IN PROGRESS`

Audit the full tourist journey: search intent -> form -> waiting -> comparing results -> hotel/tour understanding -> flights/price -> lead. Prioritize mobile.

Current work order:
- primary-form ordering/friction: first concrete ordering race fixed;
- waiting/progress, stale-search and retry/error transitions: three concrete issues fixed;
- mobile result-filter discoverability/outcome preview: first concrete issue fixed;
- lead entry/completion: success, duplicate, retry and contact-purpose clarity fixed;
- selected-tour length: room details changed to on-demand progressive disclosure;
- next: inspect remaining selected-tour hotel description and fact hierarchy for mobile density/context loss;
- then return to result-card comparison/information hierarchy and broader conversion-readiness audit.

### A3 — VISUAL: consolidate unstable cascade areas
Status: `QUEUED`

Use the ownership information produced by A6 and A2 evidence to consolidate the highest-risk visual layers incrementally. Do not perform a wholesale redesign.

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

- During A2: verify functional contracts for every UX change and inspect relevant responsive/user states deliberately.
- Keep automatic visual baseline execution manual-only until the project explicitly re-enables it.
- Approximately weekly during active development: reread the whole repository and refresh this file if architecture/priorities changed.
- After major milestones: architecture + UX + visual audit before moving to the next phase.

## Blocked/deferred

A blocked item does not stop independent work. Record it here and move to the next safe queue item.

Deferred by project decision:
- automatic Visual V2 baseline runs while there is no live traffic.
