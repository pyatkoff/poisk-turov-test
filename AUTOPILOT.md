# poisk-turov-test — Autopilot State

Updated: 2026-08-28 13:10 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**PRs #110 and #111 are merged, deployed and production-green. A whole-flow responsive audit found a real primary-search UX defect: meal choices could be clipped inside their own horizontal scroller at 1024px, and a focused regression then exposed a second breakpoint gap at 1101px. The row now wraps naturally whenever the available width is insufficient, and the regression verifies all five choices with no clipping/internal horizontal scrolling at 375/430/768/901/1024/1100/1101/1200/1440. C7 remains in evidence review, B8 waits for meaningful real-browser funnel behavior, and A8 remains active.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is protected. Global nginx/server configuration is outside this project’s allowed write scope.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile and intermediate widths.
- All primary meal choices must remain directly visible/discoverable without hidden horizontal scrolling; the row may wrap when width is insufficient.
- The fixed mobile search CTA may help while traversing the form, but must stop at the form boundary and remain hidden below it.
- Other resort/hotel/operator/flight-detail filters may remain behind the advanced filter disclosure.
- Preserve delayed/on-demand meals loading; do not return `meals` to the immediate startup API burst.
- Preserve ordered active CSS/JS source closure inside the V2 bundles; bundling is transport optimization, not a behavior rewrite.
- Preserve legacy/AI/MAX V2 entry URLs: supported camelCase and snake_case search parameters hydrate the form; auto-start requires an explicit valid search URL rather than a bare/default page visit.
- URL-requested departure/country must leave the visible primary catalogs consistent with the actual search IDs.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material production baseline

PR #97/#98 collapsed active startup static transport from 44 individual CSS/JS requests to one CSS bundle and one JS bundle while preserving source order.

PR #100 restored legacy/AI/MAX V2 URL hydration, including supported snake_case date/night aliases and explicit valid-link auto-start. PR #103 (`caef9c4946201130e55230b5015a949b0e2741b5`) fixed primary departure/country catalog consistency. Temporary production diagnostic #105 directly verified non-default `Пермь (2) → Египет (1)`, real country options with no stale/synthetic IDs, and `search_start` using the same IDs.

PR #110 (`82318973e1e76c0999e61bf92b0fab86efbc34df`) fixed confirmed clipping of the final primary meal option at 1024px by moving the meal block to a full row at the affected intermediate breakpoint. Deploy/live checks were green.

A new focused regression then caught a separate 1101px gap that page-level overflow checks could not see because clipping occurred inside `.meal-quick`: at 1101px one of five options was outside the visible 582px row (`601px` content), while 1100px and 1440px were fine. PR #111 (`ce7d5ebe6753407175d709fa59b902c97ff72996`) removed hidden horizontal meal scrolling in favor of natural wrapping and added PR-bundle validation across nine widths.

Final PR #111 regression run `33165808709` is green at **375/430/768/901/1024/1100/1101/1200/1440**: five meal choices at every width, `clipped=0`, no internal horizontal overflow, no document overflow, `flex-wrap=wrap`.

Production verification for #111 is green: Deploy V2 only `33165919804`; tour-live `33165919773`; result-detail-live `33165919885`; Visual V2 post-deploy `33165998159`; Visual V2 baseline `33165998190`; post-deploy recent-browser audit `33165998169`.

## Live evidence

Temporary privacy-safe diagnostic PR #109 was intentionally closed without merge after run `33164345519`. In a 3-hour window it found **1 real browser session, 12 requests, all HTTP 200, 0 browser 4xx/5xx**, with API actions `departures` x2, `meals` x2 and `countries` x2. There was no real `search`, `tour` or `lead` action.

The same session had three soft rate-limit records: `departures` x2 and JS bundle x1. Recorded excess values were `0.775–1.0` for departures and `0.925` for the JS bundle. This confirms repetition of soft limiting, but not material UX failure: every request succeeded and no funnel action was observed. Do **not** add client pacing or modify nginx until real-browser evidence shows measurable impact.

Post-#111 audit `33165998169`, ending `2026-08-28T11:08:23.528352+00:00`, is clean: **0 real-browser requests, 0 browser 4xx/5xx, 0 browser rate-limit events**. This is health evidence only, not conversion evidence.

## Whole-flow state

- Search lifecycle owns request/search state; progress UX owns waiting/progress/error/zero; dirty UX owns stale-results state.
- Catalog startup stays intentionally narrow; advanced catalogs are deferred and meals remain delayed/on-demand.
- CSS/JS startup transport is two bundle requests; source execution order is preserved.
- Legacy/AI/MAX query parameters hydrate V2 and explicit valid links auto-start; primary departure/country catalogs synchronize before hydration.
- Deterministic visual coverage spans mobile, intermediate and desktop states and includes populated results, selected-tour checkout and zero-result recovery.
- Primary meal visibility now has an explicit internal-overflow regression at 375/430/768/901/1024/1100/1101/1200/1440.
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
- Primary meal responsive visibility — **DONE / production-green / nine-width regression**.
- Scheduled and post-deploy live-audit paths — **DONE / verified**.
- C7 Live Conversion Optimization — **EVIDENCE_REVIEW**.
- B8 Live Product Optimization — **WAITING_FOR_MEANINGFUL_FUNNEL**.
- A8 Operational Live Feedback — **ACTIVE**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect the next non-zero privacy-safe browser samples. Repeated soft limiting is established; only implement V2-local pacing if evidence shows material load/UX/funnel impact.
3. Prioritize the first meaningful real-browser `search → tour → lead` evidence and identify concrete funnel friction before changing conversion hierarchy.
4. Continue the full V2 mobile+desktop audit: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery, including intermediate widths and URL entry.
5. Audit advanced URL hydration labels/options when advanced catalogs are deferred. Fix only if a confirmed visible/data mismatch can mislead users; do not broaden API startup merely for cosmetic completeness.
6. Preserve first-screen stars+meal, meal visibility at every width, bounded sticky CTA, delayed meals loading, bundle order and URL compatibility.
7. If production remains healthy and evidence does not identify friction, keep V2 stable rather than manufacturing micro-work.

## Deferred / boundaries

- Global nginx/rate-limit configuration: outside allowed V2/repository write scope.
- Global `/images/...` symlink-loop warnings: outside allowed V2/repository write scope while sampled access remains successful.
- Further Results/price or Selected-Tour/Lead redesign: deferred until observed funnel friction.
- Client-side pacing for `departures`/bundle: repeated soft signal exists, but deferred until measured user/funnel impact because the sampled requests all returned HTTP 200.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
