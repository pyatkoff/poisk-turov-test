# ANEX fuel surcharge: bounded diagnostic checkpoint

Date: 2026-09-09. Owner explicitly requested investigation and then authorized a
real-offer control without creating a booking. This branch is diagnostic only;
it does not replace the ANEX/Search3 product or hotel-review lanes.

## Completed in this invocation

- Base: `e8a7444c2bbab35f5507f674cad7ebb3ac6f4652` from
  `feature/anex-search-adapter-20260907` / draft #1493.
- New isolated branch: `diagnostics/anex-fuel-quote-20260909`.
- `scripts/diagnostics/anex_fuel_quote_probe.py` reuses the existing ANEX
  request transport from the exact checkout, loaded in memory on the authorized
  AnyTour SSH host. No server/application configuration or database is read.
- The prepared probe allows at most seven requests: one claimless
  `Booking_CalcClaim` dispatch/capability request and six search/dictionary
  requests for Moscow -> Egypt, one departure day, approximately seven nights,
  two adults, no children. Supplier IDs are retained only in memory; the report
  uses an offer fingerprint. The existing 1.05-second request pacing is reused.
- Init, save, ticket booking and payment methods are absent from the allowlist.
  There is no retry after an unknown SSH or supplier outcome.
- Eight local offline tests passed. They cover amount precision, unknown fuel
  inclusion, no promotion of a search price to a quote, request budget,
  missing/invalid token, raw-reference exclusion, supplier error classification,
  and secret redaction. All sample values are explicitly test fixtures.

## Important distinction

The first-stage `Booking_CalcClaim` request has **no claim identifier**. It can
report a dispatcher/access/required-parameter error. It cannot confirm a full
package price, fuel surcharge, or availability of a complete booking workflow.
A required-parameter response must not be classified as access denied.
`final_price_verified` remains false; absence of a fuel field remains unknown,
not a zero surcharge or evidence that the search price includes fuel.

## Exact live-execution blocker

Creating `.github/workflows/anex-fuel-quote-probe.yml` through the connected
GitHub write action was blocked by the tool safety system. The action did not
return a workflow commit. No live diagnostic was started and no ANEX response
was obtained in this invocation. This is NOT an ANEX permission error and NOT
a demonstrated lack of Booking methods on the supplier endpoint.

The blocked action was not retried through another workflow, tool, server or
credential route. Production, preview, hotel mappings, Tourvisor, Metrica and
lead delivery were not changed. No supplier API error code may be inferred from
this tool failure.

## Remaining execution sequence

1. Run the prepared first-stage diagnostic through an authorized execution path
   after the execution block is resolved; retain exact source SHA and sanitized
   response metadata. Do not restart hotel matching/catalogue work.
2. Retrieve and verify the supplier's enabled full-package quote contract.
   Only then implement the actual temporary-claim/selected-flight quote sequence.
   The first-stage probe is deliberately not that sequence.
3. Compare one concrete search offer with the supplier-calculated package for
   the same hotel, dates, room, meal, party, flight option and currency. Read
   mandatory service details; do not label the entire price difference as fuel.
4. Do not call any save/booking/payment operation. Do not expose a final price or
   change public pricing until the live quote and mandatory-service semantics
   are verified. No arbitrary per-person fuel amount or double addition.

## Verified public reference

- SearchTour_PRICES:
  https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:prices
  `convertedPrice` is conversion to the requested currency, not a surcharge
  calculation. `bron` is the search-time booking flag, not a final-price quote.
- API transport:
  https://dokuwiki.samo.ru/doku.php?id=onlinest:api

The full Booking pages were not successfully retrieved through the web reader
in this invocation. A historical mirrored index contains the Booking method
names; it is not treated as proof of the current ANEX contract or token access.
