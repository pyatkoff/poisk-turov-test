# poisk-turov-test — Autopilot State

Updated: 2026-08-28 12:06 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**PR #103 is merged, deployed and production-green. A confirmed legacy/AI/MAX URL-entry UX/data mismatch is fixed: when an inbound URL selects a departure different from the initially loaded catalog context, V2 now refreshes countries for that departure before normal URL hydration and explicit auto-search. Production browser verification dynamically tested Perm → Egypt and confirmed a real country label, the correct live country option set with no stale/synthetic options, and `search_start` using the same departure/country IDs. C7 remains in evidence review because no real user `search → tour → lead` funnel sample has appeared yet.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is protected. Global nginx/server configuration is outside this project’s allowed write scope.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- On mobile, primary meal choices stay discoverable without hidden horizontal swipe.
- The fixed mobile search CTA may assist inside the form but must stop at the form boundary.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve delayed/on-demand meals loading; do not return `meals` to the immediate startup API burst.
- Preserve ordered active CSS/JS source closure inside V2 bundles; bundling is transport optimization, not a behavior rewrite.
- Preserve legacy/AI/MAX V2 entry URLs: supported camelCase/snake_case parameters hydrate the form; explicit valid search URLs may auto-start, bare/default visits must not.
- When URL `from` differs from initial catalog context, refresh the country catalog for that departure before final hydration/auto-search.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or lead transport/external contract without explicit approval.

## Material production baseline

PRs #76, #77, #79, #81, #84, #85, #86 and #87 established the Search/Brand/Trust/mobile baseline. PR #93 fixed keyboard flight-radio synchronization so radio/card/selected-flight/price/lead context stay aligned.

PR #97 reduced active startup transport from 44 static requests to 2 browser bundle requests while preserving ordered source execution. The manifest now contains 20 CSS and 25 JS sources; the additional JS source is the PR #103 URL catalog-sync shim. The browser still requests one CSS bundle and one JS bundle.

PR #98 fixed deploy verification for bundled assets.

PR #100 restored legacy/AI/MAX URL hydration, supported snake_case date/night aliases, exact hotel context and explicit-valid-link auto-start without changing Metrika or lead transport.

PR #103 (`caef9c4946201130e55230b5015a949b0e2741b5`) fixes the follow-on catalog mismatch discovered during whole-flow re-audit. `url-primary-catalog-sync-v1.js` runs after direct Tourvisor catalogs initialize and before search lifecycle hydration. If an explicit inbound URL has a valid non-current departure/country pair, it switches departure and uses the existing catalog change path to load countries for that departure before normal hydration.

PR #103 PR checks were all green after updating the intentional JS source-count contract from 24 to 25: Security Guard, Validate V2 pull request, Validate V2 startup bundles, Visual V2 baseline, Visual V2 selected tour, Visual V2 B5 trust and Visual V2 pull request.

Production verification is green: Deploy V2 only `33161789679` passed predeploy validation, V2-only copy, production verify and live search smoke. Post-deploy Visual V2 baseline `33161849811` passed.

A temporary production-only diagnostic PR #105 was closed without merge after run `33161901657` passed. It dynamically selected departure `2` (Пермь) and country `1` (Египет), opened the deployed legacy URL, confirmed the selected label was `Египет` rather than a synthetic `ID 1`, verified the editable country options matched the live Perm catalog, and observed `search_start` with `departureId=2&countryId=1`.

## Live evidence

Original useful non-zero browser sample `33138875911`: 9 browser requests, all HTTP 200, 0 browser 4xx/5xx, 4 soft browser rate-limit delay events. It showed `departures`, `meals`, `countries`, but no real-browser `search`, `tour` or `lead` action.

First non-zero sample after bundling `33157609848`: 5 browser requests, all HTTP 200, 0 browser 4xx/5xx, no static bundle throttling, one soft delay on `departures`. This supports retaining the 2-request bundle transport while observing the isolated API delay rather than intentionally slowing startup or changing global nginx.

Immediate post-#103 audit `33161849849`, ending `2026-08-28T10:03:20.677894+00:00`: 0 real-browser requests, 0 4xx/5xx, 0 browser rate-limit events. It provides no new funnel evidence and therefore does not justify a conversion redesign.

There is still no useful real-browser `search → tour → lead` sample. That remains the evidence gate for deeper Results → Selected Tour → Lead conversion changes.

## Whole-flow state

- Search lifecycle owns request/search state; progress UX owns waiting/progress/error/zero; dirty UX owns stale-results state.
- Catalog startup remains narrow: departures then countries; advanced catalogs are deferred; meals remain delayed/on-demand.
- CSS/JS startup transport is two bundle requests; source execution order is preserved.
- Legacy/AI/MAX parameters hydrate V2; explicit valid search links can enter directly into search.
- Non-default URL departure context now synchronizes the editable country catalog before search.
- Deterministic baseline covers 375/430/768/1024/1440 widths, populated results, selected-tour checkout and zero-result recovery.
- Selected-tour pointer and keyboard flight selection converge on the same state/event path.
- Price synchronization and lead context remain isolated contracts; external lead mechanism is unchanged.
- No broader Results/Selected Tour/Lead redesign is justified without real funnel evidence.

## Status

- C1 Search Experience 3.0 — **DONE**.
- C2 Results Experience 3.0 — **DONE**.
- C3 Selected Tour Experience 3.0 — **DONE / production-green**.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- Search/Brand/Trust 9.0 + mobile hardening — **DONE / production-green**.
- Startup static request collapse — **DEPLOYED / production-green / non-zero browser validation confirms no static-asset throttling in latest non-zero sample**.
- Legacy/AI/MAX URL hydration + explicit auto-start — **DONE / production-green**.
- Non-default URL departure → country catalog synchronization — **DONE / production-green / direct live browser verified**.
- Scheduled and post-deploy live-audit paths — **DONE / verified**.
- C7 Live Conversion Optimization — **EVIDENCE_REVIEW**.
- B8 Live Product Optimization — **WAITING_FOR_MEANINGFUL_FUNNEL**.
- A8 Operational Live Feedback — **ACTIVE**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect subsequent non-zero privacy-safe browser samples. Confirm whether the single `departures` delay repeats or remains incidental; do not slow startup for an isolated successful-200 event.
3. Prioritize the first meaningful real-browser `search → tour → lead` evidence and identify concrete funnel friction before changing conversion hierarchy.
4. Continue full mobile+desktop audit: search/URL entry → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery.
5. Preserve first-screen stars+meal, mobile meal discoverability, bounded sticky CTA, delayed/on-demand meals loading, bundle source order and URL compatibility/catalog synchronization.
6. If repeated evidence shows material API/catalog delay, make only the smallest V2-local correction. Do not touch global nginx/server configuration.
7. If production remains healthy and evidence does not identify friction, keep V2 stable rather than manufacturing micro-work.

## Deferred / boundaries

- Global nginx/rate-limit configuration: outside allowed V2/repository write scope.
- Global `/images/...` symlink-loop warnings: outside allowed V2/repository write scope while sampled access remains successful.
- Further Results/price or Selected-Tour/Lead redesign: deferred until observed funnel friction.
- Client-side pacing for `departures`: deferred unless repeated real-browser evidence shows material user impact; current observed event returned HTTP 200.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
