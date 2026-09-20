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
| Lead handoff | Preview selection summary only; no submission or payment |

This packet is the first functional transfer, not final production migration.
Current-source provider-specific ANEX/Andromeda quote selection and the production
lead-controller handoff still need integration into this presentation before the
whole product can replace production. Cached provider offers remain display-only
and require refresh. Calendar does not initiate supplier acquisition. Descriptive
facets without complete factual coverage are not offered.

Focused acceptance: `tests/search3-prototype-real-data.cjs` exercises the copied UI
at 390 and 1440 px against deterministic API-shaped fixtures. It covers DB-only
calendar loading, search and own-profile projection, local stars, exact-tour
opening, real-shaped flight selection, full variant price including decimals and
disabled lead submission. It makes no live supplier requests. Screenshots must
be inspected. Physical iPhone/Safari and supplier-connected acceptance remain
separate evidence; they are not implied by this test.

Source assets are copied from the immutable baseline. Existing generated Search3
assets, lifecycle/controller files, DB readers, pricing implementation, lead
transport/mapping, Metrika and production entrypoints are unchanged. Keep this
new presentation isolated until its remaining handoffs and owner acceptance are
complete.
