# Bounded native update of the separate Search3 design-v2 preview

This control package updates only
`/_preview/search3-design-v2-candidate/` from the accepted standalone frontend
based on the final Sites v2 source `00fe6734c08acf234756bb59e5b61dbfecf41941`.
It does not authorize changes to NEXT, the Sites reference, sibling previews,
production, providers, auth, mappings, lead/CRM or analytics.

## Current state: hotel-offer predecessor published, family and cost-label successor reviewed

The exact public predecessor is source
`08a99f2dfe2f420c9c03a6eb451d00e9a04e5026`, receiving release
`dd43712a55b5ad1d22429938ce1b9fbe1e860693`, and terminal publication
run `38363323689` / command `6140628891`. Its 855-file public digest is
`073f569d9a13ceef6a14d5d92d8d6050d586b569ff1d0372c0963d6c4e21cc7e`.
Actual READY artifact `11795343702` (ZIP
`0f007f2b858c2f5609c8d067d5b20e0805679e62f1705c28370336b9181b4975`)
and deployment artifact `11795574678` (ZIP
`0ded514b6ac80dbdb0c0b6f11ece09417f68427ffe29d27b46bc464ae35a8d2e`)
establish the installed predecessor; terminal receipt is #4217 comment
`6140772005`. The READY request, manifest and all 855 payload hashes were
independently verified. That command and run are consumed and cannot be replayed.

Merged integration PR #4565 retains exact source
`a3b0fa5125adb1a90b32a49d5f27a6b8852f5510` and tree
`37f0810d5fd09a3d18f1004b39a290ef97fe5910`, based on release
`c816c1f8602a4c5d5fa39c9699741bdf4f47a74b`. The reviewed source passed
native CI `38386255266` and Security `38386255236`, both attempt 1. The accepted
artifact is `11807030769`, ZIP SHA256
`401a357e5ade6ee2d4ad57dc6737f4a105027e4320266d7b2b379f7bcf403330`.
That retained exact-head build was generated for source-identical PR #4564;
provenance pins source, tree, workflow, run, repository and artifact rather than
the PR number or open state. All fresh exact-head checks remain mandatory.
The ready PR's fresh native CI `38387141489` and Security `38387141490` also
passed before its receiving squash was merged.

The source includes the reviewed Russian party wording for 1–6 adults and
0–3 children in the search summary, hotel offers, selected tour and contact
context, including the grammatical forms after «за». It also fixes enlarged
cost-label wrapping: the labels retain whole words while the corresponding
value moves to another row when needed. It changes no party values,
offer identity, provider, price, flight selection or lead contract. Existing
native input/tour tests cover all 24 adult/child combinations. The retained
855-file packet has 333 rendered frames: all prior 263 states, 60 family frames
and 10 short-desktop frames. Independent artifact review confirmed ordinary
100% composition is retained and the intended enlarged cost screens improve.
Controller claim is #4217 comment `6141529055`.

The receiving squash is release
`0c3a6494c8d8e716a946395141b1e544589785e9`, with identical tree
`37f0810d5fd09a3d18f1004b39a290ef97fe5910` and single parent
`c816c1f8602a4c5d5fa39c9699741bdf4f47a74b`. Its independently verified exact
source/release relation is `diverged`, behind 1, ahead 1, with merge base equal
to that parent.
Source integration is not publication.

| Pin | Accepted value |
| --- | --- |
| source_sha | `a3b0fa5125adb1a90b32a49d5f27a6b8852f5510` |
| source_tree | `37f0810d5fd09a3d18f1004b39a290ef97fe5910` |
| release_sha | `0c3a6494c8d8e716a946395141b1e544589785e9` |
| release_tree | `37f0810d5fd09a3d18f1004b39a290ef97fe5910` |
| release_parent | `c816c1f8602a4c5d5fa39c9699741bdf4f47a74b` |
| build_run | `38386255266` |
| artifact_id | `11807030769` |
| source_ZIP_sha256 | `401a357e5ade6ee2d4ad57dc6737f4a105027e4320266d7b2b379f7bcf403330` |
| entry_html_sha256 | `1d2cf3076fbb949d0787ca82d7cfb4b442ba002364cc4b35f2be947ec490e8a7` |
| entry_php_sha256 | `c68afd233c54629b080744e47b31ea331a0c431137bbcc2b6b2439ecf3b80843` |
| source_manifest_sha256 | `71d231e67de03ce4f3ebe39961fa6192ee38e08544ff0fe3fd27a15720ae999e` |

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

The controller requires exactly 855 payload files and 333 rendered frames. The
acceptance matrix is 360/390/430/768/1280 at text 100% and 200%, with search,
results, hotel, tour and lead journeys plus error, filtering, calendar, budget,
bounded-result, loading, partial-result and flight-alternative states. All prior
263 frames remain mandatory. Each case adds six family states: 0/1/2/3 children,
applied selection and age review after changing dates, retaining ages 0/7/17.
Both 1280 cases also require search/results/hotel/tour/lead screenshots at a
720 px viewport height; their actual PNG dimensions must be 1280×720. Names,
bytes and PNG hashes must match the bounded inventory; missing or arbitrary
extra evidence is rejected.

Every case must prove:

- `familyAges:[0,7,17]` with exact integer identity,
  `familyExtraSearches:0` and `familyAgeReviewRetained:true`;
- family frames contain the readable correct party summary, no factual prices,
  and both age-review messages in the review state;
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
- every tour frame, including flight alternatives and short desktop, contains
  measured labels «Туристы», «Туроператор» and «Обязательные доплаты» with no
  clipping, broken words or overlapping lines; missing label evidence aborts;
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
- `visual-search/native-v2/tour.js`
- `visual-search/native-v2/viewport.css`
- `visual-search/native-v2/source-manifest.json`

The predecessor's viewport fixes remain intact; the reviewed cost-list
container rule lets values take the next row at or below 16 text-relative units
while labels retain whole words. Historical
baseline allowances do not permit new legacy presentation, bridge,
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
