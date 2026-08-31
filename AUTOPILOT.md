# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize site-wide visual unification under AnyTour Design System 2.0. Design System 1.0 is legacy terminology and must not be restored as the current generation. The previous technical-refactor / CI-cost phase remains superseded by the current owner direction.

## Canonical visual reference

The owner-confirmed AnyTour Design System 2.0 reference is the composite desktop/mobile mockup supplied on 2026-08-31. Treat its visual language and page composition as the implementation target, not merely the current production CSS.

Key target characteristics: compact unified white header; light travel-first canvas; large photographic travel hero with the search form as the primary first-screen object; white cards with subtle borders/shadows; blue primary UI with orange conversion CTA; dense but clean search-results layout; coherent hotel and destination pages; full dark footer; mobile as a compact continuation of the same system. Preserve the existing AnyTour logo.

Do not spend cycles polishing compositions that materially contradict this reference. Where the current implementation diverges, migrate toward the reference in safe functional slices while preserving Tourvisor, Metrika and lead contracts.

## Material progress

The shared shell/header/footer/cards/buttons/breadcrumbs/page gutters are established. Country pages use one neutral destination hierarchy and a compact live-search handoff with clear availability/flight/price signals. The `/hot/` live-offer cards now keep equal-height rhythm with bottom-aligned CTAs and safe long-name wrapping. The homepage five-card discovery grid is balanced at tablet/small-desktop widths instead of leaving orphan cards or empty grid slots. Relevant PR visual/regression checks remained green.

## Current resume point

Re-baseline the full journey against the owner-confirmed DS2 mockup. Start with shared header/footer + homepage hero/search composition, then align `/poisk-turov/` search shell and results, selected hotel/tour, and finally country/hot/editorial pages. Validate each slice at 375/430/768/1024/1440. Prefer structural convergence with the reference over one-off cosmetic fixes to the old composition.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, Tourvisor contract, or external lead-sending contract/field mapping. Preserve verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate architecture safe.
