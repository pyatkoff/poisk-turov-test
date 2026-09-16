# Independent AnyTour profile read handoff

SEARCH #1646 / canonical coordination #2530. Source-only next slice after the completed,
NO-REPLAY #2670 first 1000 live profiles. This change does not run initialization,
add data or publish an update. Existing renderer/room-normalizer claim #2660 stays owned.

## Existing consumer, explicit new mode

The existing `v2/data/hotel-details-read-v1.php` keeps its legacy `hotelId` and
`hotelIds[]` response contract and cache policy when no canonical keys are supplied.
No default consumer is switched by merging this change.

New mode is allowed only when the endpoint's resolved filesystem directory ends
in `/_preview/search3-local-candidate/data`. Query values, request paths, Host and
forwarded headers do not establish this identity. A symlink from that apparent
route to the production data directory is refused before DB access. This is an
isolation gate, not a private authentication boundary; only public profile fields
are returned. There is no HTTP writer, DDL, supplier call or automatic fallback.
Canonical requests and failures are `Cache-Control: no-store`.

GET inputs must contain `catalog=anytour` and exactly one of:

- `legacyHotelIds[]=102&legacyHotelIds[]=106`: translate already confirmed legacy
  targets and read active AnyTour profiles in one REPEATABLE READ / READ ONLY snapshot.
- `anytourHotelId=1`: read one independent profile; missing/inactive is HTTP 404.
- `anytourHotelIds[]=2&anytourHotelIds[]=1`: batch own-profile read with explicit
  requested/missing AnyTour IDs and input order.

IDs are positive integer strings/integers, exact (no leading zeros, signs, whitespace,
scientific notation or overflow). Inputs contain 1..100 entries before deduplication;
invalid batches are rejected in full before DB access. Legacy and canonical input
keys cannot be mixed. Canonical requests outside the isolated route are HTTP 403,
invalid requests 400, non-GET 405, DB/integrity failures generic 503 rather than
successful empty results. No new route or cross-origin behavior is introduced.

## Bridge response

Illustrative shape, NOT a new live readback:

```json
{
  "ok": true,
  "source": "anytour-canonical-catalog",
  "catalog": "anytour",
  "requestedLegacyIds": [102, 106, 999],
  "items": [{"id": 1, "catalog": "anytour", "revision": 1, "name": "..."}],
  "links": [{"legacyHotelId": 102, "anytourHotelId": 1}],
  "missingLegacyIds": [106, 999]
}
```

`items[].id` is always an independently allocated AnyTour ID. Do not put it into an
existing provider hotel, offer, search, selected-tour or lead ID field. Use `links`
for presentation joins; keep all transport identity/context untouched. Multiple
legacy targets can link to one own profile: retain every link and return its profile
once, in first-appearance order. Missing bridges or inactive own profiles produce
missing IDs and no usable link. Their legacy/provider profiles are never substituted.

`AnyTourCanonicalCatalog::readLegacyProfiles()` reuses `legacyTargets()` and `read()`;
there is no second mapper. At most two prepared SELECTs supply the bridge and profiles.
The snapshot prevents a concurrent bridge/editorial edit from mixing two revisions.
A corrupt profile aborts the entire read. Source JSON, provenance and internal table
metadata are not returned; content comes from the own materialized profile.

## Upstream and renderer requirements

Provider → legacy identity must first be resolved by the existing authoritative MATCH
resolver, respecting current accepted/manual/conflict/exclusion state. This endpoint
accepts resolved legacy IDs, not supplier IDs or assertions of a supplier match.
It neither imports nor caches provider identities; callers must not reuse a stale
provider resolution across searches. Numeric equality is not a mapping.

The owning renderer must batch these resolved targets, await the canonical response
before displaying the local-only card, and use the own profile for all hotel text,
gallery and traits. Search generation invalidation and selected-offer identity must
remain unchanged. Missing own coverage must be withheld/reported, not populated by
supplier text. The active renderer owner must integrate this contract separately.
This PR does not change renderer, cards, filters, rooms, meals, pricing or lead flow.

## Verification and publication boundary

Local command: `php tests/anytour_canonical_read_test.php --unit-only` runs strict
PDO doubles AND a real loopback PHP HTTP server, with no MySQL claim.
Full CI supplies a fresh dedicated loopback MySQL8 database, installs the existing
migration, tests actual scalar/batch/100-profile reads, byte-exact namespaces,
inactive/corrupt profiles, unchanged source tables, READ ONLY enforcement and a
concurrent two-connection bridge/profile change. Missing PDO/DSN fails; full SQL
never silently skips. Test fixtures are not live hotel/mapping counts.

The create-only local-preview provisioner is NO-REPLAY. Future publication needs a
separate scoped update with exact artifact/source pins, preservation of production
and existing previews, then actual endpoint readback and renderer/mobile/desktop
acceptance. CI alone is not published. Rollback of this code before activation is a
source revert; after activation revert the consumer/read change, not populated tables.
