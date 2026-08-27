# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and hard boundaries; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current product phase

**Product UX/visual development before and during live traffic.**

The active product is `v2/`. Major correctness hardening, technical refactor, SEO foundation and B1–B6 product/visual work are complete. The active roadmap item is **B7 — Performance & Visual Stability (`IN PROGRESS`)**. A8/B8 remain `WAITING_FOR_TRAFFIC`; real advertising traffic immediately outranks speculative polish when it appears.

Protected without explicit approval: Yandex Metrika configuration/goals and the existing lead-sending mechanism/external contract. Work stays inside `pyatkoff/poisk-turov-test`; production deployment stays V2-only.

## Product quality priorities

1. Production breakage, lead loss and incorrect data are highest severity.
2. UX is a primary product requirement.
3. Visual/responsive stability is high priority.
4. User-facing changes are verified at 375, 430, 768, 1024 and 1440 px.
5. Prefer complete user-journey improvements over isolated features.
6. Do not add CSS/workflow layers merely to compensate for existing layers; consolidate ownership only with equivalent coverage proven.
7. Preserve analytics and lead transport contracts.
8. Do not bundle or combine assets merely to reduce request count; require evidence of real cost or duplicated responsibility.

## Active architecture

### Page / config
- `v2/index.php`: server-rendered initial state, active CSS/JS order and public config.
- `form-defaults.php`: initial search defaults.
- `assets.php` + `asset-version-v1.php`: content-based asset versioning.
- `analytics-config.php`: Metrika counter configuration, read-only for autopilot.
- `privacy-config.php`: privacy URL.

### Search / Tourvisor
- `api-v2.php`: active Tourvisor gateway.
- `catalog-cache-v1.php`: catalog TTL cache.
- `search-lifecycle-v6.js`: sole search state/start/status/results/dirty owner.
- `search-progress-ux-v1.js`: waiting/progress/error/zero-result presentation.
- `results-renderer-v5.js`: result rendering and sorting.
- `search-continue-v6.js`: explicit continuation.
- `mobile-results-filters-v1.js`, `search-dirty-ux-v1.js`, `mobile-search-summary-v1.js`: mobile/result-state UX.

### Selected tour / checkout
- `tour-controller-v4.js`: selected-tour flow and stale-response guards.
- `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-description-v1.js`: detail presentation.
- `checkout-experience-v1.js/.css`: checkout hierarchy.
- `flight-price-sync-v1.js`: selected-flight and displayed/submitted price synchronization. A flight variant may legitimately differ from the base tour price.

### Lead path — protected transport contract
- `lead-search-context.js`: search context included with leads.
- `lead-form-guard-v1.js`: lead-entry validation/recovery/dedup/success UX.
- `lead-adapter-v2.php`, `lead-price-v1.php`, `lead-idempotency-v1.php`: active server support.

Presentation may improve; changing the sending mechanism or external contract requires explicit approval.

### Visual regression ownership
- `.github/workflows/visual-v2-baseline.yml` is the durable deterministic five-viewport owner for initial search, dates, guests, advanced filters, populated results, selected-tour checkout and zero-result recovery.
- It also asserts conversion CTA/confidence copy, checkout structure/stages and recovery actions.
- The broader selected-tour workflow remains because it covers lead/error/trust states not fully equivalent to the baseline.
- Do not create a new visual gate for a state already represented by the baseline.

## Roadmap status

- B1 Visual foundation — **DONE**
- B2 Search Experience 2.0 — **DONE**
- B3 Results Experience 2.0 — **DONE**
- B4 Tour / Checkout Experience 2.0 — **DONE**
- B5 Trust & Conversion UX — **DONE**
- B6 Visual regression baseline — **DONE**
- B7 Performance & Visual Stability — **IN PROGRESS**
- B8 Live Product Optimization — **WAITING FOR TRAFFIC**
- A8 Operational live traffic feedback loop — **WAITING FOR TRAFFIC**

Other A-series technical/product milestones are complete except A1, which is superseded by B6.

## B7 — Performance & Visual Stability

Objective: reduce client overhead, cascade complexity and layout instability without changing product behavior or weakening the B6 contract.

### Completed evidence-backed work

- PR #20 moved `flight-choice-summary` presentation out of JS inline styles into checkout CSS; merged as `6d504a3448cf2f4f61b059631c625f7402ec09f1`, production visual checks passed.
- PR #21 consolidated checkout brand rules and removed one blocking stylesheet request; merged as `048a033a73e3247f390945cdd3e3659ce253ab9e`.
- PR #22 consolidated results brand rules and removed another stylesheet request; merged as `68f0902a491fda91c4768da193c5b42a0c2df1eb`, production deploy/post-deploy/baseline passed.
- PR #23 hardened only idempotent live-tour reads against curl timeouts while leaving non-idempotent `search_start` unretried; merged as `92de5263fc4eb09dd8fbba4c6b8aaa6916a05868`.
- Diagnostic PR #25 proved a selected-tour image-driven 380 px checkout shift at 768/1024/1440. Product PR #26 reserved space only for real `.selected-picture.checkout-picture img` at >=701 px; merged as `f8b5afd8fe43f0c7025e221a06c8ba90b1af22fb` and production visual evidence passed.
- A static audit of the 24 active JS references found distinct responsibilities and no evidence-backed reason to bundle them solely by request count.
- PR #29 changed `mobile-search-summary-v1.js` so mobile DOM/listeners initialize only at <=700 px, including safe later desktop→mobile initialization. Merged as `6a5dec89b4e365326dede1c26c85d11f22098df6`; production post-deploy visual run `33081786159` passed.
- PR #30 changed `mobile-results-filters-v1.js` so the heavy mobile filter bar/sheet/handlers initialize only at <=760 px while lightweight listeners retain result data for desktop→mobile resize. Merged as `c0b1ab652db744b99801f12eb3e25bfe929e83c7`; production post-deploy run `33085353476` and main baseline run `33085353432` passed.
- PR #32 stopped creating hidden stale-results and conversion-confidence result DOM before those states exist. All six PR gates passed; merged as `a40ec76212cead4bada397cf267e9294fc9e53d3`.
- PR #33 removed the empty initial document-wide `accessibility.js` decoration scan while preserving initial ARIA/visibility setup and event-driven decoration when hotel/room/lead elements actually exist. All six PR gates passed; merged as `6dbc441952a72fa57fcb7c59643d33322acaf7b5`. Production verification was still running when this state was updated.

### Current audit observations

- Result hotel images already reserve height/min-height and use lazy loading.
- Selected-tour desktop image CLS is fixed and baseline-protected.
- Mobile-only UX modules should not build heavy DOM on desktop; the two confirmed cases are now gated.
- Hidden result-state helpers should not allocate UI until the related state exists; confirmed cases are now lazy.
- `accessibility.js` no longer dynamically bootstraps UX scripts and no longer performs a pointless empty initial decoration scan.
- `lead-form-guard-v1.js` remains a startup-audit candidate: it currently installs a `MutationObserver` on the initially empty hidden selected-tour container and schedules work before any tour is selected. This must **not** be changed until every direct and programmatic tour-selection/error path is proven to install observation before the first selected-tour mutation.
- No current evidence justifies JS bundling by file/request count alone.

## Exact next work order

1. Verify production deploy, live checks, post-deploy visual and main baseline for merged PR #33 (`6dbc441952a72fa57fcb7c59643d33322acaf7b5`).
2. Audit all selected-tour entry paths around `tour-controller-v4.js` and `lead-form-guard-v1.js`; only defer the always-on selected-tour observer if error/retry coverage can be preserved before the first mutation.
3. Continue startup/DOM/runtime measurement for other always-on modules; make only evidence-backed behavior-preserving changes.
4. Continue CSS ownership consolidation only where original cascade order can be preserved exactly and B6 screenshots remain unchanged.
5. Keep live Tourvisor/tour-flight validation green whenever search/tour/flight surfaces are touched.
6. Activate A8/B8 immediately when real advertising traffic appears.

## Guardrails carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not modify neighboring projects.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
- CI green alone is not DONE: require relevant functional, production and visual evidence when applicable.
