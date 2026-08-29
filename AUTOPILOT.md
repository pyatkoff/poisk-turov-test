# poisk-turov-test — Autopilot State

Updated: 2026-08-29 09:16 +02:00

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

- PR #225 fixes definitive result benchmarking during progressive search. `Самая низкая цена` and `Лучший рейтинг` are now hidden while the main or continued Tourvisor search is still active and are recomputed only after the current result set completes. The dedicated mobile/tablet/desktop finality guard passed.
- PR #226 fixes comparison state after a result refresh: if refreshed results remove enough selected hotels that fewer than two remain, an already-open comparison dialog closes instead of presenting an invalid one-hotel comparison; the one-hotel tray stays available so another hotel can be selected. Standalone deploy `33240182570`, live journey `33240245892` and migrated-content verification `33240245930` passed.
- PR #227 fixes a confirmed price-consistency defect in selected-tour flight choice. If a priced flight was selected and the user then switched to a flight variant whose price is pending, the UI previously retained the previous flight's amount. It now resets to the base tour/search price, refreshes selected-flight fuel when present and clearly says the selected flight price will be confirmed. The selected flight itself and the existing lead payload/transport contract remain unchanged.
- #227 passed the dedicated unpriced-flight browser regression on mobile/desktop, selected-tour visual, full V2 visual, baseline, startup/branch bundles, PHP 8.3, standalone validation and security gates. It merged as `2c537ea1bacf90a14d793e77e9eb65376544c0ed`.
- V2-only deploy `33240311682` passed validate → copy → verify → live search smoke. Standalone deploy `33240311680` passed release validation → copy → public-page verification → unchanged lead-bridge verification → live search smoke.
- Earlier recovery protections remain valid: #217 keeps status polling alive across transient progressive result-fetch failures; #219 clamps near-term date widening to today; #221 retries completed main/continue result fetches against the existing searchId instead of restarting Tourvisor work.
- Result sorting/pricing contracts were not changed. The lowest-price badge remains decision support only and does not alter the actual tour price or sorting.
- No Yandex Metrika configuration/goals, Tourvisor request contract or existing lead-sending mechanism/external field mapping changed in #225–#227.

## Exact next work order

1. Continue browser-level selected-tour recovery audit on mobile/tablet/desktop: room fallback → flight autoload/error/retry → priced/unpriced flight transitions → price/fuel confidence → lead entry/error/retry/success, without changing the external lead contract.
2. Audit selected-tour → back-to-results/resume transitions after comparison/search refreshes, including whether result/sort/comparison state remains coherent.
3. Re-run the full standalone live journey and responsive/selected-tour guards after the next material downstream UX/data fix.
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
