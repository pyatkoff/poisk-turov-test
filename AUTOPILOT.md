# poisk-turov-test — Autopilot State

Updated: 2026-08-28 21:25 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, SEO FOUNDATION 8.5

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation are all assessed at 9.0 with functional/visual evidence.

SEO/site foundation has advanced from 7.2 to **8.5**. The route-independent foundation now includes:

- reusable semantic AnyTour page shell/footer;
- server-rendered SEO content primitives and page contract;
- explicit country/resort/seasonal page types with editorial H1 ownership;
- stable first-party internal/related-link boundaries excluding query/hash/search-state URLs;
- curated clean-path page registry;
- structural publishability quality gate;
- registered parent/related page graph with unknown-reference and cycle rejection.

The current `/poisk-turov-test/v2/` route remains `noindex,follow` with no canonical. Do not promote it or invent a public canonical.

## Active roadmap

- BR1 Branded first impression — 9-LEVEL / MAINTAIN.
- BR2 Trust architecture — 9-LEVEL / MAINTAIN.
- BR3 Product-wide visual identity — 9-LEVEL / MAINTAIN.
- BR4 SEO-ready brand shell — ACTIVE at 8.5; next safe layer is a controlled editorial content-source/catalog workflow, then publication tooling independent of final route.
- BR5 Social + app footer — QUEUED; add MAX, Telegram, VK, App Store and Google Play only after exact supplied destinations are recovered/verified.
- PX1–PX6 — 9-LEVEL / MAINTAIN.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest BR4 evidence

- PR #147–#149: semantic footer, SEO page primitives and reusable landing-page contract.
- PR #151/#152/#156: stable internal/related-link boundaries.
- PR #153: reusable country/resort/seasonal page types; CI caught and removed unsafe automatic Russian inflection.
- PR #154: curated SEO page registry; query/hash/external/duplicate paths rejected.
- PR #155: publishability gate; incomplete/thin candidates and transient search state rejected.
- PR #157: registered parent/related relationship graph; unknown references and cycles rejected.
- PR #157 commit `ebdb8d8e6240283e4b89c05d5e67ff4c5cc1c076` deployed successfully in V2-only run `33206695319`; validate, copy, verify and live search smoke are green.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional/visual results for actual breakage.
2. Continue BR4 with a **controlled editorial content-source/catalog layer** that feeds only the curated registry; do not derive pages from request/search parameters.
3. Add validation that candidate content records satisfy registry, publishability and graph contracts before they can be considered publication candidates.
4. Keep current V2 route `noindex,follow`; do **not** add canonical, sitemap publication, structured data or indexability until the final public mount/URL is explicitly chosen.
5. After content-source/catalog tooling, reassess SEO/site foundation. The likely remaining blockers to 9 are real curated content inventory plus public route/canonical/indexing/sitemap policy.
6. Periodically re-audit the whole V2 conversion flow; production/lead/data/UX regressions outrank BR4.
7. Keep BR5 queued until exact external destinations are verified; do not guess URLs.
8. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

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
