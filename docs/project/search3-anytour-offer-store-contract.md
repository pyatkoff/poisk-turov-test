# Search3 AnyTour offer store v1

## Purpose

Search3 needs one current-offer model that is independent of Tourvisor hotel identity. Hotel presentation belongs to the independent `anytour_hotels` catalogue; tour suppliers only contribute concrete offers. This store is the bounded persistence layer between already validated provider search output and a future DB-first Search3 listing.

This is **not** a replacement for the existing `tour_price_observations` / daily price intelligence. Those tables are useful historical Tourvisor observations and SEO/price analytics, but their identity model is Tourvisor-specific (`search_id`, legacy `hotel_id`, numeric meal/room/operator ids), records may have unknown fuel, and they have no provider-complete refresh semantics. They remain history/analytics. `anytour_offers` is current customer-listing state.

## Provider model

`tourvisor`, direct `anex`, and `andromeda` are equal provider namespaces. Provider is not tour operator. A persisted row is attached to an explicit independent `anytour_hotel_id` only after the caller supplies the already resolved legacy-catalog bridge. Equal numeric supplier ids never create a mapping.

The input is the browser-safe INT → SEARCH customer DTO after provider normalization and surcharge readiness. v1 accepts only:

- `finalPriceReady=true`;
- positive RUB `finalPrice` equal to `price`;
- provider-qualified SHA-256 search / offer / provider-hotel identity;
- explicit resolved legacy hotel id plus a current `legacy_catalog → anytour_hotel_id` bridge;
- exact date, nights and party;
- no supplier-private code exposure;
- selection still disabled and booking disabled.

The store does not calculate ANEX `AdditionalPricesDaily`, SAMO/Andromeda transport additions, Tourvisor fuel, exchange rates, or any other money. Unknown mandatory surcharge never falls back to the base price.

## Persisted listing vs selected offer

The source search context has a short lease and must never become a long-lived selection authority. Therefore the stored browser payload deliberately drops `context`, `local_hotel_id`, `quote_state` and quote evidence and returns:

- verified listing facts and price;
- provider/operator and hashed provider offer identity;
- `selection_state=refresh_required`;
- `booking_enabled=false`.

A click on a cached offer must reacquire current provider context and revalidate/requote before selection or lead submission. v1 does not persist raw supplier-private offer ids. A later INT-owned private locator layer may bind the safe identity digest to a current source locator without exposing it to the browser.

## Refresh semantics

A refresh is scoped by `(provider, provider-neutral search-scope SHA-256)`.

- Only one active refresh per provider/scope is allowed.
- Lease expiry marks the abandoned attempt; it never replays supplier work.
- Upserts from one provider cannot write another provider namespace.
- A **successful complete** refresh deactivates unseen rows only for the same provider and scope.
- Abort/partial/error refresh keeps the previous complete snapshot intact.
- Different providers coexist for the same scope and AnyTour hotel.
- Rows have an explicit expiry; v1 caps caller TTL at six hours. Initial Search3 consumers should use much shorter TTLs and vary refresh frequency by departure proximity.
- Expired rows are never read.
- Payload SHA-256 is verified before a row is served.

## Scope digest

The store treats the scope digest as an opaque provider-neutral key. The future SEARCH orchestrator must derive it from canonical user intent, not supplier ids: departure, destination, date window, nights, party and offer-affecting filters. Provider-specific transport ids stay outside that key.

## Existing data

The existing Tourvisor collectors continue writing their historical observation and SEO tables unchanged. No current historical row is automatically promoted into `anytour_offers`, because old observations are not guaranteed to have complete mandatory surcharge or current provider identity. A later backfill may promote only rows that can satisfy the same fail-closed readiness contract.

## Installation boundary

`20260916-anytour-offer-store.sql` is additive and depends on the independent AnyTour canonical catalogue migration. This PR does **not** run it on the live database, change main/production, enable provider endpoints, call suppliers, or publish Search3. Live installation and provider consumer wiring require separate checked operations.
