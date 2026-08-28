# poisk-turov-test — Autopilot State

Updated: 2026-08-28 22:00 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, SEO FOUNDATION 8.7

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

SEO/site foundation is now **8.7**. Route-independent groundwork includes:

- reusable semantic AnyTour shell/footer;
- server-rendered SEO content primitives and page contract;
- explicit country/resort/seasonal page types with editorial H1 ownership;
- stable first-party breadcrumbs/internal/related-link boundaries excluding query/hash/search-state URLs;
- curated clean-path page registry;
- structural publishability quality gate;
- registered parent/related graph with unknown-reference and cycle rejection;
- controlled editorial catalog with draft/review/approved lifecycle and approved+publishable candidate gating;
- review-only publication manifest that carries editorial/graph context but cannot enable routes, canonical, sitemap, schema or indexing by itself.

The current `/poisk-turov-test/v2/` route remains `noindex,follow` with no canonical. Do not promote it or invent a public canonical.

## Active roadmap

- BR1 Branded first impression — 9-LEVEL / MAINTAIN.
- BR2 Trust architecture — 9-LEVEL / MAINTAIN.
- BR3 Product-wide visual identity — 9-LEVEL / MAINTAIN.
- BR4 SEO-ready brand shell — ACTIVE at 8.7. Safe infrastructure/tooling is mature; remaining material gap is real curated editorial inventory plus the explicit future public route/canonical/indexing/sitemap policy.
- BR5 Social + app footer — SHIPPED / MAINTAIN. Verified AnyTour destinations are present for MAX, Telegram, VK, App Store and Google Play; responsive/touch regressions are covered.
- PX1–PX6 — 9-LEVEL / MAINTAIN.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #160 hardened publishability breadcrumbs so unstable/query/hash/external breadcrumb ancestry cannot become publishable.
- PR #161 added the controlled editorial SEO content catalog; PR #163 integrated hardened publishability into catalog candidate regression.
- PR #164 shipped BR5 social/app footer with MAX, Telegram, VK, App Store and Google Play destinations and responsive verification.
- Post-deploy full-page evidence after #164 found a real production UX bug: structured `PHONE` from `site_conf.php` rendered as literal `Array` in the server footer.
- PR #166 introduced shared structured-phone normalization and fixed the footer. V2-only deploy `33208316418` passed validate/copy/verify/live-search smoke; post-deploy visual run `33208386400` passed all audited viewports and evidence shows the real phone number instead of `Array`.
- PR #168 completed the same structured-phone normalization for initial header state and repaired the main-only SEO live contract to validate the actual compiled stylesheet link rather than a source filename. Main SEO foundation run `33208779148` is green; V2-only deploy `33208778983` passed validate/copy/verify/live-search smoke.
- PR #169 added the route-independent publication review manifest. All PR gates were green and V2-only deploy `33208974160` passed validate/copy/verify/live-search smoke.
- Current production baseline commit: `29cc99b59e156d6ad2e6c7c64eff5eb8d2496caa`.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional/visual results for actual breakage.
2. Re-audit the complete V2 journey periodically on mobile/intermediate/desktop: search → waiting/progress → stale/zero recovery → results/comparison → selected tour/rooms → flights/price → lead entry, with production/lead/data/UX regressions outranking roadmap work.
3. Continue BR4 only with safe route-independent work. The highest-value remaining item is a **small, genuinely curated editorial content inventory** that passes catalog/publishability/graph review; do not synthesize page identity or copy from request/search state and do not mass-generate thin pages.
4. Do **not** add public routing, canonical, sitemap publication, structured data or indexability until the final public mount/URL and publication policy are explicitly chosen. This is the remaining product/routing blocker to SEO 9.0, not a reason to stop independent maintenance/re-audit work.
5. Maintain BR5 regressions: social/app URLs must remain explicit AnyTour destinations; footer/header phone must never render structured values as `Array`; mobile external controls stay touch-friendly and overflow-free.
6. Re-score SEO only after real curated inventory or a resolved public-route/indexing policy materially changes readiness. Do not inflate to 9.0 from tooling alone.
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
