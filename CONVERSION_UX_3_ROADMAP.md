# Conversion UX 3.0 roadmap

## Objective
Raise conversion by reducing cognitive load and scrolling across the complete V2 journey while preserving Tourvisor/search correctness, analytics contracts, Yandex Metrika configuration/goals, and the existing lead-sending mechanism.

The guiding flow is:

**Хочу отдохнуть → покажите варианты → этот нравится → подтвердите его мне.**

Every stage keeps the durable five-viewport visual contract at 375 / 430 / 768 / 1024 / 1440 and does not weaken recovery/error handling.

## C1 — Search Experience 3.0
Status: **DONE**

Completed through PRs #46/#48 with the active-contract follow-up #50. The first-search path is focused on route, dates, duration and guests; hotel category/meal remain available in secondary filters; mobile first-screen density and overlay stacking were improved without changing search/Tourvisor parameters.

## C2 — Results Experience 3.0
Status: **DONE**

PR #49 changed result cards to one representative tour per hotel with progressive disclosure for additional variants; PR #52 aligned the production visual contract with that reviewed hierarchy. Sorting, continuation and mobile result filters remain intact.

## C3 — Selected Tour Experience 3.0
Status: **DONE**

PR #51 keeps the five core trip facts immediately visible and moves secondary facts behind the existing progressive disclosure. Price, flight and next action remain dominant while room/details and recovery stay available.

## C4 — Lead Experience 3.0
Status: **DONE**

PR #53 made lead entry phone-first while keeping optional name/comment behind `Дополнить заявку`; PR #55 removed duplicated trust copy. Consent, validation, selected-tour/flight context, dedupe/recovery and the existing lead payload/transport contract are unchanged.

## C5 — Flight Friction
Status: **DONE**

PR #54 measured fresh production flights latency before changing behavior. Observed fresh calls were about 739–1060 ms with a median around 868 ms. PR #56 then removed the extra manual click by automatically loading flight variants after tour selection, while preserving stale-tour/generation guards, duplicate-request suppression, explicit retry, default-flight `v2:flight-selected`, final-price synchronization and lead context. Production deploy/live/post-deploy/baseline checks passed.

## C6 — Visual Refinement
Status: **DONE**

A fresh five-viewport audit after C1–C5 confirmed one material nested-card issue in checkout: the flights section was a bordered/background card containing another bordered card for each flight variant. PR #57 flattened only the outer flights container, preserving individual variants, selected state, loading/error/retry and lead presentation. V2-only deploy, live smoke, post-deploy visual and durable baseline passed.

The selected-tour visual audit then exposed a real mobile overlap from the C1 sticky search CTA: it could remain visible over checkout after a tour was selected, including the public/programmatic selection path. PR #59 suppresses that sticky immediately for direct-tour clicks and on `v2:tour-selected`, and the selected-tour visual gate now fails if the sticky overlaps checkout. V2-only deploy, live smoke, post-deploy visual and durable baseline passed.

A subsequent fresh selected-tour review still found unnecessary nested-card visual weight in the reassurance area immediately before lead entry. PR #61 compacted the existing reassurance content into a lightweight strip: the duplicate explanatory paragraph is visually removed, individual reassurance steps no longer look like nested cards, and spacing/type are reduced so the lead form sits closer to the selected flight. Trust semantics and DOM/data behavior remain intact. PR #61 passed V2-only deploy `33102452656`, active contract `33102452612`, tour live `33102452599`, result-detail live `33102452609`, security `33102452634`, post-deploy visual `33102554997` and five-viewport baseline `33102554873`.

The analogous results-card flattening candidate was reviewed on mobile and desktop and intentionally retained: the `Варианты тура` section background materially separates hotel-level comparison from a concrete tour choice on desktop. C6 therefore stops here rather than mechanically removing useful hierarchy.

## C7 — Live Conversion Optimization
Status: **WAITING_FOR_TRAFFIC**

Use real production evidence to prioritize further work from the existing funnel events:
`search_started → search_complete → tour_selected → flight_selected → lead_started → lead_submitted`.

Prioritize real breakage, lead loss, incorrect data and observed UX friction over speculative polish. When meaningful traffic exists, activate C7 together with B8/A8 and work from live evidence.

## Delivery status
1. C1 Search 3.0 — DONE
2. C2 Results 3.0 — DONE
3. C3 Selected Tour 3.0 — DONE
4. C4 Lead 3.0 — DONE
5. C5 Flight friction — DONE
6. C6 visual refinement — DONE
7. C7 live optimization — WAITING_FOR_TRAFFIC

Protected boundaries remain unchanged: no Yandex Metrika configuration/goal changes and no lead-sending mechanism/external-contract changes without explicit approval.