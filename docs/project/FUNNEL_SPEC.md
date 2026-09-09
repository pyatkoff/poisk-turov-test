# AnyTour funnel specification

Status: **proposal only — no analytics implementation authorization**
Updated: 2026-09-04

This document defines product semantics so design, code, QA and commercial
analysis speak about the same journey. It does not authorize changing Yandex
Metrica, existing goals, event names, counters, consent behavior or lead
transport. Before implementation, inventory the current `v2/analytics-v4.js`
contract and map these logical signals to it in a separate approved PR.

## North-star proposal

`confirmed delivered qualified leads / valid search sessions`

This is intentionally stricter than form-submit clicks. It rewards relevant
traffic, a usable search, retained selection context and confirmed operational
handoff. Qualification and delivery sources must be agreed with sales/CRM
owners before the metric is activated.

Supporting measures:

- valid-search completion rate;
- first useful results latency;
- hotel-open and concrete-tour selection rate;
- selected-tour → review reach rate;
- review → accepted lead rate;
- accepted → confirmed delivered lead rate;
- no-flight/error → lead recovery rate;
- duplicate and delivery-failure rate;
- manager response and booking rate when a reliable downstream source exists.

## Canonical funnel states

| State | Entry condition | Successor | Recovery |
| --- | --- | --- | --- |
| `landing_ready` | usable page and form rendered | `search_editing` | reload/help |
| `search_editing` | required parameters can be entered | `search_starting` | validation guidance |
| `search_starting` | valid request accepted by the search controller | `results_partial` / `results_complete` | retry without losing input |
| `results_partial` | supported normalized results available, search continues | `results_complete` / `hotel_opened` | continue polling/retry |
| `results_complete` | final loaded set or truthful terminal result state | `hotel_opened` | edit/retry/help |
| `hotel_opened` | hotel details and concrete tours are operable | `tour_selected` | back with filters preserved |
| `tour_selected` | one concrete tour is current and not stale | `flight_loading` | switch/back |
| `flight_loading` | atomic round-trip request is pending | `flight_selected` / `flight_unavailable` / `flight_error` | retry or manager fallback |
| `flight_selected` | real variant and price context selected | `review_ready` | change selection |
| `flight_unavailable` | empty or unavailable supplier response | `review_ready` | retry; manager fallback |
| `flight_error` | lookup failed/timeout | `review_ready` | retry; manager fallback |
| `review_ready` | truthful selected context can be inspected | `lead_editing` | back without context loss |
| `lead_editing` | required contact/consent fields are operable | `lead_submitting` | validation in place |
| `lead_submitting` | one protected send attempt is pending | `lead_accepted` / `lead_error` | pending lock; safe retry |
| `lead_accepted` | existing endpoint gives its accepted response | downstream delivery status | clear next step |
| `lead_error` | request is not accepted/confirmed | `lead_submitting` | preserve fields and retry/help |

`flight_unavailable` and `flight_error` must not be terminal states. They retain
the selected tour and expose a manager-verification lead path without claiming
a flight or final price that is not known.

## Logical measurement contract

The names below describe meaning, not final wire-format event names.

| Logical signal | Fire once when | Minimum non-sensitive properties |
| --- | --- | --- |
| `search_validated` | required search input passes validation | route, device class, parameter completeness |
| `search_accepted` | controller accepts a new generation | anonymous search/session correlation, generation |
| `results_first_useful` | first operable normalized result renders | result count, partial/final, latency bucket |
| `results_complete` | generation reaches its truthful terminal result state | hotel count, tour count, latency bucket, status |
| `hotel_opened` | user opens a hotel result | anonymous position/id, loaded-set size |
| `tour_selected` | concrete non-stale tour becomes current | anonymous tour/hotel/operator ids, position |
| `flight_state_resolved` | flight lookup resolves | selected/unavailable/error/timeout, retry count |
| `review_reached` | real review state becomes eligible | has flight, price confidence, fallback reason |
| `lead_attempted` | protected sender accepts one submit attempt | context completeness, has flight, retry count |
| `lead_accepted` | existing client receives accepted response | anonymous request correlation, response class |
| `lead_failed` | send fails or cannot be confirmed | failure class, retryable flag |
| `lead_delivery_confirmed` | authoritative downstream system confirms delivery | anonymous correlation, channel |

Do not include telephone, email, name, free text, full dates of birth or other
personal data in analytics properties. Search and tour identifiers must follow
the existing privacy/analytics policy before being used.

## Counting rules

- A `valid search session` contains at least one accepted, structurally valid
  search generation. Page views alone are not searches.
- Each search generation has one start and at most one terminal completion.
- Partial rendering is not final completion.
- A stale generation cannot emit selection, review or lead attribution after a
  newer generation becomes current.
- Re-renders and responsive view changes do not create new funnel steps.
- Local filters do not create new supplier searches or valid search sessions.
- `lead_attempted` is not `lead_accepted`; `lead_accepted` is not confirmed
  downstream delivery.
- Deduplicated repeated submits remain one logical lead and record the existing
  deduplication outcome only if the current contract exposes it safely.
- Synthetic QA traffic must be distinguishable and excluded without modifying
  production Metrica configuration.

## Required breakdowns

Only add a breakdown if it can change a product decision and does not expose
personal data. Initial candidates are device class, entry route family,
departure region, destination country, result availability, flight outcome and
price-confidence class. Avoid high-cardinality dashboards that cannot be acted
on.

## Guardrails

No experiment or rollout is successful if it improves clicks while any of the
following worsens materially:

- accepted/confirmed lead delivery;
- duplicate or failed lead rate;
- price/context correctness;
- empty/error recovery;
- search completion and first useful results latency;
- JavaScript errors, page 5xx, accessibility or Core Web Vitals;
- manager workload caused by missing or misleading context.

Numerical thresholds require a clean production baseline. Until then, release
gates use zero tolerance for contract corruption, dead ends, silent lead loss
and untruthful states, plus explicit no-regression comparisons.

## Measurement implementation prerequisites

1. Inventory current Metrica goals and `v2/analytics-v4.js` event consumers.
2. Agree the CRM/system-of-record definitions of `qualified` and `delivered`.
3. Define anonymous correlation and retention with privacy approval.
4. Capture a stable pre-change baseline and synthetic-traffic exclusion.
5. Review the event/property table with product, engineering and operations.
6. Implement in a separate PR with duplicate/stale/privacy contract tests.
7. Verify in preview and production debug views before using the data for a
   rollout decision.
