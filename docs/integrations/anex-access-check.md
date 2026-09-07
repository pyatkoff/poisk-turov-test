# ANEX access check for anytoour.ru

ANEX is an external supplier of tours and reference data for AnyTour. This
diagnostic belongs only to `pyatkoff/poisk-turov-test` and uses the existing
AnyTour SSH connection. The owner supplied two tokens and confirmed that ANEX
has allowed the AnyTour server IP (2026-09-07).

## Configuration

Repository Actions secrets:

- `ANEX_API_TOKEN`: Online SAMO API credential.
- `ANEX_REFERENCE_TOKEN`: XML reference credential.
- Existing `ANYTOOUR_DEPLOY_HOST`, `ANYTOOUR_DEPLOY_USER`, and
  `ANYTOOUR_DEPLOY_SSH_KEY`: AnyTour SSH transport.

Supplier base: `https://parser.anextour.ru/`; documented request path:
`/export/default.php`.

## Bounded read-only check

The isolated `diagnostics/anex-access-20260907` branch has a push-triggered
workflow, `.github/workflows/anex-access-probe.yml`. It first runs offline tests,
then enters the existing `$HOME/www/anytoour.ru` directory and executes the
diagnostic in memory over SSH on the existing AnyTour host.
There is no application bootstrap, database connection, package installation,
site deployment, booking request, or persistent server file.

At most four requests are made:

1. Online API `SearchTour_TOWNFROMS`: departure-city count (documented GET).
2. XML `currentstamp`: validate synchronization stamp.
3. XML `state`: first country batch only, using the stamp (no pagination).
4. XML `townstate`: available departure/destination route count.

Each request has a 20-second socket timeout and a 2 MiB response limit. The SSH
process has an overall 110-second timeout. HTTPS certificate verification stays
enabled and redirects are refused. Tokens travel via encrypted SSH stdin;
both supplier interfaces receive tokens in the documented HTTPS query format.
No response bodies, token-bearing URLs, exception messages, or secret values
are printed. Only fixed status names, HTTP codes, elapsed time, and counts leave
the process. The SSH key is held in a temporary mode-0600 runner file, removed
when the process completes; the SSH child environment excludes these secrets.

SSH uses `accept-new` with a temporary known-hosts file. This is trust on first
use for each runner, matching the unpinned existing transport; it is not an
independently verified, persistent server fingerprint.

Passing confirms only these read methods. Price search, live availability,
booking permissions, full reference import, Andromeda, and Tourvisor are outside
this diagnostic. No change to the site's live search is made.

## Verified result: 2026-09-07

Initial run [34140006120](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34140006120)
reached the AnyTour host successfully. XML returned HTTP 200 for `currentstamp`,
278 `state` records, and 850 `townstate` records. The API POST request returned
HTTP 500. The follow-up uses the exact documented GET format to distinguish
POST dispatch behaviour from a general API-access failure.

Follow-up [run 34140139672](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34140139672)
on source `85b2e7d93953e98c164e76217655b14fc08a0572` passed at
15:48 UTC, including all 12 offline tests and the SSH-side checks:

| Read method | HTTP | Records | Supplier request time |
| --- | --- | --- | --- |
| `SearchTour_TOWNFROMS` (GET) | 200 | 77 | 125 ms |
| `currentstamp` | 200 | 1 | 192 ms |
| `state` (first batch) | 200 | 278 | 204 ms |
| `townstate` | 200 | 850 | 104 ms |

Both supplied credentials work for these methods from the existing AnyTour host.
GET succeeds where the tested POST returned 500, so use the verified GET format
for this integration. The run does not establish the supplier's internal cause
for the POST error. Country and route counts are raw supplier records, not
necessarily the number of actively sold countries or unique routes.

The next integration step is a bounded tour-price lookup with the available
departure/destination IDs and then provider-specific mapping into AnyTour.
No tour-price, availability, full import or booking result is claimed here.

## Tour-price diagnostic (owner continuation)

The owner requested a real tour-price search after the access check.
`anex_access_probe.py --prices` now uses the same in-memory SSH execution from
the AnyTour directory. The isolated workflow invokes that mode instead of
repeating the already verified XML reference check.

Six read-only API methods select the request using supplier data:
`TOWNFROMS` → `STATES` → `CHECKIN` → `CURRENCIES` → `NIGHTS` → `PRICES`.
Moscow and Turkey are preferred by labels/ISO metadata, with two adults and no
children. The departure date comes from the availability string within the next
60 days; nights prefer seven from the `places` list. Currency ID also comes
from the API. No provider IDs or offer records are invented.

The first price page uses `FREIGHT=1`, `FILTER=1`, `DYN_SEPARATE=1` and hotel
minimum-price grouping. One returned group is then expanded with its own
`CATCLAIM` and `HOTELS` identifiers, removing `PARTITION_PRICE`. A group with
hotel availability is preferred. Local supplier prices are inspected; external-result
polling is not performed. SAMO may initiate external-source searches internally.
The report contains at most three sample offers with allowlisted hotel/meal/date
fields, native price/currency, and separately parsed converted price/currency.
Unknown fields, URLs, search/offer identifiers and supplier error text are
excluded. Known credentials are also rejected from textual output fields.

Only full packages for the selected dates, nights and traveller composition
with valid positive prices are accepted as samples. Booking flags, grouping,
hotel availability and economy-flight availability are reported as returned;
they do not establish final quote actualization. The diagnostic creates no
booking, lead, payment, database record or server file. It does not change the
site search. Each response stays capped at 2 MiB with a 20-second socket timeout;
price mode has a 290-second SSH deadline and a maximum budget of 12 API reads.

Initial price [run 34140983282](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34140983282)
on source `24112883b68c5d2df187645282ec46c451349d3e` succeeded at 15:58 UTC:
Moscow → Turkey, 2026-09-14 to 2026-09-21, seven nights, two adults, no children.
The first page contained 300 valid full-package grouped rows. Native prices
were EUR with separately returned RUB equivalents. The first sample was The
Lola Hotel, 773 EUR / 82,796 RUB, room Economy Double Room, RO. Both economy
flight flags were Y, while hotel availability was RRRR (on request). This is a
search result at the recorded time, not a confirmed booking or final quote.
The follow-up expands a returned group to verify a concrete ungrouped offer.

### Price method documentation

- [Available countries](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:states)
- [Available departure dates](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:checkin)
- [Currencies](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:currencies)
- [Nights and seats](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:nights)
- [Tour prices](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:prices)

- [Online SAMO API](https://dokuwiki.samo.ru/doku.php?id=onlinest:api)
- [XML gateway and reference synchronization](https://dokuwiki.samo.ru/doku.php?id=samotour:xml_gate)
