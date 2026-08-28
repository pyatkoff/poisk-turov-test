# AnyTour V2 — Brand + Product Experiments Roadmap

This roadmap is the active pre-traffic product plan. Paid/real-user traffic analysis is intentionally out of scope until the owner explicitly decides the product is ready for traffic. Current visitors are the owner/team and must not be treated as conversion evidence.

## Release gate

The core tour-search product has reached the current **>= 9/10 pre-traffic gate** across Search, recovery, results/comparison, selected tour, flights/price, lead UX, mobile, tablet/desktop, Brand/Trust, visual consistency and product differentiation.

The weakest remaining material area is **SEO / site foundation**, now assessed at 8.8. Production breakage, lead loss, incorrect data and severe UX still interrupt roadmap work immediately.

## Brand roadmap

### BR1 — Branded first impression — SHIPPED / MAINTAIN
- Existing AnyTour logo preserved.
- Header/hero/search read as one AnyTour product with grounded first-screen proof.

### BR2 — Trust architecture — SHIPPED / MAINTAIN
- Grounded office/human-support, contract/payment and pre-payment verification language is present across the decision flow.
- Keep factual; no fake urgency, invented counts, guarantees or unverifiable claims.

### BR3 — Product-wide visual identity — SHIPPED / MAINTAIN
- Primary/secondary control hierarchy, CTA treatment, responsive touch targets and cross-stage consistency have dedicated regression coverage.

### BR4 — SEO-ready brand shell — ACTIVE 8.8
- Reusable semantic site footer, content primitives and page contracts are shipped.
- Country/resort/seasonal page types, stable first-party paths, curated registry, publishability quality gate and registered page graph are shipped.
- Controlled editorial content catalog and integration guard are shipped.
- Review-only publication manifest is shipped; it contains approved + publishable editorial candidates only and deliberately has no route/canonical/index/sitemap/schema side effects.
- Current `/poisk-turov-test/v2/` route remains `noindex,follow` with no canonical until the final public URL/mount is explicitly chosen.
- Remaining material work toward 9/10 is real curated production content plus the explicit public URL/mount/indexing contract; avoid inventing more abstraction to simulate completion.

### BR5 — Social + app footer — SHIPPED / MAINTAIN
- Verified AnyTour MAX, Telegram, VK, App Store and Google Play destinations are live in the reusable footer.
- Dedicated regression covers destination integrity, nested phone normalization, 375/768/1440 layout, mobile touch targets and horizontal overflow.
- Keep the block secondary to search/lead actions; do not add/change analytics goals.

## Product experiments / competitor-gap roadmap

### PX1 — Decision support in results — SHIPPED / MAINTAIN
- Grounded lowest-price/best-rating badges and nearest-price context are shipped.
- Lightweight comparison is shipped.

### PX2 — Flexible search / recovery — SHIPPED / MAINTAIN
- Explicit zero-result date ±2 and nights ±1 recovery is shipped.
- Criteria are never silently broadened and recovery never auto-submits.

### PX3 — Price confidence — SHIPPED / MAINTAIN
- Search price vs selected-flight recalculation is explained and final manager confirmation before payment is explicit.

### PX4 — Flight decision quality — SHIPPED / MAINTAIN
- Grounded direct/connection and price-delta context is shipped while preserving selection/price synchronization.

### PX5 — Hotel choice depth — SHIPPED / MAINTAIN
- Grounded selected-tour decision summary uses actual rating/category/sea/meal/room facts only.

### PX6 — Save / compare / resume — PARTIALLY SHIPPED / MAINTAIN
- Lightweight 2–3 hotel comparison without registration is shipped.
- Broader persistence/resume is intentionally not added until a durable product contract is justified.

### PX7 — Price watch / return intent — RESEARCH
- Keep research-only until persistence, contact permission, notification channel and product-contract choices are explicit.

## Current BR4 boundary

1. Keep the temporary V2 search route non-indexable and protect search-state URLs.
2. Maintain the route-independent shell, page contracts, curated editorial catalog and publication-review manifest.
3. Do not derive page identities from arbitrary request/search parameters.
4. Choose the final public URL/mount only as a separate explicit routing/product decision.
5. Add canonical/indexing/sitemap/structured-data policy only after real public page types and URLs exist.
6. Re-run full search/conversion contracts before any public indexing change.
7. If the public-route decision remains deferred, continue independent whole-product audits rather than adding framework-only SEO layers.

## Evidence gates before traffic

For user-facing changes require, as applicable:
1. deterministic logic/functional validation;
2. existing V2 CI green;
3. visual evidence on mobile/intermediate/desktop;
4. production deploy only after green checks;
5. production smoke/contract verification;
6. score/re-audit the affected area and continue if it remains below 9/10.

Traffic/conversion analysis is not a development gate and must not appear in the active queue until explicitly re-enabled by the owner.
