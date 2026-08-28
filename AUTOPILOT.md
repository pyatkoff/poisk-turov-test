# poisk-turov-test — Autopilot State

Updated: 2026-08-28 14:04 +02:00

This is the operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the exact machine-readable resume point. `PRODUCT_ROADMAP.md` is now the active Brand + Product experiments / competitor-gap roadmap.

## Current phase

**PR #113 is merged, deployed and production-green. The product is no longer globally waiting for meaningful funnel traffic before roadmap work: Brand and safe/reversible Product experiments run in parallel with C7/A8 live evidence. The first PX1 decision-support experiment is live: within a multi-hotel result set, cards can show truthful contextual badges for the current lowest valid price and best valid rating. These badges use only already-returned data, do not change sorting, add no API traffic, and are cleared on a new/dirty search.**

Production deploy stays V2-only. Yandex Metrika configuration/goals, the existing lead-sending mechanism, neighboring projects, global server configuration and the existing AnyTour logo remain protected.

## Active roadmap lanes

- **BR1 Branded first impression — ACTIVE.** Keep the existing AnyTour logo, strengthen the coherent AnyTour product impression/value proposition without pushing the primary search task down the page.
- **BR2 Trust architecture — ACTIVE.** Real offices/reviews, human support, and clear verification/payment expectations at useful decision points; no unsupported claims or fake urgency.
- **PX1 Decision support in results — ACTIVE.** PX1.1 contextual lowest-price / best-rating badges is production-green. Next candidates must reduce comparison effort without silently changing sort or data.
- **PX2 Flexible search / recovery — QUEUED.** Explicit, user-visible relaxation only; never silently broaden criteria.
- **PX3 Price confidence — QUEUED.** Improve clarity around shown/actualized price without altering pricing contracts.
- **PX4 Flight decision quality — QUEUED.** Surface useful trade-offs while preserving existing flight-selection semantics.
- **PX5 Hotel choice depth — QUEUED.** Use already-returned rating/location/sea/room/meal facts for better decisions.
- **PX6 Save / compare / resume — QUEUED.** Research lightweight shortlist/compare without mandatory account creation.
- **PX7 Price watch / return intent — RESEARCH.** This may require persistence/contact/product-contract choices and is not an autonomous implementation yet.
- **C7 Live Conversion Optimization — EVIDENCE_REVIEW.** Funnel hierarchy/A-B-like decisions still require meaningful live evidence.
- **A8 Operational Live Feedback — ACTIVE.** Production/live findings can interrupt roadmap work by priority.

## Confirmed product contracts

- Hotel category/stars and meal type stay visible on the first search screen, including mobile and intermediate widths.
- All primary meal choices remain directly visible/discoverable without hidden horizontal scrolling; wrapping is allowed when width is insufficient.
- The fixed mobile search CTA stops at the form boundary and remains hidden below it.
- Meals remain delayed/on-demand rather than returning to the immediate startup API burst.
- Active CSS/JS bundle source order remains preserved; bundling is transport optimization, not a behavior rewrite.
- Legacy/AI/MAX V2 entry URLs continue to hydrate supported camelCase/snake_case parameters; auto-start requires an explicit valid search URL.
- URL-requested departure/country must leave visible primary catalogs consistent with actual search IDs.
- Product experiments may annotate/assist decisions, but must not silently mutate user criteria, sorting, prices or lead behavior.
- Do not redesign/replace the logo.
- Do not change Metrika/goals or lead transport/external contract without explicit approval.

## Material production baseline

- PR #97/#98: startup browser transport collapsed from 44 CSS/JS requests to one CSS bundle + one JS bundle while preserving ordered source closure.
- PR #100: legacy/AI/MAX V2 URL hydration and explicit valid-link auto-start restored.
- PR #103: departure/country catalog consistency fixed and directly production-verified with non-default `Пермь (2) → Египет (1)`.
- PR #110/#111: primary meal clipping fixed; regression green at 375/430/768/901/1024/1100/1101/1200/1440 with all five meal choices visible, no internal/document horizontal overflow.
- PR #113 (`87081d0aac971a0b84ba44602b47ea54819aef59`): activated `PRODUCT_ROADMAP.md` and shipped PX1.1 contextual result decision badges.

PR #113 PR gates are all green: Security guard `33169149763`; Validate V2 PR `33169149760`; startup bundles `33169149813`; B5 trust visual `33169149768`; selected-tour visual `33169149785`; meal visibility `33169149741`; V2 PR visual `33169149839`; full visual baseline `33169149744`.

PR #113 production verification is green: Deploy V2 only `33169273215` including V2 verify + live search smoke; Visual V2 post-deploy `33169352847`; post-deploy Visual V2 baseline `33169352930`; recent-browser audit `33169352897`.

## Live evidence

The latest non-zero privacy-safe real-browser sample remains diagnostic #109 run `33164345519`: **1 browser session, 12 requests, all HTTP 200, 0 browser 4xx/5xx**, actions `departures` x2, `meals` x2, `countries` x2, and no real `search/tour/lead` action. Soft rate limiting occurred on `departures` x2 and JS bundle x1, but there is still no measured user/funnel impact; do not add client pacing or touch global nginx on that evidence alone.

Post-#113 audit `33169352897`, ending `2026-08-28T12:01:06.688765+00:00`, is clean: **0 real-browser requests, 0 browser 4xx/5xx, 0 browser rate-limit events**. This is health evidence only, not conversion evidence.

## Whole-flow state

- Search lifecycle owns search/request state; progress UX owns waiting/progress/error/zero; dirty UX owns stale-results state.
- Deterministic visual coverage spans mobile/intermediate/desktop and populated results, selected-tour checkout and zero-result recovery.
- Result cards now combine absolute decision facts (rating/sea distance) with truthful result-relative PX1 badges when a comparison exists.
- Pointer/keyboard flight selection converge on the same event/state path.
- Flight price synchronization and lead context remain isolated contracts; external lead transport is unchanged.
- Safe Brand/Product roadmap work no longer waits for funnel volume. Changes to conversion hierarchy, silent criteria broadening, pricing semantics or lead behavior remain evidence/approval gated.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest production/security/visual/live evidence; production/lead/data failures always interrupt roadmap work.
2. Continue a whole-flow V2 audit: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery, including mobile/intermediate/desktop and URL entry.
3. Continue **PX1/PX5 decision support** from already-returned data; prefer comparison clarity over extra controls. Validate any experiment visually and functionally before release.
4. Audit **PX2 recovery** for cases where exact search returns zero/few results; any relaxation must be explicit and reversible.
5. Continue **BR1/BR2** where the AnyTour brand/trust presentation can improve without obscuring the primary task or replacing the logo.
6. Inspect the next non-zero privacy-safe browser sample. Meaningful `search → tour → lead` evidence can reprioritize C7 and override roadmap ordering.
7. Keep PX7 price-watch in research until persistence/contact/lead-contract implications are resolved; continue other independent work rather than blocking.

## Deferred / boundaries

- Global nginx/rate-limit configuration: outside allowed scope.
- Global `/images/...` symlink-loop warnings: outside allowed V2/repository write scope while sampled access remains successful.
- Client-side pacing for departures/bundle: repeated soft signal exists but no measured user/funnel impact.
- Conversion hierarchy/A-B-like conclusions: wait for meaningful live funnel evidence.
- PX7 price watch / proactive return-intent capture: research only until persistence/contact/product-contract choices are safe and explicit.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not change the logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika/goals without explicit approval.
- Do not change lead-sending mechanism/external contract without explicit approval.
- Record/defer blocked items and continue independent safe work.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
