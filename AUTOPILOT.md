# poisk-turov-test — Product roadmap / compatibility state

Updated: 2026-09-01

Operational companion to `AGENTS.md`. Execution state is authoritative in `autopilot-v2/tasks/*.json` and `autopilot-v2/state.json`; `AUTOPILOT_STATE.json` remains the compatibility resume point.

## Current phase — APPROVED ANYTOUR DS2 REDESIGN IMPLEMENTATION

The owner-approved full new AnyTour design, including Search 2.0, is being implemented in isolated task lanes. AnyTour Design System 2.0 is the only canonical design system. Already-approved lanes may proceed autonomously, but MEDIUM user-facing work remains unmergeable until its task-required browser/visual evidence is green.

The currently shipped search product remains protected at its prior 9-level functional baseline. The separate public-site visual assessment remains **7.2/10** until the approved redesign reaches integrated preview/production and is verified there. SEO/site foundation remains **8.8**; public indexing remains deliberately deferred.

## Active redesign lanes

- Search 2.0 results workspace — approved and present on `feature/search-core`; isolated preview verification is green.
- Header shell — PR #703 code-green, awaiting targeted visual QA.
- Loading/recovery — PR #704 code-green, awaiting targeted visual QA.
- Hotel/tour selection — PR #706 code-green, awaiting targeted visual QA.
- Selected tour — PR #712 implements the approved staged **Ваш тур → Выбор рейса → Заявка менеджеру** hierarchy. During review its private injected color palette was replaced with canonical DS2 tokens. Fast CI and Security guard are green at `183010fce6250bbe27b887323190b4fd075bf383`; browser room/flight/price QA and 375/1440 visual QA are still required before merge.
- Footer/community — PR #715 implements only verified MAX, Telegram, VK, App Store, Google Play, existing legal/payment links and MasterCard/Visa/Мир. Its new visual layer was also converged onto canonical DS2 tokens; targeted CI/visual QA remains required before merge.
- PR #713 is closed as superseded by the approved hierarchy in #712.

## Latest verification evidence

The isolated Search 2.0 preview deployed successfully from `feature/search-core`: public page HTTP 200, `tourvisor-direct` health response, CSS bundle marker and `noindex,nofollow` preview isolation all passed on 2026-09-01. No production release was made from the open redesign PRs.

No redesign work changes Yandex Metrika/goals, Tourvisor request/identifier contracts, pricing semantics, the external lead transport/field contract, neighboring projects or the AnyTour logo.

## Exact next work order

1. Complete required targeted browser/visual QA for #703, #704, #706, #712 and #715; merge only evidence-green lanes.
2. For #712 verify room/meal consistency, flight switching, baggage/fuel presentation and final price refresh at 375 and 1440.
3. For #715 verify one footer/community composition without overflow or duplicate footer at 375 and 1440.
4. Continue the approved lead-handoff lane only after selected-tour acceptance.
5. After integration, re-run the broader public-product assessment across 375/430/768/1024/1440 and reassess the site-wide score honestly.

## Guardrails / deferred

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone scope. Preserve the AnyTour logo, verified social/app destinations, Metrika/goals, Tourvisor contracts and the existing lead mechanism. Do not migrate unresolved legal/payment content. PR #254 remains deferred unless its separate DB/platform architecture is freshly proven safe. `technical_refactor` remains deferred until explicit owner direction. Traffic/conversion analysis remains disabled until traffic is explicitly launched.
