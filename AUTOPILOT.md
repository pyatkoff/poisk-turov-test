# poisk-turov-test — Autopilot State

Updated: 2026-08-29 19:07 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. SEO/site foundation remains **8.8** and standalone remains deliberately `noindex,follow`; publication/indexing requires a separate deliberate decision.

## Latest material evidence

- #269 is production-green. It safely improves the existing standalone social/app footer with inline brand/store icons while preserving all five verified destinations and the existing footer/search/lead contracts. The responsive footer guard now tests the DOM production actually renders (`.v2-site-community`) instead of the obsolete `.v2-site-footer` contract, and covers 375/768/1440 without horizontal overflow or undersized mobile targets.
- During #269 a separate baseline CI defect was confirmed and fixed: the active bundle manifest contains 23 CSS + 32 legitimate JS assets, while the startup-bundle guard still hard-coded 31 JS. The guard is now synchronized to 32; bundle syntax/cache/request-collapse coverage remains intact.
- #269 passed all 12 PR checks, including standalone, PHP 8.3, startup/branch bundles, dedicated footer responsive validation, Security and the complete V2 visual set. The superseded #268 footer hotfix was closed rather than merged because it removed part of the current footer contract.
- Production on commit `d03322511a18c7a6b0150ef2444222004e92cef7` is verified: V2 deploy `33264609173` passed active validation → V2-only copy → verify → live search smoke; standalone deploy `33264609196` passed release validation → public-page verification → verification that the lead bridge still reaches the unchanged production adapter → live search smoke. Live tour/flights `33264609187` and result→detail `33264609169` are also green. Post-deploy visual/content workflows are running on the same head and must be checked at the next resume point before another visual release if any failure appears.
- #266 remains the post-recovery comparison browser guard across 375/768/1440: sort rerenders preserve comparison membership by stable hotel id; a recovered/final result set removing one selected hotel closes the invalid dialog; later result retry does not resurrect stale selection; active sort persists and comparison can resume.
- #264 remains the completed-search recovery fix: `status=complete` without numeric progress=100 preserves the completed `searchId` and results-only retry instead of restarting search.
- #259 remains the production-safe real-data second-tour integration guard: live Tourvisor search resolves two distinct tour IDs and validates hotel/tour/price/flights independently without creating a lead.
- Existing downstream protections remain valid: #257 second-tour state isolation; #255 selected-tour return after rerender; #252 pending selected-flight confidence; #250 pending-flight fuel context; #246 flight autoload/retry races; #245 priced-flight fuel fallback; #243 same-tour lead recovery; #242 room recovery/stale response; #241 return/focus fallback; #237 empty-flight retry; #230 stale lead-response race; #227 stale flight-price reset; #226 comparison refresh; #225 final-set decision badges; #217/#219/#221 search recovery.
- Standalone country handoff remains correct for Turkey/Egypt/UAE/Thailand/Russia through `/poisk-turov/?country=...`; undeployed country routes intentionally remain on valid legacy destinations. `/hot/` continues to reuse the shared search flow.
- Legal/payment footer destinations remain intentionally legacy-only because source content is not reconciled. PR #254 remains deferred: despite useful content work it introduces a separate DB/platform architecture and therefore is not a safe autonomous merge into the current search baseline. #248/#249 remain stale/overlapping and deferred.

## Exact next work order

1. First check the post-deploy workflow-run fan-out for production head `d03322511a18c7a6b0150ef2444222004e92cef7` (standalone live visual/results/content/navigation/user-journey checks). If any confirmed regression exists, fix it before roadmap work.
2. Continue the whole-V2 mobile/tablet/desktop browser audit from post-recovery results into the second selected-tour cycle: selected tour → room → flights/autoload/error/retry → priced/unpriced → price/fuel confidence → lead error/retry/success. Fix only confirmed defects.
3. Re-run the complete real-result cycle after return to results, preserving sort/comparison/scroll/focus while selecting a different tour; extend guards only for an uncovered transition.
4. Continue safe non-legal standalone content UX stabilization and `/hot/`/country handoff auditing where pages reuse the existing search/API rather than duplicate logic.
5. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior, feed deployment, existing Tourvisor request contract, Metrika/goals and the existing lead contract.
6. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist. Do not run traffic diagnostics until owner explicitly launches traffic.

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
