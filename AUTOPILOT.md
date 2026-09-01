# poisk-turov-test — Product roadmap / compatibility state

Updated: 2026-09-01

Operational companion to `AGENTS.md`. Execution state is no longer stored here or in `AUTOPILOT_STATE.json`: the authoritative queue is `autopilot-v2/tasks/*.json`, terminal results are `autopilot-v2/outcomes/*.json`, and current status is derived by `python3 autopilot-v2/controller.py status`. `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work. This document keeps roadmap/history context only.

## Current redesign overlay — approved AnyTour Design System 2.0

The full agreed AnyTour redesign, including Search 2.0, is approved and is being implemented in isolated lanes. AnyTour Design System 2.0 is the only canonical design system. User-facing MEDIUM changes remain gated by task-specific browser/visual evidence before merge.

- Search 2.0 workspace is present on `feature/search-core`; isolated preview HTTP, Tourvisor-direct health, CSS and noindex verification are green.
- PR #703 header, #704 loading/recovery and #706 hotel/tour selection are code-green and awaiting targeted visual QA.
- PR #712 implements the approved staged `Ваш тур → Выбор рейса → Заявка менеджеру` hierarchy. Review found a private injected palette; commit `183010fce6250bbe27b887323190b4fd075bf383` converged the approved layer onto canonical DS2 tokens. Fast CI and Security guard are green; room/flight/price browser QA and 375/1440 visual QA remain required.
- PR #715 implements only verified MAX, Telegram, VK, App Store, Google Play, existing legal/payment links and MasterCard/Visa/Мир. Its separate hardcoded palette was likewise replaced with canonical DS2 tokens at `7f25aec09247fba85e8dbb8d65ecf8760c0668f2`; Fast CI and Security guard are green; 375/1440 visual QA remains required.
- PR #716 repaired the lead-handoff task ownership to active checkout/lead UI files while keeping backend lead transport outside task ownership.
- PR #713 is closed as superseded by #712. PR #711 is closed as obsolete because the full agreed redesign direction is already approved.

The separate public-site visual score remains **7.2/10** until redesigned lanes are integrated, deployed and production-verified. Do not raise it for open PRs.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture is explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. Country/content routes are being migrated incrementally.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active roadmap

- ROOT STABILIZATION — ACTIVE HIGHEST PRIORITY while the new standalone shell/routes are being migrated.
- Approved DS2 redesign lanes — ACTIVE with task-specific visual gates.
- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; publication/indexing policy remains deliberately deferred.
- BR5 Social + app footer — existing shipped baseline maintained while approved factual footer redesign #715 awaits QA.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #712 and #715 were reviewed for DS2 ownership consistency; both had introduced hardcoded private visual palettes in otherwise approved layouts. Both now consume canonical DS2 tokens and their post-fix Fast CI/Security checks are green.
- Whole-flow recovery fixes remain valid: progressive `search_results` refresh (#217), near-term date widening (#219), and completed-search/final result-fetch recovery (#221).
- V2-only deploy `33237509401` and standalone deploy `33237509375` passed their production verification/live search smoke for the shipped baseline.
- No Tourvisor request contract, pricing contract, Metrika/goals or external lead contract changed in the redesign token-convergence work.

## Exact next work order

The controller task contracts override this historical ordering whenever they differ.

1. Complete targeted browser/visual QA for #703, #704, #706, #712 and #715; merge only evidence-green lanes.
2. For #712 verify room/meal consistency, flight switching, baggage/fuel presentation and final price refresh at 375 and 1440.
3. For #715 verify one responsive footer/community composition at 375 and 1440 with no overflow or duplicate footer.
4. Continue the approved lead-handoff implementation after selected-tour acceptance.
5. After integration, re-run the public product at 375/430/768/1024/1440 and reassess the site-wide visual score.
6. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
7. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist.

## Guardrails

- AnyTour Design System 2.0 is the only canonical design system.
- Work only inside `pyatkoff/poisk-turov-test`; production deploy scope is the allowed V2/standalone scope only.
- Do not redesign/replace the existing AnyTour logo.
- Do not modify neighboring projects, global site assets or server config outside allowed scope.
- Do not change Yandex Metrika configuration/goals.
- Do not change Tourvisor contracts, pricing semantics or the existing lead-sending mechanism/external contract.
- Do not migrate unresolved legal/payment content.
- PR #254 remains deferred unless its separate DB/platform architecture is freshly proven safe.
- `technical_refactor` remains deferred until explicit owner direction.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
- If one item is blocked, record/defer it and continue independent safe approved work.

## Explicitly inactive until owner launches traffic

Live conversion optimization/C7; live product optimization/B8; operational traffic feedback/A8; browser-session funnel analysis; waiting for `search → tour → lead` samples; traffic-based A/B-like conclusions.

Absence of traffic is expected and is never a blocker in the current phase.
