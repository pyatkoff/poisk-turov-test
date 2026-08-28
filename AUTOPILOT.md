# poisk-turov-test — Autopilot State

Updated: 2026-08-29 00:10 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

SEO/site foundation remains **8.8**. Its remaining material path to 9 is real curated public content plus the explicit final public mount/canonical/indexing/sitemap contract; do not add abstraction merely to raise the score. The temporary `/poisk-turov-test/v2/` route remains `noindex,follow` with no canonical.

## Active roadmap

- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8 with a product/routing boundary; route-independent architecture is mature.
- BR5 Social + app footer — SHIPPED / MAINTAIN. V2 now contributes only a compact social/app community pre-footer and must not render a second full footer.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #180 fixed two owner-reported production UX regressions: trip duration is again part of the primary mobile search flow, with visible non-scrolling night choices, and V2 no longer renders a second full footer. Social/app links remain in a compact community pre-footer before the host site's canonical footer.
- PR #180 merged as production commit `62be26153bf6ed8f57a2103cbbcdad0725eafdc8`; V2-only deploy run `33214936774` completed successfully. The associated baseline/runtime workflows are green and no failed workflow is present for that release commit.
- Results decision-support audit confirmed that `Самая низкая цена` is not a decorative label: `conversion-confidence-v1.js` computes the minimum hotel price across the current result set, marks the cheapest hotel(s), and when the next distinct price is at least 1,000 ₽ higher also shows the exact gap to the nearest competing price. This is informational decision support and does not alter sorting, pricing, Tourvisor data or selection behavior.
- PR #174 fixed malformed inbound `child_age` hydration so invalid ages remain explicit invalid state instead of silently becoming age 0 or disappearing.
- PR #176 fixed unsupported inbound `child_count` values and child-count/age-control mismatches so visible tourist composition cannot differ from the composition sent to search.
- PR #175 added a true post-deploy five-viewport production-assets audit for selected tour → flights → lead recovery.
- PR #177 fixed mobile zero-result recovery focus and added five-viewport PR+post-deploy coverage for progress/zero/error recovery.

## Exact next work order

1. Treat production commit `62be26153bf6ed8f57a2103cbbcdad0725eafdc8` as the current green baseline.
2. Continue the independent whole-V2 production audit across search → waiting/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery, with special attention to owner-visible regressions that escaped prior automated checks.
3. Preserve primary trip-duration visibility on mobile and enforce the single-footer contract. Social/app destinations belong in the compact V2 community pre-footer, not a second full footer.
4. Keep `Самая низкая цена` as current-result-set decision support unless a real UX problem is confirmed; do not make it interactive merely for decoration. Verify its benchmark remains aligned with displayed hotel prices when touching results rendering.
5. Preserve post-deploy production-assets coverage for recovery, results and selected-tour surfaces; PR-only visual green is not sufficient for DONE.
6. Re-audit BR4 only when real curated content or the final public URL/mount decision is available. Keep current V2 `noindex,follow`; do not invent canonical, sitemap publication, structured data or indexability.
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
