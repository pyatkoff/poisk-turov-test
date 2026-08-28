# poisk-turov-test — Autopilot State

Updated: 2026-08-28 20:05 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10 GATE REACHED

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

The focused Tablet/Desktop and Brand/Trust audit found no remaining confirmed sub-9 core-tour-search defect. Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation are now all assessed at 9.0 with functional/visual evidence. SEO/site foundation remains 7.2 and becomes the next weakest material area.

## Active roadmap

- BR1 Branded first impression — 9-LEVEL / MAINTAIN.
- BR2 Trust architecture — 9-LEVEL / MAINTAIN; reassurance now spans first impression, results, selected tour and lead CTA.
- BR3 Product-wide visual identity — 9-LEVEL / MAINTAIN.
- BR4 SEO-ready brand shell — ACTIVE; advance reusable site/page architecture without making the temporary V2 route indexable.
- BR5 Social + app footer — QUEUED; add MAX, Telegram, VK, App Store and Google Play only after exact supplied destinations are recovered/verified. Keep secondary to conversion and touch-friendly; no analytics/lead-transport change.
- PX1–PX6 — 9-LEVEL / MAINTAIN.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Production baseline

Final Search-audit production commit remains `e656a364bde48b77267f535bff34aecea8f27969`, with Security, active V2 contract, tour-live, deterministic baseline and post-deploy visual checks green. The latest main runtime/docs commit is `51a6d1ec9898980288476096cca4815620f93a5d`; its Security guard is green.

Tablet/Desktop evidence: the primary PR visual contract covers initial/search picker/advanced/results states at 768/1024/1440 as part of the five-viewport matrix and fails on horizontal overflow or broken interactions. The selected-tour visual contract covers long facts, long descriptions, flights, lead form and error recovery at the same widths. No confirmed density, wrapping, sticky collision, hierarchy or overflow defect remained after the audit.

Brand/Trust evidence: AnyTour-specific office/contract/payment proof is present on the first screen; results include pre-request reassurance; selected tour includes no-payment, contract-before-payment, price/flight verification and support language; lead CTA is explicitly a confirmation request rather than payment. Dedicated trust and selected-tour visual contracts cover 375/430/768/1024/1440 without overflow.

## Exact next work order

1. Inspect fresh `main`, open PRs and latest deploy/security/functional/visual results for actual breakage.
2. Begin BR4 / SEO-site foundation work from the existing protected temporary-route contract.
3. Do **not** remove `noindex`, invent a canonical or promote the current `/poisk-turov-test/v2/` route; final public URL remains an explicit product/routing choice.
4. Prefer safe reusable foundation independent of final route: semantic page shell, future landing-page boundaries, internal-link/content component architecture and regression coverage.
5. Periodically re-audit the whole V2 conversion flow while SEO foundation evolves; any production/lead/data/UX regression outranks BR4.
6. Keep BR5 queued and recover exact external app/social destinations before implementation; do not guess URLs.
7. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

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
