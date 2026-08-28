# poisk-turov-test — Autopilot State

Updated: 2026-08-28 22:40 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

SEO/site foundation is now **8.8**. The route-independent foundation includes semantic shell/footer, server-rendered content primitives, explicit country/resort/seasonal contracts, stable first-party link boundaries, curated registry, structural publishability gate, registered relationship graph, controlled editorial content catalog and a deterministic review-only publication manifest. Publication candidates cannot be generated from arbitrary search/request state.

The current `/poisk-turov-test/v2/` route remains `noindex,follow` with no canonical. Final public route/canonical/indexing/sitemap policy remains intentionally deferred until the public mount is explicitly chosen.

## Active roadmap

- BR1 Branded first impression — 9-LEVEL / MAINTAIN.
- BR2 Trust architecture — 9-LEVEL / MAINTAIN.
- BR3 Product-wide visual identity — 9-LEVEL / MAINTAIN.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; route-independent architecture is mature. Remaining path to 9 is dominated by the real public mount/indexing contract and curated production content inventory rather than more abstract plumbing.
- BR5 Social + app footer — SHIPPED / MAINTAIN in PR #164 with verified MAX, Telegram, VK, App Store and Google Play destinations; responsive/touch regression coverage is active.
- PX1–PX6 — 9-LEVEL / MAINTAIN.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #160/#161/#163 hardened publishability and shipped the controlled editorial content catalog with integration coverage.
- PR #164 shipped the verified social/app footer.
- PR #166 fixed a production footer defect where structured `PHONE` could render as literal `Array`.
- PR #168 completed the same PHONE normalization for the header and repaired a false main-only SEO live check that expected a source CSS filename even though V2 serves a compiled bundle. All PR gates were green; V2-only deploy `33208778983` passed validate, copy, verify and live search smoke, and main SEO-foundation validation returned green.
- PR #169 shipped a deterministic review-only publication manifest containing only approved + publishable registered editorial records while explicitly excluding route/canonical/index/sitemap/schema side effects.

## Exact next work order

1. Verify the latest `main`/PR #169 V2-only deploy and live functional checks are green; repair any production regression before roadmap work.
2. Re-audit BR4 against the 9/10 gate. Avoid adding more framework layers solely to raise the score: the remaining material gap is real curated public content + the explicit final public URL/mount/indexing contract.
3. Keep the temporary V2 search route `noindex,follow`; do not invent canonical, sitemap publication, structured data or indexability.
4. If the public-route decision remains deferred, record it and continue independent safe work: periodic whole-V2 flow audit and repository technical-health pass, prioritizing any confirmed production/data/UX/responsive defect.
5. Maintain BR5 social/app footer and its phone/link/responsive regressions; do not alter Metrika/goals for those links.
6. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

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
