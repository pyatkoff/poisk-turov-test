# AnyTour V2 — Pre-traffic quality scorecard

Release target: every material core tour-search area >= 9/10 before paid traffic is enabled by the owner. Scores are product/UX engineering assessments from current production code, deterministic visual baselines and functional contracts; they are not conversion metrics.

| Area | Current | Evidence / remaining gap | Next material move |
|---|---:|---|---|
| Search UX | 9.0 | Primary hierarchy, destination/date/night/guest controls, advanced-filter summary/reset, guarded dirty lifecycle and date-window ownership are coherent; PR #142 closes the confirmed validation-recovery gap | Maintain; regressions only unless new confirmed search friction appears |
| Waiting / progress / recovery | 9.0 | Structured progress ownership, preserved continue-search results, explicit zero-result date ±2 and nights ±1 recovery, manual edit and filter paths are shipped | Maintain; regressions only unless a new dead end is confirmed |
| Results & comparison | 9.0 | Contextual decision badges, nearest-price delta and lightweight grounded hotel comparison are shipped and responsive | Maintain; improve only from confirmed decision friction |
| Selected tour | 9.0 | Strong checkout hierarchy plus grounded category/rating/sea/meal/room decision summary; responsive selected-tour gates are green | Maintain; deepen only where Tourvisor data supports a clearer choice |
| Flights & price confidence | 9.0 | Grounded price/routing trade-offs plus explicit search-price vs selected-flight-price explanation are shipped | Maintain price/flight contract and watch for real ambiguity |
| Lead UX | 9.0 | Clear no-payment CTA, selected context, guarded submit/recovery and unchanged lead transport contract | Maintain; regressions only unless defect found |
| Mobile UX | 9.0 | Full 375/430 journey audit closed sticky collisions and sub-44px touch targets; production visual/regression coverage is green | Maintain full-flow mobile regression coverage |
| Tablet / desktop UX | 9.0 | The complete core journey is covered at 768/1024/1440 by the primary visual contract and selected-tour checkout contract, including long titles, pickers, advanced filters, results, flights, lead recovery and horizontal-overflow checks. No confirmed density/wrapping/sticky/action-hierarchy defect remained in the focused audit | Maintain five-viewport regression coverage; fix only newly confirmed intermediate/desktop friction |
| Brand & trust | 9.0 | Trust is cross-stage: AnyTour-specific first-screen proof and office links, results reassurance, selected-tour no-payment/contract/price-check reassurance, clear lead CTA, plus shipped verified MAX/Telegram/VK/App Store/Google Play footer. The structured PHONE production defect discovered after BR5 was fixed for footer/header and is now live-regression guarded | Maintain factual trust language, destinations and contact-shell regressions |
| Visual quality / consistency | 9.0 | PR #128 aligned the main search CTA; PR #130 consolidated primary/secondary control hierarchy across recovery, results, selected tour and mobile sticky search. Five-viewport post-deploy evidence remains green | Maintain shared hierarchy and full-page viewport regression |
| Product differentiation / competitor gap | 9.0 | PX1/PX2/PX3/PX4/PX5/PX6 provide grounded decision support, recovery, compare, flight/price context and selected-tour depth without speculative data | Maintain; PX7 remains research because it changes persistence/return-intent contract |
| SEO / site foundation | 8.8 | Route-independent foundation now includes semantic shell/footer, server-rendered page primitives, country/resort/seasonal contracts, stable first-party link boundaries, curated registry, structural publishability gate, registered relationship graph, controlled draft/review/approved editorial catalog and deterministic approved+publishable-only publication-review manifest. The temporary V2 route remains safely noindex with no canonical | Remaining path to 9 is real curated production content inventory plus the explicit final public mount/URL and canonical/indexing/sitemap policy; do not add framework-only layers or make the development search route indexable |

## Active priority rule

All eleven core tour-search product areas are at the pre-traffic 9/10 gate. The weakest material area remains **SEO / site foundation (8.8)**, but its remaining route-independent architecture is already mature.

Do not inflate SEO to 9.0 from tooling alone. The material remaining gap is a real curated production content inventory plus the explicit public URL/mount/canonical/indexing/sitemap policy. The current `/poisk-turov-test/v2/` development search route must remain `noindex,follow` with no canonical.

While that product/routing choice is deferred, autonomous work continues with independent whole-V2 production/data/UX/responsive audits and confirmed regressions rather than inventing more SEO framework abstraction.

## Evidence behind this re-score

- PR #115: structured continue-search progress ownership and recovery preservation.
- PR #118: nearest returned-price context in results.
- PR #119/#132: explicit zero-result date and nights recovery without silent criteria broadening.
- PR #120: lightweight grounded hotel comparison.
- PR #122/#125: grounded flight trade-offs and selected-flight price confidence.
- PR #123: grounded AnyTour-specific first-screen proof.
- PR #126: grounded selected-tour decision summary from Tourvisor facts.
- PR #128/#130: primary CTA and product-wide control consistency.
- PR #134–#141: full mobile audit and touch/sticky fixes.
- PR #142/#144: invalid-field recovery and single-owned date-window behavior.
- PR #147–#149: reusable semantic footer, SEO page primitives and landing-page contract.
- PR #151/#152/#156: stable first-party internal/related-link boundaries with query/hash/search-state exclusion.
- PR #153: explicit country/resort/seasonal page-type adapters with editorial H1 requirement rather than guessed Russian inflection.
- PR #154: curated clean-path SEO page registry that rejects arbitrary request/search-state URL identity.
- PR #155: structural publishability gate rejecting thin/incomplete page candidates and transient date/night/hotel/operator search state.
- PR #157/#158/#160: registered relationship graph plus centralized stable-path and hardened breadcrumb publication boundaries.
- PR #161/#163: controlled editorial content catalog with draft/review/approved lifecycle and integrated approved+publishable candidate gating.
- PR #164: verified social/app footer for MAX, Telegram, VK, App Store and Google Play with responsive/touch coverage.
- PR #166/#168: fixed the production structured-PHONE `Array` rendering defect in footer/header; restored the live SEO bundle contract to validate the compiled stylesheet actually served by V2.
- PR #169: deterministic route-independent publication-review manifest containing only approved + publishable editorial records, with no route/canonical/index/sitemap/schema side effects.
- Production for PR #169 commit `29cc99b59e156d6ad2e6c7c64eff5eb8d2496caa` passed V2 validation, copy, verify and live search smoke in deploy run `33208974160`; tour-live and result-detail-live were also green.
- PR #172: five-viewport live post-deploy regression now explicitly requires valid digit-bearing `tel:` controls in both header and footer and rejects literal `Array` contact rendering.
- Current visual contracts audit initial/search-picker/advanced-filter/results and selected-tour/flight/lead states across 375/430/768/1024/1440 and fail on material overflow or broken interaction state.

## Re-score rule

After every material user-facing or architecture release:
1. verify relevant functional/visual/production state;
2. update only affected scores with concrete evidence;
3. choose the weakest material area below 9;
4. if that area is blocked by an explicit product/routing choice, continue independent production/data/UX/responsive audits instead of adding framework-only work;
5. do not use traffic volume or conversion analysis as a gate until the owner explicitly enables paid traffic.
