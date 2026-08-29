# poisk-turov-test — Autopilot State

Updated: 2026-08-29 17:05 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. SEO/site foundation remains **8.8** and standalone remains deliberately `noindex,follow`; publication/indexing requires a separate deliberate decision.

## Latest material evidence

- #261 fixed a confirmed empty-results recovery race. Relaxing arrival/region/subregion now clears dependent region/subregion/hotel constraints synchronously before the existing catalog refresh and lifecycle resubmit, so a visibly broadened recovery search cannot silently retain the previous hotel/subregion constraint. Arrival/region relaxation also clears selected hotel-service constraints that belong to the old destination. No Tourvisor request semantics, Metrika, pricing or lead contract changed.
- #261 is production green. V2 deploy `33259024852` completed successfully after active-contract validation, copy, verification and live search smoke. Standalone deploy `33259024876` completed successfully after standalone validation, public-page verification, verification that the lead bridge still reaches the unchanged production adapter, and live search smoke.
- #262 added a dedicated regression workflow for this cascade. The first workflow draft failed only because it assumed separate select listeners while catalogs use the shared `handleChange` dispatcher; the guard was corrected to the real dispatcher and run `33259182392` plus Security run `33259182439` are green. Runtime code was not changed by #262.
- #259 remains the production-safe real-data second-tour integration guard. It starts a live Tourvisor search on `anytoour.ru`, resolves two distinct real tour IDs (preferring different hotels), then independently validates hotel details, tour payload/price and flights for both without creating a lead. PR run `33256610100`, post-merge live run `33256645565`, PR Security and post-merge Security `33256645580` are green.
- #257 remains the focused browser state-isolation protection at 375/768/1440: tour-1 priced-flight price/fuel/lead-summary/room state resets before tour 2; pending-price fallback and final return target stay tied to tour 2.
- Standalone country handoff was re-audited. Turkey/Egypt/UAE/Thailand/Russia remain guarded against the live Tourvisor country catalog and hand off through `/poisk-turov/?country=...`; undeployed country routes intentionally remain on their valid legacy destination.
- `/hot/` still uses the shared `/poisk-turov/` flow and generates a near-term date handoff rather than duplicating search logic; no confirmed runtime defect was found there in this pass.
- Legal/payment footer destinations remain intentionally legacy-only. `SITE_MIGRATION_MAP.md` requires verified legal/payment content before migration, and the currently public legacy privacy source still contains old/conflicting company-location details, so silently recreating or repointing those pages would be unsafe.
- Older open Site/feed PRs #248/#249/#254 are based on stale main snapshots and overlap already shipped feed/content work; #254 additionally introduces a DB/platform architecture. They remain deferred from automatic merge/rebase.
- Existing downstream protections remain valid: #255 selected-tour return after rerender; #252 pending selected-flight confidence; #250 pending-flight fuel context; #246 flight autoload/retry races; #245 priced-flight fuel fallback; #243 same-tour lead recovery; #242 room recovery/stale response; #241 return/focus fallback; #237 empty-flight retry; #230 stale lead-response race; #229 pending price label; #227 stale flight-price reset; #226 comparison refresh; #225 final-set decision badges; #217/#219/#221 search recovery.

## Exact next work order

1. Continue the whole-V2 mobile/tablet/desktop browser audit from the next search-side states after the #261 recovery fix: waiting/progress → stale/progressive/final results → filters/sort/comparison → selected tour → room → flights → price/fuel → lead. Fix only confirmed defects.
2. Specifically re-check results/filter state transitions after broadening an empty search, including subsequent sort/comparison and a second selected-tour cycle, to ensure no stale catalog or result state survives the recovery.
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
