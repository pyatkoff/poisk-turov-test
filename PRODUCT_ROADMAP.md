# AnyTour V2 — Brand + Product Experiments Roadmap

This roadmap is the active pre-traffic product plan. Paid/real-user traffic analysis is intentionally out of scope until the owner explicitly decides the product is ready for traffic. Current visitors are the owner/team and must not be treated as conversion evidence.

## Release gate

Do not wait for funnel volume. Keep developing until every material product area is independently assessed at **>= 9/10**:

- Search UX
- Waiting/progress/recovery
- Results & comparison
- Selected tour
- Flights & price confidence
- Lead UX
- Mobile UX
- Tablet/desktop UX
- Brand & trust
- Visual quality/consistency
- Product differentiation / competitor gap
- SEO/site foundation

Production breakage, lead loss, incorrect data and severe UX always interrupt roadmap work. Otherwise take the weakest sub-9 area, improve it materially, validate mobile/intermediate/desktop, then reassess.

## Brand roadmap

### BR1 — Branded first impression — ACTIVE
- Keep the existing AnyTour logo; do not redesign or replace it.
- Make header/hero/search feel like one coherent AnyTour product rather than an embedded utility.
- Strengthen value proposition, human assistance and purchase confidence without clutter or pushing search below the fold.

### BR2 — Trust architecture — ACTIVE
- Real offices/reviews and clear human support where grounded in available data/assets.
- Contract/payment expectations at decision points.
- Explain what AnyTour verifies before payment: availability, flight, baggage and final price.
- No fake urgency, invented review counts, unsupported guarantees or unverifiable claims.

### BR3 — Product-wide visual identity — ACTIVE
- Consistent typography, spacing, chips/badges, cards, actions and trust language across search/results/selected-tour/lead.
- Brand treatment remains subordinate to choosing a tour.

### BR4 — SEO-ready brand shell — QUEUED
- Keep V2 components reusable for destination/hotel/landing pages.
- Avoid duplicating search/results logic for future SEO pages.

## Product experiments / competitor-gap roadmap

### PX1 — Decision support in results — ACTIVE
- Contextual badges from returned data only.
- Next: useful price delta context, concise trade-off summaries and comparison shortlist.
- Never invisibly change sort/order.

### PX2 — Flexible search / recovery — QUEUED
- Better alternatives for few/no tours.
- Explicit date/night/filter relaxation only; never silently broaden criteria.

### PX3 — Price confidence — QUEUED
- Explain shown price and what is confirmed before purchase.
- Surface actualization/price changes clearly.

### PX4 — Flight decision quality — QUEUED
- Surface departure time, direct/charter, baggage and price delta trade-offs.
- Preserve tested flight-selection semantics.

### PX5 — Hotel choice depth — QUEUED
- Stronger grounded decision facts: rating, sea distance, location, room and meal context.
- Compact “why choose / consider” summary only from actual data.

### PX6 — Save / compare / resume — QUEUED
- Lightweight shortlist/compare without account creation.
- Preserve URL/shareability where practical.

### PX7 — Price watch / return intent — RESEARCH
- Research only until persistence/contact/product-contract choices are explicit.

## Current shipped experiment

PX1.1 contextual result decision badges:
- “Самая низкая цена” for minimum valid hotel price in a multi-hotel result set.
- “Лучший рейтинг” for maximum valid hotel rating.
- May coexist on one hotel.
- Suppressed for one-item result sets.
- No API, analytics, sorting, lead or personalization changes.

## Evidence gates before traffic

For user-facing changes require, as applicable:
1. deterministic logic/functional validation;
2. existing V2 CI green;
3. visual evidence on mobile/intermediate/desktop;
4. production deploy only after green checks;
5. production smoke/contract verification;
6. score/re-audit the affected area and continue if it remains below 9/10.

Traffic/conversion analysis is not a development gate and must not appear in the active queue until explicitly re-enabled by the owner.