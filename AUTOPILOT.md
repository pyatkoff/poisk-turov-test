# poisk-turov-test — Autopilot State

Updated: 2026-08-29 07:12 +02:00

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

- Whole-flow recovery audit found a real reliability defect in progressive search rendering: a transient `search_results` refresh failure during an otherwise healthy Tourvisor search escaped into the outer status-poll catch and stopped polling completely.
- PR #217 isolates only intermediate progressive-result refresh failures: status polling continues, the next cycle retries results, and final result loading at completed search remains strict. No Tourvisor request contract, pricing, Metrika, goals or lead contract changed.
- All 10 PR #217 gates passed. PR #217 merged as `5e62e993ffb4096e68811352119b4f66c620a3f8`; V2-only deploy `33235027491` passed validate → copy → verify → live search smoke.
- Standalone deploy `33235027509` initially hit an external SSH `connection reset by peer` during copy. The failed job was retried without code changes and then passed release validation, copy, public-page verification, unchanged lead-bridge verification and live search smoke.
- The same recovery audit found a second user-visible edge case: `Расширить даты ±2 дня` could move `dateFrom` into the past for departures today/tomorrow, so the next search immediately failed normal date validation.
- PR #219 clamps recovered `dateFrom` to local today, preserves the widened end date, displays the actual adjusted range and strengthens the five-width recovery guard with a near-term date case. All 12 PR checks passed.
- PR #219 merged as `ab0f517517adb17d7d21b64e39b499024fbc8a06`. V2 deploy `33235437042` passed validate/copy/verify/live search smoke; standalone deploy `33235437074` passed release validation/copy/public pages/unchanged lead bridge/live search smoke; production search-recovery audit `33235485531` passed.
- Selected-tour/flight-price source audit confirms tour-switch generation guards prevent stale flight responses, flight retry is available, selected flight data is taken at lead-submit time, and no confirmed stale-price/lead-context defect was found in that pass.
- Earlier production evidence remains valid: PR #215 fixed the 3.26:1 tablet/desktop primary CTA contrast regression; responsive content visual `33234379091` and full live user journey `33234379100` passed afterward.
- URL/restored-state, child composition and stale-results validation remain guarded; `Самая низкая цена` remains current-result-set decision support and does not alter sorting or actual tour pricing.

## Exact next work order

1. Continue the whole V2 recovery audit at search completion/result-fetch errors and `Показать ещё` continuation: distinguish status failure from result failure, preserve useful retry state, and fix only confirmed user-visible defects.
2. Re-audit results decision-support/state transitions on mobile/tablet/desktop, including progressive/stale results, sorting, comparison and lowest-price presentation.
3. Re-audit selected-tour transitions in the browser: room fallback, flight autoload/retry, selected-flight price synchronization and lead entry/recovery, without changing the external lead contract.
4. Re-run full live standalone journey and responsive/selected-tour visual guards after material V2 UX changes.
5. Promote additional country/content routes only when their page exists locally and route/search handoff is verified; otherwise preserve the valid legacy destination.
6. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
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
