# Bounded native update of the separate Search3 design preview

This preparatory control work implements the owner's correction in
#4217/comment6120089701: use the exact final Sites v2 layout as a standalone
frontend. It extends the existing update transaction; it adds no second publisher.
Only `/_preview/search3-design-v2-candidate/` may be replaced. NEXT, the Sites
reference, all sibling previews and production remain protected.

## Current state: exact native source selected, not published

PR #4539 against `release/search3-design-v2` has exact source
`b53d5ca0bf541e66b684018fb7db332de8fefaf3`, tree
`65cdbc2658fd63e9782e34df4b2b83638e047aa0`. Native CI38236619254 and
Security38235455890 succeeded. Retained artifact11740981928 is independently
ZIP-hash verified. Failed/superseded runs are negative evidence only.
This source selection is not a publication receipt. Independent final visual/HIGH
review, exact-head control CI, fresh receiving refs and predecessor, exclusive
publisher claim and one fresh guarded command remain mandatory.

| Pin | Accepted value |
| --- | --- |
| source_sha | `b53d5ca0bf541e66b684018fb7db332de8fefaf3` |
| source_tree | `65cdbc2658fd63e9782e34df4b2b83638e047aa0` |
| build_run | `38236619254` |
| artifact_id | `11740981928` |
| source_ZIP_sha256 | `06d9e3a9f42d434d2fe02c6db2cd902474f9d16c2038cd330e82289896056c6d` |
| entry_html_sha256 | `2fcf7ac0ee88befb27cb656fe8ed882daa75bcd9d6aee7d155747b9157f60809` |
| entry_php_sha256 | `c68afd233c54629b080744e47b31ea331a0c431137bbcc2b6b2439ecf3b80843` |
| source_manifest_sha256 | `c434c71cb1f8f492dc4c5d15b640a39b320ccb11e14830e3e005700b855a4650` |

The only installed predecessor accepted by this successor is source
`16196410a8cb90b10e8d06b7ac8b38d4ce7d235d`, publication run `38207863194`,
attempt1, 845-file inventory digest
`62c9d3fcd75fd696f71881220b1c7cd1ff0b6e569e752b737ed1f0c83d9f25d3`,
and the matching owner with `status:published`.

An absent, foreign, modified, activated or unknown owner/target aborts. Previous
command6118298451/run38207863194 are terminal/no-replay; every older completed
source/run remains rejected. A successful native update consumes this predecessor.
This successor cannot publish again over its newly installed owner.

## Terminal preparation failure and bounded correction

Command6122535492 / run38237246171 attempt1 is terminal/no-replay. Prepare
failed with `invalid_source_tree` before SSH or activation; publish was skipped.
The controller had verified the source tree separately but omitted it from the
request passed into derivation. The correction propagates only that already
verified tree into the retained request. A regression starts from the actual
OWNER command shape with no preloaded tree and exercises review extraction,
derivation and final remote verification. The failed run is explicitly blocked.

This successor selects the separate successful source run38236619254 and retained
artifact11740981928. Its complete payload, native manifest and110 rendered frames
are byte-identical to accepted run38235455883; its build/artifact/ZIP identity is
new. The old four-pin command must not be repeated or rerun. A distinct fresh
command is permitted only after final control CI and independent HIGH review,
fresh refs/predecessor and exclusive publisher claim. No server result exists
for the failed preparation and no publication is inferred from this correction.

## Exact acceptance pins and source gates

The accepted configuration has exactly these fields: `source_sha`, `source_tree`,
`build_run`, `artifact_id`, `source_ZIP_sha256`, `entry_html_sha256`,
`entry_php_sha256`, `source_manifest_sha256`. Pins come from the final successful
retained artifact. Independent HIGH control review must cover their real values
before trusted-main merge.

Native provenance is scoped to
`.github/workflows/search3-design-v2-native.yml`, job `native-acceptance`, and the
exact source's `guard`. The native job owns source/bridge/canonical lead, price
and idempotency regressions, isolated build, rendered widths and journeys. Both
required checks must be successful GitHub Actions checks on the exact head; the
accepted workflow must be a successful same-repository PR run, attempt1.
Shared publisher workflow/artifact/gate defaults are not relaxed.

The receiving branch is always `release/search3-design-v2`. Prepare and publish
recheck fresh main/release refs, source ancestry/tree and unchanged runtime
projection. Failed, pending, stale, foreign, skipped-required or expired evidence
aborts. The review artifact is named
`search3-design-v2-native-<sourceSHA>-<buildRun>` and contains `payload/**`,
`control/inventory.json`, `control/native-build.json`, rendered `evidence/**`.
Review metadata retains `publication_authority:false` and route rewrites
unapplied. The controller verifies every byte/count, donor/brand/entry/graph hash
and complete evidence before deriving a publishable transaction.

Rendered proof requires 360/390/430/768/1280 at text100/200, five paired
donor/native screens (search/results/hotel/tour/lead), native error frames,
matching PNG hashes and no native horizontal overflow. Prices must prove actual
2x computed fonts at200%, unfragmented numeric groups and no clipping; primary
actions must have unclipped text bounds. It requires passive
hydration0 searches, one submit search, local filtering0 extra searches, exact
offer reference, original flight index/full price, accepted contact preview with
real leads0, Back/Forward0 extra searches, explicit retry and favorite retention.
These are fictional fixture checks. Independent visual review and public runtime
readback remain separate requirements.

## Bounded payload and trusted derivation

Immutable original baseline: artifact11710372908, source
`01baa3a91e1d497aa928b9866ee97de5f0cc29e2`, ZIP SHA256
`5267c2eec2549d3117a87a71faedf410d699baa0bdbf364e5f371e9410f14e5f`.
Both prepare and publish independently download and verify it. The native source
build compiles the existing graph before its isolated overlay, preserving the
accepted baseline comparison for canonical dependencies.

Exactly ten files may be added under `visual-search/native-v2/`: `bootstrap.js`,
`bridge.js`, `donor.css`, `frontend.js`, `lucide-LICENSE`, `lucide.min.js`,
`product-desktop.css`, `source-manifest.json`, `tour.js`, `viewport.css`.
The inventory has exactly855 files, all845 baseline paths retained. Only the
already reviewed legacy presentation delta paths and native `index.html` /
`index.php` entry replacement may differ. No prefix wildcard, arbitrary addition
or deletion, or protected provider/lead delta is accepted.

Schema2 native manifest pins final Sites source
`00fe6734c08acf234756bb59e5b61dbfecf41941`, exact donor files, original CSS/Lucide,
both entries, all9 assets and the explicit16-script/3-stylesheet headless/native
graph. Legacy presentation and donor mock simulator cannot be loaded. Actual
dependency checksums/lengths are verified. Unknown/missing assets, graph owners
and inline scripts abort.

Only previously reviewed canonical route literals are transformed in trusted
prepare. Exact native PHP is already design-v2-only and hash-pinned, so receives
no second boundary replacement. Old served-size ledger and native graph checksums
are updated and verified. Reversing only approved JavaScript route literals must
reproduce the pinned source manifest. All other source bytes remain unchanged.

## One transaction and readback

The retained command shape is:

```text
/update-search3-design-v2-preview <source40SHA> <release40SHA> <buildRun> <artifactId>
```

This syntax example is not a publication command. After green source/control CI
and independent source/HIGH review, claim the exclusive publisher lane in #4217,
recheck the published predecessor and issue one fresh OWNER-created command on
trusted main, run attempt1. Never replay a completed, failed or UNKNOWN operation.

Prepare has no SSH secrets. Publish independently rederives the retained archive
and request, comparing all bytes before server access. Artifact source is never
executed with SSH secrets. Fixed target, shared concurrency/lock, production and
sibling fingerprints, binding, durable receipt, exact predecessor backup and
interruption recovery remain the existing transaction's owners. Rollback restores
only that verified predecessor. Modified/foreign backup, target, owner or unknown
state stops without takeover.

Readback checks exact native versioned graph/served hashes, negative entry routes,
all existing noindex/counter0/lead403/LOCAL-only/catalogue boundaries, healthy
production routes and unchanged sibling/production fingerprints. It starts no
supplier search and sends no real lead. A terminal published receipt and verified
public address are required before reporting publication.
