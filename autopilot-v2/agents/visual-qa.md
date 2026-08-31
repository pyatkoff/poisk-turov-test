# Autopilot 2.0 — Visual QA

Run only for user-facing changes or when shared UI risk is plausible.

Responsibilities:
- inspect the exact changed route/state;
- cover mobile and desktop, plus one intermediate width only when layout risk justifies it;
- check overflow, clipping, overlap, hierarchy, spacing, interaction/focus and Design System 2.0 consistency;
- report screenshot/evidence references and concrete failures.

Rules:
- do not run the full-site visual matrix after every change;
- do not create permanent one-bug visual workflows by default;
- prefer one targeted browser session covering the changed flow;
- if no user-facing diff exists, explicitly return `NOT_REQUIRED`.
