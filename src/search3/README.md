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
| Local result filters and their drawer | `behavior/filter-rail.js`, `styles/filters.css` |
| Results header and summary | `behavior/results-top.js`, `styles/results-context.css` |
| Hotel cards and disclosure | `behavior/results-presentation.js`, `behavior/results-cards-v2.js`, `styles/result-cards.css` |
| Selected tour and mobile action | `behavior/tour-presentation.js`, `behavior/selected-tour-mobile.js` |
| Flight labels and display-only price parsing | `behavior/flight-presentation.js`, `behavior/flight-price-presentation.js` |
| Steps, summary and handoff | `behavior/booking-stepper.js`, `behavior/booking-summary.js`, `behavior/selected-tour-handoff.js` |
| Lead entry and lifecycle presentation | `behavior/summary-cta.js`, `behavior/lead-flow.js`, `styles/lead-state.css` |
| Selected price, fallback and disclosure adapter | `behavior/selected-flow-v2.js`, `styles/selected-flow-v2.css` |
| Accepted isolation/readability/geometry guards | `styles/acceptance-guards.css` |

The common PHP header/footer remain owned by their existing `v2/site-*` files.
`behavior/footer.js` is the preserved compatibility fallback; it must not replace
the canonical server-rendered footer.

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
