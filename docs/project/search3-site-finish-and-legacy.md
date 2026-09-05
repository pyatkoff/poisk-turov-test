# Site completion and legacy search — 2026-09-04

Owner priority: finish the new design, then switch the canonical search. Preview deployment authorized; production visual approval still pending.

Canonical project task: [Search3 — завершить новый дизайн сайта и подготовить перенос #996](https://github.com/pyatkoff/poisk-turov-test/issues/996). Working implementation: draft [#1334](https://github.com/pyatkoff/poisk-turov-test/pull/1334). `AUTOPILOT_STATE.json` records the same queue in this active branch; the main branch has not received these draft changes.

## Ordered remaining work

1. Shared button/header/footer convergence is published in the whole-site preview and checked against the real catalogue.
2. Whole-site preview is published; links and preservation of city, dates, nights, party and child ages are verified after the family-handoff correction below.
3. Fix confirmed customer-copy/city-inflection issues, map actions from verified office addresses and broken legal/payment links using existing approved materials. Defer missing/unapproved legal terms, hours and unsupported advantages.
4. Exclude past departures using an explicit timezone and verified availability rules for same-day departures. Preserve departure city across handoffs. A new remembered city shared by every data showcase is a separate product extension if it requires changing the data source.
5. Desktop acceptance through the actual search to the visible lead form passed. Long-value desktop layout and deterministic unavailable-flight/retry recovery passed. Complete physical iPhone/Safari acceptance. Preview does not establish receipt of a real lead.
6. Obtain visual approval of the concrete release, retain prior deployment/configuration and verify rollback/old search, then perform the authorized production migration and post-deploy checks. Confirm real lead delivery separately with a controlled agreed test.

Completed implementation is distinguished below from preview publication and production acceptance. Mass SEO expansion and technical refactor are outside this finish pass.

## Preserve the previous search

- Source archive: `archive/search-before-search3-2026-09-04`, exact main `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. This records source/assets; it is not a database or server-configuration backup.
- Prepared production compatibility route: `/poisk-turov-old/`. Disables Search3 presentation, retains maintained search/API/lead runtime, sends noindex in HTTP and HTML, canonical points to `/poisk-turov/`. The route is published only inside the isolated whole-site preview and is not added to production sitemap/navigation.
- Before actual switch: retain previous deploy artifact and server configuration under the existing release procedure; verify old and new routes, unchanged API/lead contracts, and rollback on the exact previous artifact. Do not overwrite current deployment until owner approves.
- Compatibility route is a working old interface with shared runtime. For a byte-frozen historical version use the archive branch, not this maintained route.

## Latest search correction

Published candidate `470474414a3930f1f6c095a8dbbc187075b253c0`: native select controls for nights 1–28 synchronized with original named inputs/events; readable two-row desktop form including 1920/2048 and reduced initial footer gap. CI 33925976956 passed the five standard widths plus wide-screen checks; evidence 9956839979, deploy PR #1339 and successful deployment 33926206799. Physical iPhone system night-picker UI and Safari alignment still require confirmation. Calendar opening was confirmed by the owner. Earlier `cc4e` / #1338 evidence is superseded by this candidate.

## Site review scope and findings

Live browser inspected homepage, contacts, how-to-buy, early booking, hot tours, country catalogue, Turkey, Turkey/September; checked payment link. This is a representative template/navigation/content review, not an exhaustive mobile audit of every URL. Search mobile evidence covers 375/430; full candidate widths 375/430/768/1024/1440.

| Priority | Area | Finding and action | State |
| --- | --- | --- | --- |
| Before migration | Legal/payment | `/payment/` still renders Not Found. Earlier privacy/consent 404s remain unresolved; obtain valid pages/content. Do not invent legal terms. | Blocking open work |
| Before migration | Common shell | Search preview footer/form differ from existing homepage/content pages. Converge spacing, button roles and footer content across page families in a whole-site preview. | Published and checked in isolated preview; not production |
| Before migration | Search handoff | Snapshot links retain supported dates/nights/two-adult scope; month CTA retains a future date range in its month. Exact hotel selection is not promised. | Published and live family path checked; not production |
| Before migration | Client copy | Technical SEO instructions visible on Turkey and month pages. Replace relevant country/month template copy with customer-facing price-check explanation. | Published in isolated preview; not production |
| Before migration | Price freshness | Past/malformed snapshot departures are excluded using the explicit Europe/Moscow business date while same-day allowance is preserved. | Published in isolated preview; not production |
| Next design pass | Homepage | Independent native night range, children/ages and extended-search state preservation are implemented and checked against the real catalogue. | Published in isolated preview; not production |
| Next design pass | Contacts | Four map-search actions added using the existing office addresses. Hours/photos still require factual sources. | Published in isolated preview; not production |
| Next design pass | Hot offers | Technical copy replaced with customer-facing explanations; neutral `Вылет: Москва` avoids incorrect inflection. | Published in isolated preview; not production |
| Confirmed working | Turkey links | Month links and similar destinations are populated; the earlier claim of empty blocks is not currently true for Turkey. | No fix needed |
| Retain | Buying flow | How-to-buy explains that a request is not confirmed booking/payment. Keep this consistent with tour UI. | Retain |

Do not expand mass SEO pages, analytics, transport or supplier contracts as part of these design corrections. No production lead was sent during review.

## Follow-up — desktop screenshot and handoff implementation

- Fixed the wide-screen CSS conflict: desktop now uses a readable two-row primary form; the date range, night range and submit button share the second row. Reduced initial footer margin. Added 1920/2048 geometry checks and JPEG evidence in addition to the five established widths. Safari date-value line height adjusted; physical Safari confirmation still needed.
- Country, resort and month snapshot offer links retain advertised departure date and duration; two-adult scope matches the snapshot builder's `adults=2 AND children_count=0` selection. Links do not promise exact hotel selection.
- Seasonal page CTA now selects dates inside the recorded month, beginning no earlier than tomorrow and bounded by the existing 21-day search range limit. The whole month is not searched at once.
- Home uses independent native night range controls and child-age selectors. Search page restores validated ages (0–17, up to three children) from existing `child_age[]` query values. Extended-search link retains entered form values.
- Draft CI verifies offer-to-form parameters, independent night range and child ages 0/17; home mobile/desktop layout inspected from CI screenshots. Local CI catalogue loading lacks production catalogue service and is not a finding of production failure.
- These main-site changes remain in the production draft. Common footer/button convergence, legal/payment pages, freshness cutoffs and office map actions are still open; no production switch is authorized by this follow-up.

## Shared site shell follow-up

Shared server-rendered footer now owns navigation, existing social/app links and contact phone on every page. Its marker prevents the Search3 donor from replacing the DOM. Removed donor-only header/legacy-footer CSS overrides from the production import; selected-tour footer hiding is retained. Primary search/content actions use the same blue; header search action is secondary. Four office map searches use the published addresses. Hot-page internal data-source wording and departure label are cleaned up. Legal/payment destinations remain unresolved and are not represented as repaired.

At `c46d76a973a4cb89b1c50eb985a6c4a2f155c454`, all applicable gates passed; visual run `33929767087`, artifact `9958121988`. Inspected phone/desktop evidence of the actual Search3 route as well as the shared footer and contacts. The older audit had rendered the legacy `/index.php`; CI now also exercises canonical Search3 using a local standalone wrapper. Failed catalogue loading in isolated CI is a fixture limitation, not live-site evidence.

Next task is the whole-site preview artifact/publication. The existing preview deployment is limited to nine search files and cannot publish these shared PHP/home changes. Keep its allowlist intact; prepare a separately reviewed site preview instead of calling the production deploy workflow. This draft has not changed the public preview or main site. Hourly scheduled development is enabled (Europe/Amsterdam). Continue beyond one PR when time remains for another safe step. The first background execution has not yet been verified.

Owner continuation rule: **do not stop after one PR if the current run has time for the next safe step**. Update the handoff after each checked change and continue. A final production visual-approval gate blocks production publication only, not other authorized safe preparation.

## Snapshot freshness follow-up — 2026-09-05

Confirmed in source: country/resort/hotel readers validated snapshot TTL but allowed past departure dates to occupy the cheapest-offer slots. They now filter past/malformed dates before sorting and limiting. Hot-offer display uses the same explicit Europe/Moscow business clock as the existing price calendar instead of the database session date. This is a business-day display rule, not an airport timezone or a new same-day sales cutoff. Existing same-day availability and recheck behavior remain unchanged.

Integration fixtures exercise the real readers with an in-memory SQLite database: past cheap offers, stale snapshots, empty results, city filters, valid today/future offers and limiting. Date tests include Moscow midnight with a different host timezone and leap/year boundaries. No live search/API/lead calls. Implementation `1fddaf5e4e1c1285cb768050352c3d0a1ab0cdc4` passed core CI `33934082041` and visual CI `33934082023`; it is now included in the isolated whole-site preview. Production is unchanged.

## Pre-deploy isolation review — 2026-09-05

**Do not publish previously pinned artifact 9958766470.** Its clean-docroot smoke did not cover production Bitrix/site_conf inheritance, an enabled production analytics counter or the external consultant widget. The copied legacy entry also required the transformed homepage rather than the search runtime and retained a production lead target.

The artifact builder now performs exact-source, fail-closed transformations on the copied payload only. Production PHP/analytics/goals/lead transport remain unchanged. New smoke fixtures throw on any production bootstrap and supply a nonzero counter; both search entries must still render with counter zero, no consultant widget and the disabled preview lead target. The legacy UI remains available in the payload. These checks are preparation, not proof of server-level isolation: real HTTP denial of internal PHP/config paths, pinned provenance, production fingerprints and atomic rollback still gate deployment.

Verified replacement: source `b56022b019568fa7a0719a2e1984792f59b4c38b`, tree `90ed9589ca0a4bbdcf4c1e25f246a6dd3c74a839`, artifact `9959663775`, build/HTTP smoke `33934383695`, core `33934383665`, security `33934383673`, visual `33934383671`. All 21 applicable gates passed; migration gate skipped by its configured condition. Initial smoke failure was a test selector typo (`searchForm` instead of actual `tourSearch`), corrected without weakening checks. ZIP, inner archive, control manifest and all 715 payload checksums verified locally; exact hashes are in `AUTOPILOT_STATE.json`. Mobile Turkey and desktop hot screenshots from freshness visual run `33934082023` were inspected; these empty-data fixtures do not prove live offer availability or receipt of a lead. No public whole-site deployment yet. Next is the separately reviewed one-shot preview deploy and real journey acceptance.

## Whole-site preview and family handoff — 2026-09-05

The coherent preview is live at `https://anytoour.ru/_preview/search3-site-candidate/`. The first exact deployment from `b56022b0` passed all isolation and production-fingerprint checks, then a live home-to-search pass exposed a real integration defect: the URL retained `child_age[]=0&child_age[]=17`, but Search3 cleared the old primary grid after the existing guest UI had moved `#childAges` inside it. The detached age controls disappeared and automatic search stopped with «Укажите возраст каждого ребёнка».

Search3 now detaches and retains the canonical `#childAges` container before rebuilding the grid, places it inside the Search3 tourist popup and keeps the existing catalogue renderer as its owner. No Tourvisor, price, lead or analytics contract changed. Visual CI `33937670550` verifies `child_count=2`, exact ages `0/17` and popup ownership at 375 and 1440; core `33937670572`, boundary `33937670439`, security `33937670381` and all other applicable gates passed.

Exact current published version: source `47e54a02e021659f9c8248c0e6603a1a744589de`, tree `e7b50416780ba8876973579839b4da2bc7dde082`, build `33942514581`, artifact `9962292167`, deployment `33942600148`, deployment draft #1346. The artifact contains 715 files; its ZIP, inner archive, manifest and payload-checksum hashes are recorded in `AUTOPILOT_STATE.json`. After a fresh reload, live checks confirmed content-addressed Search3 JS `6e47d3a9…`, a completed real catalogue search, an opened hotel, concrete operator offer, available flight, tour summary and visible lead form. No lead was submitted. Preview Metrika, external widget and lead delivery remain disabled; internal PHP/config paths are denied, protected production fingerprints remained unchanged, and the previous preview target was retained for rollback.

The selected supplier offer presented placement `TRPL + 1 CHD` and `3 взрослых + 1 ребёнок` after the request entered two adults with ages `0` and `17`; a control request with ages `0/10` returned `DBL + 2 CHD`. This confirms supplier normalization of a 17-year-old as an adult for the selected offer. The final section now says `Состав размещения у туроператора`, uses the common party-label helper for correct grammar, and preserves the original requested adults and ages in lead context. Tourvisor, price, lead and analytics contracts were not changed. Physical iPhone/Safari remains before owner acceptance. Main and production were not changed.

## Unavailable-flight recovery acceptance — 2026-09-05

The live catalogue samples inspected during preview acceptance all returned flights, so absence of a real no-flight sample is not represented as live production evidence. A deterministic Chromium integration check now exercises the actual tour controller, empty-flight recovery, Search3 presentation and selected-flow adapter together. At 375px, a second empty response retains exactly one retry and the user can continue to the tour summary and visible lead form without invoking `fetch` or lead transport. At 1440px, a retry returning a real flight removes the fallback state and fallback mobile action. Run `33940532420` passed at branch SHA `928f25ec5f08ccf544a6dd88acf16cf77192f47f`; all 22 applicable checks passed and one production-only migration check skipped. This coverage is included in current whole-site preview source `47e54a02e021659f9c8248c0e6603a1a744589de`.

## Result-card readability — 2026-09-05

The confirmed card defect was in the presentation layer: mobile hotel images had been forced down to 126px while several comparison labels remained 7–10px. Branch source `17b674fc54fa6b49aaa73379bcbdc35bfccfda28` gives phone images an adaptive 184–240px target, raises the hotel title to 18px, facts to 12/14px and price context/action text to at least 13/14px, and arranges the four facts in a two-column phone grid. At 1024/1440 the compact horizontal card is retained with 12/14px facts and no clipping.

The price context was not expanded to repeat card facts. It contains only the party composition; departure, nights, meal and flight each appear once in the facts grid. Deterministic browser evidence at 375/430/1024/1440 checks computed sizes, duplicate labels, card overflow and document overflow. Visual run `33941679993` passed, artifact `9962036028` has digest `sha256:598c2bb9d306d03b7c1054c3b31b5da2116b0a35619c2f56589a24ba8fb1c868`, and all four screenshots were inspected. All 22 applicable branch checks passed; the production-only gate skipped by condition.

This correction is published in coherent preview source `47e54a02e021659f9c8248c0e6603a1a744589de`; main and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`.

## Localized flight-price tradeoffs — 2026-09-05

A live post-deploy journey exposed a contradictory comparison label: an alternative flight displayed `90 049,6 ₽` against a `72 832 ₽` minimum but showed `+827 664 ₽ к минимальной`. The selected total itself was correct. The Search3 tradeoff helper had removed the Russian decimal comma while reading already-formatted DOM text, turning `90 049,6` into `900496` for the comparison badge.

The correction is intentionally limited to Search3 presentation. It parses localized formatted money for the comparison label and does not change the Tourvisor/API response, selected-flight price, price arithmetic, lead payload/transport or analytics. Deterministic Chromium now verifies that `72 832 ₽` and `90 049,6 ₽` produce `+17 217,6 ₽`, never `+827 664 ₽`; flight-source run `33942514556` passed. All 22 applicable checks passed at exact source `47e54a02e021659f9c8248c0e6603a1a744589de`. A fresh live reload then showed `72 832 ₽` and rounded `90 050 ₽` with the consistent `+17 218 ₽`, and the journey reached the lead form without submission.


## Resort destination grammar — 2026-09-05

The live coherent preview exposed the template phrase `Подобрать тур в Анталья`. The resort renderer had reused the nominative `name` field after the preposition `в` in the hero CTA, offer heading and price-calendar heading. It now uses an explicit `name_accusative` when supplied, otherwise the already reviewed destination form from H1 such as `Туры в Анталью`, with the nominative value retained as a safe fallback for indeclinable or nonstandard records.

PR #1348 passed the focused six-case grammar smoke, standalone content UX and exact-artifact build at `66a5a66e`; the squash merge `4bac82adad1be95a6f07b5413b2b1adbcc17fe24` has the same tree `4640840c5ef48e5fcc570c19b31dd213ec418f20`. All 22 applicable branch checks passed; the production-only migration gate skipped. Exact artifact `9963177822` (build `33945457730`) was published by isolated preview deploy `33945548360`, record draft #1349. Live Antalya now says `Подобрать тур в Анталью`; Kemer remains `Подобрать тур в Кемер`. Preview lead delivery, Metrika and external widget remain disabled; protected production fingerprints are unchanged and rollback is retained. Main and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`.


## Unpublished legal/payment footer destinations — 2026-09-05

Fresh HTTP checks confirmed `/payment/`, `/personal-data/` and `/politika-konfidentsialnosti/` return 404 on both production and the coherent preview. Approved source content is still unavailable, so PR #1351 removes these non-working destinations from the new-site footer rather than creating legal or payment terms. Production and preview path smoke now reject re-exposing the links until real pages exist; payment marks and lead consent behavior are unchanged.

All 22 applicable branch checks passed at exact source `57d734eb07dd26f996275dcc1f8d9cf5a01b71ab`, tree `8340078e1c2e6d3b5d3e137278435f0bb609d0d2`; artifact build `33946001085`, artifact `9963347925`. Isolated deploy `33946112648`, record #1352, retained rollback and unchanged production fingerprints. Live preview contains none of the three broken footer links and retains the corrected Antalya/Kemer wording. Main and production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`.

## Production rollback preparation — 2026-09-05

PR #1354 added a server-local snapshot utility and a separate migration runbook without changing the production deploy workflow. The utility snapshots only an explicit reviewed inventory, rejects traversal, symlinks and backup locations inside the served root, records SHA-256/mode/size metadata without printing file contents, and restores only into an empty isolated directory. Fixture coverage also proves that configuration and bridge-secret bytes and modes survive a snapshot/restore drill while their contents do not appear in command output or the manifest.

Rollback-readiness run `33948068212` passed at release SHA `7de8381388ac50b3e022ec5b7933d5663458ab60`. This status is **prepared and fixture-drill verified**, not a live backup: no production file or configuration was read or changed and no deployment was triggered. Immediately before an owner-approved migration, the exact managed-file inventory must be reviewed against previous source `fa58a0cba6dcfc8624d98c20d64fa06330eae309`, then the actual server-local snapshot and isolated restore drill must be captured. Snapshot payload/configuration must never be uploaded as a CI artifact. Physical iPhone/Safari and owner visual approval still gate the switch.
# Owner-directed mockup convergence and price calendar — 2026-09-05

The owner requested the strongest elements from the eight supplied PNGs plus the price calendar from production `/poisk-turov/`. All eight exports were recovered and SHA-256 matched against `search3-approved-mockups.json` from the reference dossier. This is a design direction, not approval to replace production.

- Reuse the existing `current-price-calendar-v1.js` unchanged: minima from the completed search, exact-date recheck through the canonical lifecycle, existing search-reset handling. Search3's broad shell CSS had hidden this supported section. The presentation adapter restores it with an expanded desktop view and a compact disclosure on phones; route, party, child ages, nights and filters remain under the existing form owner.
- Mockup 7: preserve the dark footer, clear columns and original logo, restore existing repository social/store artwork and reduce empty rows. No 24/7, refund, instant-confirmation or credit promises are reintroduced. Working links and verified phone remain authoritative.
- Mockup 6: retain a simple contact column alongside the tour card, readable inputs, a clear submit action and less duplicate framing. Hide the duplicate inline tour selection only where the desktop summary is visible. Lead transport, consent and field mapping are unchanged.
- Mockups 3–5: supplier placeholder `SU000` with both times `00:00` must say the flight is to be clarified in every summary. Zero baggage on that placeholder is unknown; genuine zero baggage on an identified flight remains distinguishable. The canonical controller already implements these semantics; only secondary presentation is corrected.
- Mockups 1, 2 and 8: retain the current readable hotel photos/facts, local filtering and staged mobile path. Do not restore illustrative unimplemented functions or unsupported business claims from the artwork.

Prepared in the active release continuation; exact CI/preview status will be recorded after verification. Production remains gated by explicit owner approval, and physical iPhone/Safari plus approved legal content remain outstanding.

## Mockup/calendar publication and live acceptance — 2026-09-05

Exact source `4a644f486e55718c52e5994fb6de8f87d0a88abe`, tree `1707ead52b090725a86a6407dd889aaa11202fda`, passed all 23 applicable checks; the production-only gate skipped. Visual run `33952301299` verifies the actual shared calendar at 375/430/1024/1440, initial disclosure state, long prices, date-button touch targets, one lifecycle submission with other parameters unchanged, and reset/selected-tour visibility. All four calendar images and relevant mobile/desktop footer images were inspected. The first candidate's visual failure exposed the high-specificity legacy-shell hide selector; the supported calendar was excluded from it before publication. Failed initial artifact was never deployed.

Exact 715-file artifact `9965218543` from build `33952301309` was independently verified by ZIP/inner archive/manifest and every payload byte, then published through isolated deploy `33952637454`, record draft #1356. Deployment evidence `9965326844` confirms nine routes, noindex, disabled lead delivery/counter/widget, denied internal PHP, retained prior preview and identical protected production fingerprints before/after/final. Main/production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`.

Fresh live search showed nine date minima. Selecting 8 September changed both departure bounds to that date, retained 7–10 nights and two adults, and completed a fresh search. The existing calendar intentionally disappears for a single-date result. LUXOR APART then opened through a supplier-placeholder flight, tour summary and contact form. Number/time/baggage correctly say they require clarification; no lead was submitted. Content-addressed live JS matched `223e6c1f5e98d08d` and `75e29c7e7a95776f`. The footer and tour/form screens were visually inspected.

The live form exposed a separate presentation defect: the full tour amount is repeated under «Цена варианта рейса». That label misleadingly suggests a standalone ticket price. A focused follow-up removes the redundant row and retains the authoritative overall price; raw variant prices, total arithmetic and lead mapping must remain unchanged. Physical iPhone/Safari and approved legal content remain outstanding; no production approval is inferred from this work.

## Single tour total: final preview checkpoint — 2026-09-05

The follow-up removes the misleading «Цена варианта рейса» block from the application sidebar. There is one authoritative full-tour amount and the existing confirmation message: «Перед оплатой менеджер подтвердит итоговую стоимость и детали перелёта.» Raw variant prices, selected-total normalization, fuel calculation and lead field mapping are unchanged. Existing flight details retain fuel information.

Final verified source `17a2c221290252232fe2eb758b2a9dd50d7785bc`, tree `5cf16fea5b83a6c2d3277be3b1010ef939ef10c0`, passed all 23 applicable checks (one production-only check skipped). Flight run `33953360079` navigates through actual review and lead controls, asserts one overall 72 832 RUB total, no repeated flight-price row, the correct alternative-flight tradeoff and zero lead requests. Visual run `33953360125` passed all four calendar widths. Test fixes corrected two fixture assumptions: selected state must derive from an actual nonempty visible tour instead of a body class later removed by the canonical observer; preserved fields must be compared by name while retaining repeated age order, since responsive controls can move in the DOM. Reset additionally proves the calendar content is cleared, beyond CSS visibility. No application behavior or acceptance assertion was disabled.

The exact 715-file artifact `9965554184` from build `33953360074` was independently verified and published by isolated deploy `33953451784`, record draft #1357. Deployment evidence `9965586513` verifies nine routes, preview noindex and lead/counter/widget isolation, internal PHP denial, retained previous preview and unchanged protected production fingerprints. Earlier follow-up artifacts with failed test runs were not published.

Fresh live reload matched Search3 JS hash `593a61096242d38b`. The real search again displayed date minima. LUXOR APART opened through the supplier-placeholder flight, tour summary and application form. The final sidebar shows one 72 832 RUB total, truthful flight clarification and the manager-confirmation note; no lead was sent. Final form screenshot was inspected. The earlier 4a644f48 live exact-date click evidence remains applicable to unchanged calendar code; final CI independently verifies its behavior.

Published: https://anytoour.ru/_preview/search3-site-candidate/ . Main/production remain `fa58a0cba6dcfc8624d98c20d64fa06330eae309`; the source archive and old-search preview route remain preserved. This checkpoint is documentation, not a new deployed source. Physical iPhone/Safari, approved privacy/payment materials and explicit owner visual approval still gate production. The existing schedule was found paused and was not changed by this owner-directed implementation turn.


## Clean booking journey and readable search context — 2026-09-05

Owner approved implementing the next quality package. Live preview17a2c221 kept the entire results grid and rail (15187px) above the lead form; lead Back was10px/38px. Source7a02bab2 adds selected-stage-only CSS isolation while leaving results and search values mounted. Existing four-width integration verifies hidden results/summary/tools and their preserved return. Lead Back/optional actions become14px/44px, submit15px/min48px.

Follow-up00f137f7 makes desktop search context12–13px and filter choices13px with12px values on a separate line. Offer count copy is12px. All23 applicable checks passed; exact artifact9965908477/build33954518510 deployed33954605142, record#1358. Live LUXOR APART→clarified flight→review→form showed no results above the form; Back restored all78hotel cards and identical input/select values. Form screenshot inspected; no lead sent.

Final3624278a59187bae7f76c9dbdb504b8f7b2858fd removes only the redundant desktop filter subtitle to prevent clipping the reset button. All23 applicable checks passed; visual33954714659/artifact9965972719 inspected at1024, with unchanged mobile scope already inspected at375/430 and desktop1440. Exact artifact9965968756/build33954714694, tree26db887754b252099465988f62967e30a9f8840e, independently verifies all715 payload files and archive/manifest/ZIP hashes. Published by isolated deploy33954792052, record draft#1359. Evidence9965993909 confirms9routes/noindex/lead403/internalPHPdeny/counter0/retainedrollback and identical before/after/final production fingerprints. Live final CSS hashd3f972df7ade17f6 matches; reset right306px stays inside the220px rail ending334px. No additional full booking replay was needed for this desktop-only subtitle removal.

Current preview: https://anytoour.ru/_preview/search3-site-candidate/ . Production/main remainfa58a0cba6dcfc8624d98c20d64fa06330eae309. Legacy route and source archive unchanged. Physical iPhone/Safari, approved legal/payment pages (including the existing consent privacy destination), owner visual production approval and separately controlled real lead receipt remain open. These fixes do not constitute9/10 acceptance or production approval. Do not restart the completed calendar or booking isolation; resume concrete remaining UX defects and those acceptance items. Scheduler remains paused and was not changed by this interactive request.


## Owner-authorized search refactoring — 2026-09-05

The owner requested “сделай рефакторинг поиска”. The scoped implementation separates
the presentation monolith into 48 ordered source modules, with an explicit manifest
and dependency-free builder reproducing the existing eight public assets. No served
bytes change in this extraction. Existing API, pricing, lead mapping/delivery,
analytics, PHP asset paths/order and CSS/runtime insertion order remain intact.
Core CI and preview artifact construction reject stale generated files. Five isolated
build tests cover exact reproduction, source drift/rebuild, protected hashes, missing
modules before writes and unlisted/out-of-root source rejection.

Development ownership: `src/search3/README.md`. Compatibility CSS is explicitly still
active; observer/cascade rewrites are not disguised as file moves. The published
preview remains `3624278a` because this source refactor produces identical runtime
assets; a redundant deployment cannot add visual evidence. Remote CI and checkpoint
status are recorded in #996 after the implementation commit.
