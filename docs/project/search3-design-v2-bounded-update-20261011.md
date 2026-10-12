# Bounded native update of the separate Search3 design preview

This preparatory control work implements the owner's correction in
#4217/comment6120089701: use the exact final Sites v2 layout as a standalone
frontend. It extends the existing update transaction; it adds no second publisher.
Only `/_preview/search3-design-v2-candidate/` may be replaced. NEXT, the Sites
reference, all sibling previews and production remain protected.

## Current state: native predecessor published, fresh visual successor selected

PR #4539 against `release/search3-design-v2` published exact source
`b53d5ca0bf541e66b684018fb7db332de8fefaf3`, tree
`65cdbc2658fd63e9782e34df4b2b83638e047aa0`, in terminal run38238503101
attempt1. The published receipt and independently verified READY artifact11741379616
agree on855 files and inventory digest
`4c811808bd4fe9b5e7adee37024c09db938a9917f85a41c918bba637615e088f`.
Command6122730823 and that publication are completed/no-replay; the old updater
cannot publish over this new owner.

This fresh successor is scoped to the visual/input package in PR #4542, exact
source `4955ee078aa0827f745d737681b4ed9187a274bd`, tree
`07009af0c6d0f3c45ec52ff5f5cbc0f92b082b84`. Native CI38253455431 succeeded
on that exact source, attempt1; artifact11748403944 is independently ZIP-hash
verified. Receiving release `3e417d9e4d6f51344c1d7e66d4209a4bc2826911`
has the same tree. These eight pins cover that final source/artifact, not the
consumed publication. Independent final visual/HIGH review, exact-head control
CI, fresh receiving refs and predecessor, exclusive publisher claim and one fresh
guarded command remain mandatory. No successor publication is inferred from this
preparatory work.

| Pin | Accepted value |
| --- | --- |
| source_sha | `4955ee078aa0827f745d737681b4ed9187a274bd` |
| source_tree | `07009af0c6d0f3c45ec52ff5f5cbc0f92b082b84` |
| build_run | `38253455431` |
| artifact_id | `11748403944` |
| source_ZIP_sha256 | `39350793d04a84c6f8d60941a0ef6eb2ef8fd0ef2b61876a60f68bedc6f1f15f` |
| entry_html_sha256 | `2fcf7ac0ee88befb27cb656fe8ed882daa75bcd9d6aee7d155747b9157f60809` |
| entry_php_sha256 | `c68afd233c54629b080744e47b31ea331a0c431137bbcc2b6b2439ecf3b80843` |
| source_manifest_sha256 | `a8b1b50ce32301fae2006e932f9353f3c9f9d285890e5a43af91b5dc56c3277d` |

The only installed predecessor accepted by this successor is source
`b53d5ca0bf541e66b684018fb7db332de8fefaf3`, publication run `38238503101`,
attempt1, 855-file inventory digest
`4c811808bd4fe9b5e7adee37024c09db938a9917f85a41c918bba637615e088f`,
and the matching owner with `status:published`.

An absent, foreign, modified, activated or unknown owner/target aborts. Previous
command6118298451/run38207863194 and command6122730823/run38238503101 are
terminal/no-replay; every older completed source/run remains explicitly rejected.
The completed lists no longer depend on the mutable predecessor constants, so
selecting another predecessor cannot silently remove historical rejection entries.
A successful native update consumes this predecessor.
This successor cannot publish again over its newly installed owner.

## Terminal preparation failure and bounded correction

Command6122535492 / run38237246171 attempt1 is terminal/no-replay. Prepare
failed with `invalid_source_tree` before SSH or activation; publish was skipped.
The controller had verified the source tree separately but omitted it from the
request passed into derivation. The correction propagates only that already
verified tree into the retained request. A regression starts from the actual
OWNER command shape with no preloaded tree and exercises review extraction,
derivation and final remote verification. The failed run is explicitly blocked.

That native restoration selected the separate successful source run38236619254
and retained artifact11740981928. Its complete payload, native manifest and110 rendered frames
are byte-identical to accepted run38235455883; its build/artifact/ZIP identity is
new. The old four-pin command must not be repeated or rerun. A distinct fresh
command was permitted only after final control CI and independent HIGH review,
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

The original110 mandatory frames remain required. This successor requires exactly
33 additional native frames: budget-results and hotel-return at all ten matrix
positions; budget-invalid and budget-draft at all six mobile positions; and
budget-progress-invalid only at390/text100. All143 names, bytes and PNG hashes
must match the bounded inventory; missing or arbitrary extra evidence is rejected.
All cases additionally prove budgetLoadedCount2 and budgetExtraSearches0. The
390/text100 case must retain progressive-update draft/focus and breakpoint draft.
Native frames require measured readable condition/navigation/flight text without
clipping or broken words, header bounds without overlap, readable sort text, and
budget inputs with measured text fitting their available width. Budget fonts must
measure original13px desktop or16px mobile, doubled at text200, and desktop budget
must preserve two columns at text100, one at text200. These added gates preserve
every original factual journey, actual price scaling and primary-action gate.

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
historical reviewed legacy presentation delta paths and native `index.html` /
`index.php` entry replacement may differ from that immutable original baseline.
No prefix wildcard, arbitrary addition or deletion, or protected provider/lead
delta is accepted.

The new855-to855 transaction additionally compares the verified staged inventory
with the current published predecessor under the existing lock before either
target is moved. Path sets must be identical, the predecessor digest must match
the pinned receipt, and exactly these existing files may differ:

- `visual-search/native-v2/bootstrap.js`
- `visual-search/native-v2/frontend.js`
- `visual-search/native-v2/source-manifest.json`
- `visual-search/native-v2/tour.js`
- `visual-search/native-v2/viewport.css`

Historical baseline allowances do not permit new legacy presentation, bridge,
canonical dependency, provider, lead, brand or font changes in this successor.
Its activation receipt reports the exact five changed payload paths. A different
delta, missing/additional file or changed predecessor stops before activation.
The original845 baseline remains the canonical byte comparison; the current855
owner is the exact backup/rollback predecessor, not a replacement baseline.

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
