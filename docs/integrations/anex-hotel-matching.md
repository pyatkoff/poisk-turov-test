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

## Result: 2026-09-07T16:23:20.4804513Z

[Run 34142991785](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34142991785)
on source `bfe698926433a2d610bc81b2b19dffc005e70217` completed successfully.
All 31 offline tests, PHP syntax checking and Security guard passed. The live
check read one XML page of 500 hotel records, skipped three programme placeholders,
and requested 25 API cards: ten were available and fifteen returned no details.
The existing AnyTour catalogue was queried in a READ ONLY transaction.

For all ten selected records, XML `inc` and API `id` agree; names and town IDs
also agree. This is sample evidence, not a guarantee for every catalogue record.
All ten local candidate IDs differ from ANEX IDs. Five candidates meet the
conservative confirmation criteria; five remain review candidates. No mapping
was applied, including the confident five.

| Отель в ANEX | ANEX API = XML | Кандидат AnyTour / Tourvisor | Результат | Расстояние, м |
| --- | ---: | ---: | --- | ---: |
| Radisson Blu Hotel Kas | 43689 | 83221 | Требует проверки | нет координат ANEX |
| Грейс Кристалл | 30160 | 40430 | Уверенное соответствие | 12.5 |
| Hotel Historia | 30536 | 17469 | Уверенное соответствие | 21.3 |
| Domina Coral Bay Harem | 464 | 185 | Требует проверки | 158.3 |
| Dreams Beach Resort & Aqua Park Sharm El Sheikh | 465 | 195 | Требует проверки | 96.4 |
| Four Seasons Resort Sharm El Sheikh | 466 | 218 | Требует проверки | 52.4 |
| Retac Qunay Dahab Resort & Spa | 468 | 295 | Требует проверки | 45.5 |
| Jaz Sharm Dreams | 469 | 245 | Уверенное соответствие | 143 |
| Taba Hotel & Nelson Village Taba (Ex. Hilton Taba) | 470 | 252 | Уверенное соответствие | 48.6 |
| Park Regency Resort (Ex. Hyatt Regency Sharm El Sheikh) | 472 | 260 | Уверенное соответствие | 0 |

### Cases requiring review

- **Radisson Blu Hotel Kas, 43689 → 83221:** name, country and Kas agree, but ANEX
  returned no coordinates or address. A second independent identity check remains.
- **Domina Coral Bay Harem, 464 → 185:** local name includes Junior Suite; verify
  whether it represents the same Harem property or a narrower product/category.
- **Dreams Beach, 465 → 195:** a strong candidate exists, 96.4 m apart on El Fanar
  in Hadaba. The long versus short name fell below the current algorithm's
  threshold. Its `unmatched` label does not mean the hotel is absent from AnyTour.
- **Four Seasons, 466 → 218:** SSH is an abbreviation in the local title; both
  addresses contain Four Seasons Boulevard and coordinates are 52.4 m apart.
- **Retac Qunay, 468 → 295:** names include different amounts of historical and
  location text. Addresses share Al Tahrir and coordinates are 45.5 m apart.
  Sharm versus Dahab region labels may reflect hierarchy, not a different hotel.

The ranking score is a sorting value, not a probability (it can exceed 1).
Coordinates alone do not establish identity: another hotel was only 12.6 m from
the ANEX Greys Kristall coordinates. The name and candidate separation matter.
The pilot is selected from one reference page and available API cards; its 5/10
confirmation fraction must not be extrapolated to the complete catalogue.

[CSV table](anex-hotel-matching-20260907.csv) includes candidate IDs and review
reasons. [Sanitized source evidence](anex-hotel-matching-sample-20260907.json)
contains the compared fields and up to three candidates per hotel.

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
