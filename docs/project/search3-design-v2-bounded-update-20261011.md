# Bounded native update of the separate Search3 design-v2 preview

This control package updates only
`/_preview/search3-design-v2-candidate/` from the accepted standalone frontend
based on the final Sites v2 source `00fe6734c08acf234756bb59e5b61dbfecf41941`.
It does not authorize changes to NEXT, the Sites reference, sibling previews,
production, providers, auth, mappings, lead/CRM or analytics.

## Current state: selected-hotel summary predecessor published, hotel-offer action successor selected

The exact public predecessor is source
`660a760d9d0a0c0121d05d53bdc0895689600bc8`, receiving release
`a9255f8cf6c64d1786f4d7f5c1d34cfaa78e293d`, and terminal publication
run `38354248961` / command `6139303622`. Its 855-file public digest is
`f7e485d5a3148f9ec51214b23d573b47d2337802d8a2266d2d7b7fa521c913d4`.
Actual READY artifact `11792775223` (ZIP
`0986bfc475f1280fe576be85d059cc9ccad6edcd8cf1c8c8cfc0957b69541947`)
and deployment artifact `11792062330` (ZIP
`a1daafd6451ca1c9f21c0408ae335ee6791ad3e1353574f6b1c8e937e70ac3da`)
establish the installed predecessor. That command and run are consumed and
cannot be replayed.

PR #4560 final source `08a99f2dfe2f420c9c03a6eb451d00e9a04e5026`
and tree `644a504eb14e84457f6454f489e33dab133bf381` passed exact-head
native CI `38359574384` and Security `38359574307`, both attempt 1. The accepted
artifact is `11794199301`, ZIP SHA256
`9e0c45cf4f1ac1b282d93bbb07b874a8bc79f5913ff072f5e557947e32487551`.
Independent source/runtime/visual review is recorded in #4217 comment
`6140234672`. It approved the 855-file, 263-frame packet and checked that all
hotel-offer actions preserve the donor price/action row at ordinary text, then
reflow to full-width in genuinely narrow 200% text containers across the
complete 360/390/430/768/1280 matrix.

The receiving squash is release
`dd43712a55b5ad1d22429938ce1b9fbe1e860693`, with the identical tree and
single parent `a9255f8cf6c64d1786f4d7f5c1d34cfaa78e293d`. Its exact source/release
relation is `diverged`, behind 1, ahead 1, merge base equal to that parent.
Source integration is not publication.

| Pin | Accepted value |
| --- | --- |
| source_sha | `08a99f2dfe2f420c9c03a6eb451d00e9a04e5026` |
| source_tree | `644a504eb14e84457f6454f489e33dab133bf381` |
| release_sha | `dd43712a55b5ad1d22429938ce1b9fbe1e860693` |
| release_tree | `644a504eb14e84457f6454f489e33dab133bf381` |
| release_parent | `a9255f8cf6c64d1786f4d7f5c1d34cfaa78e293d` |
| build_run | `38359574384` |
| artifact_id | `11794199301` |
| source_ZIP_sha256 | `9e0c45cf4f1ac1b282d93bbb07b874a8bc79f5913ff072f5e557947e32487551` |
| entry_html_sha256 | `1d2cf3076fbb949d0787ca82d7cfb4b442ba002364cc4b35f2be947ec490e8a7` |
| entry_php_sha256 | `c68afd233c54629b080744e47b31ea331a0c431137bbcc2b6b2439ecf3b80843` |
| source_manifest_sha256 | `2079b939f929a85f020f569e594d353ed9e00b3babed8ca25611d939960b80fc` |

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
acceptance matrix is 360/390/430/768/1280 at text 100% and 200%, with search,
results, hotel, tour and lead journeys plus error, filtering, calendar, budget,
bounded-result, loading, partial-result and flight-alternative states. Names,
bytes and PNG hashes must match the bounded inventory; missing or arbitrary
extra evidence is rejected.

Every case must prove:

- `partialEmptyNeutralCount:true` and no invented zero-completion claim;
- `partialSavedSearchRetained:true`;
- `partialLoadedHotels:4` for positive partial completion;
- `partialExtraSearches:0` during local rendering/filtering;
- `partialFilteredRecoveryLoadedHotels:2` after removing the proven local
  constraint;
- every `native-calendar-later` frame has a non-clipped footer, no horizontal
  scroll, a pager inside the calendar and two in-bounds controls at least
  44×44 px;
- every tour and lead frame has exactly one measured selected-hotel summary;
  ordinary-width summaries retain the donor side-by-side composition, while a
  summary at or below 18 text-relative units stacks its real image and copy at
  full width without clipping;
- every visible hotel-offer action remains inside its offer row; ordinary
  360/390/430 layouts retain the donor price/action row, while a container at
  or below 22 text-relative units promotes the action to its own full-width row;
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

- `visual-search/native-v2/viewport.css`
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
