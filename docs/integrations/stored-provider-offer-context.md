# Same stored offer -> retained native provider context

This is the server-side recovery step for the owner's 2026-09-21 requirement:
select the same ANEX/SAMO offer displayed from the AnyTour database, not a new
Tourvisor search for a similar offer. It does not enable customer selection yet.

`AnyTourStoredProviderOfferContext` consumes one **current server-read** item from
`AnyTourOfferStoreReadV2` and an already-authorized, locked private provider snapshot.
Never pass browser-supplied row contents or accept the three public digests as
session/CSRF authority. The caller must resolve the requested scope and read its
current row before invoking this adapter.

The adapter finds the exact offer by all three stored SHA-256 identities. It keeps
own hotel ID and legacy/native IDs separate, checks current canonical identity
through the required callback, compares the stay/party/room/meal/placement, and
rejects ambiguity, group minima, absent raw references or expired snapshots. The
24-hour listing lifetime never renews the existing 900-second native context.
Missing or expired offers do not trigger any supplier search or fallback offer.

`resolveAndromeda()` then invokes the existing `AnyTourAndromedaSelectedOffer::resolve`
and its current MATCH callback. Its result contains PRIVATE package context and
must not be serialized to a browser. `resolveAnex()` invokes the existing gateway's
`offer` action against a value copy of the same private session. The configured
current ANEX registry is still checked; the token-bearing client is never created.
No original session/state or prices are mutated. Existing supplier API, quote,
reservation/no-replay, price/fuel and lead contracts remain unchanged.

## Remaining runtime handoff

The prototype HTTP endpoints are deliberately unchanged in this packet. INT must
supply a trusted current-DB-row -> private snapshot locator under the existing
locks/session rules, then use the returned exact context through the existing
package/quote methods. A public handle must bind the viewer, scope and exact offer;
raw supplier IDs stay private. A successful context lookup is not a verified quote,
final/fuel-inclusive price, available flight or permission to send a lead. Only the
existing supplier-confirmed quote can authorize those later stages. Background
captured or UNKNOWN operations must never be replayed to manufacture a context.

## Tests

`php tests/stored-provider-offer-context-smoke.php` checks the pure recovery with
fictional retained snapshots. `--native` additionally uses the actual ANEX gateway,
Andromeda store/selector and existing DTO conversion; no mock provider classes.
The focused GitHub workflow always runs `--native`, has contents-read permission
only, and has no SSH, secrets, provider credentials, browser or publication steps.
These are server contract tests, not a browser or live-inventory acceptance claim.
