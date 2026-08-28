# poisk-turov-test — Autopilot State

Updated: 2026-08-28 23:19 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

SEO/site foundation remains **8.8**. Its remaining material path to 9 is real curated public content plus the explicit final public mount/canonical/indexing/sitemap contract; do not add abstraction merely to raise the score. The temporary `/poisk-turov-test/v2/` route remains `noindex,follow` with no canonical.

## Active roadmap

- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8 with a product/routing boundary; route-independent architecture is mature.
- BR5 Social + app footer — SHIPPED / MAINTAIN with verified destinations and phone/link/responsive regressions.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #174 fixed malformed inbound `child_age` hydration so invalid ages remain explicit invalid state instead of silently becoming age 0 or disappearing.
- PR #176 fixed unsupported inbound `child_count` values and child-count/age-control mismatches so visible tourist composition cannot differ from the composition sent to search.
- PR #175 added a true post-deploy five-viewport production-assets audit for selected tour → flights → lead recovery. It was proven green on the next real production deploy and again after PR #177.
- PR #177 fixed a confirmed mobile zero-result recovery defect found by the new production audit. After a search had collapsed the mobile form, recovery now expands it and focuses the CTA that is actually visible: mobile sticky `Найти туры` on short screens or inline `Найти туры` when visible. Date ±2 / nights ±1 recovery remains explicit and never auto-starts search.
- PR #177 also adds a five-viewport PR+post-deploy contract for progress/zero/error recovery, exact recovery values, no implicit search start, active CTA focus, filter handoff, alert/retry, page errors and horizontal overflow.
- PR #177 production commit `85aee5bde49a14c53db3e1ec904c85ce387903a4` deployed successfully in V2-only run `33211774500`: validate, copy, verify and live search smoke are green.
- Production recovery run `33211877630` is green on 375/430/768/1024/1440 using real production bundles.
- Production selected-tour/flight/lead run `33211877611` is green on the same five viewports using real production bundles.
- General post-deploy visual run `33211877724` and deterministic baseline run `33211877643` are green.

## Exact next work order

1. Treat production commit `85aee5bde49a14c53db3e1ec904c85ce387903a4` as the current green baseline.
2. Continue the independent whole-V2 audit while BR4 public-route/indexing decisions remain deferred: inspect actual production screenshots and remaining state boundaries across results/comparison → selected tour/rooms → flights/price → lead entry/recovery.
3. Fix only confirmed production/data/UX/responsive defects. Preserve the new recovery contract: recovery expands mobile search, focuses the effective visible CTA, changes only the explicitly requested date/night window and never submits automatically.
4. Preserve post-deploy production-assets coverage for both recovery and selected-tour surfaces; a PR-only visual pass is not sufficient for DONE.
5. Re-audit BR4 only when real curated content or the final public URL/mount decision is available. Keep current V2 `noindex,follow`; do not invent canonical, sitemap publication, structured data or indexability.
6. Maintain BR5 social/app footer and do not alter Metrika/goals.
7. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`; production deploy scope is V2 only.
- Do not redesign/replace the existing AnyTour logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika configuration/goals.
- Do not change the existing lead-sending mechanism/external contract.
- Production breakage → lead loss → incorrect data → poor UX → responsive/visual → weakest sub-9 score → roadmap → cosmetic/refactor.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
- If one item is blocked, record/defer it and continue independent safe work.

## Explicitly inactive until owner launches traffic

Live conversion optimization/C7; live product optimization/B8; operational traffic feedback/A8; browser-session funnel analysis; waiting for `search → tour → lead` samples; traffic-based A/B-like conclusions.

Absence of traffic is expected and is never a blocker in the current phase.
