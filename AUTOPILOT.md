# poisk-turov-test — Autopilot State

Updated: 2026-08-29 10:06 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture is explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. Country/content routes are being migrated incrementally.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active roadmap

- ROOT STABILIZATION — ACTIVE HIGHEST PRIORITY while the new standalone shell/routes are being migrated.
- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; publication/indexing policy remains deliberately deferred.
- BR5 Social + app footer — SHIPPED / MAINTAIN. Community/social/app content is a compact pre-footer; there must be exactly one canonical full footer.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Latest material evidence

- PR #229 fixes a selected-flight price-confidence defect: a flight variant whose price is not yet calculated no longer renders as a bare `₽`; it is explicitly labelled `Цена уточняется`, while calculated variants retain their real amounts. The existing pending-price reset behavior from #227 remains intact.
- #229 passed all 12 PR gates, including the dedicated pending-price label regression, unpriced-flight reset regression, selected-tour visual, full V2 visual, baseline, bundle, standalone and security checks, then merged as `8650c08d4c1bffafc92c28f00b07db578274adb9`.
- PR #230 fixes a confirmed selected-tour lead UI race without changing lead transport. If a lead response for tour A arrives after the visitor has switched to tour B or while B is loading, stale `lead-success` / `lead-error` events can no longer mutate B's form. Current-tour events still decorate normally, and analytics remains earlier in event order so the real outcome is still observed.
- #230 passed all 15 PR gates, including a deterministic stale/current-tour race regression, selected-tour visual, full V2 visual, PHP 8.3, bundle, standalone, comparison, unpriced-flight and security checks, then merged as `929d11b82cb04f52dccf376f7c156bab158dd947`.
- V2-only deploy `33242169768` completed successfully. Standalone deploy `33242169786` completed successfully. The subsequent commit-scoped post-deploy suite completed with no failure/in-progress runs; standalone navigation and live results visual checks were explicitly green.
- Earlier protections remain valid: #225 keeps lowest-price/best-rating claims final-set-only; #226 keeps comparison coherent after result refresh; #227 resets stale selected-flight price when the newly selected flight price is pending; #217/#219/#221 protect progressive/final search recovery.
- No Yandex Metrika configuration/goals, Tourvisor request contract, pricing contract or existing lead-sending mechanism/external field mapping changed in #229–#230.

## Exact next work order

1. Audit selected-tour → back-to-results/resume transitions on mobile/tablet/desktop, including preserved sort order, result set, comparison tray/modal state, scroll/focus behavior and repeated tour selection after returning.
2. Continue same-tour lead recovery audit: failed submit → retry → success/duplicate success, ensuring form data and selected flight/price context remain coherent without touching the external lead contract.
3. Re-check room fallback and flight empty/error/retry states for misleading confidence or dead-end UX; fix only confirmed issues.
4. Re-run the full standalone live journey and responsive/selected-tour guards after the next material downstream UX/data fix.
5. Promote additional country/content routes only when their page exists locally and route/search handoff is verified; otherwise preserve the valid legacy destination.
6. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
7. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist.
8. Do not run traffic diagnostics or make conversion conclusions until explicitly re-enabled.

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
