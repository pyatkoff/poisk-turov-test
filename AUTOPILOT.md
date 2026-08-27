# poisk-turov-test — Autopilot State

Updated: 2026-08-28

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current phase

**Conversion UX 3.0 C1–C6 is production-green. Search/Brand/Trust 9.0 refinements are production-green. C7/B8/A8 still wait for meaningful real-browser evidence. Continue 9.0 only where a fresh audit confirms a material UX/design gap; do not manufacture micro-redesigns.**

Work stays inside `pyatkoff/poisk-turov-test`; production deploy stays V2-only. Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected. The AnyTour logo is explicitly protected.

## Current confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile.
- Other resort/hotel/operator/flight-detail filters may remain behind `Все фильтры`.
- Preserve delayed/on-demand meals loading from PR #76: do not return `meals` to the immediate startup burst.
- AnyTour has four offices: Moscow, Saint Petersburg, Kaliningrad and Cheboksary.
- Yandex Maps review links may be surfaced, but do not hardcode ratings/review counts unless freshly verified.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or the lead transport/external contract without explicit approval.

## Material recent progress

### PR #79 — mobile primary search

Fresh 375px review found mobile still pushed hotel category and meal into advanced filters. PR #79 corrected this while keeping nights in advanced filters for compactness. It preserves the first-screen stars+meal contract on mobile and keeps all five star choices visible without hidden horizontal swipe. Merged as part of fresh main before Trust 9.0.

### PR #81 — Trust 9.0

PR #81 replaced generic trust copy with verified AnyTour agency proof: four office locations, office review links, and the already-confirmed contract/payment/support reassurance used in the product. It did not touch search API, analytics/Metrika, pricing or lead transport.

PR head `252750e578848823bc5c27f80ad12186b14bef77` passed all six PR gates. Main merge `9309d965db5c1232e78b7f9514709c00f5507444` is production-green: V2 deploy/live search `33122903508`, result-detail live `33122903548`, tour live `33122903586`, post-deploy visual `33122969392` and visual baseline `33122969373` passed.

### PR #82 — closed without merge

A concurrent Results 9.0 branch proposed adding representative meal to hotel decision facts. Review found meal is already shown in the best-offer price context, so the change duplicated information without evidence of a material UX gap. PR #82 was closed without merge. Preserve the current results/price hierarchy until live or visual evidence justifies a change.

## Live evidence

- `audit-v2-live-traffic.yml`: privacy-safe rolling-tail context.
- `audit-v2-recent-browser.yml`: privacy-safe recent 30-minute browser window.
- PR #72 removed advanced hidden catalogs from the eager startup burst after real-browser rate-limit evidence.
- The last persisted recent-browser sample remained too small/zero-useful for C7. No newer useful 30-minute sample was available in the fresh run list during this session.
- Cumulative real-browser funnel evidence is still insufficient to justify conversion changes.
- Global `/images/...` symlink-loop warnings remain outside allowed V2/repository write scope while sampled access remains successful.

## Status

- C1 Search Experience 3.0 — **DONE**.
- C2 Results Experience 3.0 — **DONE**; no justified Results 9.0 rewrite currently.
- C3 Selected Tour Experience 3.0 — **DONE**.
- C4 Lead Experience 3.0 — **DONE**.
- C5 Flight Friction — **DONE**.
- C6 Visual Refinement — **DONE**.
- Search 9.0 mobile primary controls — **DONE / production-green**.
- Brand/Hero 9.0 — **DONE / production-green**.
- Trust 9.0 — **DONE / production-green**.
- C7 Live Conversion Optimization — **WAITING_FOR_TRAFFIC**.
- B8/A8 — **WAITING_FOR_TRAFFIC**.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/live/security/visual results.
2. Inspect the latest privacy-safe 30-minute browser audit; require a non-zero useful sample before C7/B8/A8.
3. Re-audit the full V2 journey on mobile and desktop: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery.
4. Preserve first-screen stars+meal and delayed/on-demand meals loading.
5. Continue 9.0 only for a confirmed material gap. Current results/price hierarchy is intentionally retained; PR #82 is precedent against duplicative micro-work.
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
