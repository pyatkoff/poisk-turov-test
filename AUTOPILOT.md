# poisk-turov-test — Autopilot State

Updated: 2026-08-28

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**Conversion UX 3.0 C1–C6 is production-green. C7/B8/A8 still wait for meaningful real-browser evidence. The agreed 9.0 design roadmap may advance only where the user has supplied explicit product direction or a fresh audit confirms a material gap.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is also explicitly protected: do not redesign or replace it.

## Current confirmed product contracts

- First-screen search must keep **hotel category/stars and meal type visible**. Do not move them back into `Все фильтры`.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve the startup-rate-limit mitigation: meal options come from the real Tourvisor catalog, but PR #76 delays the `meals` request until 2.2 seconds after visible load or runs it immediately on explicit user request. Do not casually return it to the immediate startup burst.
- AnyTour has four offices: Moscow, Saint Petersburg, Kaliningrad and Cheboksary.
- Yandex Maps reviews exist for all four offices; if surfaced, use only freshly verified ratings/review counts.
- Office/manager photos will be available later.
- Payment, contract and support-before/during-trip are legitimate trust themes, but their exact operational terms are not yet recorded. Do not invent promises.

## Material progress in the current design pass

### PR #76 — primary stars + meal

Whole-flow reread found a concrete mismatch: `primary-meal-ux-v1.js` was moving both hotel category and meal back into `Все фильтры`, contrary to the explicit first-screen product requirement.

PR #76 corrected it. Visual CI caught two real defects in the first implementation before merge: meal initialized before the primary search layout and therefore appeared before departure, and meal chips clipped around tablet/desktop widths. Those issues were fixed rather than accepted into the baseline. The final responsive layout was manually inspected at 375 / 430 / 768 / 1024 / 1440 with no horizontal overflow and the intended search order. All six PR gates passed. PR #76 merged as `940758c08b9711a20d0429d124c941b6596bc710`; V2 deploy `33121218369`, result-detail live `33121218302`, security `33121218320` and post-deploy visual `33121280050` passed.

### PR #77 — Brand & Hero 9.0

PR #77 starts the agreed 9.0 visual roadmap without touching the logo or search/lead/analytics contracts. It strengthens desktop hero hierarchy and travel-tech character, trust-chip treatment, header polish, search-card depth, mobile surface continuity and reduced-motion behavior. Existing primary stars/meal UX remains intact.

All six PR gates for head `f01aa4a95dee599ec94d9b674fcef1a518d0dde1` passed. PR #77 merged as `f917db73e4ae03f7bc81aa59cd7b5525df825e0f`; V2 deploy/live smoke `33121563417` passed. Post-deploy visual/baseline follow-up was still running when this state was written and must finish green before marking #77 fully production-green.

### Results / price hierarchy reread

The next proposed 9.0 area was independently reread after #77. Current `results-renderer-v5.js` + `results-experience-v1.css` already provide a strong commercial hierarchy: hotel photo → title/location/rating/sea facts → `Лучшее предложение` price and date/night/meal context → concrete tour facts/price → orange `Выбрать тур` CTA, with responsive rules for mobile and desktop. No material defect was confirmed, so do **not** manufacture a results redesign merely to continue the roadmap. Revisit only with visual evidence, user direction or live funnel friction.

## Active architecture

### Search / Tourvisor
- `v2/api-v2.php`: active Tourvisor gateway.
- `catalog-cache-v1.php`: catalog TTL cache.
- `catalogs-v2.js`: critical departures/countries eager; advanced catalogs lazy to limit startup pressure.
- `search-filters-ux-v1.js`: primary/advanced search layout.
- `primary-meal-ux-v1.js/.css`: stars+meal primary contract and delayed/on-demand meal catalog.
- `search-lifecycle-v6.js`: sole search state/start/status/results/dirty owner.
- `search-progress-ux-v1.js`: waiting/progress/error/zero-result UX.
- `results-renderer-v5.js`: results/sorting/representative tour selection.
- `search-continue-v6.js`: recoverable explicit continuation.

### Selected tour / checkout
- `tour-controller-v4.js`: selected tour, automatic flights, flight choice, lead-form controller and stale guards.
- `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-description-v1.js`: detail presentation.
- `checkout-experience-v1.js/.css`: checkout hierarchy/disclosure.
- `flight-price-sync-v1.js`: selected-flight/displayed/submitted price synchronization.

### Lead path — protected transport
- `lead-search-context.js`, `lead-form-guard-v1.js` and `lead-adapter-v2.php` remain active.
- Presentation/recovery may improve; changing the sending mechanism/external contract requires explicit approval.

## Live evidence

- `audit-v2-live-traffic.yml`: privacy-safe rolling-tail context.
- `audit-v2-recent-browser.yml`: privacy-safe recent 30-minute real-browser window.
- PRs #63–#71 established actor/error/rate-limit attribution without exposing IPs/query strings/raw payloads.
- Live evidence found advanced hidden startup catalogs materially affected by nginx rate-limit delays; PR #72 removed them from the eager startup burst and is production-green.
- PR #74 added the recent browser window. Its first validated sample contained 0 browser requests, so it proves the audit works but cannot yet measure the post-#72 effect.
- Cumulative real-browser conversion evidence is still only 2 `search_start` and 1 `tour`; this is insufficient for C7 conversion changes.
- Global `/images/...` symlink-loop warnings remain outside the allowed V2/repository write scope while sampled access still returns HTTP 200.

## Status

- C1 Search Experience 3.0 — **DONE**, with PR #76 extending the first-screen primary contract.
- C2 Results Experience 3.0 — **DONE**; fresh reread found no justified 9.0 rewrite yet.
- C3 Selected Tour Experience 3.0 — **DONE**.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- C7 Live Conversion Optimization — **WAITING_FOR_TRAFFIC**.
- B1–B7 and completed A-series milestones remain done; B8/A8 wait for live evidence; A1 remains superseded by B6.

## Exact next work order

1. Inspect fresh `main`, open PRs, deploy/live/security/visual results and the full V2 journey.
2. Finish verification of PR #77 post-deploy visual and baseline. If either fails, diagnose and fix before further design work.
3. Inspect the recent 30-minute real-browser audit. Require a non-zero useful sample before judging the post-#72 startup effect or activating C7/B8/A8.
4. Preserve the #76 first-screen stars+meal contract and delayed/on-demand meal request.
5. Re-audit search → progress → stale/zero results → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery on mobile and desktop.
6. Continue the 9.0 design roadmap only when a material gap is confirmed. Results/price hierarchy is currently retained as-is after reread.
7. If meaningful funnel evidence appears, activate C7/B8/A8 and prioritize observed friction from `search_started → search_complete → tour_selected → flight_selected → lead_started → lead_submitted`.
8. If production is healthy and evidence is absent, keep V2 stable rather than manufacturing micro-work.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
