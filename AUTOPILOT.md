# poisk-turov-test — Autopilot State

Updated: 2026-08-29 20:14 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and `noindex,follow`; it now canonically consolidates to the standalone search. SEO/site foundation remains **8.8** and public indexing still requires a separate deliberate decision.

## Latest material evidence

- A full post-deploy audit of the previous #269 production head found one real missed failure: `Validate V2 SEO foundation` run `33264609190`. Runtime/search deploys were green, but the compatibility route still self-canonicalized while the old live guard expected no canonical. This was fixed before further product rollout rather than ignored as CI noise.
- #272 is production-green. The legacy anytour.online V2 remains `noindex,follow` but now canonically points to `https://anytoour.ru/poisk-turov/`. The SEO workflow now validates candidate `seo-config.php` locally on PR and reserves live compatibility/search-state checks for post-merge runs, avoiding pre-deploy false failures. All 12 PR checks were green. Production commit `04a6348d16f2564f322f98b5175f7d295604db2d`: V2 deploy `33267217252` and standalone deploy `33267217286` green; live SEO run `33267217242` attempt 2 green after deploy.
- #273 is production-green. Flight comparison no longer claims `Самая низкая цена` or `+… к минимальной` while any displayed flight variant still has unresolved pricing (`Цена уточняется`). Route facts such as direct/connection remain independent. Browser coverage verifies resolved → pending → resolved transitions at 375/768/1440 and preserves price/fuel/lead synchronization. All 13 PR checks were green. Production commit `c8f4410435e914bbc84afe913be35d57e9ae1cf3`: V2 deploy `33267473947` passed validate → V2-only copy → verify → live search smoke; standalone deploy `33267473950` passed successfully; live tour `33267473896` and result→detail `33267473953` are green. Old #271 was closed as superseded instead of forcing a stale baseline merge.
- #269 remains the footer/site baseline beneath these fixes: safe branded social/app footer, responsive 375/768/1440 guard aligned to `.v2-site-community`, and startup-bundle guard synchronized to the active 23 CSS + 32 JS manifest.
- #266 remains the post-recovery comparison browser guard across 375/768/1440: sort rerenders preserve comparison membership by stable hotel id; a recovered/final result set removing one selected hotel closes the invalid dialog; later result retry does not resurrect stale selection; active sort persists and comparison can resume.
- #264 remains the completed-search recovery fix: `status=complete` without numeric progress=100 preserves the completed `searchId` and results-only retry instead of restarting search.
- #259 remains the production-safe real-data second-tour integration guard: live Tourvisor search resolves two distinct tour IDs and validates hotel/tour/price/flights independently without creating a lead.
- Existing downstream protections remain valid: #257 second-tour state isolation; #255 selected-tour return after rerender; #252 pending selected-flight confidence; #250 pending-flight fuel context; #246 flight autoload/retry races; #245 priced-flight fuel fallback; #243 same-tour lead recovery; #242 room recovery/stale response; #241 return/focus fallback; #237 empty-flight retry; #230 stale lead-response race; #227 stale flight-price reset; #226 comparison refresh; #225 final-set decision badges; #217/#219/#221 search recovery.
- Standalone country handoff remains correct for Turkey/Egypt/UAE/Thailand/Russia through `/poisk-turov/?country=...`; undeployed country routes intentionally remain on valid legacy destinations. `/hot/` continues to reuse the shared search flow.
- Legal/payment footer destinations remain intentionally legacy-only because source content is not reconciled. PR #254 remains deferred: despite useful content work it introduces a separate DB/platform architecture and therefore is not a safe autonomous merge into the current search baseline. #248/#249 remain stale/overlapping and deferred.

## Exact next work order

1. Finish checking the remaining workflow-run fan-out for production head `c8f4410435e914bbc84afe913be35d57e9ae1cf3`; fix any confirmed post-deploy regression before roadmap work. Initial V2/standalone deploy, live tour and live result-detail evidence are green.
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
