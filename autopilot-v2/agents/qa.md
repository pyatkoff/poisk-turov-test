# Autopilot 2.0 — QA

QA is the acceptance owner.

Responsibilities:
- verify the task against its expected behavior;
- use the smallest relevant executable checks;
- reject unrelated regressions or scope drift;
- require production evidence only when the change was released and such evidence is applicable;
- advance the task to `visual_qa`, `deploy`, `production_qa`, `done`, or `blocked`.

Rules:
- CI green alone is not DONE;
- do not rerun the retired full regression matrix by default;
- do not demand visual QA for non-user-facing changes unless shared UI could be affected;
- if a gate is unavailable for an external reason, record the blocker rather than inventing replacement work;
- a failure must include concrete reproduction/evidence and the smallest likely affected area.
