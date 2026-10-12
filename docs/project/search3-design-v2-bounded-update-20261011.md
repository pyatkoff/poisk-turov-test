# Bounded native update of the separate Search3 design preview

This preparatory control work implements the owner's correction in
#4217/comment6120089701: use the exact final Sites v2 layout as a standalone
frontend. It extends the existing update transaction; it adds no second publisher.
Only `/_preview/search3-design-v2-candidate/` may be replaced. NEXT, the Sites
reference, all sibling previews and production remain protected.

## Current state: visual predecessor published, full-calendar successor selected

Source `0674fa6cc7744ee21cb073b284f089c20171f38e`, tree
`fc82c5ba0e7326dfc41e85292cf358a86ddfff71`, is the exact public predecessor.
Terminal run38269206819 attempt1 and command6127174112 installed its855-file
inventory with digest
`eafff576d5f4887f9d236e019a4e0f63cf72b9e2893bd2d64c20648a147bb6e2`.
The command and publication are completed/no-replay; their bounded updater cannot
be reused for another predecessor.

This successor fixes one confirmed user-facing defect without changing the final
Sites v2 composition: the price calendar now exposes every loaded departure date
through the existing seven-day pager. Later pages use only exact totals already
present in the loaded result set, preserve the page through local filters,
progressive updates and Back/Forward, and initiate no additional supplier search.
PR #4546 produced exact source
`8c2d0308eb29e26d2819342d10501db1ed82e3db`, tree
`18c5c2f9ec56f67f7c4c2b6bf69d34ceb62d1a00`. Native CI38273265388 and
Security38273265377 succeeded. Artifact11757847419 has the verified ZIP hash below,
855 payload files and173 passing frames. Independent source/runtime review approved
the exact artifact. Receiving release commit
`07ecfd601b7bb96af34c31784d14456f2285dabe` has the same exact tree after GitHub's
squash merge and exact parent `09a5ca870dc5a254161664a30e56d0142f557ca8`.

Claim6127907840 owns only the two deploy scripts, this test and this document.
Retained derivation independently reproduced the public predecessor digest above,
then produced target digest
`ca35adc2185fcbcbeed6e1bf46f97c5effe14445561f57e37f256800961537fe`;
both inventories contain the same855 paths and differ only in the two files listed
below.
Independent final HIGH control review, exact-head control CI, fresh predecessor
readback, exclusive publisher claim and one fresh guarded command remain mandatory.
No successor publication is inferred from this preparatory work.

| Pin | Accepted value |
| --- | --- |
| source_sha | `8c2d0308eb29e26d2819342d10501db1ed82e3db` |
| source_tree | `18c5c2f9ec56f67f7c4c2b6bf69d34ceb62d1a00` |
| release_sha | `07ecfd601b7bb96af34c31784d14456f2285dabe` |
| release_tree | `18c5c2f9ec56f67f7c4c2b6bf69d34ceb62d1a00` |
| release_parent | `09a5ca870dc5a254161664a30e56d0142f557ca8` |
| build_run | `38273265388` |
| artifact_id | `11757847419` |
| source_ZIP_sha256 | `1225f3c1a31e9cbcec96f86ce110780f5cb36b72a0e85cabe1266b6d0fb94286` |
| entry_html_sha256 | `1d2cf3076fbb949d0787ca82d7cfb4b442ba002364cc4b35f2be947ec490e8a7` |
| entry_php_sha256 | `c68afd233c54629b080744e47b31ea331a0c431137bbcc2b6b2439ecf3b80843` |
| source_manifest_sha256 | `9d1db6ce9d3f381ee2e5b547da1e247f64c341c60b1e284ec2f0c7a1b103a1ae` |

The only installed predecessor accepted by this successor is source
`0674fa6cc7744ee21cb073b284f089c20171f38e`, publication run `38269206819`,
attempt1, 855-file inventory digest
`eafff576d5f4887f9d236e019a4e0f63cf72b9e2893bd2d64c20648a147bb6e2`,
and the matching owner with `status:published`.

An absent, foreign, modified, activated or unknown owner/target aborts. Previous
command6118298451/run38207863194, command6122730823/run38238503101 and
command6125148522/run38255222254 are
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
`release_sha`, `release_tree`, `release_parent`, `build_run`, `artifact_id`,
`source_ZIP_sha256`, `entry_html_sha256`, `entry_php_sha256`,
`source_manifest_sha256`. Pins come from the final successful
retained artifact. Independent HIGH control review must cover their real values
before trusted-main merge.

Native provenance is scoped to
`.github/workflows/search3-design-v2-native.yml`, job `native-acceptance`, and the
exact source's `guard`. The native job owns source/bridge/canonical lead, price
and idempotency regressions, isolated build, rendered widths and journeys. Both
required checks must be successful GitHub Actions checks on the exact head; the
accepted workflow must be a successful same-repository PR run, attempt1.
Shared publisher workflow/artifact/gate defaults are not relaxed.

The receiving branch is always `release/search3-design-v2`. GitHub squash-merged
this exact source, so prepare and publish require the pinned release SHA, identical
source/release tree, single pinned release parent and exact compare receipt:
`diverged`, behind3, ahead1, merge base equal to that parent. This exception is
specific to the fully pinned source/release pair; an arbitrary equal tree or any
other history relation aborts. Prepare and publish also recheck fresh main/release
refs. Failed, pending, stale, foreign, skipped-required or expired evidence aborts.
The review artifact is named
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
budget-progress-invalid only at390/text100. Those143 names remain mandatory. This
startup package additionally requires exactly20 frames: native-startup-pending
and native-startup-error at each matrix position. The full-calendar successor
adds exactly ten `native-calendar-later-<width>-text<percent>.png` frames, one at
each matrix position. All173 names, bytes and PNG
hashes must match the bounded inventory; missing or arbitrary extra evidence is
rejected. Each case must prove startupPendingFormHidden:true,
startupErrorSearches:0 and startupRetrySearches:0. Startup frames must identify
the startup screen, have no displayed prices, and preserve readable header/full
text; error frames must have exactly one visible, readable retry action.
All cases additionally prove budgetLoadedCount2, budgetExtraSearches0,
calendarPages2, calendarLaterLoadedTotal166400, calendarPagingExtraSearches0 and
calendarHistoryPageRetained:true. The
390/text100 case must retain progressive-update draft/focus and breakpoint draft.
Native frames require measured readable form captions and condition/navigation/flight
text without clipping, broken words or overlapping lines. Every readableText row
must explicitly have overlappingLines:false; missing measurements are rejected.
Cases390/text200 and430/text200 must explicitly prove
enlargedCaptionPaintOverlapRegressionChecked:true: the browser detector measures
actual glyph ink, detects collision when the old15px line height is restored
under24px text, and observes no overlap with the repaired30px before and after.
Frames also require header bounds without overlap, readable sort text, and
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

- `visual-search/native-v2/frontend.js`
- `visual-search/native-v2/source-manifest.json`

Historical baseline allowances do not permit new legacy presentation, bridge,
canonical dependency, provider, lead, brand or font changes in this successor.
Its activation receipt reports the exact two changed payload paths. A different
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
