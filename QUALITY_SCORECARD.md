# AnyTour V2 — Pre-traffic quality scorecard

Release target: every material area >= 9/10 before paid traffic is enabled by the owner. Scores are product/UX engineering assessments from current production code, deterministic visual baselines and functional contracts; they are not conversion metrics.

| Area | Current | Why it is not 9 yet | Next material move |
|---|---:|---|---|
| Search UX | 8.9 | Strong primary flow; advanced filters still need periodic clarity audit | Keep primary task compact; improve only confirmed friction |
| Waiting / progress / recovery | 8.8 | Structured progress is strong and zero-result date recovery is now explicit; more recovery choices would need careful UX | Reassess after core weaker lanes; never silently broaden criteria |
| Results & comparison | 9.0 | Contextual best-price/rating, nearest-price gap and explicit 2–3 hotel comparison now remove most manual comparison work | Protect and regress; improve only if a concrete decision gap remains |
| Selected tour | 8.8 | Strong decision/checkout flow; can deepen grounded hotel/room trade-offs | PX5 grounded decision summary |
| Flights & price confidence | 8.9 | Stable selection plus price delta/direct-vs-connection context; final-price/actualization clarity can still improve | PX3 price-confidence layer at the selected-tour decision point |
| Lead UX | 9.0 | Clear no-payment CTA, context and guarded transport | Maintain contract; regressions only unless defect found |
| Mobile UX | 8.9 | Strong responsive coverage including compare/recovery; continue full-flow visual audits | Fix only confirmed responsive friction |
| Tablet / desktop UX | 8.9 | Strong intermediate-width and desktop coverage after meal and compare work | Continue intermediate-width audits |
| Brand & trust | 8.6 | Real offices and purchase reassurance are strong, but the first impression still uses generic utility language instead of AnyTour-specific proof | BR1/BR2 grounded first-impression pass |
| Visual quality / consistency | 8.8 | High baseline quality; some components still come from different generations | BR3 component/interaction consistency pass |
| Product differentiation / competitor gap | 8.7 | Price decision support, explicit recovery, hotel compare and flight trade-offs are now live; hotel-choice depth and return-intent remain gaps | PX5 hotel decision depth, then research PX7 without changing lead contract |
| SEO / site foundation | 7.2 | Reusable V2 exists, but current search page is intentionally noindex and not yet a full SEO content architecture | BR4 after core product lanes reach 9 |

## Shipped evidence behind this re-score

- PX1.1/PX1.2: result-relative lowest-price/best-rating badges and nearest distinct price gap.
- PX2.1: explicit zero-result “Расширить даты ±2 дня” recovery; criteria change is visible and no search auto-runs.
- PX6/PX1: lightweight comparison of 2–3 hotels from the current result set, no account/persistence/API/sort changes.
- PX3/PX4: flight options show grounded cheapest/+price delta and direct/connection context when routing is known; unknown flights are never inferred.
- General PR branch-bundle gate now exercises the actual ordered branch CSS/JS bundle against the production shell at mobile/tablet/intermediate/desktop widths.
- Automatic traffic audits are disabled in pre-traffic mode and are not development evidence.

## Active priority rule

For the current product-first phase, choose the weakest **core tour-search product** area below 9 before SEO/site expansion. SEO/site foundation is intentionally sequenced after the search product itself reaches 9 because V2 is the foundation for the later large SEO site.

Current product priority: **Brand & trust (8.6)**, followed by Product differentiation / competitor gap (8.7), then Selected tour / Visual quality / Waiting-recovery (8.8).

## Re-score rule

After every material user-facing release:
1. verify functional/visual production state;
2. update only affected scores with concrete evidence;
3. choose the weakest core product area below 9;
4. do not use traffic volume or conversion analysis as a gate until the owner explicitly enables paid traffic.
