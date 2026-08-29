# poisk-turov-test — Autopilot State

Updated: 2026-08-29 11:06 +02:00

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

- PR #235 fixes a confirmed selected-tour return-state defect without changing search, pricing, analytics or lead transport. Both the normal `Вернуться к предложениям` action and the post-success return now close the selected-tour/checkout surface, restore keyboard focus and scroll to the originating result card when it still exists, and fall back to the results region when it does not.
- The return guard deliberately preserves the existing result DOM, selected sort order and comparison state. A dedicated browser regression verifies those properties at 375 and 1440 px for both normal return and lead-success return.
- #235 passed the dedicated return-state browser guard plus V2 startup/branch bundles, PHP 8.3, comparison, lead-race, pending-flight-price, standalone, selected-tour visual, full V2 visual, trust/meal visual and deterministic visual-baseline gates. It merged as `30eefbda00c307699691915e72a06cea3c4c75d6`.
- V2-only deploy `33244554798` completed successfully through validate → copy → verify → live search smoke. Standalone deploy `33244554775` also completed successfully through release validation, copy, public-page verification, unchanged lead-bridge verification and live search smoke.
- Same-tour lead submit/retry code was re-audited after #235: failed submission re-enables the submit action, retry rebuilds payload from the still-current tour/selected flight, and normal/duplicate success both transition to the sent state. No confirmed defect was found, so the external lead contract was not touched.
- The freshly merged Web Consultant integration from #234 was included in the current-main inspection. Its earlier post-deploy journey/content checks were green; no confirmed selected-tour/return regression attributable to the widget was found in this pass.
- Earlier protections remain valid: #225 keeps lowest-price/best-rating claims final-set-only; #226 keeps comparison coherent after result refresh; #227 resets stale selected-flight price when the newly selected flight price is pending; #229 labels pending flight prices explicitly; #230 blocks stale lead-response UI mutations; #217/#219/#221 protect progressive/final search recovery.
- No Yandex Metrika configuration/goals, Tourvisor request contract, pricing contract or existing lead-sending mechanism/external field mapping changed in #235.

## Exact next work order

1. Finish the return/resume audit with repeated selection after returning, including source-card disappearance/fallback and mobile/tablet/desktop browser behavior on the real V2 shell.
2. Re-check room-details fallback/no-description/error/retry and flight empty/error/retry/autoload states for misleading confidence or dead-end UX; fix only confirmed issues.
3. Complete same-tour lead recovery as a browser-state audit: failed submit → retry → success/duplicate success, ensuring form data and selected flight/price context remain coherent without touching the external lead contract.
4. Re-run the full standalone live journey and responsive/selected-tour guards after the next material downstream UX/data fix.
5. Continue periodic whole-V2 audit: search form → waiting/progress → stale/progressive/final results → comparison → selected tour → flights/price → lead entry/recovery.
6. Promote additional country/content routes only when their page exists locally and route/search handoff is verified; otherwise preserve the valid legacy destination.
7. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
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
