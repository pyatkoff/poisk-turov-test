# SEARCH #1646 — local-catalog-first read contract

Owner direction: 2026-09-16. Coordination: #2530; #996 is read-only history.
This is an implementation handoff inside the existing product queue, not a new
roadmap or authorization for supplier, matching, schema or production changes.

## Implemented slice

`v2/data/hotel-presentation-read-v1.php` is the single read/DTO owner for local hotel
profiles. The existing `hotel-details-read-v1.php` consumes it for both scalar and
batch requests. No separate public endpoint, table, connection configuration,
writer, supplier request or browser asset is added.

The query reads active `catalog_hotels` and joins only successful
`catalog_hotel_details`. It uses one prepared SELECT for 1–100 local IDs, binds IDs
as integers, deduplicates them and returns profiles in request order. The request
limit applies before deduplication. Invalid batches are rejected in full; they are
not silently truncated. Missing/inactive IDs are explicit and never gain a supplier
fallback. Missing successful details remain `detailsAvailable=false` with unknown
fields; they do not acquire a generated description or a fabricated type.

Safe local media uses the existing pure URL validator, including the primary
image; primary comes first, images are deduplicated and capped at 100. Both read
modes expose the same DTO. No provider token, offer price or tour is added to it.

## HTTP contract

Existing request remains supported:

```text
GET data/hotel-details-read-v1.php?hotelId=10
200 {ok:true,item:{...existing profile fields...},source:"anytour-local-hotel"}
404 {ok:false,error:"Hotel not found"}
```

New bounded request (IDs here are illustrative, not real availability evidence):

```text
GET data/hotel-details-read-v1.php?hotelIds[]=10&hotelIds[]=11&hotelIds[]=99
200 {
  ok:true,
  items:[{id:10,...},{id:11,...}],
  requestedIds:[10,11,99],
  missingIds:[99],
  source:"anytour-local-hotel"
}
```

Only an indexed list is accepted for `hotelIds`; comma strings, mixed scalar/batch
parameters, nested/associative arrays, invalid IDs and >100 entries return 400.
An entirely missing valid batch returns 200 with empty `items` and all requested
IDs in `missingIds`. A database failure is 503, not a successful empty catalogue.
Successful reads retain the existing public cache policy; error responses are
`no-store` so a transient failure is not cached as a profile.

Only **local** hotel IDs are accepted. Numeric equality with a provider hotel ID is
not a match certificate. INT/MATCH must supply an already accepted local identity;
this reader does not resolve provider IDs or search names.

## Consumer handoff — not implemented by this slice

The current renderer still requests single local details after first rendering.
Its shared ownership is not taken by this package. Its next coordinated change
should batch the explicit local IDs for the current search generation, validate
response IDs/provenance and construct hotel cards from those local profiles before
display. A supplier response supplies offers, not replacement hotel text/media.
Responses from a superseded search must not be attached to the new search.
Missing profiles must not be replaced with raw supplier cards. Decide the minimal
presentation-readiness contract explicitly rather than guessing from ID alone.

Existing server consumers can call `hotel_presentation_read_many($pdo, $ids)` and
attach already-normalized offers to the returned local profile. They must retain
exact provider/search/offer IDs, raw values, freshness and the established price
readiness/fuel and selected-tour contracts. This module does not certify an offer,
calculate a minimum, store offers or accept browser-supplied prices.

Local `roomTypes` text and `meals` JSON remain the existing hotel-detail fields.
They are **not** a normalized room inventory or an accepted supplier-room/meal
mapping. A local room/meal dictionary and server-side normalized offer storage are
separate outstanding inputs, not declared complete by this read-path change.
Hotel traits can be sparse; this reader applies no population-completeness gate.

## Verification and release boundary

`tests/search3-local-hotel-catalog-test.php` exercises parsing/DTO safety and, with
`pdo_sqlite`, runs the actual SQL and the actual HTTP endpoint against a disposable
fixture database. It checks one-query batches of 100 profiles, duplicate IDs,
missing/inactive profiles, unsuccessful details, scalar/batch parity, unsafe media,
invalid input, no database writes by the reader and a fail-closed 503.

`--unit-only` is an explicit partial local mode and prints SQL/HTTP NOT_RUN. CI
runs the full test, never that partial mode. Fixture records are not live catalogue
coverage or supplier availability evidence. Existing media normalization tests
remain required. Existing whole-site/security checks apply as selected by CI.

No production migration or preview publication is implied. Rollback is the revert
of this isolated source commit; no database rollback is required because this
package writes no catalogue or offer data. Direct visual acceptance is deferred
to the separately coordinated renderer consumer change.
