# Search3 v155 — approved Sites100 interface in the existing NEXT preview

## Approved cumulative interface transfer — 2026-10-06

This candidate adapts the entire accepted Sites100/package34/v154 interface through the current NEXT owners. Base: `b74ac0fea3e0ef2c4c568f4f4db0da9a1002299f`. Donor source: `05051662c25cf8258991475e2e7536a12f7248d4`; documentary contract: Sites102 `a33878d9798e6f7c1a4ff0a3f245f27254580a27`, `qa/closing34/TRANSFER.md`. Existing PR4164 replaces its superseded Site14/O8 patch with implementation. This entry is a candidate, not a new publication receipt.

The approved form has a unified direction picker (country, resorts and multiple exact hotels), explicit replacement/apply/cancel, return-age children, Any/3/4/5 stars, canonical meals and integer budget. The results price tape stays open with month/year and exact saved prices. Mobile hotel photos span the card at the approved 1.8 ratio; rooms are plain text and only confirmed flight types get a badge. Exact offers have independent rows and current-selection marking. The selected tour and application compose existing real fields and lead hooks; returning to the passive list preserves the selected flight instead of restoring an old listing.

No donor data/fixture/parser/lead or live transport was copied. The existing lazy offer/hotel/flight owners, formatter instances, rating inventory, generated-root reconciler, pagination and month-local inventories remain in place. Canonical monetary values, quote eligibility, fuel, lifecycle, real lead transport and entry graph are unchanged.

Source interaction checks use saved snapshot/marked demos. Actual layout must be inspected from the final compiled-browser artifact at 360/390/430/768/1280. `jsdom` proves behavior, not browser geometry. The existing CI gate invokes the new full interface check through results-rendering and captures compiled screens through offer-list-browser; workflows are unchanged. Physical iPhone/Safari, keyboard, safe area and 200% text remain unverified. No live completeness/speed/conversion or real lead acceptance is inferred from demos.

Publication requires green applicable final-SHA CI, visual review and a separate exclusive exact-artifact publisher claim in #4217. Main/production and sibling previews are outside this transfer.

## Tourvisor fuel disclosure — 2026-09-29

The connected presentation owner uses Tourvisor's returned total unchanged.
The existing `three-provider-search-handoff.php::customerPriceReadiness` contract
and `tourvisor-anytour-offer-autosave-test.php` treat a Tourvisor search price with
an explicit reported fuel amount as the full listing price, without adding fuel.
The official [OpenAPI 1.2.4](https://api.tourvisor.ru/search/docs), read on
2026-09-29, calls both `Tour.price` and `TourOutput.price` «Итоговая цена».

Positive fuel reported by the same priced Tourvisor tour/flight pair is therefore
labelled included. Missing/invalid fuel, a different API, stale price, currency
mismatch, or a fee inherited from the tour instead of the selected flight cannot
acquire that assertion. No amount, transport, or protected money/lead contract changes.

`GET /tours/{id}` retrieves tour data; `GET /tours/{id}/flights` retrieves the
operator-cart actualization and flight alternatives. The application path without
flights shows «Цена предложения», keeps the manager handoff, and does not claim
that tour-data retrieval alone performed cart actualization. Known fuel inclusion
and future price/availability confirmation remain separate facts.

## Selected-tour quote owner — 2026-09-29

The owner-requested refactor pass keeps the connected `app.js` as the presentation
owner. Both explicit Tourvisor actions (application and optional flight selection)
share `quoteSelectedOffer`; it applies the same quote fields and error handling.
`isCurrentOfferRequest` checks the selection generation, exact offer key and open
offer dialog before quote/flight responses may update the view. Closing, reopening
or changing a selection prevents an earlier response from changing that view.
`offerSelectionHint` and `offerPrimaryActionHTML` keep the existing ordered UI
choices readable. No provider transport, price arithmetic or lead contract moved.

Characterization in `tests/search3-visual-live-bridge.cjs` covers late success and
failure after close/reopen for both actions, temporary versus expired quote errors,
quote-only application and retained selected flights. It uses fictional intercepted
responses; real supplier readiness is a separate acceptance step.

## SAMO quote recovery — 2026-09-26

Current-search SAMO verification retains the first pending, confirmed or failed attempt. Reopening the offer reuses that outcome; after flight confirmation it retains the flight outcome. An unconfirmed attempt returns to other offers with the listing price explicitly unconfirmed. It cannot silently submit the same quote again. A new explicit search invalidates previous receipts. Browser-local failure events retain only fixed public status/category codes, without response text, identities or private state.

Focused fixtures cover duplicate selection, reopening, terminal HTTP/network/invalid responses, changed search, flight continuation and safe recovery at mobile/tablet/desktop widths. No supplier request is required for these checks. The last real SAMO acceptance remains UNKNOWN/unconfirmed; this recovery does not establish supplier readiness. Actual cause, direct ANEX acceptance and physical Safari remain open. Latest exact preview publication receipts are tracked in coordination #4217 (#3419 is archival); previous publication records below are retained history.

## Published stage 2 — 2026-09-25

Published at https://anytoour.ru/_preview/search3-next-candidate/visual-search/ (use the plain entry for live mode; `?scenario=snapshot` explicitly stays historical). Default entry reuses canonical TV + direct ANEX + SAMO/Andromeda data/lifecycle, exact-hotel calendar and canonical lead owner. It restores URL conditions without starting a supplier search. Explicit labelled snapshot/demo scenarios retain the offline graph. LOCAL offers only feed calendar/history/SEO; the canonical hotel catalogue still owns IDs, full galleries and facts.

Publication [36171039351](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36171039351) succeeded from exact source `99baad24030cfbcae9c7c934e36d45dd95a11a80`, release `b4223e76ad716d354201a3c5f534db062f570aa7` (PR #3928, updater #3932). Readback verified 832 files, 35 visual assets, canonical dependencies, 9 routes, noindex and disabled lead delivery; production and sibling previews stayed unchanged. New-preview predecessor is now `99baad24030cfbcae9c7c934e36d45dd95a11a80`; never replay the terminal create/update commands.

Actual hosted acceptance found and fixed a shared destination-reader packaging omission: PR #3933 adds only the existing reader to the Apache allowlist and keeps a real LOCAL-path + GET boundary. LOCAL update [36172310619](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36172310619) succeeded from `04f4ca155f9c2fb77f684dcb2cdd71d0a5ba31fa`; this is the current LOCAL predecessor. The next visual continues consuming that canonical endpoint. No supplier authentication or data writes were changed.

Actual Chromium desktop UI now initializes departures/countries, enables search, loads Turkey resorts and accepts Antalya. Its calendar loaded October/November 2026 observations for Moscow → Antalya, 7 nights, 2 adults; 9–15 October displayed a historical minimum of 108,130 RUB with the previously-found label. No live supplier search was submitted. Exact deployment and acceptance evidence is in `publication-live-receipt.json`.

The v147 CSS, fonts, assets, photo heights and one/two-click calendar are unchanged. The app integration reads canonical meal identities, preserves dynamic search/coverage getters, stops provider status on cancellation, and uses the existing provider-specific quote/flight/current/additional-price actions. TV and verified SAMO applications are validated through the canonical payload owner with no preview POST, including the merged #3926 receipt contract at release `3b460f04`. ANEX estimates are not presented as final verified prices. Protected APIs, money, fuel, lead delivery and Metrika are unchanged.

Focused acceptance uses fictional intercepted responses: shared-link bootstrap makes zero supplier requests; three providers converge on one hotel; TV exact room → second flight → 133,500.50 RUB → application validation with no send; SAMO verified receipt → 125,500 RUB application validation with no send; ANEX concrete offer and server-calculated non-final surcharge; cancel preserves results and provider failure leaves remaining offers. This is not acceptance on real current tours. Actual Safari/physical phone remains open.


## Historical stage 1 — 2026-09-25 (superseded by stage 2 above)

The full v147 visual is published at https://anytoour.ru/_preview/search3-next-candidate/visual-search/ (source PR #3917, exact source `4dd5b78dab815dac199550f4bcad3047f4d850b6`, release `2583c284b2631c1cdfa94df710f71f492fe3d38f`). Create-only publication [36164769312](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36164769312) succeeded. Its command is terminal/no-replay; do not recreate this route or rerun the create-only command.

Readback verified all 31 served visual static assets, 9 routes, noindex, LOCAL-only data and lead endpoints rejected (403), and unchanged production/previous previews. Supplier searches and real leads: zero. Exact receipt and browser acceptance are in `publication-receipt.json`.

Actual public-host Chromium desktop acceptance: stored real listing (1,047 hotels / 1,453 offers); one-click date selection and cancellation restored 1–7 October plus original results; labelled family demo (children 0/8) → exact room/meal → second flight pair → 187,700 RUB → successful local application validation with the same party, flights and amount. Nothing was sent. New-host mobile/Safari/physical-device acceptance is still open; the byte-identical Site v147 mobile evidence is earlier, separate evidence.

The Site remains at v147 as the visual reference. Stage 1 was offline-only; stage 2 above supersedes its integration status.

Owner authorization 2026-09-25: transfer the full Site visual first into a NEW preview on anytoour.ru, then connect the current live search and calendar DB.

This first packet contains the exact v147 presentation files from Site commit `3aa4d9001f51dfd732b01194b695deabcf8e033f`. Entry text and script list disclose the offline stage. The saved listing is historical; full quote/flight journeys are labelled demos or explicit imported recordings. No live supplier, Site `/api/*`, lead delivery, Metrika or payment transport is loaded.

New route: `/_preview/search3-next-candidate/visual-search/`. Existing Site, functional prototype and existing preview routes are preserved. `migration-manifest.json` records byte hashes for parity. This is the first staged migration, not a second design workstream.

Next acceptance: the separately coordinated functional owner checks actual current TV/SAMO/ANEX offers. Reuse exact canonical identities, price/flight receipts and lead owner; do not substitute historical DB offers into live results. Keep the public Site reference unchanged. Physical-phone/Safari and real end-to-end acceptance remain open; mocked fixtures are not evidence of real availability.
