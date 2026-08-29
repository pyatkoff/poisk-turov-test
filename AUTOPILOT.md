# poisk-turov-test — Autopilot State

Updated: 2026-08-29 11:17 +02:00

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

- PR #235 fixed selected-tour return/resume: normal and post-lead-success `Вернуться к предложениям` now close the checkout surface, preserve result/sort/comparison state and restore keyboard focus/scroll to the originating result, with results-region fallback when the source no longer exists. Its 375/1440 browser guard and all relevant visual/runtime gates passed; V2 deploy `33244554798` and standalone deploy `33244554775` were green through live search smoke.
- PR #237 fixed a confirmed flight empty-state dead end. A successful flights response with no variants now explains that flight data has not yet been received and offers `Проверить рейсы ещё раз` through the controller's existing retry path while keeping the lead form available. Real flight variants and explicit error handling remain untouched. Its dedicated 375/1440 browser regression plus selected-tour/full-V2/baseline/startup/standalone/security gates passed. It merged as `7af64c649d23010bd9cff98df86364d29a8f1203`.
- #237 production is green: V2 deploy `33244832722` passed validate → copy → verify → live search smoke; standalone deploy `33244832726` passed release validation → copy → public pages → unchanged lead bridge → live search smoke; post-deploy visual run `33244905020` also succeeded.
- After #237 merge, the push-only `Validate active V2 contract` exposed a pre-existing CI-contract mismatch from intentional Web Consultant release #234: the historical dependency closure rejected every literal `/max-search/`, while #234 intentionally loads the canonical `anytour.online/max-search/web-consultant/` scripts only on `anytoour.ru` / `www.anytoour.ru`.
- PR #238 preserves the approved consultant and keeps the generic cross-project dependency guard strict. The runtime URL is unchanged but the approved path is composed explicitly, and a dedicated Web Consultant CI contract now asserts the exact host gate, canonical base and exactly three allowed scripts while every other literal `/max-search/` dependency remains rejected. All #238 PR gates were green, including the new consultant contract, PHP 8.3, startup/branch bundles, standalone, security and visual suites. It merged as `71876fbae02dbd8acd991b2764fd923728b8070f`.
- The previously failing push-only active dependency-closure and deployment-isolation steps are green on #238 (`Validate active V2 contract` run `33244990290`). #238 is now production-green: V2 deploy `33244990295` passed validate → copy → verify → live search smoke; standalone deploy `33244990278` passed release validation → copy → public pages → unchanged lead bridge → live search smoke.
- Same-tour lead submit/retry code was re-audited: failed submission re-enables submit, retry rebuilds payload from the still-current tour/selected flight, and normal/duplicate success transition to the sent state. No confirmed defect was found, so the external lead contract was not touched.
- Earlier protections remain valid: #225 final-set-only decision badges; #226 comparison refresh coherence; #227 stale selected-flight price reset; #229 explicit pending flight-price label; #230 stale lead-response UI race guard; #217/#219/#221 progressive/final search recovery.
- No Yandex Metrika configuration/goals, Tourvisor request contract, pricing contract or existing lead-sending mechanism/external field mapping changed in #235/#237/#238.

## Exact next work order

1. Finish return/resume behavior with repeated selection after returning and explicit source-card disappearance/fallback on the real V2 shell across mobile/tablet/desktop.
2. Re-check room-details no-description/empty/error/retry and the remaining flight autoload/error/retry transitions now that the successful-empty case is covered; fix only confirmed issues.
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
