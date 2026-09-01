# poisk-turov-test — Product roadmap / compatibility state

Updated: 2026-09-01

Operational companion to `AGENTS.md`. Execution state is no longer stored here or in `AUTOPILOT_STATE.json`: the authoritative queue is `autopilot-v2/tasks/*.json`, terminal results are `autopilot-v2/outcomes/*.json`, and current status is derived by `python3 autopilot-v2/controller.py status`. `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work. This document keeps roadmap/history context only.

## Current redesign overlay — approved AnyTour Design System 2.0

The full agreed AnyTour redesign, including Search 2.0, is approved and is being implemented in isolated lanes. AnyTour Design System 2.0 is the only canonical design system. User-facing MEDIUM changes require their task-specific browser/visual evidence before feature integration and normal production evidence before being counted as shipped.

Current accepted/shipped state on 2026-09-01:

- **Hotel/room detail hierarchy — SHIPPED via PR #739.** The previously approved hotel and room presentation was restored and verified in feature/search-core through #736/#737, then released to `main` as an intentionally narrow two-CSS-file slice. Main already contained every required DS2 token and loaded `design-system-v2.css` before both active files. All eight main PR gates were green. Production deploy run `33517984397` completed successfully through release copy, public-page verification, unchanged lead-bridge verification and live search smoke; live tour/flight validation also passed. No Tourvisor identifiers, selected-tour state, pricing semantics, Metrika, external lead transport, logo or unresolved legal/payment content changed.
- **Search 2.0 results/filter UX — accepted after PR #726 QA.** Deterministic PR-safe browser evidence is green at 375/1440 for progressive 25→100 result capture, local stars narrowing without restarting the server submit lifecycle, server auto-refresh when widening the original stars constraint, progressive additional filters, recovery state and 44px retry target. Security guard is green. The gate makes no Tourvisor call, real lead submission or production request and does not alter Metrika, pricing, lead transport or logo contracts. This lane still requires its own narrow main release.
- **Selected tour / flight choice — PR #712.** Approved staged `Ваш тур → Выбор рейса → Заявка менеджеру` hierarchy is accepted in feature/search-core. Its private palette was removed in review in favor of canonical DS2 tokens. Fast CI and Security guard are green. PR-safe Playwright evidence is green at 375/430/768/1024/1440 for hierarchy, flight step, price/fuel refresh and horizontal containment. Tourvisor IDs, selected-tour state, pricing semantics, Metrika and external lead transport were not changed. It still requires a narrow main release.
- **Factual footer/community — PR #715.** Accepted feature composition uses only the verified MAX, Telegram, VK, App Store and Google Play destinations already present in the project plus existing payment/legal links and MasterCard/Visa/Мир. Fast CI/Security and five-width PR-safe visual evidence are green. No invented contacts/socials and no parallel footer were introduced. It still requires a narrow main release.
- **Post-success lead handoff — PR #723.** Accepted feature UI appears only after `v2:lead-success`, reuses the existing verified MAX/Telegram destinations rendered by the community footer, and does not copy phone/name/comment or other PII into messenger URLs. Fast CI and Security are green. Dedicated PR-safe browser evidence at 375 and 1440 is green for sending → error/retry → success, exact two messenger destinations, no PII leak and no horizontal overflow. External lead persistence/transport and field mapping remain unchanged. It still requires a narrow main release.
- **PR-safe visual infrastructure — #722/#724/#726/#736/#737.** These no-deploy Playwright gates test checked-out PR code locally, do not call production protected services, and provide screenshot/report evidence for approved DS2 lanes.

PRs #703 header and #704 loading/recovery remain closed unmerged and are not active release candidates. Historical PR #706 stays closed; its already-approved hotel/room presentation was re-applied narrowly and shipped through #736/#737/#739 rather than reopening the stale branch.

The separate public-site visual score remains **7.2/10**. The hotel/room slice is a meaningful production step, but a site-wide score increase would be premature until more of the agreed cross-site redesign reaches production and the whole product is re-audited at five widths.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture is explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. Country/content routes are being migrated incrementally.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active roadmap

- ROOT STABILIZATION — ACTIVE HIGHEST PRIORITY while the new standalone shell/routes are being migrated.
- **DS2_NARROW_RELEASE_PLAN — ACTIVE NEXT LANE.** The first narrow accepted redesign slice is shipped through #739. Continue by inventorying the remaining accepted runtime dependencies against current `main` and prepare only self-contained main-targeted release slices. Do not bulk merge `feature/search-core`.
- REDESIGN_HOTEL_TOUR_SELECTION — SHIPPED in production via #739 after #736/#737 evidence.
- REDESIGN_SEARCH_RESULTS — ACCEPTED in feature/search-core after #726 targeted QA.
- REDESIGN_SELECTED_TOUR — ACCEPTED in feature/search-core via #712.
- REDESIGN_FOOTER_COMMUNITY — ACCEPTED in feature/search-core via #715.
- REDESIGN_LEAD_HANDOFF — ACCEPTED in feature/search-core via #723.
- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; publication/indexing policy remains deliberately deferred.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Release boundary

`main` and `feature/search-core` remain materially diverged with unrelated work mixed in. Do **not** bulk merge or broadly cherry-pick the feature branch into `main`. Accepted redesign work is not production-shipped merely because its feature PR merged.

Release only narrowly justified accepted changes. Any production release must go through the normal `main` deploy workflows and their post-deploy live/search/selected-tour/public-page gates. #739 proves the narrow-release path can work without pulling the divergent feature branch into production.

## Exact next work order

The controller task contracts override this historical ordering whenever they differ.

1. Inventory the remaining accepted Search 2.0, selected-tour, factual footer and post-success handoff runtime files against current `main`; hotel/room detail is already shipped.
2. Select the smallest self-contained accepted lane whose dependencies are already present in main, and prepare only a narrow main-targeted PR.
3. Prove each release slice excludes Metrika/goals, Tourvisor contract changes, pricing semantics, external lead transport/field mapping, logo replacement and unresolved legal/payment migrations.
4. Run the relevant main CI/visual gates before merge; after release run normal V2 + standalone deploy/post-deploy gates.
5. Re-audit the public product at 375/430/768/1024/1440 before changing the site-wide visual score.
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
- CI green alone is not production DONE; require relevant functional/production/visual evidence.
- If one item is blocked, record/defer it and continue independent safe approved work.

## Explicitly inactive until owner launches traffic

Live conversion optimization/C7; live product optimization/B8; operational traffic feedback/A8; browser-session funnel analysis; waiting for `search → tour → lead` samples; traffic-based A/B-like conclusions.

Absence of traffic is expected and is never a blocker in the current phase.
