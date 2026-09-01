# Autopilot runtime handoff

This file documents how the autonomous development loop persists execution state between assistant iterations.

## Sources of truth

- `AGENTS.md` — authority, boundaries, priorities and DONE rules.
- `autopilot-v2/project-contract.json` — immutable project boundaries and lean CI policy.
- `autopilot-v2/tasks/*.json` — authoritative execution queue.
- `autopilot-v2/outcomes/*.json` — authoritative terminal task results.
- `python3 autopilot-v2/controller.py status` — canonical derived runtime status.
- GitHub issue `[AUTOPILOT] Runtime state` — latest CI signal/handoff only; it is not a second task queue.

`AUTOPILOT_STATE.json`, `AUTOPILOT.md` and `autopilot-v2/state.json` remain compatibility/roadmap documents during migration. They must not override task contracts + outcomes when execution state disagrees.

## Event-driven part

`.github/workflows/autopilot-runtime-state.yml` listens for completion of key workflows and updates the runtime issue with workflow name, conclusion, head SHA, run link, normalized signal and recommended continuation action.

The runtime issue is an event receipt. It does not own prioritization, dependencies, ownership or task completion.

## Assistant continuation

On every development iteration:
1. Read `AGENTS.md` and `autopilot-v2/project-contract.json`.
2. Run/read the controller status and plan from task contracts + outcomes.
3. Inspect only workflow results relevant to the selected task.
4. If a relevant CI failure blocks that task, diagnose/fix it first.
5. If the failure is external or dependency-blocked, record a typed outcome/blocker and continue independent ready work.
6. Continue multiple safe steps in the same iteration rather than stopping after one commit.
7. Write/update an outcome only when the task reaches accepted, blocked or failed.

Do not manually synchronize multiple queue/status files after every step. Compatibility documents may be updated later at a material roadmap milestone.

## Cadence

GitHub Actions remain event-driven. An active assistant turn should continue through multiple safe steps. Any hourly ChatGPT automation remains a watchdog/resume mechanism rather than the intended development cadence.

## Limitation

A GitHub workflow can persist CI state immediately but cannot itself invoke a new ChatGPT development turn in this environment. The assistant resumes on an active user turn or a configured watchdog automation.
