# ANEX server adapter for AnyTour

The owner approved implementation on 2026-09-07 after the access, price and
ten-hotel identity pilots. This is an isolated, read-only integration in
`app/integrations/`, the external-adapter ownership zone in `ARCHITECTURE.md`.
It does not introduce a second public search application or Tourvisor adapter.

## Implemented boundary

- `hotel-identities.php` and `data/anex-hotel-identities.json`: explicit
  source-qualified identities. Five pilot mappings resolve only with the
  `preview` scope; five uncertain candidates remain unavailable to the resolver.
  Existing `catalog_hotels.id` values are retained. No SQL migration or DB write
  is performed. See [identity contract](anex-hotel-identities.md).
- `anex-client.php`: eight allowlisted read methods at the fixed ANEX HTTPS
  endpoint, token in the documented GET query, strict TLS, no redirects/proxy,
  bounded body/time/request count, fixed errors and token redaction.
- `anex-normalizer.php`: provider-qualified hotel/room/meal identifiers,
  decimal-string amounts, separate original and supplier-converted prices,
  grouped minimum versus concrete offer, explicit unmapped hotels and unknown
  availability. Full packages only (`packetType=0`); infant pricing is outside
  this pilot. Search price is never marked as a final quote.
- `anex-search.php`: one in-memory search session, expansion of known groups,
  rejection of cross-hotel expansion rows, and flight options for known concrete
  offers. Replacement searches invalidate old selectable offers even on error.
  It is an internal class, not a publicly callable route.

The normalizer contract is provider-independent where semantics are established:
hotel identity, dates, party, room/meal descriptions and price/currency. Supplier
IDs remain explicitly namespaced and opaque references remain server-side.
Future Andromeda and Tourvisor integrations require their own verified adapters;
no assumption is made that their fields or identifiers are interchangeable.

## Usage

```php
require_once 'app/integrations/hotel-identities.php';
require_once 'app/integrations/anex-search.php';
$identities = AnyTourHotelIdentityRegistry::fromFile();
$client = new AnyTourAnexClient($tokenFromPrivateRuntime);
$search = new AnyTourAnexSearch($client, static function ($source, $id) use ($identities) {
    return $identities->resolve($source, $id, 'preview');
});
// All criteria IDs must first be resolved from ANEX dictionaries.
$groups = $search->search($anexCriteria);
$offers = $search->expand($groups['offers'][0]['offer_key']);
$flightOptions = $search->flights($offers['offers'][0]['offer_key']);
```

Required criteria: `supplier_namespace=anex_online`, `departure_id`,
`destination_id`, `currency_id`, `checkin_begin`, `checkin_end`, `nights_from`,
`nights_till`, `adults`, `children`; children require `child_ages`. Optional
`hotel_ids` are ANEX IDs. This example omits empty/error UI handling because it
is an internal API, not an HTTP controller. Never expose `supplier_offer_id`
or dump the internal result as a public response.

## Verification and delivery

The existing ANEX diagnostic workflow owns focused PHP smoke tests and the live
adapter check. Python tests protect SSH/stdin and report boundaries. The live
check selects a current departure/date/currency through ANEX dictionaries, then
loads the exact checked PHP modules into an in-memory process under
`$HOME/www/anytoour.ru`. It creates no remote files, reads no site configuration
or DB, changes no pages and makes no booking. Secrets travel only through SSH
stdin and then local PHP stdin. Output is projected again onto a small field
allowlist; native offer references, URLs and unknown supplier fields are removed.

The first attempt targets the three confidently mapped Egyptian hotel IDs (or
the Istanbul pilot hotel if Egypt is unavailable). A single empty-result fallback
may remove that hotel filter. This is a bounded identity/search check, not a
coverage measurement. External dynamic results identified by `searchKey` are
reported as pending and are not polled in this version.

Source is based on current release `6626ac58e27c85229de0f4e2d60cbbb2f5da5221`,
with the existing diagnostic evidence from draft #1484. Source changes are held
in `feature/anex-search-adapter-20260907`; no merge, preview publication or
production deployment is part of this batch.

## Verified result: 2026-09-07T17:13:22.9598912Z

[Adapter run 34146672980](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34146672980)
passed on exact source `51c25555d5b797925c1aab21b5954397237016fa`.
[Security guard](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34146676080)
passed on the same source. PHP syntax and all four focused smoke suites passed:
identity registry (159 assertions), client (13 cases), normalizer and integrated
search/expansion/flight handling. All 34 Python tests passed in the same job.

The live selection was Moscow to Egypt, departure 2026-09-14, seven nights,
two adults, no children. The initial filter for the three Egyptian pilot hotel
IDs returned no usable offers for that selection; the documented single fallback
removed this hotel filter. It returned 161 normalized search rows, with zero
rejected rows and no external dynamic search pending. Expanding one hotel group
returned 18 concrete proposals. All remained unmapped to the five-entry preview
registry; no fallback to supplier IDs or unreviewed name matching occurred.

| Hotel / room / meal | ANEX hotel ID | Search amount | Supplier conversion |
| --- | ---: | --- | --- |
| Swiss Heaven Sharming Inn Hotel / Standard Room / AI | 5844 | 1222 USD | 112681 RUB |
| Swiss Heaven Sharming Inn Hotel / Standard Room / AI | 5844 | 1233 USD | 113695 RUB |
| Swiss Heaven Sharming Inn Hotel / Pool View Room / AI | 5844 | 1260 USD | 116185 RUB |

`Hotels_DETAILS(5844).id` agreed with the selected `PRICES.hotelKey`. The three
shown proposals had the supplier booking flag enabled; their native offer IDs
are intentionally not published. Similar visible descriptions do not establish
duplicate offers. Amounts are the response at the recorded time, not final quotes.

Flight inventory returned two directions (Moscow–Sharm El Sheikh and return),
one option per direction named `Регулярный`. Documented `yesplace/noplace`
statuses now survive as Y/N. The normalized result has no actual flight number,
carrier, airports, times or baggage for this selection, and
`itinerary_details_available=false`. This does not establish why details were
absent from the adapter result or whether another flight selection would expose
them. No flight was selected and no final price or baggage inclusion was claimed.

The check made five dictionary requests through the proven diagnostic transport
and five requests through the new PHP client (initial search, fallback search,
group expansion, hotel card, flight inventory). No booking, DB write or server
file change occurred. Existing live local-hotel attachment remains unverified:
the registry behavior is covered by focused tests, while this live run validates
the unmapped path. A follow-up should match an actually returned hotel such as
5844 before demonstrating an existing AnyTour card with a live ANEX offer.

[Final sanitized evidence](anex-adapter-verified-20260907.json) and
[first-run evidence](anex-adapter-first-run-20260907.json) are retained. The first
run already proved search and expansion; source review then corrected the
documented flight-status strings and numeric baggage handling before the final
run. No supplier raw bodies or tokens are stored in these evidence files.

## Full hotel catalogue and first exact pass: 2026-09-07

[Catalogue run 34150890531](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34150890531)
completed on source `f148feaa8d83ea1af542e67d5ddfbb3450194cad`.
It downloaded all paginated ANEX XML hotel and geography references, then read
active `catalog_hotels` rows from AnyTour in bounded 5,000-row, read-only pages.
No mapping or catalogue row was written.

| Result | Count |
| --- | ---: |
| ANEX hotel IDs | 39,638 |
| Active AnyTour catalogue hotels | 133,417 |
| Strict automatic mappings | 10,611 |
| Unique AnyTour hotels in strict mappings | 8,922 |
| Exact-name candidates for manual review | 8,358 |
| No exact-name candidate | 20,669 |

The first pass is intentionally exact-only. A supplier hotel is marked
`verified_auto` only when its normalized ANEX name has exactly one AnyTour
candidate with the same normalized country and matching town or region. An exact
name with ambiguous or insufficient geography is `review`; all other active
hotels are `unmatched`. Multiple ANEX IDs can legitimately point at one AnyTour
hotel and remain separate source-qualified identities.

The workflow artifact
[anex-hotel-catalog-match](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34150890531/artifacts/10029330453)
is retained for 30 days and contains:

- `anex-hotel-verified.csv`: strict mappings ready for a review sample;
- `anex-hotel-review.csv`: exact-name candidates requiring a decision;
- `anex-hotel-unmatched.csv`: hotels with no exact-name candidate;
- `anex-hotel-catalog-match.json`: the complete source-qualified report.

The XML snapshot also contained 278 states, 10,657 towns and 112 star values.
Country is derived from the state on each town reference, with the 850-entry
`townstate` reference as a fallback. Transient reference-page failures are
retried up to four times with bounded backoff; an incomplete catalogue is never
published as a successful artifact.

## Remaining product steps

1. Review a sample of the 10,611 strict mappings, then import accepted identities
   through a separately reviewed, idempotent registry update.
2. Work through the 8,358 exact-name candidates. Keep explicit accept/reject
   decisions so later catalogue refreshes do not recreate resolved work.
3. Run a second, separately measured fuzzy pass over the 20,669 unmatched hotels.
4. Confirm with ANEX the enabled method for final package-price recalculation.
   `bron` and flight inventory do not establish the final amount, supplements
   or the price of an arbitrary chosen flight option.
5. Add the reviewed adapter to the existing search boundary in an isolated
   preview, with private runtime credentials, bounded caching and request limits.
   Do not pass ANEX IDs into the existing Tourvisor or lead contracts.
6. Review full user journeys before any public activation.

## Sources

- [SAMO Online API](https://dokuwiki.samo.ru/doku.php?id=onlinest:api)
- [SearchTour_PRICES](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:prices)
- [Hotels_DETAILS](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:hotels:details)
- [FreightMonitor_FREIGHTSBYPACKET](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:freightmonitor:freightsbypacket)
- [Package recalculation in booking UI](https://dokuwiki.samo.ru/doku.php?id=onlinest:agency:bron)

The provided Online API index did not establish a final package recalculation
method. The Tickets methods describe ticket operations and are not substituted
for a package quote.
