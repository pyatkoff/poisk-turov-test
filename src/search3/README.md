# Search3 presentation sources

Edit this directory, then run from the repository root:

```sh
npm ci --prefix scripts/build/search3-js --ignore-scripts
python3 scripts/build/search3_assets.py --write
python3 scripts/build/search3_assets.py --check
```

Commit source changes, generated `v2/search3-*` assets and the updated
`docs/project/search3-production-import.json` together. CI rejects stale bundles,
unlisted modules and missing source files. Python assembles the private modules;
Node 18+ prints assets using pinned build-only Terser, Acorn and CSS Tree dependencies.
Install them once with the command above. The build itself makes no network requests.
These tools are outside the site payload and add no browser dependency or request.

The first JavaScript printing stage disables compression and name mangling. It retains all source
comments, quoted property keys and number spelling. An independent Acorn parse
compares every statement, operator, name, value, directive, template raw string and
ordered comment before any output is written. Only source positions, literal
spelling and the safe `{name}` / `{name:name}` notation are normalized; `__proto__`
is excluded from that shorthand equivalence. Syntax/comment changes fail closed,
including otherwise harmless empty-statement removal or template raw rewrites.
Non-shrinking assets keep their original bytes. No transpilation is performed.

A second build-only Terser stage shortens local variable/parameter and label names.
The second stage also applies standard control-flow compression with unsafe
arithmetic, pure-getter assumptions and cross-statement sequence merging disabled.
Global/top-level names, property keys, function/class names, argument arity and
scopes using direct eval are preserved. This intentional renaming is outside the first
stage's exact-name AST equality; execution/closure/eval/name cases and the existing
compiled presentation checks cover it. The final output is parsed before writes.
The second stage omits ordinary source comments from served JavaScript. Readable
modules and first-stage comment equality remain intact; license/copyright,
exclamation, preservation and source URL/map notices stay in the output.
The second stage also selects shorter quote and number spellings, omits optional
property-key quotes and avoids optional IIFE wrapping. These are output formatting
choices; the exact first-stage print and public property names remain unchanged.

The CSS build replaces private source comments with empty comment separators;
license/copyright/source-map notes, strings and escapes remain. The output also
omits horizontal indentation after ordinary newlines, retaining the newline as
a token separator. Whitespace within strings/comments and after a newline
consumed by an escape stays intact. Source formatting remains readable.
The linked CSS files then omit formatting around blocks and declarations.
CSS Tree 3.2.1 preserves exact source slices for selectors, at-rule conditions and
declaration values; it does not optimize values, combine rules or alter nesting.
A full ordered AST roundtrip must match. License comments stay in place; assets
with non-exclamation copyright/license/source-map notes retain their entire bytes.
After exact CSS printing, the pinned Lightning CSS optimizer reduces rules and
values while retaining native nesting and the existing browser targets. Raw token
values containing comments bypass optimization so separators cannot disappear.
Before that optimizer, adjacent identical single-selector parents can share one
wrapper across media blocks. Only declaration-free parents with explicit `&`
children qualify. Selector paths, media conditions, declaration order and style
nesting depth stay intact; comments in discarded wrappers prevent grouping.
Nested media uses the same [WebKit nesting support](https://webkit.org/blog/13813/try-css-nesting-today-in-safari-technology-preview/)
as the existing preview. Readable source structure remains unchanged.
Private injected CSS support uses this same pipeline before JavaScript escaping.
Both historical Search3 style injectors are now retired: current linked selected
and review owners supply their live rules. Synthetic builder fixtures retain the
escaping, invalid-path and invalid-CSS guards without shipping a style injector.
JavaScript concatenation
expands private full-line `/* @include behavior/path.js */` markers in place.
Included functions retain their original enclosing IIFE, declaration order and
shared state. There is no runtime loader, new global or additional request.
Source comments and donor markers stay available for maintenance.

`manifest.json` records the exact concatenation order. Each behavior module retains
its original IIFE scope. CSS modules retain the existing cascade order. The nine
historical `styles/cascade/` modules and `visual-compatibility.css` now retain only
provenance markers after the owner-authorized whole-layer retirement. Their needed
lead lifecycle, hidden-state and tour-grid rules live in the current `lead-state`,
`tour-detail-convergence`, `results-context` and `hotel-packages` owners.
The ten-layer retirement and its verification scope are recorded in
`docs/project/search3-whole-layer-retirement.json`.
Do not sort the manifest or load modules
independently in the browser. Static style strings in behavior modules also retain
their original insertion order. Private CSS sources can be compiled into those
same insertion points; moving them into earlier linked stylesheets requires
separate cascade evidence.

## Where to make changes

| Concern | Source |
| --- | --- |
| Primary form and field placement | `behavior/search-form.js` |
| Responsive entry and existing price-calendar adapter | `behavior/search-form/entry-presentation.js` inside `behavior/search-form.js`, `styles/entry-v1.css` |
| Desktop local result-filter rail | `behavior/filter-rail.js`, `styles/results-layout.css` |
| Mobile toolbar shell and native sort proxy | `behavior/results-presentation.js`, `styles/mobile-results-toolbar.css` |
| Canonical mobile filter bar and sheet | Existing `v2/mobile-results-filters-v1.js`; Search3 reuses `.mrf-bar` and `.mrf-sheet`, not a second drawer |
| Results header and summary | `behavior/results-presentation.js`, `styles/results-layout.css`, `styles/entry-v1.css` |
| Hotel cards and disclosure | `behavior/results-presentation.js`, `behavior/results-cards-v2.js`, `styles/results-cards-v2.css` |
| Selected tour and mobile action | `behavior/selected-flow-v2.js`, `styles/selected-flow-v2.css` |
| Flight labels and display-only price parsing | `behavior/booking/format.js` inside `booking-summary.js`, `behavior/flight-price-presentation.js` |
| Summary and handoff | `behavior/booking-summary.js`, `behavior/results-presentation.js` |
| Selected services and tourists | `behavior/booking/services.js`, inside the booking summary owner |
| Final review actions and responsive layout | `behavior/summary-cta.js`, `styles/review-layout.css`; `styles/review.css` is retired |
| Lead heading and contact note | `behavior/lead/note.js`, inside the summary CTA owner |
| Lead entry and lifecycle presentation | `behavior/summary-cta.js`, `behavior/lead-flow.js`, `styles/lead-state.css` |
| Selected price, fallback and disclosure adapter | `behavior/selected-flow-v2.js`, `styles/selected-flow-v2.css` |
| Accepted isolation/readability/hidden contracts | Current `results-layout.css`, `results-cards-v2.css`, `mobile-results-toolbar.css`, `tour-detail.css` and `selected-flow-v2.css` owners; `acceptance-guards.css` is retired |

## Smaller source owners

Repeated complete descendant prefixes inside all four stylesheets now use
one additional single-selector parent, with at most three nesting levels. Each
child retains one explicit leading `&`; a bare `&` targets the unchanged parent.
Parents contain only rules, never declarations or at-rules. Recursive expansion
reproduces the complete ordered selector/declaration/media stream. The audit is
`docs/project/search3-css-descendant-results.json` and
`docs/project/search3-css-descendant-secondary.json`. Public paths and the existing
native nesting browser boundary remain unchanged.

Consecutive rules across the four stylesheets share one native nesting parent:
`body.search3-candidate` or `html body.search3-candidate`. Every child starts with
an explicit `&`; expanding it restores the original selector. The parents have
one selector each, so specificity stays `(0,1,1)` or `(0,1,2)` plus the child.
Group only adjacent rules in the same source/media context. Keep declarations
and at-rules outside grouping parents; do not combine parents into selector lists.
The selector/declaration/media equivalence audit is
`docs/project/search3-css-nesting-results.json` and
`docs/project/search3-css-nesting-secondary.json`.

This preview uses native CSS nesting, supported by
[Safari 16.5 and later](https://webkit.org/blog/14154/webkit-features-in-safari-16-5/)
and current Chromium/Firefox. Engines without nesting do not support this variant.
Explicit `&` avoids reliance on relaxed type-selector parsing. The
[nesting specificity rule](https://www.w3.org/TR/css-nesting-1/#nest-selector)
explains why each grouping parent must remain a single selector. This does not
replace physical Safari acceptance or the production approval gate.

`behavior/results-presentation.js` keeps its guard, shared state, subscriptions
and public adapter. Its private `results/labels.js`, `results/cards.js` and
`results/toolbar.js` parts own complete function groups. Distinct formatter
contracts remain local. `behavior/selected-flow-v2.js` likewise includes private
`selected/flight-fallback.js` and `selected/flight-disclosure.js` parts; price
helpers and lifecycle remain in the enclosing owner. Both extractions preserve
their compiled IIFE bytes. Regression tests exercise the generated adapters.

Include paths are relative to `src/search3/`, must have the enclosing asset's
extension and must occur exactly once in the build. Cycles, duplicate parts,
outside-root paths and unlisted files fail before output is written. Private
parts belong to their enclosing IIFE and must not be loaded independently.

The large combined CSS sources are split at existing component and breakpoint
boundaries. Hotel packages, card convergence and width compatibility have separate
files; booking summary and stepper, final sections and lead review, desktop review
board and specificity guards, and the phone result layout are separate too.
`results-tablet-layout.css` is now a provenance-only slot; the live compact drawer
belongs to `mobile-results-toolbar.css`, while `results-mobile-layout.css` retains
only phone rules that still win in the accepted collapsed or expanded geometry.
The manifest retains their original cascade positions. These are source modules,
not additional browser requests.

The entry stylesheet is split into calendar, responsive entry, toolbar and native
control owners in the same order: `entry-calendar.css`, `entry-v1.css`,
`entry-toolbar.css`, `entry-native-controls.css`. Their concatenation preserves
source bytes. Adjacent rules with identical declarations share selector lists;
the selector/declaration/media token proof is recorded in
`docs/project/search3-css-build-compaction.json`.

`behavior/summary-cta-styles.js` and its private CSS are retired provenance slots.
Final-review presentation now has one linked owner in `styles/review-layout.css`;
the source/build tests keep the private CSS-string compiler covered with isolated
fixtures, without restoring a runtime style injector.

`styles/selected-tour.css` is also a provenance-only slot. The retained selected
tour shell lives with the current desktop owner in `styles/tour-detail.css`; its
mobile bar and narrow-state rules live in `styles/selected-flow-v2.css`.

Repeated ancestor prefixes in 74 CSS selector lists now use `:is()` for plain
class alternatives with equal specificity. Declarations and media boundaries are
unchanged; do not put alternatives of different specificity into the same group.

The common PHP header/footer remain owned by their existing `v2/site-*` files.
Search3 uses the canonical server-rendered footer. Its inactive client replacement
and the corresponding private footer CSS have been removed.

## Boundaries

The eight public asset paths, PHP inclusion order, API/runtime, price calculation,
lead transport/mapping, analytics and legacy search are unchanged. The initial
extraction reproduced all eight assets byte for byte from release `3624278a`.
These source files live outside `v2/` and are not included in the 715-file preview
payload. Deployment continues to consume the checked-in generated assets.

That initial extraction changed source ownership only. Later reductions and the
owner-authorized whole-layer retirement are separate changes with their own
audits; their verification must not be inferred from the byte-preserving split.

An earlier CSS-only consolidation removed proven duplicate declarations while
preserving the final cascade; see `docs/project/search3-css-deduplication.json`.
The later ten-layer retirement supersedes that audit's description of active
compatibility layers. Public build paths and module order remain unchanged.

## Retired presentation states

CSS for retired hotel focus/advanced controls/highlights and other absent owners
has been removed. Only positive requirements were retired; negative conditions
remain because their specificity and active matching still matter. Another 35
selector lists use equal-specificity compound alternatives in `:is()`; pseudo
elements and unequal-specificity alternatives were excluded. The audit is in
`docs/project/search3-css-owner-retirement.json`.

## Update ownership

`booking-summary.js` coalesces tour/flight/price/layout events into one deferred
update; full render includes layout and consumes the latest values.
Its private `booking/services.js` uses the same selected tour, flight, numeric
formatters and queue. Its private `booking/format.js` owns the text, escaping,
place, party, flight-label and baggage helpers; the standalone presentation-text
and flight-presentation slots retain provenance only. Tour/flight events request both sections; price-only and
lead-success events rebuild only the summary, and stage events keep layout-only
behavior. Services still render if the lead form is absent. The standalone
`final-sections.js` state/listeners are retired. Compiled regressions preserve
eleven settled markup/copy/layout snapshots from the preceding published source.
`summary-cta.js` runs the private lead note in its existing tour/review task;
the former `lead-note.js` listeners are retired. Public CTA methods and other
lead lifecycle events retain their scope.
`results-presentation.js` owns one animation-frame queue for result state. Result
mutations may synchronize search state; resize must remain geometry-only so
an open search editor is preserved. Queued geometry reads current cards, not
an item count captured before reset. Keep these queues local to their owners;
do not add a global scheduler or another observer for the same work.
The visible count and route remain here; the permanently hidden duplicate meta
counter has been retired. Static page intro text belongs only to `search-form.js`
and is not rewritten on results, reset or form-change events.

The retired `results-top.js` slot retains provenance only. Its result header,
route, edit and state lifecycle now shares the current results presentation IIFE
and its existing results/reset subscriptions. The same owner keeps a separate
zero-delay mobile-toolbar mount.
The retired `selected-tour-handoff.js` slot also retains provenance only. Result
button labels, selected-tour busy state and entry focus share the existing
results/reset/tour lifecycle in `results-presentation.js`; canonical return focus
remains in the base runtime.
Progressive-result and compact-breakpoint bursts share one pending task. Reset or
empty results cancel it; a later eligible event can retry a missing canonical
filter bar. Mounting retains the existing toolbar, native sort handoff and control
listeners. This queue does not own filtering or search submission.

Regression adapters: `tests/search3-booking-summary.cjs`,
`tests/search3-results-scheduler.cjs`, `tests/search3-mobile-toolbar-scheduler.cjs`
and `tests/search3-mobile-toolbar-ownership.cjs`, run by the presentation test suite.


A later cascade pass removes earlier declarations only when the same complete
expanded selector list, media/supports context, property and important priority
occur later in the same linked stylesheet. Each deletion records the later
witness in `docs/project/search3-active-css-declarations.json`; existing browser
CI verifies that those later values are supported, so unsupported-value fallbacks
are not silently removed. No shorthand expansion or cross-context merging is used.
Final declaration maps match the prior code; now-empty rules are removed without
reordering retained declarations. Protected acceptance guards remain intact.

Cross-asset duplicate removal also uses the mandatory main/entry/cards/selected
order in `v2/search3-presentation-v1.php`. All four stylesheets share one enabled
gate; the existing presentation test checks their order and single inclusion.
Witnesses in a later linked stylesheet may cover an earlier declaration only
under the same strict selector/media/value rules. This relies on loading the
complete four-file presentation, not the main stylesheet in isolation. Audit:
`docs/project/search3-css-cross-asset-dominance.json`.

`behavior/booking-summary.js` retains its state, formatting, price adapter and
event lifecycle. Private `behavior/booking/layout.js` owns complete layout
functions inside that same IIFE. One local setter preserves the original target,
property, value, important priority and operation order. The existing summary
regression executes the compiled owner and covers all eight layout states.

The desktop `filter-rail.js` keeps shared state, filtering and event handlers.
Private `filter-rail/availability.js` and `filter-rail/render.js` retain complete
function groups in their original IIFE positions. Sea options share one local
markup function. The compiled regression checks exact HTML/data/event traces
against the preceding bundle; no runtime loader or public asset was added.

`search-form.js` retains initialization, field references and the existing form
lifecycle. Its `search-form/primary-controls.js` and `secondary-controls.js` parts
expand at the original positions inside `init()`. Dates, nights, guests, secondary
fields and delayed cleanup keep their shared lexical scope and exact source bytes.
The secondary composition now also owns the single mobile trust/filter entry and
its ARIA toggle. The retired `mobile-search-entry.js` and `result-cards.css` remain
provenance-only manifest slots; current entry/results owners supply their retained
presentation. Legacy form presentation was removed from `base.css`; current form
and guest rules live in `entry-v1.css`.

The booking path uses one summary CTA owner for flight-to-review,
summary-to-lead and lead-to-review transitions instead of separate listeners or
a second clickable progress strip. `flight-continue.js`, `booking-stepper.js`,
`booking-stepper.css` and `review-heading.js` are provenance-only slots; the
selected hotel heading and booking summary retain the accessible review context.

The subsequent results-layer passes retire `styles/results-width-compatibility.css`,
`styles/hotel-card-convergence.css`, `styles/results-context.css` and
`styles/hotel-packages.css`. Current shell, lifecycle, MRF, card and mobile-fact
guards live in the base/results/toolbar/card owners; retired files contain
provenance comments only.
See `docs/project/search3-results-layer-retirement.json` for measured bytes and
checked-versus-published scope.
