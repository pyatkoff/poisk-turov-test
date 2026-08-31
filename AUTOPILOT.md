# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize site-wide visual unification under AnyTour Design System 1.0. The previous technical-refactor / CI-cost phase is superseded by the current owner direction.

## Material progress

The shared shell/header/footer/cards/buttons/breadcrumbs/page gutters are established. Country pages now use one neutral destination hierarchy and a compact live-search handoff with clear availability/flight/price signals; responsive PR visual evidence is green across 375/430/768/1024/1440 and production deploy/search/lead smoke passed.

## Current resume point

Run and refine the full journey `homepage → country/destination → hot/search → results → selected tour → lead` at 375/430/768/1024/1440. Fix the next confirmed hierarchy, wrapping, overflow or spacing inconsistency, then continue homepage/hot/editorial hierarchy improvements before cosmetic flourishes.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, Tourvisor contract, or external lead-sending contract/field mapping. Preserve verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate architecture safe.
