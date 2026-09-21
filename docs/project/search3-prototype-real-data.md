# Search3: connect the actual prototype

Owner direction, 2026-09-20: preserve the completed prototype interface and connect
existing search data. Calendar prices may come from the stored tour database; gaps
are acceptable. Real flight endpoints already exist. Publish a separate open URL;
do not overwrite the production search or previous version URLs.

Visual source: standalone prototype commit
`f3d489cd428f05a5a39642b24f7556dc57a44c60` (v17). The original HTML structure,
stylesheet, logo, fonts, form widgets, modal navigation and comparison components
are transferred into `v2/prototype-search/`. Fixture hotels, stock photos, generated
prices, invented flight pairs and simulated supplier responses are removed.

Intended acceptance entry:
`/_preview/search3-local-candidate/prototype-search/`.
The existing LOCAL route is used because its DB and canonical profile readers
already enforce that boundary. No reader guard or provider contract is widened.

| Prototype component | Data / action |
| --- | --- |
| Origins and countries | Existing departures cache and `V2Runtime` catalog actions |
| Dates / nights / adults / child ages | Full existing Search3 request field set; ages remain explicit |
| Search and progressive results | Existing `search_start`, `search_status`, `search_results` actions |
| Hotel identity, descriptions and photos | Existing `Search3CanonicalProfilesV1`; no supplier text fallback |
| Stored offers | Existing LOCAL DB reader and parser; full response scope equality |
| Calendar | Read-only DB requests, bounded to 22 departure dates each; missing price is a dash |
| Stars, meal, price, resort and operator | Local filtering of the same concrete offers; no new supplier search on a filter click |
| Exact Tourvisor tour | Existing `tour` action, retaining its exact opaque ID |
| Real flight pairs | Existing `flights` action, including all outbound/inbound segments and missing-data states |
| Price with a flight | Supplier variant price is the whole-tour price, never added to the listing price |
| Fuel | Unknown remains unknown; zero is shown only when explicitly supplied |
| Cached offer selection | Requires refresh; cached IDs are never sent to `tour` or `flights` |
| Favorites and comparison | Original widgets; own hotel IDs, separate real-data storage namespace |
| Lead handoff | Original selected-tour summary with editable contacts, phone validation and an immutable handoff to the existing controller; preview validates without sending |

This packet is the first functional transfer, not final production migration.
Current-source provider-specific ANEX/Andromeda quote selection still needs
integration before the whole product can replace production. The existing ANEX
adapter deliberately declines the LOCAL boundary; the Andromeda controller also
does not yet support lead delivery. These restrictions are not widened here.
Cached provider offers remain display-only
and require refresh. Calendar does not initiate supplier acquisition. Descriptive
facets without complete factual coverage are not offered.

Focused acceptance: `tests/search3-prototype-real-data.cjs` exercises the copied UI
at 390 and 1440 px against deterministic API-shaped fixtures. It covers DB-only
calendar loading, search and own-profile projection, local stars, exact-tour
opening, real-shaped flight selection, full variant price including decimals and
preview lead validation. It makes no live supplier requests. Screenshots must
be inspected. Physical iPhone/Safari and supplier-connected acceptance remain
separate evidence; they are not implied by this test.

The contact form calls `V2TourController.createLeadSession` through the presentation
adapter. That entry snapshots the confirmed Tourvisor tour, selected flight pair,
search ID and search parameters. The existing `leadPayload`, `submitLead` and
`V2LeadSearchContext.enrichPayload` remain the only mapping/delivery owners; URL,
headers, payload fields, decimal prices, child ages, success/duplicate handling
and existing events are retained. A new search invalidates the old quote/session.
Contacts remain in memory across modal navigation; consent must be selected again.

`tests/search3-prototype-lead.cjs` uses intercepted HTTP fixtures to cover unknown
provider rejection, snapshot isolation, children aged 0/17, full decimal flight
price, invalid phone, pending duplicate suppression, failure/retry, server duplicate
receipt and stale-search rejection. Fixture delivery success is not a real lead.
On the published preview, the button checks the form locally and explicitly states
that nothing was sent. The server's `preview-lead-disabled.php` remains HTTP 403.

A flight without a positive supplier variant price remains visible for inspection,
but cannot be applied or passed to the contact form. An unpriced default pair also
blocks confirmation until another priced pair is selected. The listing or exact-tour
base amount never substitutes for the missing flight price. Cancel preserves the
previous pair and amount without another request. The adapter rejects unpriced or
missing selected variants before invoking the unchanged lead owner; an explicit
no-flight fallback still follows the existing flow. `tests/search3-prototype-flight-price.cjs`
checks that boundary; the 390/1440 journey covers default unknown price, correction,
cancel and a priced decimal variant. List/comparison copy does not promise unknown
fuel inclusion, and unknown flight type is not labelled charter.

Direct cached ANEX/Andromeda selection still requires an INT handoff that resolves
the persisted provider-qualified offer/search digests into fresh private offer
context. The current DB response intentionally grants no selection authority.
Neither a cached ID nor a previously verified listing is used as a quote locator;
the existing LOCAL ANEX restriction and protected provider contracts remain intact.

Saved rows now say “Смотреть условия” instead of promising immediate selection or
flight availability. Their explicit “Найти актуальные туры” action starts one new
search for the same canonical hotel, exact departure date and nights, origin,
country, adults and child ages. Existing filters remain applied. The form, search
summary and URL use those same exact conditions. This is a current-offer search,
not a quote of the cached provider row: the returned room, meal, operator and price
may differ and the traveler must explicitly select a new offer. Merely inspecting
or cancelling saved details makes no supplier request. No-current-offers completion
is visible even when cached rows remain; it does not auto-retry or quote them.

`tests/search3-prototype-cached-selection.cjs` reproduces the previous broad-window
refresh defect and checks the existing UI at 390/1440 px with fictional ANEX and
Andromeda cached rows. It verifies exact date/nights, Kazan departure, children 0/17,
canonical legacy hotel IDs, retained filters, no automatic selection, fresh price
ownership and the empty-current result. This does not prove a direct provider quote
or live supplier availability. No provider, DB or publication boundary is widened.

Source visual assets and logo remain from the immutable baseline. The controller
gains a presentation entry and its shared build map is regenerated; legacy defaults
are retained. DB readers, supplier APIs, pricing, lead transport/mapping, Metrika
and production entrypoints remain unchanged. Production lead activation and live
supplier acceptance are separate from this isolated preview.

Browser Forward now restores a completed in-memory exact-tour selection with its
chosen flight pair and full price, without another quote or flight request. The
contact step also restores through the existing lead binder: its draft survives,
but consent must be selected again. No quote authority is persisted to storage or
history. A new search still clears the selection; if a quote was interrupted or
is no longer retained, details offer an explicit recheck rather than a dead button
or an automatic supplier request. The existing 390/1440 journey covers restored
tour details, contacts and saved-tour details with unchanged request counts.

Returning to the offer list while a quote or flight request is pending also keeps
that list open if the request later fails. Error handlers use the same current
modal and offer identity checks as successful replies. A failure on the still-open
offer remains visible and can be retried explicitly. The existing fixture journey
reproduces both late failures and checks recovery at 390/1440 px; no automatic
supplier retry, pricing change or new navigation layer is introduced.

The destination picker now searches the existing local hotel catalogue before any
tour search. It resolves its bounded legacy-ID suggestions through the existing
LOCAL canonical reader, shows own hotel names and preserves the returned legacy
IDs for the later explicit tour/DB search. It does not insert catalogue rows as
available tours. Loading, catalogue failure with explicit retry and an actual
empty result are separate states; unselected typed text cannot apply the entire
country. Cancelling or replacing a query invalidates its pending response.
Selected metadata remains in memory independently of result updates. The existing
390/1440 journey checks pre-search lookup, failure/retry, empty, exact own-to-legacy
selection and the subsequent search. Mobile destination controls also follow the
keyboard-reduced VisualViewport; the fixture models that geometry, not physical
iPhone/Safari acceptance. No catalogue, matching or supplier API is modified.
