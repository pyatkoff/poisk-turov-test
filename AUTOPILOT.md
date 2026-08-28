# poisk-turov-test — Autopilot State

Updated: 2026-08-28 12:08 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**PR #103 is merged, deployed and production-green. The remaining legacy/AI/MAX URL-entry mismatch is fixed: when a URL requests a departure/country different from the initial form state, the visible primary catalogs are now refreshed for that departure before search lifecycle hydration. A direct production browser diagnostic specifically verified a non-default departure (Пермь → Египет), the real country label/options with no stale or synthetic values, and auto-search using the same IDs. C7 remains in evidence review, B8 waits for meaningful real-browser funnel behavior, and A8 remains active.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is protected. Global nginx/server configuration is outside this project’s allowed write scope.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- On mobile, all primary meal choices stay discoverable without hidden horizontal swipe.
- The fixed mobile search CTA may help while traversing the form, but must stop at the form boundary and remain hidden below it.
- Other resort/hotel/operator/flight-detail filters may remain behind the advanced filter disclosure.
- Preserve delayed/on-demand meals loading; do not return `meals` to the immediate startup API burst.
- Preserve ordered active CSS/JS source closure inside the V2 bundles; bundling is transport optimization, not a behavior rewrite.
- Preserve legacy/AI/MAX V2 entry URLs: supported camelCase and snake_case search parameters hydrate the form; auto-start requires an explicit valid search URL rather than a bare/default page visit.
- URL-requested departure/country must leave the visible primary catalogs consistent with the actual search IDs.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material production baseline

PR #97/#98 collapsed active startup static transport from 44 individual CSS/JS requests to one CSS bundle and one JS bundle while preserving source order. The first non-zero real-browser sample after bundling showed no static-asset throttling; one successful-200 `departures` delay remains under observation.

PR #100 restored legacy/AI/MAX V2 URL hydration, including supported snake_case date/night aliases, and explicit valid-link auto-start.

PR #103 (`caef9c4946201130e55230b5015a949b0e2741b5`) closes the primary catalog consistency gap left after #100. Its V2-only shim runs after direct Tourvisor catalog initialization and before search lifecycle hydration: if `from` + `country` are explicitly requested, it switches departure and refreshes countries through the existing catalog change path so labels/options match the search context. The active JS bundle now contains 25 ordered sources.

Production verification for #103 is green: Deploy V2 only `33161789679` including predeploy validation, copy, HTTP/API/lead verification and live-search smoke; active contract `33161789731`; result-detail-live `33161789762`; Visual V2 post-deploy `33161849791`; Visual V2 baseline `33161849811`; post-deploy recent-browser audit `33161849849`.

Temporary diagnostic PR #104 was intentionally not merged. Run `33161717495` against production confirmed `from=1`, `country=2`, exact `2026-12-05` dates, 6 nights, 2 adults and `hotel=82567`; `V2SearchLifecycle.params()` contained `hotelIds=["82567"]`; auto-search started and produced searchId `13460825192`. PR #104 was then closed without merge.

A stronger temporary diagnostic PR #105 was also closed without merge after run `33161901657` passed. It dynamically selected a departure different from the default: `from=2` (Пермь), `country=1` (Египет). Production rendered the selected label as `Египет`, exposed the 33-country live catalog for Пермь with no stale/synthetic option IDs, kept `V2SearchLifecycle.params()` at departure 2 / country 1, and issued `search_start` with those same IDs (searchId `13460854706`). This is the direct production proof of the PR #103 catalog-sync behavior.

## Live evidence

The latest useful non-zero browser sample remains `33157609848`: **5 browser requests, all HTTP 200, 0 browser 4xx/5xx, 0 static-asset throttling, 1 soft rate-limit delay on `departures`**. Observed API actions were `departures`, `meals` and `countries`; there was no real-browser search/tour/lead action.

Post-deploy audit `33161849849`, ending `2026-08-28T10:03:20.677894+00:00`, is clean: **0 real-browser requests, 0 browser 4xx/5xx, 0 browser rate-limit events**. This confirms health only; a zero-traffic sample cannot justify conversion changes.

Do not add client-side pacing or change nginx because of the single historical successful-200 `departures` delay. Require repetition/material UX impact before a V2-local mitigation.

## Whole-flow state

- Search lifecycle owns request/search state; progress UX owns waiting/progress/error/zero; dirty UX owns stale-results state.
- Catalog startup stays intentionally narrow; advanced catalogs are deferred and meals remain delayed/on-demand.
- CSS/JS startup transport is two bundle requests; source execution order is preserved.
- Legacy/AI/MAX query parameters hydrate V2 and explicit valid links auto-start; primary departure/country catalogs now synchronize before hydration.
- Deterministic visual coverage spans 375/430/768/1024/1440 and includes populated results, selected-tour checkout and zero-result recovery.
- Pointer and keyboard flight selection converge on the same state/event path.
- Flight price synchronization and lead context remain isolated contracts; the external lead mechanism is unchanged.
- No broader Results/Selected Tour/Lead redesign is justified without real funnel evidence.

## Status

- C1 Search Experience 3.0 — **DONE**.
- C2 Results Experience 3.0 — **DONE**.
- C3 Selected Tour Experience 3.0 — **DONE / production-green**.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- Search/Brand/Trust 9.0 + mobile hardening — **DONE / production-green**.
- Startup static request collapse — **DONE / production-green / non-zero browser validation**.
- Legacy/AI/MAX URL hydration + explicit auto-start — **DONE / production-green**.
- URL primary catalog synchronization — **DONE / production-green / direct non-default live verification**.
- Scheduled and post-deploy live-audit paths — **DONE / verified**.
- C7 Live Conversion Optimization — **EVIDENCE_REVIEW**.
- B8 Live Product Optimization — **WAITING_FOR_MEANINGFUL_FUNNEL**.
- A8 Operational Live Feedback — **ACTIVE**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect the next non-zero privacy-safe browser samples. Confirm whether `departures` delay repeats or remains incidental.
3. Prioritize the first meaningful real-browser `search → tour → lead` evidence and identify concrete funnel friction before changing conversion hierarchy.
4. Continue the full V2 mobile+desktop audit: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery, including URL entry.
5. Audit advanced URL hydration labels/options when advanced catalogs are deferred. Fix only if a confirmed visible/data mismatch can mislead users; do not broaden API startup merely for cosmetic completeness.
6. Preserve first-screen stars+meal, mobile meal discoverability, bounded sticky CTA, delayed meals loading, bundle order and URL compatibility.
7. If production remains healthy and evidence does not identify friction, keep V2 stable rather than manufacturing micro-work.

## Deferred / boundaries

- Global nginx/rate-limit configuration: outside allowed V2/repository write scope.
- Global `/images/...` symlink-loop warnings: outside allowed V2/repository write scope while sampled access remains successful.
- Further Results/price or Selected-Tour/Lead redesign: deferred until observed funnel friction.
- Client-side pacing for `departures`: deferred unless repeated real-browser evidence shows material user impact.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
