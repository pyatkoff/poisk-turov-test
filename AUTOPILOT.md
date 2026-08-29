# poisk-turov-test — Autopilot State

Updated: 2026-08-29 12:15 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. SEO/site foundation remains **8.8** and standalone remains deliberately `noindex,follow`; publication/indexing requires a separate deliberate decision.

## Latest material evidence

- #241 fixed selected-tour return to a result whose source card became hidden/unrendered; fallback now targets the results region. Return/reselection is protected at 375/768/1440 and production green.
- #242 added room-details error → retry → empty/success and stale-response isolation coverage at 375/768/1440; no room runtime defect was found.
- #243 added same-tour lead error → retry → normal/duplicate-success browser coverage at 375/768/1440; entered data survives error and the external lead contract is unchanged.
- #245 fixed a confirmed flight data bug: after selecting a flight with a flight-specific fuel charge, selecting another priced flight that omitted `fuelCharge` could leave the previous flight's fuel amount visible. The UI and `v2:tour-price-updated` context now fall back to the tour-level fuel charge when the selected flight omits it, while explicit `fuelCharge: 0` remains explicit `—`. The dedicated 375/768/1440 regression, existing flight-tradeoff guard, selected-tour visual, V2 visual, bundle/startup, standalone and security checks are green.
- #245 production is green at commit `ce895bf1d2648677bd6ca3f905e98f0ca48c204e`: V2 deploy `33247195974` passed validate → copy → verify → live search smoke; standalone deploy `33247195969` passed release validation → public-page verification → unchanged lead bridge → live search smoke. Post-deploy full tourist journey `33247259847`, live result-card visual `33247259844`, and five-width root/search visual `33247259957` are green.
- #246 added browser evidence for remaining flight recovery races. At 375/768/1440 a late flight response for a previously selected tour cannot mutate the new selected tour; the new tour autoloads flights; explicit flight error → retry → success works. Guard `33247263720` and Security `33247263669` are green; #246 merged as `b3af7b4695f799066e47ec9fa49fc994d877fb05` with no runtime change.
- Earlier protections remain valid: #237 successful-empty flight recovery; #238 narrow approved Web Consultant dependency contract; #230 stale lead-response UI race guard; #229 pending flight-price label; #227 stale selected-flight price reset; #226 comparison refresh coherence; #225 final-set-only decision badges; #217/#219/#221 progressive/final search recovery.
- No Yandex Metrika configuration/goals, Tourvisor request contract or existing lead-sending mechanism/external field mapping changed in #241–#246.

## Exact next work order

1. Re-audit the complete priced ↔ unpriced selected-flight transition together with price-confidence text, fuel-charge fact and lead selection summary/payload display so no stale amount/status survives any flight or tour change. Fix only confirmed defects.
2. Run the next periodic whole-V2 browser audit end-to-end: search form → waiting/progress → stale/progressive/final results → comparison/sort → selected tour → room fallback → flight autoload/error/retry → price/fuel → lead entry/error/retry/success, across mobile/tablet/desktop.
3. Re-check return/reselection state after a complete real-result journey, including sort/comparison/scroll/focus preservation.
4. Continue standalone content UX stabilization and promote additional country/content routes only when the local page exists and its `/poisk-turov/` handoff is verified; otherwise preserve the valid legacy destination.
5. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and the existing lead contract.
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
