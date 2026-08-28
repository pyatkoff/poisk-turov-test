# poisk-turov-test — Autopilot State

Updated: 2026-08-28 15:18 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — PRE-TRAFFIC 9/10 QUALITY GATE

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence. Do not spend autonomous development time collecting, waiting for, interpreting or optimizing against traffic until the owner explicitly re-enables that phase.

The product release gate is now: **every material area >= 9/10**. If production is healthy, audit the whole product, score the material areas, then improve the weakest sub-9 area. Never stop because traffic is absent.

## Scorecard areas

Search UX; waiting/progress/recovery; results & comparison; selected tour; flights & price confidence; lead UX; mobile UX; tablet/desktop UX; brand & trust; visual quality/consistency; product differentiation/competitor gap; SEO/future site foundation.

Scores are product-quality assessments backed by functional and visual evidence, not traffic metrics. Re-score after material changes. A 9/10 score means ready for traffic-quality scrutiny, not “perfect forever”.

## Active roadmap

- BR1 Branded first impression — ACTIVE; grounded AnyTour-specific first-screen proof shipped in PR #123.
- BR2 Trust architecture — ACTIVE
- BR3 Product-wide visual identity — ACTIVE
- BR4 SEO-ready brand shell — QUEUED
- BR5 Social + app footer — QUEUED; add a polished lower-page/footer presence for AnyTour social channels (MAX, Telegram, VK) and mobile apps (App Store, Google Play), using the project's verified real destination URLs only. Keep it secondary to search/lead conversion, responsive and touch-friendly; do not introduce new analytics goals or alter lead transport. Recover/verify the previously supplied links before implementation rather than guessing destinations.
- PX1 Decision support in results — ACTIVE; contextual lowest-price/best-rating and nearest-price context shipped.
- PX2 Flexible search/recovery — ACTIVE; explicit zero-result date recovery shipped.
- PX3 Price confidence — QUEUED
- PX4 Flight decision quality — ACTIVE; grounded flight price/routing tradeoffs shipped.
- PX5 Hotel choice depth — QUEUED
- PX6 Save/compare/resume — ACTIVE; lightweight hotel comparison shipped.
- PX7 Price watch/return intent — RESEARCH pending product-contract choices

## Production baseline

PR #123 (`768f48b56f5cd692489f664fefec528b7e958964`) shipped a grounded first-screen AnyTour proof without changing search, analytics, lead transport or deployment scope. All PR functional/security/visual gates were green.

After merge, the main-only `Validate active V2 contract` correctly blocked the release because its flight-price grep expected spaced `flightPrice - basePrice` while the semantically identical implementation used `flightPrice-basePrice`. This was a CI contract-format false failure, not a production pricing defect. PR #124 (`5d078e7c89b84c3f20026f3f3e939496257dc14c`) restored the expected formatting only.

PR #124 verification is green: Security `33173388430`; active V2 contract `33173388343`; tour live `33173388380`; result-detail live `33173388408`; V2-only deploy/live search smoke `33173388498`. Its PR gates were also fully green, including flight tradeoffs, branch/startup bundles and all visual suites.

Production is therefore green on `5d078e7c89b84c3f20026f3f3e939496257dc14c`.

Earlier production contracts remain protected: V2 bundles, legacy/AI/MAX URL hydration, primary catalog sync, responsive meal visibility, bounded mobile CTA, selected-tour/flight/price behavior, structured continue-search progress ownership and lead transport.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional/visual results for actual breakage.
2. Re-audit the complete V2 journey on mobile/intermediate/desktop: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead/recovery.
3. Re-score the 12-area quality scorecard now that PX1/PX2/PX4/PX6 and BR1 have materially advanced since the last scorecard snapshot.
4. Take the weakest core product area below 9 and implement the highest-value safe improvement; likely candidates to inspect first are PX5 hotel choice depth, PX3 price confidence and remaining BR2/BR3 consistency gaps.
5. Include BR5 social + app footer when brand/trust/site-shell work reaches that priority: verify the supplied MAX/Telegram/VK/App Store/Google Play destinations, then implement and visually validate it across mobile and desktop without distracting from primary conversion actions.
6. Run relevant functional/regression/visual checks; deploy V2 only when green; smoke production after deploy.
7. Re-score affected areas and immediately continue to the next weakest sub-9 area.
8. Keep Brand and Product/competitor-gap queues active regardless of traffic availability.
9. Do not run traffic diagnostics or make conversion conclusions from owner/team usage until explicitly re-enabled.

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
