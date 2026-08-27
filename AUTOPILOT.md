# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and boundaries; `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current product phase

**Phase: product UX/visual development before and during live traffic.**

The active product is `v2/`. The major technical refactor, correctness hardening, SEO foundation and residual pre-traffic cleanup are complete. B1-B5 product redesign/conversion work is now production-green. Development is moving into visual-regression hardening and then performance/visual-stability cleanup.

Current primary task: **B6 — Visual Regression Baseline (`IN PROGRESS`)**.

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
- `flight-price-sync-v1.js`: selected flight and displayed/submitted price synchronization.

### Lead path — protected transport contract
- `lead-search-context.js`: search context included with the lead.
- `lead-form-guard-v1.js`: lead-entry, validation, recovery, dedup/success presentation.
- `lead-adapter-v2.php`, `lead-price-v1.php`, `lead-idempotency-v1.php`: active server lead support.

Presentation/placement/CTA UX may improve; the mechanism/external contract that sends the lead must not change without explicit approval.

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
- PR #14 removed the zero-result dead end: search parameters remain intact and the user can explicitly return to editing or open filters; a dedicated five-viewport recovery gate passed; merged as `fceaf4a1b049400031f7f9585b0b83155d1d6c0d`; production post-deploy visual run `33070022638` passed.

### B6 — VISUAL REGRESSION BASELINE — `IN PROGRESS`
Objective: lock the redesigned journey into a durable visual safety net.

Current evidence:
- `visual-v2-pr.yml` already gates initial search, dates, guests, advanced filters and populated results across 375/430/768/1024/1440;
- selected-tour, checkout, conversion and recovery states have dedicated visual workflows;
- `visual-v2-baseline.yml` already exists, but currently captures only initial/filters/children as evidence and has no durable baseline comparison.

Next work:
1. Keep `visual-v2-baseline.yml` as the single B6 baseline owner rather than create another harness.
2. Expand baseline state coverage to populated results, selected tour/checkout and recovery at all five viewports.
3. Establish a durable comparison strategy once the state set is deterministic enough for stable snapshots.
4. Consolidate specialized visual workflows only after the baseline proves equivalent coverage; do not remove gates prematurely.

### B7 — PERFORMANCE & VISUAL STABILITY — `QUEUED`
After visual behavior is locked: consolidate CSS ownership/tokens, reduce cascade complexity and layout shifts, optimize image loading/client overhead, and retain responsive stability.

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
- **A1 baseline harness — DEFERRED / superseded by B6**.
- **A8 live traffic feedback — WAITING FOR TRAFFIC**.

## Next work order

1. B6: expand the existing baseline owner to the complete redesigned journey and make its state fixtures deterministic.
2. Add durable comparison only after deterministic state capture is proven; avoid snapshot noise.
3. Consolidate redundant specialized visual gates only with equivalent baseline evidence.
4. B7: CSS/performance/CLS consolidation after B6 locks the visual contract.
5. Activate A8/B8 immediately when real advertising traffic appears.

## Hard boundaries carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deployment is V2 scope only unless explicitly extending this repository/project surface.
- Do not modify neighboring projects.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
