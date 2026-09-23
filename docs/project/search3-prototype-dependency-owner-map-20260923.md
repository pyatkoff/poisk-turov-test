# Search3 prototype dependency / owner map — 2026-09-23

Status: current prototype-specific refactor map for `v2/prototype-search/`.
Baseline after packages #3585–#3587: release `dfeb74e2106368b960b643f1122aebe044da4ab4`.

This map is intentionally scoped to the isolated target
`/_preview/search3-local-candidate/prototype-search/`. It does not reopen the
old #2423 technical pass and does not redefine production/main, supplier, INT,
MATCH, lead or analytics contracts.

## Canonical runtime owners

| Concept | Owner | Status | Known consumers / boundary | Primary evidence |
| --- | --- | --- | --- | --- |
| Prototype entry + cache-busting | `v2/prototype-search/index.php` + `index.html` | CANONICAL | Loads the isolated runtime in explicit order; no lifecycle decisions | whole-site preview build/smoke |
| Search UI/domain state | `v2/prototype-search/app.js` | CANONICAL | Search form state, filters, cards, calendar UI, comparison, saved selected-tour UI; delegates async search lifecycle | selected-tour retention, mobile/browser prototype suites |
| Search lifecycle | `v2/prototype-search/search-lifecycle-v1.js` | CANONICAL | One form submit binding, run generation/stale guard, `data.search()` orchestration, response reducer, coalesced real-search request, result-scope refresh | `tests/search3-prototype-search-lifecycle-v1.cjs` |
| Supplier/search request scope | `v2/prototype-search/data.js` | CANONICAL | Shared Tourvisor/direct ANEX/Andromeda/LOCAL params, canonical supplier scope descriptor, continuation, calendar/data reads | prototype inventory lifecycle |
| Canonical hotel/offer merge | `v2/search3-canonical-profiles-v1.js` | CANONICAL shared dependency | Identity/profile merge and offer source union used by prototype data | canonical profile + inventory regressions |
| LOCAL stored offer projection | `v2/search3-local-db-provider-v1.js` | CANONICAL shared dependency | Parse/apply guarded LOCAL snapshot; DB schema/backend are outside this refactor | LOCAL provider/results tests |
| Source/union acceptance receipt | `v2/prototype-search/source-receipt-v1.js` | CANONICAL OBSERVABILITY for current pass | Wraps the frozen data API only to expose sanitized received/mapped/visible/union evidence on `#results`; must not become request-policy owner | source-receipt focused regression |
| Prototype lead presentation | `v2/prototype-search/lead.js` | CANONICAL prototype UI | Binds selected offer to `AnyTourPrototypeData.leadSession()`; external lead transport/mapping stays protected | prototype lead regressions |
| Tour / lead-session compatibility seam | `v2/tour-controller-v4.js` | COMPATIBILITY — REQUIRED | Prototype does not use its legacy selected-tour DOM as the primary UI, but `data.js::leadSession()` directly requires `V2TourController.createLeadSession()`. Do not remove until this protected seam is migrated with equivalent lead-contract evidence. | lifecycle owner test + existing lead tests |

## Retired competing owners

The following temporary post-app owners were introduced as narrow fixes while
`app.js` had no extractable lifecycle boundary. Packages #3585–#3587 made their
behavior canonical elsewhere, so package 4 removes both source files and their
dedicated tests:

- `results-date-refresh-v1.js` — former results-date real-search decision.
  Canonical owner now: `app.js` (`dateContext.source`) +
  `search-lifecycle-v1.js::requestSubmit()`.
- `inventory-scope-refresh-v1.js` — former document observer / URL-derived
  supplier-scope decision. Canonical owner now:
  `search-lifecycle-v1.js` using applied app filters and
  `data.js::supplierScope/supplierScopeCovered/currentSupplierScope`.

Keeping their old focused tests after runtime retirement would create a second
test owner for behavior that no longer exists at those paths, so those tests are
retired with the implementation. Equivalent-or-stronger assertions live in the
canonical lifecycle regression executed by the existing whole-site source gate.

## Responsibilities after the pass

`app.js` must not call `data.search()` directly or bind the search form submit
handler. `search-lifecycle-v1.js` must not implement supplier narrowing rules.
`data.js` must not render the UI or own result-side interaction listeners.
`source-receipt-v1.js` may observe/sanitize events but must not choose suppliers,
filters, pricing or lifecycle transitions.

The remaining structural seam worth considering in a later bounded package is
`source-receipt-v1.js` wrapping `data.search` and the broader
`tour-controller-v4.js` compatibility load. Neither is a deletion candidate
today: receipt behavior has a current acceptance consumer, and the tour
controller still provides the protected `createLeadSession` seam.

## Protected boundaries

This map does not authorize changes to supplier URLs/payload/auth, Tourvisor
external contract, INT price/fuel logic, MATCH identity, DB schema, lead
transport/mapping, Metrika/analytics, production/main, or neighboring projects.
