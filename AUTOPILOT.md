# poisk-turov-test — Product roadmap / compatibility state

Updated: 2026-09-01

Operational companion to `AGENTS.md`. Authoritative execution state is `autopilot-v2/state.json` plus `autopilot-v2/tasks/*.json`; `AUTOPILOT_STATE.json` remains a compatibility resume point.

## Current phase — APPROVED ANYTOUR DESIGN SYSTEM 2.0 IMPLEMENTATION

The full agreed AnyTour redesign, including Search 2.0, is approved and is being implemented in isolated lanes. AnyTour Design System 2.0 is the only canonical design system. User-facing MEDIUM changes still require their task-specific browser/visual evidence before merge.

The shipped search baseline remains protected at its prior 9-level functional quality. The separate public-site visual assessment remains **7.2/10** until redesigned lanes are integrated, deployed and production-verified. SEO/site foundation remains **8.8** and public indexing remains deliberately deferred.

## Active redesign lanes

- Search 2.0 results workspace — approved and present on `feature/search-core`; isolated preview HTTP/health/CSS/noindex verification is green.
- Header shell — PR #703 code-green, awaiting targeted visual QA.
- Loading/recovery — PR #704 code-green, awaiting targeted visual QA.
- Hotel/tour selection — PR #706 code-green, awaiting targeted visual QA.
- Selected tour — PR #712 implements the approved staged **Ваш тур → Выбор рейса → Заявка менеджеру** hierarchy. Review found that the new layer had introduced a private injected color palette; commit `183010fce6250bbe27b887323190b4fd075bf383` replaces it with canonical DS2 tokens. Fast CI and Security guard are green; room/flight/price browser QA plus 375/1440 visual QA remain required before merge.
- Lead handoff — ownership was repaired in merged PR #716; only active checkout/lead UI files are owned, while backend transport remains protected. Implementation stays queued after selected-tour acceptance.
- Footer/community — PR #715 implements only verified MAX, Telegram, VK, App Store, Google Play, existing legal/payment links and MasterCard/Visa/Мир. Review likewise removed a new hardcoded palette in favor of canonical DS2 tokens at `7f25aec09247fba85e8dbb8d65ecf8760c0668f2`; Fast CI and Security guard are green, 375/1440 visual QA remains required.

PR #713 is closed as superseded by the actual approved hierarchy in #712. PR #711 is closed as obsolete because the full redesign direction is already approved.

## Next work order

1. Complete targeted browser/visual QA for #703, #704, #706, #712 and #715; merge only evidence-green lanes.
2. For #712 verify room/meal consistency, flight switching, baggage/fuel presentation and final price refresh at 375 and 1440.
3. For #715 verify responsive single-footer/community composition at 375 and 1440 with no overflow or duplicate shell.
4. Continue the approved lead-handoff implementation after selected-tour acceptance.
5. After integration, re-run the public product at 375/430/768/1024/1440 and reassess the site-wide visual score.

## Guardrails / deferred

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone scope. Do not replace the AnyTour logo, modify Metrika/goals, Tourvisor contracts, pricing semantics, external lead transport, neighboring projects or unresolved legal/payment content. PR #254 remains deferred unless its separate DB/platform architecture is freshly proven safe. `technical_refactor` remains deferred until explicit owner direction. Traffic/conversion analysis remains disabled until traffic is explicitly launched.
