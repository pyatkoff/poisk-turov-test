# poisk-turov-test — Autopilot State

Updated: 2026-08-29 16:06 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. SEO/site foundation remains **8.8** and standalone remains deliberately `noindex,follow`; publication/indexing requires a separate deliberate decision.

## Latest material evidence

- #259 added a production-safe real-data second-tour integration guard. It starts a live Tourvisor search on `anytoour.ru`, resolves two distinct real tour IDs (preferring different hotels), then independently validates hotel details, tour payload/price and flights for both without creating a lead. PR run `33256610100`, post-merge live run `33256645565`, PR Security and post-merge Security `33256645580` are green.
- #257 remains the focused browser state-isolation protection at 375/768/1440: tour-1 priced-flight price/fuel/lead-summary/room state resets before tour 2; pending-price fallback and final return target stay tied to tour 2. Dedicated run `33256379305` and post-merge Security `33256406686` are green.
- Together #257 and #259 clear the currently identified second-selection stale-state/data risk at both browser-state and real Tourvisor payload layers. Neither change modifies production runtime, Metrika, Tourvisor request semantics or the lead contract, so no application deploy was required.
- Standalone country handoff was re-audited. Turkey/Egypt/UAE/Thailand/Russia remain guarded against the live Tourvisor country catalog and hand off through `/poisk-turov/?country=...`; undeployed country routes intentionally remain on their valid legacy destination.
- Legal/payment footer destinations remain intentionally legacy-only. `SITE_MIGRATION_MAP.md` requires verified legal/payment content before migration, and the currently public legacy privacy source still contains old/conflicting company-location details, so silently recreating or repointing those pages would be unsafe.
- Older open Site/feed PRs #248/#249/#254 are based on stale main snapshots and overlap already shipped feed/content work; #254 additionally introduces a DB/platform architecture. They are deferred from automatic merge/rebase rather than being allowed to destabilize the current search baseline.
- #255 remains the production runtime baseline for selected-tour return after result rerender. V2 deploy `33253836915` and standalone deploy `33253836942` are green; active V2 contract `33253836814`, result-detail live `33253836918` and tour live `33253836917` are green.
- Existing downstream protections remain valid: #252 pending selected-flight confidence; #250 pending-flight fuel context; #246 flight autoload/retry races; #245 priced-flight fuel fallback; #243 same-tour lead recovery; #242 room recovery/stale response; #241 return/focus fallback; #237 empty-flight retry; #230 stale lead-response race; #229 pending price label; #227 stale flight-price reset; #226 comparison refresh; #225 final-set decision badges; #217/#219/#221 search recovery.

## Exact next work order

1. Continue the whole-V2 mobile/tablet/desktop browser audit from the search side again: search form → waiting/progress → stale/progressive/final results → filters/sort/comparison → selected tour → room → flights → price/fuel → lead. Fix only confirmed defects.
2. Continue standalone content UX stabilization. Prioritize missing non-legal content/live-value gaps that can be verified safely; do not copy stale prices, legal text or payment instructions.
3. Audit `/hot/` and country/content pages for useful live-data handoff improvements that reuse the existing search/API rather than duplicating search logic.
4. Continue auditing selected-tour/flight/lead consumers for stale wording/state while preserving the external lead contract.
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
