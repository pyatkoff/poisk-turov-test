# poisk-turov-test — Product roadmap / compatibility state

Updated: 2026-09-01

Operational companion to `AGENTS.md`. Execution state is no longer stored here or in `AUTOPILOT_STATE.json`: the authoritative queue is `autopilot-v2/tasks/*.json`, terminal results are `autopilot-v2/outcomes/*.json`, and current status is derived by `python3 autopilot-v2/controller.py status`. `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work. This document keeps roadmap/history context only.

## Current redesign overlay — approved AnyTour Design System 2.0

The full agreed AnyTour redesign, including Search 2.0, is approved and is being implemented in isolated lanes. AnyTour Design System 2.0 is the only canonical design system. User-facing MEDIUM changes require their task-specific browser/visual evidence before feature integration and normal production evidence before being counted as shipped.

Current accepted/shipped state on 2026-09-01:

- **Hotel/room detail hierarchy — SHIPPED via PR #739.** The previously approved hotel and room presentation was restored and verified in feature/search-core through #736/#737, then released to `main` as an intentionally narrow two-CSS-file slice. Main already contained every required DS2 token and loaded `design-system-v2.css` before both active files. All eight main PR gates were green. Production deploy run `33517984397` completed successfully through release copy, public-page verification, unchanged lead-bridge verification and live search smoke; live tour/flight validation also passed. No Tourvisor identifiers, selected-tour state, pricing semantics, Metrika, external lead transport, logo or unresolved legal/payment content changed.
- **Selected tour / flight choice — SHIPPED via PR #743.** The accepted feature/search-core hierarchy from #712 was rebuilt narrowly on current `main`: `selected-tour-description-v1.js` carries the approved `Ваш тур → Выбор рейса → Заявка менеджеру` DS2 hierarchy and responsive presentation; `flight-price-sync-v1.js` changed only the tradeoff-chip palette to canonical DS2 tokens. Crucially, newer main protections for selected-flight fuel fallback and incomplete-price comparison were preserved rather than overwritten by the older accepted diff. All eleven PR workflows were green, including standalone/V2 validation, selected-tour visual, five-width visual baseline, flight interaction, fuel fallback, second-tour state isolation, bundles and Security guard. Production deploy run `33522968133` completed successfully through release copy, public-page verification, unchanged lead-bridge verification and live search smoke. Tourvisor, pricing semantics, Metrika, external lead transport/field mapping, logo and unresolved legal/payment content were not changed.
- **Search 2.0 results/filter UX — accepted after PR #726 QA.** Deterministic PR-safe browser evidence is green at 375/1440 for progressive 25→100 result capture, local stars narrowing without restarting the server submit lifecycle, server auto-refresh when widening the original stars constraint, progressive additional filters, recovery state and 44px retry target. Security guard is green. The gate makes no Tourvisor call, real lead submission or production request and does not alter Metrika, pricing, lead transport or logo contracts. This lane still requires its own narrow main release.
- **Factual footer/community — accepted feature composition, current cleanup BLOCKED.** PR #715 established the factual destination contract using only verified MAX, Telegram, VK, App Store and Google Play destinations plus existing payment/legal links and MasterCard/Visa/Мир. A newer cleanup PR #742 correctly removes unsupported 24/7/best-price/instant-confirmation/info@anytour.ru/Apple Pay claims, but its five-width visual workflow currently fails at 375/430 because one or more mobile social/app targets fall below the 44px interaction contract. Do not release this footer cleanup until that visual regression is fixed and the gate is green.
- **Post-success lead handoff — PR #723.** Accepted feature UI appears only after `v2:lead-success`, reuses the existing verified MAX/Telegram destinations rendered by the community footer, and does not copy phone/name/comment or other PII into messenger URLs. Fast CI and Security are green. Dedicated PR-safe browser evidence at 375 and 1440 is green for sending → error/retry → success, exact two messenger destinations, no PII leak and no horizontal overflow. External lead persistence/transport and field mapping remain unchanged. It still requires a narrow main release.
- **PR-safe visual infrastructure — #722/#724/#726/#736/#737 plus main release gates for #739/#743.** These no-deploy Playwright gates test checked-out PR code locally, do not call production protected services, and provide screenshot/report evidence for approved DS2 lanes.

PRs #703 header and #704 loading/recovery remain closed unmerged and are not active release candidates. Historical PR #706 stays closed; its already-approved hotel/room presentation was re-applied narrowly and shipped through #736/#737/#739 rather than reopening the stale branch.

The separate public-site visual score remains **7.2/10**. Two meaningful search-flow DS2 slices are now production-shipped, but a site-wide score increase would still be premature until more of the agreed cross-site redesign reaches production and the whole product is re-audited at five widths.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture is explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. Country/content routes are being migrated incrementally.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active roadmap

- ROOT STABILIZATION — ACTIVE HIGHEST PRIORITY while the new standalone shell/routes are being migrated.
- **DS2_NARROW_RELEASE_PLAN — ACTIVE NEXT LANE.** Two narrow accepted redesign slices are shipped through #739 and #743. Continue by inventorying remaining accepted runtime dependencies against current `main` and prepare only self-contained main-targeted release slices. Post-success lead handoff is the next likely small candidate; Search 2.0 should be inventoried independently. Do not bulk merge `feature/search-core`.
- REDESIGN_HOTEL_TOUR_SELECTION — SHIPPED in production via #739 after #736/#737 evidence.
- REDESIGN_SELECTED_TOUR — SHIPPED in production via #743 after accepted #712 evidence plus current-main preservation checks.
- REDESIGN_SEARCH_RESULTS — ACCEPTED in feature/search-core after #726 targeted QA.
- REDESIGN_FOOTER_COMMUNITY — ACCEPTED contract, but current cleanup #742 is BLOCKED by 375/430 mobile target failure.
- REDESIGN_LEAD_HANDOFF — ACCEPTED in feature/search-core via #723.
- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; publication/indexing policy remains deliberately deferred.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Release boundary

`main` and `feature/search-core` remain materially diverged with unrelated work mixed in. Do **not** bulk merge or broadly cherry-pick the feature branch into `main`. Accepted redesign work is not production-shipped merely because its feature PR merged.

Release only narrowly justified accepted changes. Any production release must go through the normal `main` deploy workflows and their post-deploy live/search/selected-tour/public-page gates. #739 and #743 prove the narrow-release path can work without pulling the divergent feature branch into production.

## Exact next work order

The controller task contracts override this historical ordering whenever they differ.

1. Inventory the accepted post-success lead handoff against current `main` as the next likely small self-contained release candidate; separately inventory Search 2.0 dependencies.
2. Keep footer/community cleanup #742 out of release until its 375/430 mobile target regression is fixed and the full five-width visual gate is green.
3. Select the smallest self-contained accepted lane whose dependencies are already present in main, and prepare only a narrow main-targeted PR.
4. Prove each release slice excludes Metrika/goals, Tourvisor contract changes, pricing semantics, external lead transport/field mapping, logo replacement and unresolved legal/payment migrations.
5. Run the relevant main CI/visual gates before merge; after release run normal V2 + standalone deploy/post-deploy gates.
6. Re-audit the public product at 375/430/768/1024/1440 before changing the site-wide visual score.
7. Preserve legacy `/poisk-turov-test/v2/` runtime paths, privacy URL, Bitrix session behavior and existing lead contract.
8. Revisit BR4 indexing only after deliberate publication policy and reviewed content inventory exist.

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
