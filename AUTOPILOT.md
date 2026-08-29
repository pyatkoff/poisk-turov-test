# poisk-turov-test — Autopilot State

Updated: 2026-08-29 06:42 +02:00

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

- PR #211 fixed a false production-journey regression: Tourvisor may return a valid empty optional room-enrichment response, matching the existing client fallback contract. Full production journey run `33233807572` passed afterward.
- `/contacts/`, `/how-to-buy/` and `/rb/` were re-audited for CTA, trust and search handoff. No confirmed product/data defect remains on those routes.
- PRs #212–#214 expanded the production responsive guard to validate the actual visible primary CTA on every standalone route across mobile/tablet/desktop, including the mobile sticky search submit.
- That stronger guard exposed a real tablet/desktop defect: white text on the main orange `Найти туры` button had only 3.26:1 contrast.
- PR #215 changed the inline search CTA to accessible AnyTour dark orange `#D83D00`, preserved the blue mobile sticky hierarchy and added/retained visible keyboard focus. All 10 PR gates passed.
- PR #215 merged as `dc62dc59798ca931e1fdf3e72f6cdf65a670d165`. Standalone deploy `33234312261` passed release validation, copy, public-page verification, unchanged lead-bridge verification and live search smoke. V2-only deploy `33234312259` also passed validation, copy, verify and live search smoke.
- Post-deploy responsive content visual run `33234379091` passed at 375/768/1440, confirming the corrected primary CTA plus one canonical full footer and compact community pre-footer.
- Post-deploy full live user journey run `33234379100` passed homepage → search → Tourvisor results → selected tour → optional room enrichment → flights → lead-adapter health without creating a lead.
- URL/restored-state, child composition and stale-results logic were re-read during this pass. Current validation blocks invalid dates/nights/adults/child composition/price ranges; stale results are explicitly marked as belonging to previous conditions and require refresh. No new confirmed data defect was found there.
- `Самая низкая цена` remains current-result-set decision support: it is assigned only to the minimum-priced result, optionally shows the gap to the next distinct price, and does not alter sorting, tour pricing or selection.

## Exact next work order

1. Continue the whole standalone/V2 audit at waiting/progress/recovery → results/comparison → selected tour/rooms → flights/price → lead entry/recovery, looking for user-visible or data defects beyond the green smoke journey.
2. Re-audit results decision-support semantics and state transitions on mobile/tablet/desktop, including progressive/stale results, sorting, comparison and `Самая низкая цена` presentation.
3. Re-audit selected-tour transitions: room fallback, flight autoload/retry, selected-flight price synchronization and lead context, without changing the external lead contract.
4. Promote additional country/content routes only when their page exists locally and route/search handoff is verified; otherwise preserve the valid legacy destination.
5. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
6. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist.
7. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

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
