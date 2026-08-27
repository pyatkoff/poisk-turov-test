# poisk-turov-test — Autopilot State

Updated: 2026-08-27

This file is the operational companion to `AGENTS.md`. `AGENTS.md` defines authority and hard boundaries; `AUTOPILOT_STATE.json` is the exact machine-readable resume point.

## Current product phase

**Conversion UX 3.0 C1–C6 is production-green. C7/B8/A8 wait for meaningful real traffic evidence.**

The active product is `v2/`. Search/results/selected-tour/lead/flight conversion passes have been completed and verified at the durable 375 / 430 / 768 / 1024 / 1440 viewport contract. Real advertising/live-user evidence now outranks speculative polish. A real-browser performance signal was found and fixed in PR #72; PR #74 now provides a privacy-safe recent 30-minute browser window so the effect can be judged only from fresh post-deploy sessions. Conversion changes still wait for a larger funnel sample.

Protected without explicit approval: Yandex Metrika configuration/goals and the existing lead-sending mechanism/external contract. Work stays inside `pyatkoff/poisk-turov-test`; production deployment stays V2-only.

## Product quality priorities

1. Production breakage, lead loss and incorrect data are highest severity.
2. UX is a primary product requirement, including recovery from transient API/network failures.
3. Visual/responsive stability is high priority.
4. User-facing changes are verified at 375, 430, 768, 1024 and 1440 px.
5. Prefer complete user-journey improvements over isolated features.
6. Preserve analytics, Yandex Metrika and lead transport contracts.
7. Do not manufacture micro-work while waiting for traffic; require evidence for further optimization.
8. When an audit disproves a suspected improvement, record/defer it rather than forcing a change.

## Active architecture

### Search / Tourvisor
- `v2/api-v2.php`: active Tourvisor gateway.
- `catalog-cache-v1.php`: catalog TTL cache.
- `catalogs-v2.js`: critical departures/countries bootstrap eagerly with retry/stale-filter clearing; advanced destination/operator/type/service/meal catalogs load only when `Фильтры отдыха` is opened, reducing the initial request burst while preserving the public refresh helper.
- `search-lifecycle-v6.js`: sole search state/start/status/results/dirty owner.
- `search-progress-ux-v1.js`: waiting/progress/error/zero-result presentation.
- `results-renderer-v5.js`: result rendering and sorting.
- `search-continue-v6.js`: explicit continuation with recoverable retry.
- Mobile/result-state UX: `mobile-results-filters-v1.js`, `search-dirty-ux-v1.js`, `mobile-search-summary-v1.js`.

### Selected tour / checkout
- `tour-controller-v4.js`: selected-tour, automatic flight loading, flight selection, lead-form controller and stale-response guards.
- `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-description-v1.js`: detail presentation.
- `checkout-experience-v1.js/.css`: checkout hierarchy and progressive disclosure.
- `flight-price-sync-v1.js`: selected-flight/displayed/submitted price synchronization.

### Lead path — protected transport contract
- `lead-search-context.js`: search context included with leads.
- `lead-form-guard-v1.js`: lead-entry validation/recovery/dedup/success UX.
- `lead-adapter-v2.php`, `lead-price-v1.php`, `lead-idempotency-v1.php`: active server support.

Presentation/recovery may improve; changing the sending mechanism or external contract requires explicit approval.

### Live production evidence
- `.github/workflows/audit-v2-live-traffic.yml` is the privacy-safe hourly rolling-tail evidence source.
- `.github/workflows/audit-v2-recent-browser.yml`, added in PR #74, is the privacy-safe recent 30-minute browser-only evidence source for post-deploy attribution. It reports aggregated browser request/status/action/path counts, 4xx/5xx and rate-limit delays without emitting IPs or query values.
- PRs #63–#65 separated headless CI from real browsers and attributed 4xx/5xx by actor.
- PRs #67–#71 added nginx severity/family classification, sanitized signatures, safe path/action attribution and actor correlation without emitting IPs, query strings or raw user payloads.
- That evidence found 106 real-browser nginx rate-limit delays in the sampled rolling tail. Hidden startup catalogs were materially affected: `hotel_types` 12/18 browser requests delayed, `operators` 12/18 and `meals` 8/14.
- PR #72 therefore kept critical departures/countries eager and moved advanced catalogs behind opening `Фильтры отдыха`. It passed all six PR gates, V2 deploy `33117246904`, live search smoke, post-deploy visual `33117341554` and baseline `33117341548`.
- The first validated PR #74 recent-window run at 2026-08-27 23:29 +02 saw 0 real-browser V2 requests, 0 browser 4xx/5xx and 0 browser rate-limit delays. This proves the recent window works but provides no traffic sample yet; do not claim a PR #72 performance improvement until a non-zero recent browser window appears.
- Nginx also reports `Too many levels of symbolic links` for global `/images/logo.svg`, `/images/pay_icons.png` and `/images/mir-logo-h229px.png`. Only six sampled events mapped to real browsers and access responses remain HTTP 200. Those global assets are outside the allowed V2/repository write scope, so this is explicitly deferred unless user impact is confirmed or a safe V2-local asset source becomes available.

### Visual regression ownership
- `.github/workflows/visual-v2-baseline.yml` is the durable deterministic five-viewport owner for initial search, dates, guests, advanced filters, populated results, selected-tour checkout and zero-result recovery.
- The selected-tour workflow remains because it covers lead/error/trust states not fully equivalent to the baseline.
- Do not create a new visual gate for a state already represented by an existing owner.

## Conversion UX 3.0 status

- C1 Search Experience 3.0 — **DONE**. PRs #46/#48 simplified the first decision path and compacted mobile; #50 aligned the active contract.
- C2 Results Experience 3.0 — **DONE**. PR #49 made hotel comparison primary with one representative tour and progressive disclosure; #52 aligned the post-deploy visual contract.
- C3 Selected Tour Experience 3.0 — **DONE**. PR #51 keeps core facts first and secondary facts behind disclosure.
- C4 Lead Experience 3.0 — **DONE**. PR #53 made the form phone-first; #55 removed duplicated trust copy without changing lead transport.
- C5 Flight Friction — **DONE**. PR #54 measured fresh production flight latency (~739–1060 ms, median ~868 ms); PR #56 automatically loads flights after tour selection while preserving explicit retry/default-flight event/price and lead synchronization. V2 deploy/live/post-deploy/baseline passed.
- C6 Visual Refinement — **DONE**. Fresh five-viewport evidence confirmed nested flight-section chrome; PR #57 removed only the redundant outer flight card while keeping individual variants/recovery. Deploy `33101438316`, post-deploy visual `33101538683` and baseline `33101538761` passed. A selected-tour audit then found a real mobile regression: the C1 sticky search CTA could remain over checkout after tour selection, including programmatic selection. PR #59 suppresses the sticky on direct-tour selection and `v2:tour-selected`, adds a selected-tour visual assertion, and passed V2 deploy `33101901865`, post-deploy visual `33102012154` and baseline `33102012178`. Fresh selected-tour evidence still showed the reassurance block carrying unnecessary nested-card visual weight; PR #61 compacted it into a lightweight reassurance strip while preserving the existing trust semantics. PR #61 is production-green: deploy `33102452656`, active contract `33102452612`, tour live `33102452599`, result-detail live `33102452609`, security `33102452634`, post-deploy visual `33102554997` and baseline `33102554873` passed. The analogous results panel remains intentionally retained because it improves hotel-vs-tour hierarchy on desktop.
- C7 Live Conversion Optimization — **WAITING_FOR_TRAFFIC**. The cumulative real-browser funnel remains only 2 `search_start` and 1 `tour`; the first fresh 30-minute browser window contains 0 requests. This is insufficient for conversion changes. Live-derived performance work is allowed when a concrete production signal exists, as demonstrated by PR #72.

See `CONVERSION_UX_3_ROADMAP.md` for the stage-level record.

## Earlier roadmap status

- B1 Visual foundation — **DONE**
- B2 Search Experience 2.0 — **DONE**
- B3 Results Experience 2.0 — **DONE**
- B4 Tour / Checkout Experience 2.0 — **DONE**
- B5 Trust & Conversion UX — **DONE**
- B6 Visual regression baseline — **DONE**
- B7 Performance & Visual Stability — **DONE**
- B8 Live Product Optimization — **WAITING_FOR_TRAFFIC**
- A8 Operational live traffic feedback loop — **WAITING_FOR_TRAFFIC**

Other A-series technical/product milestones are complete except A1, superseded by B6.

## Evidence retained from B7

B7 closed after evidence-backed performance/recovery work and a whole-flow audit. Important retained decisions:
- safe live-read timeout retry does not auto-retry non-idempotent `search_start`;
- selected-tour desktop image space is reserved for CLS stability;
- mobile-only/deferred DOM work initializes lazily where safe;
- hot repeated price formatters were cached where evidence justified it;
- diagnostic PR #40 disproved progressive-results dedup as useful in its live sample;
- PR #42 added in-place selected-tour retry;
- PR #43 fixed critical catalog bootstrap/change recovery;
- `search-continue-v6.js`, hotel details and room details already expose recovery paths;
- selected-tour MutationObserver, dynamic catalog observation and small formatter/matchMedia micro-optimizations remain retained/deferred unless real profiling evidence changes priority.

## Exact next work order

1. On every autonomous run inspect fresh `main`, open PRs, production deploy/live/security/visual results and the current V2 journey.
2. Inspect `.github/workflows/audit-v2-recent-browser.yml` first. Require a **non-zero recent 30-minute real-browser sample** before judging whether advanced startup catalog actions/rate-limit delays disappeared after `06edf5905a9290698a413efd11e1606a86a74a2c`. Use the rolling audit only as historical/context evidence.
3. Re-audit search → waiting/progress → stale/zero results → results/comparison → selected tour → rooms/details → flights/price → lead entry/recovery, including mobile and desktop.
4. If production breakage, lead risk, incorrect data, UX friction or responsive regression is confirmed, fix it immediately and verify through relevant contracts/live/visual gates.
5. If meaningful live traffic/funnel evidence is available, activate C7 + B8 + A8 together and prioritize observed friction from `search_started → search_complete → tour_selected → flight_selected → lead_started → lead_submitted`.
6. If production is healthy and there is no real evidence, keep V2 stable; do not create speculative visual/performance changes. Do not change the global `/images` symlink layer from this repository.

## Guardrails carried forward

- Work only inside `pyatkoff/poisk-turov-test`.
- Production deploy scope is V2 only.
- Do not modify neighboring projects, global site assets or server configuration outside the allowed V2 deployment scope.
- Do not change Yandex Metrika configuration or goals without explicit approval.
- Do not change the existing lead-sending mechanism/external contract without explicit approval.
- If one item is blocked, record/defer it and continue independent safe work.
- CI green alone is not DONE: require relevant functional, production and visual evidence when applicable.
