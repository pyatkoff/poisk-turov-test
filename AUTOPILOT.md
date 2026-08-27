# poisk-turov-test — Autopilot State

Updated: 2026-08-28

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and hard boundaries; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current product phase

**Conversion UX 3.0 C1–C6 is production-green. C7/B8/A8 wait for meaningful real traffic evidence.**

The active product is `v2/`. Search/results/selected-tour/lead/flight conversion passes are complete and verified at the durable 375 / 430 / 768 / 1024 / 1440 viewport contract. Real advertising/live-user evidence outranks speculative polish. A real-browser startup performance signal was fixed in PR #72 and PR #74 provides a privacy-safe recent 30-minute browser window. The available conversion funnel remains too small to activate C7.

A user-confirmed product contract was added in PR #76: **hotel category/stars and meal type belong on the first-screen primary search**. They must not be pushed back into `Все фильтры`. The logo is protected and must not be redesigned. Other visual design inside this repository remains proactively editable.

Protected without explicit approval: Yandex Metrika configuration/goals and the existing lead-sending mechanism/external contract. Work stays inside `pyatkoff/poisk-turov-test`; production deployment stays V2-only.

## Product quality priorities

1. Production breakage, lead loss and incorrect data are highest severity.
2. UX is a primary product requirement, including recovery from transient API/network failures.
3. Visual/responsive stability is high priority.
4. User-facing changes are verified at 375, 430, 768, 1024 and 1440 px.
5. Prefer complete user-journey improvements over isolated features.
6. Preserve analytics, Yandex Metrika and lead transport contracts.
7. Do not manufacture micro-work while waiting for traffic; require evidence for further optimization.
8. When an audit disproves a suspected improvement, record/defer it rather than forcing a change.

## Active architecture

### Search / Tourvisor
- `v2/api-v2.php`: active Tourvisor gateway.
- `catalog-cache-v1.php`: catalog TTL cache.
- `catalogs-v2.js`: critical departures/countries bootstrap eagerly with retry/stale-filter clearing; advanced destination/operator/type/service catalogs load lazily to reduce startup pressure.
- `search-filters-ux-v1.js`: primary search layout and advanced-filter presentation.
- `primary-meal-ux-v1.js/.css`: PR #76 keeps stars and meal in the primary search. Meal values still come from the real Tourvisor catalog, but the meals request is delayed until 2.2 seconds after a visible page load or runs immediately on explicit user request. This deliberately preserves the PR #72 startup-burst mitigation. Advanced-filter reset preserves primary stars/meal and the advanced summary does not double-count them.
- `search-lifecycle-v6.js`: sole search state/start/status/results/dirty owner.
- `search-progress-ux-v1.js`: waiting/progress/error/zero-result presentation.
- `results-renderer-v5.js`: result rendering and sorting.
- `search-continue-v6.js`: explicit continuation with recoverable retry.
- Mobile/result-state UX: `mobile-results-filters-v1.js`, `search-dirty-ux-v1.js`, `mobile-search-summary-v1.js`.

### Selected tour / checkout
- `tour-controller-v4.js`: selected-tour, automatic flight loading, flight selection, lead-form controller and stale-response guards.
- `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-description-v1.js`: detail presentation.
- `checkout-experience-v1.js/.css`: checkout hierarchy and progressive disclosure.
- `flight-price-sync-v1.js`: selected-flight/displayed/submitted price synchronization.

### Lead path — protected transport contract
- `lead-search-context.js`: search context included with leads.
- `lead-form-guard-v1.js`: lead-entry validation/recovery/dedup/success UX.
- `lead-adapter-v2.php`, `lead-price-v1.php`, `lead-idempotency-v1.php`: active server support.

Presentation/recovery may improve; changing the sending mechanism or external contract requires explicit approval.

## Brand/product facts carried forward

- Logo: **do not change**.
- Other V2 design may be changed proactively when evidence/product direction supports it.
- AnyTour currently has four offices: Moscow, Saint Petersburg, Kaliningrad and Cheboksary.
- Yandex Maps reviews exist for those offices, but trust-copy should use only freshly verified factual ratings/counts if surfaced.
- Payment, contract and support-before/during-trip are valid trust themes, but their exact operating terms are not yet recorded. Do not invent promises until the factual details are available.
- Office/manager photography may be added later and does not block current V2 work.

## Live production evidence

- `.github/workflows/audit-v2-live-traffic.yml`: privacy-safe rolling-tail evidence source.
- `.github/workflows/audit-v2-recent-browser.yml`: privacy-safe recent 30-minute browser-only evidence source for post-deploy attribution.
- PRs #63–#65 separated headless CI from real browsers and attributed 4xx/5xx by actor.
- PRs #67–#71 classified nginx severity/families and safe actor/action/path attribution without exposing IPs, query strings or raw payloads.
- Evidence found real-browser nginx rate-limit delays concentrated in hidden startup catalogs. PR #72 kept critical departures/countries eager and moved advanced catalogs out of the startup burst; its deploy/live/post-deploy/baseline were green.
- The first validated PR #74 recent-window sample contained 0 real-browser requests, so it proves the window works but does not yet prove the post-#72 performance effect.
- The cumulative real-browser conversion sample remains only 2 `search_start` and 1 `tour`; do not activate C7 on that sample.
- Nginx `Too many levels of symbolic links` warnings for global `/images/...` assets remain outside the allowed V2/repository write scope and are explicitly deferred while access responses remain HTTP 200.

## PR #76 — primary stars + meal

Whole-flow reread after the user clarified the first-screen product requirements found a real UX mismatch: `primary-meal-ux-v1.js` was moving both hotel category and meal back into `Все фильтры`, contradicting the intended search flow.

PR #76 corrected the first screen and was deliberately treated as a visual baseline refresh only after inspecting the five-viewport evidence. The first implementation was **not** accepted: visual CI exposed an initialization-order bug that placed meal before departure and clipped meal chips around tablet/desktop widths. The cause was enhancement timing relative to `search-filters-ux-v1.js`. It was fixed by initializing the primary meal enhancement after the search layout, then giving meal adequate responsive grid space. Final evidence showed correct order, visible meal choices and no horizontal overflow at 375 / 430 / 768 / 1024 / 1440.

PR gates for final head all passed, including Validate V2, Security, selected-tour visual, trust visual, general visual and the explicit visual baseline refresh. PR #76 merged as `940758c08b9711a20d0429d124c941b6596bc710`. Production V2 deploy `33121218369`, result-detail live validation `33121218302`, security `33121218320` and post-deploy visual `33121280050` all passed.

## Conversion UX 3.0 status

- C1 Search Experience 3.0 — **DONE**. PRs #46/#48/#50 established the simplified primary path; PR #76 additionally locks stars and meal into the first-screen primary search.
- C2 Results Experience 3.0 — **DONE**.
- C3 Selected Tour Experience 3.0 — **DONE**.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- C7 Live Conversion Optimization — **WAITING_FOR_TRAFFIC**. Require meaningful real-browser/funnel evidence rather than speculative conversion work.

Earlier B1–B7 and technical A-series milestones remain complete; B8 and A8 remain waiting for traffic. A1 remains superseded by B6.

## Exact next work order

1. On every autonomous run inspect fresh `main`, open PRs, production deploy/live/security/visual results and the current V2 journey.
2. Inspect `.github/workflows/audit-v2-recent-browser.yml` first. Require a **non-zero recent 30-minute real-browser sample** before judging the startup/rate-limit effect or making C7 conversion changes. Use the rolling audit as historical/context evidence.
3. Preserve the PR #76 first-screen contract: departure/country + dates/duration/tourists + stars/meal are primary; additional resort/hotel/operator/flight-detail filters can stay behind `Все фильтры`.
4. Preserve the delayed/on-demand meals request; do not casually return it to the immediate startup request burst.
5. Re-audit search → waiting/progress → stale/zero results → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery, including mobile and desktop.
6. If production breakage, lead risk, incorrect data, UX friction or responsive regression is confirmed, fix it immediately and verify through relevant contracts/live/visual gates.
7. If meaningful live traffic/funnel evidence is available, activate C7 + B8 + A8 and prioritize observed friction from `search_started → search_complete → tour_selected → flight_selected → lead_started → lead_submitted`.
8. If production is healthy and there is no evidence, keep V2 stable; do not create speculative micro-work. Do not change the global `/images` symlink layer from this repository.

## Guardrails carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server configuration outside the allowed V2 deployment scope.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
- CI green alone is not DONE: require relevant functional, production and visual evidence when applicable.
