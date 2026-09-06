# Search3 technical refactor autopilot

Owner request recorded on 2026-09-06: «давай на автопилот поставь».

## Current owner priority — rapid CSS/JS reduction

The subsequent owner request on 2026-09-06 explicitly prioritizes quickly reducing
and splitting Search3 CSS/JS for the isolated whole-site preview. Larger reversible
presentation batches are authorized. This priority supersedes the older suggestion
below to spend each continuation on another small toolbar/facet defect. Preserve
protected contracts and the production lock; use focused checks and existing CI.

### Current verification priority — lean preview cycle — 2026-09-06

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

### Current published checkpoint — private CSS, markup and media overlap — 2026-09-06

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
