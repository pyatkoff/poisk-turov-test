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

## Remaining product steps

1. Resolve remaining mapping candidates and grow a measured mapping cohort.
2. Confirm with ANEX the enabled method for final package-price recalculation.
   `bron` and flight inventory do not establish the final amount, supplements
   or the price of an arbitrary chosen flight option.
3. Add the reviewed adapter to the existing search boundary in an isolated
   preview, with private runtime credentials, bounded caching and request limits.
   Do not pass ANEX IDs into the existing Tourvisor or lead contracts.
4. Review full user journeys before any public activation.

## Sources

- [SAMO Online API](https://dokuwiki.samo.ru/doku.php?id=onlinest:api)
- [SearchTour_PRICES](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:prices)
- [Hotels_DETAILS](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:hotels:details)
- [FreightMonitor_FREIGHTSBYPACKET](https://dokuwiki.samo.ru/doku.php?id=onlinest:api:freightmonitor:freightsbypacket)
- [Package recalculation in booking UI](https://dokuwiki.samo.ru/doku.php?id=onlinest:agency:bron)

The provided Online API index did not establish a final package recalculation
method. The Tickets methods describe ticket operations and are not substituted
for a package quote.
