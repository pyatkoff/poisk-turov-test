# poisk-turov-test — Autopilot State

Updated: 2026-08-29 15:03 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture remains explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. SEO/site foundation remains **8.8** and standalone remains deliberately `noindex,follow`; publication/indexing requires a separate deliberate decision.

## Latest material evidence

- #255 fixed a confirmed selected-tour return/reselection defect after results rerender. The result renderer recreates `.direct-tour` buttons during sort/refresh, so the previously saved DOM button could become detached even while the same tour remained in the refreshed results. Return now preserves the stable tour id, resolves the current usable button with the same `data-tid`, and only falls back to the results region when that tour is genuinely unavailable/hidden.
- #255 merged as `4503bb7db97727256f1ad6c69ed241187ce99f6d` after all 11 PR checks completed successfully. The dedicated browser guard covers initial return, hidden-source fallback, rerendered-source restoration and repeated selection/success-return at 375/768/1440 while preserving sort/comparison state.
- Production is green for #255: V2 deploy `33253836915` completed successfully and standalone deploy `33253836942` completed successfully. Active V2 contract `33253836814`, result-detail live `33253836918`, tour live `33253836917` and Security guard `33253836893` are green. Post-deploy visual workflows were launched from the successful deploy and had no failure at the point this state was advanced.
- #252 remains the pending selected-flight price-confidence protection: an already-selected flight whose recalculated price is pending explicitly says the selected-flight price is being clarified while the search price is temporarily shown.
- The downstream lead-selection summary was re-audited after the price-confidence fix. It observes the already-synchronized selected-flight DOM/context after synchronous flight listeners, so no confirmed stale external payload defect was found and no lead-contract change was made.
- #250 remains the pending-flight fuel context protection. When an unpriced selected flight omits `fuelCharge`, UI and `v2:tour-price-updated` share tour-fuel fallback semantics while explicit zero remains explicit.
- The feed publishing/deploy work remains preserved: `80b1969e89ef63f253f9dc3f7ca9d1308eb8655e` published the AnyTour catalog feed and `21e48625b279ac678dc77e0919c9c8f7839ee1ee` added its production deploy; feed deployment `33248750721` was green.
- Earlier protections remain valid: #246 flight autoload/retry races; #245 priced-flight fuel fallback; #243 same-tour lead error/retry/success; #242 room error/retry/stale-response isolation; #241 selected-tour return/focus fallback; #237 successful-empty flight recovery; #230 stale lead-response UI race; #229 pending flight-price label; #227 stale selected-flight price reset; #226 comparison refresh coherence; #225 final-set-only decision badges; #217/#219/#221 progressive/final search recovery.
- No Yandex Metrika configuration/goals, Tourvisor request contract, pricing external contract or existing lead-sending mechanism/external field mapping changed in #255.

## Exact next work order

1. Continue the periodic whole-V2 browser audit end-to-end: search form → waiting/progress → stale/progressive/final results → comparison/sort → selected tour → room fallback → flight autoload/error/retry → priced/unpriced transitions → price/fuel confidence → lead entry/error/retry/success, across mobile/tablet/desktop. Fix only confirmed defects.
2. Continue the second-tour cycle after return now that rerendered-source focus/scroll restoration is protected: verify a real-result sort/comparison change, return, choose a different tour, and re-check room/flight/price/lead state isolation.
3. Continue auditing remaining selected-tour/flight/lead UI consumers for stale pending/price/fuel/selection wording or state, while preserving the external lead contract; the lead-selection summary itself is currently cleared by audit.
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
