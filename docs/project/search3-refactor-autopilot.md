Warning: truncated output (original token count: 70188)
Total output lines: 2785

# Search3 technical refactor autopilot

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
or speed claim. One successful final buil…40188 tokens truncated…corrects the preceding stored gzip absolute by +4 bytes; the package delta is measured on both exact trees with one method.

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
