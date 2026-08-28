# AnyTour V2 — Pre-traffic quality scorecard

Release target: every material area >= 9/10 before paid traffic is enabled by the owner. Scores are product/UX engineering assessments from current production code, deterministic visual baselines and functional contracts; they are not conversion metrics.

| Area | Current | Evidence / remaining gap | Next material move |
|---|---:|---|---|
| Search UX | 8.9 | Strong primary flow and guarded lifecycle; no new confirmed primary-search defect in the latest whole-flow passes | Keep primary task compact; improve only confirmed friction |
| Waiting / progress / recovery | 8.9 | Primary and continue-search now share structured progress ownership; explicit zero-result date recovery shipped, but broader recovery choices are still limited | PX2: inspect nights/filter recovery only where it reduces confirmed dead ends |
| Results & comparison | 9.0 | Contextual decision badges, nearest-price delta and lightweight grounded hotel comparison are shipped and responsive | Maintain; improve only from confirmed decision friction |
| Selected tour | 9.0 | Strong checkout hierarchy plus grounded category/rating/sea/meal/room decision summary; responsive selected-tour gates are green | Maintain; deepen only where Tourvisor data supports a clearer choice |
| Flights & price confidence | 9.0 | Grounded price/routing trade-offs plus explicit search-price vs selected-flight-price explanation are shipped | Maintain price/flight contract and watch for real ambiguity |
| Lead UX | 9.0 | Clear no-payment CTA, selected context, guarded submit/recovery and unchanged lead transport contract | Maintain; regressions only unless defect found |
| Mobile UX | 8.9 | Repeated 375/430 selected-tour and full baseline checks are green, but complete flow still warrants periodic small-screen audit | Full-flow mobile audit; fix only confirmed friction |
| Tablet / desktop UX | 8.9 | Repeated 768/1024/1440 and intermediate-width gates are green; visual generations still need consolidation | BR3 consistency pass across core surfaces |
| Brand & trust | 8.9 | Grounded AnyTour first-screen proof, offices/contract/payment reassurance and selected-tour confirmation language are present | BR2/BR3 consistency across stages; BR5 later |
| Visual quality / consistency | 8.8 | Baseline quality is high and responsive gates are stable, but components still reflect multiple visual generations | BR3 design-token/component consolidation on core flow |
| Product differentiation / competitor gap | 9.0 | PX1/PX2/PX3/PX4/PX5/PX6 now provide grounded decision support, recovery, compare, flight/price context and selected-tour depth without speculative data | Maintain; PX7 remains research because it changes persistence/return-intent contract |
| SEO / site foundation | 7.2 | Reusable V2 exists, but current search page is intentionally noindex and not yet a full SEO content architecture | BR4 after all core product areas reach 9 |

## Active priority rule

For the current product-first phase, choose the weakest **core tour-search product** area below 9 before SEO/site expansion. SEO/site foundation remains intentionally sequenced after the search product itself reaches 9 because V2 is the foundation for the later large SEO site.

Current product priority: **Visual quality / consistency (8.8)** via BR3. After that, re-audit the tied 8.9 areas (Search UX, Waiting/recovery, Mobile, Tablet/Desktop, Brand/Trust) and improve only confirmed material friction rather than adding speculative features.

## Evidence behind this re-score

- PR #115: structured continue-search progress ownership and recovery preservation.
- PR #118: nearest returned-price context in results.
- PR #119: explicit zero-result date recovery without silent criteria broadening.
- PR #120: lightweight grounded hotel comparison.
- PR #122: grounded flight price/routing trade-offs.
- PR #123: grounded AnyTour-specific first-screen proof.
- PR #125: explicit selected-tour search-price vs selected-flight-price confidence; production deploy/live smoke and post-deploy visual audit green.
- PR #126: grounded selected-tour decision summary from Tourvisor category/rating/sea/meal/room facts; PR functional/branch-bundle/selected-tour visual gates green and V2-only deploy/live search smoke green.

## Re-score rule

After every material user-facing release:
1. verify functional/visual production state;
2. update only affected scores with concrete evidence;
3. choose the weakest core product area below 9;
4. do not use traffic volume or conversion analysis as a gate until the owner explicitly enables paid traffic.
