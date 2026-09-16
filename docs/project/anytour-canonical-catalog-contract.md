# Independent AnyTour catalogue — first materialized hotel slice

Owner authorized the fourth catalogue on 2026-09-16; SEARCH #1646 and coordination
#2530, claim `search-anytour-canonical-catalog-20260916`. #996 is read-only history.
This is additive to release e7586f98e27373a68184cc4aba54c4e71ffc553f, not a rename
of the Tourvisor-derived catalogue and not a second search renderer.

## What exists in this package

Three new InnoDB tables in the existing database:

- `anytour_hotels`: independently allocated AUTO_INCREMENT identity, materialized
  profile JSON/hash, revision and canonical active flag. Supplier refresh does not
  update any existing profile, editorial revision, gallery order or active flag.
- `anytour_hotel_sources`: explicit case-sensitive `(namespace, external_key)`
  identity, foreign key to AnyTour, acquisition channel, last saved source
  profile/hash and first/last snapshot timestamps. There is no fixed list of three
  providers. A source identity cannot point to two AnyTour hotels.
- `anytour_catalog_control`: schema version and a row lock for cooperating seed
  writers. No source/MATCH table is used as a writer lock or write target.

The current seed uses only `legacy_catalog` / `saved_catalog` provenance. It does
NOT certify that a legacy ID is the native ID of Tourvisor or another operator.
Legacy IDs, geography/type codes and original descriptive room/meal blocks are
preserved in the separate source snapshot. Canonical profiles do not expose those
geography/type codes as new AnyTour dictionary IDs. Descriptive room/meal/service
blocks remain `hotelInformation`, not normalized offer dictionaries or traits.

The first import copies actual saved content into the new catalogue. Reads then
come from that materialized copy, not a fallback to the old catalogue. Numeric
coincidence between a newly allocated ID and any supplier ID has no meaning.
The profile reader identifies its output as `anytour-canonical-catalog`.

## Preserve existing matching, do not repeat it

Existing MATCH resolvers and their manual/exclusion/rejection priority remain the
only authority for supplier -> legacy local identity. After a resolver confirms a
legacy target, `legacyTargets()` translates that explicit target into an AnyTour
ID. It never accepts a provider ID, searches by name, copies pending matches or
writes existing registries. A revoked match must be rejected by its original
resolver before this translation; no cached accepted provider link is introduced.
The bridge is the migration path for already approved correspondences, not a new
competing provider resolver. Initial creation does not claim to merge duplicate
physical hotels already present in the old catalogue.

## Explicit schema preparation and bounded seed

The migration is a separate SQL file, NOT added to the automatic bootstrap,
source synchronizers, public endpoint or deployment workflow. Review the actual
server version, table existence/engines, backups and source fingerprint before
running it against a live database. Existing source tables are never altered.
MySQL DDL is not rolled back together with a data transaction; therefore the
schema must be installed separately and is not described as transactional.

CLI uses the existing `ANYTOUR_DATA_*` DB configuration and requires an explicit
1..1000-ID batch. There is no implicit all-catalogue apply or supplier request.
After separately preparing the schema, the command below is READ ONLY:

```sh
php scripts/catalog/anytour_catalog_seed.php --ids=7001,7002
```

The resulting `source_sha256` pins the exact ordered saved profiles, missing IDs
and requested ID set. Inspect that plan; applying requires the exact hash:

```sh
php scripts/catalog/anytour_catalog_seed.php --ids=7001,7002 --apply-source-sha256=<64-lowercase-hex>
```

These IDs are illustrative, not live availability evidence. Each batch is read
through the existing prepared local profile reader in chunks of at most 100.
Apply acquires the new control row, re-reads the saved data in a repeatable-read
transaction, rejects source drift before any insert, then commits the independent
profiles and bridges together. A mid-batch error rolls back all its data writes.
A second writer waits/fails on the control lock; source identity uniqueness is a
second barrier against duplicates. An inactive/missing source never deletes or
reactivates its canonical counterpart.

Each affected bridge/profile is reread after COMMIT and both content digests are
checked. A post-COMMIT connection/readback error is not a claimed rollback:
inspect committed state before a next apply. Repeat *catalogue materialization*
is idempotent; this does NOT authorize replay of supplier or MATCH operations.

Updated saved input refreshes only its source snapshot. Applying changes to
canonical fields later needs explicit field ownership/editorial policy; no
unconditional source refresh is advertised as automatic catalogue maintenance.
The current snapshot is not a full source-history archive. No background schedule
or collector is created by this package.

## Verification and delivery

The focused workflow uses an empty loopback disposable MySQL 8.0 service, no
production config, SSH or account secrets. It executes the actual additive SQL,
real PDO seed/read operations, 1000-profile repeat, source drift, editorial
preservation, foreign-key/namespace constraints, a trigger-induced partial batch
failure and two connections contending for the seed lock. Tests refuse any other
host/database name. `--unit-only` explicitly reports SQL_NOT_RUN; full CI never
substitutes that mode for database tests. Hosted tests are not a live DB migration.

The three new tables are not publicly routed. Old reads, old source imports,
#2660 renderer, #2659 facets, prices, leads, Metrika and old previews are unchanged.
Initial deployment/seed and the `search3-local-candidate` read switch require an
explicit reviewed operation/consumer package. Do not replay the create-only
preview provisioner or publish this over `search3-site-candidate`.

Next slices: validated live preflight/schema/seed with retained receipt; new-preview
consumer; independent room and meal catalogues with exact supplier mappings;
server-side normalized offer storage and freshness. These are not claimed done.

Rollback before live activation is an ordinary source revert. After seeding,
revert consumers and retain the additive tables/bridges for diagnosis; do NOT drop
canonical records or delete editor data as an automatic rollback. No existing
source catalogue or MATCH data needs a reverse migration from this package.
