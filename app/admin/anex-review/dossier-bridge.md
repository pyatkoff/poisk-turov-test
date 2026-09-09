# Durable observed dossiers — P2 source packet

This bridge stores **existing** `anex-observed-hotel-triage.json`, not fresh hotel
or price responses. No acceptance, reclassification, retry, content collection or
update of historic checkpoints is performed. The archived raw row includes all
saved candidates and historical hints, without converting hints to verified evidence.

## Flow and limits

1. Restore the latest verified ANEX artifact through the existing approved runner
   restoration mechanism. Independently bind its artifact ID and the triage file's
   SHA256; do not guess either value or work around a denied ZIP download.
2. `anex-review-dossier-pack.py TRIAGE --sha256 DIGEST --artifact-id ID` verifies
   the source bytes, scope/schema, counts/IDs, origin, no-retry/no-accept flags and
   every existing Python-canonical evidence digest. It rejects duplicate JSON keys.
   Limits: 8 MB source, 1,000 rows, 1 MB per raw row, 20 MB packed envelope.
3. After the separately approved additive migration in `dossier-schema.sql`, pass
   that envelope to `anex-review-dossier-import.php` using the existing AnyTour
   SSH/DB-helper path. The CLI is restricted to the AnyTour document root; no web
   route and no new credentials. It does not run DDL. Runtime wiring is not yet
   added, so **this packet does not populate the application database**.
4. The PHP store verifies hashes/IDs/flags, then transactionally archives one batch
   plus immutable rows with byte-for-byte readback. It locks observations in ID
   order, using the same mutex as the panel decision writer. Existing finalized
   batches replay without inserts; conflicting/missing rows cause rollback, never
   reset or repair. All historic versions stay present. Largest artifact ID selects
   the latest imported version per hotel; importing an older artifact cannot regress it.
5. Panel service reads the optional local archive. Fresh observation country must
   agree. Only a saved `review` evidence candidate list is projected (at most 20
   distinct candidates on screen, all raw candidates retained). `source_error`,
   `interrupted_result_unknown`, unmatched/protected and historical hints never
   become candidate suggestions. Missing local targets remain visibly unavailable.
   Archive artifact/source/row hashes enter the evidence version: a newly imported
   dossier invalidates a prior decision form. Missing tables preserve staging fallback;
   corrupt installed archives fail closed rather than falling back to stale suggestions.

The panel shows original observed demand ordering and never claims a capped list
is complete. Gallery/description formatting and owner acceptance policy are unchanged.
The `no_candidates` filter uses the latest dossier's validated country and projected
candidate count, matching the detail view; only absent archives fall back to staging.
Those derived fields are checked against immutable raw evidence on import/readback.

## Connection still required

- Confirm live owner authorization and finish the separate importer pair-exclusion
  guards; this bridge does not enable either gate.
- Apply only the additive archive schema and wire the exact CLI source plus class
  into an approved existing workflow stage with latest-artifact restoration.
- On the runner: save source/manifest/packing report, transport once, inspect DB
  counts/digests and preserve registry/manual/staging/catalog hashes. Archive loss
  or a provenance conflict is a blocker, not permission to reset.
- Only after the preceding gates: isolated panel deployment/live access/readback.

Tests use a disposable MySQL database and generated fixtures, not a downloaded live
artifact. No source-stage or live DB completion is claimed by unit/CI green alone.
