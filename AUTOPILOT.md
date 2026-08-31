# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize visual convergence with the owner-confirmed AnyTour Design System 2.0 mockup. Technical refactor may support reliability and consolidation but must not preempt the active DS2 implementation direction.

## Canonical visual reference

The canonical target is the composite desktop/mobile AnyTour Design System 2.0 mockup supplied and reconfirmed by the owner on 2026-08-31. Treat the mockup—not current production CSS—as the visual source of truth when they diverge.

Target language: compact unified white header; light travel-first canvas; large photographic travel hero with search as the primary first-screen object; white cards with subtle borders/shadows; blue primary UI with orange conversion CTA; dense but clean search-results layout; coherent hotel and destination pages; full dark footer; mobile as a compact continuation of desktop. Preserve the existing AnyTour logo.

## Ordered work

1. Align one shared header and one shared footer with the DS2 reference.
2. Align homepage hero + search composition with the reference instead of polishing contradictory legacy hero compositions.
3. Align `/poisk-turov/` search shell and results hierarchy.
4. Align selected hotel/tour experience.
5. Bring country, hot and editorial pages onto the same DS2 language.
6. Validate each slice at 375/430/768/1024/1440 and preserve search/recovery/results/lead regressions.

## Current resume point

Start the first runtime convergence slice with shared header/footer plus homepage/search first-screen composition. Prefer structural convergence with the reference over one-off cosmetic fixes to old layouts.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, Tourvisor contract, or external lead-sending contract/field mapping. Preserve verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate architecture safe.
