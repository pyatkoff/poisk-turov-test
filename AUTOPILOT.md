# poisk-turov-test — Autopilot State

Updated: 2026-08-29 05:08 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture is explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. Country/content routes are being migrated incrementally.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active roadmap

- ROOT STABILIZATION — ACTIVE HIGHEST PRIORITY while the new standalone shell/routes are being migrated.
- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; publication/indexing policy remains deliberately deferred.
- BR5 Social + app footer — SHIPPED / MAINTAIN. Community/social/app content is a compact pre-footer; there must be exactly one canonical full footer.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #204 removed internal migration/roadmap language from customer-facing homepage, `/hot/` and `/country/`; the public copy now describes traveler choices rather than implementation state.
- `/hot/` now hands off to `/poisk-turov/` with a useful nearest-two-weeks date window instead of a generic search CTA, while keeping dates editable in the full search.
- Homepage now exposes already-live `/rb/` as a journey card without crowding the primary header/navigation; the five-card layout remains responsive.
- PR #204 merged as `8cfe1de254f9f5b5cb2efe3ab751151b92151dbb`. Deploy anytoour.ru run `33230329189` completed successfully, including public-page verification, unchanged lead-bridge verification and live search smoke.
- Post-deploy full live user journey run `33230425740` and root visual run `33230425748` completed green.
- Post-deploy migrated-content run `33230425725` exposed a second guard-only defect: fetched country pages and handoff assertions used different `/tmp` filenames. PR #205 unified both through one `page_out()` helper.
- PR #206 made the migrated-content live guard self-validating when its own workflow changes. Production validation run `33230550373` completed green, confirming all migrated routes and country → `/poisk-turov/?country=…` handoffs with the corrected guard.
- PR #202 remains the responsive navigation baseline: production navigation is visible at 375/768/1024/1440, `/rb/` is discoverable from internal navigation and no page-level overflow was found.
- PR #199 remains the country handoff/data baseline: Turkey `4`, Egypt `1`, UAE `9`, Thailand `2`, Russia `47` are verified against the live Tourvisor catalogue.
- PR #196 remains the homepage exact-night baseline; PR #180 remains the one-full-footer baseline.
- `Самая низкая цена` remains current-result-set decision support only; it does not alter sorting, tour pricing or selection.

## Exact next work order

1. Continue standalone content-route UX audit on `/contacts/` → `/how-to-buy/` → `/rb/`; `/hot/` customer copy/search handoff and homepage `/rb/` discoverability are now closed by PR #204.
2. Continue whole standalone user-journey audit: homepage → search → waiting/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery. No live lead submission is required for regression proof.
3. Re-audit user-facing content for any remaining migration/implementation language or weak route-to-search handoffs; keep technical migration state in repository docs, not public copy.
4. Promote additional country/content routes only when their page exists locally and route/search handoff is verified; otherwise preserve the valid legacy destination.
5. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
6. Keep `Самая низкая цена` unchanged unless a confirmed UX defect appears.
7. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist.
8. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`; production deploy scope is the allowed V2/standalone scope only.
- Do not redesign/replace the existing AnyTour logo.
- Do not modify neighboring projects, global site assets or server config outside allowed scope.
- Do not change Yandex Metrika configuration/goals.
- Do not change the existing lead-sending mechanism/external contract.
- Production breakage → lead loss → incorrect data → poor UX → responsive/visual → weakest sub-9 score → roadmap → cosmetic/refactor.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
- If one item is blocked, record/defer it and continue independent safe work.

## Explicitly inactive until owner launches traffic

Live conversion optimization/C7; live product optimization/B8; operational traffic feedback/A8; browser-session funnel analysis; waiting for `search → tour → lead` samples; traffic-based A/B-like conclusions.

Absence of traffic is expected and is never a blocker in the current phase.
