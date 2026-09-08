# Search3 technical refactor autopilot

## Current resume point — native entry and shared-owner compaction #1619 — 2026-09-08

Exact source `efd0368b99f91533cb5538d433244a71fb303c25`, checked release
`fa9917bf35b88f548bbea44baf26785dc5a1aa93`; base was
`e4fae092af9fa9d1607cdc141b52cc270ab00ca2`. Eight public assets are
**33355 → 23506 raw B (−9849)**. Search3 shared JavaScript is
**106735 → 101537 B (−5198)**, so complete loaded CSS/JS is
**140090 → 125043 B (−15047)**.

The entire client entry-control projection is retired. Canonical server markup,
catalogs and lifecycle now directly own the visible form, meal loading, child
ages, URL hydration and `FormData`. Only the compatibility ready marker and compact
44px/Safari-safe native control rules remain. Exact SHA-locked build normalization
serves compact Search3-only representations of `tour-controller-v4.js` and
`catalogs-v2.js`; their canonical protected sources and the complete legacy route
remain byte-identical.

Security `34265965821` and exact artifact `34265965943` passed on the first head.
Reuse artifact `10071852547`, digest
`sha256:5553468af7d54aa90ea04b50a8187e2af009d14bec27f0fdd8c8f453de59aa94`.
Actual isolated Chromium checked native entry at 375/1440 and 12 current results
states at 375/760/761/999/1000/1440 with raw/served parity, external calls0 and
leads0. Entry evidence `10071851661`, digest
`sha256:837d9d430c2fbf81d7a50446ec83e80cb912f1e5fa7829f481211848f174d12a`;
results evidence `10071852147`, digest
`sha256:10d6c05a7a5a641ac81c6fffb6fdda82a37c7a75e56c76284739cdc872e30ca2`.
Manual screenshot inspection was not performed.

**Checked release is not published preview.** Preview remains
`c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production unchanged. Owner visual
acceptance, physical Safari/safe-area, live current-source preview and the full
lead/responsive/site/SEO journey remain deferred. Revert merge `fa9917bf` for
rollback. Audit: `docs/project/search3-native-entry-shared-compaction.json`.

Next: assess one coarse retirement of booking-summary/booking-format/summary-CTA
presentation. Retain TourController lead form, pending/confirmed price,
review/back/lead transitions and original tour/flight facts; do not make a
standalone micro-trim.

## Current resume point — booking/accessibility/rail reset #1614 — 2026-09-08

Exact source `70cc8105a6920472fafccabc172158ed15f48bca`, checked release
`61df0ca55c4791d04c0c59ce965b92377e300a6f`; fresh base was
`ed740780483850bf85f884f248cdd8692b096cdb`. Eight public assets are
**42074 → 33355 raw B (−8719)**: main JS23070→16656 (−6414), main
CSS10725→8420 (−2305). Search3 shared file payload drops another2619B by
excluding `accessibility.js` from Search3 only; complete loaded file payload is
151428→140090 (−11338). The full legacy route and all eight public paths remain.

Whole private booking services/layout/lead-note owners, the duplicate booking
fact card, redundant CTA copy, dead desktop filter rail and its empty220px column
are retired. Compact booking total, pending/confirmed arithmetic, flight label,
review/back/lead transitions, original placement/fuel/baggage facts and lead
fields remain. Static ARIA/live attributes, results busy lifecycle and native
details close-on-search replace the removed accessibility runtime in Search3.

Security34258249194, exact artifact34258249202 and standalone navigation
34258249247 passed on the final merged head. Reuse artifact10068815027, digest
`sha256:60d7d83b3dfe963fc1d5b42af74cd6fa7fac7200167e8437f2f5f64e4d63eadc`;
archive `d2959364524a72c91eb2af8b6016557ea813799676b5c03af8467a9acd64bcc1`.
Selected evidence10068814072, results evidence10068814511 and entry
evidence10068813396 retain their exact recorded digests in the audit.

Actual isolated Chromium: selected detail/review/lead12 states at
375/760/1000/1440, native entry lifecycle30 states, current raw/served results12
states at375/760/761/999/1000/1440, retry/fallback/decimal price and native header.
External calls0 and leads0. The exact run repaired one real 44px results disclosure
regression and retired stale fixtures for already removed drawer/header/card skins;
no claim that53 pre-existing skipped historical tests passed. Manual screenshot
inspection, physical Safari/safe-area, live current-source preview and owner visual
acceptance remain deferred.

**Checked release is not published preview.** Preview remains
`c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; production unchanged. Revert #1614
for rollback. Audit: `docs/project/search3-booking-accessibility-rail-reset.json`.

Next: independent post-merge audit found no whole optional shared-JS owner ≥2KB.
All remaining owners at that size are protected runtime/results/API/Tourvisor,
lead, price, catalog, URL, lifecycle or analytics. Do not start another micro-trim.
The next coarse reduction requires a new architecture boundary that preserves those
contracts; continue audit/design work without changing main/production.

## Current resume point — shared runtime compaction #1615 — 2026-09-08

Exact source `1edc81fe4fc61c845cfd21e453c2d4f5255515e1`, tree `38a21ff0873fd6d0871d64e3d36bfdf628d14c59`,
checked code release `ed740780483850bf85f884f248cdd8692b096cdb`. Base was `0957dba138acdc3eaf7ab12878b01b86ec37f1d8`.

New package only: shared JS **118613 → 109354 raw B (−9259)**;
eight public assets **41959 → 42074 B (+115 CSS)**; complete loaded file payload
**160572 → 151428 B (net −9144)**. The115B repairs confirmed mobile sort toolbar
overflow: its old width reserved234px for a retired rail. PHP actually serves
shared JS **119376 → 110117 B**, including unchanged script boundaries.
Joined shared-JS gzip estimate30629→28897; this is not a whole-route transfer claim.

Fifteen retained shared modules use a deterministic source/code-SHA256 checked
representation with local binding renaming, no statement/arithmetic compression,
no property/global/eval mangling, preserved function/class names and AST shape proof.
Canonical sources, full legacy response/cache, protected contracts and eight paths
remain. Missing/stale/corrupt data serves canonical source. Search3 cache includes
both source and map fingerprints. The map was generated once; do not sum unchanged
canonical file lengths as served Search3 bytes after this checkpoint.

Security34257278495 and exact34257278546 passed; boundary34257278502 passed.
Reuse artifact10068440285, digest `sha256:6c6dcef0346838b06d03af83c29bbd34b39e4448078dae0245fcd476e9b90b6a`;
archive57f503b7f9e6bebd71284b86e2aae3e14b2f0c36167dc3c9bbc0461b63c5fb55.
Results/native evidence10068439161 (`sha256:a8249324081cc71e3b140b7a953fcd49293b47835021d456be6a9c9a755c79b0`),
selected evidence10068437965 (`sha256:56d645eef2978c0bbe5c30d55fd5b6b5b2973e4bd8e39b8367292ab6f19e1e40`).
No release/docs rebuild. First exact34255999699 was red on a fabricated retired
button; second34256744950 correctly exposed pre-existing raw-JS mobile overflow.
The final source fixes it. Both red runs remain red in the audit.

Actual isolated Chromium:12 raw/served result DOM and geometry states across
375/760/761/999/1000/1440; real expand/collapse, sorting, tour identity, decimal price,
empty-result editing and native header/unchanged logo. Selected detail/review/lead12,
native form lifecycle30, guest/night FormData375/1440 and retry/fallback/decimal checks
also pass. No external calls or real leads. The required result gate now checks the
actual retained UI and native entry instead of fabricated retired disclosure/drawer/
guest/header skins. All source/PHP/path/presentation/isolation guards remain. No
claim that historical pixel expectations or53 previously skipped tests passed.
Manual screenshot inspection, live current source, physical Safari/safe-area,
owner acceptance and full site/SEO/lead matrix remain deferred.

**Checked release is not published preview.** Preview stays `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`.
Main freshly observed `86fc165277a13ae9bef1369659e9b150399f0e35` changed independently;
this package did not change main or production. No new fingerprint capture is
claimed because no publication occurred. Revert #1615 for rollback.
Audit: `docs/project/search3-shared-runtime-compaction.json`.

Next: Refresh release and moving PR #1614 before any next edit. It owns booking/services/layout/lead-note, dead rail and accessibility; retain its work and the #1615 compact map/current results fixture in sequential integration. Do not recreate obsolete UI to satisfy historical fixtures. After that coarse package, audit retained controller/catalogs compaction only with an exact syntax-preserving representation: current printer drops an EmptyStatement in tour-controller-v4 and converts a numeric string property key in catalogs-v2, so both remain raw. Preserve protected arithmetic, API/URL/payload, lead and analytics; do not make a micro-PR or repeat this 15-module pass.

## Historical checkpoint — selected owner retirement #1612 — 2026-09-08

Source `26c7200ae676d1d35581680f64f1fda5a4806955`, checked code release
`7eadf95d1164f51530fe545a5e42fb1ea977096e`. This supersedes the older
resume suggestions below; do not repeat entry/selected/results retirements.

Eight public assets **54,605 → 41,959 raw B (−12,646)**. Selected JS is
18,705 → 5,589; main JS22,600 → 23,070 includes470 B restoring result-summary
and empty-result edit buttons to the native search form. Loaded route
173,218 → 160,572; shared V2 JS118,613 and CSS0 unchanged. #1610's earlier
6,206 B saving is separate, not counted again as this package.

Whole selected trust/mobile/disclosure/optional-field and duplicate projection
layers are retired. Original facts, flight variants and lead fields remain.
No-flight retry/review, selected-open and decimal-safe labels are retained;
observed DOM writes settle without repeated mutations. API/URL/payload/price
arithmetic/lead mapping/transport/analytics/logo/native browser contracts unchanged.

Single required source pass: Security34247520934 and exact34247521545 success.
Reuse artifact10064661836, digest
`sha256:a5a5774cd9ea7388fbd0140417beb04ebcd7d6c7bafd7e5125c2930fb2ddd0e9`;
evidence10064661265, digest
`sha256:ea81152b3927abb80d535c4113093ac3ff429075fb4ad6060056c4a93c558004`.
No release/docs rebuild. All active source/PHP/path/presentation/isolation guards
passed. No new test skips or conditional geometry bypass.

Actual isolated Chromium:12 detail/review/lead states at375/760/1000/1440 preserve
facts, price, lead fields and transitions without overflow. Pending base and
confirmed totals checked. Native form geometry/values/lifecycle match current
baseline def1cf84 in30 states; result and empty-result edit repaired at375/1440.
No-flight retry/review/lead, retry recovery, decimal labels and stable observed
DOM pass. External calls blocked; no real leads. Inspected375-review and1440-detail
screenshots: intentionally largely unstyled UI; **not pixel parity, polished design,
or owner visual acceptance**. Expectations reflect the authorized visual removal,
not a claim that the old wrapper geometry remains. Physical Safari/safe-area,
live current-source interaction and full responsive/site/SEO checks deferred.

No publication. Preview stays source
`c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production untouched.
Audit: `docs/project/search3-selected-owner-retirement.json`.

Next bounded audit found only the duplicate private booking/services owner as
a justified retirement candidate (~2022 raw B estimated, not built or counted).
Accumulate with another useful coarse package; no standalone micro-PR/deploy.
Keep booking summary/pending total, summary CTA and original tour/fuel/baggage
facts. Do not delete native search-form/secondary controls: they still own live
meal URL/catalog/reset and native direct/night behavior.


## Latest checked layer retirement — enhancements.css — 2026-09-08

PR #1554 / source `d42367668ebfbfc13938f8b6c07a2106d7e51b8d`, integrated
release `942ed8d0f895ea17d69e7c41b9d77b3a77224ca8`, removes the complete
11,744-byte `enhancements.css` presentation layer from Search3. The unchanged
layer remains in the full old-search manifest.

The first exact run `34197097949` passed source/build/isolation guards and exposed
only two live declarations: positioning contexts for the selected picture and
flight variant. Those 53 bytes now live in `selected-tour-ux.css`. Final Search3
route payload is **535,608 → 523,917 raw bytes (−11,691)**; CSS is
178,591 → 166,900, JS remains 183,740, and carried same-method gzip is
119,180 → 117,126 (−2,054). The eight generated assets rise 53 bytes solely for
the retained positioning declarations.

Security `34197557533` and exact artifact runs `34197557413` /
`34197764610` pass. Reuse artifact `10044609590`, digest
`sha256:c935859742995af5186953d99910c964e5a5096d9a94234dae1db9b1cc0c4ec0`.
Selected/result/entry geometry is green; no external API or lead request occurred.
Preview remains source `c9ba7952`; `main` and production are unchanged. Live
current-source interaction, manual screenshots, Safari/safe-area and owner visual
acceptance remain deferred.

Next: audit `tour-design-v1.css` as one reversible whole-layer candidate. Keep
its unchanged old-search owner and restore only exact current-owner geometry
identified by focused CI. Do not repeat `design-v1.css` or `enhancements.css`.

Owner request recorded on 2026-09-06: «давай на автопилот поставь».

## Hourly development resumed — 2026-09-06

The owner explicitly requested: «давай что-то глобальнее; может слои целиком удалять,
а потом допишешь то что сломал; поставишь на автомат каждый час?»
The existing automation `6a9b55c965ac81918c5327b6b43faed2` is now enabled with
`RRULE:FREQ=HOURLY`, title «Уменьшать CSS/JS Search3». No duplicate task was created.
The scheduler confirms enabled configuration; the first background run after this
resume has not yet been observed. Previous PAUSED/scheduler-paused statements in
historical checkpoints are superseded by this explicit owner request.

Each run must perform actual development, prioritizing large reversible removals
of obsolete presentation layers and compact repair of concrete preview regressions.
Do not repeat the twelve retired layers or completed minifier/dominance/media scans.
Read fresh release state and #996/#1334; checked code and published preview may differ.
Continue beyond one PR while a safe independent next step remains. Avoid overlapping
edits or duplicate deployments; retain the focused two-source-job policy and reuse
the successful artifact. Do not publish or replay a browser journey merely because
an hourly run or PR completed. Report real byte savings, actual checks, publication
state and the next step; do not send repeated no-progress reports.

This does not authorize main/production changes or modifications to protected
price, URL/payload, Tourvisor/API, lead, analytics, logo or browser contracts.
Experimental layout breakage is confined to reversible release/isolated-preview
work; repair it or roll back the affected experiment. Unverified states remain
deferred and are never reported as passed.

## Latest checked follow-up — selected-tour phone repair — 2026-09-07

Source PR#1470 / `1c1a885fdbf0d1140ceb729b0acbc14cde94ba8e`, tree `809ea3719c67e140b3cd3c576d28acd4315f89dd`, closes the geometry evidence deferred in#1467. The first actual375px screenshots confirmed that the lead heading overlapped its explanation. Required phone root/heading layout, paragraph/fact spacing and picture/header borders now remain in `tour-detail.css`; the selected-tour donor stays retired.

This invocation removes **0 new bytes** and adds **434 CSS bytes** for the repair. Eight raw served assets:163740→164174; CSS93518 / JS70656. Previously prepared retirement savings are not recounted.

The existing exact-artifact job conditionally runs12 fixed-tour detail/review/lead states at375/760/1000/1440, using pre-retirement CSS0f1efa2b on the same current isolated payload. All visible rectangles/styles match after repair, no document overflow or unexpected API/lead call; screenshots inspected. The canonical host fixture correction and stable viewport coordinates are test-only. Compiled navigation13-transition invocation is restored. Security34122423681 and artifact34122423688 pass; reuse artifact10018756721 without release/docs rebuild. Evidence10018755973 and full hashes are recorded in `docs/project/search3-selected-geometry-repair.json`.

Preview remains b9445bc3; main remains fa58a0cb. Physical Safari/safe-area and current-source live acceptance remain deferred. An existing11px mobile-bar button touch target requires a separate intentional fix.

Concurrent#1471 advanced release to a5a494c1 after this repair. Its working source484d32de predates the434-byte phone repair. Neither independent source artifact is exact for the combined release; obtain a fresh combined-source artifact before publication.1470 geometry evidence stays scoped to1c1a885f. All1471 code is preserved in this documentation merge.

Next: Do not repeat selected-tour retirement or its phone repair (#1467/#1470). The focused Chromium fixture covers detail/review/lead at375/760/1000/1440; reuse the checked artifact. Concurrent#1471 already consolidated results-top.js in releasea5a494c1. Do not repeat it. The working-source484d32de artifact excludes the1470 phone repair; obtain a combined-source artifact before publishing current release. Retain results-layout.css until collapsed/expanded card geometry is available. The fixture also reveals a pre-existing11px mobile-bar button height at375; handle the touch-target defect separately with intentional geometry evidence. Physical Safari/safe-area, live current-source and full card/editor matrices remain deferred; production approval remains required.

## Current owner priority — rapid CSS/JS reduction

The subsequent owner request on 2026-09-06 explicitly prioritizes quickly reducing
and splitting Search3 CSS/JS for the isolated whole-site preview. Larger reversible
presentation batches are authorized. This priority supersedes the older suggestion
below to spend each continuation on another small toolbar/facet defect. Preserve
protected contracts and the production lock; use focused checks and existing CI.

### Current execution priority — batch reductions, limit manual checks — 2026-09-06

The owner again requested faster CSS/JS reduction and objected to spending more
than half an hour checking the preview for a 2827-byte saving. This instruction
supersedes the earlier per-batch live journey wording below for behavior-preserving
technical work. Production approval and protected contracts stay unchanged.

- Keep several useful reductions in one release-based working draft, with separate
  logical commits when practical. Push the completed batch for one final CI cycle.
- Build generated assets once after the source batch, measure the eight served
  files and run the narrow existing equivalence/affected-behavior check. Do not
  repeat the full artifact check set locally.
- Retain the two existing mandatory source jobs on the final SHA: Security
  (including owner validators) and the exact artifact build (including focused
  presentation/PHP/path/isolation checks). Release/docs reuse that artifact.
- Do not publish or open a browser solely because a technical PR completed.
  A checked batch may remain unpublished in release. Publish at an accumulated
  checkpoint, for a relevant behavior/layout change, or on owner request.
- At a justified publication, inspect the changed area. Do not replay the entire
  search/flight/review/lead journey unless the affected behavior requires it.
  Limit manual verification to five minutes. This is not permission to accept a
  failed or missing check: mark evidence deferred, exclude uncertain changes from
  the verified batch, and continue independent safe work.
- Prioritize measured removal of large duplicate CSS/JS blocks. Use the current checked asset table below to identify the largest files. Add micro-savings opportunistically;
  do not open separate PR/deploy/browser cycles for them.
- Routine user reports: show only newly removed bytes in the current invocation. Do not repeat the original baseline, cumulative savings or percentages. Completing a previously reported local packet counts as zero new reduction; distinguish its integration from new work. Preserve historical source comparisons only in audits. Distinguish checked from published work.
  Exact-artifact deployment, noindex, disabled preview leads, rollback and
  production fingerprints still apply whenever publishing actually occurs.
  Full applicable gates restore on ready_for_review; hourly development is enabled.

The latest checked release source is `94c76b6dade243c06055b5ffe65395e33e3800e3`;
the published preview remains on source `b9445bc3c8aa0713b148241bcddeefdf07576079`.
See the newest checkpoint below. Historical publication notes remain for provenance.

### Latest checked checkpoint — selected-tour owner retirement — 2026-09-07

PR #1467 / source `22c6a4dd0dbefe1e8885827ee74b160a7ee8f6f8`, integrated release
`94c76b6dade243c06055b5ffe65395e33e3800e3`, retires the complete
`selected-tour.css` presentation donor. Required shared shell, photo/facts/section
primitives remain in the current `tour-detail.css` owner; fixed CTA and narrow-state
rules remain in `selected-flow-v2.css`. Dead 330px picture geometry, forced results
display and superseded narrow ordering were not copied.

Eight raw assets **164762 → 163740 bytes (−1022 CSS)**. Main results CSS falls
62808→60385 while selected-flow CSS grows 9401→10802 for retained live rules; six
other assets are byte-identical. Security `34120401772` and exact artifact build
`34120401716` succeeded. Reuse artifact `10017964340`, digest
`sha256:6056d9b2a952b82b021bf0dc8c4e22052482cc4fb6eb36f0aa08fd55bfb0b9bb`;
release/docs do not rebuild it. Audit: `docs/project/search3-selected-tour-owner-retirement.json`.

The source-order audit caught a late narrow margin override, a stretched mobile
back button and a photo/header seam risk; all three were corrected before the final
build. Source build/check and the focused presentation suite pass. **Not published:**
preview remains `b9445bc3`; main/production remain `fa58a0cb`. Actual browser
geometry at 375/760/1000/1440, fixed-CTA safe-area and physical Safari remain deferred.

Next: do not repeat #1466/#1467. Audit a whole results presentation owner: consolidate
`results-top.js` into `results-presentation.js` only with exact initial/results/edit
scheduling coverage, or retire a bounded `results-layout.css` family only after
collapsed/expanded responsive geometry is available. Keep final-sections,
booking-summary, price, lead, API and analytics contracts.

### Latest checked checkpoint — booking navigation and hidden card copy — 2026-09-07

Source PR #1466 / `bc5e0d123e125ddd6d4603e2c6051dbe161726c4`, tree `f837c7ea0a1c88717e9190f6904f012a369ebdcc`, completes the previously uncommitted packet. `flight-continue.js` is now a provenance-only slot; its live heading/review transitions share `summary-cta.js` root, tour/review tasks and document click handler. Current card CSS removes late typography/placement rules only for price-copy descendants already hidden by the retained has-results guard. Core selection button-label restoration remains live and necessary.

**Current invocation:0 newly removed bytes.** This pass completes the previously reported local746-byte packet (CSS538 / JS208) through CI and release integration; do not count it again. Six other assets remain identical and main JS12→11 IIFEs. Per-source asset sizes and historical comparisons are retained in the audit, not routine user reports.

The permanent `tests/search3-booking-navigation.cjs` executes compiled IIFEs and compares13 full DOM/event/scroll/focus snapshots with baseline7e51960a; digest `800e65e6d7658048cac39579fb279d2825987c25961995ee53e5902ed013232b`. Baseline snapshots match but the two-task queue fails the new one-task assertion. Current passes with one tour/review task and one click listener. The existing lead-note fixture needed classList.contains for the merged heading owner; corrected before CI. Source check and22 focused presentation checks pass; one local PHP skip is covered in artifact CI. Eleven earlier booking/services snapshots also remain green.

Security `34119497936` and exact artifact `34119497935` SUCCESS. Reuse artifact `10017627199`, digest `sha256:86f485df344f956ae6a074b6f888604a882f9da9bbfe3ac92da8fac8019d3b34`; no release/docs rebuild. Full per-asset hashes: `docs/project/search3-navigation-owner-consolidation.json`.

**Not published.** Preview remains b9445bc3, main fa58a0cb. Browser responsive/current-source lifecycle and physical Safari remain deferred. No duplicate source PR, deployment or production change.

Next: Do not repeat #1466 flight-continue/summary-cta/hidden-card-copy; its old uncommitted mirror on refactor/search3-results-card-owner-v1 is already integrated. Resume from fresh release, not that stale checkout. Next substantial candidate is the remaining results-layout.css card geometry family, but retain it until focused current-source collapsed/expanded geometry and specificity at375/760/999/1000/1440 are verified; the broad relocation experiment was excluded. Keep selected-tour.css pending detail/review/lead375/760/1000 evidence. The supported browser has no viewport resize; do not substitute desktop evidence or retry blocked URL workarounds. final-sections/booking-summary CSS still provide live service and summary geometry; restoreProductionLabels still repairs the core controller final button label. Do not delete these as dead code. Accumulate useful reductions and reuse the checked artifact at the next justified publication; no micro-deploy.

### Previous checked checkpoint — booking and lead-note owners — 2026-09-07

PR #1464 / source `337955cb77977508da3f40df23ef9c926dd2dd1a`, tree
`5c998ce960fd844e5769d8f814918294c90d46a3`, retires two standalone runtime owners.
`booking/services.js` shares the existing booking summary tour/flight state,
number/money helpers and event queue. Tour/flight bursts use one task instead of
three; price-only updates do not recreate services. The private lead note shares
the existing CTA tour/review task; other lifecycle events and public APIs retain
scope. Services without a lead form and note insertion into replaced forms remain.

Eight raw assets **166271 → 165508 (−763 JS)**; CSS94644, JS70864.
Seven assets are byte-identical. Main JS has14→12 IIFEs;9 untouched IIFEs are
byte-identical, while unchanged search-form source has compiler-local identifier
swaps only. This is a small measured technical batch, not a claimed large CSS win.
Baseline301524→165508: **136016 bytes saved**.

Both duplicate-task regressions first failed on the preceding published bundle.
The final compiled source preserves11 settled markup/copy/layout snapshots
(SHA256 `cc860066f8dbf857a76b926ac4bf5cae2b54e84981b86e319ebd4c9646a10234`).
Existing summary/formatter checks pass. One write build and source check; final
Security `34115679894` and exact artifact `34115679887` both succeed, including
PHP/path/source/presentation/isolation checks. Reuse artifact `10016141814`, digest
`sha256:f9ecccf03ccaaaba15612b46df347712b6e616a4fd88d4f6d5de3ecc941dd914`;
release/docs do not rebuild it. Full per-asset hashes and evidence are in
`docs/project/search3-booking-owner-consolidation.json`.

**Not published.** Preview remains `b9445bc3`, main/production `fa58a0cb` unchanged.
Responsive/lifecycle browser evidence and physical Safari remain deferred. Browser
capabilities were refreshed: no viewport resize is advertised. No local/data URL
workaround or repeated preview journey was attempted.

Next: do not repeat these owners. Keep selected-tour.css until current-source
375/760/1000 detail/review/lead geometry is available. Independently audit the
remaining results-layout.css card family against results-cards-v2.css for a
substantial batch; preserve expanded-card/mobile-fact boundaries and exclude
uncertain visual removals. Accumulate technical work; no micro-deploy.

### Latest published checkpoint — accumulated reduction and review repair — 2026-09-07

The accumulated candidate was published through #1460 / run `34112546655` using
the already-checked #1458 artifact. The bounded desktop inspection found a real
cascade regression: the shared `body:has(#selectedTour) #selectedTour` selector
carries two IDs and defeated the linked review owner's single-ID selector. The
38px hotel heading was squeezed into a 176.844px column beside a 467.156px note.
This intermediate visual state was not accepted as green.

Correction #1461 / source `b9445bc3c8aa0713b148241bcddeefdf07576079` restores
sufficient specificity inside the current review owner, with one compact gap and
left-aligned note. No donor layer was restored. A focused regression failed first,
then passed; the 22-test presentation suite completed with one PHP-only local skip
covered by source CI. Security `34113092248` and exact artifact `34113092204` passed.

Final artifact `10015159660`, digest
`sha256:67310b31ff15ed13d0d7eb0ec5f4f5e770648bb0e5085f1fe5374e2d7d197d6f`,
was reused without a rebuild in corrective publication #1462 / run `34113272969`.
Deployment evidence `10015213085`, digest
`sha256:95edb09b7c67bdcc0d19b778c4952635d3e80e6e5bd66a9fcc2d7023611dc84f`,
confirms all715 exact files, noindex, counter0, disabled lead403, internal PHP denial,
retained rollback and unchanged13 production fingerprints. Evidence ZIP and
before/after/final fingerprints were independently verified after both publications.

Final eight raw assets: **166978 → 166271 bytes (−707 net this pass)**.
The injector/boundary package saved863; the live correction adds156, fully counted.
CSS **94644**, JS **71627**. Compared with the prior published219880 bytes, the
preview is **53609 bytes smaller**; cumulative reduction from301524 is **135253**.
Per-file hashes and full provenance: `search3-review-injector-boundary-repair.json`.

Actual visual evidence: cloud Chromium **1363×936**, initial form, results/tools,
expanded hotel and selected flight/review. Corrective screenshot confirms LUXOR
APART in one line at22px, a single632px heading column, left-aligned note,69301RUB
total and the application CTA. No horizontal document overflow; all eight live
cache keys match the final source. The lead form was not visited or submitted.
Map label was inspected; map interaction was not. No full matrix was repeated.

Responsive375/760/999/1000, lead lifecycle visuals and physical Safari remain
**deferred**: this browser has no supported viewport-resize capability. The earlier
local/data fixture policy blocks were not bypassed; normal public preview interaction
was independently authorized. Main remains `fa58a0cba6dcfc8624d98c20d64fa06330eae309`.

Next: retire the whole `selected-tour.css` donor only after current-source
detail/review/lead geometry at375/760/1000. Its6002 emitted bytes still own shared
shell/back/photo/facts/section-title/fixed-CTA primitives; salvage those in current
owners. Estimated2–3KB net is an opportunity, not measured savings. If the viewport
prerequisite remains unavailable, audit another independent presentation owner.
Do not repeat completed removals or publish another micro-savings-only batch.

### Previous checked candidate — final style injector and boundary repairs — 2026-09-07

PR #1458 / source `90b3a3df3a54ccd513d76dade1307f8354920cbd` retires the last
runtime CSS injector (`summary-cta-styles.js` and its private stylesheet). Common
review grid anchors now have one linked owner in `review-layout.css`; phase
isolation, phone recap geometry, CTA sizing and native nesting are retained.

The same package repairs four confirmed boundaries from earlier unpublished
retirements: compact toolbar display through 999px without duplicate desktop
actions, default hiding of the phone entry on desktop, canonical map-button text,
and synchronous displayed-total refresh through the compatible `decorate()` API.
Price arithmetic and lead/API/analytics contracts are unchanged. Regressions were
reproduced in focused tests before fixes; these tests are not visual acceptance.

Eight raw assets: **166978 → 166115 bytes (−863 net)**. JS falls **2899 bytes**;
linked CSS grows **2036 bytes**, included in that net result. Injector consolidation
alone saves 924 bytes; compact repairs add 61. Cumulative baseline reduction is
**135409 bytes** from 301524. Audit with all eight exact hashes:
`docs/project/search3-review-injector-boundary-repair.json`.

Security `34111711478` and exact artifact build `34111711401` completed successfully.
Reusable artifact `10014623594`, digest
`sha256:bbd6ae86ba257c2c5f73c8a4c75c14971a7ad03d54902e16dd3017e7eb818eaf`.
Local build/check, toolbar/results/selected-flow/linked-style regressions, 35
source/presentation tests and owner validators passed; the one PHP-only local skip
is covered by CI. Docs reuse the source artifact and do not rebuild it. The older
results-context audit also receives a metadata-only empty-file SHA256 correction.

**Not published.** Main remains `fa58a0cba6dcfc8624d98c20d64fa06330eae309`;
no production or preview operation was performed. A cloud browser is available,
but current-source local/data fixture navigation was blocked by its URL policy.
Those attempts stopped without a workaround; no screenshots or visual pass are
claimed. Current-source toolbar/entry/map/review geometry and Safari stay deferred.

Next: reuse this exact artifact for a justified isolated-preview checkpoint and
briefly inspect affected toolbar/entry/map/review states. Then retire the whole
`selected-tour.css` donor only after current-source detail/review/lead geometry at
375/760/1000 is checked. Its 6002 emitted bytes contain still-required shared
shell, back-button, photo/facts, section-title and fixed-mobile-CTA primitives;
salvage those into current owners. The estimated 2–3 KB opportunity is not measured
savings or permission to remove the donor without the missing geometry evidence.

### Latest checked candidate — legacy results context owner — 2026-09-07

PR #1456 / source `9d24bb7242779f560bc1e603e0346078b591d818` retires
the full legacy `results-context.css` top-chrome owner. Functional initial/results/
edit/local-empty guards, shell isolation, mobile drawer default and expanded-card
frame remain in current owners. The old five-column desktop summary, dense tools
skin and list/map text substitutions were intentionally not copied.

Eight raw assets fall **172787 → 166978 bytes (−5809 CSS)**. Across #1455/#1456
this run removes **9063 bytes**; cumulative baseline reduction is **134546 bytes**.

Security `34109163416` and exact artifact build `34109163488` passed. Reusable
artifact `10013640138`, digest
`sha256:aafee14237902c79f00148eed35666f3882b0ae1ada71e00fddc3f0d2f6bd114`.
Local exact build/check, focused state-owner regressions, 35 source/presentation
tests and both owner validators pass; one PHP-only local skip is covered by CI.
Audit: `docs/project/search3-results-context-owner-retirement.json`.

This is a checked isolated-candidate design experiment, **not a published visual
acceptance**. Preview remains `4b061396`; main/production remain `fa58a0cb`.
Chromium is unavailable, so initial/results/edit/local-empty geometry at
375/999/1000/1440 and the expanded-card frame remain deferred.

Next: before publication, inspect the intentional summary/tools reset at 375 and
1440 plus the toolbar seam at 999/1000. If browser evidence remains unavailable,
continue auditing a different independent owner; do not restore this retired layer.

### Latest checked candidate — card and selected presentation owners — 2026-09-07

PR #1455 / source `a3d5b0a36f304bb7408d2d818d07f3d896439d34` retires
the remaining `result-cards.css` donor, the static selected-tour style injector and
the standalone `tour-presentation.js` scheduler. Live mobile entry/order rules now
belong to current entry/results owners; selected formatting and price scope share
the existing selected-flow RAF/observer and retain the compatibility facade.

Eight raw assets fall **176041 → 172787 bytes (−3254)**. CSS rises 566 bytes from
moving live rules into linked owners; JS falls 3820 bytes by deleting two runtime
owners. Cumulative baseline reduction is **128737 bytes**.

Security `34107936139` and exact artifact build `34107936135` passed. Reusable
artifact `10013136334`, digest
`sha256:4050faa4151357634d9e2c2344f0924104b0ba0389fa27d3b2a72d0608dcc45d`.
Local exact build/check, focused selected-flow/toolbar/linked-style regressions,
35 source/presentation tests and both owner validators pass; one PHP-only local
skip is covered by artifact CI. Audit:
`docs/project/search3-card-selected-presentation-owner-retirement.json`.

This source is **checked but not published**. Preview remains `4b061396`; main and
production remain `fa58a0cb`. Chromium is unavailable, so mobile entry/result order,
selected-tour geometry and physical Safari remain deferred; no visual equivalence
is claimed.

Next: audit `results-context.css` against current results-layout and entry owners.
Keep its unique compact-summary and shell boundaries until exact ownership and
responsive geometry are bounded.

### Latest checked candidates — selected mobile and hotel package owners — 2026-09-07

Two independent whole-owner packages were completed and fast-forwarded into release:

- PR #1452 / source `b778f18b70fd6dab330696c5b7ef89bca406de8d`
  removes `selected-tour-mobile.js`. The current selected-flow owner now provides the
  single RAF/observer, mobile bar, compatibility API, lead-field normalization and
  normal/no-flight CTA behavior.
- PR #1453 / source `15ba3e46a43513b8452cc02a38ed6576ac59f3c1`
  retires `hotel-packages.css`. Required expanded-offer and mobile package rules now
  live in the current results layout owners; obsolete card/mobile fallbacks and dead
  scrollbar selectors were not copied.

Eight raw assets fall **180248 → 176041 bytes (−4207)**: CSS 100125 → 97695
(−2430), JS 80123 → 78346 (−1777). This is a net payload measurement: the mobile
compatibility code moved into selected-flow is counted against the removed JS.
Cumulative baseline reduction is **125483 bytes**.

Both packages passed the two mandatory source jobs. Final Security is `34103338435`;
final exact artifact build is `34103338407`, artifact `10011377372`, digest
`sha256:9abca895127b7701554b3089f0a03512af17a789cf95bab81dcf5402170b7e6d`.
Local exact build/check, 35 source/presentation tests, the selected-flow compatibility
regression and both owner validators pass; one PHP-only local skip is covered by CI.
Audit: `docs/project/search3-selected-mobile-package-owner-retirement.json`.

This source is **checked but not published**. Preview remains `4b061396`; main and
production remain `fa58a0cb`. Chromium was unavailable, so selected mobile/review/
lead and expanded-package geometry plus physical Safari are deferred; no visual
equivalence is claimed.

Next: combine retirement of the remaining `result-cards.css` donor and static
selected-tour style injector with another material owner package. Preserve mobile
search entry/order and bound card/selected geometry before any preview publication.

### Latest checked candidates — review and tour density retirement — 2026-09-07

Two substantial packages were completed and fast-forwarded into release:

- PR #1450 / source `62a21a311a411533ef4d85aa763badceed56f374`
  removes the historical desktop/mobile review-density layer and stale mobile
  grid rows 6/7. The current review board remains; a compact `<=999px` boundary
  keeps the lead form and summary in one explicit column.
- PR #1451 / source `f6496c8a7b2532914a3769bfd8f42aabb4f1bea6`
  removes desktop tour/flight density overrides. Paired-flight layout, stage
  isolation, secondary facts, room access and current CTA owners remain.

Eight raw assets fall **185325 → 180248 bytes (−5077)**: CSS 105202 → 100125;
JS remains 80123. Cumulative baseline reduction is **121276 bytes**. Both source
packages passed Security and exact artifact CI. Final runs are Security
`34097800910` and artifact `34097800920`; reusable artifact `10009288882`, digest
`sha256:e5e2dfcae6e619c9e318bd8bdd68125157b91fbdce3df81625ade25db2cba220`.
Local exact build/check, 35 source/presentation tests and both owner validators pass;
one local PHP-only skip is covered by artifact CI. Audit:
`docs/project/search3-review-tour-density-retirement.json`.

This source is **checked but not published**. Preview remains `4b061396`; main and
production remain `fa58a0cb`. Review/lead geometry, desktop tour/flight visual
density and physical Safari are deferred and are not claimed as visually passed.

Next: consolidate `selected-tour-mobile.js` into the current selected-flow owner
while preserving its compatibility API, lead-field normalization and normal/no-flight
CTA behavior. Accumulate the smaller result-card/injected-style remnants rather than
opening standalone micro-PRs.

### Previous checked candidate — hidden results chrome and shared drawer — 2026-09-07

Source #1449 / exact release `bacc8d2547c0516229a1eb0e8e4938bbedec7d65`, tree
`c424ad77b20853a4a870845c120c51e59d367681`, removes the permanently hidden
results-meta renderer and repeated static intro resets. Visible counts/routes and
the one search-form H1 owner remain. Repeated phone/tablet drawer surface rules
now live in mobile-results-toolbar; unique sizing, padding and fixed/sticky action
placement remain in their breakpoint modules. Original fractional media gaps remain.

Eight raw assets fall **187488 → 185325 bytes (−2163)**: CSS 106281 → 105202
(−1079), JS 81207 → 80123 (−1084). Cumulative baseline reduction: **116199**.
Five public files remain byte-identical; protected contracts are unchanged.

Required source CI passed: Security `34095295829`; exact artifact `34095295930`,
artifact `10008361158`, digest
`sha256:544d437bfa48295b0518f61be4e160f72a27b589e56be63ae72b595c7f45673d`.
35 local source/presentation tests pass, with one PHP-only local skip covered by CI.
The hidden-meta regression first failed on baseline. The affected CSS final maps
match at 375/760/760.5/761/999/999.5/1000. One local correction/rebuild retained the
fractional gap before the only source push/CI; docs/release reuse that artifact.
Audit: `docs/project/search3-results-chrome-drawer-retirement.json`.

This source is **checked but not published**. Preview remains `4b061396`, main
and production `fa58a0cb`. Browser connected, but local fixture navigation was
blocked by URL policy; no workaround or visual acceptance is claimed. Public
preview publication, drawer visual states and physical Safari are deferred.

Next: Do not repeat hidden results meta/intro resets or phone/tablet drawer consolidation. Next substantial candidate: replace the historical 3521-source-byte desktop/mobile review-layout block with the compact current lead/review boundary, but first verify relevant geometry using an allowed browser fixture or exact isolated preview. Check the remaining mobile review grid-row 6/7 overrides before claiming the previous row compaction complete. Keep result-cards (~1022 net) and static-injector wrappers (~563 net) accumulated, not standalone PRs. Continue independent safe work if geometry remains unavailable.

### Previous checked candidate — booking chrome owner retirement — 2026-09-07

Source #1447 / exact release `7662d6e15122f58fdc8407431fceee8efd2f0513`,
tree `2c02091d36b3013f566d925281d668b09665ac61`, retires the separate
booking-progress runtime and CSS owner plus the decorative final-review heading
runtime. The selected-tour path keeps its primary flight-to-review action,
summary-to-lead/back actions, booking summary, lead lifecycle and accessible
selected-hotel heading. The current selected-flow owner also continues to hide
the old `.selected-tour-progress` donor.

Selected and review grids no longer reserve empty rows for the removed chrome.
Eight raw public assets fall **195944 → 187488 bytes (−8456)**: CSS 110339 →
106281 (−4058), JS 85605 → 81207 (−4398). From the 301524-byte whole-layer
baseline the checked reduction is now 114036 bytes. All eight public paths and
protected business contracts remain unchanged.

Both mandatory source jobs passed with guards unchanged: Security `34092785050`
and exact artifact build `34092785098`; artifact `10007458840`, digest
`sha256:bfb96aec3231e93ed4b7763e51469ddb81aae7f1d5fa4b79dae575c46511c1f6`.
The single exact source build was reused. Local exact check and 35 focused
source/presentation checks passed; one PHP-only local check was skipped because
PHP is unavailable. Audit: `docs/project/search3-booking-chrome-owner-retirement.json`.

This source is **checked but not published**. The isolated preview remains source
`4b061396`; main and production remain `fa58a0cb`. No browser or visual pass is
claimed. Selected-tour → review → lead/back and geometry at
375/640/999/1000/1363 plus physical Safari remain deferred.

Next: do not restore the retired progress strip or decorative review banner. A
fresh independent audit measured only about 1022 net bytes from retiring
`result-cards.css` after active-rule migration, so accumulate it with another
substantial owner and only when bounded mobile/result-order geometry can be
checked. Otherwise inspect a different large presentation owner. Protected
price, Tourvisor/API, lead transport/mapping and analytics remain out of scope.

### Previous checked candidate — form and mobile-entry owner retirement — 2026-09-07

Source #1445 / exact release `c62ebc1dedcba458fe69ab232fea6000aadf781c`,
tree `39b698edd9cb26e78df5bf2cf144c945073fc4ac`, retires the legacy form
presentation family from `base.css`. That module now owns only the shell, page
intro and mobile gutter. Required form surface, controls, guest popover, quick
filters and responsive entry rules are retained in current `entry-v1.css`.

The separate `mobile-search-entry.js` runtime donor is provenance-only. Its one
trust/filter DOM instance and ARIA toggle now live inside the existing search-form
composition; linked CSS remains the presentation owner and no runtime style is
injected. The same package removes booking-summary's resize subscriber because
its layout is already CSS-owned; render, price and review/lead lifecycle events
are unchanged.

Eight raw public assets fall **199740 → 195944 bytes (−3796)**: CSS 111769 →
110339 (−1430), JS 87971 → 85605 (−2366). From the 301524-byte whole-layer
baseline the checked reduction is now 105580 bytes. All eight public paths and
protected business contracts remain unchanged.

Both mandatory source jobs passed with guards unchanged: Security `34088767330`
and exact artifact build `34088767361`; artifact `10006114313`, digest
`sha256:3c9b75bdde96d378774a61df31af59ffe32925b86d25931bdc1b0b0f5d6286d9`.
The single exact source build was reused. Local build check and 34 focused
source/presentation checks passed; one PHP-only local check was skipped because
PHP is unavailable. Audit: `docs/project/search3-form-mobile-owner-retirement.json`.

This source is **checked but not published**. The isolated preview remains source
`4b061396`; main and production remain `fa58a0cb`. The executor had no Chromium
binary, so no browser claim is made. Initial/editing form widths, guest popover,
child ages, mobile advanced toggle and physical Safari remain deferred.

Next: do not repeat the base form or mobile-entry retirement. The complete
`result-cards.css` donor is the next measured candidate, but its active mobile
entry and result-order rules must first move to current owners and pass bounded
browser geometry. If that evidence is unavailable, inspect a different large
owner instead of speculatively removing it. Protected price presentation,
`final-sections.js` and `review-layout.css` remain outside speculative deletion.

### Latest checked candidate — review and focus owner retirement — 2026-09-07

Source #1444 / exact release `ad997e4ed010f6178fabcdec2ba0d17fddadf0f8`,
tree `3dca79618b8079e8ca79f965b017c389fbe9f50c`, retires the complete
legacy `review.css` presentation donor and the obsolete desktop family in
`selected-tour.css`. Unique heading/action/submit rules now live in current
`review-layout.css`; four non-final grid/seam declarations are retained in
`tour-detail.css`. The existing responsive review geometry block is byte-identical.

The same package replaces six-frame selected-entry polling with one frame and
removes Search3's duplicate eight-frame return-focus loop. Production renders the
selected DOM before `v2:tour-selected`; the base `selected-tour-return-v1.js`
remains the canonical owner that focuses the exact initiating tour button.
Production-label recovery and `aria-busy` lifecycle remain in Search3.

Eight raw public assets fall **203704 → 199740 bytes (−3964)**: CSS 113988 →
111769 (−2219), JS 89716 → 87971 (−1745). From the 301524-byte whole-layer
baseline the checked reduction is now 101784 bytes. All eight public paths and
protected business contracts remain unchanged.

Both mandatory final-source jobs passed with guards unchanged: Security
`34085700487` and exact artifact build `34085700527`; artifact `10005140803`,
digest `sha256:9351169c7b89c9dfb01998a4dd3e7c7dec34b7ae73521d32d5237dee8d8b7bad`.
One exact source build was reused. Local exact check, 19 presentation tests (one
PHP-only local skip), focused review/handoff/summary/selected regressions and diff
check passed. Audit: `docs/project/search3-review-focus-owner-retirement.json`.

An earlier Security attempt `34085544728` failed because the prior docs-only
checkpoint contained a truncated `AUTOPILOT_STATE.json` Git blob. The exact local
blob `bcc118b9c70707cd706de6f0394f9295912cf7f0` was restored; validators and runtime
guards were not changed. The final exact head passed both required jobs.

This source is **checked but not published**. The isolated preview deliberately
remains source `4b061396`; main and production remain `fa58a0cb`. No browser or
visual pass is claimed. Selected/back focus, review/lead geometry at
375/430/641/999/1000/1363, final fact visibility and physical Safari remain deferred.

Next: do not repeat `review.css`, selected-tour desktop geometry or the duplicate
focus loops. `final-sections.js` remains the only visible owner of several review
facts and must not be removed yet. The separately audited 3535-byte review-layout
block still requires computed review/lead evidence. Audit a different large owner
or combine other measured safe fragments; protected price presentation stays out
of this reduction pass.

### Latest checked candidate — filter and maket7 owner retirement — 2026-09-07

Source #1443 / exact release `38be9f08c1b06635bb59d631e5cc85f7b3af3d70`,
tree `04bfe2f0b2356a170e0ffee8172caac07041e6e8`, retires the complete
`filters.css` presentation donor and the complete `maket7-lock.js` DOM/inline-style
owner. Only active rail/mobile declarations move to `results-layout.css` and
`mobile-results-toolbar.css`; the injected confidence grid becomes static in
`selected-flow-v2.css`. Current search-form/entry modules retain field placement.
The same package removes results-top inline width/offset/padding calculations and
its resize listener while preserving coalesced result-state mutation updates.

Eight raw public assets fall **209753 → 203704 bytes (−6049)**: CSS 115796 →
113988 (−1808), JS 93957 → 89716 (−4241). From the 301524-byte whole-layer
baseline the checked reduction is now 97820 bytes. All eight public paths and the
protected business contracts remain unchanged.

Both mandatory source jobs passed with guards unchanged: Security `34084631292`
and exact artifact build `34084631257`; artifact `10004793873`, digest
`sha256:098afd4abc7e9f2941e7d64fa87e37b57dcdb38267902effe9e638c25219dc89`.
One exact source build was reused. Local exact check, 18 presentation tests (one
PHP-only local skip), focused result scheduler/filter/mobile owner regressions and
diff check passed. Audit: `docs/project/search3-filter-maket7-owner-retirement.json`.

This source is **checked but not published**. The isolated preview deliberately
remains source `4b061396`; main and production remain `fa58a0cb`. No browser or
visual pass is claimed. Filter/results-tools geometry at 390/768/999/1000/1024/1440,
selected confidence at 1000/1363, review/lead geometry and physical Safari remain
deferred.

Next: do not repeat `filters.css`, `maket7-lock.js` or results-top inline geometry.
The separately audited 3535-byte review-layout block still requires computed
review/lead evidence at 641/999/1000/1363. Until that evidence exists, inspect a
different large presentation owner or combine the measured selected-tour and
responsive fragments with a substantial safe package; do not touch protected
price presentation merely to increase the byte saving.

### Latest checked candidate — results donor retirement — 2026-09-07

Source #1442 / exact release `8b0cc058cf33ba724a376f8dd254373e227ee990`,
tree `78901179d7475cb35986536e48d5b5470011089c`, retires the complete legacy
`hotel-results.css` presentation donor and preserves its active hotel/tour rules in
the current `hotel-packages.css` owner. The same measured package consumes the
previously accumulated superseded `results-context.css` set, removes the duplicate
tour-list header renderer and orphan rules, and removes JS-owned booking-summary
geometry while retaining its dataset/title/flight-label lifecycle contract.

Eight raw public assets fall **215784 → 209753 bytes (−6031)**: CSS 120391 →
115796 (−4595), JS 95393 → 93957 (−1436). From the 301524-byte whole-layer
baseline the checked reduction is now 91771 bytes. All eight public paths and the
protected business contracts remain unchanged.

Both mandatory source jobs passed with guards unchanged: Security `34080753450`
and exact artifact build `34080753417`; artifact `10003613312`, digest
`sha256:79a9746e93a840a134ba3bc0b5847bba46efb861af00bf8b3794d17a3ec12fa1`.
One exact source build was reused. Local exact check, 17 presentation tests (one
PHP-only local skip), injected-style and mobile-toolbar scheduler/ownership checks
and diff check passed.

This source is **checked but not published**. The isolated preview deliberately
remains source `4b061396`; main and production remain `fa58a0cb`. No browser or
visual pass is claimed for this unpublished source. Hotel/tour states at
390/768/1024/1440, collapsed/hidden rows, booking review/lead geometry at
641/999/1000/1363 and physical Safari remain deferred.

Next: do not repeat these retired owners. The next large candidate is the 3535-byte
review-layout block at lines 15–71, but it is a medium-risk visual redesign and must
first prove computed review/lead geometry at 641/999/1000/1363. If that evidence is
not available, accumulate the independently safe 643-byte selected-tour and
737-byte responsive fragments with another substantial owner before one build.

### Latest checked candidate — acceptance card families — 2026-09-07

Source #1441 / exact release `0dd4fd4e656f222bb8f75fce8e53772ea2133146`,
tree `66071f29ad139260db6c69ef23d2d3325d2a022d`, removes two duplicate
result-card/readability families from `acceptance-guards.css` and the earlier
mobile lifecycle foundation from `lead-state.css`. Independent source audits
found the unique mobile fact gap/min-height and stay-site width before deletion;
those declarations now live in `results-cards-v2.css` and the retained current
lead-state owner. Selected-tour typography lives in `selected-flow-v2.css`.
The remaining acceptance and lead-state blocks are active and are not whole-file
retirement candidates.

Eight raw public assets fall **219880 → 215784 bytes (−4096)**, entirely in CSS:
124487 → 120391. JS remains 95393 bytes. From the 301524-byte whole-layer
baseline the checked reduction is now 85740 bytes. Paths and protected contracts
are unchanged; this is raw byte accounting, not a transfer-size or speed claim.

Both mandatory source jobs passed without guard changes: Security `34077463937`
and exact artifact build `34077463934`; artifact `10002568100`, digest
`sha256:da8d91f85e14f2f487da6d820d5a2e316fa2942bab6448e579c2e2b5d149e99d`.
Local exact build/check, 16 presentation tests (one PHP-only local skip), injected
style/toolbar ownership and diff checks passed. The successful source artifact was
fast-forwarded into release without rebuilding.

This source is **checked but not published**. The isolated preview deliberately
remains source `4b061396`; main and production remain `fa58a0cb`. No browser or
visual pass is claimed for the unpublished source. Card widths 375/430/1024/1348/
1440, expanded tours 375/430, selected flight 375/1024, lifecycle states at mobile/
tablet boundaries and physical Safari remain deferred.

Next: do not repeat the retired acceptance/readability/lifecycle families. A fresh
read-only audit found a proven superseded block set in `results-context.css` worth
1064 public bytes, but it is too small for a standalone PR/build/deploy; accumulate
it with another substantial safe owner removal. Do not retire `results-context.css`,
`results-layout.css` or `tour-detail-convergence.css` wholesale: their lifecycle,
structural/open-card and selected-tour geometry remains unique. Use one measured
package, two mandatory jobs and an exact artifact; publish only at a justified
checkpoint.

### Latest checked and published candidate — lead review owners — 2026-09-07

Two large reversible owners were retired in one run. Source #1437 / exact release
`4b061396bf3374e026f29943c78f90396352a135`, tree
`d00be539e6ca873658d22cbea0ae99f7996f8383`, removes the complete
`lead-review.css` layer and the complete private lead-entry block from injected
`selected-tour-mobile.css`. The compact active rules now live in current owners:
`review-layout.css`, `selected-flow-v2.css` and `lead-state.css`. Consent and
protection presentation, hidden duplicate summary/comment, mobile final rows and
sending/success/error lifecycle are retained. Pending steps stay blank until
complete; the sending state keeps its spinner instead of premature ticks.

The same eight raw public assets fall **225972 → 219880 bytes (−6092)**:
CSS 128494 → 124487 (−4007), JS 97478 → 95393 (−2085). The first package
saved 4175 bytes and the independent injected-owner package saved another 1917
net bytes. From this run's 235139-byte start the total saving is 15259 bytes;
from the 301524-byte whole-layer baseline it is 81644 bytes. These are raw asset
bytes, not transfer-size or speed claims; all eight public paths are unchanged.

Both mandatory source jobs passed with their owner/source/PHP/path/presentation/
isolation guards unchanged: Security `34072173651`; artifact `34072173661`,
artifact `10000822583`, digest
`sha256:47025ef6e71d2e1ceaf24081fee8f8f7daf7ea1a56cb4516ecac2aa6d46ede7d`.
The artifact has 715 files; archive, manifest and payload-control hashes are recorded
in `docs/project/search3-lead-review-layer-retirement.json`.

The first publication control #1438 / run `34072338032` failed safely before SSH:
an incorrectly reconstructed local payload-control hash did not match the exact
artifact. Nothing was published or changed on the server. The control was read
from the downloaded artifact and the corrected #1439 / run `34072481129` succeeded.
Deployment evidence `10000913111` confirms exact source/tree, all 715 files,
noindex, counter 0, lead 403, retained rollback and the identical production
fingerprint before/after/final. Preview and checked release now both use source
`4b061396`; main/production remain `fa58a0cb` and unapproved.

One bounded Chromium desktop check at 1363×936 traversed live search, an expanded
hotel, selected tour/flight, final review and the empty lead form without submission.
The no-flight fallback reads «Аэрофлот · рейс уточняется», not fabricated `SU000`;
real alternatives retain their supplied flight numbers. No horizontal overflow or
lead-form clipping was found; consent is flex with a 15×15 checkbox, protection text
and the 320px summary are visible. All eight live cache keys match exact source
hashes and no external Metrika/consultant script is loaded.

The supported browser cannot resize this tab. Mobile 375/430, tablet 641/768/999,
preview sending/success/error visuals, physical Safari, full matrix and real lead
submission are deferred, not passed. Source lifecycle regressions passed, but they
do not replace those visual claims.

Next: audit obsolete block families inside `acceptance-guards.css` and
`lead-state.css`; neither file is safe for whole-file deletion. Remove only proven
superseded families in one measured batch while retaining lifecycle, hidden and
accessibility fallbacks. Do not repeat the two lead owners or earlier retired layers.

### Previous checked and published candidate — responsive review owners — 2026-09-07

Source #1434 / `339a1aefa3ba87b156134da37ad2c40b7e5725f0`, tree
`99b03301632b565c99bad491f17a88cfa6d7cd00`, is fast-forwarded into release.
The complete legacy `review-responsive.css` layer and the earlier injected mobile
final-review block are retired. Current summary CTA, selected-tour mobile, review
layout and intrinsic flight owners retain the final, lead and tablet boundaries.

Eight raw public assets fall **235139 → 225972 bytes (−9167)**: CSS −7754,
JS −1413 from removed private embedded CSS. Same eight public paths; no transfer-size
or speed claim. One successful final build, 13 source-build tests, injection/root/
idempotence and final/lead assertions, selected scheduler and all eight booking-summary
operation traces passed locally.

Both mandatory source jobs passed without guard changes: Security `34071038792`;
artifact `34071038942`, artifact `10000469617`, digest
`sha256:c0489ded399f00fa6227467407ecc47f88acbbd23f11a92e04736bb9ca6deab6`.
The exact artifact was reused in isolated publication #1435 / run `34071230834`;
deployment evidence `10000522297`. All 715 files, actual controls, noindex, counter0,
disabled preview leads, rollback and unchanged production fingerprints passed.

The published desktop initial page was inspected at 1363×936: no horizontal overflow;
all eight live cache keys match exact source hashes; no Metrika/consultant script.
The supported cloud browser cannot resize this tab. Therefore affected mobile final
review/lead at 375/430 and tablet flight choices at 641/768/999 are explicitly deferred,
as are physical Safari, full matrix and real lead submission. Production/main remain
unchanged and unapproved. Audit: `docs/project/search3-responsive-review-layer-retirement.json`.

Next: audit the `lead-review.css` / `lead-state.css` lifecycle overlap as the next
large reversible candidate. Do not repeat `review-responsive` or the earlier retired
layers. Require sending/success/error and 641–999 boundary evidence before deletion.

### Latest checked and published candidate — native cards and flights — 2026-09-07

Source `71efd3fe4e42987fbd596c0e4d2bba08f785fa5d` (#1432), tree `a5b3994ffe8df4626b2e3fbc0134a89a00368705`.
Preceding source `857fba4a3768b5647f1f1eb59641c5e372f952ce` (#1430).
Three substantial presentation block families retired in one batch: old result-card
geometry, private mobile/tablet microlayout, flight direction class-mutation + its CSS.
The retained active lifecycle owners are not counted as wholly retired files.

| Eight raw assets | Before this run | Final | Saved |
| --- | ---: | ---: | ---: |
| CSS | 142301 | 136248 | 6053 |
| JS | 103190 | 98891 | 4299 |
| Total | 245491 | 235139 | 10352 |

Initial package245491→235657; corrective package235657→235139 (−518CSS, allJS identical).
JS saving includes embedded CSS. Whole-layer baseline301524→235139 (−66385).
Previously published267536→235139 (−32397); no compression or speed claim.

Both mandatory source jobs passed. Final Security34069311162, artifact
34069311068, artifact9999945885, digest `sha256:494c414b54be8320a3a3120972f11553b763f29ff4f936feca1618dcefc570ee`.
One build per useful package; no release/docs rebuild. Existing owner/source/PHP/path/
presentation/isolation guards unchanged. Focused scheduler/primary review-back,
injection/root/idempotence and mobile toolbar ownership passed.

Exact isolated preview published through#1433, run34069485080,
evidence9999985225; source and published candidate now match.
Actual archive, both controls and all715 files verified before activation; noindex,
counter0, disabled leads, retained rollback, unchanged production fingerprints confirmed.
Main staysfa58a0cba6dcfc8624d98c20d64fa06330eae309; production acceptance not granted.

Browser was available through control-browser; the preceding no-browser conclusion
was inaccurate and superseded here. Initial desktop1363×936 inspection found narrow
accommodation wrapping, tiny9/7.5px flight details and duplicate tick over price.
Introduction by the last deletion was not proven. Removed the six-column/nth-position
and tiny-font rules in their existing owners; kept existing14/12px flight text and
native selected radio/border. Corrective visual evidence: Corrective Chromium1363x936 screenshots inspected: expanded LUXOR APART facts now have three readable columns without midword accommodation wrap; selected outbound/return airport text14px and secondary12px, duplicate selected tick no longer overlaps72832RUB price. Native radios and next CTA visible.
Deferred: mobile/tablet widths, physical Safari, live no-flight branch, lead-entry/success/error, full filter/editor/price matrix. No full matrix or visual parity claim.
Audit: `docs/project/search3-mobile-card-flight-retirement.json`.

Next: Next substantial candidate: audit review-responsive.css mobile/tablet geometry against current selected-flow/review owners, retire duplicate layout only after protecting hidden/lead-shell/primary CTA boundaries. Do not repeat retired17CSS+1JS owner or the3block families and6-column/tiny-font repair. Current mobile/tablet, live no-flight and physical Safari remain deferred. Continue independent permitted work; production acceptance does not pause automation.

### Latest checked follow-up — review/recap retirement — 2026-09-06

Source PR#1428 is integrated into release at `211f792e5d64ee9016a1742c7fa802f6097a3ad1`.
Four obsolete CSS geometry layers plus the live duplicate desktop recap are retired.
Eight raw assets:265927→253113 (−12814:CSS−9250,JS−3564). Required source Security34067813555
and artifact34067813526 both passed, first attempt. Exact artifact9999511038;
sha256:92de0b79b0b83aa80b7524f635fdbef61a99ff01280208ceeaf69a9af727dc4d.
No release/docs rebuild or deploy. Published preview still9b4303a5, main stillfa58a0cb.
This is an intentional reversible geometry experiment, not visual equivalence.
Selected/no-flight, review/mobile/tablet and lead-entry visual checks are DEFERRED:
no current browser capability. Local scheduler/utility/ownership plus source PHP/path/
presentation/isolation checks passed. Audit:search3-review-layer-retirement.json.
The subsequent form-compatibility package is checked in#1429; see latest status above. Native four-column entry grid remains canonical; do not
restore the superseded six-column form. Historical twelve layers below stay retired.

### Latest checked follow-up — two results layers — 2026-09-06

Source PR#1427 retires results-width-compatibility.css and hotel-card-convergence.css.
After compact fallback salvage, public CSS/JS fall267536→265927 bytes:−1609CSS.
All four JS files are byte-identical to the published ten-layer source.
Across both batches in this continuation:301524→265927,−35597 bytes (11.8%).

This follow-up is CHECKED, NOT PUBLISHED. Final source
`db39eadb15149fd39fce2305fb6de49251685b38` passed Security34066837695 and artifact34066837736
on the first attempt. Exact artifact9999214211 is retained.
The current published preview remains9b4303a5/#1426 at267536 bytes; its focused
desktop screenshots do not verify this follow-up source.

Convergence facts, mobile title/place placement and flex price-row fallbacks now
finish hotel-packages.css at the same position before subsequent owners. Shell,
hidden/lifecycle/MRF and expanded-card guards begin results-context.css.
Independent review identified and avoided moving old margin-left/right resets
across the intervening filters.css shorthand. Uncertain toolbar padding,
letter-spacing and gap were retained. These are compact owner transfers plus
old geometry retirement, not a blanket pixel-equivalence claim.

One source build and four exact JS hash comparisons passed. Source CI retains
Security and the exact artifact job; no second preview/browser journey or
release/docs artifact rebuild. Audit: `docs/project/search3-results-layer-retirement.json`.
Twelve historical CSS layers have now been retired across#1425/#1427; do not repeat those modules or completed minification/dominance/comment/media scans. Published preview remains9b4303a5 with focused desktop evidence; two-layer follow-up source awaits the next justified publication. Preserve remaining shell/hidden/MRF/mobile facts/flex and lead-state fallbacks. No further ready large safe batch is established in this run; further active-layer removal needs concrete state coverage and compact salvage, with mobile/intermediate and lead-state evidence still deferred. Keep one useful source batch/two mandatory CI jobs and artifact reuse. Main/production locked; hourly automation enabled by the latest owner request.

### Historical published batch — retire ten whole CSS layers — 2026-09-06

The owner explicitly requested a larger approach: delete entire layers and repair
resulting breakage. This authorizes reversible presentation-layer experiments in
release and the isolated preview. Protected business contracts and production
approval remain unchanged; incomplete mobile evidence is never called passed.

Source PR#1425 merged into release: `9b4303a50efe79ad6d3477d068bdf599092e74f6`.
Security34065664531 and artifact34065664535 passed; exact artifact9998862575.
One-shot control#1426 published that artifact in run34066277509 and closed without
merge. Evidence9999036179; the deployment workflow is absent from release/main.

| Eight public assets | Previous checked bytes | Published bytes | Saved |
| --- | ---: | ---: | ---: |
| CSS | 194729 | 160782 | 33947 |
| JS | 106795 | 106754 | 41 |
| Total | 301524 | 267536 | 33988 |

Raw reduction11.27%; eight public paths and seven nonempty generated lines retained.
Accumulated reduction versus the previous published preview bff20777:44151 bytes.
No transfer-compression or page-speed claim.

All nine historical cascade modules and visual-compatibility.css now contain
provenance comments only. Small necessary rules belong to lead-state.css,
tour-detail-convergence.css, hotel-packages.css and results-context.css.
This preserves lead status/hidden lifecycle guards, desktop flight/continue
isolation, expanded-tour grid and initial/reset results visibility. Existing
public paths, source ordering and donor hash checks remain. Also removed unused
summary-cost selectors; the only executable JS-tree difference is that exact
CSS literal selector deletion. Three other JS assets are byte-identical.

One asset build and narrow compiled summary/eight layout traces passed.
The initial CI failure was a same-length drift fixture targeting !important,
which no longer exists in marker-only donors. The fixture now corrupts a final
newline with equal byte length; the hash guard remains and final CI passed.

Publication verified the actual archive, both control hashes and all715 payload
files before activation and again remotely. Nine routes, noindex, disabled
preview lead403, counter0, internal PHP denial and retained rollback passed.
All13 protected production fingerprints match before/after/final. Main remains
fa58a0cba6dcfc8624d98c20d64fa06330eae309; no production migration.

Fresh local artifact download returned403 and was not retried. Expected control
hashes were independently reconstructed from the previously verified published
manifest plus the exact eight-asset GitHub payload diff, verifying local assets
against source blob IDs. Fresh archive hash came from green CI; unchanged
deployment guards then verified the actual artifact. No fresh local archive
validation is claimed. A local fixture file URL was also rejected by browser
policy; no workaround or fixture acceptance claim.

Focused live1363px inspection: initial form →100 hotels/426 tours →expanded
LUXOR APART →selected tour with supplier placeholder flight →review. Screenshots
inspected,75,005 RUB retained, next-to-application CTA visible, no horizontal
overflow. No additional CSS repair was necessary in these observed states.
No lead form opened or lead submitted. Mobile/intermediate widths, lead status
visuals, full flight/return/filter matrix, full site/SEO and physical Safari remain
deferred; this is not blanket visual parity or production acceptance.

Audit: `docs/project/search3-whole-layer-retirement.json`.
Next: Continue owner-authorized large reversible presentation-owner retirement in one useful batch; repair demonstrated layout breakage in current owners. Do not repeat ten-layer, minifier, comments, media/dominance or tiny factoring passes. Preserve eight public paths, hidden/lifecycle recovery states, price/API/URL/payload/lead/analytics/logo/browser contracts. Next candidates results-width-compatibility/hotel-card-convergence need active-rule assessment before removal; no safe wholesale deletion established yet. Keep narrow checks and two source CI jobs, reuse artifact; publish only for a justified layout/accumulated checkpoint. Mobile/intermediate widths, lead status visuals and physical Safari remain deferred, not passed. Main/production locked; scheduler paused.

The sections below record status at their original completion; older statements
that a batch was unpublished are historical. Batches#1422–#1424 are now included
in the current publication.

### Historical checkpoint at completion — common CSS blocks and JS spelling — 2026-09-06

PR #1424, source `04cc08317bc0c4d64e31b60bf9694e30164fa972`. Two mandatory jobs passed on the final source:
Security 34064217365 and artifact 34064217372; exact artifact 9998431100 retained.
This is CHECKED, NOT PUBLISHED. Preview remains bff20777/#1421; main/production
remain owner-approval locked and the scheduler stays paused.

| Eight public assets | Previous bytes | Checked bytes | Saved |
| --- | ---: | ---: | ---: |
| CSS | 195173 | 194729 | 444 |
| JS | 107035 | 106795 | 240 |
| Total | 302208 | 301524 | 684 |

Eight paths and seven nonempty generated lines remain. This is uncompressed size;
no transfer/page-speed claim. Accumulated #1422/#1423/#1424 saving versus published
preview: 10163 bytes. No artifact download, preview deployment or browser cycle.

Seventeen adjacent leaf-rule groups share exact declaration sequences, crossing
only disjoint reset families. Original selector strings and parent/media contexts
remain; no nesting increase or selector-list specificity amplification. Independent
review found no cascade/reset conflict. JS changes only second-stage quote/number/
property-key/IIFE spelling; all four final executable Acorn trees and retained
comment sequences match the previous source. First-stage exact printing and
protected import fingerprints remain. Existing unit coverage includes public key
order, prototype setter vs computed own property and numeric spelling.

One final asset build passed. Initial CI found quote-dependent test adapters in CSS literal extraction and
filter/mobile ownership. Literal checks now read actual Acorn values; ownership
checks accept either valid quote spelling while keeping the same operation,
selector and handoff assertions. Final source CI passes; broader responsive/site/SEO
and physical Safari remain deferred.

Further bounded scans found no recursive media gain, only19 bytes from a new
selector-list factor,450 bytes from value pooling with new inherited-variable
complexity, and no large identical helper inside26 JS owners/427 functions. These
experiments were not applied. Previously deferred117/23-byte CSS cases stay deferred.
The CSS444/JS240 tails were combined here; do not repeat completed scans. Further
material reductions require a demonstrated redundant presentation owner while
retaining fallback/intermediate card states and all protected contracts.

Audits: `docs/project/search3-common-css-js-format-reduction.json` and
`docs/project/search3-css-common-block-factoring.json`.

### Previous checked release batch — media roots and cross-asset CSS — 2026-09-06

PR #1423, source `9cf5fe886201e59c9f0be3acc096992dba308290`. Both required jobs passed on the first attempt:
Security34062645262 and artifact34062645263; artifact9997962349 is retained.
This checked batch is NOT PUBLISHED. Preview remains bff20777/#1421; no new
deploy-control PR, artifact download or browser journey.

| Eight public assets | Previous bytes | Checked bytes | Saved |
| --- | ---: | ---: | ---: |
| CSS | 199199 | 195173 | 4026 |
| JS | 107035 | 107035 | 0 |
| Total | 306234 | 302208 | 4026 |

Uncompressed reduction 1.31%; all four JS files byte-identical. Eight public paths,
source ordering, protected fingerprints and readable modules retained.
The new build pass shares only contiguous equivalent single-selector roots
across media, after exact printing. Explicit-& child rules, unchanged selector
paths/conditions/order/style depth; comments in discarded wrappers reject grouping.
The existing CSS test covers those boundaries, including comments inside media.

The source pass removes 75 declarations/13 empty rules dominated by mandatory
later entry/selected stylesheets. The PHP owner and existing presentation test
prove all-or-none fixed main/entry/cards/selected inclusion. No new shorthand or
lifecycle assumptions. Retained declaration stream and combined cascade maps agree
at all42 width samples. One final local build and focused CSS tests passed; full
responsive/site/SEO and physical Safari checks remain deferred.

Card analysis found that visible results do not guarantee results-active or all
final sibling/hidden predicates, so broad old-card removal was rejected. A separate
JS formatter experiment yields only240 bytes; seven broader within-asset CSS
deletions yield117 bytes. Neither micro-pass was applied. A subsequent remaining cross-asset basic enum/color
scan found one23-byte deletion only; it was also deferred without source edits.

Audits: `docs/project/search3-media-cross-asset-reduction.json` and
`docs/project/search3-css-cross-asset-dominance.json`.
Next: Completed follow-up: remaining cross-asset basic enum/color scan found one 23-byte deletion only; defer it and do not repeat that scan. Completed media-wrapper grouping and 75 cross-asset deletions stay in place; earlier completed passes must not repeat. Next substantive reduction needs structural ownership/declaration factoring with a measured net gain: preserve the fallback/intermediate hotel-card states because has-results does not imply results-active or final sibling/hidden predicates. Do not delete those old blocks on that false assumption. Preserve selector specificity, shorthand reset semantics, four-stylesheet order, native nesting depth and all public/protected contracts. Use one working draft per useful batch, narrow evidence and two mandatory CI jobs; no automatic preview/browser. Published preview remains bff20777; checked code9cf5fe; main/production locked, scheduler paused.

### Previous checked release batch — source notes and numeric CSS — 2026-09-06

Working PR #1422; final source `b926eeb4509da236597268af868457eb5f11ef43`. This batch is checked in release
and intentionally NOT PUBLISHED under the owner batch policy. Published preview
remains source bff20777 / deploy #1421. No new deploy-control PR or browser session.

| Eight public assets | Previous bytes | Checked bytes | Saved |
| --- | ---: | ---: | ---: |
| CSS | 201014 | 199199 | 1815 |
| JS | 110673 | 107035 | 3638 |
| Total | 311687 | 306234 | 5453 |

Net reduction 1.75%, uncompressed. Eight paths retained: four CSS files, three
nonempty JS files and the existing empty overlay slot. Readable sources stay.
All four executable JS ASTs match bff20777 exactly; license/tool comments and
first-stage AST/comment equality remain. Test adapters extract actual compiled
IIFEs by stable runtime markers. Source-drift fixtures now mutate executable code
because ordinary comment-only edits intentionally leave generated files unchanged.

CSS removes 61 dominated declarations plus 14 empty rules. Different-value
witnesses are limited to simple supported numeric longhands; no supports or
variable fallback removal. Retained declaration order and final selector/property
cascade maps match across 42 width samples. Acceptance guards and nesting unchanged.

One final local source build, compaction tests, eight affected compiled adapters
and the two corrected drift fixtures passed. First CI attempt exposed those two
comment-only fixtures; the corrected final source passed both mandatory jobs: artifact 34061763852 and
Security 34061763860. Artifact 9997701449 is retained for a later publication.
Broader suites and new live verification remain deferred, not passed.
Audit: `docs/project/search3-batched-css-js-reduction.json`.

Next: Next substantive step: consolidate the hotel-card CSS owner under #results across base/card/cascade/layout sources, first proving the affected final cascade including shorthand/longhand interactions and breakpoint states. Do not rerun completed private CSS optimization, markup helpers, source-comment filtering, 36 equal-value or 61 numeric-dominance deletions. Remaining JS micro-candidates measured below 500 bytes are deferred, not separate PRs. Use one working draft per useful batch, narrow evidence and the two required CI jobs. Do not automatically deploy or replay the live journey; current published preview remains bff20777. New local commands and focused tests worked in this continuation; the historical rejected HTTP poll was not retried. Main/production locked; scheduler paused.

### Previous verification priority — lean preview cycle — 2026-09-06

The owner explicitly requested fewer checks and faster file-size reduction:
«миллион проверок не обязательно ... цель как можно быстрее уменьшить размер».
This supersedes older requirements below to run all 23 workflows, full responsive
matrices, repeated screenshot comparisons or exhaustive journeys for every PR.

Use working draft PRs based directly on `release/search3-production-ready-v1`;
do not temporarily target main just to trigger unrelated CI. Batch useful source
reductions before one release integration/publication. Locally run source build,
size/import checks and the relevant small behavior tests. The existing artifact
workflow supplies PHP rendering, presentation checks and preview isolation once.
The owner additionally asks to remove more duplicate checks. Run two jobs on a
working code draft: Security guard includes both owner-policy validators; the
exact artifact job includes source/presentation, PHP syntax, path boundaries and
isolation. Reuse this successful source artifact after release fast-forward and
documentation checkpoints. The cumulative draft release runs only Security guard;
do not rebuild or redeploy documentation. The source artifact SHA must remain an
ancestor of the exact release pin, as enforced by the unchanged deploy control.

Nineteen broader workflows plus standalone owner/boundary jobs defer only for
draft Search3 release PRs or draft PRs targeting this release. Existing
production-only jobs, main pushes, schedules and manual runs are unchanged.
`ready_for_review` explicitly restores all applicable PR gates, including a fresh
release artifact build, before production consideration; main remains locked.

For a preview batch, inspect the changed controls in the live browser, normally
one desktop and one mobile state when layout changed. Do not run the full site/SEO
visual suite or compare dozens of screenshots after every small change. Broaden
checks only for a concrete failure or at production acceptance. Do not call a
deferred check passed. Keep exact-artifact publication, disabled preview leads,
noindex, rollback and production fingerprints. Scheduler remains paused.

Build compaction uses exact AST/comment equality only for the first printing
stage. The next optimization stage may simplify JavaScript control flow and CSS
values/rules. Preserve public keys, globals, function/class names, argument arity,
eval scopes and getter side effects. Disable unsafe arithmetic and cross-statement
sequence merging; retain native CSS nesting and the existing browser boundary.
Readable runtime sources and protected business contracts remain unchanged.

### Historical published checkpoint — private CSS, markup and media overlap — 2026-09-06

Source `bff20777468c2a7d41df684dc1902c267adde3cf`, source PRs #1419/#1420; one isolated publication #1421,
deploy 34060271120 succeeded on attempt 1. Each working draft passed Security and
the exact artifact job. Release/docs reuse the final source artifact; full
responsive/site/SEO workflows remain deferred under the owner lean policy.

| Eight public assets | Previous bytes | Published bytes |
| --- | ---: | ---: |
| Four CSS | 202114 | 201014 |
| Four JS | 112400 | 110673 |
| Total | 314514 | 311687 |

Net saving 2827 bytes (0.90%); 4 CSS + 55 JS = 59 generated lines. Uncompressed,
excluding shared runtime/legacy and duplicate source; no page-speed claim.
Two private style strings use the existing optimizer before escaping at their
original JS insertion positions. Four behavior owners share private markup and
class prefixes. Sixteen compiled old/new traces retain exact HTML, DOM/selector
operations, listener registrations/arity and supplier getter reads. A conservative
optimizer guard retains Raw CSS comment token boundaries.

The CSS pass removes 36 identical-value declarations with exact-selector witnesses
under broader media or important priority, then nine empty rules. Retained ordered
streams are exact; final selector/property/priority maps match at all 42 numeric
media boundary/adjacent points. Non-numeric/supports contexts and protected
acceptance source stay unchanged. Donor/order and native nesting max three remain.
Build/check/import tests pass; PHP rendering passed in artifact CI.

Artifact 9997204595 from build 34060126171 /
`sha256:d2c69cd121ccbd2ee4159b53fb77b27b698d1f4c3de8bd366b70658b605962cd`.
All 715 payload hashes and eight generated assets verified before publication.
Nine routes, noindex, counter zero, disabled leads, rollback and 13 unchanged
production fingerprints are recorded in evidence 9997242581 /
`sha256:ecb1cad2bf5682df01354e09e31d8d3a3a1c296e2aea949994d2e050ecba6580`.

Focused live desktop 1363px: Moscow/Turkey, 10–11 Sep, seven nights, two adults;
100 hotels / 480 tours -> ANAHTAR APART -> SU2156/SU2157, 72099 -> 89317 RUB ->
review -> empty lead form -> review -> return 100 -> zero-filter 0 -> restore 100
without another search -> editor dates/nights retained. Review and lead screenshots
inspected; no horizontal overflow or real lead. Physical Safari/live mobile and
the full responsive/site/SEO suite were deferred. Browser API had transient
post-click timeouts; fresh DOM confirmed each resulting state.

The local direct-HTTP asset-hash polling result is unconfirmed: automatic approval
review rejected the poll because environment usage capacity was exhausted.
No retry or indirect workaround was used; artifact/remote activation hash proofs,
deployment evidence and the independent browser journey were completed.
Main remains fa58a0cb; production/protected contracts untouched; scheduler paused.

Audit: `docs/project/search3-static-css-publication.json`.
Next: Continue measured reduction from source bff20777. Private injected CSS optimization, four presentation markup/class-prefix helpers and 36 exact-value media/priority deletions are complete; do not repeat them. Remaining different-value CSS candidates require browser-compatibility and cascade evidence before removal. Prefer the largest measured source simplification; preserve eight paths, native nesting max depth three, public keys/globals and price/API/lead/analytics contracts. Reuse one final working-source artifact, focused live checks only. Local command polling hit an environment usage-limit auto-review rejection; do not retry or bypass that blocked operation. Resume new local development when execution capacity is available. Main/production locked; scheduler paused.

### Previous published checkpoint — single build and standard minification — 2026-09-06

Source `47bc232e5f5b48b2a78a026c4f77f01b33fdc23e`, source PR #1416, isolated publication #1417,
deploy 34058520640 (success, attempt 1). Working draft: two successful jobs, two
standalone jobs deferred. Cumulative release draft: one Security job succeeded,
23 jobs deferred; no duplicate release artifact build. Owner validators run in
Security; PHP/path/presentation/isolation run in the one working-PR artifact.
All applicable PR gates restore on ready_for_review; main/production stay locked.

Follow-up #1418 removes twelve identical fixture builds from source-test setup
and checks independent PHP/JS payload files in two processes. All baseline,
drift/failure assertions and syntax checks remain. This changes CI only; the
published source/artifact above remains exact and requires no second deployment.
Follow-up `27d48f081e457a879aa55bcddb3a94d7e18129d2` is in release; Security and
build 34058941809 passed. In these runs the focused stage fell from 48 to 24s
and payload parsing/build from 26 to 10s (combined 74 to 34s). This is an observed
CI run comparison, not a page-speed benchmark.

| Public assets | Previous bytes | Published bytes |
| --- | ---: | ---: |
| Four CSS | 205437 | 202114 |
| Four JS | 115477 | 112400 |
| Total | 320914 | 314514 |

Saving 6400 bytes (1.99% overall); 4 CSS + 55 JS = 59 generated lines.
Build-only Lightning CSS 1.33.0 optimizes rules/values while retaining native
nesting and the existing browser boundary. Terser compression preserves function
and class names, argument arity, eval and getter behavior; unsafe arithmetic,
property/global mangling and cross-statement sequence merging remain disabled.
The first printing-stage exact AST guards remain. Readable runtime sources and
all protected price/API/lead/analytics files remain unchanged. Metrics are
uncompressed and exclude shared runtime/legacy; no page-speed benchmark claimed.

Artifact 9996682414 from build 34058350586 / `sha256:92229fe8e5b17ce9744d4d8ff0922014eb0c25ec5f546f31fec2a0f4da111a7e`.
Evidence 9996718591 / `sha256:4678457ba0263555cfc889084d5df026b8fdba34c96fd0e6d16d80b406c1b33e`. Exact 715-file payload verified;
all seven changed CSS/JS matched by HTTP. Nine routes, noindex, counter zero,
lead disabled, rollback retained, 13 production fingerprints unchanged.

Focused live desktop 1363px: Moscow/Turkey 10–11 Sep, seven nights, two adults;
100 hotels / 431 tours; zero-filter -> restore 100; ANAHTAR APART, SU2156/SU2157,
72099 -> 89317 RUB; review -> empty lead form -> return to 100 results. No real
lead or horizontal overflow. Results screenshot inspected. Full responsive matrix
and physical Safari deferred; mobile live viewport unavailable in the supported
browser surface, compiled mobile toolbar behavior passed in artifact CI.

Audit: `docs/project/search3-single-build-minification.json`.
Next: Continue measured CSS/JS reduction in release-based draft batches. Single-build CI routing and standard CSS/JS minification are complete: do not repeat optimizer comparisons or build after release/docs. Next inspect repeated static presentation strings/private injected CSS for a measured net reduction before source changes; retain readable source, eight paths, native nesting, globals/public keys and all price/API/lead contracts. One working source artifact plus focused live checks; broaden only for a concrete failure. Main/production locked; scheduler paused.

### Previous published checkpoint — lean preview and local JS names — 2026-09-06

Source `4ad216b457562097c78a6a6b9c179e7365d87a0c`, source PR #1414, isolated publication #1415,
deploy 34056652968 (success, attempt 1). The lean verification policy above is active.
Working PR: three required workflows passed, three deferred. Release draft: four
required workflows passed, nineteen broad jobs deferred plus one old migration skip.
Existing release-push rollback check also passed. Deferred checks are not acceptance.

Local variable/parameter/label names now shorten in the build, with compression,
global/top-level/property mangling disabled; function/class names and eval scopes
stay. First-stage exact printing AST guard remains, followed by syntax parsing and
focused compiled execution checks. Readable runtime sources and all CSS unchanged.

| Public assets | Previous bytes | Published bytes |
| --- | ---: | ---: |
| Four CSS | 205437 | 205437 |
| Four JS | 130610 | 115477 |
| Total | 336047 | 320914 |

Saving 15133 bytes (11.59% JS, 4.50% overall); 37 CSS + 55 JS = 92 generated lines.
Uncompressed, excludes shared runtime/legacy and duplicate src; no speed benchmark.
Artifact 9996124818 / `sha256:bec74724991723328cb8839c2e0cb54e4a7e7e5c97a5c279f2083f53004a2cf8`; evidence 9996165721 /
`sha256:5333ce5d982a72f78d62eb392895eb1d830e746bcfad605d218ac8b129e4fc4a`. Exact 715-file deployment payload verified;
all three changed JS files matched over HTTP. Nine routes pass, preview leads
disabled, counter zero, rollback retained, thirteen production fingerprints unchanged.

One focused live desktop 1363px scenario: Moscow/Turkey 10–11 Sep, seven nights,
two adults -> 100 hotels / 425 tours -> zero price matches with visible rail ->
restore 100 -> ANAHTAR APART -> choose SU2156/SU2157, 72099 -> 89317 RUB ->
review -> empty lead form (inspected) -> review -> 100 results -> preserved editor.
No real lead or horizontal overflow. Full responsive/site matrix intentionally
deferred; physical Safari and prior production acceptance limitations remain.

Audit: `docs/project/search3-lean-preview-reduction.json`.
Next: Continue measured CSS/JS reduction in release-based draft batches with the lean preview cycle. Local-name shortening and workflow routing are complete. Inspect remaining repeated static presentation markup or build compaction opportunities; preserve public keys/globals, price/API/lead contracts and eight paths. Do not rerun full site/SEO/responsive matrices without a concrete failure; restore full applicable gates for production review. Preview-only exact publication, production locked, scheduler paused.

### Previous published checkpoint — checked compact assets and zero-match recovery — 2026-09-06

Published source/release integration: `53f87c79cb3d4ca139c2391d1bcf818fa874e737`. Source PRs #1408/#1409/#1410/#1412.
Separate exact preview publications #1411 then #1413; #1413 supersedes #1411 with
the inherited zero-match filter recovery fix. Earlier preparation/pending notes
are historical and superseded by this checkpoint.

| Eight public assets | Previous bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 211465 / 4007 | 205437 / 37 |
| Four JS | 140057 / 1826 | 130610 / 55 |
| Total | 351522 / 5833 | 336047 / 92 |

Net reduction 15475 bytes (4.40% overall; JS 6.75%) and 5741 generated lines.
Uncompressed, excluding shared runtime/legacy and duplicate src; no speed or
deferred-loading claim. Readable source parts remain. Rail owner 11175 -> 5746
bytes, form owner 9433 -> 3682, with two private parts each in original scope.
Eight public paths remain; no new runtime loader/request/global.

Build-only pinned Terser 5.51.2 prints JS with compression and mangling disabled.
Independent Acorn 8.18.0 ordered AST/comment equality rejects unsafe changes before
writes. Pinned css-tree 3.2.1 removes external CSS formatting while selectors,
conditions and value source slices stay exact; ordered AST roundtrip is required.
Protected comments retained; nonshrinking files stay original. Cascade/order,
acceptance guards and native nesting depth three remain. Source build requires
`npm ci --prefix scripts/build/search3-js --ignore-scripts`; existing CI installs it.

The live zero-match trap was also reproduced by the new compiled regression on
pre-run b3b508d0, proving it inherited. The rail now marks local zero matches
before synchronous render; existing geometry/card owners retain their shell.
Restore, external fresh results and actual reset clear it. No filter/price logic
change. Fix costs 347 JS bytes; net savings above already include it.

All four sources passed 23 applicable workflows and one expected skip. Final
core 34054786441, responsive 34054786477, flight 34054786509, build 34054786524.
Twelve source-build, eight JS printing, six CSS printing and 14 presentation tests;
PHP covered in CI. Existing responsive CI verifies zero matches -> resize
1000/1440 -> visible slider End restoration -> reset. New empty-rail screenshot
inspected. Editors at 375/1440 inspected and pixel-identical; 33/42 final common
images exact, nine fixture calendar/date/control variations retained. No blanket
pixel parity. Physical Safari remains unqualified; native nesting contract stays.

Final artifact 9995634191 / ZIP `sha256:7b7588e9e9ee754f2024016ce7242f096591bae99d0a1a9ffaf030468fb8c6ff`.
Deploy 34055271173 succeeded attempt 1; evidence 9995763506 /
ZIP `sha256:4055ea6d3d8b4c7192384f5f4ee37eb6cab471bbba628b1f72e1cfb2b4550731`. All 715 payload files and eight live assets match.
Nine routes HTTP 200/noindex; counter 0, disabled synthetic lead 403, internal PHP
denied, rollback retained; 13 production fingerprints unchanged before/after/final.
Main stays `fa58a0cba6dcfc8624d98c20d64fa06330eae309`; production untouched.

Fresh live desktop 1363px: Moscow/Turkey, 10–11 September 2026, seven nights,
two adults -> 100 hotels / 430 tours. Slider Home 70000 -> zero cards and visible
rail/empty message; End 265000 -> 100 cards without new search. ANAHTAR APART ->
flights 6 -> 71 -> 6 -> review 72099 RUB -> empty lead -> review -> return with
100 cards -> editor retaining dates/nights. Review/lead screenshots inspected;
summary column 3 / row 4–12 -> column 2 / row 1 -> restored review layout.
No horizontal overflow or real lead submission. Editor opened after asynchronous
return completed. Live no-flight fallback, physical Safari and inherited hidden
legacy backdrop on desktop-to-mobile transition remain outside qualification.

Consolidated audit: `docs/project/search3-compact-assets-publication.json`.
Source audits: `search3-filter-rail-private-parts.json`,
`search3-js-build-compaction.json`, `search3-css-external-formatting.json`,
`search3-empty-local-filter-shell.json` (all under `docs/project/`).

Next: Refresh release, PRs and publication before another bounded reduction. Remaining results/cards.js (7698 source bytes) and tour-presentation.js (6114) were inspected: mixed DOM decoration and protected price presentation require a focused behavior trace before deduplicating their helpers. Measure a concrete saving before another source PR. Do not repeat completed rail/form splits, checked JS/CSS printing, or the zero-match shell fix. Keep eight public paths, event/data/price/lead contracts and maximum CSS nesting depth three. Separate exact preview publication only; production locked; scheduler paused.

### Previous published checkpoint — descendant CSS and private booking layout — 2026-09-06

Published source and release integration: `360cf4b7f9b08ca0a85e6d4f96a83997dd07b477`. Source PRs #1405/#1406;
one exact-artifact isolated preview publication #1407. Preparation notes below are
historical and their pending-publication statements are superseded here.

138 adjacent groups now share repeated complete descendant prefixes inside the
existing CSS nesting. Each new parent has one selector and only nested rules;
each child retains one leading explicit `&`, including bare `&` for the parent.
Maximum nesting depth is three. Recursive expansion preserves the complete
ordered selector/declaration/media stream. All declarations and specificity stay;
acceptance guards, cascade donor and section order are unchanged. Public CSS
saves 37946 bytes. The native nesting boundary remains Safari 16.5+ and supporting
modern engines; physical Safari is not qualified.

`booking-summary.js` is reduced from 6859 to 4472 source bytes, with a 2246-byte
private `booking/layout.js` part inside the original IIFE. Fifteen setters share
one local function, saving 183 served JS bytes. Eight layout states pass against
baseline and current compiled owners. Reversing only these setter replacements
restores the original compiled owner exactly; all other compiled JS owners are
byte-identical. Event scheduling, rendering, price and lead contracts remain.
No new public path, request, runtime loader or global.

| Eight public assets | Previous bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 249411 / 3783 | 211465 / 4007 |
| Four JS | 140240 / 1825 | 140057 / 1826 |
| Total | 389651 / 5608 | 351522 / 5833 |

Reduction: 38129 bytes (9.79% overall, 15.21% CSS). Physical lines increase 225
because of explicit grouping braces. Uncompressed; excludes shared runtime/legacy
and duplicate src. This is not a speed benchmark or deferred-loading claim.

Both exact sources passed 23 applicable workflows and one expected migration skip.
Final core 34050151325, responsive 34050151371, flight 34050151339, build 34050151346.
Eleven source-build and 14 presentation tests, with PHP covered in CI; eight layout
states checked. Responsive 375/430/1024/1348/1440 and toolbar 999/1000 roundtrip pass.
Inspected 375/1440 editor images are pixel-identical to the prior publication.
18/24 final compared images match exactly; fixture calendar/date/control variation
remains. No blanket pixel-parity or physical-device acceptance claim.

Artifact 9994293361 / ZIP `sha256:8829e481d385895c4aeeba10c2d5934c5f2421cb1c5ce8b35ce0c40772b34b46`.
Deploy 34050404831 succeeded on attempt 1; evidence 9994364828 / ZIP `sha256:d72f71abae36f2164089e5179b546c9c498b4bb323204ce0fd25a5432d68332a`.
All 715 payload hashes and eight served assets match. Nine routes are HTTP 200 /
noindex; counter 0, disabled synthetic lead 403, internal PHP denied, rollback
retained. All 13 production fingerprints before/after/final are unchanged.
Main remains `fa58a0cba6dcfc8624d98c20d64fa06330eae309`; production is untouched.

Live desktop 1363px: Moscow → Turkey, 10–11 September 2026, seven nights, two adults
→ 100 hotels / 322 tours → ANAHTAR APART → flights 6 → 71 → 6 → review 72099 RUB
→ visible empty lead form → review → return with 100 cards → editor preserving
dates/nights. Review summary uses column 3 / row 4–12, lead summary column 2 / row 1,
and returning restores review layout. Review and empty lead screenshots inspected.
No horizontal overflow or real lead submission. Existing hidden legacy-filter
backdrop and immediate editor click during asynchronous return remain outside
qualification; this editor opened after return completed. No live no-flight
fallback or physical Safari test is claimed.

Audits: `docs/project/search3-css-descendant-results.json`,
`docs/project/search3-css-descendant-secondary.json`,
`docs/project/search3-descendant-publication.json`.

Next: Refresh release, PRs and publication before another bounded reduction. Inspect remaining repeated static markup and private source boundaries in active filter-rail/search-form JS; require measured net savings and compiled behavior evidence. Keep event/data/price/lead contracts and eight public paths. Do not deepen CSS nesting beyond three levels or repeat completed root/descendant grouping, overridden declarations, CSS injections, booking-layout and earlier source splits. Publish only a separate exact preview artifact. Production locked.

### Previous published checkpoint — private CSS and overridden declarations — 2026-09-06

Published source and release integration: `731eb3a8e1e490d2c07f4727135c373449d4958f`. Source PRs #1402/#1403;
one exact-artifact isolated preview publication #1404. Preparation notes below are
historical and their pending-publication statements are superseded here.

Two static style injections now use private CSS sources compiled into escaped
JS literals at the original insertion positions. IDs/order/root guard/idempotence
remain. Six single-parent explicit-& groups preserve expanded ordered CSS streams.
Injection source owners 11230 → 490 and 4218 → 434 bytes; public JS saves 1940 bytes.
All other compiled JS behavior owners are byte-identical. No new public path,
browser request, runtime loader/global or move into earlier linked stylesheets.

Removed 182 earlier CSS declarations shadowed by later identical full expanded
selector lists/media-supports contexts/properties/important priority. Each deletion
records its later witness; browser CI confirms CSS.supports for all 182 witnesses.
No shorthand expansion or selector-list merging. Removed 74 now-empty rules/groups;
retained declaration order and final per-selector/context/property/priority maps
match baseline. CSS saves 8057 bytes. Cascade donor/order and acceptance guards stay.

| Eight public assets | Previous bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 257468 /3855 | 249411 /3783 |
| Four JS | 142180 /1903 | 140240 /1825 |
| Total | 399648 /5758 | 389651 /5608 |

Combined reduction 9997 bytes (2.50%) and 150 lines. Uncompressed; excludes shared
runtime/legacy and duplicate src. Private CSS is counted in its JS host. No speed
benchmark or deferred-loading claim. Native nesting browser boundary is unchanged:
Safari  16.5+ and supported modern engines; physical Safari remains unqualified.

Both exact code heads passed 23 applicable workflows and one expected migration
skip. Final core 34047451648, responsive 34047451646, flight 34047451706,
build 34047451594. Eleven build tests and 14 presentation tests, PHP covered by CI.
Responsive 375/430/1024/1348/1440 passed; inspected editor 375/1440 images match prior
preview pixel-for-pixel. 19/24 compared images exact; fixture scroll/date/control
state variation remains. No blanket pixel-parity or physical-device acceptance.

Artifact 9993533050 / ZIP `sha256:209b7c4a42fcd353bb7db37a629efeba47628277ff3a36ea0e24658d6e6ea8db`.
Deploy 34047654589 succeeded; evidence 9993589644 / ZIP `sha256:3f94fb1ac4529fe1b01cfef498eec0103047ff57dd2fe259ffc009fec304404d`.
All 715 payload hashes and 8 served assets exact; 9 routes 200/noindex, counter 0,
disabled synthetic lead 403, internal PHP denied, rollback retained. All 13 production
fingerprints before/after/final unchanged. Main `fa58a0cba6dcfc8624d98c20d64fa06330eae309`; no production publication.

Live 1363 px: Moscow → Turkey 10–11 Sep 2026,7 nights,2 adults  → 100 hotels/423 tours  →
ANAHTAR APART  → flights 6 → 78 → 6  → review 72099 RUB  → visible empty lead form  → return
with 100 cards and hidden selected tour  → editor with dates/nights retained.
Both injected styles occur once and contain the compiled nesting. No horizontal
overflow; phone empty, no lead sent. Existing hidden legacy-filter backdrop and
immediate-editor-click during asynchronous return remain outside qualification;
the checked editor opened after return completed. No live no-flight fallback or
physical Safari test is claimed.

Audits: `search3-injected-css-sources.json`, `search3-active-css-declarations.json`,
and consolidated `docs/project/search3-continued-reduction-publication.json`.

Next: Refresh release/PRs and publication before another bounded reduction. Inspect remaining repeated inline-style setter groups in active results/selected presentation JS; require identical DOM operation order/values/priority and measured net savings before changing them. The private CSS string extraction and the 182 same-selector/context overridden declarations are complete; do not repeat these or prior nesting, compaction, formatter, filter/toolbar and source-split passes. Preserve eight paths, IIFEs and protected business contracts; publish only a separate exact preview artifact. Production locked.

### Previous published checkpoint — native CSS nesting — 2026-09-06

Published source and release integration: `74b87bff36db79f40fb17e18fb9478d339b6bc8c`. Source PRs #1399 and #1400;
isolated one-shot preview #1401. Both passes are complete; preparation notes below
are historical. This checkpoint supersedes their pending-CI/publication statuses.

Consecutive rules sharing one identical `body.search3-candidate` or
`html body.search3-candidate` parent now use explicit `&`: 131 groups across46
source modules. No declarations or at-rules occur in grouping parents. Expanding
the nesting reproduces ordered selectors, declaration values/important flags and
media contexts exactly; original declaration bytes and public asset order retained.
Cascade donor identity/order and protected acceptance guards retained.

| Eight public assets | Previous bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 290846 /3593 | 257468 /3855 |
| Four JS | 142180 /1903 | 142180 /1903 |
| Total | 433026 /5496 | 399648 /5758 |

Reduction:33378 CSS bytes (11.48%;7.71% of the eight-asset total). Main results CSS
removes29802 bytes; remaining three stylesheets remove3576. JS is byte-identical.
Physical lines increase262 for explicit grouping braces. Uncompressed, excluding
shared runtime/legacy and duplicate src; no speed benchmark or deferred-load claim.

Browser boundary: this preview requires native CSS nesting. Explicit `&` works in
[Safari16.5+ per WebKit](https://webkit.org/blog/14154/webkit-features-in-safari-16-5/).
Older engines without nesting are unsupported; physical Safari is not qualified.
Single-parent specificity follows the [CSS nesting specification](https://www.w3.org/TR/css-nesting-1/#nest-selector).

Each exact code head passed23 applicable workflows and one expected migration skip.
Final core34045800403, responsive34045800482, flight34045800430, build34045800429.
Nine source-build tests and14 presentation tests, PHP covered in CI. Responsive
375/430/1024/1348/1440;375/1440 editor images inspected and pixel-identical to prior
publication.19 of24 compared images are pixel-identical; calendar scroll/header
and date-selection variation remain in fixture captures. No blanket pixel-parity
or physical-device acceptance claim. Source audits: search3-css-nesting-results.json
and search3-css-nesting-secondary.json; consolidated evidence:
`docs/project/search3-css-nesting-publication.json`.

Artifact9993052180, ZIP `sha256:0dc48f09371af3424e8de13d160eb2bd41832a1aa83cfe49ca25bec4c5a8c830`.
Deploy34046027330 succeeded; evidence9993119103, ZIP `sha256:a4b1a28daea5cd93fa55cb300eeda67ebbed9ce598d0eb9c09a5457de85f19e8`.
715 payload hashes and8 live assets match.9 routes200/noindex; counter0, disabled
synthetic lead403, internal PHP denied and rollback retained. All13 production
fingerprints unchanged; main remainsfa58a0cb. No production publication.

Live1363px: Moscow→Turkey,10–11Sep2026,7nights,2adults →100hotels/449tours →
ANAHTAR APART →flight disclosure6→109→6 →review72099 RUB →visible empty lead
form →return/offers/editor with dates and nights retained. No horizontal overflow.
Phone remains empty; no lead submitted. Return completed despite one automation
snapshot protocol timeout; fresh DOM verified completion before editor click.
Earlier immediate-click return race and inherited legacy advanced-filter backdrop
on desktop→mobile remain outside this qualification. No-flight fallback and
physical Safari were not tested live in this pass.

Next: Refresh release, active PRs and this publication before the next bounded reduction. Inspect remaining active CSS declaration duplication and JS presentation owners for measured net savings, preserving media/order/specificity, IIFE ownership and protected business contracts. Do not repeat completed nesting, indentation/comment compaction, private JS extraction, geometry helpers, retired selectors or shared formatters. Keep eight public paths; preview only via a separate exact-artifact publication. Production locked.

### Previous published checkpoint — CSS output indentation — 2026-09-06

Exact runtime/release code: `4517879c22929706aabc3365b7e3906ecf19bc24`. Source #1397; preview control #1398.
This continuation also completed JS source split #1395 and publication #1396 below.

The dependency-free builder removes4667 CSS bytes of horizontal indentation after
ordinary newlines. Newline separators, strings/comments and whitespace after
escaped newline/hex-escape terminators remain. Readable source formatting stays.
Full recursive CSS token sequences match after normalizing whitespace-token
contents only; no token removed or reordered. Four JS assets stay byte-identical.
Audit: search3-css-output-indentation.json. Nine source-build tests pass, including
escape terminators;14 presentation tests with PHP covered in CI.

| Public assets | Previous bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 295513 /3593 | 290846 /3593 |
| Four JS | 142180 /1903 | 142180 /1903 |
| Total | 437693 /5496 | 433026 /5496 |

Together with #1395:5126 fewer bytes from438152 (-1.17%); five private JS source
parts, with two additional physical lines. Uncompressed; excludes shared runtime,
legacy and duplicate source files. No speed benchmark or deferred-loading claim.

All23 applicable PR workflows succeeded, one expected migration skip. Core
34042766478, responsive34042766475, flight34042766521, build34042766609.
375/1440 editor screenshots inspected. Artifact9992182320, digest
`sha256:d2e0fe380bb1e1f7ab14942f928e2faac7bc141cd3a060a29293475997609723`.
Deployment34042946055 succeeded; evidence9992236531, digest
`sha256:f16906a2d9a2ec922a2dc0c95078cd451cdb0a4b540fd8ea2108a97fa54e09c8`.715 payload hashes and8 served assets match;
9 routes200/noindex, lead403, counter0, internal PHP denied, rollback retained.
13 production fingerprints unchanged. Main remainsfa58a0cb; no production release.

Latest live1363px entry/native-controls check passed. The same JS was verified
earlier in this continuation with100 hotels/428 tours and ANAHTAR APART flight
disclosure6→78→6; that scoped evidence remains below and in publication history.
No repeated live lead journey, physical Safari, no-flight fallback or inherited
backdrop acceptance claimed. Immediate editor click during asynchronous return
was superseded in the earlier check; not fixed or requalified by indentation.

Next: refresh release; inspect remaining active CSS/JS owners for measured net
reduction. Do not repeat this indentation step, private extractions, geometry
helpers or earlier CSS/shared formatter work. Preserve eight paths and protected
contracts; preview publication remains separate, production remains locked.

### Previous published checkpoint — private JS parts — 2026-09-06

Exact runtime/release code: `c5fcbea26ddd4e54628c8b60966ca186deb09ecc`.
Source PR #1395; separate one-shot preview control #1396.

Results labels/cards/toolbar and selected-flight fallback/disclosure now have five
private source parts, expanded within their original IIFEs by the dependency-free
builder. Shared state, declaration order and public adapters are preserved; both
extracted compiled IIFEs remain byte-identical. No new global, runtime loader or
browser request. Main source owners shrink17427→4027 and13205→7490 bytes; the
largest private part is7619 bytes. Audit: search3-private-js-parts.json.

Toolbar inline-style helpers remove459 served JS bytes.48 baseline/current
geometry traces match operation order, values and important priority. Seven of
eight public assets are byte-identical. Local build tests cover private-part
drift, cycles, duplicates and outside-root paths; regression adapters exercise
compiled results and selected-flow code.

| Public assets | Previous bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 295513 /3593 | 295513 /3593 |
| Four JS | 142639 /1901 | 142180 /1903 |
| Total | 438152 /5494 | 437693 /5496 |

This pass principally separates source ownership; payload savings are459 bytes
(0.105%), with two additional physical lines. Counts are uncompressed, excluding
shared runtime/legacy; no deferred loading or speed benchmark is claimed.

All23 applicable PR workflows succeeded, one expected migration skip. Core
34040858804, responsive34040858748, flight34040858761, build34040858770.375px
readability and1348px toolbar screenshots inspected. Source artifact9991626458,
digest `sha256:a49995acfc219f50eb3121a8cf5ac17db22e7c1c986148fd79ddd89ce0a6c5dc`.
Deployment34041100182 succeeded; evidence9991692069,
digest `sha256:5085190aed04e8f28a24e266813af8fc24ede52dd4173eb9c8e2ed01f3ecf89b`.
All715 payload hashes and8 served assets match;9 routes200/noindex, counter0,
disabled lead403, internal PHP denied, rollback retained.13 production
fingerprints unchanged. Main remainsfa58a0cb; no production release.

Live desktop1363px: Moscow–Turkey,10–11 September2026,7 nights,2 adults;
100 hotels /428 tours. ANAHTAR APART opens2 tours and78 flight choices;
disclosure6→78→6 verified with stable collapsed screenshot. Base total72099 RUB
retained; after return settles, editor preserves dates and7–7 nights. An immediate
editor click during asynchronous return was superseded; not fixed in this pass.
Document width1348<=1363. No lead sent.
Inherited legacy backdrop, physical Safari and live no-flight fallback were not
requalified; earlier full lead journey remains in publication history.

Next: refresh release; inspect remaining active source owners for measured net
CSS/JS reduction. Do not repeat these private extractions/geometry helpers or the
completed CSS and shared formatter work below. Preserve eight public paths,
IIFE ownership and protected contracts; preview publication remains separate.

### Previous published checkpoint — CSS build compaction and entry split — 2026-09-06

Exact runtime/release code: `9bedc2be31c5765c2fde2f383647487616127cc5`.
Source PR #1393; separate one-shot preview control #1394.

- The dependency-free builder compacts private CSS comments into empty token
  separators. Source notes and donor markers stay in source; licenses, strings,
  escapes and whitespace are retained. This is payload reduction, not a claim
  that source complexity or browser execution time decreased by that amount.
- Twenty-eight adjacent rules with identical declaration blocks share selector
  lists. Expanded selector/declaration/media token streams match the baseline.
- Entry CSS is split into calendar, responsive entry, toolbar and native controls;
  the four ordered chunks reproduce the original source bytes. Four public JS
  files remain byte-identical. Source audit: search3-css-build-compaction.json.

| Public assets | Previous bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 311609 / 3653 | 295513 / 3593 |
| Four JS | 142639 / 1901 | 142639 / 1901 |
| Total | 454248 / 5554 | 438152 / 5494 |

Reduction: 16,096 bytes /60 lines (3.54%). Uncompressed; excludes shared runtime,
legacy and source duplication. No page-load speed benchmark is claimed.

All 23 applicable workflows succeeded, one expected migration-only skip.
Core 34030842353, responsive 34030842375, flight 34030842370, build 34030842392.
Responsive 375px editor and 1440px calendar screenshots inspected.
Artifact 9988542775 digest
`sha256:b20f486345c0fa934afdd6e6aecfea5370622613e67ec21d49cb735e9929df02`.
Deployment 34030973693 succeeded; evidence 9988586892 digest
`sha256:9fcd903bc2de8f357c4f6f4a73ef336c696a3f429cbe9a8df96b5cec47d894a2`.
All 715 payload hashes and eight served assets match. Nine routes 200/noindex,
counter 0, disabled lead 403, internal PHP denied, rollback retained; 13 protected
production fingerprints unchanged. Main remains fa58a0cb; no production release.

Targeted live search/editor verification is recorded in AUTOPILOT_STATE.json.
The previous full live journey to the unsubmitted lead form remains historical
evidence below. No new lead delivery, physical Safari or production acceptance
is claimed. The inherited legacy advanced-filter backdrop remains documented.

Next: refresh release and inspect remaining active results/selected presentation
owners for a net source-level reduction. Preserve distinct formatter contracts
and IIFE lifecycle; do not repeat entry split, comment compaction, adjacent merges,
retired-state cleanup or shared text/plural work. Keep eight public asset paths
and protected contracts; preview publication remains a separate exact operation.

### Previous published checkpoint — retired CSS and shared JS — 2026-09-06

Exact preview runtime: `3f32ebb377795d2d146a0e0a2f3c8c3e18fbfa18`.
Release integration: `84ae82a58f18460994fe8d5d584d7e82bbc73d3b`.
Source PR #1389; separate one-shot preview control PR #1392. Older pending
statements below are historical checkpoints, not the current publication state.

- Removed 136 rules /147 selectors requiring 21 retired positive classes and
  two empty media containers: 21,788 CSS bytes. Negative conditions remain.
- Consolidated 35 equal-specificity compound selector groups: 2,566 CSS bytes.
  Expanding retained selectors reproduces the ordered declaration/media stream
  against concurrent release f8e10a30. Audit: search3-css-owner-retirement.json.
- Preserved concurrent #1385/#1386/#1388/#1390 shared text, inflection, helper
  cleanup, fact typography and regression tests, plus #1391 checkpoint history.
  The overlapping local formatter experiment was withdrawn; no second namespace
  is shipped. Do not repeat these completed steps.

| Eight public assets | Previous preview bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 336274 / 3871 | 311609 / 3653 |
| Four JS | 144163 / 1926 | 142639 / 1901 |
| Total | 480437 / 5797 | 454248 / 5554 |

Net reduction since previous preview: 26,189 bytes (5.45%) /243 lines, including
concurrent changes. Own CSS reduction against f8e10a30: 24,354 bytes /215 lines.
Uncompressed assets excluding shared runtime/legacy; not a speed benchmark.

All 23 applicable workflows passed, one expected skip. Core 34028476949,
responsive 34028476946, flight 34028476933, artifact 34028476925.
Responsive 375/1440 editor screenshots inspected; physical Safari not tested.
Source artifact 9987823291 digest
`sha256:0cd1cc2a6b6f12a5bb3485aa6444bd282077fa96893715c9f7c508653c2e03fe`.
Deployment 34028783194 succeeded; evidence 9987916338 digest
`sha256:6887eb1d1abdb40dd29747f63241e358c2bd9d491daf5f0f3a0e63e28a2f7248`.
All 715 payload hashes and eight served assets match. Nine routes 200/noindex,
lead-disabled probe 403, counter 0, internal PHP denied, rollback retained;
13 production fingerprints identical before/after/final. Main remains fa58a0cb.

Live desktop 1363px: Moscow–Turkey, 10 September 2026, 7 nights, 2 adults;
100 hotels /370 tours. ANAHTAR APART → flight choices → review → visible
unsubmitted lead form, one 72,099 RUB sidebar total; phone empty and document
width 1363px. The inherited legacy advanced-filter backdrop on desktop-to-mobile
resize remains documented; this desktop journey does not requalify that case.

Next: inspect remaining large active cascade owners and repeated result templates
for equivalent consolidation from refreshed release. Preserve eight public paths,
protected contracts, source ownership and existing publication history. Keep main
and production locked; publish preview through the exact-artifact process.

### Previous code checkpoint — CI-verified, not republished — 2026-09-06

Exact release code: `f8e10a30b57fa965ee8f5e7294a1e08819b75fc2`.
Preparation PRs: #1388 and #1390. The exact preview below remains on the older
published code `26988e62`; this checkpoint was not deployed.

- One earlier-loaded immutable presentation-text owner now serves HTML escaping,
  supplier scalar/array normalization, compact party labels and hotel place labels
  used by booking summary, final sections and the selected-tour rail.
- Snapshot and load-order regression covers escaping, supported supplier shapes,
  compact party/place output, owner immutability and all three consumers. The
  intentionally different human-readable final-party formatter remains local.
- Three identical hotel/tour fact typography rules are consolidated with `:is()`.
  Both arguments preserve the original one-class specificity and the trailing
  element selector is unchanged.
- Price-number/money helpers were deliberately excluded: price arithmetic and
  presentation ownership remain untouched. Generated assets and import hashes were
  rebuilt together; all eight public paths and their order remain unchanged.

On the final code, **22 workflow runs completed successfully** and one migration-only
workflow was expectedly skipped. The sole job `101473212868` in the remaining SEO
primitives run `34028335788` and all its steps completed successfully, while GitHub
still reported its run wrapper as `in_progress` at checkpoint time; do not count it
as a 23rd completed workflow until rechecked. Core `34028335801`, responsive
`34028335842`, flight `34028335806`, whole-site artifact `34028335783`. Visual
artifact `9987783755`, digest
`sha256:2b2266489194c444d40f94660b19a4f311460ac4aa7bd7242b8e09b9492fcc4b`;
whole-site artifact `9987780814`, digest
`sha256:c011dac1edc2eed227a1427c53f2925c1ec83634dafecc4b73fcfcbb6b0da158`.
The 375px and 1440px search/readability images were inspected without new clipping,
overflow or typography drift. This is not a physical Safari or live-preview
acceptance claim.

| Public assets | Bytes / lines |
| --- | ---: |
| Four CSS | 335,963 / 3,868 |
| Four JS | 142,639 / 1,901 |
| Total | 478,602 / 5,769 |

This continuation removes another **1,045 bytes / 8 lines** from `4a4d762c`;
cumulative reduction from `684825be` is **45,123 bytes / 349 lines**. Counts are
uncompressed and exclude shared runtime/legacy plus duplicate source files; they
are not a page-load benchmark.

Next: first recheck SEO primitives run `34028335788`. Then audit only a new proven
equivalent presentation/cascade duplicate. The remaining `number`/`money` helpers
belong to protected price paths and are not a safe generic-dedup target; the flight,
selected-flow and final-party text helpers have different contracts and must not be
folded into the shared owner. Do not add abstraction without a net reduction or
publish preview as a refactor side effect.

### Previous published checkpoint — 2026-09-06

Exact runtime code: `26988e62eb674f8165d380de71e3be3d8feb19c3`.
Release integration: `0fabb1eca248b0f6b48ffe750cc73eaf93f52956`.
Source PR: #1382; separate exact-artifact preview publication: #1384.
The preparation/pending notes in older sections below are historical snapshots.

Two completed refactor passes:

- Seven large CSS owners split into 21 component/breakpoint files. Two static
  style-injection modules separated from mobile-bar/summary-CTA behavior. Local
  selector constants preserve exact emitted CSS while removing 10,219 JS bytes.
- Seventy-four plain-class selector lists share their ancestor prefix with
  equal-specificity `:is()` groups, removing 12,098 CSS bytes. Expanding the groups
  reproduces the same rule/media/declaration stream; cascade order is preserved.
- Retired the inactive Search3 footer replacement, its two private stylesheets,
  five orphan footer rules and obsolete presentation-only messenger selector.
  The server already emits the canonical shared footer with the marker that made
  the old replacement return immediately. This step removes 20,971 bytes / 215
  lines. Canonical PHP/footer styles, logo, destinations and lead transport remain.

The concurrent price-facet fix and publication history through `684825be` and
`154700ea` were preserved. The source builder remains dependency-free and all
eight public paths remain fixed. Source modules now total 69.

| Public assets | Before (`684825be`) bytes / lines | Published bytes / lines |
| --- | ---: | ---: |
| Four CSS | 363,166 / 4,175 | 336,274 / 3,871 |
| Four JS | 160,559 / 1,943 | 144,163 / 1,926 |
| Total | 523,725 / 6,118 | 480,437 / 5,797 |

Reduction: **43,288 bytes (8.3%) / 321 physical lines**. Uncompressed asset
accounting, excluding shared runtime/legacy and duplicate `src` files; not a
measured page-load improvement. Both injected styles are byte-identical to the
baseline, with guards, insertion order and idempotency preserved. Footer ownership
regression failed before removal and passed afterward.

All **23 applicable workflows passed**, with one expected migration-only skip:
core `34024249265`, responsive `34024249262`, flight `34024249322`, artifact build
`34024249256`. Final responsive 375px results and 1348px editor images inspected;
PHP rendering passed in existing CI. Visual artifact `9986537178`, digest
`sha256:6074e34be1da4588a7a487d5b7ada719099c2220ebd2d988f4e205317a378e46`.

Preview publication run **`34024574911` succeeded**, using source artifact
`9986530879` (digest `sha256:5a9343dd778c06fbc0e6547e3121816e203563df7d01755061926699a830e812`).
Evidence `9986634293` (digest `sha256:085c2521f407c358ad6bad49c7774519ec12b944d0f3af4d18ac9cd8b44d88d3`)
confirms all 715 payload files, nine HTTP-200/noindex routes, counter zero, disabled
synthetic lead probe 403, internal-PHP denial and retained rollback. The 13
production fingerprints match before/after/final; `main` remains `fa58a0cb`.

All eight live Search3 assets matched source/artifact bytes. A real desktop
1363px journey found 100 hotels / 186 tours (Moscow–Turkey, 10 September, seven
nights, two adults), opened ANAHTAR APART, inspected flight choices, and reached
review and the visible lead form with one 72,099 RUB sidebar total. Screenshot
inspected; no horizontal overflow; phone empty and no lead submitted.

Boundary: published and live-desktop-verified **preview only**. Physical Safari
and production acceptance are not claimed. The previously recorded hidden legacy
advanced-filter backdrop on desktop-to-mobile resize remains a known limitation;
its earlier controlled reproduction is preserved in publication history. This
pass did not change that owner or claim a new full live responsive acceptance.

Next: refresh the release head; inspect repeated templates in
`behavior/results-presentation.js` and remaining `styles/cascade` owners for
equivalent consolidation, then batch verified removals. Do not repeat these
splits, restore the retired footer or redo completed filter/toolbar work. Preserve
protected contracts and the eight public paths. Main/production remain locked;
preview publication remains a separate exact-artifact operation.

## Execution and activation are separate

This is the persistent task prompt for autonomous Search3 presentation refactoring. It does not create a scheduler, start a background coding process or authorize production publication.

The existing project records name a ChatGPT task «Продолжать разработку AnyTour», with hourly continuation in Europe/Amsterdam. Its current enabled state, task ID and next run have not been verified in this session: scheduler-management tools were unavailable. Reuse/update that existing task rather than create a duplicate. Keep its model/settings unless the owner changes them. Desired cadence remains hourly; do not describe it as continuous execution or claim activation without a scheduler confirmation.

GitHub issue #2 and autopilot-runtime-state.yml persist CI signals only. A successful CI run or this document is not evidence that a new coding session will start.

## Copyable task prompt

Продолжай автономный технический refactor-pass Search3 в репозитории pyatkoff/poisk-turov-test. Выполняй разработку, а не только мониторинг и отчёт. Работай по активной ветке release/search3-production-ready-v1 либо явно согласованной в #996 ветке-преемнику; текущий release draft — #1334.

В начале каждого запуска прочитай #996 с последними комментариями и #1334; затем AGENTS.md, OWNER_PRIORITY.json, AUTOPILOT.md, AUTOPILOT_STATE.json, src/search3/AGENTS.md, src/search3/README.md и этот документ ИЗ АКТИВНОЙ ВЕТКИ. Общий roadmap #1 — контекст, не повод перезапускать завершённые этапы. Search2/#810 и старые записи в main не являются текущей очередью. Устаревшие упоминания общего отложенного refactor не отменяют последующее разрешение владельца на ограниченный Search3 presentation refactor; оно не распространяется на защищённые контракты.

Последняя проверенная точка кода на момент записи: 57a675f0a43a1a6ddf2cb7e24dafebc94a11a9f8. Три toolbar-прохода #1373/#1374/#1375 уже завершены и интегрированы только в release; подробности последней точки ниже. Перед изменениями заново проверь фактические head ветки/main, PR, CI и параллельную работу. Не откатывай ветку к этому SHA, если она уже продвинулась. Не перетирай чужие изменения, не force-push и не дублируй выполняющийся шаг/выкладку. При параллельном выполнении проверь его результат или выбери независимый безопасный пункт.

Уже завершены: source split девяти cascade-модулей на 03e7422e; удаление 127 cascade-деклараций и 60 пустых блоков на 91a7ff5d; удаление ещё 75 compatibility-деклараций и 17 опустевших правил на 2d4cd972; единый владелец мобильного фильтра и удаление orphan lifecycle/cascade на 53f482eb/35e2ae36; frame-coalescing price slider, одна final-count нотификация, отмена ожидающего price render при reset/sea/charter/fresh-source, повторное применение активных фильтров к свежим progressive results, подавление sort/same-reference filter events/DOM writes и сохранение смонтированных контролов при progressive refresh на 51ebb674/4679200a/35345fb1/35207333/b1b5791c/b72f7494/43856063/56099e49/f0198905. Устаревший silent-mode полной отрисовки удалён на a8ae0202; выбранный ценовой предел переживает временное сужение source bounds на 21bf320c; парные budget/flight поля очищаются на d66183a4; charter result/form state синхронизирован на 41c27412; пустой источник корректно объявляет смену фильтров на 6978435a; повторная пустая отрисовка не уничтожает исходный набор на 243d0515; Search3 price input отделён от legacy DS2 listener на 134611b5; неполный seaDistance facet скрывается и сбрасывается на 0dc0efe6; reset восстанавливает source и объявляет финальный count ровно один раз на 911ef327; лишний общий price collector для отеля без tour rows удалён на 623b5c17. Промежуточный c4d796e2 только восстановил полностью переданный generated bundle после транспортного усечения и не является отдельным изменением поведения. Пустой @media iteration1b уже удалён. Сохранены предыдущие JS event-coalescing, latest-price-wins, idempotent DOM/aria/dataset updates и кеш календаря. Не повторяй эти проходы. Прежний неудачный split filter-rail.js полностью отменён; не считать его выполненным.

Выбирай следующий шаг по реальному уменьшению сложности и риска, а не по числу коммитов. Следующий кандидат для анализа — оставшиеся перекрытия CSS мобильного toolbar в results-context.css/results-layout.css. До удаления активных правил доказать эквивалентность computed styles в initial/results/editor/selected/reset на 375/430/768/999/1000/1348/1440. Завершённые selector cleanup, local queue и desktop boundary не повторять. Не повторять уже завершённые ownership, price-frame и fresh-announcement исправления, не разрезать IIFE вслепую и не добавлять глобальный scheduler или дублирующие observers. Альтернативный независимый шаг — доказуемые оставшиеся CSS-дубли в существующих owners. Разные media/specificity, shorthand, переменные и fallback не объявлять дублями без отдельного доказательства. Не добавлять код или тесты только ради активности.

Редактируй src/search3, затем используй python3 scripts/build/search3_assets.py --write и --check. Source, generated assets, нужные section contracts и production-import hashes коммить вместе. Сохраняй восемь публичных asset paths, порядок подключения и защищённые контракты. Применяй текущий lean preview cycle выше: рабочие PR сразу в release, короткие проверки сборки/размера/затронутого поведения, одна проверка изменённого сценария после пакетной публикации. Полные CI/visual/SEO проверки отложены для Search3 draft по явному указанию владельца; перед production-review они возвращаются. Не создавай дублирующую CI-инфраструктуру и не запускай полный visual suite ради документации.

Не останавливаться после одного PR или коммита, если в текущем запуске есть возможность следующего безопасного шага. Сначала доводи блокирующие ошибки своего изменения до исправления либо безопасного отката, затем продолжай. При внешнем блокере запиши его и продолжи независимую безопасную работу. Требуемая физическая проверка Safari, юридические материалы и production approval не блокируют независимый разрешённый presentation refactor, но не могут быть объявлены выполненными автоматически.

Запрещено менять Tourvisor/API, расчёт цены, lead transport/маппинг, Метрику/цели, логотип и соседние проекты. Не отправляй реальные заявки. Разрешены ветки, коммиты, draft PR и CI. Не merge в main и не deploy production. Не запускай автоматический deploy как побочный эффект refactor-pass; отдельно разрешённый preview-процесс остаётся отдельной exact-SHA операцией с его существующими ограничениями. Не изменяй серверы через SentinelX в рамках этого задания.

После каждого существенного проверенного шага обновляй presentation_refactor_checkpoint в AUTOPILOT_STATE.json и handoff в #1334/#996, не заменяя историю публикаций результатами локальной сборки. Зафиксируй следующий конкретный шаг и границы. В итоговом отчёте укажи точный SHA, фактически выполненные изменения, результаты применимых проверок и общий размер/число строк четырёх CSS и четырёх JS из manifest без двойного учёта src/v2. Отличай «подготовлено», «CI пройден», «опубликовано в preview» и «проверено в production». Не обещай продолжение между запусками без реально включённого планировщика.

## Checkpoint metrics, not a live measurement

At code SHA 2d4cd972: Search3 CSS is 375,207 bytes / 4,268 physical lines; JS is 162,360 bytes / 1,928 lines. Total: 537,567 bytes / 6,196 lines across eight public assets, uncompressed, excluding shared runtime/legacy. Recompute after material code changes. These source metrics do not establish deployed size or measured page-load performance.

The code checkpoint passed 23 PR workflows, with one expected skipped workflow, as recorded in PR #1334. This handoff-only document makes no new runtime/visual/production verification claim.

## Verified filter-rail checkpoint — 2026-09-06

Exact code head: `623b5c17b9fef927c8e95fb6a42333a905e9b584`.

- `53f482eb` removed the unused mobile drawer lifecycle so the active mobile controls have one owner.
- `51ebb674` keeps slider labels immediate but coalesces rapid price filtering to one render per animation frame with the latest values.
- `4679200a` removed the second filter-change announcement after a fresh result source; `renderRail()` remains the single announcement owner.
- `35345fb1` invalidates a queued price render on both local reset and `v2:search-reset`.
- `35e2ae36` removed the corresponding orphan drawer cascade after the ownership regression proved those selectors had no owner.
- `35207333` cancels a superseded queued price render when a sea-distance or charter change immediately applies the same latest price state.
- `b1b5791c` reapplies active local filters when a fresh progressive result source arrives, so new unfiltered cards cannot leak into the visible set.
- `b72f7494` cancels a pending price frame when that fresh source already consumed the latest price state.
- `43856063` keeps sort/same-reference rerenders outside the public filter-change event contract.
- `56099e49` skips the redundant count/word DOM writes for that same-reference path.
- `f0198905` keeps the existing filter controls mounted during progressive source updates while synchronizing their price bounds and preserving the selected price.
- `a8ae0202` removes the now-obsolete silent full-render mode, leaving one explicit initialization/reset contract.
- `21bf320c` keeps the user's price limit separate from temporary source bounds, so a narrower intermediate result only clamps the displayed slider and the chosen limit returns with wider results.
- `d66183a4` makes “reset all” clear both form budget bounds and both flight constraints before the existing single desktop submit.
- `41c27412` restores the result-rail charter state from the form on search reset and mirrors local charter changes back to that form without adding another search request.
- `6978435a` keeps the active-filter count and public event synchronized when price, sea or charter changes while the current source is empty.
- `243d0515` treats an empty filtered output as a valid previous render, preserving the original hotels when that output is rendered again and the filter is later cleared.
- `134611b5` removes the legacy `data-ds2-price` opt-in from the Search3 slider, so the retained base listener cannot perform a second immediate filter pass before Search3's scheduled pass.
- `0dc0efe6` exposes the sea-distance facet only for a complete positive `seaDistance` payload and resets it before filtering when a progressive source becomes incomplete.
- `911ef327` restores the unfiltered result source once on reset and publishes one final count event instead of two identical announcements.
- `623b5c17` removes the general price collector from the no-tour branch and reads the normalized hotel price directly; focused regression preserves exclusion and restoration behavior.

`c4d796e2` is a transport-recovery commit only: it restores the complete generated bundle after the preceding API upload was truncated, and introduces no separate behavior change. The final source/generated tree is exact and verified.

Focused price-input and filter-ownership regressions pass, the generated assets match their sources, and all 23 applicable PR workflows passed with one expected migration-only skip. Core run: `34019983915`; responsive visual run: `34019983937`, artifact `9985164901`, digest `sha256:aac4f850d4db60708ea895031d1ce4552ef2f8e720d784e697312621216d3d37`; whole-site artifact run: `34019983948`, artifact `9985158068`, digest `sha256:4b08abf84d934b222994c460ad498260c5d66cebb40bb1c2b6f832ad4f9e7896`.

At this code head, the four public CSS assets are 366,267 bytes / 4,196 physical lines; the four public JS assets are 158,758 bytes / 1,921 lines. Total: 525,025 bytes / 6,117 lines, uncompressed, excluding shared runtime and legacy and without double-counting `src` and generated `v2` files. Compared with the `2d4cd972` checkpoint, the active eight-asset set is 12,542 bytes and 79 lines smaller; this aggregate includes both the JS work and the independently completed orphan CSS/lifecycle cleanup, so it is not attributed to one commit or presented as a measured page-load gain.

Status boundary: prepared and CI-verified only. This refactor pass did not publish a new preview, merge `main`, deploy production, alter Tourvisor/API, price arithmetic, lead transport/mapping, Metrika/goals, logo or neighboring projects. The next run must refresh the release head and prove a new independent problem before changing code.

## S3_RETIRED_TOOLBAR_CSS — verified preparation, 2026-09-06

Code: `5cd00f0d3795822803b19c0464d1d3134b996440`. Removed only retired mobile action/filter/sort/chip selectors in four existing CSS sources. Actual toolbar shell, mrf bar/sheet and sort owner retained. All seven other public assets and protected runtime unchanged.

Public assets: 521702 bytes / 6091 physical lines. Standard PR CI still pending at this checkpoint; no merge to main or deploy. Earlier completed steps above must not be repeated.

Next: Run standard PR CI and integrate into release only; continue the separately reproduced duplicate toolbar-task scheduler step. Never merge main or deploy as a side effect.

## S3_TOOLBAR_LOCAL_QUEUE — verified preparation, 2026-09-06

Code: `f3804f9e64b49751994fa99f0b6a11e1a13ae4c6`. One local zero-delay task for progressive-results and compact-breakpoint toolbar mounts, cancelled on reset or empty results. Timer ID zero, late mrf initialization, stable DOM and two-way native/proxy sort handoff covered. No new observer/global scheduler or filtering/search/price/lead contract change.

Existing responsive test passes 375/430/1024/1348/1440. Standard PR CI pending. Public assets: 522235 bytes / 6107 lines; no main merge or deployment.

Next: Review and integrate CSS then scheduler PR into release only after applicable CI. Inspect fresh responsive evidence, update #996/#1334, and audit the remaining toolbar visibility across desktop resize before changing another owner. No main merge/deploy.

Scheduler source formatting normalized as `7b869e12d80d7c6c047559545a3118a64e39249e`; behavior unchanged and exact local source hash verified. CSS #1373 integrated as27649614 with23applicable CI success +1expected skipped. Scheduler PR #1374 remains release-only, no deployment.

## Current verified toolbar checkpoint — 2026-09-06

Exact code: `57a675f0a43a1a6ddf2cb7e24dafebc94a11a9f8`; release integration: `0448750ba6c7ad77538794a0c45f97a5e11ea18d`. Historical pending/preparation notes above describe their earlier snapshots, not the current queue.

- #1373 → `27649614`: removed retired mobile action/filter/sort/chip CSS; canonical mrf owner and actual toolbar retained. CSS cleanup removed 3323 bytes / 26 lines.
- #1374 → `204b7104`: one deferred toolbar mount for result/breakpoint bursts, with reset/empty cancellation. Regression: 40 pending tasks before, 1 after; stable DOM and two-way sort retained.
- #1375 → `0448750b`: mobile-to-desktop resize no longer exposes an extra mobile sort row. Existing browser regression covers both sides of999/1000, preserved form values, roundtrip to430, Escape and focus return.

Code build/source (5 tests), presentation (12 tests) and existing responsive checks passed. Final code integration passed 23 applicable PR workflows plus one expected migration-only skip: core34022534281, visual34022534319, whole-site artifact34022534215. Focused responsive evidence: run34022311073, artifact9985910235, sha256:380826e39991d04d3427274eec2c49dd7a742d7119d42eeed7f2d1404f72dbe1. Desktop1348/mobile430 screenshots inspected; physical Safari and live-site acceptance are not claimed. The earlier test initialization race was diagnosed and corrected in the fixture; no assertion was removed.

Eight public assets: CSS363166 bytes/4175 lines; JS159313/1937; total522479/6112, uncompressed. Net for this continuation from49f2d2e: -2546 bytes/-5 lines. This is source accounting, not a page-load benchmark. Prior preview/production publication records are preserved; no main merge, deployment or real leads in this continuation.

Next: audit only proven remaining toolbar cascade overlap using the existing browser suite and source owners. Do not create another drawer, observer, global scheduler or test workflow, repeat these three completed steps, or alter protected contracts.

## S3_CHARTER_FACET_COMPLETENESS — verified release checkpoint, 2026-09-06

Exact release code: `5832457c6d43270290967a49f815a12463b19c18`; preparation PR: #1377.

- The result-rail charter facet is visible only when every loaded normalized tour row contains an explicit boolean `isCharter` value.
- An incomplete progressive source hides and clears only the local facet. It does not silently rewrite the primary `onlyCharter` search constraint.
- When complete data arrives again, the local control and active-count state are restored from the primary form.
- The focused regression failed against the unchanged runtime, then passed after the guard. It covers empty, complete, incomplete-progressive and restored-form paths.

The source build/check, focused filter-rail checks and production-presentation suite passed locally. On the integrated release SHA, all 23 applicable PR workflows passed and one migration-only workflow was expectedly skipped. Core run: `34022965922`; responsive visual run: `34022965889`, artifact `9986123628`, digest `sha256:143d2e896580549a814c39534e37644fefdea77b43737eb4ac0d6fae77f1cb30`; whole-site artifact run: `34022965916`, artifact `9986118224`, digest `sha256:26493e2cafc0274a3556480c3e0dda0f32f42bb21197886e49576451368e4afb`. Search/readability images at 375 and 1440 px were inspected; no new clipping or owner regression was found. This is not a physical Safari or live-site acceptance claim.

Eight public assets: CSS 363,166 bytes / 4,175 lines; JS 160,007 bytes / 1,940 lines; total 523,173 bytes / 6,115 lines, uncompressed and without double-counting `src` and generated `v2` files. The +694 bytes / +3 lines from the previous release code is the explicit completeness guard and regression-backed state synchronization, not a measured page-load result.

Status boundary: integrated and CI-verified in the release draft only. Preview, `main` and production were not updated; Tourvisor/API, price arithmetic, lead transport/mapping, Metrika/goals, logo and neighboring projects were not changed.

Next: refresh the release head and prove a new independent presentation ownership or incomplete-facet defect before editing. Do not repeat charter/sea completeness, reset, price-frame or the three completed toolbar passes.

## S3_PRICE_FACET_COMPLETENESS — verified release checkpoint, 2026-09-06

Exact release code: `312353cad6aedec005dab4d590c97a1e1c216d17`; preparation PR: #1379.

- The local price facet is visible only when every loaded normalized tour row has a positive price, or a hotel without tour rows has its own positive normalized price.
- An incomplete progressive source hides the price control and clears its local limit, so an unknown price is not presented under a misleading visible “up to” promise.
- The existing no-tour hotel price path remains supported and is covered by the focused regression.
- The focused regression failed against the unchanged runtime, then passed after the completeness guard. Empty, complete and incomplete-progressive paths are covered.

Source build/check, focused filter-rail checks and the production-presentation suite passed locally. On the integrated release SHA, 22 applicable workflow runs completed successfully and one migration-only run was expectedly skipped. The only job (`101459884290`) in Security guard run `34023357535` and all of its steps completed successfully at `2026-09-06T08:59:20Z`, while GitHub still reported the enclosing run wrapper as `in_progress` at checkpoint time; do not convert that external status lag into a claim of 23 completed workflows until rechecked. Core run: `34023357536`; responsive visual run: `34023357572`, artifact `9986254745`, digest `sha256:fe7badee8d98acca7f1d9028d9beec4e4e0eb41ffcc6be0cb1328ee00aae414f`; whole-site artifact run: `34023357550`, artifact `9986248680`, digest `sha256:8dfe129b8f4de840927e7feda7707a29429e4be15c6412e89c0f56548f494fb5`. Readability images at 375 and 1440 px were inspected with no new clipping or owner regression. This is not a physical Safari or live-site acceptance claim.

Eight public assets: CSS 363,166 bytes / 4,175 lines; JS 160,559 bytes / 1,943 lines; total 523,725 bytes / 6,118 lines, uncompressed and without double-counting `src` and generated `v2` files. The +552 bytes / +3 lines from the previous code checkpoint are the explicit completeness guard and regression, not a measured page-load result.

Status boundary: integrated in the release draft; 22 workflows and the Security guard job are verified successful, with the enclosing Security workflow run status still lagging. Preview, `main` and production were not updated; Tourvisor/API, price arithmetic, lead transport/mapping, Metrika/goals, logo and neighboring projects were not changed.

Next: first recheck Security guard run `34023357535`. After its wrapper finalizes, update the exact CI count; then prove a new independent defect before editing. Do not repeat price/charter/sea completeness, reset, price-frame or the three completed toolbar passes.


### Completed source preparation: native CSS nesting in results

- Baseline release `86697aa10be922f041c66603f352c7479f227aa1`; published preview remains `4517879c22929706aabc3365b7e3906ecf19bc24` until an exact artifact passes CI and isolated publication.
- Grouped consecutive rules under a single identical `body.search3-candidate` or `html body.search3-candidate` parent, with explicit `&` in every child. No declarations or at-rules in grouping parents; existing media contexts/order and declaration bytes preserved.
- Main results CSS: 258997 → 229195 bytes (−29802). Eight public assets: 433026 → 403224 bytes; seven other assets remain byte-identical. Audit: `docs/project/search3-css-nesting-results.json`.
- Expanded ordered selector/declaration/media streams match the baseline exactly. Source build tests and focused presentation tests pass locally; PHP-dependent local check awaits CI. Cascade ownership hashes updated without changing donor identity or section order.
- Browser boundary: this test variant requires native CSS nesting (explicit `&`, Safari 16.5+ per WebKit); engines without nesting are unsupported. Physical Safari qualification remains open. Production and protected business contracts remain locked.
- Next: responsive/browser CI, then the same bounded rewrite in the remaining CSS owners and one combined exact-artifact preview publication.


### Completed source preparation: remaining CSS owners

- Builds on results nesting code `9597192cb58c00814a84ba21ee3f32f96f38dc53` / #1399; public preview has not changed yet.
- Applied the same single-parent, explicit-`&` rewrite to six modules in entry, result cards and selected flow: 23 groups, −3576 bytes. All four expanded CSS streams match this baseline; all four JS files and the main results CSS are byte-identical. Audit: `docs/project/search3-css-nesting-secondary.json`.
- Combined reduction from release `86697aa`: CSS 290846 → 257468 bytes (−33378, 11.48%); JS stays 142180 bytes. Eight-asset total 433026 → 399648 bytes (−7.71%).
- Native CSS nesting browser boundary from the results pass applies. Existing source ownership, protected acceptance guards and production/business locks remain unchanged. Publish only the final combined artifact after required CI and release integration.


### Completed source preparation — private injected CSS sources

Baseline release `d7882e504a6e66d3f3d71910e4f6f4650aa8b494`; preview remains `74b87bff` until separate publication. Two static injected-style owners now use private CSS sources and build-time escaped string literals at the original insertion positions. Six explicit single-parent nesting groups preserve expanded ordered selector/declaration/media streams. Original IDs, selected-root guard and idempotence verified against compiled owners. No earlier linked stylesheet, new request/global or behavior/price/lead/API change.

JS123837→121897 for the main asset (−1940 bytes); seven other assets byte-identical. Eight-asset total399648→397708 bytes /5680lines; CSS257468/3855, JS140240/1825. Source owners11230→490 and4218→434bytes. Eleven build tests and14 presentation checks pass locally with one PHP-dependent local skip; PHP awaits existing CI. Audit: `docs/project/search3-injected-css-sources.json`. Next: CI, then remaining proven same-selector/context declaration repetition; one combined exact-artifact preview after checks.


### Completed source preparation — later CSS declarations

Private injected CSS #1402 / `10d78a4463732a1d730d94af90b04cb6af795d37` passed23 applicable workflows and one expected skip: core34047082610, visual34047082664, flight34047082620, build34047082564. Preview still74b87bff.

The next source pass removes182 earlier declarations with a later identical full expanded selector list, media/supports context, property and important flag; retained values win later in the same stylesheet. Every removal records its later witness; existing browser CI now requires CSS.supports for those witnesses. No shorthand expansion, selector-list merging or protected acceptance-source change. Removed74 now-empty style/group/media rules; retained ordered declaration stream verified. Full public final per-selector/context/property/priority maps match baseline. Audit: `docs/project/search3-active-css-declarations.json`. Cascade donor/order unchanged; hashes updated. Publish only after responsive and applicable CI pass.

### Completed source preparation — repeated descendant prefixes in main CSS

Baseline release `2b2213db2865973ff4f26c3194a7cfdb6f0feaac`; published preview remains `731eb3a8e1e490d2c07f4727135c373449d4958f` until separate exact publication. Factored 119 adjacent groups under repeated complete descendant prefixes within existing main CSS nesting. Every new parent has one selector and only nested rules; every child has one leading explicit `&`, including bare `&` when selecting the parent itself. Maximum nesting depth is three. Recursive expanded ordered selector/declaration/media streams are exact. Acceptance guards and cascade donor/order remain; cascade hashes updated.

Main CSS saves 32659 public bytes; seven other assets are byte-identical. Total eight files: 389651 → 356992 bytes. Audit: `docs/project/search3-css-descendant-results.json`. Browser compatibility remains native nesting, Safari 16.5+; physical Safari unqualified. Next: existing responsive CI, then remaining CSS owners and a bounded booking-layout JS reduction; one combined isolated preview after checks. No production or protected business changes.

### Completed source preparation — remaining descendant groups and booking layout

First source #1405 / `56bc654af46ee8814ad2b62eec5d83f9ae02aaa4` passed 23 applicable workflows and one expected skip; integrated into release only. Core 34049874338, responsive 34049874332, flight 34049874340, build 34049874271. Editor images at 375/1440 inspected. Published preview still `731eb3a8`.

Nineteen additional single-parent groups in entry/cards/selected CSS preserve recursively expanded ordered CSS streams. Public secondary CSS saves 5287 bytes. Booking layout is a private include inside the original IIFE; source owner 6859 → 4472 bytes and layout part 2246 bytes. Fifteen setters share one local function, saving 183 served JS bytes. Eight layout states pass against baseline and current compiled owners; reversing only the setter replacement restores the original compiled owner exactly. Price arithmetic, rendering, scheduling and events remain unchanged.

Combined eight-asset total: 389651 → 351522 bytes (−38129). CSS 211465 bytes /4007 lines; JS 140057 /1826; total 5833 lines, +225 due to explicit grouping. Audit: `docs/project/search3-css-descendant-secondary.json`. Next: second source CI and one combined exact isolated preview publication. Native nesting boundary and production/protected locks retained.

## S3_RESULTS_CARD_OWNER_RETIREMENT — checked release, 2026-09-07

Source PR #1475 / `7c2f5a07146e5b1c03c2484e0d1ee174ae072655`; checked release `85e387ce3325f186128c86e53e36d9745fdbdb5b`. The complete legacy result-card and expanded-package geometry family was removed from `results-layout.css`. Required card, facts, disclosure and direct-tour geometry remains in the current `results-cards-v2.css` owner; obsolete comparison, inline-detail and decision-badge chrome is not restored.

Eight public assets are **163280 → 161470 raw bytes (−1810 CSS bytes)**. Main CSS is 60819 → 55036 (−5783); the compact current card owner is 6798 → 10771 (+3973); six assets are byte-identical. This is the measured reduction for this source package, not a cumulative baseline claim.

Security `34129515788` and exact artifact build `34129515852` completed successfully. Reusable whole-site artifact `10021526537`, digest `sha256:a37c645ecff250626d0ca4aed903cb8a5c72d7caef306d70344efe08635ba350`. Geometry evidence `10021525549`, digest `sha256:405ffc4000cc166ca0bb2f1caa8a6fd0980776a3453c59404d03dec208627cd6`, covers collapsed and expanded cards at 375/760/999/1000/1440: no horizontal overflow, photo/body overlap, hidden-row leak or clipped facts/actions. Representative images were inspected. Source build/check,13 source-build tests,22 presentation tests and both owner validators passed; one local PHP-only skip is covered by exact artifact CI.

Status boundary: checked release only. Published preview remains `b9445bc3c8aa0713b148241bcddeefdf07576079`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical Safari/safe-area and live current-source acceptance are deferred. Do not repeat #1474 or #1475; audit the next whole presentation owner from fresh release and bundle only a material reduction. Price arithmetic, URL/payload, Tourvisor/API, lead transport/mapping, analytics and eight public paths remain protected.

## S3_BOOKING_FORMAT_OWNER_LOCALIZATION — checked release, 2026-09-07

Source PR #1476 / `11cbdb17e7cf241e5ad8c719d0554a23ef9e58f4`; checked release `b995bdda0598fb1c3bb4e8efa2f9387c058c0f0b`. The standalone `presentation-text.js` and `flight-presentation.js` runtime owners were retired. Their required escaping, supplier-text, party/destination, flight and baggage presentation now live as private `booking-summary` format/services parts; the internal global adapters and one runtime IIFE are gone.

Eight public assets are **161470 → 161116 raw bytes (−354 JS bytes)**. `v2/search3-results-filters-v1.js` is 54407 → 54053; seven assets are byte-identical. This is the measured reduction for this source package, not a cumulative baseline claim.

Security `34130331196` and exact artifact build `34130331206` completed successfully. Reusable whole-site artifact `10021826730`, digest `sha256:2ed6b2ba104e775be24ddf308ee9e61910d0a2e3d7108c232d37cea663d7518a`. Source build/write/check, presentation utilities, flight presentation, booking summary/services and source/presentation suites passed. Price arithmetic, tour/flight events, booking scheduling, lead lifecycle, URL/payload, Tourvisor/API and analytics were not changed.

Status boundary: checked release only. Published preview remains `b9445bc3c8aa0713b148241bcddeefdf07576079`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical Safari/safe-area and live current-source acceptance remain deferred. Do not repeat #1474, #1475 or #1476; audit the next whole presentation owner from the fresh release and bundle only a material reduction.

## S3_ENTRY_RUNTIME_OWNER_LOCALIZATION — checked release, 2026-09-07

Source PR #1480 / `d3132c72ab1b98b1819cd6568076c6cda45deba2`; checked release `6eebb1a510e257392e0ebd6c8ea17d554e3be954`. The standalone `entry-v1.js` runtime was retired into a private part of the current `search-form.js` owner. The public `search3-entry-v1.js` path remains present with a zero-byte payload. `Search3CandidateEntryV1.sync`, responsive region placement, the existing price-calendar adapter, results summary, four-delay settle schedule and legacy matchMedia listener fallback remain available.

Eight public assets are **161116 → 160579 raw bytes (−537 JS bytes)**. `search3-entry-v1.js` is 3226 → 0; `search3-results-filters-v1.js` is 54053 → 56742; six assets are byte-identical. This is the measured reduction for this source package, not a cumulative baseline claim.

Security `34133307944` and exact artifact build `34133307934` completed successfully. Reusable whole-site artifact `10022984486`, digest `sha256:e30614dd479563f2e50f64da1419e26a7bb78e0057d569f6c7631b2f663b9bdf`. Source build/check and 36 source/presentation checks passed; one local PHP-only skip is covered by the exact artifact CI. Price arithmetic, search API, URL/payload, Tourvisor, lead transport/mapping and analytics were not changed.

Status boundary: checked release only. Published preview remains `b9445bc3c8aa0713b148241bcddeefdf07576079`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical Safari/safe-area and live current-source acceptance remain deferred. Next validated candidate is the full `acceptance-guards.css` retirement; rebase its prototype on the fresh release, retain the current drawer/readability/hidden contracts, run focused card and selected-flow geometry, and do not remove `results-tablet-layout.css`.

## S3_ACCEPTANCE_GUARD_OWNER_RETIREMENT — checked release, 2026-09-07

Source PR #1482 / `6bf28b3d89426c3aed3f06683d0339aa8e48e6de`; checked release `c42a8bca9bacb447a9bbed529761993669fc61be`. The complete live `acceptance-guards.css` overlay is now provenance-only. Necessary drawer visibility, results/filter/card readability, selected-flow isolation and selected-tour hidden-state truth live in their current toolbar, results, cards, tour-detail and selected-flow owners. Redundant phone alignment, desktop grid/height and completion-status overrides were not restored.

Eight public assets are **159805 → 157523 raw bytes (−2282 CSS bytes)**. Main results CSS is 54262 → 51784 (−2478); cards CSS is 10771 → 10894 (+123); selected CSS is 10802 → 10875 (+73); five assets are byte-identical. This is the measured reduction for this source package, not a cumulative baseline claim.

Security `34134720944` and exact artifact build `34134720906` completed successfully. Reusable whole-site artifact `10023536739`, digest `sha256:3f4600d19aab8503a56e6ec53620e257ac25b33e25d2f34b6968a8af71cb6ef5`. Results/drawer geometry artifact `10023536324`, digest `sha256:d156d33365bda1264b06e261853e3eaa26138ad70c8b605445e5f0a76c79854b`, covers 12 collapsed/expanded states at 375/760/761/999/1000/1440. Selected geometry artifact `10023535920`, digest `sha256:ee476ec6d410cb1218bd97ad13c85f73000fed26c41915acf999c4271d468b0d`, proves detail/review/lead equivalence in 12 states at 375/760/1000/1440. Source build/check and 37 source/presentation checks passed; one local PHP-only skip is covered by exact artifact CI.

Status boundary: checked release only. Published preview remains `b9445bc3c8aa0713b148241bcddeefdf07576079`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical Safari/safe-area and live current-source acceptance remain deferred. Do not repeat #1482 or remove `results-tablet-layout.css`; audit the next whole presentation owner against the fresh release and bundle only a material reduction.

## S3_MOBILE_SALES_CARD_OWNER — checked release, 2026-09-07

Source PR #1485 / `cca517ac14d7938a7d228d10fdce80d1d450c0ee`; release integration `591feb00f5d3cf1c574093210663c94ef881d019`. The separate `results-mobile-layout.css` donor is provenance-only. Live phone card rules now belong to `results-cards-v2.css`; results tools, drawer/actions, safe-area and scan order remain in `mobile-results-toolbar.css`. The retired five-row compatibility grid and 390px 10/11px fact squeeze were not restored.

Eight public assets are **157523 → 156516 raw bytes (−1007 CSS bytes)**. Security `34140643757` and exact artifact `34140643794` passed. Reusable whole-site artifact `10025783714`, digest `sha256:e20f0df72a3d5ed509eeeb981ebfb0735fee37117968fbacd04244c819bb6bf1`. Results geometry artifact `10025782914`, digest `sha256:5ee98fb8dabb84ae22e5b3a3469723deb88b959b72dea9f25ef802540c60b1ad`, covers 12 collapsed/expanded card and drawer states at 375/760/761/999/1000/1440.

Status boundary: checked release only. Preview remains `b9445bc3c8aa0713b148241bcddeefdf07576079`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical Safari/safe-area and live current-source interaction are deferred. Audit: `docs/project/search3-mobile-sales-card-owner.json`.

## S3_SINGLE_DESKTOP_FILTER_OWNER — checked release, 2026-09-07

Source PR #1486 / `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; checked release `4063a48049f46704dcb94a623d3219361808914b`. The already loaded DS2 result filters are now the sole desktop result-filter UI. Search3 retains only a compact empty-result/reset bridge. Direct/charter/concrete-hotel parameters remain in the canonical edit-search form instead of a duplicate result rail. Two repeated lead-entry hide lists were removed because `Search3SummaryCta.isolateRootChildren()` owns that state synchronously.

Eight public assets are **156516 → 147378 raw bytes (−9138: −8605 JS, −533 CSS)**. `search3-results-filters-v1.js` is 56742 → 48137; main CSS is 49859 → 49326; six assets are byte-identical. Security `34141824444` and exact artifact `34141824479` passed. Reusable artifact `10026227838`, digest `sha256:290789ab82bda4e2169a488ceb3c0449842facce120d47ee811781cb0ad5909b`. The existing selected-geometry detector was strengthened to cover all current selected/review/lead owners; its 12 detail/review/lead states at 375/760/1000/1440 ran and passed. Evidence artifact `10026226970`, digest `sha256:e976c332cd8963aa7b54d00a99082a09e0d58a1c0b4d646b4a7675d03d2a1450`.

The technical raw-size target is reached at **147378 bytes**. Stop byte-only micro-PRs: do not restore the duplicate rail or remove active `results-tablet-layout.css`. Next prepare one accumulated exact isolated preview with a bounded card/filter acceptance pass, then switch priority to selling search UX and a lean Search3 base bundle. Preview is still `b9445bc3`; production remains unchanged and requires explicit visual approval. Live current-source filter interaction and physical Safari/safe-area are deferred. Audit: `docs/project/search3-single-desktop-filter-owner.json`.

## S3_FILTER_MOBILE_ACCUMULATED_PREVIEW — published, 2026-09-07

Exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3` was published to the existing isolated whole-site preview by one-shot control #1489 / run `34143220797`; the control was closed without merge. Evidence artifact `10026686548`, digest `sha256:fb43e9546a7df9a836473faf631c3bd6bef1d1336a610371657b7e9491a34589`. The deployment reused source artifact `10026227838` without rebuilding it and verified the exact source tree, archive, manifest, payload checksums and all 715 files.

Noindex, preview lead HTTP 403, Metrika counter 0, internal PHP denial, HTTPS-to-SSH target binding, atomic rollback and 13 unchanged production fingerprints passed. `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`.

Bounded live check: Moscow→Turkey completed with 100 hotels / 445 tours. The desktop budget rail reduced visible hotels 100→3 at 80000 RUB and Reset restored 100. Supplier-incomplete meal/category/rating/sea facets stayed hidden by the existing completeness guard; `Изменить поиск` exposed category, rating, meal, concrete hotel and direct-flight parameters. No lead was submitted. Physical Safari and device safe-area remain deferred.

The byte-only stage and its accumulated preview acceptance are complete. Next switch priority to selling search UX and a lean Search3 base bundle; do not restore the duplicate rail or remove active `results-tablet-layout.css`. Audit: `docs/project/search3-filter-mobile-preview-publication.json`.

## S3_RETIRED_FILTER_PRESENTATION — checked release, 2026-09-07

Source PR #1490 / `b71d99e6dae73e406190c9d4cea64acbf1f3f2db`; checked release `04f726371d3e62913ec734df99ed29b806ae2091`. After DS2 became the sole desktop filter owner, the remaining unreachable Search3 filter-section, edit-row, radio-skin and empty filter-subtitle families were removed. DS2 budget/meal/category/rating/sea/reset/count, the mobile filter, zero-result bridge and all protected business contracts remain.

Eight public assets are **147378 → 145660 raw bytes (−1718 CSS bytes)**. Seven assets are byte-identical. Security `34143403167` and exact artifact/results-geometry run `34143403145` succeeded. Reusable artifact `10026777435`, digest `sha256:cae31b2ff8e194bacb1caa1e228a46912d39554a4b8d14f7d43181739e8715c6`; results geometry artifact `10026776947`, digest `sha256:60579f3eb925391942354f73df6690fb7173b85794104d661beccc37a1ef144f`.

Status boundary: checked release only. The isolated preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3` at 147378 bytes; the later 1718-byte removal is unreachable CSS and was not republished. `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical Safari/safe-area remains deferred. Audit: `docs/project/search3-retired-filter-presentation.json`.

Next: the byte-only stage is closed. Do not open micro-reduction PRs, restore the duplicate rail or remove active `results-tablet-layout.css`. Begin the selling-search UX stage and lean Search3 base bundle; production still requires explicit visual approval.

## S3_MOBILE_SELECTED_CTA_SALES_REPAIR — checked release, 2026-09-07

Source PR #1492 / `1afd5c5d0866a26284be015c2cfc70e2818af534`; checked release `5b491ce0d0a05d7ee7a6a815564b529fa89589d3`. The first conversion-critical product repair closes the confirmed 375px selected-tour defect where the fixed mobile CTA had no height owner and could collapse to roughly 11px. The current `selected-flow-v2.css` owner now gives the existing `<=640px` action a 48px minimum height.

Eight public assets are **145660 → 145676 raw bytes (+16 CSS bytes)**. This is an intentional accessibility/conversion repair, not a size saving. Seven assets are byte-identical. Security `34144224022` and exact artifact/selected-geometry run `34144224020` succeeded; the ready-for-review repeat `34144352197` also succeeded. Reusable artifact `10027068097`, digest `sha256:c7e1ce6a777179518a8e0aad25f3d1f9b49a519ad4f5c81df24c4bc274e5b957`; selected geometry artifact `10027067465`, digest `sha256:ad41486e5ef0fd7125712838adb7b9453fbb89572c956daf657006523bab0b55`.

The browser fixture requires the 375px CTA to be at least 48px and permits change only inside that mobile bar; the rest of detail/review/lead geometry at 375/760/1000/1440 remains equal, with no overflow or lead submission. The change is checked release only. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical iPhone/Safari safe-area and live current-source journey remain deferred.

Next: audit search-form completion friction and local-filter feedback using the current single-owner architecture. Accumulate the next product batch before preview publication; do not return to byte-only micro-PRs. Plan: `docs/project/search3-size-and-sales-plan.md`. Audit: `docs/project/search3-mobile-selected-cta.json`.

## S3_MOBILE_SEARCH_FORM_USABILITY — checked release, 2026-09-07

Source PR #1494 / `d65a0f8e2acfdcc1c7475806563cdb80119b967b`; checked release `8d5db6ca041c4d6b84271b8d1cf0cc1a7ccde4a0`. Mobile form labels are now at least 12px, primary inputs 16px, primary controls/search/advanced-filter action 48px, and quick filters 44px. The tourist popover follows the taller summary and its selectors are 44px/16px. Form values, request behavior and all protected business contracts are unchanged.

Eight public assets are **145676 → 146599 raw bytes (+923 CSS bytes)**. This is intentional sales-readiness weight, not a size saving; seven assets are byte-identical. Security `34148483288`, initial exact artifact `34148483292` and ready repeat `34148657647` succeeded. Reusable artifact `10028594347`, digest `sha256:84f9e32eb8a2e9fbff1ad0ca77f0d81b42be00df44fad728a9d8b9a7aadf763a`; combined results/entry geometry artifact `10028594064`, digest `sha256:ca6aff6e7a028fcd431b5d44c53b253810f5a1c605840c4aa22abb4e09313fc3`.

The Chromium fixture passed entry geometry at 375/760/761 and the existing 12 collapsed/expanded result states, with no horizontal overflow. Screenshots were retained but not manually inspected; no lead was submitted. The change is checked release only. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Physical iPhone/Safari safe-area and live current-source interaction remain deferred.

Next: add immediate, accessible local-filter feedback and a recoverable zero-results state in the single DS2 owner. Do not add a second filter runtime or a request per local change. Accumulate a meaningful product checkpoint before the next isolated preview publication. Audit: `docs/project/search3-mobile-form-usability.json`.

## S3_LOCAL_FILTER_FEEDBACK — checked release, 2026-09-07

Source PR #1496 / `3a9fe863edb6c44adda45fc9a0b35bbf9e1136f3`; checked release `95ad5f3ff5bc9545ef7c877676c60c8e68f500ec`. The sole DS2 desktop filter owner now exposes its live result count as a polite atomic status. A local zero match uses the explicit heading “Ничего не найдено” and the recovery copy “Сбросьте фильтры или измените параметры”; resetting restores the source result count. The synchronous local renderer remains the only execution path, with no Tourvisor/API request added.

The eight Search3 public assets remain **146599 → 146599 raw bytes (0)**. The already loaded `v2/ds2-results-filters.js` changes **10913 → 11091 bytes (+178 JS)**. Security `34149537357`, initial exact artifact `34149537437` and ready repeat `34149630582` succeeded. Reusable artifact `10028902712`, digest `sha256:71b4038743cc80b42ddd0a1cdb5832084a48c90c95183b5652a2d3aee07e35ab`.

Focused VM acceptance covered complete facets, a legitimate zero match, Search3 empty-shell preservation, recovery copy, reset, restored source results and the sole-owner boundary. This package did not trigger or claim browser geometry evidence. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Live current-source interaction, physical iPhone/Safari and safe-area acceptance remain deferred; no lead was submitted.

Next: audit the “Ближе к морю” sort and common map action. Keep only controls backed by complete supplier data and working behavior. Accumulate the next product checkpoint before isolated preview publication. Audit: `docs/project/search3-local-filter-feedback.json`.

## S3_HONEST_RESULTS_CONTROLS — checked release, 2026-09-07

Source PR #1498 / `24e893e60958b6f12df66cecb8acc1e5d67bc628`; checked release `8980502708a1c7dd77e2dfec81131ae88e3f5562`. The offered “Ближе к морю” mode was not implemented and silently used price order. The common map button dispatched `v2:results-map-requested`, but the repository had no consumer. Both misleading controls and their dead runtime/CSS branches are removed. Price, rating and star sorting plus list/grid views remain.

The eight Search3 assets remain **146599 → 146599 raw bytes (0)**. Three supporting loaded files shrink **29214 → 28737 bytes (−477 raw bytes)**: `v2/index.php` 12872→12731, `v2/ds2-search.css` 11843→11755 and `v2/search-redesign-v2.js` 4499→4251. Security `34150688964`, initial exact artifact `34150688836` and ready repeat `34150780889` succeeded. Reusable artifact `10029282505`, digest `sha256:8b49234e3cd866af6e538144eca3f16e660ac4e0601dbd345e418bb94e5f3c4c`.

Focused tests require exactly the three implemented sort modes, preserve the mobile sort proxy and results lifecycle, and reject reintroduction of the unhandled map action. This non-geometric package did not run or claim browser geometry. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Live current-source interaction, physical iPhone/Safari and safe-area acceptance remain deferred; no lead was submitted.

Next: audit the result-card action hierarchy and keep one clear primary path from hotel to tour selection. Preserve supplier data, price arithmetic, selection/lead lifecycle and analytics. Accumulate before isolated preview publication. Audit: `docs/project/search3-honest-results-controls.json`.

## S3_SINGLE_CARD_PRIMARY_CTA — checked release, 2026-09-07

Source PR #1500 / `635e512f469dab9222b12fa7637672428ab40c5a`; checked release `2e79f12c74f24ae2737de8827447b44b134acf77`. The result card now has one count-aware primary action (`Показать N туров`) instead of a separate availability sentence plus generic `Показать туры`. Correct Russian plural forms, expanded `Скрыть туры`, collapse restoration and each concrete offer's `Выбрать тур` action remain in the current results owner.

Eight public Search3 assets are **146599 → 145974 raw bytes (−625: −597 CSS, −28 JS)**. The removed `.search3-hotel-action__copy` markup and responsive presentation are no longer emitted. Five assets are byte-identical. Price arithmetic, supplier facts, Tourvisor/API, URL/payload, selection, lead transport/mapping and analytics are unchanged.

Security `34152822504`, initial exact artifact `34152822452` and ready repeat `34152950201` succeeded. Reusable artifact `10029998652`, digest `sha256:56dbeb79466ea02d559e2a65284c4fd0ff0fe79efeefe7880726abb15f01ee4b`; results geometry artifact `10029998348`, digest `sha256:25ef1a9f56a652e3d6c01891d5c9b8b73098bc2c018bb7ab01b2a6a2f799469f`. The Chromium fixture passed 12 collapsed/expanded states at 375/760/761/999/1000/1440 with no horizontal overflow. Screenshots were retained but not manually inspected; no lead was submitted.

Status boundary: checked release only. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3` at 147378 Search3 bytes; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Live current-source interaction, physical iPhone/Safari and safe-area acceptance remain deferred.

Next: verify whether Search3's list/grid switch produces two distinct layouts despite the current one-column Search3 result owner. Remove or condition only a nonfunctional Search3 control while preserving the working legacy `/poisk-turov-old/` grid. Audit: `docs/project/search3-single-card-primary-cta.json`.

## S3_HONEST_VIEW_CONTROL — checked release, 2026-09-07

Source PR #1502 / `2f300216b61b900614f443af907f04eb92a3fcba`; checked release `d2077ede44e9517fd06af6cfc0c081a04ee02d45`. The shared list/grid switch changed classes and localStorage, but Search3's stronger current results owner always renders a one-column flex list. Search3 therefore no longer emits the two ineffective view buttons. The maintained `/poisk-turov-old/` presentation still renders both controls and keeps the existing shared runtime and grid CSS.

The eight Search3 public assets remain **145974 → 145974 raw bytes (0)**. The rendered Search3 HTML removes **268 bytes** and two misleading controls; this supporting-payload reduction is recorded separately and is not counted as an eight-asset reduction. Price/rating/stars sorting, supplier data, Tourvisor/API, URL/payload, selection, lead transport/mapping and analytics are unchanged.

Security `34156356460`, exact artifact `34156356467` and standalone navigation `34156356471` succeeded. Reusable artifact `10031101653`, digest `sha256:3080f8b043d65042d94f10e65214e4777e611196d27f58d9963610c2a064c81f`. Exact PHP rendering verifies the Search3 switch is absent and both legacy controls remain. No CSS/card geometry changed, so the geometry jobs correctly skipped; no visual acceptance is claimed and no lead was submitted.

Status boundary: checked release only. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3` at 147378 Search3 bytes; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Live current-source interaction, physical iPhone/Safari and safe-area acceptance remain deferred.

Next: begin the lean Search3 base-bundle audit. Classify full-manifest modules by Search3 dependency, then use the existing bundle endpoint for a route-scoped Search3 manifest while keeping `/poisk-turov-old/` on the complete legacy bundle. Require measured loaded raw/gzip savings and source/closure/browser evidence before changing the route. Audit: `docs/project/search3-honest-view-control.json`.

## S3_LEAN_BASE_BUNDLE_V1 — checked release, 2026-09-07

Source PR #1504 / `826c424ee2f8f5c9b3e5bbc0fd19a337fbe6b126`; checked release `fc3550dd320cbc1791f7fb7f04d186a5a88dd8c0`. The existing `bundle-v1.php` endpoint now selects a Search3 manifest scope on the canonical route while the legacy `/poisk-turov-old/` route retains the complete original manifest and URL contract. Search3 excludes the complete `search-redesign-v2.js` owner: its list/grid behavior is legacy-only, and the required route/date/night/guest summary now belongs to the current Search3 results owner.

Loaded Search3 CSS/JS is **665247 → 662065 raw bytes (−3182)**. The shared JavaScript scope is 279690 → 275439 (−4251), while the eight Search3 assets are 145974 → 147043 (+1069) for the retained summary behavior. The endpoint plus independent-asset gzip estimate is 102901 → 102309 (−592). Shared CSS is unchanged at 239583 bytes. This is a route-loaded total; the eight-path subtotal is recorded separately and is not mislabeled as a reduction.

Security `34160747906`, exact artifact `34160834731` and preview-boundary `34160834648` succeeded. Reusable artifact `10032545333`, digest `sha256:7e4e4e025bb75ba1486a56ef5bf5bd16353b5aad6f4b6efdae916c90e493b8b2`. Closure checks prove 44 full versus 43 Search3 JavaScript owners, unchanged CSS scope, legacy retention and Search3 exclusion. Focused VM tests cover route, dates, nights, tourists, results/reset and edit focus. No CSS geometry changed; browser geometry correctly skipped and no visual acceptance or lead submission is claimed.

Status boundary: checked release only. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; `main` and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Live current-source interaction and physical iPhone/Safari safe-area remain deferred.

Next: audit `conversion-confidence-v1` as the next whole-owner Search3 exclusion candidate. Its compare/decision/agency surfaces are currently hidden or unreachable under the Search3 presentation, but preserve any genuinely live selected CTA/trust behavior in a current owner before excluding it. Keep the full legacy bundle unchanged. Audit: `docs/project/search3-lean-base-bundle-v1.json`.

## S3_LEAN_CONFIDENCE_OWNER — checked release, 2026-09-07

Source PR #1506 / `ed0f8a299fd81c1f7d786b688ecdfdede1631585`; checked code release `36758a05df21042fb39f253faa681ff1de89150d`. Search3 no longer loads the complete `conversion-confidence-v1.js`, `compare-refresh-guard-v1.js` and `conversion-confidence-v1.css` owners. Hidden compare/decision/result-note/agency surfaces are no longer created. Desktop selected-tour trust and tour-choice labels are retained by the existing selected/results owners. All three original files and the full legacy route manifest remain unchanged.

This invocation removes **28288 raw bytes: 662065 → 633777** across the scoped shared CSS/JS and eight public Search3 assets. Shared CSS is 239583 →227955, shared JS 275439 →256635, and the eight-asset subtotal is 147043 →149187 (+2144 for retained behavior/styles). Gzip estimate is 146052 →139934 (−6118), including BOTH shared CSS/JS endpoint bodies and the eight independent files. The earlier lean-bundle audit's gzip totals omitted unchanged shared CSS; its −592 delta is valid, but those totals were a partial subtotal.

Security `34163231518`, exact artifact `34163231531` and ready repeat `34163330446` succeeded on the final source. Reusable artifact `10033349256`, digest `sha256:84d8e9e4cbdc12e039a0504e58d0ee42f51bb1ebcd9dd58e80931b84d2787e5d`. Selected geometry artifact `10033348290`; results/entry geometry artifact `10033348786`. The browser suite passed 12 detail/review/lead states at 375/760/1000/1440, 12 result states at 375/760/761/999/1000/1440 and three entry states. It now uses the actual scoped CSS and compares prior-release runtime alongside the retained frozen CSS baseline. Desktop trust remains visible, mobile trust remains hidden, and the retired surfaces/global are absent. Initial test-loader incompatibility with the pre-scope historical baseline was repaired without relaxing the geometry comparison. No lead was submitted.

Status boundary: checked release only; no new publication. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main/production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Manual screenshot inspection was deferred: artifact materialization returned HTTP 403. Live current-source interaction and physical Safari/safe-area remain deferred; automated geometry is not manual visual approval.

Next: audit `mobile-search-summary-v1.js` (9381 raw) and its CSS (4907 raw). Search3 hides its sticky CTA/summary, but its search-start collapse, dirty/validation recovery and field-placement side effects must be retained or deliberately replaced in current owners. Require real-route mobile form/recovery and 700/701/760/761 transition evidence before excluding it. Keep the live sales-leader photo fallback/supplier badge and protected contracts. Audit: `docs/project/search3-lean-confidence-owner.json`.

## S3_LEAN_MOBILE_SUMMARY — checked release, 2026-09-07

Source PR #1509 / `019420f4181630c3de35849004ede9e1119a414a`; checked code release `cdc2d82d7785dcc82843cf74b489e3e3259b712f`. Search3 excludes the complete `mobile-search-summary-v1.js` and CSS owners. The current entry owner preserves the 700px search-start collapse, dirty/validation recovery and desktop restore. The retired sticky CTA/summary/sentinel and duplicate observers are absent; canonical fields and native inputs are retained. Full legacy manifest/files remain unchanged.

Packet loaded raw **633777 → 620431 (−13346)**; shared CSS 227955 →223048, shared JS 256635 →247254, eight Search3 paths 149187 →150129 (+942 for retained lifecycle). Gzip estimate across both shared endpoint bodies and eight independent files is 139934 →136884 (−3050). Together with #1506, this invocation is **662065 →620431 raw (−41634)** and 146052 →136884 gzip (−9168). These are loaded totals; the eight-file subtotal alone increased, so it is not reported as an eight-asset reduction.

Security `34164093668`, exact artifact `34164093654` and ready repeat `34164198720` passed. Reusable artifact `10033635222`, digest `sha256:2a1f82cedf72543136a2124624bfb4c46c24a3820d74d89a6caba0f6b6b6b8ee`. Selected/lifecycle evidence `10033634255`, results/entry evidence `10033634715`. Real isolated-route Chromium assertions passed 30 form states at 375/700/701/760/761/1440 (initial, started, validation, dirty, resize), 12 selected, 12 results and three entry geometry states. Form values/order, visibility/recovery and overflow are checked. The first new comparison exposed the intentionally removed 1px sticky sentinel; reference canonicalization removes only that retired node, preserving strict field/form comparison. Existing source/PHP/path/presentation/isolation guards remain enabled. No lead or supplier request was sent by the new fixture.

Checked release is ahead of published preview: no publication in this invocation. Preview stays at `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main/production stay at `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Live current-source interaction, manual screenshots (artifact download HTTP 403), physical Safari and safe-area acceptance are deferred. Automated assertions are not owner visual acceptance.

Next: audit the complete `primary-meal-ux-v1.js`/CSS owner (7301+2040 raw). Its meal catalogue API loading, URL food restoration and reset preservation are live and must remain in a current owner before excluding obsolete quick-choice presentation. Do not blindly remove it or the live sales-leader photo fallback/supplier badge. Audit: `docs/project/search3-lean-mobile-summary.json`.


## S3_LEAN_PRIMARY_MEAL_OWNER — checked release, 2026-09-07

Source PR #1512 / `46388519c2832eb55f7804e256df501ba2873e3d`; checked release `05763d64cfeab128c84698b58fd6630123edef1c`. Search3 excludes the complete `primary-meal-ux-v1.js` and CSS owners. The current catalog owner exposes its existing meal loader, and the current Search3 form owner loads it on native-select focus plus the existing bounded automatic attempt, restores `food` from the URL after asynchronous options arrive and retains stars/meal values across the legacy additional-filter reset. Obsolete quick choices are absent. The full legacy manifest/files remain unchanged.

Loaded raw is **620431 → 612019 bytes (−8412)**: shared CSS 223048→221008, shared JS 247254→240024 and eight Search3 assets 150129→150987 (+858 for retained current-owner behavior). Exact emitted endpoint plus eight independent-file gzip is **136884 → 134646 (−2238)**. The eight-path increase is not mislabeled as a saving.

Security `34165119169`, initial exact artifact `34165119105` and ready repeat `34165273963` passed. Reusable artifact `10033962139`, digest `sha256:70b665898145c6881d7c237f1efc002d76322cb967bbb378146305597a588bbe`; selected evidence `10033961421`, results/entry evidence `10033961789`. Browser checks cover accessible native meal options, URL value restoration and no retired quick choices at 375/700/701/760/761/1440, plus the existing 12 selected and 12 result states. No supplier search or lead was submitted.

No publication: preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main/production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Manual screenshot inspection and physical Safari/safe-area are deferred.

## S3_LEAN_PARAMETER_FILTER_RAIL — checked release, 2026-09-07

Source PR #1514 / `ee11ef2cb06771dc2e25688acf39349c213dbd56`; checked code release `ba8ff686c4bc9995762a3c09a4ad7e7b6a8b6241`. Search3 excludes the complete `search-params-filter-rail-v1.js` and CSS pair; current Search3 form and DS2 desktop/mobile result-filter owners remain. The full legacy route retains both files.

The first exact browser run found a real hidden dependency: `search-filters-ux-v1.js` temporarily placed the hotel-category field in the legacy main grid, and the retired rail moved it out before the current Search3 owner cleared that grid. The current form owner now performs this one existing-node move before clearing legacy markup. The strict field-order/value/geometry comparison was preserved and passed after the repair.

Packet loaded raw is **612019 → 605488 bytes (−6531)**: shared CSS 221008→218589, shared JS 240024→235774 and eight Search3 assets 150987→151125 (+138 retained behavior). Gzip is **134646 → 133134 (−1512)**. Combined with #1512, this invocation is **620431 → 605488 raw (−14943)** and **136884 → 133134 gzip (−3750)**.

Security `34166319834`, initial exact artifact `34166319872` and ready repeat `34166449552` passed. Reusable artifact `10034328909`, digest `sha256:d7bdf4003890865a4b990527eec476df66b604eb32e932e6dcdef157bc5191a0`; selected evidence `10034327927`, results/entry evidence `10034328420`. Chromium passed 30 form lifecycle states at 375/700/701/760/761/1440, 12 selected states and 12 result states. No real request or lead was sent.

PR #1513 attempted whole `ds2-search-intro-v1.css` exclusion but exact CI failed all 12 selected-tour geometry states. It was closed unmerged; its projected bytes are not counted. This proves that owner still contains live selected-tour geometry and must not be retried as a blanket removal.

Checked release is ahead of the published preview. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main/production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. Manual screenshot inspection, live current-source interaction and physical Safari/safe-area are deferred. Audit: `docs/project/search3-lean-primary-meal-and-params.json`.

Next: audit complete `results-layout-guard-v1.css` exclusion against the current card/drawer owners. Preserve the full legacy manifest and require exact 12-state results plus selected geometry. Do not retry blanket `ds2-search-intro-v1.css` deletion.


## S3_LEAN_LEGACY_GUARDS — checked release, 2026-09-07

Source PRs #1516 / `93e7cfbd685d32cf0a377262a5b29c1e3e6f5dbb`, #1517 / `56345ec59e08808336d0bdfbc0449b31c27ff44c` and #1520 / `2230920eb0a96c16d3eced698dcbdc4b420b6120`; checked code release `26fc0efbe8b306fbaca87b2d5330bc3311d97c6b`.

Search3 excludes three complete obsolete CSS owners while the full legacy route keeps them:
- `results-layout-guard-v1.css`: old card/photo/sidebar/nights overrides superseded by current results/card/mobile owners;
- `search-header-layout-guard-v1.css`: desktop compatibility geometry superseded by the current shared header;
- `ds2-search-tablet-filters-v1.css`: 701–820px styling for the hidden legacy `details.extras`; Search3 uses its current quality grid.

No compensation code was added. Loaded Search3 raw is **605488 → 594063 (−11425)**, entirely scoped shared CSS: 218589→207164. Shared JS remains 235774 and the eight public Search3 paths remain exactly 151125. Exact emitted endpoints plus eight independent files gzip is **133134 → 131441 (−1693)**.

Each source passed Security and both initial/ready exact artifacts: Security `34167475861`, `34167795105`, `34168181748`; exact `34167475846`/ready `34167577360`, `34167795073`/ready `34167934629`, `34168181757`/ready `34168312060`. Final reusable artifact `10034902803`, digest `sha256:9420a39c0a653f38de70e6f0300dc5126e2cca3c7b0d398f91312ce55833b61f`; selected evidence `10034902422`, results/entry evidence `10034902626`.

Chromium retained 12 selected detail/review/lead states, 12 result-card/drawer states and the six-width entry lifecycle including 700/701/760/761. No real supplier request or lead was sent. Screenshots were retained but not manually inspected.

Checked release is ahead of published preview. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`. During final checkpointing, `main` independently advanced from `fa58a0cba6dcfc8624d98c20d64fa06330eae309` to `47d6ccd0c324aceb4d6090fe53ffac03b9c41816` through #1519, which changed only `.github/workflows/deploy-anytoour.yml`; none of these Search3 PRs targeted `main`. No Search3 production deployment was performed, and production SHA was not re-verified in this invocation. Live current-source interaction, manual screenshot review and physical Safari/safe-area remain deferred. Audit: `docs/project/search3-lean-legacy-guards.json`.

Next: combine `selected-tour-layout-guard-v1.css`, `search-footer-rhythm-v1.css` and `search-shell-grid-v1.css` into one ≥1 KB legacy-guard audit. Do not open separate micro PRs. Preserve exact selected/shell/footer geometry and the full legacy manifest.

## S3_LEAN_SHELL_CHECKOUT_OWNERS — checked release, 2026-09-07

Source PRs #1523 / `802f2a222864d54e7bafacbf348ea6b7422583a7` and #1524 / `56c2c1d5bdda7747d705c036ac6722f9ec991b19`; checked code release `3d083e5d19a5dd434a61f394f8959370fd29d0d5`. Search3 no longer loads complete `search-shell-grid-v1.css`, `search-footer-rhythm-v1.css` and `checkout-experience-v1.css` owners. The full legacy route retains every file. `selected-tour-layout-guard-v1.css` remains loaded because exact CI proved that it still owns live geometry. Checkout JavaScript remains loaded.

Loaded raw is **594063 → 584544 bytes (−9519)**: scoped shared CSS 207164→196984, shared JS remains 235774 and the eight Search3 paths 151125→151786 (+661 retained current-owner bytes). Exact emitted endpoints plus the eight independent files gzip is **131441 → 129651 (−1790)**. These are net route-loaded totals; transferred rules are included. An exact repository recount corrected the original checkpoint by +8 raw bytes and +3 gzip bytes.

#1523 passed Security `34169904203` and final exact artifact `34170067115`. #1524 passed Security `34171462978` and exact artifact `34171462972`. Reusable final artifact `10035859121`, digest `sha256:bb28f0a059b5170d59d5bcdd320c52ba9387a39f046876ff508daf6f210ee409`; selected evidence `10035858365`, results/entry evidence `10035858746`. Exact Chromium passed 12 selected detail/review/lead states at 375/760/1000/1440, 12 result states at 375/760/761/999/1000/1440 and the entry lifecycle widths. Initial checkout-removal runs failed on selected geometry; only 634 generated bytes of demonstrated image/flight/lead rules were restored in current owners. Guards were not weakened.

Manually inspected final current screenshots for selected detail at 375, lead entry at 1440, filter drawer at 375 and expanded result card at 1440. No new overflow or clipping was found relative to the exact retained baseline; the 48px mobile selected CTA remains. No supplier request or lead was sent.

Checked release is ahead of published preview. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816` and was not changed by these PRs. No Search3 production deployment occurred. Live current-source interaction, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-shell-checkout-owners.json`.

Next: audit the complete `checkout-experience-v1.js` presentation runtime. Retain optional lead-field disclosure and any required classes/ARIA in current owners, preserve lead transport and the full legacy route, and exclude the whole runtime only after focused lifecycle and exact browser CI pass.


## S3_LEAN_CHECKOUT_RUNTIME — checked release, 2026-09-08

Source PR #1526 / `e1a847d7ffb805c2398a95c6c0db26df87b9e0e9`; checked code release `5a867bd074b63b59348c1f442352754463b64c05`. Search3 no longer loads the complete 6061-byte `checkout-experience-v1.js` runtime. The full legacy route still loads it. The current selected-flow owner retains only live checkout geometry classes, facts/flight ARIA, optional name/comment disclosure and lead-success hiding. Hidden journey/facts-heading markup, its stage mutations and the separate selected-tour observer are retired.

Loaded raw is **584544 → 580581 bytes (−3963)**: scoped shared JavaScript 235774→229713, the eight Search3 paths 151786→153884 (+2098), and shared CSS remains 196984. Exact emitted endpoints plus eight independent files gzip is **129651 → 128767 (−884)**. An exact repository recount corrected the preceding checkpoint by +8 raw bytes and +3 gzip bytes; this does not change the package delta.

Security `34172559663` and both exact artifacts `34172559608` / `34172785030` passed. Reusable final artifact `10036258837`, digest `sha256:9203361cc166c5388cfdd360165762d4c5c56b9c48fb0c6921b5d0f47c8a2b94`; selected evidence `10036258261`, results/entry evidence `10036258592`. Exact Chromium passed 12 selected detail/review/lead states, 30 entry states and 12 result states. No supplier request or lead was sent.

Checked release is ahead of published preview. Preview remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No preview or production deployment occurred. Live current-source interaction, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-checkout-runtime.json`.

Next: audit the complete `sales-leader-ui-v1.js/.css` presentation pair. Preserve the current selected-tour trust block, full legacy route and all protected contracts; exclude the old pair only after focused source and exact browser CI.

## S3_LEAN_SALES_LEADER_AND_DIRTY_OWNERS — checked release, 2026-09-08

Source PRs #1528 / `913208a812999e84b1a810c1e1278ba02e8e5e23` and #1529 / `3a8f12535a13d368572287462c799d2cbf234cd2`; checked code release `6d95ad0274f470c73cb77b282abdb219570ade02`. Search3 no longer loads the complete `sales-leader-ui-v1.js/.css` and `search-dirty-ux-v1.js/.css` owners. The full legacy route retains all four files unchanged.

The current result-card owner retains the supplier badge and broken-photo fallback. The current results owner retains one accessible stale-results banner, refresh through `V2SearchLifecycle.submit`, clearing on search start/reset and reapplication after rerender. The duplicate legacy dimming/pseudo-message layer and its unused Search3 global are retired.

Loaded raw is **580581 → 578965 bytes (−1616)**: scoped shared CSS 196984→195452, scoped shared JS 229713→226610 and eight Search3 assets 153884→156903 (+3019 retained current behavior). Exact emitted endpoints plus eight independent files gzip is **128767 → 128501 (−266)**. The current invocation including #1523/#1524/#1526 is **594063 → 578965 raw (−15098)** and **131441 → 128501 gzip (−2940)**.

#1528 passed Security `34173718206` and exact artifacts `34173718133` / `34173860069`. Its reusable artifact is `10036603891`, digest `sha256:195e108d40f89327c4433cc6462efd3f72195d44b7af7351b2c140c8645dcc46`; selected/results evidence `10036603298` / `10036603609`. #1529 passed Security `34174597073` and exact artifacts `34174597057` / `34174703669`. Its reusable artifact is `10036876352`, digest `sha256:a7e956879588c70024a585e84c51e5ee774b013e41b45293409b4057b9da7291`; selected/results evidence `10036875494` / `10036875928`. One preceding #1529 run failed only because the expected legacy CSS array order was stale; the assertion was corrected to the actual unchanged order and no guard was weakened.

Exact Chromium retained 12 selected detail/review/lead states, 30 entry states and 12 result-card/drawer states. No supplier request or lead was sent. Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-sales-and-dirty-owners.json`.

Next: audit `search-complete-recovery-v1.js` and `search-progress-ux-v1.js/.css` against current entry/results owners. Preserve retry, progress and empty/error recovery; retire a whole owner only when focused lifecycle plus exact browser CI pass and net route saving is at least 500 raw bytes.

## S3_LEAN_COMPLETE_RECOVERY_OWNER — checked release, 2026-09-08

Source PR #1531 / `db7a8789b1b267a1d03143bb2f05f8ffdbe6dcdf`; checked code release `ed2b4f2c35674f7242203d4e5db8db98eec112c2`. Search3 no longer loads the complete 1584-byte `search-complete-recovery-v1.js` runtime. The full legacy route retains the file unchanged.

`search-progress-ux-v1.js` already owns completed-search/status-error detection, renders the same accessible alert/copy/button and performs the actual result-only retry with search ID, generation and dirty-state protection. No compensation code was needed. The duplicate event subscriptions, state and unused Search3 global are retired.

Loaded raw is **578965 → 577381 bytes (−1584)**: scoped shared JavaScript 226610→225026; shared CSS and the eight Search3 assets remain 195452 and 156903. Exact emitted endpoints plus eight independent files gzip is **128501 → 128290 (−211)**. The current invocation total is **594063 → 577381 raw (−16682)** and **131441 → 128290 gzip (−3151)**.

Security `34175615768` and exact artifacts `34175615653` / `34175744875` passed. Reusable artifact `10037202118`, digest `sha256:463c93d6cec9c305a289f43855c60921893f0a59318ae6f20ba14d7964acfe78`; selected/results evidence `10037201648` / `10037201881`. Exact Chromium retained 12 selected, 30 entry and 12 result states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-complete-recovery-owner.json`.

Next: audit the complete `search-progress-ux-v1.js/.css` owner against current entry/results behavior. Preserve progress, continue-search, empty/error recovery and `retryResultsOnly` semantics; retire it only after focused lifecycle and exact browser CI with at least 500 raw bytes net saving.

## S3_LEAN_PROGRESS_OWNER — checked release, 2026-09-08

Source PR #1533 / `a9653110264e6b47c0f4467afb14300c6a80c16d`; checked code release `aad34a0590b94bf3b140097411e935e9be33116f`. Search3 no longer loads the complete 12769-byte `search-progress-ux-v1.js` and 3068-byte `search-progress-ux-v1.css` owners. Both unchanged files and the compatibility global remain on the full legacy route.

One current Search3 owner retains all 13 progress/continue lifecycle states, accessible empty/error presentation, non-submitting date/night relaxation, edit/filter actions, normal retry and guarded result-only retry. Focused differential coverage verifies recovery success/failure and stale-generation rejection. The initial mobile loading state remains sticky through a scoped rule; 44px actions remain at widths through 700px. The excluded mobile-summary safe-area selector and redundant post-results override are retired.

Loaded raw is **577381 → 574518 bytes (−2863)**: shared CSS 195452→192384, shared JavaScript 225026→212257 and eight Search3 assets 156903→169877, including all retained current-owner code. Exact emitted endpoints plus eight independent files gzip is **128290 → 127657 (−633)**. This is the complete reduction for this invocation.

Security `34178713799` and exact artifacts `34178713803` / `34178849497` passed. Reusable final artifact `10038202718`, digest `sha256:86cf62bcbe99f2a3619550c740f6d3e78b2dd1e19b006e96ce90a414cf76eda8`; selected/results evidence `10038202139` / `10038202445`. Exact Chromium retained 12 selected, 30 entry and 12 result states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual focused screenshot review, physical Safari/safe-area, the pre-existing browser-default result-only retry styling and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-progress-owner.json`.

Next: exclude the complete 6266-byte `header-current-site.css` owner from Search3 after focused global-header geometry proves it only targets absent legacy header classes. Keep the file in the full legacy manifest and do not touch the live `selected-tour-layout-guard-v1.css` owner.

## S3_LEAN_HEADER_AND_SELECTED_DESCRIPTION — checked release, 2026-09-08

Source PRs #1535 / `bb7ed9e3f4eefcda01a2f49f0a1ff186c7064e0a` and #1536 / `cc35208f740cd957ca450f7b19fa2acfe6af7e6d`; checked code release `7d7cadd20fb890cf81423f0ae3c9abf11f218868`. Search3 no longer loads the complete 6266-byte `header-current-site.css` owner or the complete 12957-byte `selected-tour-description-v1.js` runtime. The full legacy route retains both unchanged files.

The current shared header passed a new exact ten-width geometry matrix at 375/520/521/768/769/1024/1025/1100/1101/1440. The current selected-flow owner retains the long hotel-description disclosure, secondary facts disclosure after five facts, all ARIA state and the `ВАШ ТУР` eyebrow. The first selected-description exact run correctly exposed live selected-head spacing and eyebrow declarations from the injected theme. Only those demonstrated declarations plus the required compatibility class were retained in the current linked owner; the full injected theme, hidden stepper, duplicate decision summary, second observer and unused Search3 global are retired. Guards were not weakened.

The two-package payload is **574518 → 556977 raw bytes (−17541)**: scoped shared CSS 192384→186118, scoped shared JS 212257→199300 and eight Search3 assets 169877→171559 (+1682 retained behavior/presentation). Exact emitted endpoints plus the eight independent files gzip is **127657 → 124085 (−3572)**. Together with the immediately preceding unreported #1533 package, the current technical sequence is **577381 → 556977 raw (−20404)** and **128290 → 124085 gzip (−4205)**.

#1535 passed Security `34179958602` and exact artifacts `34179958601` / `34180190441`. Its reusable artifact is `10038644238`, digest `sha256:bb4e598493804b4072972cf88311a0b8e6d15119c93a048bc20bde5718993ff8`; header/results evidence `10038643910`, selected evidence `10038643590`. #1536 passed final-source Security `34181078426` and exact artifacts `34181078436` / `34181265652` after the diagnostic exact failure `34180675055`. Its reusable artifact is `10039007717`, digest `sha256:67a3c704cccb21dd254c91a774d91ce35d327ec06e1cfcdd1ebbd720614235f3`; results/header evidence `10039007339`, selected evidence `10039006960`.

Final exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result-card/drawer states and all ten header widths. No supplier request or lead was sent. Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area, owner visual acceptance and the pre-existing browser-default result-only retry styling remain deferred. Audit: `docs/project/search3-lean-header-and-selected-description.json`.

Next: audit the complete 4295-byte `header-current-site.js` runtime. The Search3 hero target is hidden by the current form owner and legacy mobile-menu nodes are absent, but server-rendered phone/navigation and any remaining native-header behavior must be proved before whole-owner exclusion. Preserve the full legacy route and require focused header behavior plus exact artifact CI.

## S3_LEAN_HEADER_RUNTIME — checked release, 2026-09-08

Source PR #1538 / `9a7ff81f041cf13d643deaf2eb73d57bc51e871b`; checked code release `602124449fbe8595b86e55119304df355d82cc0e`. Search3 no longer loads the complete 4295-byte `header-current-site.js` runtime. The full legacy route retains the unchanged runtime and its compatibility global.

The current header renders the phone value/link and ordered desktop/mobile navigation on the server. Its mobile menu is native `details`/`summary`; focused browser coverage now opens and closes it by clicking the summary at every applicable width. The obsolete `.at-site-header`/`.at-mobile-menu` mutation paths target markup absent from Search3, and the current Search3 owner already hides the old product hero. No compensation runtime was added.

Loaded raw is **556977 → 552682 bytes (−4295)**: scoped shared JavaScript 199300→195005; shared CSS and the eight Search3 assets remain 186118 and 171559. Exact emitted endpoints plus the eight independent files gzip is **124085 → 122720 (−1365)**.

The initial exact run `34182323940` correctly failed because the selected-tour guard still combined a pre-#1536 generated CSS baseline with a runtime base that no longer contained the retired injected theme. The geometry baseline was advanced to final checked #1536 source `cc35208f740cd957ca450f7b19fa2acfe6af7e6d`; no assertion or compared property was removed. Final Security `34182736622` and exact artifacts `34182736594` / `34182975846` passed.

Reusable final artifact `10039560348`, digest `sha256:ca5d0f292acc46e75bbca8cc9a774d5a39fd055050ac889106af54673f731f45`; selected evidence `10039559706`, digest `sha256:dbeca28e0b285d4a37f4b4c45bfe23f6794a87e7687238e1efc37549b3b7b027`; results/header evidence `10039560064`, digest `sha256:05bc19d1dfa73de02a581c3c98afdb456d723facca6944a74a6fdc628e3b9b17`. Final exact Chromium retained 12 selected states, 30 entry states, 12 result states and the header matrix at 375/520/521/768/769/1024/1025/1100/1101/1440. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area, owner visual acceptance and the pre-existing browser-default result-only retry styling remain deferred. Audit: `docs/project/search3-lean-header-runtime.json`.

Next: audit the complete 2526-byte `selected-tour-return-v1.js` owner against `tour-controller-v4.js` and the current selected/results handoff. Preserve source-button or results fallback focus, reveal/scroll behavior, `aria-hidden` state and `v2:tour-returned`; retire only through one current owner after focused return lifecycle and exact browser CI.

## S3_LEAN_SELECTED_RETURN_RUNTIME — checked release, 2026-09-08

Source PR #1540 / `dddbaa5d9ead9b3178766a44eeba329418b4e497`; checked code release `269233aba77ac17817c810256cd1c84bfc62dd78`. Search3 no longer loads the complete 2526-byte `selected-tour-return-v1.js` owner. The full legacy route retains the unchanged file and `V2SelectedTourReturnV1` compatibility global.

The current `tour-controller-v4.js` now captures the exact initiating tour action and id, recovers an equivalent action after result rerender, hides the selected root visually and through `aria-hidden`, and returns focus with reveal/scroll. If the source action is gone, `#results` receives temporary focus and its added tabindex is removed on blur. Both `.back-results` and `.lead-success-back` dispatch the existing `v2:tour-returned` detail. Selecting another tour clears stale `aria-hidden` before loading.

Loaded raw is **552682 → 551591 bytes (−1091)**: scoped shared JavaScript 195005→193914; shared CSS and the eight Search3 assets remain 186118 and 171559. Exact same-method gzip recount is **122724 → 122454 (−270)**. This corrects the preceding stored gzip absolute by +4 bytes; the package delta is measured on both exact trees with one method.

Security `34184579113` and exact artifacts `34184579101` / `34184702799` passed. Reusable final artifact `10040123280`, digest `sha256:6b40ec5faf8e19e1f5cfe20f38e149fbb7c4f8cabffdeab4c06f8ad0b7da3542`; selected evidence `10040122833`, digest `sha256:5e44b0c51a1d5bd57514ece700ebac4b85409df2b4a42030a073f5f56d8bb23e`; results evidence `10040123068`, digest `sha256:2ceb6a6c4438c720955be9ded8cf47c8bae00cfccda7ca412a0a796d1bd2cb30`.

Focused deterministic coverage passed original source, rerendered same-tour source, results fallback, lead-success return and temporary tabindex cleanup. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area, owner visual acceptance and the pre-existing browser-default result-only retry styling remain deferred. Audit: `docs/project/search3-lean-selected-return-runtime.json`.

Next: audit the complete 1855-byte `flight-empty-recovery-v1.js` owner against the current `selected/flight-fallback.js` owner. Retain the friendly no-flight message and exactly one delegated retry button, preserve fallback review/lead and price behavior, and require empty→retry→recovery plus exact selected browser CI. Do not take the larger `price-confidence-v1.js` before this lower-risk presentation-only owner.

## S3_LEAN_FLIGHT_EMPTY_RUNTIME — checked release, 2026-09-08

Source PR #1542 / `0301f3bf9c2ab77d7f837e86f6915c344f9f87fe`; checked code release `dac375ad4c0f4d366cab3c8a1be4855f3ad6dfcd`. Search3 no longer loads the complete 1855-byte `flight-empty-recovery-v1.js` runtime. The full legacy route retains the unchanged file and `V2FlightEmptyRecoveryV1` compatibility global.

The current selected-flow fallback owner now upgrades the empty-flight copy and creates exactly one delegated `.load-flights.secondary[data-tid]` action from the current tour id. Existing controller delegation performs the retry. The current owner does not decorate `.flight-error`, does not alter flight variants, and preserves the no-flight review/lead handoff. No Tourvisor/API, price, payload or lead transport code moved.

Loaded raw is **551591 → 550542 bytes (−1049)**: scoped shared JavaScript 193914→192059, shared CSS remains 186118, and the eight generated Search3 assets are 171559→172365 (+806 retained behavior). Exact emitted endpoints plus eight independent files gzip is **122454 → 122288 (−166)**.

Security `34186013087` and exact artifacts `34186013106` / `34186184019` passed. Reusable final artifact `10040600906`, digest `sha256:fa390f4dc397d04cf7a9c940d8051d3d416e309c2892c91bab14c3d6c43cc73c`; selected evidence `10040599919`, digest `sha256:3cf61cca1b18f87ae2ef4a1a980c5d8c4aa2e5900406160137ed15f21a5a49cb`; results evidence `10040600381`, digest `sha256:b7619849afd13b93d6913481889e1007dd73cd27f510dd7b19644c2a02c3fabf`.

The required source suite verifies friendly copy, current-tour id, one retry across repeated sync, error exclusion and fallback navigation. Exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 header states without external API or lead requests. The dedicated empty→retry→recovered browser file was updated but is only wired to the main-target flight workflow, so its execution remains deferred rather than claimed green.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Physical Safari/safe-area, live current-source verification, manual screenshot review, dedicated empty→recovery Chromium and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-flight-empty-runtime.json`.

Next: audit the complete `price-confidence-v1.js` runtime. Preserve truthful totals, pending-price states, selected-flight arithmetic and all protected payload/lead contracts; exclude it only if focused price snapshots demonstrate that the legacy layer is presentation-only.

## S3_LEAN_PRICE_CONFIDENCE_RUNTIME — checked release, 2026-09-08

Source PR #1544 / `8a5c9c895b23cd6a068dfcf5b40dcbf8141c3759`; checked code release `90124c5440eecbc46b8aeb104614800e3cc54c80`. Search3 no longer loads the complete 2399-byte `price-confidence-v1.js` runtime. The full legacy route retains the unchanged runtime and `V2PriceConfidenceV1` compatibility global.

The runtime only created a second explanatory note. Current owners already preserve the protected truth: `pricePending` uses the base tour price, a confirmed flight replaces it with one normalized total across the selected header, mobile bar and booking summary, and the booking summary states that a manager confirms final price and flight details before payment. `flight-price-sync-v1.js`, `unpriced-flight-price-reset-v1.js`, price arithmetic and lead payload/transport were not changed. The 99-byte dead review selector for the retired node was removed.

Loaded raw is **550542 → 548044 bytes (−2498)**: scoped shared JavaScript 192059→189660 and eight Search3 assets 172365→172266; shared CSS remains 186118. Exact emitted endpoints plus eight independent files gzip is **122288 → 121807 (−481)**.

Security `34188350663` and exact artifacts `34188350666` / `34188528176` passed. Reusable final artifact `10041387959`, digest `sha256:99faf7dd6e12c92697fab64c2729721075991a08c69637580b0b43e32caf41be`; selected evidence `10041387331`, digest `sha256:da267f7de034dfa0b67b89a5ea854cfe5898a4c5943012010c53a62e7cb78ae8`; results evidence `10041387639`, digest `sha256:bd88065902147240b66a13c5c720c0c4e5f8b2a6377c297122477be8b41fa654`.

Focused source checks cover base, pending and confirmed totals plus the retained booking confirmation copy. Exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 header states without external API or lead requests. The first exact run correctly detected the intentional two-node note removal; the runtime baseline was advanced to the checked source while keeping every compared property, state and width.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-price-confidence-runtime.json`.

Next: audit the complete `results-filter-autorefresh-v1.js` owner against current instant/local DS2 result filters. Preserve loaded-result completeness guards, zero-result recovery and explicit search submission; do not keep an automatic Tourvisor refresh path in Search3 unless a focused contract proves it is required.

## S3_LEAN_FILTER_AUTOREFRESH_RUNTIME — checked release, 2026-09-08

Source PR #1546 / `c7118489fe450ae7850f058206bb20031e6e3004`; checked code release `401c095df33a9cfd440534d48d72ff5a5e8a4c89`. Search3 no longer loads the complete 2714-byte `results-filter-autorefresh-v1.js` runtime. The full legacy route retains the unchanged file and `V2ResultsFilterAutorefreshV1` compatibility global.

Current Search3 result facets remain instant and local, with completeness guards, zero-result recovery and reset intact. Changes to primary search parameters still mark results stale and are submitted explicitly through the current stale-results action and `V2SearchLifecycle.submit()`. The retired legacy owner only scheduled a second supplier search 650 ms after old-form filter changes; no replacement network path was added.

Loaded raw is **548044 → 545330 bytes (−2714)**: scoped shared JavaScript 189660→186946; shared CSS and the eight Search3 assets remain 186118 and 172266. Exact emitted endpoints plus eight independent files gzip is **121807 → 121305 (−502)**.

Security `34189315892` and exact artifacts `34189315891` / `34189452118` passed. Reusable final artifact `10041716679`, digest `sha256:bc39e469877ee9b24721262975490a1d9c6dca8c2ea23d592d558512c92eb384`; selected evidence `10041715945`, digest `sha256:1715d7b6a6ed15922830f774e5e1736899ff6d6fc632fafb4781d849cfb86f2f`; results evidence `10041716298`, digest `sha256:be83f6de2980095d3a23026ff785d0c341e4239e97e5279a9abeef25f85a1e95`.

Focused source checks cover instant/local facets, completeness guards, zero-result recovery, explicit stale-results submission and unchanged legacy retention. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-filter-autorefresh-runtime.json`.

Next: audit `results-depth-v1.js` against `search-lifecycle-v6.js`. The lifecycle already renders 100 results before emitting `v2:search-complete`; preserve progressive 25-result refreshes and the final 100-result render, and remove the old post-completion owner only if focused coverage proves its second request is redundant.

## S3_LEAN_RESULTS_DEPTH_RUNTIME — checked release, 2026-09-08

Source PR #1548 / `e3c6fe0615e64c9117faa0d1895c35a09fe9d7c4`; checked code release `98599ae41594d809af24ef767e7586a9706f8ac6`. Search3 no longer loads the complete 1346-byte `results-depth-v1.js` runtime. The full legacy route retains the unchanged file and `V2ResultsDepthV1` compatibility global.

The current `search-lifecycle-v6.js` remains the single network owner: it fetches progressive 25-result batches while search runs, then fetches and renders 100 results before emitting `v2:search-complete`. The retired owner listened to that completion event and issued the same 100-result request again. No replacement request or behavior code was added.

Loaded raw is **545330 → 543984 bytes (−1346)**: scoped shared JavaScript 186946→185600; shared CSS and the eight Search3 assets remain 186118 and 172266. Exact emitted endpoints plus eight independent files gzip is **121305 → 120947 (−358)**.

Security `34190365275` and exact artifacts `34190365259` / `34190514822` passed. Reusable final artifact `10042067449`, digest `sha256:3afd81112c9f06803248747e27edb48d8d5de6e44256b15db06f08ce0fdb1bc4`; selected evidence `10042066429`, digest `sha256:c4bee1faaea696858c5bce1f1378dab1ae319bf773749f7a8e78cbbf394cd209`; results evidence `10042066943`, digest `sha256:9bebbde6699e8935680a29eaa3f34e0d0d350c47fe1114856082bff03eaad206`.

Focused source checks assert one final 100-result request, completion only after its render, retained progressive limit 25 and absent Search3 compatibility global. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-results-depth-runtime.json`.

Next: audit `results-local-filters-v1.js` against `ds2-results-filters.js` and `search-lifecycle-v6.js` as one substantial owner-consolidation package. Preserve every unique form-narrowing, result-facet, completeness, catalog-refresh and explicit supplier-search contract; do not remove working filter behavior for size alone.

## S3_LEAN_LOCAL_FILTER_OWNER — checked release, 2026-09-08

Source PR #1550 / `4cd7961bb85b3654a1c2cfae32d78747deb5c888`; checked code release `1ed8fec4b87792b5274d7a61ba4f5a1930cace89`. Search3 no longer loads the complete 5765-byte `results-local-filters-v1.js` runtime. The unchanged runtime and `V2ResultsLocalFiltersV1` global remain in the full manifest used by `/poisk-turov-old/`.

The unique form contract moved into the current `ds2-results-filters.js` owner: stars, rating, price bounds, scalar/object region and subregion IDs, region-dependent catalog refresh, tour pruning and minimum-price recomputation. Changes that would broaden the supplier snapshot still reach the current explicit lifecycle submit path. Both form narrowing and DS2 facets now filter one canonical source list, preventing a locally rendered subset from being recaptured as a second owner’s source and preserving the Search3 zero-result bridge.

Loaded raw is **543984 → 542124 bytes (−1860)**: scoped shared JavaScript 185600→183740; shared CSS and the eight Search3 assets remain 186118 and 172266. Exact same-method endpoint gzip delta is **−438 bytes**, giving **120947 → 120509** from the preceding exact checkpoint. The removed legacy runtime is 5765 bytes and the retained behavior adds 3905 bytes to the current shared owner; only the net loaded saving is reported.

Security `34192366116` and exact artifacts `34192366178` / `34192605097` passed. Reusable final artifact `10042768273`, digest `sha256:6b1e966e07714ccff226269fd0ee8099288b34e3ffb87e1f93c9ca764cf588df`; selected evidence `10042767008`, digest `sha256:ede37e35a92a7ca573371a9d28776702f7fc1d35191ac07842d092e4061ca583`; results evidence `10042767642`, digest `sha256:6a1cb432427f5f28337440ed6b3eaac68c153ae70a701253cc7bf4550f2ec39f`.

Focused source checks cover stars narrow/clear, rating and price bounds, region clearing subregion, one catalog refresh, tour-price recomputation, unsafe broadening handoff, DS2 facet zero/recovery and old-route retention. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-local-filter-owner.json`.

Next: audit the complete `design-v1.css` and `enhancements.css` legacy presentation layers against current Search3 private/DS2 owners. Remove a whole layer only if focused geometry proves it has no unique Search3 contract; keep both layers on the old route and avoid micro-removals.

## S3_LEAN_DESIGN_V1_LAYER — checked release, 2026-09-08

Source PR #1552 / `802e1adff7395fb817563512f080ef2d798b0ef6`; checked code release `f0f39e91f037343fa3aa30a4d5953f01ca42cf9f`. Search3 no longer loads the complete 7527-byte `design-v1.css` presentation layer. The unchanged layer and full manifest order remain available to `/poisk-turov-old/`.

Exact Chromium deliberately drove the repair. Five red runs (`34193483597`, `34193997477`, `34194429152`, `34195148577`, `34195705377`) exposed only the live selected-tour and mobile-entry slice: Back spacing, flight route/arrow geometry, lead consent/CTA dimensions, the 10px mobile form grid rhythm and 49px native fields. These rules now live in current Search3 owners; the rest of the legacy layer remains excluded. The red runs are recorded as red, not described as successful.

Loaded raw is **542124 → 535608 bytes (−6516)**: scoped shared CSS 186118→178591, shared JavaScript remains 183740, and the eight generated Search3 assets are 172266→173277 after retaining 1011 bytes of current-owner geometry. Carried same-method gzip is **120509 → 119180 (−1329)**.

Security `34196012323` and exact artifacts `34196012398` / `34196175190` passed. Reusable final artifact `10044015653`, digest `sha256:ee6824caa70a9aaabf48720bc517c475e28d83e0f7fb8b1159f116718d4b4514`; selected evidence `10044014763`, digest `sha256:95c9ce657e50f82d9477d22987c77f25061b38297ee47f4245e5eb83f69f8143`; results evidence `10044015196`, digest `sha256:685945e258b13936a05989c0f58dd806b23297a553ba8a75811bc328e7985266`.

Exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-design-v1-layer.json`.

Next: audit the complete `enhancements.css` presentation layer. Keep it unchanged on the old route and attempt only a whole-layer, reversible Search3 exclusion with focused geometry-driven repair; do not create micro-removal PRs.
## S3_LEAN_TOUR_DESIGN_LAYER — checked release, 2026-09-08

Source PR #1556 / `87190b028bb628c7b831c65e11d5ea8d54d8639c`; checked code release `74bb11cca818e111503f888a99b3a61e306c1186`. Search3 no longer loads the complete 5269-byte `tour-design-v1.css` presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

Exact Chromium drove the repair. Three red runs (`34198929535`, `34199068139`, `34199526115`) exposed malformed literal line breaks and then the genuinely live selected-tour/mobile-flight geometry. Only a 670-byte slice was retained in the current `selected-tour-ux.css` owner: selected overflow and price alignment, selected/lead positioning and rhythm, and mobile flight segment/title/route/baggage presentation. The red runs remain recorded as red.

Loaded raw is **523917 → 519318 bytes (−4599)**: scoped shared CSS 166900→162301, shared JavaScript remains 183740, and the eight generated Search3 assets are 173330→174000 after retaining current-owner geometry. Carried same-method gzip is **117126 → 116431 (−695)**.

Security `34199833477` and exact artifacts `34199833496` / `34200268121` passed. Reusable final artifact `10045565727`, digest `sha256:738136f729747089b59cb65d9e61a018b53f6c46737959d62990baf18ed21a23`; selected evidence `10045564964`, digest `sha256:903a62215194e12a3bb5bf7394d0552d0aca62aea855cd74f791c8b1815a1e48`; results evidence `10045565347`, digest `sha256:95af06173ab0262b92f0d25bc54eed81be45f52ac45a931adf0156e7cf97a821`.

Exact Chromium retained selected detail/review/lead, result and entry geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-tour-design-layer.json`.

Next: audit the complete `hotel-details-design.css` presentation layer. Keep it unchanged on the old route and exclude it from Search3 only if focused result/selected coverage proves the inline-detail owner is obsolete.

## S3_LEAN_HOTEL_DETAILS_LAYER — checked release, 2026-09-08

Source PR #1558 / `60223b579d05456d782722ceead4e755a22167d9`; checked code release `4dc61c0154d4129a9d514121120e48692c82cc97`. Search3 no longer loads the complete 4640-byte `hotel-details-design.css` presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

The layer belongs to the old `.hotel-actions`, `.hotel-info-toggle`, `.hotel-inline-detail` and inline gallery/facts surface. Current Search3 already hides the retired actions and inline detail, while its hotel CTA, expanded tour rows, selected tour and active room details remain owned by current modules. No replacement CSS or JavaScript was added.

Loaded raw is **519318 → 514678 bytes (−4640)**: scoped shared CSS 162301→157661; shared JavaScript and the eight generated Search3 assets remain 183740 and 174000. Carried same-method gzip is **116431 → 115816 (−615)**.

Security `34202647856` and exact artifacts `34202647875` / `34202904621` passed. Reusable final artifact `10046592943`, digest `sha256:d81ea21c4ec39cda85b576bc745108376c6c9bdf695e1db8709ed5194bb2fd24`; selected evidence `10046591698`, digest `sha256:2d028c44e64423351193a4986726b143c9844d298b37ed343bd4d76c1cc10f8f`; results evidence `10046592310`, digest `sha256:2acf4dd85f72f4a039ceef69c521264c30fc743f2843f781edb0e70cf9c70d42`.

Both exact Chromium runs retained selected detail/review/lead, result and entry geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-hotel-details-layer.json`.

Next: audit the complete `results-experience-v1.css` presentation layer as one reversible package. Preserve active Search3 result geometry in current owners, keep the layer unchanged on the old route, and do not remove active `room-details.css` for size alone.

## S3_LEAN_RESULTS_EXPERIENCE_LAYER — checked release, 2026-09-08

Source PR #1561 / `886ae5f7b1cbe886ad2bc047c2785edebd343d88`; checked code release `b9d4240871c1194187022ea0b3f80a16ae0f4435`. Search3 no longer loads the complete 8656-byte `results-experience-v1.css` presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

Current Search3 owners already preserve result tools, hotel card/photo, hotel facts, package disclosure, expanded tour rows, price/CTA and actionable empty state. Both exact Chromium runs passed without adding any replacement CSS or JavaScript.

Loaded raw is **514678 → 506022 bytes (−8656)**: scoped shared CSS 157661→149005; shared JavaScript and the eight generated Search3 assets remain 183740 and 174000. Carried same-method gzip is **115816 → 114104 (−1712)**.

Security `34204676344` and exact artifacts `34204676400` / `34204924813` passed. Reusable final artifact `10047392842`, digest `sha256:bb40958fb09d03030f6dccd52cb8609579fba06a69e99908d3c2ee69a33d7a2b`; results evidence `10047392372`, digest `sha256:4d1aed11ba7554daa3bebc58204d5f1338d73f35fb7450fb3adb39388d2c8eb3`; selected evidence `10047392000`, digest `sha256:21998738db112adc43866985216ec8ff7a573550a3205658a9c9d9d481460612`.

Exact Chromium retained selected detail/review/lead, result, expanded-package and entry geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-results-experience-layer.json`.

Next: audit the complete `anytour-brand.css` presentation layer against current Search3 owners as one reversible package. Preserve brand tokens and active geometry, keep the layer unchanged on the old route, and do not remove active `room-details.css` or `search-states-design.css` merely for size.

## S3_LEAN_ANYTOUR_BRAND_LAYER — checked release, 2026-09-08

Source PR #1563 / `775e7279f1f6cd2d923136451a1ea5594b69e282`; checked code release `d42691a6797f58abb5da7aca730d2125d57b425f`. Search3 no longer loads the complete 9722-byte `anytour-brand.css` legacy V2 presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

The retired file owned its own hero/brand/form/card skin and private `--anytour-*` variables; focused source inspection found no consumers of those variables outside that same file. Current Search3 owners preserve its form, result cards, selected/lead states and shared site header/logo. Active `room-details.css` and `search-states-design.css` remain loaded. No replacement CSS or JavaScript was added.

Loaded raw is **506022 → 496300 bytes (−9722)**: scoped shared CSS 149005→139283; shared JavaScript remains 183740 and the exact artifact-proven eight generated Search3 assets remain 173277. This corrects the previously transcribed 174000 subtotal without changing the 496300 loaded total. Carried same-method gzip is **114104 → 112163 (−1941)**.

Security `34205833337` and exact artifacts `34205833396` / `34206067387` passed. Reusable final artifact `10047845216`, digest `sha256:7111070f1ff14b5c9be8f20292219c3a752cb1dd8aef9b8c9dbdadb9d7014da6`; results evidence `10047844646`, digest `sha256:28dc1e34ebe754433ecb98ac9565dd2901db8d09fef698e83126b6d2b69edb82`; selected evidence `10047844074`, digest `sha256:0dd0c4cc01227efb3693bda83a791956ba230793a7a4a1b27fa797cce1e17306`.

Exact Chromium retained selected detail/review/lead, result, expanded-package, entry and shared header/logo geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-anytour-brand-layer.json`.

Next: audit the complete `product-shell-v1.css` presentation layer against the current Search3 page intro and shared site shell. Preserve header/footer/logo and active route geometry, retain the full old-route layer, and do not retire active `room-details.css` or `search-states-design.css` merely for size.

## S3_LEAN_PRODUCT_SHELL_LAYER — checked release, 2026-09-08

Source PR #1568 / `9bcaaae15cc287f929a9fb8c4a42a03307d81b47`; checked code release `c46d6fab0f7d8ecfd43d6fc46acdf446a742b1db`. Search3 no longer loads the complete 10197-byte `product-shell-v1.css` legacy presentation layer. The unchanged file remains in the full manifest used by `/poisk-turov-old/`.

The retired layer owned the obsolete `.at-site-header*`, nav/mobile-menu, product hero and old `.primary-search-flow` grid. Current Search3 uses `.at-global-header`, hides and replaces the old hero, and owns its shell, entry, results, selected and lead surfaces. Exact Chromium identified the retained slice: hidden legacy hero before initialization, 52px mobile/tablet shell bottom spacing, Aeroport font and the inherited ink/soft values. Those contracts add 232 compiled bytes to the current Search3 base; no other legacy rule moved.

Loaded raw is **496300 → 486335 bytes (−9965)**: scoped shared CSS 139283→129086; shared JavaScript remains 183740; the eight public assets are 173277→173509. Exact artifact `10047845216` proves the prior eight-asset subtotal was 173277 rather than the transcribed 174000; its 496300 loaded total is unchanged. Same-method endpoint/eight-file gzip delta is **−1978 bytes**, carrying **112163 → 110185**.

Security `34208217683` and exact artifacts `34208217590` / `34208410446` passed. The first exact run `34207759220` is retained as red: node counts and geometry were unchanged, but it exposed the font, ink and soft-token visual drift that was then repaired. Reusable final artifact `10048797611`, digest `sha256:aad6972854eb311400c17cc831f17135717bf610838f0e205bc3de9888141f5f`; selected evidence `10048796241`, digest `sha256:0db90db602f31f76f115fc2e789a0a447fb01c05765812562c0fd06dce2500b1`; results evidence `10048796853`, digest `sha256:35e247ede326b51f4192cd6f3d8b078b200d50f6dca7252a8acc936f5f28e1ae`.

Final exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 shared-header states without external API or lead requests. Header/footer/navigation and canonical logo are unchanged. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, native/nesting contracts are preserved.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-product-shell-layer.json`.

Next: audit the complete `search-header-shared-shell-v1.css` layer against the current `.at-global-header` and Search3 base/entry owners. Preserve header/footer/logo/navigation and active route geometry, and keep the full layer on the old route.

### S3_LEAN_SHARED_HEADER_SHELL_LAYER — CHECKED RELEASE, NOT PUBLISHED, NOT PRODUCTION (2026-09-08)

Source PR #1573 / `2b6afe8602587ff6e45e13127129daec4187e22d`; checked release
`e887eef45e8a23560d5f5138c0b38908f0be7ca3`. Search3 no longer loads the complete
`search-header-shared-shell-v1.css` layer. All of its selectors belong to the legacy
`.at-site-header`, `.at-site-*` and `.at-mobile-menu-*` families. Current Search3
renders `.at-global-header`, whose logo, navigation, mobile menu and geometry remain
owned by `site-header-v2.php/css`. The full old-search manifest retains the 4,455-byte
file; no compensating Search3 CSS was required.

Loaded raw is **486335 → 481880 bytes (−4455)**: scoped shared CSS
**129086 → 124631**, shared JavaScript remains **183740**, and the eight generated
Search3 public assets remain exactly **173509**. Exact same-method endpoint plus
eight-independent-file gzip is **110185 → 109432 (−753)**.

Local source build/check, 13 source-build tests, 45 presentation tests (one local
PHP-dependent skip) and the owner-priority validator passed. Security
`34209958561`, initial exact artifact `34209962002` and ready repeat
`34210172714` succeeded. Reusable artifact `10049486332`, digest
`sha256:879ab7e79da7b10782b64d55273028dce5efbddec0f77a0fd26b6107502c3d5c`.
Results/header evidence `10049485574`, digest
`sha256:1da978383f64e0401a269ca7cd312033610c6c2743a3bbe66999a1349d2f49e5`;
selected evidence `10049484807`, digest
`sha256:1d4a362e8ed0255888f343830f540337abb913e5380cbcb88aca2d231cbf24f7`.
Exact source/PHP/path/presentation/isolation guards and Chromium header, entry,
results and selected-tour geometry passed without external API or lead requests.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not
published. Main and production were not changed. Live current-source visuals,
representative screenshot inspection, physical Safari/safe-area and owner acceptance
remain deferred. Audit: `docs/project/search3-lean-shared-header-shell-layer.json`.

Next: audit complete `ds2-search-intro-v1.css` and
`ds2-selected-tour-convergence-v1.css` layers against current Search3 owners.
Retire only a proven whole layer or bounded family; preserve active entry, results,
selected-tour, price, URL/payload, Tourvisor/API, lead, analytics, logo and browser
contracts.

### S3_LEAN_SELECTED_CONVERGENCE_LAYER — CHECKED RELEASE, NOT PUBLISHED, NOT PRODUCTION (2026-09-08)

Source PR #1575 / `b86a5eeb7396134b08b2eb2ced2eddd717a0f882`; checked release
`13df507f22e6e56c8320b944cb3c531ad8a796b9`. Search3 no longer loads the complete
10,798-byte `ds2-selected-tour-convergence-v1.css` layer. The full old-search
manifest retains the unchanged file.

The first exact run correctly exposed the donor's remaining live presentation slice.
Current Search3 owners now explicitly preserve selected shell sizing and box sizing,
price-label tracking, facts and disclosure heights, section typography, flight-choice
grid placement, route radius and mobile lead-input radius. The final desktop mismatch
was the inherited `.045em` price-label tracking: without it the auto price column
grew 12.6px. No business logic moved.

Loaded raw is **481880 → 472663 bytes (−9217 net)**: scoped shared CSS
**124631 → 113833**, shared JavaScript remains **183740**, and the eight generated
public assets are **173509 → 175090** (+1581 retained current-owner CSS).
Carried same-method endpoint plus eight-file gzip is **109432 → 107843 (−1589)**.

Security `34215439259`, exact source run `34215439223` and ready repeat
`34215627824` passed. Reusable artifact `10051687021`, digest
`sha256:6b813f3bc74c55ec3e9a4ff3acfab85edd1560b4f697a0400f09bc4dd7be6f65`.
Results/entry evidence `10051686606`, digest
`sha256:178b841171f92931285942b5968c722a71e5690edf4b7bab8f02b43a105ca351`;
selected evidence `10051686211`, digest
`sha256:9c16de58147951a5a0a4291bc22b1aca3aded16df1a722d0aea49896a87812ef`.

Exact source/PHP/path/presentation/isolation guards passed. Chromium retained all
12 selected detail/review/lead states at 375/760/1000/1440 and the guarded
results/entry states, with no external API or lead request. Eight public paths,
price arithmetic, URL/payload, Tourvisor/API, lead transport/mapping,
Metrika/analytics/goals, logo and native-browser/nesting contracts are preserved.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not
published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not
changed. Live current-source visuals, representative manual screenshot inspection,
physical Safari/safe-area and owner acceptance remain deferred. Audit:
`docs/project/search3-lean-selected-convergence-layer.json`.

Next: retire the complete `selected-tour-layout-guard-v1.css` and audit
`br3-control-consistency-v1.css` in one substantial reversible package. Preserve
current progress/error controls, focus/touch targets, selected return/description
geometry, and keep both legacy files unchanged on the old route.

## S3_LEAN_CONTROL_GUARDS_LAYER — checked release, 2026-09-08

Source PR #1580 / `5b07409fdacb9b5cd8912f0c117eabd9e5378172`; checked code release `4a8b279deda40075817013653bf03dcd51f927eb`. Search3 no longer loads the complete 4357-byte `br3-control-consistency-v1.css` and 916-byte `selected-tour-layout-guard-v1.css` layers. Both unchanged files remain in the full manifest used by `/poisk-turov-old/`.

Current owners retain only active secondary/progress/retry/filter controls, focus and reduced-motion behavior, direct-tour CTA state, hotel-description disclosure, and the tablet/phone selected-head and price geometry. The first exact run `34218075137` correctly exposed an over-specific retained selector that moved review content at 760px and left the phone review grid template wrong. The selector was reduced to donor specificity and the phone one-column head restored; no guard or compared property was removed.

Loaded raw is **472663 → 470476 bytes (−2187 net)**: scoped shared CSS **113833 → 108560**, shared JavaScript remains **183740**, and the eight generated public assets are **175090 → 178176** (+3086 retained current-owner CSS). Carried same-method gzip is **107843 → 107522 (−321)**.

Security `34220520877`, exact source run `34220520872` and ready repeat `34220737644` passed. Reusable artifact `10053671415`, digest `sha256:101937e4872190abb5126f9280f6103596918ceb1d20def4d2de5afaf2516d08`. Results/entry evidence `10053670660`, digest `sha256:c6caf779e233c7b3220d9d21a7b077947a5b6c91e50addae0ee8e405141c8bc8`; selected evidence `10053669978`, digest `sha256:35b64a1363a68353f446656709ea1f4436e60d86e67f28a50d8109804ab30931`.

Exact source/PHP/path/presentation/isolation guards passed. Chromium retained all 12 selected detail/review/lead states at 375/760/1000/1440 and the guarded results/entry states, with no external API or lead request. Eight public paths, price arithmetic, URL/payload, Tourvisor/API, lead transport/mapping, Metrika/analytics/goals, logo and native-browser/nesting contracts are preserved.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, representative manual screenshot inspection, physical Safari/safe-area and owner acceptance remain deferred. Audit: `docs/project/search3-lean-control-guards-layer.json`.

Next: audit `search-filters-ux-v1.css` and `selected-tour-ux.css` as whole-owner candidates. Retire only the stronger substantial candidate after compactly preserving active Search3 lifecycle/geometry in current owners; keep the old route unchanged.


### S3_LEAN_SEARCH_FILTER_SKIN — checked release, not published

[#1584](https://github.com/pyatkoff/poisk-turov-test/pull/1584) removes the complete legacy `search-filters-ux-v1.css` layer from the Search3 scope while leaving the file unchanged in the full manifest for `/poisk-turov-old/`. The current Search3 form retains native date/night controls, quality controls, mobile advanced filters and the tourist popover. The only retained dependencies are a scoped pre-init hidden-wrapper rule and child-age spacing; the two tourist selects now explicitly shed the legacy visually-hidden class, `aria-hidden` and negative tab order.

Loaded shared CSS + shared JS + eight public Search3 assets: **470,476 → 458,693 raw bytes (−11,783 B)**. Shared CSS: 108,560 → 96,535 B; shared JS: 183,740 B unchanged; eight public assets: 178,176 → 178,418 B (+242 B retained current ownership).

Implementation source `a201f645bc614bdab65ee3008ebfd152a70b78af`; checked release `f1a7fe122d878918157cb27f4c83165569f0638e`. Security run [34222910866](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34222910866) and exact artifact/browser run [34222910847](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34222910847) passed. Artifact `10054529522`, digest `sha256:84a7c55e4612fbd9395f5b315477945ccee8f9f0d1ee44ebbb9baf3031dbcccd`; results/entry geometry `10054528862`; selected geometry `10054528235`. No external API or lead request was made.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, physical Safari/safe-area and manual screenshot inspection remain deferred.

Next: retire `selected-tour-ux.css` as one reversible Search3-only package, retaining only confirmed detail, flight, room-disclosure and lead-state contracts in current owners. Following the owner’s test-branch guidance, use one Security + exact artifact/smoke pass per substantial package and do not repeat the same CI after ready transition.


### S3_LEAN_SELECTED_TOUR_SKIN — experimental test release, not published

[#1588](https://github.com/pyatkoff/poisk-turov-test/pull/1588) removes the complete 14,190-byte `selected-tour-ux.css` layer from Search3 only. The unchanged full manifest still serves it to `/poisk-turov-old/`. Confirmed description/room disclosure, flight recovery/mobile route and lead-state rules remain in current Search3 owners; old decision summaries, repeated checkout skin and legacy lead-success/trust presentation were not copied.

Loaded shared CSS + shared JS + eight public assets: **458,693 → 447,863 raw bytes (−10,830 B)**. Shared CSS: 96,535 → 82,345 B; shared JS: 183,740 B unchanged; eight public assets: 178,418 → 181,778 B (+3,360 B retained current rules).

Implementation `33395ab79eb4148c4c440edb0ea1bcb2fddd48e1`; experimental release `20df2019ea2948ef725526bc896ca290fdd154ab`. Security [34223970798](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34223970798) passed. Exact artifact/browser [34223970803](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34223970803) is **red** at selected-tour pixel equality: removal changed legacy spacing/skin in all 12 detail/review/lead snapshots. It did not produce a reusable release artifact. Evidence `10054929764`, digest `sha256:71cd2ce050e3d5a444a981905ff88464241888d2023dfd44794a5c17bc495d03`, records equal DOM node counts for every state and no horizontal overflow. This is recorded as an owner-authorized visual experiment on the test release, not as green CI or a checked release. Last fully checked release remains `f1a7fe122d878918157cb27f4c83165569f0638e`.

Preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Manual inspection of changed selected-tour visuals, live current-source interaction and physical Safari remain deferred.

Next: continue an independent whole-layer audit of `app.css` / `search-states-design.css`. Do not restore retired selected-tour pixels unless an actual functional regression is confirmed. Keep the reduced owner-requested policy: one Security plus one build/smoke run per substantial source package, no automatic ready-transition repeat.

### S3_LEAN_SELECTED_TOUR_AND_RUNTIME_OWNERS — checked release, not published

The experimental #1588 result was not left red. Its retained DOM was intact, but exact run `34223970803` exposed omitted live flight/detail/lead geometry. #1590 restores only that active slice in current owners; `selected-tour-ux.css` stays completely excluded from Search3 and unchanged for `/poisk-turov-old/`. Security `34225808267` and exact artifact/browser `34225808272` passed all 12 detail/review/lead states at 375/760/1000/1440. Reusable artifact `10055697968`, digest `sha256:374ddaf86efdfad49a82d46c31d17bdb37d20428ae58e67f1d2586462d6da4f6`; selected evidence `10055697041`, digest `sha256:53de20c17c1ef2f1b025ae225a7c867069484d999b00b9c48b160cc0312a8415`.

In parallel, #1587 retires the standalone `flight-price-presentation.js` and `filter-rail.js` runtimes. Their live display-only decimal correction and bounded zero-result bridge now run inside the existing selected/results schedulers; authoritative `v2/flight-price-sync-v1.js`, price events and protected contracts are unchanged. Its eight generated assets are **178,418 → 177,847 raw bytes (−571 B)**. Security `34224138160` and exact artifact `34224138265` passed; artifact `10055011556`, digest `sha256:5fb3eba01d21851bea83b2701907a2f7ab134105234b3f5b16eed9707d32807a`.

Final checked release `c155faa2cfcbb2e04dbafa841022d491ee863abe` is **458,693 → 448,524 loaded raw bytes (−10,169 B)** across the retired selected-tour layer, retained current-owner repair and JS consolidation. The eight generated assets are 182,439 B at that combined release; only #1587 contributes a real eight-asset reduction, while the selected CSS layer saves bytes in the scoped shared endpoint. The original red run remains recorded and is not called green.

Preview was not published and remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction and physical Safari remain deferred. Audits: `docs/project/search3-lean-selected-tour-skin.json` and `docs/project/search3-current-presentation-runtime-consolidation.json`.

Next: audit the next complete shared presentation layer against current Search3 owners. Keep the old route intact and take only a substantial package with a positive final loaded-byte result.

### S3_LEAN_APP_PRESENTATION_LAYER — checked release, not published

[#1591](https://github.com/pyatkoff/poisk-turov-test/pull/1591) removes the complete 12,911-byte `app.css` layer from Search3 only. The unchanged full manifest continues to serve it to `/poisk-turov-old/`. Exact artifact failures were used as a repair list: current owners retain only scoped box sizing/body baseline, entry grid and native-control dimensions, selected/flight/lead primitives, and the result-card container/image/typography geometry that is still live.

Final checked Search3 payload is **448,524 → 438,274 raw bytes (−10,250 B)**. Shared CSS is **82,345 → 69,434 B** and shared JS remains **183,740 B**. The eight generated public assets are **182,439 → 185,100 B (+2,661 B)** because the live replacement slice now belongs to current modules; this package is therefore recorded as a real route payload reduction, not as an eight-asset reduction. Across the preceding selected/runtime package and this layer, the checked route moved **458,693 → 438,274 B (−20,419 B)**.

Final source `7fe04821c961c48243a69d6edee0e345bcc43d8a`; checked release `de49b39989891945b8e47434e72d5e79d9f4080b`. Security `34230516546` and exact artifact/browser `34230516579` passed. Reusable whole-site artifact `10057632192`, digest `sha256:8248e14765ed95dce5f84ff16799e893a16a1dd479aebb07910e7282a7bdbb4f`; selected evidence `10057629961`, digest `sha256:d854e0350839288dd2da6635f5dc6ed6bca8fcf7876be24df0e32cebb700cc22`; results/entry evidence `10057631078`, digest `sha256:dfb7988688678d2a4907dcda54c6bc612ce427f2620ddb9c0c3285a8364a653f`.

Exact Chromium retained selected detail/review/lead at 375/760/1000/1440, entry lifecycle and breakpoint resize, and result-card/drawer collapsed/expanded geometry at 375/760/761/999/1000/1440. No external API or lead request was made. Eight public paths, price arithmetic, URL/payload, Tourvisor/API, lead transport/mapping, Metrika/analytics/goals, logo and native-browser/nesting contracts remain protected.

Preview was not published and remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, manual screenshot inspection, physical Safari/safe-area and owner acceptance remain deferred. Audit: `docs/project/search3-lean-app-layer.json`.

Next: re-audit the complete `search-states-design.css` layer after `app.css` retirement. Its previous fallback assumptions are obsolete; preserve explicit current status, skeleton and empty-state ownership and proceed only with a positive final loaded-byte result.

### S3_LEAN_APP_STATE_MOBILE_LAYERS — checked release, not published (2026-09-08)

Three consecutive Search3-only packages retired complete legacy presentation layers while preserving every file in the full manifest for `/poisk-turov-old/`:

- [#1591](https://github.com/pyatkoff/poisk-turov-test/pull/1591), source `7fe04821c961c48243a69d6edee0e345bcc43d8a`, release `de49b39989891945b8e47434e72d5e79d9f4080b`: removed 12,911-byte `app.css`; 2,661 bytes of active base/entry/result/selected primitives moved to current Search3 owners, net **448,524 → 438,274 raw (−10,250 B)** and same-method gzip **100,143 → 98,083 (−2,060 B)**.
- [#1592](https://github.com/pyatkoff/poisk-turov-test/pull/1592), source `1607948872372a5d1426076ccd1158846258be68`, release `49f898823d30b6f3e60cb682e9d474767a03f812`: removed complete 2,199-byte `search-states-design.css` with no compensation; skeleton, empty and tour-loading structure remains in current `search-progress.css`.
- [#1594](https://github.com/pyatkoff/poisk-turov-test/pull/1594), source `220667185984d1e3c05ede301fa222038b0637b4`, checked release `58b5cf9dc1c13388c6ebc9706cbdc2a293f7bbfc`: removed complete 4,247-byte `mobile-results-filters-v1.css` with no compensation. `mobile-results-filters-v1.js` remains loaded and the current Search3 toolbar owner already supplies the bar, drawer, option and action presentation.

Combined loaded shared CSS + shared JS + eight generated public assets are **448,524 → 431,828 raw bytes (−16,696 B)**. Shared CSS is **82,345 → 62,988 B**, shared JS remains **183,740 B**, and the eight generated assets are **182,439 → 185,100 B** after the retained current-owner primitives from #1591. All eight public paths are unchanged.

Required Security runs `34230516546`, `34231461485`, `34231823029` and exact artifact runs `34230516579`, `34231461481`, `34231823219` passed. Final reusable artifact `10058165680`, digest `sha256:feee4efdd6bdef701221c28a0d72f35e0ecacca08f7ff5005d0da8c3888dedf3`; selected evidence `10058163708`, digest `sha256:84b34d9689a3b14c14a527ad2031eb2c5827a59f039665075fbd0515c277870c`; results/entry evidence `10058164665`, digest `sha256:d8f80b8afdc76c6ef1c5276409f1ec8eb1d7e7663190482d30d264e6a0d93ee9`. Final exact source/PHP/path/presentation/isolation guards and Chromium 12 selected detail/review/lead, 30 entry and 12 result/drawer states passed without external API or lead requests.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; none of these sources was published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, manual screenshot inspection, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-app-state-mobile-layers.json`.

Next: retire `current-price-calendar-v1.css` from Search3 after moving only its active day-grid/button primitives into `entry-calendar.css`. Preserve `current-price-calendar-v1.js`, all price arithmetic, the eight public paths and the complete old-route layer.

### S3_HALF_BUNDLE_AND_DEAD_FOOTER — checked release, not published (2026-09-08)

The owner requested one coarse reduction with minimal repeated checks. [#1602](https://github.com/pyatkoff/poisk-turov-test/pull/1602), source `ba53409864d25d44f6900e83d0095d03fe155d33`, removed three complete CSS layers and nine JavaScript owners from the Search3 route. The full manifest and files remain available to `/poisk-turov-old/`. The optional price calendar, room gallery/details, hotel autocomplete and secondary loaded-result filters were intentionally not retained on the test release; core search, Tourvisor/API, selection, price arithmetic, URL/payload, analytics and lead transport remain.

The first exact run `34237093531` was correctly red: the supposedly entry/results-only `ds2-search-intro-v1.css` contained an undocumented selected-tour focus block. Evidence `10060370921`, digest `sha256:a4ac4facee5b6ac84646c02d54afa3df42e0ee1e8c8d6fd197a58492124a25bd`. Only that live slice was moved into the current `tour-detail` owner; the complete legacy layer stayed excluded. Final Security `34238161870` and exact artifact `34238161854` passed, including selected detail/review/lead at 375/760/1000/1440 and guarded entry/results geometry. Reusable artifact `10060846914`, digest `sha256:8b111ac4af05d0a459022f956189950c472dbeb7439e08e3be703aabfd2e34af`; selected evidence `10060844850`, digest `sha256:2219dfa938529d8a38c8ad25af4821ead910f9d4ad1c65a88b7d933d77a7ec0c`; results evidence `10060845900`, digest `sha256:91c60d11e8235d8907b26baabf3852327cc96bc9479ebefafecde1420fba4248`. Checked release after #1602: `e8c50167b32a08bc2e21aab084f61ff64ba969c9`.

The same invocation continued with [#1603](https://github.com/pyatkoff/poisk-turov-test/pull/1603), source `7c509cdd9f8c8073c30383445029f5b7a6273731`: two unused generations (`v2-site-community` and `at-site-footer`) were removed from `site-footer-v1.css`; the active DS2 footer, logo, contacts, navigation, social/app links and responsive rules remain. Security `34239017232` and exact artifact `34239017211` passed. Artifact `10061148819`, digest `sha256:06b40cdd95b9e384819884efdd608984c3fb4f0de0751a315c0aa28d6e6d92ca`. Final checked release: `789f4cdb200ca19bab9967aad5f64edbe60014da`.

Across these two PRs, loaded Search3 CSS/JS is **419167 → 324836 raw bytes (−94331 B)**. Shared CSS is **57078 → 20670 B**, shared JavaScript **183740 → 126853 B**, and the eight generated public assets **178349 → 177313 B**. Against the original lean-bundle baseline, the route is **665247 → 324836 B (−340411 B / 51.17%)**. This is the first checked release below half of the original loaded raw size.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; neither source was published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, manual screenshot inspection and physical Safari/safe-area are deferred. Audit: `docs/project/search3-half-bundle-reduction.json`.

Next: audit remaining required JavaScript only as a coarse dependency package. Do not micro-trim or repeat CI on ready transition; preserve the core search, Tourvisor/API, price, routing, analytics and lead contracts.

## 2026-09-08 — whole-layer half-size reset (#1604)

[#1604](https://github.com/pyatkoff/poisk-turov-test/pull/1604), exact source `a25fb7d4492ffa3c7308bc5ac92fadd8b91f9017`, removed 42 obsolete/duplicate Search3 CSS modules, the standalone progress presentation module, all shared CSS and seven optional shared runtime owners. The eight public paths remain stable. Base-aware eight-asset raw size is **178349 → 78454 B (−99895 B / 56.01%)**; complete loaded Search3 CSS/JS is **412027 → 197067 B (−214960 B / 52.17%)**.

Security `34241546821` and exact artifact build `34241546778` passed. Reusable artifact `10062216567`, digest `sha256:4f694ae0a1b0bba75193f5be0cccb1b7e230e5c99dfe5d6af9480dd674cd6eeb`; focused reset evidence `10062216044`, digest `sha256:e114b9d49421d63f1c9ea1e2c8d9850a1b9d0914cf8b7c9954eccf32c2984ad8`. The one focused browser pass covered entry/detail/review/lead at 375/1440, blocked all external requests and sent zero leads. It caught and repaired a sub-44px search submit and mobile selected-tour overflow before merge. Release is `9d0d29f21033414fe86ee8179a9027f922d98896`.

Preview was not published: published source remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Manual screenshot inspection, live current-source interaction, physical Safari/safe-area and the broad responsive/site/SEO matrix are deferred, not passed.

Next: audit retained results/selected JavaScript as one coarse owner package. Do not return to micro-trims or per-layer browser/deploy cycles; preserve API/Tourvisor, URL/payload, price, lead and analytics contracts.

## 2026-09-08 — results presentation reset (#1607)

The same active reduction pass continued after #1604 with [#1607](https://github.com/pyatkoff/poisk-turov-test/pull/1607), exact source `e15d9e67feb0e08d97dfd0255a75a8e89b245980`. The duplicate Search3 results presentation, card decoration and label owners were removed as whole modules; `results-top.js` is now a small route-visibility bridge. The canonical `v2/results-renderer-v5.js` remains responsible for result rendering, sorting, empty recovery and the real `.direct-tour` action.

Eight public assets are **78454 → 60811 raw bytes (−17643 B)** and same-method gzip is **22594 → 18093 (−4501 B)**. Complete loaded Search3 CSS/JS is **197067 → 179424 B**. Against the original 665247-byte route, the checked release has removed **485823 B (−73.03%)** and is **3.71× smaller**.

Security `34243761242` and exact artifact/core-browser run `34243761739` passed. Reusable artifact `10063127413`, digest `sha256:11adb12faeebc6402c15758e45696ba0e9c286e79d309f4da734287796f961fa`; browser evidence `10063126829`, digest `sha256:b1c8e44ab397f253fc41fd37938683111aa2e373376088a843613ee5c52db14a`. The deliberately narrow smoke rendered a result through the retained renderer, selected its actual action and reached detail/review/lead at 375 and 1440 with external requests blocked and zero leads sent. It first caught and then repaired an empty mobile rail overflow and desktop results remaining expanded under selected detail. Checked code release: `686cd04e26a7d07bed559d637301cf30b4aed7b2`.

Preview was not published and remains source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`. Main observed `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Manual visual acceptance, live current-source interaction and physical Safari/safe-area remain deferred.

Next: treat retained selected-flow JavaScript as one coarse package. Preserve price and pending arithmetic, URL/payload, Tourvisor/API, lead transport/mapping and analytics; do not spend a separate cycle on micro-reductions.

## 2026-09-08 — native entry owner retirement (#1610)

[#1610](https://github.com/pyatkoff/poisk-turov-test/pull/1610), exact source `ca42aa75d7558b902399b72f5135c3d52a4a0055`, removes the complete `entry-presentation.js` calendar/summary/timer-layout owner, the custom guest popup and obsolete mobile filter/trust wrappers. The original adult/child/child-age controls are directly editable; their nodes, values, names and catalog handlers remain. Native date/night controls, URL/payload, meals, price, API/Tourvisor, lead, analytics and all eight public paths are unchanged.

Fresh base `686cd04e26a7d07bed559d637301cf30b4aed7b2`: eight assets **60811 → 54605 raw B (new saving 6206 B)**; main JS **28806 → 22600 B**; loaded route **179424 → 173218 B**. Concurrent #1607 had already retired results while #1608 was being prepared; #1608 was closed unmerged and its candidate bytes are not counted. Checked code release after #1610 is `def1cf844aa1b9d15bb59219d8633ad4e1409314`.

One Security `34244914013` and exact artifact `34244914086` passed on the first source. Reusable artifact `10063598031`, digest `sha256:c5bbb4fa23c854381fc585fee8b06bbfa77135545d455a694ed06606b7768f38`; native-entry evidence `10063597221`, digest `sha256:90572d15f4f4a850fd73393fc5af6aeaa6e5a66e355b2a72582866d328bffd24`. Source build drift/malformed-input fixtures remain executable in the retained primary-controls module. Existing source/PHP/path/presentation/isolation and geometry conditions were not weakened. The exact presentation suite reports 7 active tests and 53 pre-existing skipped historical tests; this is not a claim that historical pixel assertions passed.

Actual Chromium checks: 375/1440 URL guest hydration, original adult/child/age nodes, changed FormData values, night range, native >=44px tap targets and no horizontal overflow. Both screenshots were inspected: controls and labels are visible; the intentionally stripped shell remains from the earlier reset. Catalog/image requests were blocked, no search/lead request was sent. This does not establish live data availability or owner visual acceptance.

Preview not published: source remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`. Main observed `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production unchanged. Physical Safari, live current-source preview, owner acceptance and the full lead/responsive/site/SEO matrix are deferred. Audit: `docs/project/search3-entry-owner-retirement.json`.

Next coarse owner audit is complete: selected-flow is 18705 raw B and still mixes optional disclosures/mobile bars/trust with required no-flight retry/review, selected-open state and decimal-safe price labels. Retire its presentation only with that salvage; recheck parallel PRs before editing. No speculative selected-byte saving has been counted.
