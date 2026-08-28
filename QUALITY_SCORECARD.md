# AnyTour V2 — Pre-traffic quality scorecard

Release target: every material area >= 9/10 before paid traffic is enabled by the owner. Scores are product/UX engineering assessments from current production code, deterministic visual baselines and functional contracts; they are not conversion metrics.

| Area | Current | Evidence / remaining gap | Next material move |
|---|---:|---|---|
| Search UX | 9.0 | Primary hierarchy, destination/date/night/guest controls, advanced-filter summary/reset, guarded dirty lifecycle and date-window ownership are coherent; PR #142 closes the confirmed validation-recovery gap by moving focus to the offending field and marking it invalid | Maintain; regressions only unless new confirmed search friction appears |
| Waiting / progress / recovery | 9.0 | Structured progress ownership, preserved continue-search results, explicit zero-result date ±2 and nights ±1 recovery, manual edit and filter paths are shipped. Recovery changes only update the form and require explicit user submit | Maintain; regressions only unless a new dead end is confirmed |
| Results & comparison | 9.0 | Contextual decision badges, nearest-price delta and lightweight grounded hotel comparison are shipped and responsive | Maintain; improve only from confirmed decision friction |
| Selected tour | 9.0 | Strong checkout hierarchy plus grounded category/rating/sea/meal/room decision summary; responsive selected-tour gates are green | Maintain; deepen only where Tourvisor data supports a clearer choice |
| Flights & price confidence | 9.0 | Grounded price/routing trade-offs plus explicit search-price vs selected-flight-price explanation are shipped | Maintain price/flight contract and watch for real ambiguity |
| Lead UX | 9.0 | Clear no-payment CTA, selected context, guarded submit/recovery and unchanged lead transport contract | Maintain; regressions only unless defect found |
| Mobile UX | 9.0 | Full 375/430 journey audit closed a real summary/progress sticky collision plus sub-44px touch targets in search filters, results filters, selected-tour text actions and flight choice. PR, live, deploy, deterministic baseline and post-deploy viewport checks are green | Maintain full-flow mobile regression coverage; fix only newly confirmed friction |
| Tablet / desktop UX | 8.9 | Repeated 768/1024/1440 and intermediate-width gates are green; control hierarchy is coherent across core surfaces | Audit the complete intermediate/desktop journey for density, wrapping, sticky overlap and action hierarchy; fix confirmed friction only |
| Brand & trust | 8.9 | Grounded AnyTour first-screen proof, offices/contract/payment reassurance and selected-tour confirmation language are present | BR2 consistency across stages; BR5 later after destination verification |
| Visual quality / consistency | 9.0 | PR #128 aligned the main search CTA; PR #130 consolidated primary/secondary control hierarchy across recovery, results, selected tour and mobile sticky search. Production visual evidence is green | Maintain shared hierarchy; regressions only unless a concrete mixed-generation defect is found |
| Product differentiation / competitor gap | 9.0 | PX1/PX2/PX3/PX4/PX5/PX6 provide grounded decision support, recovery, compare, flight/price context and selected-tour depth without speculative data | Maintain; PX7 remains research because it changes persistence/return-intent contract |
| SEO / site foundation | 7.2 | Reusable V2 exists, but current search page is intentionally noindex and not yet a full SEO content architecture | BR4 after all core product areas reach 9 |

## Active priority rule

For the current product-first phase, choose the weakest **core tour-search product** area below 9 before SEO/site expansion. SEO/site foundation remains intentionally sequenced after the search product itself reaches 9 because V2 is the foundation for the later large SEO site.

Current product priority: the remaining tied **8.9 areas**, beginning with **Tablet/Desktop UX**, then Brand/Trust. Improve only confirmed material friction rather than inventing features.

## Evidence behind this re-score

- PR #115: structured continue-search progress ownership and recovery preservation.
- PR #118: nearest returned-price context in results.
- PR #119: explicit zero-result date recovery without silent criteria broadening.
- PR #120: lightweight grounded hotel comparison.
- PR #122: grounded flight price/routing trade-offs.
- PR #123: grounded AnyTour-specific first-screen proof.
- PR #125: explicit selected-tour search-price vs selected-flight-price confidence; production deploy/live smoke and post-deploy visual audit green.
- PR #126: grounded selected-tour decision summary from Tourvisor facts; production deploy/live smoke and visual baselines green.
- PR #128: primary search CTA visual-system correction; production visual baseline green.
- PR #130: unified primary/secondary control hierarchy; production deploy/live smoke/post-deploy visual/baseline green.
- PR #132: explicit zero-result nights ±1 recovery within existing 1–28 bounds; no auto-submit or hidden broadening. All PR gates, V2-only deploy/live smoke, post-deploy visual and deterministic baseline are green.
- PR #134: mobile search-summary/progress sticky collision fixed on <=560px.
- PR #135: mobile advanced-filter toggles normalized from 40px to 44px with visible keyboard focus.
- PR #136: dedicated sticky regression workflow now covers search-progress CSS and asserts the summary-aware stack offset at 375/430.
- PR #138: mobile result-filter chips/choices/close/empty action normalized from 42px to 44px; production deploy green.
- PR #139: selected-tour back/description text actions normalized to 44px on mobile.
- PR #141: flight-choice row normalized from 34px to 44px on mobile; all PR gates, V2-only deploy `33190849173`, active contract/result-detail live checks, deterministic baseline `33190955983` and post-deploy viewport audit `33190955981` are green.
- PR #142: validation now keeps the existing rules but focuses and marks the specific invalid search control, removing the confirmed hunt-for-the-error friction without changing API parameters, analytics or lead transport.
- PR #144: the full Search UX audit caught and removed a duplicate date-bound implementation introduced during the same pass; enhanced date-window constraints remain single-owned by `search-filters-ux-v1.js`. Final production commit `e656a364bde48b77267f535bff34aecea8f27969` completed all eight production/workflow checks without failure, including post-deploy visual `33193311378` and deterministic baseline `33193311547`.

## Re-score rule

After every material user-facing release:
1. verify functional/visual production state;
2. update only affected scores with concrete evidence;
3. choose the weakest core product area below 9;
4. do not use traffic volume or conversion analysis as a gate until the owner explicitly enables paid traffic.
