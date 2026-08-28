# AnyTour V2 — Pre-traffic quality scorecard

Release target: every material core area >= 9/10 before paid traffic is enabled by the owner. Scores are product/UX engineering assessments from production code, deterministic visual baselines and functional contracts; they are not conversion metrics.

| Area | Current | Evidence / remaining gap | Next material move |
|---|---:|---|---|
| Search UX | 9.0 | Primary hierarchy, validation recovery, advanced filters and dirty lifecycle are coherent and regression-covered | Maintain |
| Waiting / progress / recovery | 9.0 | Structured progress, preserved continue-search results and explicit zero-result recovery are shipped | Maintain |
| Results & comparison | 9.0 | Grounded badges, price delta and hotel comparison are shipped | Maintain |
| Selected tour | 9.0 | Checkout hierarchy and grounded decision summary are shipped | Maintain |
| Flights & price confidence | 9.0 | Routing trade-offs and selected-flight price explanation are shipped | Maintain |
| Lead UX | 9.0 | Clear no-payment CTA, selected context and guarded recovery with unchanged lead transport | Maintain |
| Mobile UX | 9.0 | 375/430 journey and touch/sticky contracts are green | Maintain |
| Tablet / desktop UX | 9.0 | 768/1024/1440 full-flow contracts are green | Maintain |
| Brand & trust | 9.0 | Cross-stage factual trust plus verified social/app footer are shipped | Maintain factual language |
| Visual quality / consistency | 9.0 | Shared primary/secondary hierarchy and responsive contracts are green | Maintain |
| Product differentiation / competitor gap | 9.0 | PX1–PX6 provide grounded decision support without speculative data | Maintain |
| SEO / site foundation | 8.8 | Route-independent shell/primitives/page types, stable links, curated registry, publishability gate, relationship graph and controlled editorial catalog with draft/review/approved workflow are shipped. Temporary V2 remains noindex. Missing: real curated content inventory and explicit public mount/canonical/indexing/sitemap policy | Prepare real editorial inventory only from approved factual content; public promotion waits for explicit mount decision |

## Active priority rule

All core tour-search and Brand/Product areas are at the pre-traffic 9/10 gate. The weakest material area remains **SEO / site foundation (8.8)**.

Do not make the current development route indexable. The final public URL/canonical decision remains a separate routing/product choice. Safe autonomous work may improve content governance and validation, but must not invent destination copy or derive indexable pages from search parameters.

## Evidence behind the current SEO score

- PR #147–#149: reusable semantic footer, SEO content primitives and landing-page contract.
- PR #151/#152/#156: stable first-party internal/related-link boundaries excluding query/hash/search state.
- PR #153: explicit country/resort/seasonal page types with editorial H1 ownership.
- PR #154: curated clean-path page registry.
- PR #155: structural publishability gate rejecting thin/incomplete candidates and transient search state.
- PR #157: registered parent/related graph with unknown-reference and cycle rejection.
- PR #160/#163: hardened breadcrumb-chain validation and catalog integration coverage.
- PR #161: controlled editorial catalog with stable IDs, draft/review/approved states and publication candidates only for approved + publishable records.
- PR #164: verified MAX/Telegram/VK/App Store/Google Play footer destinations; dedicated 375/768/1440 footer regression.
- PR #164 production deploy run `33207695145` passed V2 validation, copy, verify and live search smoke; post-deploy visual run `33207793430` and baseline run `33207793424` passed.

## Re-score rule

After every material user-facing or architecture release:
1. verify relevant functional/visual/production state;
2. update only affected scores with concrete evidence;
3. choose the weakest material area below 9;
4. do not use traffic volume or conversion analysis as a gate until the owner explicitly enables paid traffic.
