# AnyTour V2 — Brand + Product Experiments Roadmap

This roadmap is the active pre-traffic product plan. Paid/real-user traffic analysis is intentionally out of scope until the owner explicitly decides the product is ready for traffic. Current visitors are the owner/team and must not be treated as conversion evidence.

## Release gate

The core tour-search product has reached the current **>= 9/10 pre-traffic gate** across Search, recovery, results/comparison, selected tour, flights/price, lead UX, mobile, tablet/desktop, Brand/Trust, visual consistency and product differentiation.

The weakest remaining material area is **SEO / site foundation**, so BR4 is now the active development lane. Production breakage, lead loss, incorrect data and severe UX still interrupt roadmap work immediately.

## Brand roadmap

### BR1 — Branded first impression — SHIPPED / MAINTAIN
- Existing AnyTour logo preserved.
- Header/hero/search now read as one AnyTour product with grounded first-screen proof.
- Improve only if a confirmed cross-stage brand inconsistency appears.

### BR2 — Trust architecture — SHIPPED / MAINTAIN
- Grounded office/human-support, contract/payment and pre-payment verification language is present across the decision flow.
- Keep factual; no fake urgency, invented counts, guarantees or unverifiable claims.

### BR3 — Product-wide visual identity — SHIPPED / MAINTAIN
- Primary/secondary control hierarchy, CTA treatment, responsive touch targets and cross-stage consistency have dedicated regression coverage.
- Continue regression maintenance rather than cosmetic churn.

### BR4 — SEO-ready brand shell — ACTIVE
- Reusable semantic site footer shipped in PR #147.
- Route-independent server-rendered SEO content primitives shipped in PR #148.
- Reusable landing-page data contract/composer shipped in PR #149.
- Current `/poisk-turov-test/v2/` route must remain `noindex,follow` with no canonical until the final public URL/mount is explicitly chosen.
- Continue building reusable country/resort/seasonal page architecture without duplicating search/results logic or publishing arbitrary search-state combinations.

### BR5 — Social + app footer — DEFERRED / VERIFIED-URL DEPENDENCY
- Add polished MAX / Telegram / VK and mobile-app destinations to the reusable footer only from exact verified destination URLs.
- Verified social destinations can be retained for later integration; exact App Store and Google Play store URLs must be recovered/confirmed before shipping the complete block.
- Keep this secondary to navigation/search/lead actions and do not add/change analytics goals.

## Product experiments / competitor-gap roadmap

### PX1 — Decision support in results — SHIPPED / MAINTAIN
- Grounded lowest-price/best-rating badges and nearest-price context are shipped.
- Lightweight comparison is shipped.
- Sorting remains explicit and unchanged by badges.

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

## Current BR4 sequence

1. Keep the temporary V2 route non-indexable and protect its search-state boundary.
2. Build reusable route-independent page shell/content primitives.
3. Define reusable page data contracts/templates for country/resort/seasonal pages.
4. Define internal-linking and search-handoff patterns without making search-state URLs indexable.
5. Choose the final public URL/mount only as a separate explicit routing/product decision.
6. Add canonical/indexing/structured-data policy only after the real public page types and URLs exist.
7. Re-run full search/conversion contracts before any public indexing change.

## Evidence gates before traffic

For user-facing changes require, as applicable:
1. deterministic logic/functional validation;
2. existing V2 CI green;
3. visual evidence on mobile/intermediate/desktop;
4. production deploy only after green checks;
5. production smoke/contract verification;
6. score/re-audit the affected area and continue if it remains below 9/10.

Traffic/conversion analysis is not a development gate and must not appear in the active queue until explicitly re-enabled by the owner.
