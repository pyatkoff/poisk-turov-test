# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and hard boundaries; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current product phase

**Production-ready V2 awaiting real traffic evidence.**

The active product is `v2/`. Major correctness hardening, technical refactor, SEO foundation and B1–B7 product/visual/performance work are complete. **B8 Live Product Optimization** and **A8 Operational live traffic feedback loop** are `WAITING_FOR_TRAFFIC`; real advertising/live-user evidence immediately outranks speculative polish when it appears.

Protected without explicit approval: Yandex Metrika configuration/goals and the existing lead-sending mechanism/external contract. Work stays inside `pyatkoff/poisk-turov-test`; production deployment stays V2-only.

## Product quality priorities

1. Production breakage, lead loss and incorrect data are highest severity.
2. UX is a primary product requirement, including recovery from transient API/network failures.
3. Visual/responsive stability is high priority.
4. User-facing changes are verified at 375, 430, 768, 1024 and 1440 px.
5. Prefer complete user-journey improvements over isolated features.
6. Preserve analytics and lead transport contracts.
7. Do not bundle/combine assets or add workflow/CSS layers merely to reduce counts; require evidence of real cost or duplicated responsibility.
8. When profiling disproves a suspected optimization, record/defer it and move on rather than adding complexity.
9. While waiting for traffic, keep production stable and fix only confirmed regressions/correctness/UX issues; do not manufacture micro-work.

## Active architecture

### Search / Tourvisor
- `v2/api-v2.php`: active Tourvisor gateway.
- `catalog-cache-v1.php`: catalog TTL cache.
- `search-lifecycle-v6.js`: sole search state/start/status/results/dirty owner.
- `search-progress-ux-v1.js`: waiting/progress/error/zero-result presentation.
- `results-renderer-v5.js`: result rendering and sorting.
- `search-continue-v6.js`: explicit continuation with recoverable retry on failure.
- `catalogs-v2.js`: direct Tourvisor catalog bootstrap/change lifecycle with critical catalog retry and stale-dependent-filter clearing.
- Mobile/result-state UX: `mobile-results-filters-v1.js`, `search-dirty-ux-v1.js`, `mobile-search-summary-v1.js`.

### Selected tour / checkout
- `tour-controller-v4.js`: selected-tour, flights and lead-form controller with stale-response guards and in-place selected-tour load recovery.
- `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-description-v1.js`: detail presentation.
- `checkout-experience-v1.js/.css`: checkout hierarchy.
- `flight-price-sync-v1.js`: selected-flight/displayed/submitted price synchronization. A flight variant may legitimately differ from the base tour price.

### Lead path — protected transport contract
- `lead-search-context.js`: search context included with leads.
- `lead-form-guard-v1.js`: lead-entry validation/recovery/dedup/success UX.
- `lead-adapter-v2.php`, `lead-price-v1.php`, `lead-idempotency-v1.php`: active server support.

Presentation/recovery may improve; changing the sending mechanism or external contract requires explicit approval.

### Visual regression ownership
- `.github/workflows/visual-v2-baseline.yml` is the durable deterministic five-viewport owner for initial search, dates, guests, advanced filters, populated results, selected-tour checkout and zero-result recovery.
- The selected-tour workflow remains because it covers lead/error/trust states not fully equivalent to the baseline.
- Do not create a new visual gate for a state already represented by an existing owner.

## Roadmap status

- B1 Visual foundation — **DONE**
- B2 Search Experience 2.0 — **DONE**
- B3 Results Experience 2.0 — **DONE**
- B4 Tour / Checkout Experience 2.0 — **DONE**
- B5 Trust & Conversion UX — **DONE**
- B6 Visual regression baseline — **DONE**
- B7 Performance & Visual Stability — **DONE**
- B8 Live Product Optimization — **WAITING FOR TRAFFIC**
- A8 Operational live traffic feedback loop — **WAITING_FOR_TRAFFIC**

Other A-series technical/product milestones are complete except A1, superseded by B6.

## B7 — Performance & Visual Stability — DONE

B7 closed after evidence-backed performance/visual/recovery work and a final whole-flow audit found no remaining material issue worth speculative complexity.

### Completed evidence-backed work

- CSS/result/checkout consolidation removed redundant blocking stylesheet requests while preserving cascade order and screenshots.
- Live-read timeout hardening retries only safe idempotent reads; non-idempotent `search_start` is not auto-retried.
- Selected-tour desktop image space is reserved to eliminate the confirmed 380 px checkout shift at 768/1024/1440.
- Mobile-only search summary/results-filter DOM initializes only on mobile widths.
- Hidden stale/confidence DOM and empty startup scans/listeners are deferred/lazy where lifecycle evidence proved the UI did not yet exist.
- Search progress uses the exact rendered-result count instead of repeatedly scanning `.hotel-card`.
- Results/hotel/room/accessibility startup work is event/lifecycle driven where safe.
- PR #39 cached `Intl.NumberFormat('ru-RU')` in the hot results price path; production deploy/live/post-deploy/baseline passed.
- PR #41 cached the search-status `minPrice` formatter used by repeated polling; production deploy/live/post-deploy/baseline passed.
- Temporary diagnostic PR #40 sampled one real progressive search. It produced 25 hotels at 37% and 25 hotels at 100%; the material fingerprints differed (`changed_transitions=1`, `unchanged_transitions=0`). PR #40 was closed without merge, so current evidence does not justify result-payload dedup or incremental-render complexity.
- PR #42 fixed the selected-tour transient-load dead end with an in-place **«Повторить загрузку тура»** action through the existing guarded `selectTour()` path. Production deploy/live/post-deploy/baseline passed.
- PR #43 fixed critical catalog recovery: initial departures/countries failures no longer leave indefinite “Загружаем…” placeholders; an on-page retry preserves dates/guest inputs; a countries failure after departure change clears stale country and dependent destination filters instead of allowing an invalid old combination to survive. A temporary jsdom fault-injection diagnostic emitted `CATALOG_RECOVERY_OK initial_failure_retry=1 stale_destination_cleared=1` and was closed without merge. PR #43 merged as `00a2c2d36b662ed18ecfecb06c3f8e86d48cb3da`; deploy `33092056581`, active contract `33092056592`, tour live `33092056594`, result-detail live `33092056633`, security `33092056598`, post-deploy visual `33092133320` and durable baseline `33092133372` all passed.
- Final recovery audit confirmed `search-continue-v6.js` restores its action as **«Попробовать ещё раз»** after continue/status/results failure and guards stale operations; no extra change was justified.
- Lead retry/success and flight/price paths were re-audited with no additional material dead end confirmed.
- Obsolete/superseded B7 PRs #28, #31 and #34 were closed so they no longer appear as pending product work.

### Explicitly deferred / retained

- `lead-form-guard-v1.js` selected-tour `MutationObserver` stays: public programmatic `V2TourController.selectTour()` can mutate `#selectedTour` before `v2:tour-selected`; click-only lazy observation would weaken recovery.
- `primary-meal-ux-v1.js` observation stays because catalog options are replaced dynamically.
- Per-card `initialTourLimit()` `matchMedia` allocation is real but too small to justify change without profiling/traffic evidence.
- Selected-tour/flight price formatter allocations are user-driven and low-frequency; do not optimize without interaction profiling evidence.
- Progressive result fingerprint dedup/incremental render stays deferred because the live diagnostic found no unchanged transition.
- No evidence justifies JS bundling by request count alone.

## Exact next work order

1. On each autonomous run, first inspect production CI/deploy/live checks and the current V2 journey for confirmed regressions: search → progress → stale results → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery.
2. If a production, correctness, lead-risk, UX or responsive issue is confirmed, fix it immediately with relevant regression/live/visual verification.
3. If production remains healthy and there is no new evidence, keep V2 stable rather than creating speculative micro-optimizations.
4. Activate B8 and A8 together as soon as meaningful real advertising/live-user evidence is available; prioritize observed funnel friction and live behavior before deferred performance micro-work.
5. Keep live Tourvisor/tour-flight validation green whenever search/tour/flight surfaces are touched.

## Guardrails carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not modify neighboring projects.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
- CI green alone is not DONE: require relevant functional, production and visual evidence when applicable.
