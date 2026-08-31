# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize site-wide visual unification under AnyTour Design System 2.0. Design System 1.0 is legacy terminology and must not be restored as the current generation. The previous technical-refactor / CI-cost phase remains superseded by the current owner direction.

## Material progress

The shared shell/header/footer/cards/buttons/breadcrumbs/page gutters are established. Country pages use one neutral destination hierarchy and a compact live-search handoff with clear availability/flight/price signals. The `/hot/` live-offer cards now keep equal-height rhythm with bottom-aligned CTAs and safe long-name wrapping. The homepage five-card discovery grid is balanced at tablet/small-desktop widths instead of leaving orphan cards or empty grid slots. Relevant PR visual/regression checks remained green.

## Current resume point

Continue the full journey `homepage → country/destination → hot/search → results → selected tour → lead` at 375/430/768/1024/1440 under Design System 2.0. The next confirmed inconsistency is the search-shell horizontal gutter at 561–768px: align `.v2-shell` with the shared DS2 24px page gutter while preserving the 20px mobile gutter at 560px and below. Then continue through search results → selected tour → lead and fix the next confirmed hierarchy, wrapping, overflow or spacing mismatch before cosmetic flourishes.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, Tourvisor contract, or external lead-sending contract/field mapping. Preserve verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate architecture safe.
