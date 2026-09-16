# Reviewed rooms and meal plans: executable batch population

SEARCH #1646 / coordination #2530, claim5700222256. Depends on #2666 + #2668.
Source baseline: `3822557bbb520b12f35839c60902d6df27d08404`.

## What this adds

`scripts/catalog/anytour_stay_import.php` is an executable CLI and importable
repository adapter, not another catalogue model. It consumes 1..1000 explicitly
reviewed decisions. It creates missing concrete hotel rooms and accepted room/meal
mappings through `AnyTourStayCatalog` in ONE transaction. Existing exact accepted
records are preserved; existing negative, pending, conflict, different target or
different review provenance blocks the entire batch rather than being overwritten.

It does NOT install schema, discover provider IDs, create hotel-source bridges,
parse room-description prose, infer identity from labels, fetch supplier data,
translate unreviewed room names, edit canonical profiles or publish a preview.
There is no HTTP writer, scheduler, remote dispatcher or application credential
handling in the library. The CLI reuses the existing `v2_data_db()` connection.

## Input contract

A local UTF-8 JSON file (at most4MiB) has exactly `version:1`, a lowercase
`operation` ID and a `rows` list. Each row contains exactly:

- `scope`: `namespace`, `hotelKey`, `operatorKey` (explicit strings, case and leading
  zeros preserved; the already-existing source relation must point to our hotel).
- `reference`: `kind` (`room` or `meal`), `keyKind` (`code` or `label`), `externalKey`.
- `hotelId`: actual independent AnyTour integer ID, never a copied provider ID.
- `sourceSha256`: current retained hotel-source snapshot digest. The importer also
  rehashes the stored bytes and checks the canonical profile integrity and activity.
- `target`: for a meal, the reviewed EXACT local `code` (e.g. `half-board-plus`,
  not its broader family). For a room, `localKey`, reviewed `nameRu`, nullable
  `categoryCode`, and `facts` as defined by #2668. Names/views/buildings/capacity
  are not inferred; contradictory definitions of one local room reject the batch.
- `evidence`: retained dossier `ref`, its `sha256`, and `reviewedBy`.

A source snapshot digest proves the retained source hotel context, NOT by itself
that a supplier room code equals a canonical room. The separate reviewed dossier
must establish the exact supplier reference and intended target. The caller must
retain and verify that evidence before producing the manifest; this importer does
not fetch a dossier from an arbitrary URL or turn metadata into independent proof.
No example provider/canonical IDs are shipped as real mappings.

Multiple reviewed source references may explicitly converge on one local room;
only one room is created. Code and label references remain distinct. Unknown meal
codes and room categories stop the batch. Historical negative decisions are never
promoted by this import; review transitions need a separate versioned review path.

## Plan, apply and receipts

Default command (no writes):

```sh
php scripts/catalog/anytour_stay_import.php --manifest=/private/op/reviewed.json
```

The plan reports proposed rooms/mappings, already-present mappings, normalized
manifest digest, current-state digest and `planSha256`. One REPEATABLE READ READ ONLY
transaction inspects all rows. All seven previously installed InnoDB tables and
hotel schema version1 are required. This guard is not a substitute for the separate
installation owner's exact CURRENT schema/constraint acceptance.

A separately authorized, exclusively reserved operation may apply the reviewed plan:

```sh
php scripts/catalog/anytour_stay_import.php --manifest=/private/op/reviewed.json \
  --apply-plan-sha256=EXACT_REVIEWED_PLAN_SHA256 --receipt=/private/op/NEW-receipt.jsonl
```

The receipt file is created exclusively with owner-only creation permissions BEFORE
DB access. Existing files are refused. Keep it outside web roots and retain its
parent operation reservation in the existing operations runner; changing its filename
is not permission to replay. No network path, DDL or operations-dispatch route is added.

Apply re-reads/locks the current hotel/source/targets/mappings and the existing
catalogue control row; it must reproduce the entire reviewed plan. Changes to any
relevant source bytes, canonical revision, target definition, activity or existing
decision stop the batch. All INSERTs and internal readback are in one transaction.
No partial acceptance, UPDATE/upsert/delete, internal retry or source writes occur.
The pre-COMMIT expected-readback fingerprint is flushed/fsynced into the receipt.
A new read-only transaction after COMMIT must match it exactly.

Statuses distinguish `failed_before_commit`, `commit_unknown`,
`committed_unverified`, and `committed_verified`. A lost COMMIT acknowledgement is
NEVER reported as rollback. A failed post-COMMIT receipt/readback does not imply no
rows were written. Every attempted operation is no-replay until reconciled from
retained evidence. A fresh plan after a completed batch reports a zero-write repeat;
old pre-insert plan hashes no longer match. The library requires a checkpoint
callback: callers must persist/fsync it, not silently discard it outside fixtures.

## Verification and live handoff

The new CI has NO server secrets or deployment step. It runs the unchanged #2668
suite plus this importer suite in separate disposable loopback MySQL8 databases.
Tests cover 1000 real room+mapping inserts, unchanged repeat, preserved source/profile
rows, three-source convergence, plus-meal identity, source/profile drift, negatives,
rollback after INSERTs, failure after COMMIT, and real COMMIT with simulated lost ack.
`--unit-only` is allowed locally but refused in CI. Missing PDO/fixture is failure.

This source addition does not claim actual server installation or populated real
supplier mappings. SEARCH bootstrap #2667 (claim5699409340) owns a separate
six-file read-only preflight; its operation is not extended or replayed here. Live stay installation requires
terminal hotel-bootstrap evidence and its own checked CURRENT operation. Population
then needs actual reviewed rows with existing independent hotel-source bridges.
Thereafter the consumer can be wired to `search3-local-candidate` under renderer
ownership. `search3-local-preview`, shared renderer/CSS, MATCH/INT, main and production
are unchanged. Before any live invocation, rollback is source revert only; after a
live commit inspect the exact receipt and never delete reviewed data blindly.
