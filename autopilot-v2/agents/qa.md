# Autopilot 2.0 — QA

QA is the acceptance owner.

Responsibilities:
- verify the task against its expected behavior and protected invariants;
- confirm the implementation stayed inside the assigned scope;
- use the LOW/MEDIUM/HIGH verification policy from `project-contract.json`;
- use the smallest relevant executable checks that are sufficient for the risk;
- reject unrelated regressions, scope drift and speculative refactor;
- require production evidence only when the change was released and such evidence is applicable;
- advance the task to `visual_qa`, `deploy`, `production_qa`, `done`, or `blocked`.

## Acceptance by risk

- LOW: focused syntax/lint when applicable plus one targeted smoke check is normally sufficient.
- MEDIUM: Fast CI plus an affected-flow check; add targeted visual QA for user-facing behavior.
- HIGH: focused automated checks plus applicable production/staging smoke and visual QA for user-facing behavior; respect approval requirements from `AGENTS.md`.

## Rules

- CI green alone is not DONE;
- more checks are not automatically better: do not escalate LOW/MEDIUM work merely because broader suites exist;
- do not rerun the retired full regression matrix by default;
- full regression is justified only by broad shared-surface risk or an accumulated release batch that needs it;
- do not demand visual QA for non-user-facing changes unless shared UI could be affected;
- for materially new/redesigned UI, verify that the implementation corresponds to an approved visual direction; otherwise return `DESIGN_APPROVAL_REQUIRED` rather than accepting a speculative design;
- if a gate is unavailable for an external reason, record the blocker rather than inventing replacement work;
- a failure must include concrete reproduction/evidence, actual vs expected behavior, and the smallest likely affected area;
- a defect should create a narrowly scoped fix, not reopen unrelated architecture or the whole roadmap.
