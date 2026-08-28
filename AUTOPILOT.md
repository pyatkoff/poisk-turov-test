# poisk-turov-test — Autopilot State

Updated: 2026-08-29 01:05 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE ROOT STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

A new standalone production mount at `https://anytoour.ru/` is now being stabilized while the legacy `/poisk-turov-test/v2/` route remains available. This migration is currently higher priority than roadmap work because defects in the standalone shell or authenticated lead handoff can affect navigation, legal/consent UX or lead delivery.

SEO/site foundation remains **8.8**. The standalone root is still `noindex,follow`; do not enable canonical/indexing/sitemap/structured-data publication merely because the root mount now exists. The remaining material path to 9 still requires a deliberate public indexing contract and real curated content.

## Active roadmap

- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8 with indexing/publication still deliberately deferred.
- BR5 Social + app footer — SHIPPED / MAINTAIN. V2 contributes only a compact social/app community pre-footer and must not render a second full footer.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #180 fixed the owner-reported mobile trip-duration and duplicate-footer regressions: nights remain primary mobile search controls and V2 contributes only the compact social/app pre-footer before the canonical full footer.
- PR #182 introduced the standalone `anytoour.ru` root deployment while preserving the legacy V2 route and existing search/lead contracts.
- PR #183 restored the hidden Bitrix `sessid` token on the legacy Bitrix-backed page after the standalone bootstrap work made that dependency optional.
- Lead bridge investigation found two separate migration failures. First, nested HTTP forwarding could block on the PHP/Bitrix session lock; the receiver was moved to an authenticated in-process adapter handoff. Second, the standalone deploy updated only the receiver on the legacy host, which could pair it with an older incompatible adapter. PR #192 now deploys receiver + adapter + lead helpers as one compatible runtime. The production proof is not DONE until the queued standalone deploy passes the non-writing 422 validation probe and live-search smoke.
- PR #188 fixed standalone shell routing: V2 runtime assets resolve from `V2_CONFIG`, while full-site navigation/legal destinations that are not deployed on `anytoour.ru` temporarily route to the existing AnyTour site instead of dead root-relative pages.
- PR #189 fixed the lead-form privacy/consent destination on standalone root while preserving the legacy relative privacy URL.
- Results decision-support audit confirmed that `Самая низкая цена` benchmarks the minimum hotel price across the current result set and optionally shows the next-price gap; it does not alter sorting, pricing or selection.

## Exact next work order

1. Finish standalone production stabilization first. Verify `Deploy anytoour.ru` for current `main` after PR #192: public root → authenticated non-writing lead probe must return production validation (HTTP 422) → live Tourvisor search smoke must complete with unique results.
2. If the lead probe is still red, diagnose the exact response/log before touching unrelated UX. Do not weaken public CSRF/session validation, lead validation, field mapping, SOURCE, idempotency or Bitrix write behavior.
3. Once standalone deploy is green, inspect the actual root across mobile/intermediate/desktop for search → waiting/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery. Preserve visible primary trip duration and the single-footer contract.
4. Audit standalone-only links/assets as part of that pass: logo, phone fallback, header navigation, footer/legal links, privacy consent, social/app links and bundle/runtime paths must all remain reachable.
5. Keep `Самая низкая цена` as current-result-set decision support unless a real UX problem is confirmed; do not add interaction merely for decoration.
6. Preserve the legacy `/poisk-turov-test/v2/` behavior while stabilizing root. Standalone fixes must not break its relative runtime paths, privacy URL, Bitrix session behavior or existing lead contract.
7. Re-audit BR4 publication only when real curated content and an explicit indexing/canonical/sitemap decision exist. Root deployment by itself is not permission to index.
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
