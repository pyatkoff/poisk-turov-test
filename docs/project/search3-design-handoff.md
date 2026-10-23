## Site215 — current path reference and prepared NEXT handoff, 23 October 2026

The final flight-editor simplification was published and presented to the owner. The owner then asked whether the current path was satisfactory. After the recommendation to preserve this exact version as the basis, the owner replied **«давай дальше»**. Use Site215 as the next design basis. This entry prepares the receiver change; it does not assert that the new source is implemented, merged, published or accepted in NEXT.

**Donor:** same PUBLIC project `appgprj_6abcd9299ef88191bbce1fc1bf8c767a`, Site215/package104/v240, source `57db3c55856b80ac5874b89d582005f4de58f260`. Native saved version `appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_5e5360d7dec481918ec32ba273558f6e`; deployment `appgdep_6ada8fc443708191b9846f573dab816c` succeeded 22Oct22:36:03Z (23Oct00:36 Europe/Kaliningrad). [Open the whole path](https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/mobile-preview?width=430). [Exact publication/evidence receipt](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6352825331).

Donor app SHA256 `aa72ebc993033d4365654baaab233fc512ccf94afcc933121b74c120859c7a01`; styles `f0bc71c91e1559e8e8218c5550ae78fe98d6aa36e6b40a9d2ba17bf0bbdd3596`. Native archive SHA256 `fab3741c6e9fd70607868745173c30351e90e1fae7fe218f1117547d78217773`. Existing source/evidence are in `/workspace/sites/anytour-search3-simplicity`, `qa/quality51/simplicity-20261022/{README.md,evidence.json}` (whole_choice102, compact_flight103, flight_context104). Preserve the prior209/203 approval and publication history below.

### One receiver and the current dependency

Receiver ONLY: [existing ORIGINAL NEXT](https://anytoour.ru/_preview/search3-next-candidate/visual-search/), repository `pyatkoff/poisk-turov-test`, `release/search3-production-ready-v1`. Preparation base `b2cebd5b4bed5db2524cc400a4ad919d3f0a238a`; installed source `1fdfddb3`. The active SEARCH owner [6352550419](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6352550419) owns [PR4829](https://github.com/pyatkoff/poisk-turov-test/pull/4829), observed final head `a920e7d1ea796627ad223a85fab5427aa7dc3750`, before merge/publication. That same owner should consume this next packet after its current source and publisher claim is terminal. Do not take its app paths, create a parallel implementation or layer Site215 over its unmerged infant fix.

The receiver mapping below was checked against that exact PR head. Its `app.js` blob is `6880b0eb860f0f652753c69ee18da93572fc757f`. Refresh the actual merged release before implementing; retain the current unknown-infant → existing whole-party ANEX package quote transition. Existing INT full-quote receipt [6352400238](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6352400238), durable evidence `26f7753a`, is consumed acceptance, not authority to rerun the supplier case.

### Final product behavior to adapt

Results → concrete room/meal and flight choice **inside the same hotel** → current whole-party price → contacts. Use one main continuation. Selecting or returning from flights must not require confirming the same room again. Contact flight editing opens directly above the retained application; Apply and Cancel return to those contacts. Keep the phone draft; changed conditions reset separate consent.

Mobile flight cards compare the two directions side by side with full dates, stops, airports, allowances and whole-tour prices. In the flight editor, show compact full hotel/date/nights/party context, filters and local-time note. Full room/meal/operator/child ages and the committed listing-to-selected explanation stay in “О туре”. The existing live footer shows the candidate total and its delta. Keep the immutable current selection distinct from an unconfirmed candidate.

| Donor presentation/interaction | Actual receiver owner | Required adaptation |
| --- | --- | --- |
| Same-hotel room → inline flights/current total | `renderHotelRooms`, `syncHotelOfferSelection`, `continueHotelOffer`; existing `hotel-details-v1.js` renderer | Extend the connected room owner, preserving exact offer/raw/search identity and current quote/load controller. No second offer-list stage or duplicate renderer. |
| Compact paired choices | `flight-picker-v18.js` facade, lazy `flight-picker-ui-v1.js`, `renderFlightPicker` | Preserve receiver lazy loading, request guards, restored filters/page/scroll, original indices and callbacks. The donor's simpler bind signature is not a replacement. |
| Compact editor context | `offerDetailBodyHTML(..., flightContent, checkedTotal)` and `tour-flight-editing` CSS | Keep receiver `displayMealLabel`, `tourHeroHTML`, supplied provider content and **checkedTotal** provenance; move the same price explanation into the existing disclosure. |
| Direct contact Apply/Cancel | `openLeadPreview`, `applyFlightPair`, modal/history owner and canonical lead runtime | Adapt the current return target rather than copying the donor lead shim. The observed receiver Apply still returns to `renderRealOffer`; remove that loop while preserving provider-specific application eligibility. |
| ANEX inventory/current total | `refreshLiveHotel`, `loadAnexPackageQuote`, `openAnexPackageQuote`, `anexFlightInventoryHTML` | Present the same flow through the existing provider controller. No generic TV picker, estimated arithmetic or donor quote state may supply ANEX verified-price authority. |

Candidate implementation paths are the existing `v2/visual-search/app.js`, `styles.css`, `hotel-details-v1.js` or `flight-picker-ui-v1.js` only where their connected owner needs the adaptation, plus the migration manifest and affected existing tests. Declare the final exact subset when the SEARCH claim opens. Do not modify fixtures, provider adapters/backends, canonical lead transport, analytics or publication controllers for this UI packet.

### Provider authority and price boundaries

- TV quote-only, flight-pair and manager handoff keep their current distinct price qualifications. An explicit manager choice is eligible only where the receiver already permits it; never fabricate a priced pair from the quote.
- ANEX unknown infant supplement stays unknown/null. Preserve PR4829's current-identity → existing whole-party package-quote route, original multi-pair explicit choice, sole-pair rules and provider application binding. Never use a child fee, zero, default fuel or donor total to fill the gap.
- A new ANEX pair uses the existing bounded calculation/queue/receipt owner; a cached verified pair uses existing retained authority. Changing a radio is not permission for duplicate requests or background calculations of every visible pair. Preserve caps, UNKNOWN/noReplay, expiry, gross currency/party/identity, late-reply and consent guards.
- No-flights/error/unknown-price states require an explicit eligible manager choice or another available path. Missing data is not zero or verified. Preserve terminal410 retention and all source-specific failure handling.
- The design reduces navigation and repetition. It does **not** prove fewer supplier calls, lower supplier latency, final-price readiness or conversion growth. Required live work remains real.

### Bounded implementation acceptance

| Scenario | Required outcome |
| --- | --- |
| Same hotel → room/meal → flight/current total → contacts | Exact identity stays in one hotel owner; no extra offer screen or repeated room confirmation. |
| Known inventory from contacts | Open directly, Apply returns the same application with exact pair/amount; Cancel retains the old pair/amount/consent. |
| Lazy inventory, Back/Close or changed room | Retain the form/phone/current choice until Apply; reject late responses; no silent default pair or duplicate quote. |
| Changed quote or unknown pair total | Keep explicit price acceptance and honest unknown/manager eligibility; no premature contacts or invented zero. |
| TV and current ANEX infant/whole-party route | Preserve canonical authority, provider-specific application and all current4829 tests. Do not repeat the consumed INT supplier case. |
| 430/1280 and short1280×720 | Inspect the affected choice/editor/contact/return frames once on the same corpus; compact context/details/filter layouts do not overlap the footer. |

Reuse the affected existing actual-owner journey/price/late-response tests and the normal final-head compiled CI. Preserve every mandatory gate; extend only the changed boundary, not a second full-search audit. Keyboard/200%/other manual widths retain their current owner-deferred scope. Production, real lead delivery and physical Safari remain separate gates. Use the existing exclusive NEXT publisher/readback only after the changed source is reviewed and its exact-head checks pass.

SEARCH-QUALITY-1 stays in its current audit with the same five criteria and evidence scopes. Donor213–215 has bounded fixture/source/browser acceptance, not a whole-search9.5/10 or an installed score. No source merge/publication is asserted by this documentation packet.

---

# Search3 approved Sites100 transfer — NEXT implementation checkpoint

## Approved whole Site209 hotel-to-contact transfer — 22 October 2026

Preparation claim [6345148273](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6345148273), narrowed test/hash extension6345239100. Base release764c1096; installed ORIGINAL NEXT remains source15d1/runtime0683. Whole-version owner visual+behavior approval was given by the direct reply «давай делать» to the concrete transfer question and recorded in [6347757208](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6347757208). Approved donor is the same PUBLIC Site209/source `bf2f8f9b9108f36823c47457b0fb614c2c16f660`, native version `appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_8140190643648191b9c33e4b988abfd1`, archive SHA256 `6afc6466bc8e78a4e267ed62770562d8f03c91958730461bf31ab5bff1e9cf0b`. Deployment `appgdep_6ad9e80c3ee48191b141d84ac3a8cb76` succeeded; evidence-only source `fbe1847906302f7288feed624eb9dbe5a88db6d7` does not replace the published source. Approval authorizes transfer only to the existing original NEXT after normal technical gates; it is not a publication receipt.

The existing hotel owner keeps a live ANEX group expansion inside its rooms section instead of opening a second offer list. Selecting a room and using the existing footer starts the same provider checks inline. The Tourvisor path checks its exact tour and opens canonical contacts without requesting flight alternatives; an identity-matched current tour/pair is reused. ANEX current-context and additional-price operations stay in the existing controller and safe retained-result contract. Their calculated total remains an estimate, visibly labelled; an explicit continue opens the existing estimated-price contact form. No final-price, supplier latency or HTTP reduction is claimed. Each room retains an optional Conditions and flights action through the original details/package route.

The quote-generation owner rejects late responses after Close or a changed selection. Foreign raw/search/room/party/listing-price facts cannot lend a prior quote; terminal and UNKNOWN results are not retried automatically. Contact drafts and consent are still owned by the unchanged canonical lead modules. No donor fixture, provider adapter, price formula, lead contract, analytics, workflow or runtime file is transferred.

Independent follow-up [5618780665](https://github.com/pyatkoff/poisk-turov-test/pull/4820#pullrequestreview-5618780665) accepted the cleared loading state and approval metadata, but found that an inactive Tourvisor HTTP410 was discarded. The earlier forever-cached quote stub did not match the canonical TV owner; its retained-TV-success/UNKNOWN noReplay claim is withdrawn. The existing quote failure owner now retains an exact terminal result without navigating from another room. Explicit return projects that result and refuses a second quote. Late foreign raw/search/room/listing-price outcomes cannot seal another identity. The regression executes canonical data.quote and the complete runtime-v3 with one deferred intercepted HTTP410, without real supplier HTTP; removing terminal retention fails its noReplay assertion. Explicit recheck after an ignored known success may make another tour request and is not claimed as a saving. ANEX continues to use its existing retained controller.

All five checks and compiled430/1280 affected frames at prior product head9e508 remain historical exact evidence ([6347556067](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6347556067)); final-source CI and limited independent follow-up are required for this terminal-retention repair. Local actual-owner journey, canonical deferred410 regression, unchanged68-case modal digest/76 observations, syntax and whitespace pass. Supplier HTTP and real leads are zero. Release merge, exclusive stock NEXT publication, exact readback and an affected installed scenario remain separate gates. No UI score change, physical browser acceptance or installed publication is asserted.

## Approved whole Site203 journey — 19 October 2026

Whole-version owner approval: [#4217/6270421744](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6270421744). Original PUBLIC project `appgprj_6abcd9299ef88191bbce1fc1bf8c767a`, product `8c826b44bce5713adc04b5d06db19c4f6f3d67cd`, acceptance evidence `dd7a48c0b018bd3b55a69a584de1f50d68b617fd` / [6270378393](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6270378393). Preserve project, history and public audience. This receiver adaptation starts from `c0be08868a9e940860bf7a5b7e5490b58f05aee9`, original NEXT installed source `55fbf6d98db877933ccf63ee2eb67dab2eb82b38`; source claim6270542519 plus controlled test-corpus extension6270605391.

The existing tour body places full hotel/trip/party context in one disclosure while selecting flights. Existing ANEX rows show three pairs followed by a native disclosure for the remainder; full raw leg labels, exact selection and the selected tail pair survive pending calculation. The whole route retains a request with manager-selected flights after a current tour price, optional exact-pair selection, the existing canonical contact step, phone draft and consent reset on changed conditions, and Back/reopen state. The current receiver's canonical data, lead, quote, price, repricing, expiry, history and transport owners remain authoritative. Donor fixtures, rehearsal lead shims and provider contracts are not transferred.

Source preparation is not publication or installed acceptance. Applicable focused/compiled checks and all final-head CI, independent real-frame review, permitted release merge, an exclusive stock NEXT-only publisher, exact readback and installed scenario are required. Donor contact/return quality is bounded `2/2/1.5/2/2=9.5`; it does not score the receiver or the whole product. Physical Safari/iPhone/keyboard/safe area remain not_measured;200% deferred. No supplier requests or real lead delivery are used for this transfer.

Date: 2026-10-06. Existing PR: [4164](https://github.com/pyatkoff/poisk-turov-test/pull/4164). Coordination: #4217; queue: #1646. #3419 is archival. Source writer claim: 6019886183.

The owner accepted the whole fixed interface for isolated preview transfer. Donor is the installed public [Site100](https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site), package34/v154, source `05051662c25cf8258991475e2e7536a12f7248d4`, native version `appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_c787e6e54144819197a0bbd94d678ad9`. Documentary Site102/source `a33878d9798e6f7c1a4ff0a3f245f27254580a27` has identical runtime and `qa/closing34/TRANSFER.md`. Do not replay completed Site saves/deployments or the old prepared patch.

## Implemented scope

Current release base is `b74ac0fea3e0ef2c4c568f4f4db0da9a1002299f`. Target remains `v2/visual-search/index.php -> app.js` on `/_preview/search3-next-candidate/visual-search/`, UI v155. `index.php` and the live graph are unchanged. Existing app/index/styles/mobile-controls/filter-panel and lazy offer-list/hotel-details owners adapt the accepted cumulative form/full calendar, always-open price tape, large 1.8 mobile photos, filters, hotel/exact offers, selected tour/flights, application and return. There is no new design, CSS override layer or runtime renderer owner.

The superseded Site14/O8 prepared patch/manifest are removed from this PR implementation; PR history is retained by incorporating the fresh release as an additional parent. No whole donor app/styles/preview-lead/fixture/parser/lab/mobile-preview transport is applied.

## Verification and limits

Local source and compiled-runtime DOM checks PASS 360/390/430/768/1280, date/child/meal/budget drafts and cancellation, multiple-hotel OR, saved calendar minima, empty/unknown dates, exact RIA 96953 vs 99938 offers and returning to the chosen offer. Existing canonical numeric/identity/quote/stale-response/lead and refactor work proofs remain required. Presentation golden traces change only for the approved markup, confirmation steps, multi-hotel scope and preserving a current flight on passive all-offers return; calendar/price/data oracles are unchanged. The provider-history slice retains its independent historical 203-record digest.

Local final checks PASS: 19 existing semantic/transport/refactor suites, results/rating/summary/calendar owner work, live bridge, migration and compiled asset AST/size checks. The complete form and exact-offer return PASS both source and compiled scripts at all five widths. The form-filter history position loss was corrected in the existing owner. Compiled Chromium then identified a stale group-container reference in the show-more handler: the existing handler now retains bounded pagination and focuses the first newly revealed exact offer, including when it sorts before older rows. The screenshot review also found two missing base rules for the accepted numbered selection steps; they are restored at the existing stylesheet owner, with a browser geometry assertion. The selected-tour browser assertion now reads the approved visible footer price on mobile and the price breakdown on desktop. The browser harness serves the immutable SVG logo with the correct MIME type and uses the visible mobile search control. 236 other pinned files retain their original Git blob hashes. These DOM checks are not layout-engine evidence. The final CI and browser gate must execute on the actual final PR head. Compiled browser evidence is captured by `tests/search3-visual-offer-list-browser.cjs` in `visual-live-evidence/offer-list-lazy/` at all five widths. Compiled Chromium receipt84674310/37512599810 verifies the complete saved demo path, cold retries/history/pagination and actual layout on all five widths, with supplier/lead requests0 and horizontal overflow0; its screens have been inspected. Hotel/terminal checks also pass. The approved quick-budget label/value/chevron geometry now passes. Controlled-live acceptance then found the obsolete hidden mobile hotel-query assertion. The mobile test now retains and types in the approved visible budget editor across late source updates; desktop retains the hotel-query coverage. Both preserve editor identity, focus/caret, cancellation and no repeated search. The controlled live fixture has a single saved photo: its sole desktop thumbnail retains the previous NEXT quarter-width size instead of duplicating the full-size photo. This bounded only-child rule leaves the approved multi-photo gallery and mobile main photo unchanged. The hotel browser matrix now covers all five widths and serves the immutable SVG with the correct MIME. The operator detail navigation is restored from the approved donor in the existing filter-panel owner, including overview scroll and focus on Back; DOM and Chromium cover its separate detail screen, progressive list query and complete-draft application. Stars and budget remain directly visible in the mobile overview. Controlled-live acceptance also replaces the obsolete immediate visibility/group-disclosure fallback with awaiting the actual flat exact row after its cold owner loads, preserving all TV/SAMO/ANEX quote/flight/application assertions. The saved browser matrix includes the operator detail screen and Back at360/390/430/768. Final-head controlled-live CI, final screenshot review and exact installed-artifact readback remain pending. Do not call this document a publication receipt.

Physical iPhone/Safari, keyboard, safe area and 200% text are UNVERIFIED. Demos do not establish live completeness, speed, conversion or real lead delivery. Supplier requests and real applications during these checks: 0.

## Next authorized completion

Finish any confirmed regression within these existing owners; green applicable final-head checks -> inspect the compiled browser artifact -> fresh exclusive preview publisher claim -> normal exact-artifact NEXT preview update/readback -> terminal receipt and release claim. Production/main transfer requires a separate concrete owner decision. Preserve refactor/canonical data/price/fuel/auth/provider/lead/lifecycle/analytics, LOCAL#4191, sibling previews and shared AUTOPILOT state/workflows. Do not restart paused Sites automation or create a new cosmetic queue.


## 2026-10-13 — original Site185 transfer to existing NEXT (implementation candidate)

Owner whole-version visual+behavior approval: direct «давай переносить», recorded
in #4217/6140805640. Donor remains PUBLIC original project
appgprj_6abcd9299ef88191bbce1fc1bf8c767a, Site185/package88/v224,
source dee9043bfce695de275223c417902f393f6c9114, native version
appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_71bf1c751c348191b6790481583f2eaf,
archive SHA256 e7b6c44438eb98e0cebaf774d56c45bd21c9c6a23879edf1d8f5e5a16914f115.
Receiver only /_preview/search3-next-candidate/visual-search/ on
release/search3-production-ready-v1, base919bb3a1aa30a6e50845a7998388f9e0c091f66f.
Installed predecessor source7691cc485fb3b32effe0fc6c28850c9e0d1c8b29.
No design-v2 score, evidence, branch or publisher is part of this transfer.

The existing owners receive the approved card hierarchy and separate exact saved
choice, country/count summaries, thumbnail/geography/stars picker presentation,
hotel-room draft + explicit continue, same-tour flight editing and explicit
application continuation, compact contact review and bounded visible viewport.
Raw canonical catalogue/OR/generation/lookup cache, cold loader fences, incremental
rendering, original numeric/ranking/calendar/list oracles and all transport/money/
fuel/FX/lead owners remain. Two CSS owners only. Immutable brand/media unchanged.

Independent read-only review found and corrected bare-defer live-graph template
serialization, a cold draft-context access, a stale raw-generation selection
strip, lost saved fixture pair on reopen, and old relocated price on provider
receipt expiry. Expiry clears presentation only, without recalculation. Historical
Site100 approval and complete historical test pins remain explicit inputs; changes
to test collaborators/actions apply solely to approved presentation boundaries.

Local source matrices and compiled static offline demo geometry passed at
360/390/430/768/1280, including pair3/whole total190300, explicit continuation and
passive exact saved-choice reopen. The static offline layout harness is not the
PHP live-entry or installed-runtime gate. Final exact-head stock CI, PHP compiled
browser evidence and artifact readback are still required before publication.
No supplier HTTP or real lead delivery was used in this acceptance. Physical
iPhone/Safari/OS keyboard/safe-area/text-only200% remains UNVERIFIED.

SEARCH-QUALITY-1 retains12 historical/11 required rows and five0/1/1.5/2 criteria.
Design-lab mockup, design-lab clickable path and installed NEXT remain independent.
Installed NEXT stays unchanged until publication; no donor score inheritance, no
new quality increase for approval, source adaptation, CI or branch integration.
New NEXT visual/UX criteria remain not_measured until exact installed acceptance.



## Original Site191 approved transfer — 2026-10-14

Owner answered «да» to whole Site191 visual+behavior approval, recorded in #4217/6157263706. Exact donor54f7154ad595544da3dc10ef116cda02483f14ef / PUBLIC projectappgprj_6abcd9299ef88191bbce1fc1bf8c767a; nativeversionappgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_be83bbb7ca8c8191ae26384b09f8c1eb, terminal6157154465. Receiver only existing NEXT release/search3-production-ready-v1 at2012c7df85a7806c976d4d9a8882b0a0d47a91a1, installed712ac9923b93916f375d14a635bd4635e4f52332. Claims6157276748/6157292784; foreignv2 and draft4502 remain separate.

Existing generic TV/demo picker now offers explicit manager handoff for a pair without a whole-tour amount: commits no pair, retains checked offer amount, clears old leg/fuel/baggage metadata; contact/Back/reopen has no implicit pair0; explicit known0 remains valid. New chosen pair clears stale manager metadata. Existing local picker refinements/history and quote/identity gates remain. Falsy malformed flight projection takes existing recoverable error instead of erasing selected offer. Provider-specific ANEX/SAMO flight/application paths and money contracts stay unchanged.

Existing price explanation separates listing, checked offer, and committed pair totals. Provider renderer passes its canonical receipt amount explicitly; generic tour.price is not adopted as provider authority. Pending/error/loading amount cannot become final or zero; draft footer does not rewrite committed facts. No formula, price arithmetic, provider call, lead transport, new renderer/CSS layer, catalogue/mapping/DB, main/workflow or schedule change. 200% remains deferred; no new scenarios.

Focused full-source JSDOM price-origin/provider authority, manager/contact/Back/null/known0, lazy cold loading/history, live bridge/recovery and passive295 exact digest pass. Source compiler preserves AST/protected lead runtime and emits exact derived graph. This is preparation, not CI/merge/publication/runtime acceptance. Existing full CI and compiled ordinary360/390/430/768/1280 journeys must pass on the final head before release and separately claimed stock NEXT publication. Physical Safari not_measured; whole SEARCH-QUALITY-1 score not_measured, no donor score inheritance or points for approval.



## Original Site195 approved transfer — 2026-10-14

Owner answered «переноси» after viewing whole Site195 and the concrete visual+behavior approval question. Approval/source claim #4217/6165931031 and guard extension6165950684 authorize this existing original NEXT transfer after applicable exact-head gates. Exact PUBLIC donor b7475a99bf0e695d7714a316e124e6030fc95a86, package95/v231, native version appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_046a61b8ca24819181e898ac6b47cfcc; archive dbc6ff889d41f21855a2a8a6239dbb42fc3488e320353b351e41e0bd55d8bc49, evidence-only successor2de0334a055b7eae8da490d2ba692e20306a0b0d. Site100/185/191 approval history remains explicit; current approvedPresentation195 is distinct from runtime visualVersion227.

At <=760px, the existing results calendar begins as a compact dates-and-prices disclosure containing the applied departure or range/year. Explicit expansion reveals the same heading, seven-date price rail, saved-price notices and exact-date reset; desktop remains open. The UI-only preference survives render, late prices and desktop/mobile resize without URL/search/filter/offer/price mutation. Existing price/identity/positive-finite merge and generated-root owners remain authoritative. The same source owner shortens the unconfirmed flight-pair caption and keeps280px for the flight-choice list through the existing scrolling-price predicate; other tour/contact viewport rules remain unchanged. Existing NEXT unknown-price wrapping already matches approved Site193, so styles.css is unchanged.

Site194's fixed multi-date synthetic corpus is a design-lab fixture change, not a live NEXT defect; it is not copied. Supplier/lead/receipt contracts and actual source data remain unchanged. Exact receiver base b394e2397f9d5e80e4ebbf11d692901a5803cadb and published predecessor8ae7d9aab3feb6a33559e1bef491b319936a5104/terminal6157886829 remain until publication. Runtime files app.js/index.html/mobile-controls-v1.css are byte-identical to reviewed draft6371de0754bc01e3fe7e44a6bcbd79267a34324f; this acceptance preparation changes metadata and existing test expectations only.

Actual disclosure/viewport VM and all182 previous price sequences/digests plus adjacent month/minimum/facet/raw-data guards pass. Rendering harness retains historical oraclec0e286...; new disclosure nodes are checked separately. Browser runner now expects the approved concise unknown caption without changing amount/action/null/request protections and captures the same unknown-pair journey at ordinary360x480 to prove list/action geometry. Final exact-head source/build/focused/full compiled gates and independent matched ordinary360/390/430/768/1280 plus short360x480 frame review remain required. Inherited existing200% checks may execute in the unchanged stock gate; no new stress scenario or physical claim. Root owns ready transition, permitted release merge and separate exclusive stock original NEXT publication/readback; agent performs no merge/deploy. Supplier searches0, real leads0, production/sibling/foreignv2/workflows/schedules unchanged; physical Safari and overall SEARCH-QUALITY-1 remain unmeasured.


## Original Site197 approved transfer — 2026-10-14

Owner continuation «Давай дальше» immediately follows the complete published
Site197 and its stated whole-version visual+behavior approval condition. This
affirmative instruction is recorded as approval/source claim #4217/6173070018;
consumer correction6173171355 and test alignment6173520386 stay bounded to the
original target. Donor remains PUBLIC original project
appgprj_6abcd9299ef88191bbce1fc1bf8c767a, c2a1939f342bb6ef8cea281414918c155a6277b6,
package97/v233/native197, version
appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_9a642caad5cc8191b501a0ec4ccf9dfb.
Published archive be5c10b326935d7363f066c8a08df80854050aef977911bff641a45222d91c2d;
registered native archive30f92d349f0b21528c6178e5e1b8e5ff6dee71ff3c9edbeddba133513d8ab0f2.
Evidence-only9b42af916ec4e560c312d93757d435e78259cc99 is runtime-identical;
donor terminal6170970686. Approval history100/185/191/195 is retained.

Receiver only /_preview/search3-next-candidate/visual-search/ on
release/search3-production-ready-v1@72a7307975348e58d7a7c696bac968bc8ce876b2;
installed predecessor695b6503be6b304cb3df6dd566c00111bb8f9b90/terminal6166709267.
The predecessor publisher command6166465326 is consumed and never replayed.
No foreign design-v2 artifact, rating, writer or publication is part of this work.

The existing app/card CSS owner receives mobile full-width minimum-offer summary,
compact known/short operator columns, long-operator full rows, fewer unused tracks,
metadata stars/geography on one flex row and explicit title/profile44px access.
Single-offer mobile cards omit the duplicate About footer; multi-offer cards keep
one full-width variants action. Photos retain1.8 and the complete representative
exact offer, facts, price/CTA and saved-selection owners. Tablet variants separate
operator/flight from the total; desktop refinements/sort receive44px targets.
The existing lazy offer-list starts refinements collapsed at every first entry,
while retained history/drafts still restore its explicit state. The form action
reads «Уточнить параметры». The existing quote alert uses a Russian recovery
message; raw error/code, terminal policy, price, retry and application gates stay
untouched. There is no new renderer, stylesheet, overlay or transport owner.

Runtime visualVersion227 remains separate from approved donor233. The receiving
room/meal helpers and all data/parser/selection/lifecycle/quote/flight/money/fuel/
FX/provider/lead/CRM/Metrika/DB/catalogue/auth/URL/payload bodies remain authoritative.
No donor fixture or supplier path is copied. Broad200% remains deferred, with no
new stress test or defect repair. PhysicalSafari/OS keyboard/safe-area are
not_measured, not inferred from Chromium or historical owner screenshots.

Before publication require focused source/identity/oracle checks, every applicable
final-head CI, compiled360/390/430/768/1280 plus existing short-screen journeys,
original-frame inspection, permitted release merge, a separate exclusive stock
NEXT publisher claim for the exact artifact, and exact installed readback/path
acceptance. This section describes implementation, not a publication receipt.
Supplier requests0 and real leads0 during acceptance. Main/production, workflows,
allowlists, secrets, sibling previews and other schedules remain untouched.

SEARCH-QUALITY-1 uses the existing12/11/5 rubric. Donor mockup and clickable scores
retain their exact evidence; receiving source/CI/approval earns no points. The
installed NEXT score remains separate and requires matched rendered/behavior
acceptance after the exact publication; no invented row total or donor inheritance.
