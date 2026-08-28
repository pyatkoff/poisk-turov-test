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
| Brand & trust | 9.0 | Trust is now cross-stage rather than isolated: AnyTour-specific first-screen proof and office links, results reassurance before opening a tour, selected-tour no-payment/contract/price-check reassurance and lead CTA clarification are all present. Dedicated trust and selected-tour visual contracts cover 375/430/768/1024/1440 without overflow | Maintain factual trust language; BR5 social/app footer later after exact destination verification |
| Visual quality / consistency | 9.0 | PR #128 aligned the main search CTA; PR #130 consolidated primary/secondary control hierarchy across recovery, results, selected tour and mobile sticky search | Maintain shared hierarchy |
| Product differentiation / competitor gap | 9.0 | PX1/PX2/PX3/PX4/PX5/PX6 provide grounded decision support, recovery, compare, flight/price context and selected-tour depth without speculative data | Maintain; PX7 remains research because it changes persistence/return-intent contract |
| SEO / site foundation | 8.5 | Reusable semantic shell, server-rendered content primitives, country/resort/seasonal page contracts, stable first-party linking, curated registry, structural publishability gate and registered parent/related graph are now shipped. The temporary V2 route remains safely noindex. What is still missing is the real editorial content catalog plus the explicit public mount/canonical/indexing/sitemap policy | Build controlled editorial content-source/catalog tooling next; defer public route/canonical/indexing/sitemap promotion until the final mount decision exists |

## Active priority rule

All core tour-search product areas are at the pre-traffic 9/10 gate. The next weakest material area remains **SEO / site foundation (8.5)**, so BR4 continues while preserving every existing search/conversion contract.

Do not make the current development route indexable. The final public URL/canonical decision remains a separate product/routing choice. Safe autonomous SEO work can continue on the controlled editorial content-source/catalog layer and publication tooling that remain valid regardless of the final mount point.

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
- PR #157: registered parent/related page graph with unknown-reference and cycle rejection.
- Production deploy for PR #157 commit `ebdb8d8e6240283e4b89c05d5e67ff4c5cc1c076` passed V2 validation, copy, verify and live search smoke in deploy run `33206695319`.
- Current PR visual contract audits initial/search-picker/advanced-filter/results states at 375/430/768/1024/1440 and fails on overflow or broken interaction state.
- Current selected-tour visual contract audits long-content facts, flights, lead form, trust/CTA, error recovery and overflow at the same five viewports.

## Re-score rule

After every material user-facing or architecture release:
1. verify relevant functional/visual/production state;
2. update only affected scores with concrete evidence;
3. choose the weakest material area below 9;
4. do not use traffic volume or conversion analysis as a gate until the owner explicitly enables paid traffic.
