# New LOCAL retained-only initialization — 10.10.2026

Owner «Давай делать», schema claim6096839793 / retained extension6096988983.
PR4500 merged source3cc3d29595c8a9ec71a0435ee2efeb939c40adb6. Control PR4508
merged main34bc0a3746d120875bb3e9794740a5c2a68aa9e7 after all12 native/control tests.
Actual inspection38048092114, bootstrap38048178692 and independent readback38048258005
are COMPLETE: both new InnoDB tables valid, empty, source/config/DB identities stable,
schema writes2, old profile/MATCH/content/supplier writes0, production unchanged.

The new stock executor mode local-tv-seed-v1 has three fixed operations, without
caller-controlled IDs, table, SQL, budget or old recovery manifest:

```
/run-int-server-v1 <current_release_sha> local-tv-seed-v1 int-andromeda-local-tv-seed-inventory-20261010-v1
/run-int-server-v1 <current_release_sha> local-tv-seed-v1 int-andromeda-local-tv-seed-apply-20261010-v1
/run-int-server-v1 <current_release_sha> local-tv-seed-v1 int-andromeda-local-tv-seed-readback-20261010-v1
```

The inventory covers ALL surviving genuine observation stores and the same filters
as the reviewed backfill, bounded at50000 hotels for current review. It streams private,
durable SHA256-verified full profile/source/raw-cache before images (256MiB/file bound),
records exact observed IDs/times and validates retained full cards by raw hash, identity,
timestamp and the existing normalizer. Dictionary-only entries never register. Removed
history cannot be reconstructed. Public receipts expose aggregate counts/hashes only.

Apply needs same current source/control/config/DB, a fresh successful inventory,
both valid empty new tables, all exact current inventory fingerprints and the shared
LOCAL advisory lock. It registers the complete planned history, calls the already
reviewed migrateLinks with snapshot/manual preservation, then saveSource for every
valid retained card. It has no provider class, HTTP callback or daily acquisition limit.
Source cache and old profile/source bytes must remain unchanged. The registry flag,
schedules, public entrypoints, offers, prices, supplier/lead/Metrika contracts stay intact.
Only local_tv_hotels/local_tv_legacy_links receive DML. Before/started/link-result/after
receipts are private. Any failure after a possible first DML stops UNKNOWN/no-replay;
there is no automatic rollback/retry or claim of zero effects. Legacy link conflicts
are explicit issues, not accepted links. Missing/invalid source remains unfinished.

Readback validates new source and legacy snapshot hashes, JSON/normalization, actual
discovered/ready/unfinished/links/manual-fields/photos and a bounded actual DTO sample.
This establishes retained fill only. Fresh provider acquisition, persistent registration,
browser rollout and deployed DB→API→NEXT acceptance remain separate subsequent scopes.
Old STOP6047119931, phase3 UNKNOWN and D1/consumed operations remain untouched.

The separate fixed operation `int-andromeda-local-tv-frontier-20261010-v1` diagnoses
the current nonempty target; it never repeats seed/DDL/apply. It uses an actual MySQL
REPEATABLE READ / READ ONLY transaction, inventories surviving observations and
protected source fingerprints, validates current catalog/link content, and groups at
most1000 unfinished IDs by missing/corrupt/foreign/generic/valid-retained source.
The complete ID/revision/source-hash scope is saved privately with a verified digest;
public evidence contains ID-only reason groups and12 bounded samples. Finite deployed
capture-file hashes and the loaded flag are inspected without returning private config.
Provider code remains absent from the bundle and HTTP/old/new DB writes remain0.
This is preflight evidence only: it does not authorize old held IDs, turn on capture,
change a schedule, or execute content acquisition. Native tests prove real MySQL rejects
an attempted UPDATE inside the diagnostic snapshot and that all catalog bytes survive.

The new tests execute actual isolated MySQL fixtures, full130-image transfer, manual
content, provider snapshots, history-vs-dictionary separation, retained corruption,
changed cache/profile, wrong/nonempty target, active lock and lost ACK after a real
new link commit. Local native tests are skipped unless the guarded disposable CI
MySQL is present; CI explicitly enables them and must pass before runtime commands.
