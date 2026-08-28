# poisk-turov-test — Autopilot State

Updated: 2026-08-28 07:53 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**Search/Brand/Trust 9.0 plus mobile hardening are production-green. Selected Tour keyboard flight synchronization from PR #93 is production-green. Both scheduled live-audit workflows are now proven to execute. The only known real-browser rate-limit signal remains one tiny visit with 9/9 HTTP 200 requests and four soft delay events; the latest scheduled 30-minute browser window contained no browser traffic and no rate-limit events. C7 stays in evidence review, B8 still waits for meaningful funnel behavior, and A8 remains active.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is protected. Global nginx/server configuration is outside this project’s allowed write scope.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- On mobile, all primary meal choices stay discoverable without a hidden horizontal swipe.
- The fixed mobile search CTA may help while traversing the form, but must stop at the form boundary and remain hidden below it.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve delayed/on-demand meals loading; do not return `meals` to the immediate startup burst.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material production baseline

PRs #76, #77, #79, #81, #84, #85, #86 and #87 established the current production-green Search/Brand/Trust/mobile baseline. PR #93 fixed native keyboard flight-radio state synchronization so checked radio, selected card, selected flight state/event, price context and eventual lead payload stay aligned. Its production verification remains green: Deploy V2 only `33138817934`, active contract `33138817904`, tour-live `33138817887` and result-detail-live `33138817891`.

No later product/V2 change has required a deploy.

## Live evidence

Post-deploy recent-browser run `33138875911` supplied the first useful non-zero browser sample: **9 browser requests, all HTTP 200, 0 browser 4xx/5xx, 4 browser rate-limit delay events**. Observed API actions were `departures`, `meals` and `countries`; no real-browser `search`, `tour` or `lead` action was observed.

Scheduled full live audit `33141756241` is also green. Its retained log tail contained 1142 V2 requests, all HTTP 200. Of 597 retained rate-limit events, 591 were attributed to headless checks, four to the same browser visit and two to curl checks. This confirms the historical rate-limit tail is overwhelmingly automation noise rather than evidence of broad user failure.

Most importantly, `Audit V2 recent browser traffic` has now also run successfully from GitHub event `schedule`: run `33145238161`, SHA `c46f219ef506a6cfd789c2ea5d916d882470a4dd`. Its true wall-clock window ended at `2026-08-28T05:36:05.083809+00:00` and contained **0 browser requests, 0 browser 4xx/5xx and 0 browser rate-limit events**.

Interpretation: scheduled execution is no longer a deferred reliability concern. The browser throttling signal is real but not yet repeated in another non-zero sample, so a request-architecture rewrite or global nginx change is not justified.

## Whole-flow state

- Search ownership remains coherent: search lifecycle owns request/search state; progress UX owns waiting/progress/error/zero; dirty UX owns stale-results state.
- Catalog startup remains intentionally narrow: departures then countries; advanced catalogs are deferred; meals remain delayed/on-demand.
- The deterministic baseline covers the primary mobile/desktop flow across 375/430/768/1024/1440 widths, populated results, selected-tour checkout and zero-result recovery.
- Selected-tour pointer and keyboard flight selection now converge on the same state/event path.
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
- Recent-browser wall-clock correctness — **DONE / verified**.
- Post-deploy recent-browser `workflow_run` — **DONE / verified**.
- Scheduled full live audit — **DONE / verified**.
- Scheduled recent-browser audit — **DONE / verified**.
- C7 Live Conversion Optimization — **EVIDENCE_REVIEW**.
- B8 Live Product Optimization — **WAITING_FOR_MEANINGFUL_FUNNEL**.
- A8 Operational Live Feedback — **ACTIVE**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect the next **non-zero** privacy-safe browser sample. Determine whether browser rate-limit delays repeat or remain isolated.
3. If repeated evidence shows material delay, reduce avoidable V2 request burst inside the repository using the smallest validated change. Do not touch global nginx/server configuration.
4. Continue the full V2 mobile+desktop audit: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery.
5. Preserve first-screen stars+meal, mobile meal discoverability, bounded sticky CTA and delayed/on-demand meals loading.
6. Require meaningful real-browser `search → tour → lead` evidence before changing Results/Selected-Tour/Lead conversion hierarchy.
7. If production remains healthy and evidence does not repeat, keep V2 stable rather than manufacturing micro-work.

## Deferred / boundaries

- Global nginx/rate-limit configuration: outside allowed V2/repository write scope.
- Global `/images/...` symlink-loop warnings: outside allowed V2/repository write scope while sampled access remains successful.
- Further Results/price or Selected-Tour/Lead redesign: deferred until observed funnel friction.
- Request-burst mitigation: deferred until another non-zero browser sample confirms persistent/user-relevant throttling.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
