# poisk-turov-test — Product roadmap / compatibility state

Updated: 2026-09-01

Operational companion to `AGENTS.md`. Execution state is no longer stored here or in `AUTOPILOT_STATE.json`: the authoritative queue is `autopilot-v2/tasks/*.json`, terminal results are `autopilot-v2/outcomes/*.json`, and current status is derived by `python3 autopilot-v2/controller.py status`. `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work. This document keeps roadmap/history context only.

## Current redesign overlay — approved AnyTour Design System 2.0

The full agreed AnyTour redesign, including Search 2.0, is approved and is being implemented in isolated lanes. AnyTour Design System 2.0 is the only canonical design system. User-facing MEDIUM changes require their task-specific browser/visual evidence before feature integration and normal production evidence before being counted as shipped.

Current accepted/shipped state on 2026-09-01:

- **Hotel/room detail hierarchy — SHIPPED via PR #739.** The previously approved hotel and room presentation was restored and verified in feature/search-core through #736/#737, then released to `main` as an intentionally narrow two-CSS-file slice. Main already contained every required DS2 token and loaded `design-system-v2.css` before both active files. All eight main PR gates were green. Production deploy run `33517984397` completed successfully through release copy, public-page verification, unchanged lead-bridge verification and live search smoke; live tour/flight validation also passed. No Tourvisor identifiers, selected-tour state, pricing semantics, Metrika, external lead transport, logo or unresolved legal/payment content changed.
- **Selected tour / flight choice — SHIPPED via PR #743.** The accepted feature/search-core hierarchy from #712 was rebuilt narrowly on current `main`: `selected-tour-description-v1.js` carries the approved `Ваш тур → Выбор рейса → Заявка менеджеру` DS2 hierarchy and responsive presentation; `flight-price-sync-v1.js` changed only the tradeoff-chip palette to canonical DS2 tokens. Crucially, newer main protections for selected-flight fuel fallback and incomplete-price comparison were preserved rather than overwritten by the older accepted diff. All eleven PR workflows were green, including standalone/V2 validation, selected-tour visual, five-width visual baseline, flight interaction, fuel fallback, second-tour state isolation, bundles and Security guard. Production deploy run `33522968133` completed successfully through release copy, public-page verification, unchanged lead-bridge verification and live search smoke. Tourvisor, pricing semantics, Metrika, external lead transport/field mapping, logo and unresolved legal/payment content were not changed.
- **Post-success lead handoff — SHIPPED via PR #745.** The accepted #723/#724 handoff was released as one active JS file on current `main`. It appears only after `v2:lead-success`, discovers MAX and Telegram from the existing shared community footer, and does not copy phone/name/comment or other PII into messenger URLs. All nine main PR workflows were green, including standalone/V2 validation, selected-tour visual, visual baseline, lead recovery, bundles and Security guard. `Deploy V2 only` run `33523683585` and standalone deploy run `33523683467` both succeeded; V2 verification, public pages, unchanged production lead bridge and live search smoke passed. External lead persistence/transport and field mapping remain unchanged.
- **Search 2.0 results/filter UX — ACCEPTED and dependency-inventoried via #748/#752.** Deterministic PR-safe browser evidence remains green at 375/1440 for progressive 25→100 result capture, local stars narrowing without restarting the server submit lifecycle, server auto-refresh when widening the original stars constraint, progressive additional filters, recovery state and 44px retry target. #748 repaired task ownership so the canonical visual owner is `v2/ds2-search.css`. #752 records the dependency-safe narrow-release inventory: `results-depth-v1.js` owns 25→100 expansion, `results-local-filters-v1.js` owns payload-local narrowing, `ds2-results-filters.js` owns the desktop rail, and `search-redesign-v2.js`/`ds2-search.css` own the accepted shell. Crucially, current `main` already has `results-filter-autorefresh-v1.js` **v3**, while feature/search-core contains older **v1**; the main v3 behavior must be preserved rather than overwritten. This lane now moves from inventory to fresh-main release assembly.
- **Factual footer/community — accepted feature composition, current cleanup BLOCKED.** PR #715 established the factual destination contract using only verified MAX, Telegram, VK, App Store and Google Play destinations plus existing payment/legal links and MasterCard/Visa/Мир. #747 repaired the task boundary to own canonical `v2/ds2-search.css`. Verification wiring was then corrected through #749/#751 so the approved visual gate measures the real checked-out `site-footer-v1.php` component instead of stale fixture markup. The latest #742 visual run `33529088535` is green at 768/1024/1440 and fails only at 375/430: real social targets are 34px and app-store targets 30px, below the 44px mobile interaction contract. Do not release the footer cleanup until those targets are fixed in canonical `v2/ds2-search.css` and the gate is green.
- **PR-safe visual infrastructure — #722/#724/#726/#736/#737/#749/#751 plus main release gates for #739/#743/#745.** These no-deploy Playwright gates test checked-out PR code locally, do not call production protected services, and provide screenshot/report evidence for approved DS2 lanes.

PRs #703 header and #704 loading/recovery remain closed unmerged and are not active release candidates. Historical PR #706 stays closed; its already-approved hotel/room presentation was re-applied narrowly and shipped through #736/#737/#739 rather than reopening the stale branch.

The separate public-site visual score remains **7.2/10**. Three meaningful search/lead-flow DS2 slices are production-shipped, but a site-wide score increase would still be premature until Search 2.0 and more of the agreed cross-site redesign reach production and the whole product is re-audited at five widths.

## Current phase — CORE PRODUCT 9/10, STANDALONE SITE STABILIZATION, SEO FOUNDATION 8.8

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX, Mobile UX, Tablet/Desktop UX, Brand/Trust, Visual Quality and Product Differentiation remain assessed at 9.0 with functional/visual evidence.

Standalone architecture is explicit: `https://anytoour.ru/` is the new homepage and `https://anytoour.ru/poisk-turov/` is the full tour search. The legacy `/poisk-turov-test/v2/` route remains compatibility-only and must not regress. Country/content routes are being migrated incrementally.

SEO/site foundation remains **8.8**. Standalone remains deliberately `noindex,follow`; do not enable indexing/sitemap publication merely because routes are live. The remaining path to 9 requires deliberate publication/indexing policy and reviewed real content.

## Active roadmap

- ROOT STABILIZATION — ACTIVE HIGHEST PRIORITY while the new standalone shell/routes are being migrated.
- **DS2_NARROW_RELEASE_PLAN — ACTIVE NEXT LANE.** Three narrow accepted redesign slices are shipped through #739, #743 and #745. Search 2.0 dependency inventory is complete via #752; next assemble the smallest self-contained release on fresh `main`, preserving newer main runtime protections instead of copying feature files wholesale. Do not bulk merge `feature/search-core`.
- REDESIGN_HOTEL_TOUR_SELECTION — SHIPPED in production via #739 after #736/#737 evidence.
- REDESIGN_SELECTED_TOUR — SHIPPED in production via #743 after accepted #712 evidence plus current-main preservation checks.
- REDESIGN_LEAD_HANDOFF — SHIPPED in production via #745 after #723/#724 evidence.
- REDESIGN_SEARCH_RESULTS — ACCEPTED in feature/search-core after #726 targeted QA; release inventory locked by #752.
- REDESIGN_FOOTER_COMMUNITY — ACCEPTED contract, but current cleanup #742 is BLOCKED only by 375/430 mobile target sizing after real-component QA correction #749/#751.
- BR1–BR3 — SHIPPED / MAINTAIN at 9-level.
- BR4 SEO-ready brand shell — ACTIVE at 8.8; publication/indexing policy remains deliberately deferred.
- PX1–PX6 — SHIPPED / MAINTAIN at 9-level.
- PX7 Price watch/return intent — RESEARCH pending persistence/contact/product-contract choices.

## Release boundary

`main` and `feature/search-core` remain materially diverged with unrelated work mixed in. Do **not** bulk merge or broadly cherry-pick the feature branch into `main`. Accepted redesign work is not production-shipped merely because its feature PR merged.

Release only narrowly justified accepted changes. Any production release must go through the normal `main` deploy workflows and their post-deploy live/search/selected-tour/public-page gates. #739, #743 and #745 prove the narrow-release path can work without pulling the divergent feature branch into production.

For Search 2.0 specifically, `DS2_SEARCH2_RELEASE_INVENTORY.md` is the current assembly guardrail. The feature bundle manifest is not a release recipe: current-main `results-filter-autorefresh-v1.js` v3 must remain authoritative over feature v1, while only the missing accepted Search 2.0 modules should be added around it.

## Exact next work order

The controller task contracts override this historical ordering whenever they differ.

1. Assemble accepted Search 2.0 from a fresh `main` branch using `DS2_SEARCH2_RELEASE_INVENTORY.md`: add only missing accepted modules and minimally adapt current-main `index.php`/`bundle-manifest-v1.php`.
2. Preserve current-main `results-filter-autorefresh-v1.js` v3; prove local narrowing does not compete with server autorefresh and widening still uses the existing `V2SearchLifecycle.submit()` path.
3. Independently fix footer/community #742 social/app targets to at least 44px at 375/430 inside canonical `v2/ds2-search.css`; rerun the real-component five-width gate before merge.
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
