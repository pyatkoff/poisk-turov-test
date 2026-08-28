# AnyTour V2 — Brand + Product Experiments Roadmap

This roadmap is active in parallel with live conversion evidence. Brand and competitor-gap work does **not** wait for meaningful production funnel volume when a change is safe, reversible and independently verifiable.

## Operating rule

Production breakage, lead loss, incorrect data and severe UX always interrupt this roadmap. Otherwise, continue the highest-value safe item and validate it on mobile, intermediate and desktop widths before release.

## Brand roadmap

### BR1 — Branded first impression — ACTIVE
- Keep the existing AnyTour logo; do not redesign or replace it.
- Make the header/hero feel like one coherent AnyTour product rather than an embedded search utility.
- Strengthen clear value proposition, human assistance and purchase confidence without adding clutter.
- Preserve mobile search focus and avoid pushing the primary form too far below the fold.

### BR2 — Trust architecture — ACTIVE
- Real offices/reviews and clear human support.
- Contract/payment expectations stated at decision points, not repeated everywhere.
- Explain what AnyTour verifies before payment: availability, flight, baggage and final price.
- Avoid unsupported claims, fake urgency, invented review counts or unverifiable guarantees.

### BR3 — Product-wide visual identity — QUEUED
- Consistent typography, spacing, chips/badges, cards, actions and trust language across search/results/selected-tour/lead.
- Any brand treatment must remain subordinate to the tour-selection task.

### BR4 — SEO-ready brand shell — QUEUED
- Keep V2 components reusable for future destination/hotel/landing pages.
- Avoid architecture that requires duplicating search/results logic for SEO pages.

## Product experiments / competitor-gap roadmap

### PX1 — Decision support in results — ACTIVE
Goal: reduce manual comparison work inside the result list.

Experiments:
- Contextual result badges such as lowest current price / best current rating, based only on the returned result set.
- Make badges descriptive, not promotional; never change underlying sort/order invisibly.
- Next candidates: useful price delta context, concise trade-off summaries, comparison shortlist.

### PX2 — Flexible search / recovery — QUEUED
- Better alternatives when exact criteria produce few/no tours.
- Controlled date/night/filter relaxation with explicit user-visible changes.
- Never silently broaden a user's request.

### PX3 — Price confidence — QUEUED
- Explain what the shown price represents and what is confirmed before purchase.
- Surface price changes after actualization clearly.
- Candidate: price-change/availability status as an explicit selected-tour decision layer.

### PX4 — Flight decision quality — QUEUED
- Make flight choice easier by surfacing useful trade-offs: departure time, direct/charter, baggage and price delta.
- Avoid changing flight-selection semantics already covered by regression tests.

### PX5 — Hotel choice depth — QUEUED
- Stronger decision facts using data already returned: rating, sea distance, location, room/meal context.
- Candidate: compact “why choose / consider” summary only when grounded in actual data.

### PX6 — Save / compare / resume — QUEUED
- Explore lightweight shortlist/compare behavior that does not require account creation.
- Preserve URL/shareability where practical.

### PX7 — Price watch / return intent — RESEARCH
Competitor gap: current major tour-search products expose price-drop notifications/return-intent mechanics. Research a version appropriate for AnyTour before implementation; this may require persistence/contact decisions and is not an autonomous write until the product/lead contract is clear.

## Current experiment

PX1.1 — contextual result decision badges:
- “Самая низкая цена” for the minimum valid hotel price in the current returned result set.
- “Лучший рейтинг” for the maximum valid hotel rating in the same set.
- May appear together when one hotel genuinely has both properties.
- Suppressed for a one-item result set because there is no meaningful comparison.
- No API requests, analytics changes, sorting changes, lead changes or hidden personalization.

## Evidence gates

For user-facing experiments require, as applicable:
1. deterministic logic check;
2. existing V2 functional CI green;
3. visual PR evidence at mobile/intermediate/desktop widths;
4. production deploy only after green checks;
5. live smoke after deploy;
6. later real-user funnel evidence may keep, revise or remove an experiment.
