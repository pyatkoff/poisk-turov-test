# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and boundaries; `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current product phase

**Phase: product UX/visual development before and during live traffic.**

The active product is `v2/`. The major technical refactor, correctness hardening, SEO foundation and B1-B6 product/visual work are complete. Development is now focused on performance and visual stability while the durable five-viewport visual contract protects the redesigned journey.

Current primary task: **B7 — Performance & Visual Stability (`IN PROGRESS`)**.

Parallel waiting task: **A8/B8 — live traffic feedback loop (`WAITING FOR TRAFFIC`)**. When advertising traffic appears, production evidence immediately outranks speculative polish.

Existing Yandex Metrika configuration/goals and the existing lead-sending mechanism remain protected and must not be changed without explicit approval.

## Product quality priorities

1. Production breakage, lead loss and incorrect data remain highest severity.
2. UX is a primary product priority, not secondary polish.
3. Visual coherence and responsive stability are high-priority requirements.
4. User-facing visual changes are checked at 375, 430, 768, 1024 and 1440 px.
5. Prefer complete user-journey improvements over isolated features.
6. Do not add CSS/workflow layers merely to compensate for existing layers; consolidate ownership when equivalent coverage is proven.
7. Preserve analytics and lead transport contracts.
8. Production deployment remains V2-only and uses the repository deploy workflow.

## Active architecture

### Page / config
- `v2/index.php`: server-rendered page composition, active CSS/JS order and public V2 config.
- `v2/form-defaults.php`: initial search defaults.
- `v2/assets.php` + `v2/asset-version-v1.php`: content-based asset versioning.
- `v2/analytics-config.php`: configured Metrika counter id; read-only for autopilot.
- `v2/privacy-config.php`: privacy URL.

### Search / Tourvisor
- `v2/api-v2.php`: active Tourvisor gateway.
- `v2/catalog-cache-v1.php`: catalog TTL caching.
- `search-lifecycle-v6.js`: start/status/results ownership, validation, generation/searchId state and dirty invalidation.
- `search-progress-ux-v1.js`: waiting/progress/error/zero-result recovery presentation.
- `results-renderer-v5.js`: result rendering, sorting and hotel-vs-tour hierarchy.
- `search-continue-v6.js`: explicit additional-results continuation.
- `mobile-results-filters-v1.js`, `search-dirty-ux-v1.js`, `mobile-search-summary-v1.js`: mobile/result-state UX.

### Selected tour / checkout
- `tour-controller-v4.js`: selected-tour flow and stale-response guards.
- `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-description-v1.js`: detail presentation.
- `checkout-experience-v1.js` / `.css`: selected-tour checkout hierarchy.
- `flight-price-sync-v1.js`: selected flight and displayed/submitted price synchronization. A flight variant price may legitimately differ from the base tour price; the selected variant delta is reflected in the displayed/submitted selection.

### Lead path — protected transport contract
- `lead-search-context.js`: search context included with the lead.
- `lead-form-guard-v1.js`: lead-entry, validation, recovery, dedup/success presentation.
- `lead-adapter-v2.php`, `lead-price-v1.php`, `lead-idempotency-v1.php`: active server lead support.

Presentation/placement/CTA UX may improve; the mechanism/external contract that sends the lead must not change without explicit approval.

### Visual regression ownership
- `.github/workflows/visual-v2-baseline.yml` is the durable deterministic five-viewport owner for initial search, dates, guests, advanced filters, populated results, selected-tour checkout and zero-result recovery.
- It also asserts conversion CTA/confidence copy, checkout structure/stages and recovery actions, then compares PR screenshots with the latest compatible green main baseline.
- The broader selected-tour trust/error workflow remains separate because it still covers lead/error presentation that is not equivalent to the baseline.
- Do not create a new visual gate for a state already represented by the baseline; extend the existing owner instead.

## B-series roadmap

### B1 — VISUAL FOUNDATION — `DONE`
Header/navigation, product shell, branded hero and responsive visual foundation shipped. Production five-viewport visual checks passed.

### B2 — SEARCH EXPERIENCE 2.0 — `DONE`
Search hierarchy, date/guest pickers, child ages, advanced filters and responsive behavior shipped. Production five-viewport checks passed.

### B3 — RESULTS EXPERIENCE 2.0 — `DONE`
Hotel-vs-tour hierarchy, best-offer context, result filtering and populated-result visual coverage shipped. Hotel-level operator regression was fixed in `8c43c98b5e1581517f9a58379c538504ac904d68`; production post-deploy visual run `33064644452` passed.

### B4 — TOUR / CHECKOUT EXPERIENCE 2.0 — `DONE`
Selected tour became a staged checkout summary with clearer facts, flight choice and lead-entry hierarchy while preserving flight/price sync and lead transport. PR #10 merged as `87a10fd10f10af293a591e17ec9e5ec3d96fcb05`; production post-deploy visual run `33066545500` passed. A resulting SEO H1 regression was isolated and fixed in PR #12 as `8161feb64cc0d3e6346641368a35e1d132d879b4`.

### B5 — TRUST & CONVERSION UX — `DONE`
Result:
- PR #11 strengthened tour/flight/lead error recovery, preserved lead data on failure and added explicit no-payment reassurance;
- PR #13 changed the result CTA to `Проверить тур` and explains that flight, baggage and final price are checked before the no-payment contact step; merged as `760d9c8c28c46ce769cd8ebf0a88cbb8bf8403af`; production post-deploy visual run `33069724534` passed;
- PR #14 removed the zero-result dead end: search parameters remain intact and the user can explicitly return to editing or open filters; merged as `fceaf4a1b049400031f7f9585b0b83155d1d6c0d`; production post-deploy visual run `33070022638` passed.

### B6 — VISUAL REGRESSION BASELINE — `DONE`
Result:
- PR #17 extended the existing baseline owner to deterministic initial/dates/guests/advanced/results/checkout/recovery states at 375/430/768/1024/1440 and folded conversion, checkout and recovery semantic assertions into the same owner;
- a comparator false-positive was diagnosed: checkout stage assertions dispatched real product events and changed downstream presentation height; stage checks were isolated via the checkout public stage API, after which the comparator passed with no unintended screenshot changes;
- PR #17 merged as `694d588487fa8fc80be107e4a14e4e6e426d2051`;
- PR #18 retired four now-redundant PR visual workflows only after equivalent baseline coverage and green comparison evidence were proven, while preserving the broader selected-tour trust/error gate; merged as `e5a153ff85269602716472ffdc1bb16b222c80d4`;
- V2 deploy run `33072399106`, production post-deploy visual run `33072469440` and main baseline run `33072469294` passed.

A subsequent `Validate V2 tour live` failure was investigated before moving on. The old validator incorrectly required the selected flight price to always equal the base tour price, although active `flight-price-sync-v1.js` explicitly supports a variant price delta. PR #19 aligned the validator with product semantics, added PR execution for the live validator, and passed a fresh five-tour live sample. It merged as `0a91b36bacc6a7170282a86c01302c3a00e7f3a5`; main live-tour run `33072844638` passed.

### B7 — PERFORMANCE & VISUAL STABILITY — `IN PROGRESS`
Objective: reduce client overhead, CSS ownership/cascade complexity and layout instability without changing product behavior or weakening the B6 visual contract.

Completed evidence-backed work:
- PR #20 moved `flight-choice-summary` presentation out of JS inline-style ownership into the existing checkout stylesheet with identical declarations. Contract/security/general visual/selected-tour/baseline checks passed; merged as `6d504a3448cf2f4f61b059631c625f7402ec09f1`. V2 deploy `33073307610`, post-deploy visual `33073391975` and main baseline `33073391983` passed.
- The active page was statically measured at roughly 22 CSS + 24 JS local V2 references before request consolidation.
- PR #21 concatenated the existing `anytour-checkout-brand.css` rules ahead of `checkout-experience-v1.css` in the same cascade order and removed the redundant stylesheet link. All PR gates, including the durable five-viewport baseline and selected-tour coverage, passed. It merged as `048a033a73e3247f390945cdd3e3659ce253ab9e`; V2 deploy `33074552891` and result-detail live `33074553035` passed.
- PR #22 applied the same cascade-preserving ownership consolidation to `anytour-results-brand.css` + `results-experience-v1.css`, removing a second blocking stylesheet request. PR contract/security/general visual/selected-tour/durable baseline all passed; merged as `68f0902a491fda91c4768da193c5b42a0c2df1eb`. V2 deploy `33074813386`, active contract `33074813410` and live-tour `33074813385` passed; final main post-deploy/baseline evidence was still completing when this state was written.
- Active `v2/index.php` now references 20 CSS + 24 JS local assets (44 CSS/JS resources), down by two CSS requests without intentional visual change.
- A red live-tour run on the first consolidation was traced to a transient `curl` exit 28 on an idempotent status read. The old shell used `set -e`, so it aborted before its own retry/fallback logic. A direct rerun passed. PR #23 made only idempotent `search_status`, `search_results`, `tour` and `flights` reads tolerant of curl-level timeouts while deliberately leaving non-idempotent `search_start` unretried; PR live run `33074794356` passed, it merged as `92de5263fc4eb09dd8fbba4c6b8aaa6916a05868`, and main live run `33074920997` passed.

Current audit observations:
- result hotel images already reserve explicit height/min-height and use lazy loading, so they are a lower CLS risk;
- selected-tour imagery has explicit aspect-ratio reservation on mobile but not yet on desktop; this is a measurement target, not an assumed bug, because source image geometry varies;
- `accessibility.js` no longer dynamically bootstraps UX helper scripts, so older architecture text suggesting that responsibility is stale and should not drive refactoring;
- header logo layout has explicit CSS dimensions, while further intrinsic-size/CLS work should only be done with verified asset geometry;
- after the two request consolidations, the remaining 24 JS references should be audited by responsibility/startup cost before any bundling; request count alone is not sufficient justification for combining behavior modules.

Next work:
1. Complete/record the production post-deploy and main baseline evidence for `68f0902a...`.
2. Measure layout shifts in progressive selected-tour/image states before changing geometry; only reserve desktop image space if evidence shows a meaningful shift and the chosen rule safely handles variable hotel-image ratios.
3. Audit adjacent JS responsibilities and startup cost before considering request consolidation; preserve load order and protected analytics/lead contracts.
4. Continue consolidating repeated CSS ownership only where original cascade order can be preserved exactly and B6 screenshots stay unchanged.

### B8 — LIVE PRODUCT OPTIMIZATION — `WAITING FOR TRAFFIC`
Use real searches/errors/result interactions/tour selections/leads to reprioritize product work. Do not change Metrika goals/config merely for reporting convenience.

## Completed A-series milestones

- **A2 UX journey audit — DONE**: search, stale state, retry clarity, mobile filters, lead completion and room disclosure hardened.
- **A3 visual cascade consolidation — DONE**.
- **A4 conversion readiness — DONE**.
- **A5 live Tourvisor correctness — DONE**: search/continue/tour/rooms/flights/price validated; transient continuation failure was not given an unsafe automatic retry.
- **A6 focused whole-project refactor — DONE**.
- **A7 SEO foundation — DONE**.
- **A9 residual V2 generation/CI cleanup — DONE**.
- **A1 baseline harness — DEFERRED / superseded by completed B6**.
- **A8 live traffic feedback — WAITING FOR TRAFFIC**.

## Next work order

1. B7: finish current production visual evidence, then measure progressive selected-tour CLS rather than guessing geometry.
2. Audit JS responsibility/startup cost and continue only small, behavior-preserving consolidation with B6 baseline comparison.
3. Keep live Tourvisor/tour-flight validation green whenever search/tour/flight surfaces are touched.
4. Activate A8/B8 immediately when real advertising traffic appears.

## Hard boundaries carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deployment is V2 scope only unless explicitly extending this repository/project surface.
- Do not modify neighboring projects.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
