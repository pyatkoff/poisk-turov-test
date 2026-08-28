# poisk-turov-test — Autopilot State

Updated: 2026-08-29 01:55 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture is now explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. Country/content routes are being migrated incrementally.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active roadmap

- ROOT STABILIZATION — ACTIVE HIGHEST PRIORITY while the new standalone shell/routes are being migrated.
- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; publication/indexing policy remains deliberately deferred.
- BR5 Social + app footer — SHIPPED / MAINTAIN. Community/social/app content is a compact pre-footer; there must be exactly one canonical full footer.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #196 fixed the new homepage → search contract. The single `Ночей` field had silently submitted the default range `7–10`; it now submits an exact duration and keeps `daysTill` synchronized with `daysFrom`.
- PR #196 also removed dead standalone navigation during migration: `/poisk-turov/` stays local, while sections not yet migrated continue to the active legacy site. Header rewriting now preserves the valid local search route.
- PR #196 repaired stale CI assumptions after the homepage/search split: standalone rendering, SEO semantics, startup bundle count, live user-journey checks and visual checks now distinguish pre-merge branch validation from post-deploy production validation.
- PR #196 merged as `986b536c648de89b0e99cd209fa9815bd38f68b7`; Deploy anytoour.ru run `33221467704` completed successfully.
- Post-deploy live user journey completed homepage → full search → Tourvisor search → unique results → hotel/tour details → optional room → flights → non-writing lead-adapter health. No lead write was performed and the lead external contract was not changed.
- The first post-deploy visual run exposed a stale footer assertion rather than a product regression. Subsequent standalone commits added/standardized the canonical footer on migrated pages. Preserve exactly one `.at-site-footer` plus the compact `.v2-site-community` pre-footer.
- Main has since advanced beyond #196 with initial migrated country routes and verified local homepage navigation; verify the latest current-main deploy/visual/content-live runs before promoting additional routes.
- PR #180 remains the regression baseline for visible mobile trip duration and the no-second-full-footer contract.
- `Самая низкая цена` remains current-result-set decision support only; it does not alter sorting, tour pricing or selection.

## Exact next work order

1. Verify the latest current-`main` standalone deploy after the newest migrated-route/navigation commits, not only #196. Require deploy success and post-deploy homepage/search/content live checks.
2. Inspect the five-viewport standalone visual audit against the current contract: homepage and `/poisk-turov/` visible, no horizontal overflow, exact nights handoff, one compact community pre-footer and exactly one canonical full footer.
3. Continue whole standalone user-journey audit: homepage → search → waiting/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery. No live lead submission is required for regression proof.
4. Audit each newly migrated country/content route before promoting more routes: real route reachable, CTA/query handoff to `/poisk-turov/`, responsive layout, canonical/noindex semantics, and footer/navigation contract.
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
