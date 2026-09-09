# Search3 presentation sources

Start with the [AnyTour development entrypoint](../../docs/project/anytour-development.md)
for lane ownership, coordination and the current queue. Follow [AGENTS.md](AGENTS.md)
and the [repository rules](../../AGENTS.md). `AUTOPILOT_STATE.json.current_task`
is the resume point; this README maps implementation, not priorities or release status.

## Current source owners

Paths below are relative to the repository root. Source bodies and
[manifest.json](manifest.json) determine what ships; a retained filename or donor
comment does not establish an active owner.

| Concern | Current owner |
| --- | --- |
| Native form markup, date/night/party fields and page composition | `v2/index.php`; defaults in `v2/form-defaults.php`, catalogs in `v2/catalogs-v2.js` |
| Search3 result busy state and edit/focus glue | `src/search3/behavior/search-form.js` |
| Loaded hotel name/category/meal filtering and renderer projection | `src/search3/behavior/results/local-hotel-filter.js` with `v2/results-renderer-v5.js` |
| Native selected-tour lead handoff, large flight-list disclosure and display corrections | `src/search3/behavior/summary-cta.js`; canonical selected markup/state remains in `v2/tour-controller-v4.js` |
| Search form layout and native controls | `src/search3/styles/entry-native-controls.css` |
| Results toolbar/cards, responsive layout, selected tour and lead presentation | `src/search3/styles/results-layout.css` |
| Selected image width bound | `src/search3/styles/selected-tour.css` |
| Search lifecycle, progressive loading and supplier-facing client runtime | Existing `v2/search-lifecycle-v6.js`, `v2/search-continue-v6.js`, `v2/runtime-v3.js` |
| Shared site header/footer and content controls | Existing `v2/site-header-v2.php/.css`, `v2/site-footer-v1.php/.css`, `v2/shared-content-primitives-v1.css` |

The three active Search3 behavior owners are `search-form.js`,
`results/local-hotel-filter.js` and `summary-cta.js`. Other manifest behavior slots
currently preserve provenance; do not restore their old decorators or handlers.
In particular, `search-form/primary-controls.js`, `secondary-controls.js`,
`results/toolbar.js`, `booking-summary.js` and `booking/format.js` are retired slots.
The separate cards CSS/JS and entry JS slots do not supply another implementation.
`results-presentation.js`, the former entry adapter and older split CSS owners
are not current source paths. Historical comments may still name them.

## Build and generated outputs

Run from the repository root; install the pinned build dependencies once:

```sh
npm ci --prefix scripts/build/search3-js --ignore-scripts
python3 scripts/build/search3_assets.py --write
python3 scripts/build/search3_assets.py --check
```

Node 18+ and Python build these files without adding a browser dependency.
Commit source changes, generated outputs and
[`docs/project/search3-production-import.json`](../../docs/project/search3-production-import.json)
together. Preserve its hashes and the existing import checks.
Do not edit generated `v2/search3-*` assets independently.

| Public output under `v2/` | Manifest input under `src/search3/` |
| --- | --- |
| `search3-results-filters-v1.js` | Ordered behavior entries in `manifest.json`, including the three active owners above |
| `search3-results-filters-v1.css` | `styles/results-layout.css` |
| `search3-entry-v1.css` | `styles/entry-native-controls.css` |
| `search3-entry-v1.js` | `behavior/entry-v1.js` — retired slot |
| `search3-results-cards-v2.css` | `styles/result-cards.css` — retired slot |
| `search3-results-cards-v2.js` | `behavior/results-cards-v2.js` — empty overlay slot |
| `search3-selected-flow-v2.css` | `styles/selected-tour.css` — image bound |
| `search3-selected-flow-v2.js` | Empty input list |

Keep all eight public paths, manifest order, IIFE/include boundaries and the
single ordered inclusion in `v2/search3-presentation-v1.php`. Private includes
remain inside their enclosing scope; do not load source modules independently.
`v2/search3-shared-runtime.json` is also generated code, not a replacement source
owner. Shared renderer/controller changes must follow their existing build and
verification contract; coordinate them across lanes before editing.

The pinned JavaScript/CSS printers and optimizers have existing AST, syntax,
source/path and composition guards. Preserve them. Source work requires `--check`,
the import hash check and focused applicable evidence; use the existing source
build and production presentation tests. Follow the current task's CI policy
instead of rerunning historical test lists or rebuilding for documentation.

## Boundaries and evidence

Preserve canonical lifecycle/events, original result items/tour references,
progressive counts, supplier API contracts, price arithmetic, lead delivery and
field mapping, analytics, legacy search and preview isolation. Local facets must
reset/hide when their loaded data is incomplete and must not submit a new search.
Useful visual/functionality growth is allowed; replace superseded rules and
handlers in their current owner rather than adding another presentation layer.

The shared site shell and SEO/path helpers remain outside this source directory;
use the development entrypoint to coordinate changes to mixed-responsibility files.
Source/build checks do not establish physical Safari acceptance or owner approval
of a concrete production release. Documentation alone requires no preview deploy.

Historical evidence explains earlier changes; it is not a queue or instruction to
restore retired owners:

- [Whole-layer retirement](../../docs/project/search3-whole-layer-retirement.json)
- [Native entry and shared compaction](../../docs/project/search3-native-entry-shared-compaction.json)
- [Shared runtime compaction](../../docs/project/search3-shared-runtime-compaction.json)
- [DS2 results and selected-tour restoration](../../docs/project/search3-ds2-restoration-product.json)
- [Flight and meal presentation](../../docs/project/search3-flight-meal-product.json)
- [Local meal facet](../../docs/project/search3-local-meal-facet-product.json)
- [Calendar and acceptance corrections](../../docs/project/search3-calendar-acceptance-corrections.json)
