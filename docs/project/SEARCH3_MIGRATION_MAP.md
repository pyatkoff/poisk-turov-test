# Search3 migration map

Status: active donor-to-production map
Updated: 2026-09-04

## Decision

Search3 is the owner-designated UX/reference target under review, not an
approved production candidate and not a merge candidate. Its useful
interaction and visual decisions must be rebuilt on a fresh `main` inside
canonical owners, while later production safety fixes and protected contracts
remain intact.

Planning evidence:

| Ref | SHA / evidence |
| --- | --- |
| audited `main` | `fa58a0cba6dcfc8624d98c20d64fa06330eae309` |
| `feature/search3-preview` head | `6ce565620becaba8e91d50aff13529b5a52aba37` |
| preview implementation version | `e5baf32f455cdb0aa1a704964f28e5efbebf57ff` |
| merge base at audit | `dcafc3a5` |
| preview visual evidence | run 467 green; 390 / 834 / 1440 captures |
| target comparison status | all eight rows remain `REVIEW`; direct diffs remain for layouts 1, 2, 4, 5, 7 and 8 |

At audit time the branches had more than one thousand main-only commits and
more than five hundred Search3-only commits; a direct tree comparison touched
hundreds of files. Search3 also layers dozens of CSS/JS files, thousands of
`!important` declarations, DOM rearrangement, observers, timers and runtime
styles over the older page. This is good prototype evidence and unsafe
production architecture.

## Highest-priority gaps

1. Search3 QA selects up to several cards until it finds flight variants.
   Empty, error and timeout on the first/cheapest selected tour are therefore
   not release-proven.
2. Continue/review becomes available through a flight-selected event. Without
   that event, lead entry may be unreachable.
3. Lead sending/success/error in preview QA is simulated; the preview lead
   endpoint returns `403`. A green visual run does not prove the protected send
   chain or recovery.
4. The stepper can activate future visual steps without underlying eligibility.
5. Hidden native date/number controls remain potential tab stops; popovers and
   the mobile dialog lack parts of their focus/ARIA lifecycle.
6. Small typography, two H1 elements and hotel/tour count ambiguity reduce
   readability and semantic clarity.
7. Footer copy includes conflicting or unsupported availability, guarantee,
   timing, payment and privacy claims.
8. The preview still references retired `anytour.online` consultant assets;
   current `main` uses `https://app.anytoour.ru/web-consultant/widget.js`.

## Behavior and ownership map

| Search3 donor | Preserve as requirement | Rebuild in canonical owner | Key acceptance |
| --- | --- | --- | --- |
| compact search composition | field hierarchy, summaries, quick choices | search form markup/state/catalog controllers | semantic labels, URL/default parity, keyboard, mobile |
| hotel-level cards | dense comparison and hotel→tour expansion | `v2/results-renderer-v5.js` successor/view owner | partial/final results, missing data, correct counts |
| desktop filter rail | hierarchy and immediate loaded-set feedback | `v2/ds2-results-filters.js` / one facet engine | complete-data facets only; no supplier request |
| mobile filter drawer | mobile presentation of the same facets | mobile view over the same facet state | focus trap/restore, clear/apply truthfulness |
| selected-tour layout | visual summary and back/change actions | `v2/tour-controller-v4.js` and selected-tour view | stale isolation, context preservation |
| direction/flight panels | clear outbound/return comparison | existing room/flight/price owners | atomic round-trip, empty/error/retry |
| booking stepper | progress communication | projection of one checkout state machine | unavailable steps not interactive/active |
| final review | exact context and uncertainty | checkout/review owner | with/without flight; price confidence shown honestly |
| lead lifecycle visuals | editing/pending/success/error presentation | existing checkout + `v2/lead-form-guard-v1.js` / `v2/lead-ui-race-guard-v1.js` | real sender states, pending locks, preserved fields |
| header/footer framing | visual consistency where factual | `v2/site-header-v2.php`, `v2/site-footer-v1.php` | current logo/links/widget; verified claims only |

The exact active asset list and order always come from
`v2/bundle-manifest-v1.php`; filenames above identify current conceptual owners,
not permission to bypass that manifest.

## Do not port

- deploy-time Python/string insertion around `</head>` or other rendered HTML;
- Search3 batch/convergence/final/maket-lock and screenshot-only CSS layers;
- DOM proxy controls that leave the original interactive fields focusable;
- page-wide DOM movers, repeated init timers or CSS injected from JavaScript;
- donor-only `feature/search3-preview:v2/search3-preview-state-simulator.js` or synthetic lifecycle events in runtime;
- a third implementation of local result filtering;
- Search3-specific header/footer or retired consultant integrations;
- old shared/core files merely because they exist on the donor branch;
- broad deletions or file moves in the same PR as behavior migration.

## Target state ownership

The migration may remain in the current flat `v2/` layout initially. Do not
create a framework rewrite. The important boundary is one state/controller
owner per domain, with views for desktop/mobile:

```text
search: editing -> starting -> partial -> complete
                            -> error / retry

booking: tour_loading -> tour_ready -> flight_loading
flight_loading -> flight_selected | flight_unavailable | flight_error
flight_selected | flight_unavailable | flight_error -> review
review -> lead_editing -> submitting -> accepted | error
```

- A new search generation invalidates prior asynchronous writes.
- The stepper reads booking state; it does not own or mutate progress.
- Desktop and mobile views share data and business rules.
- UI controllers use the current integration owners, not Tourvisor directly.
- `flight_unavailable` and `flight_error` retain tour context and allow manager
  fallback without fabricated flight/price data.
- Test-state injection changes a fixture/store, not real analytics/lead events.

## Production fixes that must survive

Fresh `main` includes protections absent from the donor branch, including
selected-tour stale guards, lead-pending interaction locks, room stale guards,
complete-facet enforcement, responsive/touch fixes and the current consultant
integration. Each migration PR must prove these protections still work.

Protected end-to-end lead boundary:

`tour controller -> lead search context -> public bridge -> legacy receiver ->
direct adapter -> idempotency/price helpers -> Bitrix`

Do not infer that editing one public PHP filename preserves this chain. Snapshot
the payload and mapping, then treat the workflow, bridge, receiver, adapter and
helpers as one protected contract.

## Viewport/evidence matrix

Search3 preview evidence covers 390, 834 and 1440 px. Production acceptance must
cover canonical 375, 430, 768, 1024 and 1440 px, plus layout-edge checks at 360,
390, 834, 999, 1000, 1280 and 1366 px when relevant. Evidence must include
keyboard/focus, async error/retry and real controller behavior, not screenshots
alone.

## Migration completion criteria

- all target layouts and remaining review differences are resolved in direct semantic views;
- positive, empty, error, timeout, stale, back/switch and duplicate paths pass;
- a real lead can be attempted with or without a flight through the unchanged
  external contract;
- no Search3 overlay/lock/simulator or retired external asset ships;
- only the canonical bundle/state owners drive the route;
- owner approves the exact candidate, and the candidate passes release gates;
- donor/preview assets are retained until a separate consumer audit proves them
  safe to remove.
