# DS2 Search 2.0 — narrow release inventory

Purpose: define the smallest dependency-safe production release of the already accepted Search 2.0 lane from `feature/search-core` onto current `main`. This is an inventory/guardrail document only; it does not change runtime behavior.

## Canonical visual owner

- `v2/ds2-search.css` is the single canonical DS2 visual layer for the Search 2.0 lane.
- `v2/search-redesign-v2.js` owns the approved Search 2.0 DOM/presentation wiring.
- Do not restore deleted visual layers or add a parallel search stylesheet.

## Required Search 2.0 runtime pieces not currently present on main

- `v2/results-depth-v1.js` — progressive depth: initial 25 results, then fetch up to 100 for the same `searchId` only after the initial search completes; aborts stale/dirty/pending expansion.
- `v2/results-local-filters-v1.js` — local narrowing for stars/rating/price/region/subregion from the already loaded result payload when current filters are equal to or narrower than the search snapshot.
- `v2/ds2-results-filters.js` — DS2 desktop filter rail over the current loaded result set.
- `v2/search-redesign-v2.js` — approved Search 2.0 shell/results presentation wiring.
- `v2/ds2-search.css` — approved canonical visual layer.

These modules must be integrated with current-main `v2/index.php` and `v2/bundle-manifest-v1.php`; do not copy the feature manifest wholesale.

## Main behavior that must win over older feature code

`main` already has a newer `v2/results-filter-autorefresh-v1.js` (version 3) than `feature/search-core` (version 1). The main version adds important protections that must be preserved:

- desktop-only server autorefresh (`min-width: 761px`);
- avoids duplicate change handling for number/text/search/range controls;
- explicit `.search-filters-reset` server refresh behavior;
- cancels pending timers when desktop mode is left;
- version 3 contract.

Therefore **do not copy feature `results-filter-autorefresh-v1.js` over main**. Search 2.0 integration must compose local narrowing with main v3 server refresh, using server submit only when the local module reports the change is outside the original search bounds / requires widening.

## Search behavior invariants

- First usable results remain progressive; depth expansion must not start a new Tourvisor search.
- Simple narrowing over already loaded normalized results must not restart Tourvisor.
- Widening beyond original search bounds must use the existing `V2SearchLifecycle.submit()` lifecycle rather than direct/new transport.
- Existing result renderer, selected-tour, flight-price/fuel, comparison and lead contracts on current main remain authoritative where newer than feature.
- No Metrika/goals, Tourvisor API contract, lead persistence/transport/field mapping, AnyTour logo, legal/payment destinations or other projects may change in this release.

## Narrow release assembly order

1. Rebase/assemble from fresh `main`, never merge `feature/search-core` wholesale.
2. Add the four missing Search 2.0 runtime/visual modules above plus canonical `ds2-search.css`.
3. Adapt current-main `index.php` and `bundle-manifest-v1.php` minimally, preserving all newer main runtime modules and ordering constraints.
4. Keep current-main `results-filter-autorefresh-v1.js` v3; add an integration guard only if targeted tests prove local narrowing and v3 autorefresh still compete.
5. Run Fast CI, Security guard, bundle/owner policy, Search 2.0 behavior QA, and visual QA at 375/430/768/1024/1440.
6. Verify: 25→100 expansion, local narrowing without server search, widening with one normal server lifecycle, empty/recovery, first hotel card, tour variant, selected-tour → back, flight/fuel/price, and lead regression.
7. Merge/deploy only after all required checks are green and production smoke is clean.

## Separate footer blocker

The factual DS2 footer cleanup is not part of the Search 2.0 release. Real component QA confirmed the footer's social targets are 34 px and app-store targets 30 px; the accepted mobile requirement is at least 44 px at 375/430. Fix that in canonical `v2/ds2-search.css` under the footer task boundary before merging footer cleanup.
