# AnyTour INT → SEARCH authoritative offer field map

Date: 2026-09-19

This handoff documents the **existing** provider-neutral INT contract for Search3. It does not add a supplier call, booking authority, price formula, persistence rule, Search3/UI change or runtime publication.

## Exact source pin

Feature base used for this document: `feature/anex-search-adapter-20260907@4c5359cd745c91fe8e5e69c4c022d660d6b47b5c`.

Relevant source blobs at that pin:

- `app/integrations/three-provider-operator.php` — `a9c2c827c03a2aab8767f17fa3dbd98393695ac0`
- `app/integrations/three-provider-flight-details.php` — `ae2fd75b113def1456dda5e3c2e2b85eaaac07c6`
- `app/integrations/three-provider-search-handoff.php` — `a2fb2acdce65a6721ea7076a4186e35e2b790ba2`
- `app/integrations/three-provider-quote-envelope.php` — current source at the feature pin
- `app/integrations/anytour-offer-snapshot-producer.php` — `3f0469eca60c49f5b650108d8f43720438fa0ad6`
- `app/integrations/andromeda-selected-quote.php` — current source at the feature pin
- `app/integrations/andromeda-price-observation.php` — `c42a325f49f6951836e2e93243c8a003f219afb5`

**Runtime pin:** none is asserted by this document. This is a source/consumer contract handoff only. Do not infer that a Search3 preview or production runtime is installed from the source SHA above.

## Hard boundary

1. `provider` is the technical owning source (`tourvisor`, `anex`, `andromeda`). `operator` is a separate supplier-reported/operator fact. They are never treated as synonyms.
2. Search3 must not add fuel, flight markup, exchange-rate arithmetic or a zero/default surcharge. It consumes the INT result.
3. A positive supplier `calc` is the authority for an Andromeda verified total. Search-time transport markup is evidence/estimate only and is not fuel.
4. A verified total does **not** authorize booking. Current provider-neutral output keeps `selection_state=disabled` and `booking_enabled=false`.
5. Missing inclusion/status data is `unknown`; absence is never rewritten as “included”, “free”, `0`, or “no surcharge”.
6. Retained offer context is short-lived. Current handoff validation requires a current context whose retained TTL is between 60 and 900 seconds.

## Public field map

| Public path / fact | Semantics | Tourvisor | Direct ANEX | Andromeda | Unknown / fail-closed policy |
| --- | --- | --- | --- | --- | --- |
| `provider` | Technical source that owns the normalized offer | yes | yes | yes | must be one of the supported providers |
| `operator.raw` | Supplier/search label, not cross-provider identity | yes | ANEX label required | yes when supplied | `null` is explicit absence |
| `operator.canonical_name` / `canonical_verified` | Canonical operator only when source proves it | not inferred | `ANEX` / true | not inferred | no guessed cross-provider equivalence |
| `local_hotel_id` | AnyTour local hotel identity | yes after mapping | yes after mapping | yes after mapping | unresolved hotel is not persisted as a local offer |
| `identity.*` | Hashed/provider-neutral exact offer identity provenance | yes | yes | yes | no raw supplier UID exposed |
| `tour.checkin` | Exact departure/check-in date | yes | yes | yes | no client-side ±date inference |
| `tour.nights` | Exact nights for this concrete offer | yes | yes | yes | never display a range for a concrete offer |
| `tour.party` | Adults/children/child ages bound to the offer context | yes | yes | yes | must match retained context |
| `tour.meal.raw` | Supplier/source meal label | yes | yes | yes | preserve raw label when family is not verified |
| `tour.meal.family` / qualifiers | Normalized family only where deterministic | supported by common normalizer | supported by common normalizer | supported by common normalizer | no guessed equivalence; `family_verified=false` when not known |
| `tour.room.raw` / `tour.placement.raw` | Authoritative source labels plus deterministic normalization | yes | yes | yes | do not manufacture room equivalence, bed or occupancy facts |
| `tour.availability` | Provider-normalized search availability facts | yes | yes | yes | source absence remains unknown |
| `tour.flight_details` at search handoff | Capability/state envelope, not an itinerary | source capability declared | source capability declared | `package_conditional`, initially `not_loaded` | `segments=[]`, baggage and price effect unknown until authoritative detail exists |
| `money.search_price` | Search-source amount/currency for the whole retained offer context | yes | yes | yes | not a promise of final payable total by itself |
| `money.fuel_charge_reported` | Search-level reported fuel fact where provider contract supports it | supported | not this field | not this field | missing fuel never becomes zero |
| `money.additional_prices_reported` | Explicit additional-price evidence accepted by provider contract | unsupported at search | APD/search surcharge evidence | transport surcharge evidence only | transport surcharge must not be relabelled as fuel |
| `money.fuel_surcharges_reported` on verified Andromeda quote | Supplier-reported type-8 / `Топливный сбор` service rows copied as evidence | n/a | n/a | yes when present | amount/currency/route evidence only; inclusion/unit/aggregation relation stays unknown unless separately proven |
| `money.transport_markups_reported` on verified Andromeda quote | Per-selected-flight supplier markup evidence | n/a | n/a | yes when unambiguous | `aggregation=unknown`; never summed by SEARCH |
| `money.operator_currency_rates_reported` | Supplier-reported FX-rate evidence | n/a | APD has separate direct-ANEX path | yes on verified quote | evidence only; quote envelope does not apply it |
| `money.calc_money_facts_reported` | Raw-safe supplier calc money facts | n/a | n/a | yes on verified quote | evidence only; no derived commission/fee arithmetic in SEARCH |
| `quote_state` | `unknown` or supplier `verified` | usually unknown at listing | unknown at listing | verified only after exact selected-offer calc | never promote an estimate to verified |
| `final_price_verified` | Owning supplier verified final total | false in ordinary search handoff | false in ordinary search handoff | true only through verified quote envelope | false/unknown if calc is absent or fails |
| `quote_evidence_digest` | SHA-256 binding of sanitized verified quote evidence | null | null | non-null for verified quote | no raw claim/UID in public handoff |
| `finalPriceReady` | Listing/store readiness, distinct from booking authority | guarded search-price contract | charter/APD protected total | only verified quote is admitted by current INT snapshot producer | false if mandatory money composition is not proven |
| `finalPrice` / `price` / `currency` | Customer listing value emitted only by an accepted price-state branch | supported when ready | charter ready or regular confirmation-required | verified positive RUB calc only for ready persistence | no fallback to base price after failed/ambiguous repricing |
| `context.generation/page/issued_at/expires_at/current_context_verified` | Exact retained search identity and freshness envelope | yes | yes | yes | stale context is rejected; do not reuse as current |
| `selection_state` | Public selection authority state | disabled | disabled | disabled | verified price does not change it |
| `booking_enabled` | Booking/bron authority | false | false | false | always false in this handoff |

## Andromeda selected-flight evidence versus listing flight details

Andromeda has a richer **selected quote** object than the ordinary listing handoff. The selected quote can safely expose whitelisted flight facts after `get_flights` / selected-flight continuation, including:

- direction (`0` outbound, `1` return), source name/date/class;
- airline code/name and flight number when unambiguous in supplier transport details;
- departure/arrival airport codes;
- departure/arrival datetime strings when supplier syntax is valid;
- duration;
- baggage code/note as supplier text;
- departure/arrival state/town/port;
- one selected-flight transport markup fact when unambiguous.

Raw flight UIDs and the retained claim remain private. The current canonical listing DTO still carries the search-time `tour.flight_details` capability envelope; it does **not** currently copy the verified selected quote flight list into that field. SEARCH must therefore not infer that listing rows already contain confirmed segment/baggage detail.

### Exact dependency for SEARCH/LOCAL

A future additive provider-neutral detail DTO is required before Search3 can truthfully render the full OTA comparison block from the local DB alone. It should carry, only when supplier-authoritative:

- outbound/return segments and segment order;
- carrier / flight number;
- airport codes;
- local departure/arrival datetimes plus explicit timezone/offset semantics;
- duration;
- baggage count/weight/unit, not a guessed parse of a free-form baggage note;
- transfer state: `included` / `excluded` / `unknown`;
- medical-insurance state: `included` / `excluded` / `unknown`;
- mandatory-charge state and amount/unit/inclusion provenance;
- selected-offer refresh outcome (`unchanged`, changed-price, unavailable) bound to the same exact identity/context.

Until that contract is added, SEARCH should show only existing authoritative fields and explicit unknowns. INT should not take over SEARCH/LOCAL files to fill this gap.

## Price-state matrix

### 1. Ready charter listing

Typical direct-ANEX charter path: the provider-normalized search offer plus protected APD arithmetic proves a complete customer listing total. The persisted DTO can have:

```text
provider=anex
quote_state=unknown
final_price_verified=false
finalPriceReady=true
price == finalPrice
currency=RUB
selection_state=disabled
booking_enabled=false
```

`finalPriceReady=true` here means **listing-ready**, not supplier-selected/booking-ready.

### 2. Regular/GDS confirmation-required listing

Direct-ANEX regular/GDS may be stored only through the explicit confirmation-required branch:

```text
provider=anex
quote_state=unknown
final_price_verified=false
finalPriceReady=false
finalPrice=null
price=<current search price in RUB>
selection_state=disabled
booking_enabled=false
```

SEARCH may label this as requiring confirmation/revalidation, but must not call it a confirmed final total.

### 3. Andromeda verified selected quote

Only owning-supplier `calc` establishes the verified total. The verified envelope binds provider/operator/local hotel/exact retained context and emits:

```text
provider=andromeda
quote_state=verified
final_price_verified=true
quote_evidence_digest=<64 lowercase hex>
finalPriceReady=true        # added by the INT snapshot producer after verified positive RUB validation
price == finalPrice
currency=RUB
selection_state=disabled
booking_enabled=false
```

Supplier-reported fuel rows, selected-flight markup rows, rates and calc money facts remain separate evidence. They are not reverse-engineered into a fuel formula.

### 4. Andromeda search-time transport estimate

Current policy is intentionally fail-closed. Even if the low-level price helper can validate a `party_transport_surcharge` estimate, `AnyTourIntOfferSnapshotProducerV1` **holds Andromeda unverified estimate rows** and does not persist them as a full customer total. This prevents flight markup from masquerading as fuel/final price.

### 5. Stale or unavailable

There is no public “pretend current” fallback. The handoff validates the retained identity and freshness before emitting a current DTO. If the context is stale/mismatched, it is rejected and requires a provider-owned refresh/revalidation. A cached DB row, including an earlier `final_verified` row, does not gain booking authority merely because it exists locally.

## Changed-price semantics

For an Andromeda selected quote the existing observation object compares the search estimate, when one exists, with the supplier-verified final price. Its states are:

- `comparable` — same currency; signed/absolute delta and relative basis points are available;
- `estimate_unavailable` — no safe search estimate existed;
- `currency_mismatch` — both values exist but cannot be compared without inventing conversion arithmetic.

This observation is evidence, not a product threshold or auto-confirmation rule. SEARCH must not turn it into a confirmation probability.

## Data requested by OTA/mobile comparison that is currently unsupported or incomplete

The following are **not** safe to manufacture from the current listing DTO:

- supplier-authoritative confirmation probability;
- exact timezone semantics when a datetime has no explicit offset;
- structured baggage count/weight/unit when only a supplier baggage string exists;
- transfer/insurance included/excluded state when no authoritative service fact exists;
- mandatory fee inclusion/aggregation when amount/unit/PRICE-inclusion is unproven;
- flexible departure ±1/2/3 days as a capability. The current canonical search offer binds one exact `checkin`; no provider-neutral contract here proves that ±day is an actual supplier query parameter/result set.

For each of these, the correct current state is `unknown` / unsupported, not a guessed UI value.

## Fuel-specific constraint

The retained-FULL-claim sanitizer added in #3009/#3010 is the evidence reader for the remaining Andromeda fuel question. It deliberately keeps current services separate from alternative services and only reports bounded facts such as required/dependency/packet, amount/currency/unit, clients/common and explicit PRICE-inclusion flags. Missing inclusion stays `unknown`; conflicting flags stay `conflicting`; generic flight markup / `party_transport_surcharge` is not promoted to fuel.

No new supplier operation should be launched solely to populate UI examples. Existing terminal/no-replay supplier operations remain sealed.

## Current operational evidence, not a new runtime claim

The latest accepted receipts in the canonical queues already establish that the original “nonzero local provider rows” objective is no longer at zero:

- direct ANEX: 2,080 active/unexpired latest-snapshot rows in the owner-scope readback, with 1,484 listing-ready and 596 confirmation-required;
- Andromeda: the latest terminal mass-fill receipt remains at 135 canonical active/ready/verified rows.

Those counts are evidence of the existing local-store path. This document neither refreshes them nor claims they are immutable; currentness still follows the normal TTL/refresh contract.

## SEARCH implementation handoff

SEARCH can safely consume now:

- exact provider/operator/local-hotel/offer identity;
- exact dates/nights/party/meal/room/placement and normalized availability;
- current price-state fields without doing arithmetic;
- `finalPriceReady`, confirmation-required versus verified quote state;
- quote freshness/context and the fact that booking remains disabled.

SEARCH must wait for an additive INT/LOCAL detail contract before presenting selected-flight segment/baggage/timezone and transfer/insurance/mandatory-charge inclusion as confirmed local-DB facts. That missing detail contract is a dependency, not permission for UI inference.
