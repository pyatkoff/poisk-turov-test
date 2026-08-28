# poisk-turov-test — Autopilot State

Updated: 2026-08-28 11:05 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**PR #100 is merged, deployed and production-green. V2 now hydrates legacy/AI/MAX search URLs, including supported snake_case date/night aliases, and auto-starts only an explicit valid search link. The first non-zero post-bundle real-browser sample also arrived: 5 browser requests, all HTTP 200, no static-asset throttling, and one soft nginx delay on the `departures` API route. This validates the repository-local static request collapse from PR #97/#98 while leaving the isolated catalog/API delay under observation rather than justifying an intentional UX slowdown or a global nginx change. C7 remains in evidence review, B8 waits for meaningful funnel behavior, and A8 remains active.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is protected. Global nginx/server configuration is outside this project’s allowed write scope.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- On mobile, all primary meal choices stay discoverable without a hidden horizontal swipe.
- The fixed mobile search CTA may help while traversing the form, but must stop at the form boundary and remain hidden below it.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve delayed/on-demand meals loading; do not return `meals` to the immediate startup API burst.
- Preserve the ordered active CSS/JS source closure inside the V2 bundles; bundling is transport optimization, not a behavior rewrite.
- Preserve legacy/AI/MAX V2 entry URLs: supported camelCase and snake_case search parameters must hydrate the form; auto-start must require an explicit valid search URL rather than a bare/default page visit.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material production baseline

PRs #76, #77, #79, #81, #84, #85, #86 and #87 established the Search/Brand/Trust/mobile baseline. PR #93 fixed native keyboard flight-radio state synchronization so checked radio, selected card, selected flight state/event, price context and eventual lead payload stay aligned.

PR #97 (`e27b0ecaf481a0f8e35aca34783844d9822cafa7`) addressed the retained real-browser static-startup throttling signal at the safest repository-local layer: CSS/JS transport. The active manifest keeps 20 CSS and 24 JS sources in historical order, but the browser requests one CSS bundle and one JS bundle instead of 44 separate static files. `Validate V2 startup bundles` run `33150164420` passed syntax, manifest, immutable-cache and request-collapse checks.

PR #98 (`7073054b8d1dfb69c118357f5dacd09f1e4edf8e`) fixed an obsolete deploy-verifier assumption that still expected individually referenced source files. This was a verification-contract regression caused by bundling, not a product or lead-path defect.

PR #100 (`154eab24f27dd456e12c8b07002f3236b1686603`) restores a critical inbound-entry contract for legacy/AI/MAX links moving to V2. `form-defaults.php` accepts the supported snake_case date/night aliases alongside camelCase inputs, and the search lifecycle hydrates URL-supplied core/advanced fields after catalogs initialize. It auto-starts only when an explicit search URL has the required departure/country context plus an explicit date/night/hotel criterion. Metrika and lead transport are unchanged.

Current production verification for #100 is green: Deploy V2 only `33157514891`, active contract `33157514914`, tour-live `33157514902`, result-detail-live `33157514873`, Visual V2 post-deploy `33157609853`, Visual V2 baseline `33157609829`, and post-deploy recent-browser audit `33157609848`.

## Live evidence

The original useful non-zero browser sample was run `33138875911`: **9 browser requests, all HTTP 200, 0 browser 4xx/5xx, 4 soft browser rate-limit delay events**. It showed `departures`, `meals` and `countries`, but no real-browser `search`, `tour` or `lead` action. Several of those delays were on startup assets, which motivated the V2-local bundle change rather than a global server change.

The first **non-zero** browser sample after bundling is now run `33157609848`, ending at `2026-08-28T09:01:02.749199+00:00`: **5 browser requests, all HTTP 200, 0 browser 4xx/5xx, 1 soft browser rate-limit delay**. The browser paths were the V2 page, `phone-config.php`, and three API requests; observed API actions were `departures`, `meals` and `countries`. Crucially, **no static CSS/JS bundle asset was rate-limited**. The single remaining delay was `departures`.

Interpretation: the static startup mitigation now has a non-zero real-browser validation signal and appears to have removed the asset portion of the prior request-burst problem. Do not add client-side pacing or delay catalog startup on the basis of one successful-200 `departures` delay. Require repetition/material UX impact before a V2-local API mitigation. Never modify global nginx/rate-limit configuration from this project.

There is still no real-browser `search`, `tour` or `lead` action in the useful samples. That remains the gating evidence for deeper Results → Selected Tour → Lead conversion changes.

## Whole-flow state

- Search ownership remains coherent: search lifecycle owns request/search state; progress UX owns waiting/progress/error/zero; dirty UX owns stale-results state.
- Catalog startup remains intentionally narrow: departures then countries; advanced catalogs are deferred; meals remain delayed/on-demand.
- CSS/JS startup transport is two bundle requests; source execution order is preserved by the manifest.
- Legacy/AI/MAX query parameters can now hydrate the V2 form and explicit valid search URLs can enter directly into the search flow.
- The deterministic baseline covers the primary mobile/desktop flow across 375/430/768/1024/1440 widths, populated results, selected-tour checkout and zero-result recovery.
- Selected-tour pointer and keyboard flight selection converge on the same state/event path.
- Price synchronization and lead context remain isolated contracts; the external lead mechanism is unchanged.
- No broader Results/Selected Tour/Lead redesign is justified without real funnel evidence.

## Status

- C1 Search Experience 3.0 — **DONE**.
- C2 Results Experience 3.0 — **DONE**.
- C3 Selected Tour Experience 3.0 — **DONE / production-green**.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- Search/Brand/Trust 9.0 + mobile hardening — **DONE / production-green**.
- Startup static request collapse — **DEPLOYED / production-green / non-zero browser validation confirms no static-asset throttling in latest sample**.
- Deploy verification for bundled assets — **DONE / production-green**.
- Legacy/AI/MAX URL hydration and explicit auto-start — **DONE / production-green**.
- Scheduled and post-deploy live-audit paths — **DONE / verified**.
- C7 Live Conversion Optimization — **EVIDENCE_REVIEW**.
- B8 Live Product Optimization — **WAITING_FOR_MEANINGFUL_FUNNEL**.
- A8 Operational Live Feedback — **ACTIVE**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect subsequent non-zero privacy-safe browser samples. Confirm whether the single `departures` delay repeats or remains incidental; do not slow the startup path for an isolated successful-200 event.
3. Prioritize the first meaningful real-browser `search → tour → lead` evidence and identify concrete funnel friction before changing conversion hierarchy.
4. Continue the full V2 mobile+desktop audit: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery, including legacy URL entry into the search flow.
5. Preserve first-screen stars+meal, mobile meal discoverability, bounded sticky CTA, delayed/on-demand meals loading, bundle source order and URL hydration compatibility.
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