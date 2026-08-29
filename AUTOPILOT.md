# poisk-turov-test — Autopilot State

Updated: 2026-08-29 12:07 +02:00

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

- PR #241 fixed a confirmed return/resume edge case: when the originating result button remained in the DOM but its card became hidden after filtering/refresh, `Вернуться к предложениям` could focus an invisible control. The return helper now rejects hidden/unrendered source controls and falls back to the results region. Its browser regression covers initial return, hidden-source fallback, repeated selection and post-success return on 375/768/1440 while preserving sort/comparison state. #241 merged as `1b966e41fd2dc83ff90b100966779683dcaf7207` and is production-green: V2 deploy `33246743154` passed validate → copy → verify → live search smoke; standalone deploy `33246743138` passed release validation → copy → public pages → unchanged lead bridge → live search smoke.
- PR #242 added a dedicated room-details recovery browser guard after re-auditing the runtime. Error → retry → empty/no-description fallback, stale async room responses after changing tours, and successful recovery all pass on 375/768/1440. No runtime defect was confirmed, so the room API/runtime contract was not changed. Guard run `33246782004` is green; #242 merged as `89f2ccb874b25695298b6450869122b32b404c6b`.
- The latest whole-site live evidence after #242 is green: full standalone user journey `33246833160`; migrated-content guard `33246833155`; navigation visual `33246833229`; live results visual `33246833181`; standalone content visual `33246833176`; and five-width homepage/search visual `33246833166`.
- PR #243 added the missing same-tour lead recovery browser-state regression. Existing runtime behavior was confirmed correct: entered name/phone/comment survive an error; retry state is explicit; both normal success and duplicate-success finish in the final success panel with the lead number. The guard passes on 375/768/1440 (`33246841715`), Security guard is green, and #243 merged as `07049f366bfc2dcf54d9cf2f9bd4b3c6e3d745d7`. It does not submit a real lead and does not alter the lead endpoint/payload.
- Earlier protections remain valid: #237 successful-empty flight recovery; #238 narrow approved Web Consultant dependency contract; #230 stale lead-response UI race guard; #229 pending flight-price label; #227 stale selected-flight price reset; #226 comparison refresh coherence; #225 final-set-only decision badges; #217/#219/#221 progressive/final search recovery.
- No Yandex Metrika configuration/goals, Tourvisor request contract, pricing contract or existing lead-sending mechanism/external field mapping changed in #241/#242/#243.

## Exact next work order

1. Complete the remaining selected-tour downstream browser audit around flight autoload/error/retry: initial autoload, explicit error → retry, switching tours while a flight request is pending, and correct selected-flight/price/fuel context after recovery. Fix only confirmed runtime issues.
2. Re-check priced ↔ unpriced flight transitions together with fuel-charge confidence and lead selection summary so no stale amount/status can survive a flight or tour change.
3. Run another periodic whole-V2 audit end-to-end: search form → waiting/progress → stale/progressive/final results → comparison/sort → selected tour → room fallback → flights/price → lead entry/error/retry/success, across mobile/tablet/desktop.
4. Re-run the full standalone live journey and responsive/selected-tour visual guards after the next material runtime UX/data fix.
5. Continue standalone content UX stabilization and promote additional country/content routes only when the local page exists and its search handoff is verified; otherwise preserve the valid legacy destination.
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
