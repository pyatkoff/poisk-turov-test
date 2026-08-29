# poisk-turov-test — Autopilot State

Updated: 2026-08-29 06:29 +02:00

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

- PR #208 fixed a confirmed standalone CTA accessibility/visual regression: primary orange CTAs now meet text contrast and expose a visible keyboard focus indicator.
- PR #209 identified that standalone content routes could not rely on root static CSS delivery; PR #210 closed the live styling break by inlining the trusted local shared-shell CSS. `Deploy anytoour.ru` run `33233481410` completed green.
- Post-#210 production visual content run `33233571681` passed across 375/768/1440 for homepage, search, contacts, how-to-buy, early booking, hot tours, country catalog and Turkey. It confirmed no page overflow, exactly one canonical footer + one compact community pre-footer and valid search handoffs.
- The first post-#210 full journey run `33233571698` failed only because its regression test treated enriched room description as mandatory. The real V2 client already treats an absent `/rooms` description as optional and renders fallback copy.
- PR #211 aligned the live guard with actual product behavior: a room-details response must be valid/non-error, but enrichment may legitimately be empty. The self-validating full production journey run `33233807572` then passed through homepage → search → Tourvisor results → selected tour → optional room enrichment → flights → lead-adapter health without creating a lead.
- `/contacts/`, `/how-to-buy/` and `/rb/` source/product audit is complete: all have a clear primary handoff to `/poisk-turov/`, secondary manager/contact paths where appropriate, no migration/implementation language and no confirmed data/product defect.
- PR #212 expands the responsive production visual guard so every standalone content route, not only Contacts, must retain a visible primary CTA with >=4.5:1 text contrast and keyboard focus. The guard self-validates when its own workflow changes.
- PR #206 remains the migrated-content live baseline; country → `/poisk-turov/?country=…` handoffs are verified for Turkey `4`, Egypt `1`, UAE `9`, Thailand `2`, Russia `47`.
- PR #196 remains the homepage exact-night baseline; PR #180 remains the one-full-footer baseline.
- `Самая низкая цена` remains current-result-set decision support only; it does not alter sorting, tour pricing or selection.

## Exact next work order

1. Resume the whole standalone/V2 user-journey audit at search waiting/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery, looking for user-visible or data defects beyond the already-green live smoke path.
2. Re-audit the search form and URL/restored state across mobile/desktop, then results card comparison/lowest-price semantics and selected-tour state transitions; prioritize confirmed UX/data issues over framework work.
3. Re-audit user-facing content for any remaining migration/implementation language or weak route-to-search handoffs; `/contacts/`, `/how-to-buy/`, `/rb/`, `/hot/` are currently closed unless new evidence appears.
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
