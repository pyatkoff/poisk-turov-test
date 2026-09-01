# poisk-turov-test — Product roadmap / compatibility state

Updated: 2026-09-01

Operational companion to `AGENTS.md`. Execution state is no longer stored here or in `AUTOPILOT_STATE.json`: the authoritative queue is `autopilot-v2/tasks/*.json`, terminal results are `autopilot-v2/outcomes/*.json`, and current status is derived by `python3 autopilot-v2/controller.py status`. `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work. This document keeps roadmap/history context only.

## Current phase — CORE PRODUCT 9/10, APPROVED DS2 REDESIGN IMPLEMENTATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

The owner has explicitly approved the full new AnyTour design direction, including Search 2.0. New visual concepts still require approval before implementation; already-approved lanes may be implemented without asking again, but user-facing MEDIUM changes still require their task-specific browser/visual evidence before merge.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 for the currently shipped baseline. The separate site-wide public/editorial visual score remains 7.2/10 until approved redesign slices are merged, deployed and production-verified.

Standalone architecture remains explicit: `https://anytoour.ru/` is the homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active redesign lanes

- `REDESIGN_SEARCH_RESULTS` — approved implementation is present on `feature/search-core`; isolated Search 2.0 preview deploy is green, targeted QA still required before release.
- `REDESIGN_HEADER_SHELL` — PR #703 code-green, held for targeted visual QA.
- `REDESIGN_LOADING_RECOVERY` — PR #704 code-green, held for targeted visual QA.
- `REDESIGN_HOTEL_TOUR_SELECTION` — PR #706 code-green, held for targeted visual QA.
- `REDESIGN_SELECTED_TOUR` — PR #713 implemented on canonical DS2 tokens; Fast CI and Security guard green; room/flight/price browser QA plus 375/1440 visual evidence still required before merge.
- `REDESIGN_LEAD_HANDOFF` — queued after selected-tour acceptance.
- `REDESIGN_FOOTER_COMMUNITY` — queued.

## Latest material evidence

- PR #713 replaces the old selected-tour local visual vocabulary with canonical DS2 tokens in `v2/selected-tour-ux.css`, strengthens price/facts/flight/lead hierarchy and preserves existing markup/state, Tourvisor identifiers, pricing semantics and external lead payload contracts. Fast CI and Security guard passed at `4246ba42a927602227a4eac68a3cba1d04a4cf1c`; merge is intentionally blocked on the contract-required browser and 375/1440 visual evidence.
- The Search 2.0 isolated preview for `feature/search-core` passed release validation, public HTTP 200, Tourvisor-direct health, bundle CSS verification and `noindex,nofollow` preview isolation on 2026-09-01.
- PR #708 records explicit owner approval of the full new design and removes the obsolete Search 2.0 approval hold.
- PR #709 repairs the selected-tour redesign ownership contract to the active runtime files.
- PRs #703/#704/#706 remain unmerged because their targeted visual QA has not yet been collected; CI-green alone is not treated as DONE.
- Existing production reliability fixes remain valid: progressive result-refresh recovery (#217), near-term date widening clamp (#219), completed-search/final-result recovery (#221), selected-tour stale reset, room normalization and flight/fuel price synchronization.
- No Tourvisor request contract, pricing contract, Metrika/goals, external lead contract or AnyTour logo changed in the redesign work above.

## Exact next work order

The controller task contracts override this historical ordering whenever they differ. Current order:

1. Obtain task-required targeted browser/visual evidence for #703, #704, #706 and #713; merge only lanes whose evidence is green.
2. For #713 specifically, verify selected room/meal consistency, flight switching, baggage/fuel presentation and final price refresh at 375 and 1440 before merge.
3. Continue to `REDESIGN_LEAD_HANDOFF` only after selected-tour acceptance, preserving the external lead contract.
4. Continue `REDESIGN_FOOTER_COMMUNITY` and remaining approved DS2 shell convergence without inventing unsanctioned new mockups.
5. Re-run broader 375/430/768/1024/1440 public-site assessment after the approved redesign slices reach the integrated preview/production branch.
6. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
7. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist.
8. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

## Guardrails

- AnyTour Design System 2.0 is the only canonical design system.
- Work only inside `pyatkoff/poisk-turov-test`; production deploy scope is the allowed V2/standalone scope only.
- New DS2 mockups require explicit owner approval before implementation. Existing recorded approval remains valid for its exact lane.
- Do not redesign/replace the existing AnyTour logo.
- Do not modify neighboring projects, global site assets or server config outside allowed scope.
- Do not change Yandex Metrika configuration/goals.
- Do not change Tourvisor request/identifier contracts merely for redesign work.
- Do not change the existing lead-sending mechanism/external contract.
- Do not migrate unresolved legal/payment content.
- PR #254 remains deferred unless separate DB/platform architecture is freshly proven safe.
- `technical_refactor` remains deferred until new explicit owner direction.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
- If one item is blocked, record/defer it and continue independent approved work.

## Explicitly inactive until owner launches traffic

Live conversion optimization/C7; live product optimization/B8; operational traffic feedback/A8; browser-session funnel analysis; waiting for `search → tour → lead` samples; traffic-based A/B-like conclusions.

Absence of traffic is expected and is never a blocker in the current phase.
