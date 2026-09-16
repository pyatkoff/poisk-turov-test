# AnyTour: read-only pre-schema inspection

This change follows #2666 at release commit
`eb7d91dab537879ba6bf1cae6c0db213bd5d5c4d` (SEARCH #1646, coordination #2530).
It does not install a schema, seed hotels, dispatch a deployment or switch a preview.

## Why a separate preflight is needed

`AnyTourCanonicalCatalog::plan()` deliberately requires the installed canonical
schema. It remains unchanged. `preflight()` can inspect saved source profiles
while all three new target tables are absent, so operators do not need to perform
DDL just to obtain the first source fingerprint.

The method reuses `sourceBatch()` and the existing bounded presentation reader.
For the same IDs and source snapshot, its `source_sha256` is identical to the
existing plan/seed digest. No second DTO or supplier-content fallback is added.

## Inputs and report

The private CLI takes an explicit 1–1000-ID set plus two required target
fingerprints. Credentials are supplied by the authorized on-host runner through
`ANYTOUR_DATA_DSN`, `ANYTOUR_DATA_DB_USER`, `ANYTOUR_DATA_DB_PASSWORD`.
There is no site configuration or document-root fallback.

```sh
php scripts/catalog/anytour_catalog_preflight.php \
  --ids=65108,158344 \
  --expect-dsn-sha256=<SHA256-of-exact-ANYTOUR_DATA_DSN> \
  --expect-database-sha256=<SHA256-of-expected-database-name>
```

The fingerprints must come from the reviewed target configuration, not be copied
blindly from an unknown database. They bind the selected target; they do not prove
physical isolation or authorize a write. Establish independently that the selected database is the existing AnyTour project
database approved for the three new canonical tables. A separate preview database
is NOT required. The preview route identifies a consumer, not a separate database.
Do not repoint to an arbitrary database or alter any existing source/MATCH tables.

The report contains source count, missing/inactive IDs, unnamed IDs, counts with
saved details/descriptions/images, exact source digest and target table presence.
It contains no hotel texts, source snapshots, database name, DSN or credentials.
Table states are `absent`, `partial_requires_review`, or `present_requires_review`.
Existing tables are never treated as proof of ownership or compatible structure.
Missing, non-InnoDB or view-backed source tables are rejected.

Reads use a repeatable-read, read-only transaction. The report is not a lasting
lock against concurrent DDL. Both `migration_authorized` and `seed_authorized`
are always false. Unknown CLI arguments, apply flags, invalid IDs and target
fingerprint mismatches fail closed with sanitized diagnostics.

## Live operation boundary

An authorized writer must separately verify server compatibility, database
identity, exact table structure/ownership and concurrent-operation controls.
Only then may a separately reviewed runner apply the additive migration from
#2666. Re-run the original `plan()` after migration and compare its source digest
before using the unchanged seed CLI. Stop on source/schema drift or a partial
schema; do not auto-repair, drop tables, or recreate an existing preview.

Retain the preflight, migration and committed seed/readback receipts privately.
No CLI in this patch performs the latter two actions. No automatic live execution
or workflow change is introduced. After seeding, retain editorial data on rollback
and revert consumers rather than dropping canonical tables.

## Tests and current evidence

```sh
php -l v2/data/anytour-canonical-catalog-v1.php
php -l scripts/catalog/anytour_catalog_preflight.php
php tests/anytour_catalog_preflight_test.php
```

The added tests use a strict PDO double and the actual shared DTO/digest code.
They cover pre-schema success, partial/existing schema review, source failures,
input rejection, preservation of a caller transaction, exact plan digest parity,
source drift, no-mutation SQL traces and a 1000-profile batch in ten bounded reads.
They do NOT substitute for real SQL tests. The existing catalogue CI additionally
runs `tests/anytour_catalog_preflight_mysql_test.php` against a separate empty
loopback MySQL fixture: engine-enforced read-only transaction, source failures,
CLI target guards, 1000 actual profiles and preflight/plan/seed digest parity.
The existing 68-check catalogue suite is retained unchanged. Neither fixture
is a live database preflight, schema installation or seed.
