# ANEX and AnyTour hotel identifiers: bounded comparison

The owner requested comparison of ten hotels across ANEX Online API, ANEX XML
references, and the existing AnyTour hotel catalogue. This is evidence gathering;
no identifiers or hotel records are changed.

## Selection and read scope

The XML documentation does not provide a single-hotel lookup by `inc`. The pilot
therefore selects ten named, non-deleted records with available API cards from the first XML `hotel`
reference page, rather than downloading the full catalogue to chase IDs from
the earlier tour-price search. This sample is not a random coverage estimate.

The diagnostic performs `currentstamp`, one XML hotel page (up to 500 updated
records), and at most thirty `Hotels_DETAILS` requests until ten API cards are
available. Empty API cards and obvious programme placeholders are counted and
skipped. Each request uses the corresponding XML
identifier. It tests whether the returned API ID, name, and town ID correspond;
equality across these interfaces is not assumed in advance.

An in-memory PHP process in `$HOME/www/anytoour.ru` uses the existing audited
`data/db-v1.php` (or the site's `v2/data/db-v1.php`) connection helper. It opens
a READ ONLY transaction and selects at most eight active catalogue candidates
per hotel. Queries use bound parameters for names/distinctive tokens and a
small coordinate bounding box. Optional existing hotel-details data supplies
address and coordinates. No schema, rows, configuration, public pages, sessions,
or neighbouring projects are modified. No live Tourvisor API request is needed.

## Interpretation

- `same_record`: API and XML IDs agree, names correspond and available town IDs
  do not conflict.
- `confirmed`: the best local candidate has name similarity at least 0.85,
  coordinates within 200 m, no known country conflict and no similarly ranked
  second candidate. This remains a proposed mapping, not a database write.
- `probable`: plausible name match, but the evidence is insufficient for the
  stronger criterion (for example missing coordinates).
- `ambiguous`: multiple similarly ranked candidates.
- `geo_conflict`: the candidate conflicts by known country or by a distance
  greater than 5 km.
- `unmatched`: no sufficiently similar candidate among the bounded results.

Candidate SQL ranks exact names first, distinctive-token name matches second,
and coordinate-only neighbours last before applying its eight-row limit. This
prevents a dense city block from displacing a genuine name candidate.

Only hotel identity fields, coordinates, numeric IDs, matching evidence and
fixed statuses leave the processes. Credentials, contact details, URLs, raw
supplier bodies, SQL/configuration errors and descriptions are excluded. All
matching logic is tested offline before live execution; the PHP reader is also
syntax-checked by the workflow.

## Sources

The initial [run 34142635134](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34142635134)
confirmed that the existing AnyTour DB connection works read-only. The first ten
raw XML rows mixed normal hotels with programme placeholders and unavailable API
cards. For example, Radisson Blu Hotel Kas uses ANEX ID 43689 while the active
AnyTour/Tourvisor candidate uses ID 83221; name, country and town agree, but ANEX
coordinates were missing. Crowne Plaza produced same-name candidates in several
countries and must not be merged by name. The follow-up uses the availability
selection and SQL ordering described above.

- [AnyTour catalogue ingest](https://github.com/pyatkoff/poisk-turov-test/blob/45c589b06d939fe5ff494733e259745c41ea4d97/v2/data/sync-catalog-v1.php): local hotel IDs come from Tourvisor.
- [SAMO XML reference protocol](https://dokuwiki.samo.ru/doku.php?id=samotour:xml_gate)
- [Hotels_DETAILS](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:hotels:details)
