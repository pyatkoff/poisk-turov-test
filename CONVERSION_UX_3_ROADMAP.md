# Conversion UX 3.0 roadmap

## Objective
Raise conversion by reducing cognitive load and scrolling across the complete V2 journey while preserving Tourvisor/search correctness, analytics contracts, Yandex Metrica configuration/goals, and the existing lead-sending mechanism.

The guiding flow is:

**Хочу отдохнуть → покажите варианты → этот нравится → подтвердите его мне.**

Every stage must keep the durable five-viewport visual contract at 375 / 430 / 768 / 1024 / 1440 and must not weaken recovery/error handling.

## C1 — Search Experience 3.0
Status: **DONE**

Delivered:
- shortened the primary search path around route, dates, duration and guests;
- moved hotel category and meal into secondary filters while preserving the same form/search parameters;
- retained access to all advanced preferences and existing validation/catalog lifecycle behavior.

## C2 — Results Experience 3.0
Status: **DONE**

Delivered:
- hotel comparison is the primary results task;
- one representative/best tour is shown by default on every viewport;
- additional variants remain available through progressive disclosure;
- sorting, continuation, mobile filters and Tourvisor result contracts are preserved.

Production note:
- two stale CI contracts were found after the intentional C1/C2 hierarchy changes and corrected without changing product behavior: active secondary-filter validation and the post-deploy results visual contract.

## C3 — Selected Tour Experience 3.0
Status: **DONE**

Delivered:
- the first five core trip facts stay immediately visible;
- secondary conditions remain accessible through `Ещё детали тура` on all viewports;
- room/details, flight flow, price synchronization and recovery paths remain intact;
- production deploy, live checks, post-deploy visual and baseline verification are green.

## C4 — Lead Experience 3.0
Status: **DONE**

Delivered:
- phone-first lead entry: phone stays immediately visible and required;
- optional name/comment are behind `Дополнить заявку` while retaining the same inputs and payload;
- consent, privacy, validation, dedupe, recovery and lead transport remain unchanged;
- selected tour/flight context remains visible around the form;
- production deploy, live checks, post-deploy visual and baseline verification are green.

Follow-up visual simplification:
- remove duplicated trust/help copy while retaining one clear no-payment/manager-confirmation promise close to submit.

## C5 — Flight Friction
Status: **MEASURED / MORE EVIDENCE BEFORE PRODUCT CHANGE**

Measured on fresh production tours via the existing live flights validator:
- samples: 3;
- latency: 739 ms / 868 ms / 1060 ms;
- median: 868 ms; measured max/p95 in this small sample: 1060 ms;
- all sampled payloads contained flight variants with clean segment/baggage/timezone/fuel/default-flight shape.

Decision:
- latency is promising for future automatic loading/preload;
- do not remove the explicit flight check yet from only one three-tour sample;
- keep accumulating fresh live-run evidence, then implement auto-load/preload only if latency and reliability remain consistently safe;
- explicit retry and flight-price synchronization remain mandatory.

## C6 — Visual Refinement
Status: **IN PROGRESS**

Goals:
- reduce nested cards, borders, badges and competing font weights;
- strengthen typography/spacing hierarchy;
- make each stage have one obvious primary action;
- simplify repeated trust messaging;
- preserve brand blue/orange identity and responsive stability.

Current first item:
- remove duplicated lead trust/help text already covered by the dynamic selected-tour/flight context and the single trust note near submit.

## C7 — Live Conversion Optimization
Status: **WAITING_FOR_TRAFFIC**

Use real production evidence to prioritize further work from the existing funnel events:
`search_started → search_complete → tour_selected → flight_selected → lead_started → lead_submitted`.

Prioritize real breakage, lead loss, incorrect data and observed UX friction over speculative polish.

## Delivery order
1. C1 Search 3.0 — DONE
2. C2 Results 3.0 — DONE
3. C3 Selected Tour 3.0 — DONE
4. C4 Lead 3.0 — DONE
5. C5 Flight friction — initial live measurement complete; broader evidence pending before behavior change
6. C6 visual refinement — IN PROGRESS
7. C7 live optimization — WAITING_FOR_TRAFFIC

Each material user-facing PR must pass relevant contract/security checks and visual verification at 375 / 430 / 768 / 1024 / 1440 before production merge.
