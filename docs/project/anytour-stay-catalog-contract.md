# AnyTour: canonical rooms and meal plans v1

SEARCH #1646; coordination #2530 (claim 5699702197). Additive continuation of
independent hotel catalogue #2666, release baseline
`eb7d91dab537879ba6bf1cae6c0db213bd5d5c4d`.

## Ownership and actual scope

`anytour_meal_plans` defines our 10 distinct Russian meal categories. Its database
IDs are not supplier codes. `family_code` is for broad filtering, never for exact
offer equivalence. HB/HB+, FB/FB+, AI/UAI/Soft/alcohol-free retain distinct IDs.
The seed does NOT claim particular drinks, restaurant access or meal times.
A hotel's meal concept and a concrete offer's inclusions remain separate inputs.
There are no automatic supplier mappings, including for a literal `AI`.

`anytour_room_categories` is a broad local classification (10 initial categories),
not a list of actual hotel rooms. `anytour_hotel_rooms` owns each hotel's specific
room type, its Russian name and confirmed view/building/bedroom/area/capacity facts.
Unknown facts are absent/null, not zero/false. A studio may explicitly have zero
bedrooms. General `standard` classification never merges sea/land/building variants.
Room descriptions/photos and guaranteed beds/tariff inclusions are not populated
by this slice. No room is created by splitting legacy room-description text.

`anytour_stay_mappings` resolves the exact tuple
`namespace + source hotel key + operator key + kind + key kind + external key`.
A source ID and label are different key kinds. Keys retain bytes, case and leading
zeros. In v1 even meal mappings are hotel-scoped: there is no wildcard/global
fallback that can override a hotel's negative or more specific decision.

The mapping references an existing `anytour_hotel_sources` relation. This package
neither changes that relation nor performs MATCH. A composite FK prevents a room
from belonging to a different canonical hotel; each read also joins the CURRENT
source relation and refuses `source-drift` if it now points elsewhere. Updating a
source snapshot without changing its hotel does not overwrite canonical room/meal
content. Different sources can point to the same reviewed local room; that still
does not make their flights, occupancy, tariffs, availability or prices identical.

## PHP repository, no HTTP writer

`v2/data/anytour-stay-catalog-v1.php` exposes `AnyTourStayCatalog(PDO)`:

- `meals()` / `rooms($anytourHotelId)` read active canonical records.
- `createRoom($hotelId, $localKey, $nameRu, $categoryCode, $facts)` inserts a room
  with a DB-assigned ID. It requires an explicit caller transaction and active hotel.
- `recordDecision($scope, $reference, $expectedHotelId, $state, $targetId, $evidence)`
  records `pending`, `accepted`, `rejected` or `conflict`. An accepted decision
  requires an active target and matching CURRENT hotel; other states have no target.
  Evidence reference/digest/reviewer are mandatory metadata. The caller is responsible
  for acquiring and reviewing the evidence; the repository does not fabricate it.
  This v1 writer is INSERT ONLY. An existing decision cannot silently be overwritten
  or upgraded; later review transitions require a separately reviewed versioned path.
- `resolve($scope, $references)` reads up to100 room/meal references in ONE prepared
  SELECT, preserving request order and duplicates. It returns `hotelId`, per-reference
  status and a canonical record only when accepted and active. Empty/malformed/oversized
  requests fail. Missing schema/DB faults propagate as errors, not successful empty lists.

Example read (IDs/codes are illustrative, not real mappings):

```php
$catalog = new AnyTourStayCatalog($pdo);
$result = $catalog->resolve(
    ['namespace'=>'tourvisor', 'hotelKey'=>'00015', 'operatorKey'=>'5'],
    [
        ['kind'=>'room', 'keyKind'=>'code', 'externalKey'=>'STD SV'],
        ['kind'=>'meal', 'keyKind'=>'code', 'externalKey'=>'AI'],
    ]
);
```

Without reviewed mappings the result is `unmapped`, not an inferred Standard/AI.
The resolver does not accept, rewrite or return an entire supplier offer. The caller
retains its original provider/search/offer IDs, room/meal values, dates, exact nights,
party, flights and price. Customer HTML must still escape labels at render time.
Unknown/negative/source-drift/disabled-target statuses cannot authorize a sale or an
unverified final price. This read contract is not yet wired into live renderer/lead flow.

## Installation and operation boundary

The migration `20260916-anytour-stay-catalog.sql` creates FOUR new tables after the
independent hotel schema. It does not ALTER any existing table or source mapping.
It deliberately fails on existing/partial installation: no `IF NOT EXISTS` camouflage,
no automatic DDL at request/startup and no upsert that overwrites reviewed labels.

DDL is not a rollbackable MySQL transaction. Live installation needs its own CURRENT
schema/engine/version preflight, reviewed immutable operation, reservation and retained
receipt. Do not append it to the active hotel bootstrap writer's operation. An interrupted
installation must be inspected, not replayed. All operational DB writes are deferred.

This package has no live installer, command trigger, schedule, supplier API calls,
credentials, source-catalog/MATCH updates or production/preview publication. An operations
owner may consume it after the separate hotel bootstrap handoff. Source merge alone does
not mean the tables exist on the server or that any real room has been mapped.

## Verification and rollback

`php tests/anytour_stay_catalog_test.php --unit-only` explicitly runs only pure contracts
outside CI. CI refuses that mode and requires the full test on a disposable loopback
MySQL8 database named `anytour_stay_fixture`; application DSNs/secrets are never read.
The suite exercises actual DDL/seeds/foreign keys, exact namespace/operator/hotel scoping,
unknown/negative states, wrong-hotel and stale-source rejection, inactive records, preserved
inputs, insertion rollback and 100 references / 1 SELECT. Missing pdo_mysql/fixture DSN
is a failure, never a skip. Fixtures create rooms and mappings; they are not live evidence.

Rollback before any live installation is a source revert only. After a future installation,
do not drop tables containing reviewed records; rollback the consumer separately and inspect
that operation's retained database receipt. Renderer/CSS, source transport, fuel arithmetic,
lead/Metrika, production and the existing local-preview baseline remain untouched here.
