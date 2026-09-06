# Search3 presentation sources

Edit this directory, then run from the repository root:

```sh
python3 scripts/build/search3_assets.py --write
python3 scripts/build/search3_assets.py --check
```

Commit source changes, generated `v2/search3-*` assets and the updated
`docs/project/search3-production-import.json` together. CI rejects stale bundles,
unlisted modules and missing source files. The build uses Python's standard library;
it requires no npm installation, transpiler or additional browser requests.

`manifest.json` records the exact concatenation order. Each behavior module retains
its original IIFE scope. CSS modules retain the existing cascade order; compatibility
modules are still active, not dead code. Do not sort the manifest or load modules
independently in the browser. Static style strings in behavior modules also retain
their original insertion order; moving them into styles requires separate cascade
evidence.

## Where to make changes

| Concern | Source |
| --- | --- |
| Primary form and field placement | `behavior/search-form.js`, `behavior/maket7-lock.js` |
| Responsive entry and existing price-calendar adapter | `behavior/entry-v1.js`, `styles/entry-v1.css` |
| Desktop local result-filter rail | `behavior/filter-rail.js`, `styles/filters.css` |
| Mobile toolbar shell and native sort proxy | `behavior/results-presentation.js`, `styles/results-layout.css`, `styles/entry-v1.css` |
| Canonical mobile filter bar and sheet | Existing `v2/mobile-results-filters-v1.js`; Search3 reuses `.mrf-bar` and `.mrf-sheet`, not a second drawer |
| Results header and summary | `behavior/results-top.js`, `styles/results-context.css` |
| Hotel cards and disclosure | `behavior/results-presentation.js`, `behavior/results-cards-v2.js`, `styles/result-cards.css` |
| Selected tour and mobile action | `behavior/tour-presentation.js`, `behavior/selected-tour-mobile.js` |
| Flight labels and display-only price parsing | `behavior/flight-presentation.js`, `behavior/flight-price-presentation.js` |
| Steps, summary and handoff | `behavior/booking-stepper.js`, `behavior/booking-summary.js`, `behavior/selected-tour-handoff.js` |
| Lead entry and lifecycle presentation | `behavior/summary-cta.js`, `behavior/lead-flow.js`, `styles/lead-state.css` |
| Selected price, fallback and disclosure adapter | `behavior/selected-flow-v2.js`, `styles/selected-flow-v2.css` |
| Accepted isolation/readability/geometry guards | `styles/acceptance-guards.css` |

## Smaller source owners

The large combined CSS sources are split at existing component and breakpoint
boundaries. Hotel packages, card convergence and width compatibility have separate
files; booking summary and stepper, final sections and lead review, desktop review
board and specificity guards, and mobile/tablet result layouts are separate too.
The manifest retains their original cascade positions. These are source modules,
not additional browser requests.

`behavior/selected-tour-mobile-styles.js` and `behavior/summary-cta-styles.js`
own the two static style injections immediately before their behavior modules.
Repeated selector prefixes use local constants. The injected CSS text, style IDs,
insertion order and original selected-root guard are preserved. Do not move these
injections into the earlier linked stylesheets without checking cascade order.

Repeated ancestor prefixes in 74 CSS selector lists now use `:is()` for plain
class alternatives with equal specificity. Declarations and media boundaries are
unchanged; do not put alternatives of different specificity into the same group.

The common PHP header/footer remain owned by their existing `v2/site-*` files.
Search3 uses the canonical server-rendered footer. Its inactive client replacement
and the corresponding private footer CSS have been removed.

## Boundaries

The eight public asset paths, PHP inclusion order, API/runtime, price calculation,
lead transport/mapping, analytics and legacy search are unchanged. The initial
extraction reproduces all eight assets byte for byte from release `3624278a`.
These source files live outside `v2/` and are not included in the 715-file preview
payload. Deployment continues to consume the checked-in generated assets.

This is a source-ownership refactor, not a CSS redesign or a performance claim.
Consolidating compatibility declarations or observers requires a separately
verified change; it must not be hidden inside a file move.

A subsequent CSS-only consolidation removes proven earlier duplicate declarations
while preserving the final cascade. Its audit is in
`docs/project/search3-css-deduplication.json`; remaining compatibility rules are
still active. Public build paths and module order are unchanged.

## Shared labels and retired presentation states

`behavior/presentation-labels.js` owns the pure date, meal, room, placement and
Russian plural/party labels. It precedes `results-presentation.js` in the same
public bundle and registers one frozen `Search3CandidateLabelsV1` namespace.
The original `Search3CandidateResultsV1` formatting API delegates to those same
functions. The selected-tour owner reuses the plural function; no new timer,
listener or observer belongs to this helper. The results owner is now 14,504
bytes (previously 17,602), with the 3,470-byte formatter kept separately.

CSS for retired hotel focus/advanced controls/highlights and other absent owners
has been removed. Only positive requirements were retired; negative conditions
remain because their specificity and active matching still matter. Another 35
selector lists use equal-specificity compound alternatives in `:is()`; pseudo
elements and unequal-specificity alternatives were excluded. The audit is in
`docs/project/search3-css-owner-retirement.json`.

## Update ownership

`booking-summary.js` coalesces tour/flight/price/layout events into one deferred
update; full render includes layout and consumes the latest values.
`results-top.js` owns one animation-frame queue for result geometry. Result
mutations may synchronize search state; resize must remain geometry-only so
an open search editor is preserved. Queued geometry reads current cards, not
an item count captured before reset. Keep these queues local to their owners;
do not add a global scheduler or another observer for the same work.

`results-presentation.js` owns a separate existing zero-delay mobile-toolbar mount.
Progressive-result and compact-breakpoint bursts share one pending task. Reset or
empty results cancel it; a later eligible event can retry a missing canonical
filter bar. Mounting retains the existing toolbar, native sort handoff and control
listeners. This queue does not own filtering or search submission.

Regression adapters: `tests/search3-booking-summary.cjs`,
`tests/search3-results-scheduler.cjs`, `tests/search3-mobile-toolbar-scheduler.cjs`
and `tests/search3-mobile-toolbar-ownership.cjs`, run by the presentation test suite.
