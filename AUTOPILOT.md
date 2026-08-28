# poisk-turov-test — Autopilot State

Updated: 2026-08-28 18:41 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — PRE-TRAFFIC 9/10 QUALITY GATE

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence. Do not spend autonomous development time collecting, waiting for, interpreting or optimizing against traffic until the owner explicitly re-enables that phase.

The product release gate remains: **every material area >= 9/10**. If production is healthy, audit the whole product, score the material areas, then improve the weakest sub-9 area. Never stop because traffic is absent.

## Scorecard areas

Search UX; waiting/progress/recovery; results & comparison; selected tour; flights & price confidence; lead UX; mobile UX; tablet/desktop UX; brand & trust; visual quality/consistency; product differentiation/competitor gap; SEO/future site foundation.

Latest re-score after the focused 375/430 mobile journey audit: Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Visual Quality and Product Differentiation are at 9.0. Search, Tablet/Desktop and Brand/Trust remain tied at 8.9. SEO remains intentionally deferred at 7.2 until the core search product reaches the gate.

## Active roadmap

- BR1 Branded first impression — ACTIVE; grounded AnyTour-specific first-screen proof shipped in PR #123.
- BR2 Trust architecture — ACTIVE; next where cross-stage trust gaps are confirmed.
- BR3 Product-wide visual identity — 9-LEVEL / MAINTAIN; PR #128/#130 plus the mobile audit removed confirmed mixed-generation/control-size defects.
- BR4 SEO-ready brand shell — QUEUED until all core product areas >= 9.
- BR5 Social + app footer — QUEUED; add MAX, Telegram, VK, App Store and Google Play only after exact supplied destinations are recovered/verified. Keep secondary to conversion, responsive/touch-friendly; no new analytics goals and no lead-transport change.
- PX1 Decision support in results — ACTIVE / 9-level.
- PX2 Flexible search/recovery — 9-LEVEL / MAINTAIN; PR #132 adds explicit nights ±1 recovery alongside date ±2, edit and filter paths. Recovery only edits form values and never silently submits.
- PX3 Price confidence — SHIPPED / 9-level.
- PX4 Flight decision quality — ACTIVE / 9-level.
- PX5 Hotel choice depth — SHIPPED / 9-level.
- PX6 Save/compare/resume — ACTIVE / 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Production baseline

The focused mobile audit closed confirmed friction across the complete journey without changing protected contracts:

- PR #134 fixed the <=560px sticky collision between the mobile search summary and active progress state.
- PR #135 normalized advanced-filter toggle targets from 40px to 44px and added visible keyboard focus.
- PR #136 extended the dedicated 375/430 sticky regression workflow to cover `search-progress-ux-v1.css` and assert the summary-aware stack offset.
- PR #138 normalized mobile results-filter chips/choices/close/empty action from 42px to 44px.
- PR #139 normalized selected-tour back/description text actions to 44px.
- PR #141 normalized the clickable flight-choice row from 34px to 44px.

PR #141 production commit is `fe442fcf02708b577ad3c95e895d5a708bee7228`. All PR checks passed. V2-only deploy `33190849173` is green; active V2 contract `33190849147` and result-detail live `33190849208` are green; deterministic baseline `33190955983` and post-deploy viewport audit `33190955981` are green. Mobile UX is therefore re-scored to 9.0.

Earlier production contracts remain protected: V2 bundles, legacy/AI/MAX URL hydration, primary catalog sync, responsive meal visibility, bounded mobile CTA, selected-tour/flight/price behavior, structured continue-search progress ownership and lead transport.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional/visual results for actual breakage.
2. Audit Search UX (8.9) as the next weakest core area: primary form hierarchy, destination/date/night/guest clarity, advanced-filter discoverability, validation/error recovery, submit lifecycle and return-to-edit behavior across mobile/intermediate/desktop.
3. Fix only confirmed search friction; keep the primary task compact and preserve existing search/API/analytics/lead contracts. Run relevant branch/functional/visual gates and deploy V2 only when green.
4. If Search UX is already 9-level after evidence-based audit, re-score it and move immediately to Tablet/Desktop UX (8.9), then Brand/Trust (8.9).
5. Periodically re-audit the entire V2 journey, not only recently changed surfaces.
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
