# Autopilot 2.0 — Active pilot

`active_pilot` is the first mutating Autopilot 2.0 mode. It may create and implement narrowly scoped SAFE or MEDIUM product fixes when QA has reproduced a concrete defect. It does not relax any boundary from `AGENTS.md` or `project-contract.json`.

## Execution loop

1. Orchestrator reads `AGENTS.md`, `AUTOPILOT_STATE.json`, `project-contract.json` and `state.json`.
2. QA observes the selected user flow and records one result: `PASS`, `DEFECT`, or `BLOCKED_EXTERNAL`.
3. `PASS`: attach evidence and continue the queue. Do not create code work.
4. `BLOCKED_EXTERNAL`: record the blocker, retry only the narrow probe when useful, and continue independent safe work.
5. `DEFECT`: Orchestrator creates one child task with an exact reproduction, expected behavior, area and risk.
6. SAFE/MEDIUM child task: hand to Developer. HIGH child task: stop mutation and require the approval already defined by `AGENTS.md`.
7. Developer makes the smallest product diff and runs `Fast CI`.
8. QA independently verifies the exact defect. Developer cannot mark its own work DONE.
9. If user-facing behavior changed, Visual QA checks only the affected route/state at relevant mobile + desktop widths.
10. Release only through the existing deploy path. After release, use `Production smoke`; run deeper targeted checks only when evidence justifies them.
11. Orchestrator records evidence, closes the child task, returns to the parent audit and selects the next highest-priority task.

## Anti-bloat rules

- Never recreate the retired full validation matrix.
- Never add a new workflow when an existing Fast CI / targeted manual check can prove the same thing.
- One active code task per functional area.
- Observation-only work has no CI requirement.
- A subjective polish idea is not a DEFECT unless it is a material UX/visual regression.
- Preserve Design System 2.0; Design System 1.0 is not a valid target or fallback.

## Automatic handoff contract

A reproduced defect becomes a Developer task only when QA supplies all of:
- exact affected route/state;
- reproduction steps;
- actual result;
- expected result;
- risk classification;
- smallest required gate set.

If any field is missing, the task stays with QA/Orchestrator instead of generating speculative code work.
