# poisk-turov-test — Autopilot State

Updated: 2026-08-28

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**Conversion UX 3.0 C1–C6 is production-green. Search/Brand/Trust 9.0 plus the latest mobile hardening are production-green. C7/B8/A8 still wait for meaningful real-browser funnel evidence. Continue 9.0 only where a fresh audit confirms a material UX/design gap; do not manufacture micro-redesigns.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is explicitly protected.

## Current confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- On mobile, all primary meal choices stay discoverable without requiring a hidden horizontal swipe.
- The fixed mobile search CTA may help while traversing the form, but must stop at the form boundary and stay hidden below it so post-form content is never covered.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve delayed/on-demand meals loading from PR #76: do not return `meals` to the immediate startup burst.
- AnyTour has four offices: Moscow, Saint Petersburg, Kaliningrad and Cheboksary.
- Yandex Maps review links may be surfaced, but do not hardcode ratings/review counts unless freshly verified.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material recent progress

### PR #81 — Trust 9.0

PR #81 replaced generic trust copy with verified AnyTour agency proof: four office locations, office review links, and confirmed contract/payment/support reassurance. It did not touch search API, analytics/Metrika, pricing or lead transport. Merge `9309d965db5c1232e78b7f9514709c00f5507444` was production-green.

### PR #82 — closed without merge

A Results 9.0 branch proposed adding representative meal to hotel decision facts. Review found meal is already shown in the best-offer price context, so the change duplicated information without evidence of a material UX gap. Preserve the current results/price hierarchy until live or visual evidence justifies a change.

### PR #84 — Trust 9.0 mobile readability

Five-viewport review found trust/reassurance secondary copy dropping to roughly 9–10px on compact screens. PR #84 raised the mobile readability floor while preserving layout and meaning. Merged production-green.

### PR #85 — Search 9.0 mobile meal discoverability

Visual review at 375/430 found the primary meal selector still used a hidden horizontal scroller: `Всё включено` was visibly clipped and later options required an undisclosed swipe. PR #85 wraps the meal choices on `<=700px`, so every primary meal option remains visible. It passed all PR visual/functional gates and deployed green.

### PR #86 + #87 — mobile sticky CTA boundary

PR #86 fixed a confirmed 430px overlap where the fixed `Найти туры` CTA remained over content after the search form. The CTA now yields to the normal inline submit once the form end reaches the safe viewport area.

A deeper audit immediately caught an edge-case: after the sentinel scrolled above the viewport, the first IntersectionObserver implementation could re-show the fixed CTA below the form. PR #87 hardens the rule: once the form end is reached or passed, the fixed CTA stays hidden. A dedicated Playwright gate now checks 375 and 430 at initial state, the form boundary and below-form/trust content.

PR #87 head `d280a616fcdb19685369f86c21ce54a86ac1205d` passed security, validation, selected-tour, B5 trust, baseline, general visual and the new mobile sticky-boundary regression. Merge `eac3f5dafe3078f8f62e30e6664fb2d72a652b17` is production-green: V2 deploy `33126181209`, active contract `33126181270`, tour-live `33126181200`, result-detail-live `33126181365`, post-deploy visual `33126233365` and baseline `33126233434` all passed.

### PR #89 — fresh browser audit trigger hardening

Repository Actions history showed no `schedule`-event runs even though `audit-v2-recent-browser.yml` declares an hourly cron. PR #89 therefore retained the cron and configured an additional `workflow_run` trigger after successful `Deploy V2 only`, checking out the exact deployed SHA. This is CI/observability only: no V2 product code, Metrika/goals, lead transport or neighboring project changed.

PR #89 passed Security Guard and merged as `b599e6269a421824ffa5bd370d82af9f81406e8e`. Its immediately following audit run `33134465815` was successful but the GitHub event is `push`, not `workflow_run`; therefore it verified the audit itself, not the post-deploy trigger path. Repository history still has no observed `schedule` or audit `workflow_run` event, so those trigger paths remain configured but unverified.

### PR #91 — true wall-clock recent audit

A re-audit found a correctness bug in the privacy-safe “recent 30m” evidence: the cutoff was derived from the newest log line, which could make an old idle period look like a fresh 30-minute window. PR #91 anchors access-log filtering to current UTC wall clock and error-log filtering to current server-local wall clock, while printing the exact UTC window end for evidence.

PR #91 passed Security Guard and merged as `555c303e3dff6aa5d77777fb9e9755099d61dab4`. Audit run `33137670977` passed on the merged code. Its actual window ended at `2026-08-28T03:01:53.839494+00:00`; access log latest was `2026-08-28T05:01:03+02:00`, error log latest `2026-08-28T04:53:18`, with **0 browser requests**, **0 browser 4xx/5xx** and **0 browser rate-limit events**. No product files changed, so no V2 production deploy was needed.

## Whole-flow re-audit

- Search ownership remains coherent: `search-lifecycle-v6.js` owns request/search state; `search-progress-ux-v1.js` only presents waiting/progress/error/zero states; `search-dirty-ux-v1.js` only presents stale-results state.
- The deterministic five-viewport baseline covers initial search, dates, guests, advanced filters, populated results, selected-tour checkout and zero-result recovery. Waiting/progress and stale presentation are currently contract-reviewed but are not explicit screenshot states in that baseline; extend the existing baseline rather than creating another overlapping visual workflow if a material regression or related product change appears.
- Selected-tour flight selection still advances journey state through `v2:flight-selected`; automatic default-flight loading remains intact.
- Price synchronization and lead context remain isolated contracts.
- Lead guard still provides phone validation, no-payment reassurance, selected price/flight summary, recoverable errors and success state.
- No confirmed material Search/Results/Selected Tour/Lead product defect was found in this pass, so no speculative product rewrite was made.

## Live evidence

- `audit-v2-live-traffic.yml`: privacy-safe rolling-tail context.
- `audit-v2-recent-browser.yml`: privacy-safe true wall-clock recent 30-minute browser window after PR #91; cron and successful-deploy `workflow_run` triggers remain configured.
- Fresh audit `33137670977`: window end `2026-08-28T03:01:53.839494+00:00`; access log latest `2026-08-28T05:01:03+02:00`; **0 real browser requests**, **0 browser 4xx/5xx**, **0 browser rate-limit events**, and therefore no `search`, `tour` or `lead` funnel signal in the actual current 30-minute window.
- Current absence of traffic gives no evidence basis for C7/B8/A8 conversion changes or another startup/rate-limit architecture change.
- GitHub cron and audit `workflow_run` execution remain unverified because repository Actions history exposes no matching events. The triggers stay configured and should be re-checked opportunistically; do not claim a trigger path proven until such a run is observed.
- Global `/images/...` symlink-loop warnings remain outside allowed V2/repository write scope while sampled access remains successful.

## Status

- C1 Search Experience 3.0 — **DONE**.
- C2 Results Experience 3.0 — **DONE**; no justified Results 9.0 rewrite currently.
- C3 Selected Tour Experience 3.0 — **DONE**.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- Search 9.0 primary controls/mobile discoverability/sticky boundary — **DONE / production-green**.
- Brand/Hero 9.0 — **DONE / production-green**.
- Trust 9.0 + mobile readability — **DONE / production-green**.
- Recent browser audit wall-clock correctness — **DONE / verified**.
- Cron/post-deploy audit trigger execution — **CONFIGURED / not yet observed**.
- C7 Live Conversion Optimization — **WAITING_FOR_TRAFFIC**.
- B8/A8 — **WAITING_FOR_TRAFFIC**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect the latest privacy-safe true wall-clock 30-minute browser audit; require a non-zero useful funnel sample before C7/B8/A8. Re-check for actual `schedule` or audit `workflow_run` events without blocking other safe work.
3. Re-audit the full V2 journey on mobile and desktop: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery.
4. Preserve first-screen stars+meal, mobile meal discoverability, bounded sticky CTA and delayed/on-demand meals loading.
5. Continue 9.0 only for a confirmed material gap. Current results/price and selected-tour/lead hierarchies are intentionally retained until evidence says otherwise.
6. If meaningful funnel evidence appears, activate C7/B8/A8 and prioritize observed friction from `search_started → search_complete → tour_selected → flight_selected → lead_started → lead_submitted`.
7. If production is healthy and evidence is absent, keep V2 stable rather than manufacturing work.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
