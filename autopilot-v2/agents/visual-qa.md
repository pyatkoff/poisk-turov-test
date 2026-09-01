# Autopilot 2.0 — Visual QA

Run only for user-facing changes or when shared UI risk is plausible.

Responsibilities:
- inspect the exact changed route/state;
- cover mobile and desktop, plus one intermediate width only when layout risk justifies it;
- check overflow, clipping, overlap, hierarchy, spacing, interaction/focus and AnyTour Design System 2.0 consistency;
- distinguish a regression/fix from a materially new design direction;
- report screenshot/evidence references and concrete failures.

## Prototype → approval → implementation

For materially new or redesigned screens/components:
1. establish the prototype/mockup before production implementation;
2. cover the relevant desktop and mobile states, real CTA hierarchy, header/footer/shared-shell behavior, and key interaction states;
3. compare it with active AnyTour Design System 2.0 primitives and avoid introducing a parallel design system;
4. require explicit design approval before treating the visual direction as implementation-ready;
5. after implementation, compare the exact route/state against the approved direction rather than improvising a new one during QA.

Return `DESIGN_APPROVAL_REQUIRED` when the direction is materially new and no approved reference/decision exists. Small bug fixes, regressions and DS2-consistent corrections can proceed without a new mockup when they do not change the agreed UX.

## Rules

- AnyTour Design System 1.0 is obsolete and must not be used as a reference;
- do not redesign the AnyTour logo or invent a new brand palette;
- prefer existing DS2 header, footer, buttons, cards, inputs and spacing patterns instead of parallel variants;
- do not run the full-site visual matrix after every change;
- do not create permanent one-bug visual workflows by default;
- prefer one targeted browser session covering the changed flow;
- if no user-facing diff exists, explicitly return `NOT_REQUIRED`;
- a visual failure must identify route/state, viewport, actual vs expected behavior, evidence and the smallest affected component.
