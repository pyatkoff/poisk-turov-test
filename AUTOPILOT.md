# poisk-turov-test — Autopilot State

Updated: 2026-08-29 17:10 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. SEO/site foundation remains **8.8** and standalone remains deliberately `noindex,follow`; publication/indexing requires a separate deliberate decision.

## Latest material evidence

- #264 fixed a confirmed final-results recovery edge. The lifecycle already considers `status=complete` a completed Tourvisor search even when numeric progress is absent or below 100, but the recovery UI previously keyed only off numeric 100 and could offer a full search restart after a final `search_results` fetch failure. The UI now remembers both completion signals and restores the existing results-only retry, preserving the completed `searchId` and avoiding an unnecessary second search.
- #264 passed the full 17-check PR bundle. Production is green: V2 deploy `33259402253` passed active-contract validation, copy, verify and live search smoke; standalone deploy `33259402295` passed standalone validation, public-page verification, verification that the lead bridge still reaches the unchanged production adapter, and live search smoke.
- #261 fixed the empty-results relaxation dependency race: arrival/region/subregion recovery clears dependent region/subregion/hotel constraints synchronously before catalog refresh and resubmit; arrival/region also clear destination-specific hotel services. V2 deploy `33259024852` and standalone deploy `33259024876` are green.
- #262 added a dedicated regression workflow for #261. The first workflow draft failed only because it assumed separate select listeners while catalogs use the shared `handleChange` dispatcher; the guard was corrected to the real dispatcher and run `33259182392` plus Security run `33259182439` are green. Runtime code was not changed by #262.
- #259 remains the production-safe real-data second-tour integration guard. It starts a live Tourvisor search on `anytoour.ru`, resolves two distinct real tour IDs, then independently validates hotel details, tour payload/price and flights for both without creating a lead. PR run `33256610100` and post-merge live run `33256645565` are green.
- #257 remains the focused browser state-isolation protection at 375/768/1440: tour-1 priced-flight price/fuel/lead-summary/room state resets before tour 2; pending-price fallback and final return target stay tied to tour 2.
- Standalone country handoff remains correct for Turkey/Egypt/UAE/Thailand/Russia through `/poisk-turov/?country=...`; undeployed country routes intentionally remain on valid legacy destinations. `/hot/` continues to reuse the shared `/poisk-turov/` flow with a near-term date handoff; no confirmed runtime defect was found there.
- Legal/payment footer destinations remain intentionally legacy-only because current source content is not reconciled. Older open PRs #248/#249/#254 remain deferred because they are stale/overlapping and #254 additionally introduces a separate DB/platform architecture.
- Existing downstream protections remain valid: #255 selected-tour return after rerender; #252 pending selected-flight confidence; #250 pending-flight fuel context; #246 flight autoload/retry races; #245 priced-flight fuel fallback; #243 same-tour lead recovery; #242 room recovery/stale response; #241 return/focus fallback; #237 empty-flight retry; #230 stale lead-response race; #227 stale flight-price reset; #226 comparison refresh; #225 final-set decision badges; #217/#219/#221 search recovery.

## Exact next work order

1. Continue the whole-V2 mobile/tablet/desktop browser audit from progressive/final results after the #261/#264 recovery fixes: filters/sort/comparison → selected tour → room → flights → price/fuel → lead. Fix only confirmed defects.
2. Specifically re-check state transitions immediately after empty-search broadening and results-only final-fetch retry, including sort/comparison and a second selected-tour cycle, to ensure no stale catalog/result state survives recovery.
3. Continue standalone content UX stabilization. Prioritize missing non-legal content/live-value gaps that can be verified safely; do not copy stale prices, legal text or payment instructions.
4. Continue `/hot/` and country/content-page handoff audits only where they reuse the existing search/API rather than duplicating search logic.
5. Continue auditing selected-tour/flight/lead consumers for stale wording/state while preserving the external lead contract.
6. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior, feed deployment and the existing lead contract.
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
