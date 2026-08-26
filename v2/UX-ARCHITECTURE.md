# V2 UX architecture

This file documents the active UX layers for the V2 tour search and the contracts between them. It is intentionally implementation-focused so future UX work can avoid adding another overlapping layer.

## Active search flow

1. `index.php` owns the server-rendered initial state and script order.
2. `catalogs-v2.js` owns Tourvisor catalog hydration and child-age controls.
3. `search-filters-ux-v1.js` owns the primary form presentation for dates, nights, guests, stars, and the additional-filter summary/count/reset.
4. `primary-meal-ux-v1.js` promotes meal selection into the primary form.
5. `search-lifecycle-v6.js` is the single owner of search parameters, validation, search generation/searchId, dirty invalidation, polling, and result loading.
6. `search-progress-ux-v1.js` presents lifecycle progress only; it must not own search state.
7. `results-renderer-v5.js` is the single owner of result rendering and result sort order.
8. `mobile-results-filters-v1.js` owns client-side result filtering on mobile. Its sheet uses draft state and only applies on `Показать`.
9. `search-dirty-ux-v1.js` presents stale/dirty UI only. Lifecycle remains the source of truth.
10. `mobile-search-summary-v1.js` presents compact mobile search context only.
11. `accessibility.js` decorates active UI and currently bootstraps several UX helper scripts. This bootstrap responsibility should be removed in the refactor below.

## State ownership contracts

- Search request state: `search-lifecycle-v6.js` only.
- Search result data: `results-renderer-v5.js` only.
- Additional search-filter count: `search-filters-ux-v1.js` only. Primary stars and meal are not part of this count.
- Mobile result filters: `mobile-results-filters-v1.js` only. These do not mutate Tourvisor search parameters.
- Dirty state: lifecycle decides when the search is dirty; presentation layers only react to emitted events.
- Initial visibility: server markup plus `[hidden]` contract in `index.php`. Results/status/selected-tour must not be visible before JS explicitly opens them.

## Events used as public contracts

- `v2:search-reset`
- `v2:search-dirty`
- `v2:search-started`
- `v2:search-progress`
- `v2:search-complete`
- `v2:search-error`
- `v2:results-rendered`
- `v2:tour-selected`

New UX layers should consume these events instead of reaching into polling/search internals.

## Refactor plan (behavior-preserving)

1. Make all active UX scripts explicit in `index.php`; remove unrelated dynamic script loading from `accessibility.js`.
2. Move inline style injection from JS UX modules into one search UX stylesheet, preserving selectors and visual output.
3. Split `search-filters-ux-v1.js` by responsibility internally: primary controls, additional-filter summary/reset, panel close behavior. Keep one public facade until regression coverage exists.
4. Consolidate repeated mobile control tokens (44/48 px targets, radii, focus states, sheet spacing) into the existing brand/design CSS layer instead of per-module styles.
5. Keep result filtering separate from search filtering, but document naming clearly as `search filters` vs `result filters` to avoid accidental state coupling.
6. After each structural change: active V2 contract, isolation, live Tourvisor, then deploy. Do not combine behavior changes with refactor commits.

## Guardrails

- No production behavior change in a refactor commit.
- No new global state owner if an existing owner already exists.
- No new dynamically loaded UX script unless it is genuinely optional/lazy.
- No CSS override layer added just to fix an existing override layer; consolidate instead.
- Mobile-first UX remains the priority, but desktop behavior must stay regression-safe.
