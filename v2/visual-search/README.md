# Search3 v155 — approved Sites100 interface in the existing NEXT preview

## Temporary SAMO pause — 2026-10-08

The shared live configuration now enables only Tourvisor and direct ANEX. Both optional SAMO/Andromeda endpoints are disabled, so initial search, continuation and quote actions cannot send SAMO requests. The integration remains in the source for a later explicit re-enable; its retained regression journeys opt in only within fictional transport fixtures. The calendar still uses previously observed database prices.

## Bounded flight repricing — 2026-10-07

New supplier quote contexts explicitly carrying `repricing.enabled=true` can reopen their retained flight inventory from the verified tour and calculate another exact pair. The existing flight screen shows the returned whole-party total and its change; returning to a previously verified pair reuses the immutable cached result. Native transport markup remains informational and is never summed into a tour price.

Changing the pair clears displayed price/application authority immediately. The current mutable supplier request finishes; only the latest unsent draft proceeds through the canonical data adapter. Late responses cannot replace a newer pair or another tour. A cached pair stays pending while another mutation is active. A healthy three-pair limit keeps earlier verified pairs usable; UNKNOWN or expiry removes all current price/application authority, including on Back/Forward. Legacy contexts retain their original single-pair path.

`tests/search3-visual-anex-flight-continuation.cjs` keeps its original ANEX/SAMO journeys and adds opt-in actual-adapter fixtures for A/B/A and the pair cap at 360/390/430/768/1280, A/B/C coalescing, UNKNOWN after cached return, late responses, exact application and passive history. The existing compiled browser runner adds both providers at all five widths, with edited verified/pending/cached-pending/application/UNKNOWN screenshots and measured overflow, footer, contact and touch-target guards. Exact compiled CI and image inspection remain required before publication. Real supplier/session acceptance and physical Safari are separate evidence.

## Customer journey block — 2026-10-07

The primary current concrete ANEX action goes through the existing exact-context verification directly into package flight/price calculation. It skips the context-only screen; the existing no-flight/APD estimate path stays secondary. The same single-pair/four-stage authority and retained outcomes apply.

Tourvisor's flight picker now applies its authoritative pair total and opens the existing application in one action. Back retains the selected pair and price. A SAMO response with exactly one flight per direction continues the existing pair operation only after an explicit customer verification; passive reopen/Forward and late inventory do not authorize this continuation. Multiple choices retain explicit confirmation.

SAMO and ANEX confirmed-price screens reuse the current tour composition with exact room/meal/party, full returned total and listing-to-confirmed change. Their applications reuse the current responsive review/contact composition and receipt-based lead binding. Estimates remain labelled, missing transport facts remain unknown, contacts/consent/expiry/no-replay are unchanged. No supplier contract, money/fuel formula, lead transport or production change.

Required coverage: direct and legacy ANEX paths; sole SAMO success/failure/duplicates/late inventory/late price; direct TV application and Back; source guards and compiled 390/768/1280 journey screenshots. Controlled fixtures do not certify physical iPhone/Safari or live supplier availability.

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

### Applied departure day in results

The compact result header uses the same applied departure scope as the result
filter and trip summary. Selecting a day in the open price tape shows that exact
day, including when no loaded offers match it. Clearing the day, removing its
filter chip or tapping it again restores the original range. Cancelling a date
edit preserves the applied day, cards and URL. These local actions do not start
supplier searches. Fictional DOM and compiled Chromium coverage distinguishes
this from live supplier or physical iPhone/Safari acceptance.

### Multiple selected hotels after a full URL reload

The existing picker restores every selected own hotel ID through the canonical
profile and verified legacy-link reader, including a `hotels=…|…` URL. Pending
or unavailable links keep the exact selection and block a partial or whole-country
search. The existing form status owner shows loading or a visible error/retry
above the submit action, including mobile layouts which hide the initial results.
Retry rereads only unresolved hotels; query,
country or selection changes cannot accept a former restoration response.

Focused source checks cover partial failure/retry, duplicate work, stale selection
and country, and exact ID/country rejection. Compiled browser acceptance covers
full reload, pending/partial-failure gates, Cancel, retry without supplier replay
and both verified legacy IDs on explicit submission at 360/390/430/768/1280.
Fixtures are fictional intercepted responses, not live availability or physical
iPhone/Safari acceptance. No data, supplier, money or lead owner changes.

### Hotel picker usability block

Current successful catalogue rows remain authoritative for that exact query and country, including server-accepted aliases whose display name differs. Cached suggestions still use local name matching; a changed query/country or ignored abort cannot reuse the old alias response. Forward restores the query and exact multi-ID draft through a fresh catalogue read, without starting suppliers.

The same canonical hotel catalogue supplies available thumbnails, stable own IDs and verified legacy links. Missing or failed photos show a placeholder. Hotel matches stay within the selected country; countries remain reachable while a hotel name is typed. Accepting a country change repeats only the bounded catalogue lookup, keeps the query and rejects obsolete responses. Supplier search still requires the form submit.

The mobile picker uses the visual viewport height above an open keyboard, with a compact header, three visible fixture rows and an accessible confirmation action. Chromium checks at 390/768/1280 include a simulated keyboard and viewport pan; these are not physical iPhone/Safari acceptance. Query errors, retry, draft cancel/Forward, 8+2 pagination, current-country cache and verified hotel ID submission are covered with fictional responses and no live supplier or lead HTTP.

Passive history recovery also retains every bounded exact hotel draft ID while canonical metadata is pending or missing. Open-picker reload and Forward keep selection chips and the two-hotel Apply action; known foreign-country metadata is rejected. Pending country identity cannot enable Apply. Apply keeps unresolved IDs but the existing search gate still blocks supplier submission until every verified link is present.
