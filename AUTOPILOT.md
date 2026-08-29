# poisk-turov-test — Autopilot State

Updated: 2026-08-29 08:05 +02:00

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

- Whole-flow recovery audit found and fixed three real reliability gaps in succession: progressive `search_results` refresh failure could abort healthy polling (#217), near-term `Расширить даты ±2 дня` could move departure into the past (#219), and final result-fetch failure after a completed search could force an unnecessary full restart (#221).
- PR #221 makes completed-search recovery results-only: when status has already reached completion but final `search_results` fails, the UI offers `Загрузить результаты ещё раз` and reuses the existing `searchId` instead of sending another `search_start`.
- #221 also fixes `Показать ещё`: once `search_continue` has completed, a transient final result-fetch failure preserves already-rendered hotels and the retry fetches only `search_results`; it does not send a second `search_continue` request.
- All PR #221 checks passed, including the dedicated continue-search recovery contract, startup/branch bundle checks, security, selected-tour visual, main V2 visual and search-recovery visual. PR #221 merged as `25e628777e2157d9859b01fdb6ade012a92e2049`.
- V2-only deploy `33237509401` passed validate → copy → verify → live search smoke. Standalone deploy `33237509375` passed release validation/copy/public-page verification/unchanged lead-bridge verification/live search smoke.
- No Tourvisor request contract, pricing contract, Metrika/goals or external lead contract changed in #221.
- Earlier recovery evidence remains valid: #217 keeps polling alive across intermediate result-refresh failures; #219 clamps empty-search date widening to today and passed five-width production recovery audit `33235485531`.
- Selected-tour/flight-price source audit confirms tour-switch generation guards prevent stale flight responses, flight retry is available, selected flight data is taken at lead-submit time, and no confirmed stale-price/lead-context defect was found in that pass.
- Earlier production evidence remains valid: #215 fixed the 3.26:1 tablet/desktop primary CTA contrast regression; responsive content visual `33234379091` and full live user journey `33234379100` passed afterward.
- URL/restored-state, child composition and stale-results validation remain guarded; `Самая низкая цена` remains current-result-set decision support and does not alter sorting or actual tour pricing.

## Exact next work order

1. Re-audit results decision-support/state transitions on mobile/tablet/desktop, including progressive/stale results, sorting, comparison, lowest-price presentation and recovery after result refreshes.
2. Re-audit selected-tour transitions in the browser: room fallback, flight autoload/retry, selected-flight price synchronization and lead entry/recovery, without changing the external lead contract.
3. Re-run full live standalone journey and responsive/selected-tour visual guards after material V2 UX changes.
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
