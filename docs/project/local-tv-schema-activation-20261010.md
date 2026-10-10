# New LOCAL schema activation — 10.10.2026

Owner: «Давай делать», following reviewed source PR4500. Source is now merged into
release at `3cc3d29595c8a9ec71a0435ee2efeb939c40adb6`; main product is unchanged.
Coordination: #4217, schema claim6096839793. This is a new two-table schema operation,
with no old recovery operation, candidate list or phase3 manifest as an input.

The stock `/run-int-server-v1` executor keeps owner/main/current-release checks,
SSH host-key verification, reservation before execution, immutable source hashes,
private receipts, production entrypoint fingerprints and operation no-replay.
Mode `local-tv-schema-v1` admits three fixed operations: inspect, bootstrap, readback.
No caller-controlled table, database, file, SQL, card ID, limit or supplier budget.

The only schema is `v2/data/migrations/20261010-local-tv-catalog.sql`, SHA256
`79e53072085eaac5eb37753a04ca7c68f3fcab2a4a4431d4e426e87e9f244bd2`.
It creates `local_tv_hotels` and `local_tv_legacy_links`, empty, in InnoDB. It has no
ALTER/DROP, DML, old-table FK, matching/profile/offer write, flag/config change or
supplier request. The reviewed DB helper is also pinned by file SHA256. Credentials
remain in the existing AnyTour root config; neither values nor DSN are exported.

Inspect reads metadata and counts for only these two tables. Bootstrap requires a
fresh successful inspection in the same control revision, both tables absent,
unchanged config fingerprint/selected DB, disabled registration flag and the same
advisory lock as the LOCAL daily collector. A preexisting/partial/drifted schema is
held for current review. Before-state, started marker and each acknowledged DDL are
saved privately before the terminal receipt. A failure after a DDL may have committed
is UNKNOWN/no-replay, without a zero-effect claim or automatic rollback/retry.
Readback only reads the new schema. Old UNKNOWN/STOP6047119931 operations remain held.

Commands use the actual current release SHA after fresh ownership/head checks:

```
/run-int-server-v1 <release_sha> local-tv-schema-v1 int-andromeda-local-tv-schema-inspect-20261010-v1
/run-int-server-v1 <release_sha> local-tv-schema-v1 int-andromeda-local-tv-schema-bootstrap-20261010-v1
/run-int-server-v1 <release_sha> local-tv-schema-v1 int-andromeda-local-tv-schema-readback-20261010-v1
```

These are eligible only after the new control PR passes CI, merges to main, and its
main readback matches. Each operation is reserved once; the commands are not a replay
queue. Current server receipts, not this document, establish whether they ran.

After schema readback, separately establish the actual surviving-observation list,
confirmed old→TV bridge/manual preservation and all-history transfer scope. The
daily content limit does not bound backfill/link migration. Then fill via the existing
collector and check saved DB→GET API→deployed NEXT, with no paid search for content.
Schema installation alone does not mean a populated catalog or enabled NEXT.
