# poisk-turov-test — Autopilot State

Updated: 2026-08-28 05:58 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**Search/Brand/Trust 9.0 plus mobile hardening are production-green. Selected Tour keyboard flight synchronization from PR #93 is production-green. The first useful non-zero real-browser sample now exists: all sampled requests completed with HTTP 200, but nginx rate-limit delays affected one catalog request and three V2 static assets. C7 is therefore in evidence review; B8 still waits for meaningful funnel behavior; A8 live feedback is active.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is protected. Global nginx/server configuration is outside this project’s allowed write scope.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- On mobile, all primary meal choices stay discoverable without a hidden horizontal swipe.
- The fixed mobile search CTA may help while traversing the form, but must stop at the form boundary and remain hidden below it.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve delayed/on-demand meals loading from PR #76; do not return `meals` to the immediate startup burst.
- AnyTour has four offices: Moscow, Saint Petersburg, Kaliningrad and Cheboksary.
- Yandex Maps review links may be surfaced, but do not hardcode ratings/review counts unless freshly verified.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material recent progress

### Search / Brand / Trust 9.0

PRs #76, #77, #79, #81, #84, #85, #86 and #87 established the current production-green search/brand/trust baseline: primary stars+meal remain visible, all primary meal choices are discoverable on mobile, trust copy has a readable mobile floor, and the mobile sticky search CTA cannot overlap or reappear below post-form content. PR #82 was closed without merge because the proposed Results meal duplication did not solve a confirmed material gap.

### PR #91 — true wall-clock recent audit

PR #91 corrected the privacy-safe recent-browser audit so its 30-minute window is anchored to actual current wall-clock time rather than the newest log line. This removed the possibility of an old idle interval being mistaken for fresh evidence.

### PR #93 — Selected Tour keyboard flight synchronization

A whole-flow re-audit found a confirmed consistency bug with native radio keyboard navigation. Arrow-key movement could change the checked flight radio while `.is-selected`, `selectedFlightIndex`, `v2:flight-selected`, price context and the eventual lead payload remained on the previous flight.

PR #93 routes native radio `change` through the existing `selectFlight()` state/event path while preserving native activation. A deterministic Playwright regression uses `ArrowDown` and verifies that the second flight becomes the selected visual/state variant and that the mock lead payload contains that same flight and price. The external lead transport contract was not changed.

Merge `215adb6428cbc3f2bc0ba3dcfd521a825e818fbf` is production-green: Deploy V2 only `33138817934`, active contract `33138817904`, tour-live `33138817887` and result-detail-live `33138817891` all passed.

## Live evidence

Post-deploy audit run `33138875911` is important for two reasons:

1. It actually ran with GitHub event `workflow_run` after successful Deploy V2 only and checked out deployed SHA `215adb6428cbc3f2bc0ba3dcfd521a825e818fbf`. The post-deploy trigger path is therefore now verified.
2. Its true wall-clock window ended at `2026-08-28T03:26:25.014078+00:00` and contained the first useful non-zero browser sample: **9 browser requests, all HTTP 200, 0 browser 4xx/5xx and 4 browser rate-limit delay events**.

Observed browser API actions were `departures` x1, `meals` x1 and `countries` x1. Rate-limit events affected `departures` once plus `mobile-search-summary-v1.js`, `mobile-search-summary-v1.css` and `anytour-brand.css` once each. There was no `search`, `tour` or `lead` funnel action in this small sample.

Interpretation: no failed request is proven because all nine completed with 200, but the delay events are a real performance/UX signal. One tiny sample is not enough to justify a broad startup rewrite or asset bundling. Re-sample first; if the signal repeats, prefer safe V2 request/burst reduction. Do not change global nginx/server configuration from this project.

GitHub schedule-event execution is still unobserved: repository Actions history currently shows zero `schedule` runs. This is non-blocking because the successful-deploy `workflow_run` path is now proven.

## Whole-flow re-audit

- Search ownership remains coherent: `search-lifecycle-v6.js` owns request/search state; `search-progress-ux-v1.js` presents waiting/progress/error/zero; `search-dirty-ux-v1.js` presents stale-results state.
- Catalog startup remains intentionally narrow: `catalogs-v2.js` loads `departures` then `countries`; advanced catalogs load after the advanced section is opened. `meals` remains delayed/on-demand. Do not regress this contract while investigating rate limiting.
- The deterministic five-viewport baseline covers initial search, dates, guests, advanced filters, populated results, selected-tour checkout and zero-result recovery. Waiting/progress and stale presentation should be extended inside the existing baseline only when a material related regression/change appears.
- Selected-tour keyboard and pointer flight selection now converge on the same state/event path. Automatic default-flight loading remains intact.
- Price synchronization and lead context remain isolated contracts. Lead guard still provides validation, reassurance, selected price/flight summary, recoverable errors and success state.
- No broader Results/Selected Tour/Lead redesign is justified by the current live sample.

## Status

- C1 Search Experience 3.0 — **DONE**.
- C2 Results Experience 3.0 — **DONE**; current hierarchy intentionally retained.
- C3 Selected Tour Experience 3.0 — **DONE**; PR #93 keyboard consistency fix production-green.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- Search/Brand/Trust 9.0 + mobile hardening — **DONE / production-green**.
- Recent-browser wall-clock correctness — **DONE / verified**.
- Post-deploy recent-browser `workflow_run` — **DONE / verified**.
- Scheduled audit execution — **CONFIGURED / not observed**.
- C7 Live Conversion Optimization — **EVIDENCE_REVIEW**.
- B8 Live Product Optimization — **WAITING_FOR_MEANINGFUL_FUNNEL**.
- A8 Operational Live Feedback — **ACTIVE**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Re-sample the privacy-safe true wall-clock browser window. Determine whether browser rate-limit delays repeat, disappear or concentrate on specific V2 routes/assets.
3. If repeated evidence shows material delay, reduce avoidable V2 request bursts inside the repository using the smallest validated change. Do not touch global nginx/server configuration.
4. Continue the full V2 mobile+desktop audit: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery.
5. Preserve first-screen stars+meal, mobile meal discoverability, bounded sticky CTA and delayed/on-demand meals loading.
6. Require meaningful `search → tour → lead` evidence before changing Results/Selected-Tour/Lead conversion hierarchy.
7. Re-check schedule-event audit execution opportunistically; it is not a blocker now that post-deploy `workflow_run` is proven.
8. If production remains healthy and evidence does not repeat, keep V2 stable rather than manufacturing micro-work.

## Deferred / boundaries

- Global nginx/rate-limit configuration: outside allowed V2/repository write scope.
- Global `/images/...` symlink-loop warnings: outside allowed V2/repository write scope while sampled access remains successful.
- Further Results/price or Selected-Tour/Lead redesign: deferred until observed funnel friction.
- Small formatter/matchMedia/progressive-results micro-optimizations: deferred until profiling/live evidence justifies them.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
