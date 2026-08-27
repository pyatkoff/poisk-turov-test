# Conversion UX 3.0 roadmap

## Objective
Raise conversion by reducing cognitive load and scrolling across the complete V2 journey while preserving Tourvisor/search correctness, analytics contracts, Yandex Metrica configuration/goals, and the existing lead-sending mechanism.

The guiding flow is:

**Хочу отдохнуть → покажите варианты → этот нравится → подтвердите его мне.**

Every stage must keep the durable five-viewport visual contract at 375 / 430 / 768 / 1024 / 1440 and must not weaken recovery/error handling.

## C1 — Search Experience 3.0
Status: **IN PROGRESS**

Goals:
- shorten the mobile first screen and bring the search task above the fold;
- keep the first decision path focused on route, dates, duration and guests;
- move hotel category/meal into secondary mobile preferences without changing underlying form/search parameters;
- provide a persistent mobile search CTA with compact trip context;
- preserve all current validation, catalogs and Tourvisor lifecycle behavior.

Success criteria:
- search remains fully usable at all five baseline widths;
- advanced mobile filters retain stars/meal access;
- sticky CTA submits the existing form and mirrors busy state;
- no analytics, API or lead contract change.

## C2 — Results Experience 3.0
Status: **QUEUED**

Goals:
- make hotel comparison the primary task;
- show one representative/best tour by default on both mobile and desktop;
- reduce card information density and improve scan speed;
- strengthen hierarchy: hotel → location/rating → best price/context → primary action;
- keep additional tour variants available through progressive disclosure;
- preserve sorting, continuation and mobile result filters.

Success criteria:
- materially more hotel cards are scannable per viewport without losing important decision facts;
- one primary CTA per hotel is visually dominant;
- no loss of tour/date/meal/operator access.

## C3 — Selected Tour Experience 3.0
Status: **QUEUED**

Goals:
- reduce above-the-fold checkout density;
- summarize only the most important trip facts first;
- move secondary conditions into progressive disclosure;
- reduce nested cards/borders and make price/flight/next action visually dominant;
- keep room/details and error recovery intact;
- avoid adding another decorator; consolidate presentation ownership when the redesign requires structural markup changes.

Success criteria:
- selected tour is understandable before a long scroll on mobile;
- price, core conditions and the next action are visible immediately;
- all current underlying data remains accessible.

## C4 — Lead Experience 3.0
Status: **QUEUED**

Goals:
- switch presentation to phone-first lead entry;
- keep name and comment optional behind a lightweight disclosure;
- keep consent and privacy contract intact;
- clarify one promise close to submit: no payment, manager confirms details first;
- remove duplicated trust copy around the form.

Hard boundary:
- do not change the existing lead-sending mechanism or external payload contract.

Success criteria:
- default visible form requires the minimum cognitive effort;
- existing lead payload/dedupe/recovery continues unchanged.

## C5 — Flight Friction
Status: **QUEUED / MEASURE FIRST**

Goals:
- measure real flights API latency after tour selection;
- if evidence supports it, preload or automatically load flight variants after `Проверить тур`;
- avoid a separate user click when it does not improve reliability or perceived control.

Success criteria:
- only implement auto-load/preload when measured latency/reliability makes it safe;
- preserve explicit retry and flight-price synchronization.

## C6 — Visual Refinement
Status: **QUEUED**

Goals:
- reduce nested cards, borders, badges and competing font weights;
- strengthen typography/spacing hierarchy;
- make each stage have one obvious primary action;
- simplify repeated trust messaging;
- preserve brand blue/orange identity and responsive stability.

## C7 — Live Conversion Optimization
Status: **WAITING_FOR_TRAFFIC**

Use real production evidence to prioritize further work from the existing funnel events:
`search_started → search_complete → tour_selected → flight_selected → lead_started → lead_submitted`.

Prioritize real breakage, lead loss, incorrect data and observed UX friction over speculative polish.

## Delivery order
1. C1 Search 3.0
2. C2 Results 3.0
3. C3 Selected Tour 3.0
4. C4 Lead 3.0
5. C5 Flight friction measurement/experiment
6. C6 visual refinement pass
7. C7 live optimization

Each material user-facing PR must pass relevant contract/security checks and visual verification at 375 / 430 / 768 / 1024 / 1440 before production merge.
