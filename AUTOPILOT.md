# poisk-turov-test — Autopilot State

Updated: 2026-08-29 03:14 +02:00

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

- PR #198 repaired the standalone post-deploy result-card visual gate: Playwright is now executed from the checkout where it is installed instead of `/tmp`. The repaired production result-card audit run `33225740279` completed green against current standalone production.
- PR #199 fixed migrated country → search handoff. The live Tourvisor catalogue for departure `1` confirms Turkey `4`, Egypt `1`, UAE `9`, Thailand `2`, Russia `47`; all five local country pages now send their authoritative `country=` value into `/poisk-turov/`.
- PR #199 also routes the five actually migrated countries locally from `/country/`; undeployed country pages remain on the active legacy site so migration does not create 404s.
- PR #199 closed the remaining mobile trip-duration overflow. Root cause was the more-specific old `.primary-search-flow .nights-quick` `!important` cascade overriding the earlier guard. The final guard now wins at the same specificity. Production five-viewport run `33225740311` reports `nightsOverflow:false` and page `overflow:false` at 375, 430, 768, 1024 and 1440; footer contract is one community pre-footer plus one canonical full footer at every width.
- PR #199 merged as `0bb1d9a1d6c1237f3bf118f5e8ffd10e257c2dba`; Deploy anytoour.ru run `33225638355` completed successfully. Live user-journey run `33225740338` also completed green through the search/tour flow without a live lead write.
- A dedicated source regression contract now cross-checks migrated country IDs against the live Tourvisor catalogue. The next live-content guard also verifies exact deployed `?country=` CTA handoff after deployment.
- PR #196 remains the homepage exact-night baseline: one homepage `Ночей` value submits an exact duration, not a hidden range.
- PR #180 remains the one-full-footer baseline.
- `Самая низкая цена` remains current-result-set decision support only; it does not alter sorting, tour pricing or selection.

## Exact next work order

1. Finish/verify the live-content country-handoff guard on production, including exact deployed `country=` values for Turkey/Egypt/UAE/Thailand/Russia.
2. Continue standalone content-route UX audit beyond the five initial countries: `/hot/` → `/contacts/` → `/how-to-buy/` → `/rb/`, checking mobile/tablet/desktop hierarchy, local/legacy navigation, CTA handoff, no 404s and one-footer contract.
3. Continue whole standalone user-journey audit: homepage → search → waiting/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery. No live lead submission is required for regression proof.
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
