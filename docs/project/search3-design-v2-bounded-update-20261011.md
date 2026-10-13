# Bounded native update of the separate Search3 design-v2 preview

This control package updates only
`/_preview/search3-design-v2-candidate/` from the accepted standalone frontend
based on the final Sites v2 source `00fe6734c08acf234756bb59e5b61dbfecf41941`.
It does not authorize changes to NEXT, the Sites reference, sibling previews,
production, providers, auth, mappings, lead/CRM or analytics.

## Current state: compact-flight predecessor published, truthful partial-result successor selected

The exact public predecessor is source
`9de4cdb3d044b060b039abc096f9351466147d86`, receiving release
`54daa24bc815ea1da80a9fb7da6a681276efadc8`, and terminal publication
run `38320934646` / command `6134401708`. Its 855-file public digest is
`1d0839ccd874c407c93e1c56e8f91140b4d892b6fbf9e18d4c2a016976b749de`.
Actual READY artifact `11779162675` (ZIP
`b6d732c2b390b721ee814139c3fd027c71df7785ff48f9dc496f0e0896f1a40c`)
and deployment artifact `11778933686` (ZIP
`0f37fdb7673ac65aca493be600d3cb316b8771b574e6308ff9f2ea70ec4d6407`)
establish the installed predecessor. That command and run are consumed and
cannot be replayed.

PR #4554 final source `5a84a0d66394116f9845de3565563ce1d025b6bc`
and tree `f8c889e007bdd2b95c7c0b654d4d6de2d742bc9d` passed exact-head
native CI `38328089239` and Security `38328089238`, both attempt 1. The accepted
artifact is `11781426609`, ZIP SHA256
`1ff20b842f7528724543ead87a915cdf925c7eae1970fccc5413269aa8b43cad`.
Independent source/runtime/visual review is recorded in PR comment
`6135636873` and #4217 comment `6135639385`. It approved the 855-file,
263-frame packet and confirmed that the earlier failed run `38325169014` was
only the controlled fixture crossing UTC midnight; the product still rejects
real past dates.

The receiving squash is release
`059349ad4a10902e102b5b21e34f3cdc6c3fc9b5`, with the identical tree and
single parent `54daa24bc815ea1da80a9fb7da6a681276efadc8`. Its exact source/release
relation is `diverged`, behind 4, ahead 1, merge base equal to that parent.
Source integration is not publication.

| Pin | Accepted value |
| --- | --- |
| source_sha | `5a84a0d66394116f9845de3565563ce1d025b6bc` |
| source_tree | `f8c889e007bdd2b95c7c0b654d4d6de2d742bc9d` |
| release_sha | `059349ad4a10902e102b5b21e34f3cdc6c3fc9b5` |
| release_tree | `f8c889e007bdd2b95c7c0b654d4d6de2d742bc9d` |
| release_parent | `54daa24bc815ea1da80a9fb7da6a681276efadc8` |
| build_run | `38328089239` |
| artifact_id | `11781426609` |
| source_ZIP_sha256 | `1ff20b842f7528724543ead87a915cdf925c7eae1970fccc5413269aa8b43cad` |
| entry_html_sha256 | `1d2cf3076fbb949d0787ca82d7cfb4b442ba002364cc4b35f2be947ec490e8a7` |
| entry_php_sha256 | `c68afd233c54629b080744e47b31ea331a0c431137bbcc2b6b2439ecf3b80843` |
| source_manifest_sha256 | `c8033201b19e0686f301b2210d2c1320aa90872cea42d76ab1947fe7e62e9d64` |

An absent, foreign, modified, activated or unknown owner/target aborts. Previous
completed or failed commands and runs remain terminal/no-replay. A new publisher
still requires independent HIGH control review, green exact-head control CI,
fresh main/release/source and predecessor checks, an exclusive publisher claim,
and one fresh guarded OWNER command.

## Exact acceptance and rendered proof

Native provenance is scoped to
`.github/workflows/search3-design-v2-native.yml`, job `native-acceptance`, and
the exact source's guard. Both required checks must be successful GitHub Actions
checks on that exact head; the accepted workflow must be a successful
same-repository PR run, attempt 1. The retained artifact contains `payload/**`,
`control/inventory.json`, `control/native-build.json` and rendered `evidence/**`.
Review metadata retains `publication_authority:false` and route rewrites
unapplied.

The controller requires exactly 855 payload files and 263 rendered frames. The
original acceptance matrix is 360/390/430/768/1280 at text 100% and 200%, with
search, results, hotel, tour and lead journeys plus error, filtering, calendar,
budget, bounded-result, loading and flight-alternative states. This successor
adds exactly 30 frames: `native-results-partial-empty`,
`native-results-partial` and `native-filter-empty-partial` at every matrix
position. Names, bytes and PNG hashes must match the bounded inventory; missing
or arbitrary extra evidence is rejected.

Every case must prove:

- `partialEmptyNeutralCount:true` and no invented zero-completion claim;
- `partialSavedSearchRetained:true`;
- `partialLoadedHotels:4` for positive partial completion;
- `partialExtraSearches:0` during local rendering/filtering;
- `partialFilteredRecoveryLoadedHotels:2` after removing the proven local
  constraint;
- retained exact hotel/offer identities and full factual prices;
- original flight selection, expiry and query identity;
- passive hydration, local filtering and Back/Forward cause no supplier search;
- retry is explicit, favorite state is retained and real leads remain zero.

Only the named empty/loading/error frames may have no result prices. A partial
empty result must expose exactly one readable recovery action and must not say
that a partially completed search is a complete zero result. A positive partial
result retains the received rows and prices. A partial local-filter empty state
describes only received rows and offers only the recovery supported by the
proven constraint. The durable history bridge uses `replaceState` for the
current snapshot and must not submit a search.

All frames retain measured readable text, unbroken price groups, unclipped
actions, no horizontal overflow and no overlapping lines. Mobile and 200% text
checks include form captions, statuses, sorting, navigation and flight choices.
These are fictional fixture checks; public runtime readback is a separate
requirement.

## Bounded payload and trusted derivation

Immutable original baseline: artifact `11710372908`, source
`01baa3a91e1d497aa928b9866ee97de5f0cc29e2`, ZIP SHA256
`5267c2eec2549d3117a87a71faedf410d699baa0bdbf364e5f371e9410f14e5f`.
Prepare and publish independently download and verify it. Exactly ten files may
be added under `visual-search/native-v2/`: `bootstrap.js`, `bridge.js`,
`donor.css`, `frontend.js`, `lucide-LICENSE`, `lucide.min.js`,
`product-desktop.css`, `source-manifest.json`, `tour.js`, `viewport.css`.
All 845 baseline paths remain present.

The new 855-to-855 transaction compares the verified staged inventory with the
published predecessor under the existing lock. Path sets must be identical, the
predecessor digest must match the pinned receipt, and exactly these existing
files may differ:

- `visual-search/native-v2/frontend.js`
- `visual-search/native-v2/source-manifest.json`

Historical baseline allowances do not permit new legacy presentation, bridge,
canonical dependency, provider, lead, brand or font changes. A different delta,
missing/additional file or changed predecessor stops before activation.

Schema 2 native manifest pins final Sites source
`00fe6734c08acf234756bb59e5b61dbfecf41941`, exact donor files, original
CSS/Lucide, both entries, all assets and the explicit native graph. Legacy
presentation and donor mock simulator cannot be loaded. Unknown or missing
assets, graph owners and inline scripts abort.

Only previously reviewed canonical route literals are transformed in trusted
prepare. Exact native PHP is already design-v2-only and hash-pinned. Reversing
only approved JavaScript route literals must reproduce the pinned source
manifest. All other source bytes remain unchanged.

## One transaction and readback

The retained command shape is:

```text
/update-search3-design-v2-preview <source40SHA> <release40SHA> <buildRun> <artifactId>
```

This syntax is not a publication command. After green control CI and independent
HIGH review, the publisher must claim the exclusive lane in #4217, recheck the
published predecessor and issue one fresh OWNER-created command on trusted main,
run attempt 1. Never replay a completed, failed or UNKNOWN operation.

Prepare has no SSH secrets. Publish independently rederives the retained archive
and request before server access. Fixed target, shared lock, sibling/production
fingerprints, durable receipt, exact predecessor backup and interruption recovery
remain mandatory. Rollback restores only that verified predecessor.

Readback checks exact native graph and served hashes, negative entry routes,
noindex/counter-zero/lead-403/LOCAL-only/catalogue boundaries, healthy production
routes and unchanged sibling/production fingerprints. It starts no supplier
search and sends no real lead. A terminal published receipt and verified public
address are required before reporting publication.
