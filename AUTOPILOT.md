# poisk-turov-test — Autopilot State

Updated: 2026-08-29 13:00 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. SEO/site foundation remains **8.8** and standalone remains deliberately `noindex,follow`; publication/indexing requires a separate deliberate decision.

## Latest material evidence

- #250 fixed a confirmed pending-flight context mismatch. When an unpriced selected flight omitted `fuelCharge`, the UI correctly fell back to the tour fuel but `v2:tour-price-updated` incorrectly emitted `fuelCharge: 0`. Pending and priced flight paths now share the same selected-fuel fallback semantics, while an explicit flight `fuelCharge: 0` remains explicit. The browser guard covers priced → pending-specific-fuel → pending-tour-fallback transitions at 375/768/1440, including price reset, `pricePending`, lead selection text and overflow.
- #250 merged as `42cd3b76ee4de9d0d379c79b41137b371fc95334` after all 12 PR checks completed without failure. Production is green: V2 deploy `33249002281` passed validate → copy → verify → live search smoke; standalone deploy `33249002273` passed release validation → public-page verification → unchanged lead bridge → live search smoke. Push live-tour/result-detail/standalone validation also passed.
- The newer feed work on main was preserved: `80b1969e89ef63f253f9dc3f7ca9d1308eb8655e` published the AnyTour catalog feed and `21e48625b279ac678dc77e0919c9c8f7839ee1ee` added its production deploy; feed deployment `33248750721` and Security were green before #250.
- #245 remains the priced-flight fuel protection: selecting a priced flight that omits `fuelCharge` cannot retain the previous flight's fuel; the tour-level fuel is used instead. #246 protects flight autoload/retry races at 375/768/1440.
- Earlier protections remain valid: #243 same-tour lead error/retry/success; #242 room error/retry/stale-response isolation; #241 selected-tour return/focus fallback; #237 successful-empty flight recovery; #230 stale lead-response UI race; #229 pending flight-price label; #227 stale selected-flight price reset; #226 comparison refresh coherence; #225 final-set-only decision badges; #217/#219/#221 progressive/final search recovery.
- No Yandex Metrika configuration/goals, Tourvisor request contract or existing lead-sending mechanism/external field mapping changed in #250.

## Exact next work order

1. Run the next periodic whole-V2 browser audit end-to-end: search form → waiting/progress → stale/progressive/final results → comparison/sort → selected tour → room fallback → flight autoload/error/retry → priced/unpriced transitions → price/fuel confidence → lead entry/error/retry/success, across mobile/tablet/desktop. Fix only confirmed defects.
2. Re-check return/reselection state after a complete real-result journey, including sort/comparison/scroll/focus preservation and a second selected-tour cycle.
3. Audit downstream consumers of `v2:tour-price-updated`/selected-flight context for any remaining stale price/fuel/pending status, without changing the external lead contract.
4. Continue standalone content UX stabilization and promote additional country/content routes only when the local page exists and its `/poisk-turov/` handoff is verified; otherwise preserve the valid legacy destination.
5. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior, feed deployment and the existing lead contract.
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
