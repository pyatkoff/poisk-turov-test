# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize visual convergence with the owner-confirmed AnyTour Design System 2.0 mockup. Technical refactor may support reliability and consolidation but must not preempt the active DS2 implementation direction.

## Canonical visual reference

The canonical target is the composite desktop/mobile AnyTour Design System 2.0 mockup supplied and reconfirmed by the owner on 2026-08-31. Treat the mockup—not current production CSS—as the visual source of truth when they diverge.

Target language: compact unified white header; light travel-first canvas; large photographic travel hero with search as the primary first-screen object; white cards with subtle borders/shadows; blue primary UI with orange conversion CTA; dense but clean search-results layout; coherent hotel and destination pages; full dark footer; mobile as a compact continuation of desktop. Preserve the existing AnyTour logo.

## Material progress

Structural DS2 convergence is now present across the main search journey. The homepage first screen is light/travel-first with search as the primary object; the shared white header is compact across desktop/mobile; `/poisk-turov/` uses the compact light DS2 intro; and desktop live results now switch into a two-column DS2 composition with a compact sticky search/filter rail and a lighter hotel-card column while widths at and below 1024px retain the established one-column flow. The results slice passed all PR regressions, deployed successfully to anytoour.ru and passed post-deploy visual validation.

The production live journey is also green after correcting the smoke for real Tourvisor inventory volatility. A result can become sold between search results and the flights call; the smoke now checks a bounded set of returned tours and skips only the documented stale/sold `error.code=2` case. Malformed flight responses, unexpected errors, broken tour/hotel data, missing viable inventory and lead-health failures still fail the journey. Runtime Tourvisor, Metrika and lead contracts were not changed.

The selected-tour DS2 detail-state is implemented on PR #577 as a CSS-only presentation layer. Its dedicated selected-tour regression is green. Generic visual checks are being refreshed after the newly merged results layout changed the expected desktop results baseline; do not merge #577 until those checks are green.

## Ordered work

1. Finish selected hotel/tour DS2 convergence and live verification.
2. Bring country, hot and editorial pages onto the same DS2 language.
3. Replace the homepage/country scenic placeholder with an approved repository/local travel image when one is available; do not hotlink an arbitrary external asset.
4. Continue shared-shell consistency for header/footer, typography, spacing, buttons, cards and breadcrumbs.
5. Validate each slice at 375/430/768/1024/1440 and preserve search/recovery/results/flight/price/lead regressions.

## Current resume point

Finish PR #577 only after its refreshed generic visual checks are green, then deploy and verify the selected-tour detail state. Next audit and align `/country/` plus a representative destination, then `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/`. Keep the dark shared footer as the site-wide endpoint. A photography-led homepage/country hero remains intentionally deferred until an approved local/repository travel image exists; do not regress into oversized blue heroes or substitute an unapproved external image.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, Tourvisor contract, or external lead-sending contract/field mapping. Preserve verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate architecture safe.
