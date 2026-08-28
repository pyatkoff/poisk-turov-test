# poisk-turov-test — Autopilot State

Updated: 2026-08-28 09:59 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**V2 startup request-burst mitigation is now deployed and production-green. PR #97 collapsed the historical 44 static startup asset requests into 2 bundled requests while preserving the ordered 20 CSS + 24 JS source closure. PR #98 restored deploy verification compatibility with the bundled asset contract. Current deploy, active-contract, tour-live, bundle, visual and post-deploy browser-audit checks are green. The first post-bundle 30-minute audit contained no real browser traffic, so the mitigation is deployed but not yet proven against a non-zero real-user sample. C7 remains in evidence review, B8 waits for meaningful funnel behavior, and A8 remains active.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is protected. Global nginx/server configuration is outside this project’s allowed write scope.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- On mobile, all primary meal choices stay discoverable without a hidden horizontal swipe.
- The fixed mobile search CTA may help while traversing the form, but must stop at the form boundary and remain hidden below it.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve delayed/on-demand meals loading; do not return `meals` to the immediate startup API burst.
- Preserve the ordered active CSS/JS source closure inside the V2 bundles; bundling is transport optimization, not a behavior rewrite.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material production baseline

PRs #76, #77, #79, #81, #84, #85, #86 and #87 established the Search/Brand/Trust/mobile baseline. PR #93 fixed native keyboard flight-radio state synchronization so checked radio, selected card, selected flight state/event, price context and eventual lead payload stay aligned.

PR #97 (`e27b0ecaf481a0f8e35aca34783844d9822cafa7`) addressed the only retained real-browser throttling signal at the safest repository-local layer: CSS/JS startup transport. The active manifest keeps 20 CSS and 24 JS sources in historical order, but the browser now requests one CSS bundle and one JS bundle instead of 44 separate static files. `Validate V2 startup bundles` run `33150164420` passed syntax, manifest, immutable-cache and request-collapse checks.

PR #98 (`7073054b8d1dfb69c118357f5dacd09f1e4edf8e`) fixed an obsolete deploy-verifier assumption that still expected individually referenced source files. This was a verification-contract regression caused by bundling, not a product or lead-path defect.

Current production verification for #98 is green: Deploy V2 only `33150281686`, active contract `33150281677`, tour-live `33150281694`, Visual V2 post-deploy `33150370523`, and post-deploy recent-browser audit `33150370576`.

## Live evidence

The original useful non-zero browser sample remains run `33138875911`: **9 browser requests, all HTTP 200, 0 browser 4xx/5xx, 4 soft browser rate-limit delay events**. It showed `departures`, `meals` and `countries`, but no real-browser `search`, `tour` or `lead` action.

The bundle mitigation is now live. Its immediate post-deploy browser audit `33150370576` ended at `2026-08-28T07:08:53.524135+00:00` with **0 browser requests, 0 browser 4xx/5xx and 0 browser rate-limit events**. Therefore the deployment is healthy, but this zero-traffic window cannot prove that real-user throttling is resolved.

Interpretation: do not make another request-architecture change yet. Inspect the next non-zero post-bundle browser sample. If user-browser throttling persists, identify whether it is now limited to API/catalog routes and make only the smallest V2-local correction. Never modify global nginx/rate-limit configuration from this project.

## Whole-flow state

- Search ownership remains coherent: search lifecycle owns request/search state; progress UX owns waiting/progress/error/zero; dirty UX owns stale-results state.
- Catalog startup remains intentionally narrow: departures then countries; advanced catalogs are deferred; meals remain delayed/on-demand.
- CSS/JS startup transport is now two bundle requests; source execution order is preserved by the manifest.
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
- Startup static request collapse — **DEPLOYED / production-green / awaiting non-zero browser validation**.
- Deploy verification for bundled assets — **DONE / production-green**.
- Scheduled and post-deploy live-audit paths — **DONE / verified**.
- C7 Live Conversion Optimization — **EVIDENCE_REVIEW**.
- B8 Live Product Optimization — **WAITING_FOR_MEANINGFUL_FUNNEL**.
- A8 Operational Live Feedback — **ACTIVE**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect the next **non-zero post-bundle** privacy-safe browser sample. Confirm whether the prior browser rate-limit delays disappear or remain on API/catalog routes.
3. If repeated evidence shows material user delay, reduce only the remaining avoidable V2-local request burst. Do not touch global nginx/server configuration.
4. Continue the full V2 mobile+desktop audit: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery.
5. Preserve first-screen stars+meal, mobile meal discoverability, bounded sticky CTA, delayed/on-demand meals loading and bundle source order.
6. Require meaningful real-browser `search → tour → lead` evidence before changing Results/Selected-Tour/Lead conversion hierarchy.
7. If production remains healthy and evidence does not identify friction, keep V2 stable rather than manufacturing micro-work.

## Deferred / boundaries

- Global nginx/rate-limit configuration: outside allowed V2/repository write scope.
- Global `/images/...` symlink-loop warnings: outside allowed V2/repository write scope while sampled access remains successful.
- Further Results/price or Selected-Tour/Lead redesign: deferred until observed funnel friction.
- Declaring startup throttling resolved: deferred until a non-zero real-browser sample is observed after the bundle deployment.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
