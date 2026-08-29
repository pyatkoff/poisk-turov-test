# poisk-turov-test — Autopilot State

Updated: 2026-08-29 04:08 +02:00

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

- PR #201 fixed a confirmed standalone responsive regression: homepage navigation had disappeared below 1050px and internal content navigation below 900px. Navigation now remains available as a touch-friendly horizontally scrollable row on mobile/tablet, with no page-level horizontal overflow.
- PR #201 also exposes the already-live `/rb/` early-booking page from internal content navigation and aligns `SITE_MIGRATION_MAP.md` with actually-live country/content routes.
- PR #201 merged as `0001bfa7e8989fa5525bd3e365ec2caaa27e28ae`. Deploy anytoour.ru run `33227812050` completed successfully, including public-page verification, unchanged lead-bridge verification and live search smoke.
- Post-deploy root/search five-viewport visual run `33227911794` completed green.
- The migrated-content post-deploy run `33227911753` initially failed after all ten checked routes returned HTTP 200. The failure was in the guard itself: Bash `set -u` expanded `slug` inside the same `local` declaration before assignment. PR #202 fixes that nounset defect.
- PR #202 adds a dedicated live responsive navigation guard at 375/768/1024/1440. Production navigation is visible on homepage and `/hot/` at every audited width; `/hot/` exposes `/rb/`; no page-level overflow was found. The PR live run completed green.
- PR #199 remains the country handoff/data baseline: Turkey `4`, Egypt `1`, UAE `9`, Thailand `2`, Russia `47` are verified against the live Tourvisor catalogue and handed into `/poisk-turov/`.
- PR #196 remains the homepage exact-night baseline: one homepage `Ночей` value submits an exact duration, not a hidden range.
- PR #180 remains the one-full-footer baseline.
- `Самая низкая цена` remains current-result-set decision support only; it does not alter sorting, tour pricing or selection.

## Exact next work order

1. Confirm the corrected migrated-content post-deploy guard on the next standalone deployment; its previous failure was test-code-only and all checked production routes were HTTP 200.
2. Continue standalone content-route UX audit: `/hot/` → `/contacts/` → `/how-to-buy/` → `/rb/`, checking responsive hierarchy, CTA/search handoff, trust clarity, no 404s and the one-full-footer contract.
3. Audit discoverability from the homepage, including whether already-live `/rb/` deserves a first-class home navigation/card entry without crowding primary search conversion.
4. Continue whole standalone user-journey audit: homepage → search → waiting/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery. No live lead submission is required for regression proof.
5. Promote additional country/content routes only when their page exists locally and route/search handoff is verified; otherwise preserve the valid legacy destination.
6. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
7. Keep `Самая низкая цена` unchanged unless a confirmed UX defect appears.
8. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist.
9. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

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
