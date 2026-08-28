# poisk-turov-test — Autopilot State

Updated: 2026-08-28 14:11 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — PRE-TRAFFIC 9/10 QUALITY GATE

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence. Do not spend autonomous development time collecting, waiting for, interpreting or optimizing against traffic until the owner explicitly re-enables that phase.

The product release gate is now: **every material area >= 9/10**. If production is healthy, audit the whole product, score the material areas, then improve the weakest sub-9 area. Never stop because traffic is absent.

## Scorecard areas

Search UX; waiting/progress/recovery; results & comparison; selected tour; flights & price confidence; lead UX; mobile UX; tablet/desktop UX; brand & trust; visual quality/consistency; product differentiation/competitor gap; SEO/future site foundation.

Scores are product-quality assessments backed by functional and visual evidence, not traffic metrics. Re-score after material changes. A 9/10 score means ready for traffic-quality scrutiny, not “perfect forever”.

## Active roadmap

- BR1 Branded first impression — ACTIVE
- BR2 Trust architecture — ACTIVE
- BR3 Product-wide visual identity — ACTIVE
- BR4 SEO-ready brand shell — QUEUED
- PX1 Decision support in results — ACTIVE; PX1.1 contextual lowest-price/best-rating badges shipped in PR #113.
- PX2 Flexible search/recovery — QUEUED
- PX3 Price confidence — QUEUED
- PX4 Flight decision quality — QUEUED
- PX5 Hotel choice depth — QUEUED
- PX6 Save/compare/resume — QUEUED
- PX7 Price watch/return intent — RESEARCH pending product-contract choices

## Production baseline

PR #113 is merged/deployed/production-green and activated the Brand/Product roadmap. Earlier production contracts remain protected: V2 bundles, legacy/AI/MAX URL hydration, primary catalog sync, responsive meal visibility, bounded mobile CTA, selected-tour/flight/price behavior and lead transport.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional/visual results for actual breakage.
2. Re-audit the complete V2 journey on mobile/intermediate/desktop: search → waiting/progress → stale/zero → results/comparison → selected tour → rooms/details → flights/price → lead/recovery.
3. Maintain a 12-area 9/10 scorecard and identify the weakest material area below 9.
4. Implement the highest-value safe improvement in that area; continue through multiple independent tasks while time allows.
5. Run relevant functional/regression/visual checks; deploy V2 only when green; smoke production after deploy.
6. Re-score affected areas and immediately continue to the next weakest sub-9 area.
7. Keep Brand and Product/competitor-gap queues active regardless of traffic availability.
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