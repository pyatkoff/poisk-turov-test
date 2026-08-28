# poisk-turov-test — Autopilot State

Updated: 2026-08-28 17:19 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — PRE-TRAFFIC 9/10 QUALITY GATE

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence. Do not spend autonomous development time collecting, waiting for, interpreting or optimizing against traffic until the owner explicitly re-enables that phase.

The product release gate remains: **every material area >= 9/10**. If production is healthy, audit the whole product, score the material areas, then improve the weakest sub-9 area. Never stop because traffic is absent.

## Scorecard areas

Search UX; waiting/progress/recovery; results & comparison; selected tour; flights & price confidence; lead UX; mobile UX; tablet/desktop UX; brand & trust; visual quality/consistency; product differentiation/competitor gap; SEO/future site foundation.

Scores are product-quality assessments backed by functional and visual evidence, not traffic metrics. Re-score after material changes. A 9/10 score means ready for traffic-quality scrutiny, not “perfect forever”.

Latest re-score after PX3/PX5: core areas now at or above 9 for Results/Comparison, Selected Tour, Flights/Price, Lead UX and Product Differentiation. Weakest core area remains **Visual quality / consistency (8.8)**; Search, Waiting/Recovery, Mobile, Tablet/Desktop and Brand/Trust are at 8.9. SEO remains intentionally deferred at 7.2 until the core search product reaches the gate.

## Active roadmap

- BR1 Branded first impression — ACTIVE; grounded AnyTour-specific first-screen proof shipped in PR #123.
- BR2 Trust architecture — ACTIVE; next after/alongside BR3 where consistency gaps are confirmed.
- BR3 Product-wide visual identity — ACTIVE; first concrete cross-generation defect fixed in PR #128: the blue primary search CTA no longer carries the orange header-order shadow. Branch-bundle regression coverage now protects the blue primary-action styling at 375/768/1024/1440. Continue the whole-flow consistency audit before re-scoring above 8.8.
- BR4 SEO-ready brand shell — QUEUED until all core product areas >= 9.
- BR5 Social + app footer — QUEUED; add a polished lower-page/footer presence for AnyTour social channels (MAX, Telegram, VK) and mobile apps (App Store, Google Play), using verified real destination URLs only. Keep it secondary to search/lead conversion, responsive and touch-friendly; do not introduce new analytics goals or alter lead transport. Recover/verify the previously supplied links before implementation rather than guessing destinations.
- PX1 Decision support in results — ACTIVE / 9-level; contextual badges, nearest-price context and compare support shipped.
- PX2 Flexible search/recovery — ACTIVE; explicit zero-result date recovery shipped; score 8.9 pending confirmed broader recovery friction.
- PX3 Price confidence — SHIPPED; PR #125 clarifies search price vs selected-flight price and pre-payment reconfirmation.
- PX4 Flight decision quality — ACTIVE / 9-level; grounded flight price/routing trade-offs shipped.
- PX5 Hotel choice depth — SHIPPED; PR #126 adds grounded selected-tour category/rating/sea/meal/room decision summary.
- PX6 Save/compare/resume — ACTIVE / 9-level; lightweight hotel comparison shipped.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Production baseline

PR #125 (`c8f14b459234596af3bbb9b6bc0ff4772bafa2a4`) shipped selected-tour price-confidence guidance. A startup-bundle CI contract had a stale hard-coded JS count (25 vs the legitimate new 26th asset); the CI expectation was corrected in the PR, after which all functional/security/visual gates passed. V2-only deploy `33182874015`, live search smoke and post-deploy visual audit `33182946157` are green.

PR #126 (`2ffe387f2d05ab99172533cf51cb42f04be85063`) shipped the grounded selected-tour decision summary. PR branch-bundle validation exercised the new summary at 375/768/1024/1440 with no overflow, and all PR functional/security/visual gates passed. V2-only deploy `33183567573`, active contract/verification/live search smoke, post-deploy visual audit `33183656505` and deterministic visual baseline `33183656489` are green.

PR #128 (`51fb4925e13cf7982e47f61c16e65a47a4ed9ff7`) started BR3 by correcting the mismatched orange shadow on the blue primary search CTA and adding a computed-style branch-bundle guard. All PR gates passed. V2-only deploy `33184249233`, verification/live search smoke, post-deploy visual audit `33184352882` and deterministic baseline `33184352963` are green.

Production is therefore fully green on `51fb4925e13cf7982e47f61c16e65a47a4ed9ff7`, with search/lead/pricing/Metrika contracts unchanged.

Earlier production contracts remain protected: V2 bundles, legacy/AI/MAX URL hydration, primary catalog sync, responsive meal visibility, bounded mobile CTA, selected-tour/flight/price behavior, structured continue-search progress ownership and lead transport.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional results for actual breakage.
2. Continue BR3 whole-flow visual consistency audit across mobile/intermediate/desktop: progress/recovery → results/compare → selected tour → rooms → flights/price → lead, plus remaining search component families. Prioritize concrete mixed-generation component behavior, inconsistent control hierarchy, spacing/type or surface treatment; avoid cosmetic-only churn.
3. Run full branch-bundle and relevant visual gates for each BR3 change; V2-only deploy only when green; smoke production and post-deploy viewports.
4. Re-score Visual quality only after enough material inconsistency is removed to justify >=9. Until then keep it at 8.8.
5. Once Visual quality reaches >=9, take the weakest confirmed 8.9 area rather than inventing features.
6. Keep BR5 social/app footer queued; recover and verify exact external destinations before implementation.
7. Keep SEO expansion deferred until all core product areas reach 9.
8. Do not run traffic diagnostics or make conversion conclusions from owner/team usage until explicitly re-enabled.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`; production deploy scope is V2 only.
- Do not redesign/replace the existing AnyTour logo.
- Do not modify neighboring projects, global site assets or server config outside allowed V2 scope.
- Do not change Yandex Metrika configuration/goals.
- Do not change the existing lead-sending mechanism/external contract.
- Production breakage → lead loss → incorrect data → poor UX → responsive/visual → weakest sub-9 score → roadmap → cosmetic/refactor.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
- If one item is blocked, record/defer it and continue independent safe work.

## Explicitly inactive until owner launches traffic

Live conversion optimization/C7; live product optimization/B8; operational traffic feedback/A8; browser-session funnel analysis; waiting for `search → tour → lead` samples; traffic-based A/B-like conclusions.

Absence of traffic is expected and is never a blocker in the current phase.
