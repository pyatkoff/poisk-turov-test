# AnyTour V2 — Pre-traffic quality scorecard

Release target: every material area >= 9/10 before paid traffic is enabled by the owner. Scores are product/UX engineering assessments from current production code, deterministic visual baselines and functional contracts; they are not conversion metrics.

| Area | Current | Why it is not 9 yet | Next material move |
|---|---:|---|---|
| Search UX | 8.9 | Strong primary flow; advanced filters still need periodic clarity audit | Keep primary task compact; improve only confirmed friction |
| Waiting / progress / recovery | 8.5 | Good state ownership, but recovery can offer more explicit alternatives | PX2 explicit date/night/filter alternatives |
| Results & comparison | 8.4 | Good cards and contextual badges; comparison still requires mental arithmetic | PX1 price-delta context, then shortlist/compare |
| Selected tour | 8.8 | Strong decision/checkout flow; can deepen grounded hotel/room trade-offs | PX5 grounded decision summary |
| Flights & price confidence | 8.6 | Flight selection is stable; price/flight trade-offs can be easier to scan | PX3/PX4 explicit price and flight deltas |
| Lead UX | 9.0 | Clear no-payment CTA, context and guarded transport | Maintain contract; regressions only unless defect found |
| Mobile UX | 8.9 | Strong responsive coverage; continue full-flow visual audits | Fix only confirmed responsive friction |
| Tablet / desktop UX | 8.9 | Strong nine-width coverage after meal fix | Continue intermediate-width audits |
| Brand & trust | 8.6 | Strong AnyTour shell/offices/trust, but brand language can be more coherent across stages | BR1/BR2/BR3 consistency pass |
| Visual quality / consistency | 8.8 | High baseline quality; some components still come from different generations | BR3 design-token/component consolidation |
| Product differentiation / competitor gap | 7.9 | Decision support has started, but comparison/recovery/price-watch depth is still limited | PX1 → PX2 → PX3/PX4/PX5/PX6 |
| SEO / site foundation | 7.2 | Reusable V2 exists, but current search page is intentionally noindex and not yet a full SEO content architecture | BR4 after core product lanes reach 9 |

## Active priority rule

For the current product-first phase, choose the weakest **core tour-search product** area below 9 before SEO/site expansion. SEO/site foundation is intentionally sequenced after the search product itself reaches 9 because V2 is the foundation for the later large SEO site.

Current product priority: **Product differentiation / competitor gap (7.9)**, with Results & comparison (8.4) as the first implementation surface.

## Re-score rule

After every material user-facing release:
1. verify functional/visual production state;
2. update only affected scores with concrete evidence;
3. choose the weakest core product area below 9;
4. do not use traffic volume or conversion analysis as a gate until the owner explicitly enables paid traffic.
