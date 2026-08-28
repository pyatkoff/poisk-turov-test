# poisk-turov-test — Autopilot State

Updated: 2026-08-28 18:12 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — PRE-TRAFFIC 9/10 QUALITY GATE

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence. Do not spend autonomous development time collecting, waiting for, interpreting or optimizing against traffic until the owner explicitly re-enables that phase.

The product release gate remains: **every material area >= 9/10**. If production is healthy, audit the whole product, score the material areas, then improve the weakest sub-9 area. Never stop because traffic is absent.

## Scorecard areas

Search UX; waiting/progress/recovery; results & comparison; selected tour; flights & price confidence; lead UX; mobile UX; tablet/desktop UX; brand & trust; visual quality/consistency; product differentiation/competitor gap; SEO/future site foundation.

Scores are product-quality assessments backed by functional and visual evidence, not traffic metrics. Re-score after material changes. A 9/10 score means ready for traffic-quality scrutiny, not “perfect forever”.

Latest re-score after BR3 PR #130: Results/Comparison, Selected Tour, Flights/Price, Lead UX, Visual Quality and Product Differentiation are now at 9.0. Search, Waiting/Recovery, Mobile, Tablet/Desktop and Brand/Trust remain tied at 8.9. SEO remains intentionally deferred at 7.2 until the core search product reaches the gate.

## Active roadmap

- BR1 Branded first impression — ACTIVE; grounded AnyTour-specific first-screen proof shipped in PR #123.
- BR2 Trust architecture — ACTIVE; next where cross-stage trust gaps are confirmed.
- BR3 Product-wide visual identity — 9-LEVEL / MAINTAIN. PR #128 corrected the primary search CTA mismatch. PR #130 unified primary/secondary control hierarchy across search recovery, results/selected-tour controls and mobile sticky search, including focus/touch/reduced-motion treatment. Production visual evidence is green.
- BR4 SEO-ready brand shell — QUEUED until all core product areas >= 9.
- BR5 Social + app footer — QUEUED; add a polished lower-page/footer presence for AnyTour social channels (MAX, Telegram, VK) and mobile apps (App Store, Google Play), using verified real destination URLs only. Keep it secondary to search/lead conversion, responsive and touch-friendly; do not introduce new analytics goals or alter lead transport. Recover/verify the previously supplied links before implementation rather than guessing destinations.
- PX1 Decision support in results — ACTIVE / 9-level.
- PX2 Flexible search/recovery — ACTIVE; explicit zero-result date recovery shipped; score 8.9 and now the highest-priority UX audit.
- PX3 Price confidence — SHIPPED / 9-level.
- PX4 Flight decision quality — ACTIVE / 9-level.
- PX5 Hotel choice depth — SHIPPED / 9-level.
- PX6 Save/compare/resume — ACTIVE / 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Production baseline

PR #130 (`930ba5e8492f4e03d8caac80ac0aac60e849871c`) consolidated the BR3 control hierarchy without changing search behavior, pricing, analytics, Metrika, lead transport, logo or external contracts. It aligned the formerly mixed orange/black primary controls with the established AnyTour blue action hierarchy across results/selected-tour, search recovery and the mobile sticky search path, while standardizing secondary controls and focus/touch/reduced-motion behavior.

A startup-bundle CI contract initially failed only because the legitimate new CSS asset increased the hard-coded manifest count from 20 to 21. The contract was synchronized, after which all 10 PR checks passed. V2-only deploy `33188516514` is green including Verify V2 and Live search smoke. Post-deploy visual `33188608918` and deterministic visual baseline `33188608930` are green. Production is fully green on `930ba5e8492f4e03d8caac80ac0aac60e849871c`.

Earlier production contracts remain protected: V2 bundles, legacy/AI/MAX URL hydration, primary catalog sync, responsive meal visibility, bounded mobile CTA, selected-tour/flight/price behavior, structured continue-search progress ownership and lead transport.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional/visual results for actual breakage.
2. Audit Waiting / progress / recovery (8.9) across mobile/intermediate/desktop, especially zero/stale-result dead ends and whether explicit nights/filter alternatives can reduce friction without silently broadening criteria.
3. If a material recovery improvement is confirmed, implement it as explicit user-controlled recovery, run functional/regression/visual gates, deploy V2 only when green and smoke production.
4. If recovery has no safe material gap, immediately continue the tied 8.9 areas: Mobile UX → Search UX → Tablet/Desktop → Brand/Trust, fixing confirmed friction only.
5. Periodically re-audit the full V2 journey: search → waiting/progress → stale/zero → results/comparison → selected tour/rooms → flights/price → lead/recovery.
6. Keep BR5 social/app footer queued; recover and verify exact external destinations before implementation.
7. Keep SEO expansion deferred until all core product areas reach 9.
8. Do not run traffic diagnostics or make conversion conclusions from owner/team usage until explicitly re-enabled.

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
