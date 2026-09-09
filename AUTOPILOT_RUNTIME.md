# Autopilot runtime handoff

Current program entrypoint: `START_HERE.md`. The active PR sequence, release
gates and blocked decisions are owned by `docs/project/MASTER_PLAN.md`,
`docs/project/RELEASE_GATES.md` and `AUTOPILOT_STATE.json`. Older queue and
scorecard files are historical evidence, not resume instructions.

This file documents how the autonomous development loop persists state between assistant iterations.

## Sources of truth

- `AGENTS.md` — authority, boundaries, priorities and DONE rules.
- `AUTOPILOT.md` — human-readable current phase and resume summary.
- `docs/project/MASTER_PLAN.md` — dependency-ordered implementation and release program.
- `AUTOPILOT_STATE.json` — machine-readable current task, queue, blockers and continuation policy.
- GitHub issue `[AUTOPILOT] Runtime state` — machine-maintained latest key CI result/handoff.

## Event-driven part

`.github/workflows/autopilot-runtime-state.yml` listens for completion of key workflows and updates the runtime issue with:
- workflow name;
- conclusion;
- head SHA;
- run link;
- normalized signal (`CI_SUCCESS`, `CI_NEEDS_ATTENTION`, `CI_INFORMATIONAL`);
- recommended continuation action.

This makes GitHub the persistent state machine even when no chat session is active.

## Assistant continuation

On every development iteration:
1. Read `AGENTS.md`, `START_HERE.md`, `AUTOPILOT.md` and `AUTOPILOT_STATE.json`.
2. Inspect the latest `[AUTOPILOT] Runtime state` issue and relevant workflow run/logs.
3. If CI failed, diagnose/fix it first when it blocks the current highest-priority task.
4. If the failure is external or blocked, record it and continue independent work.
5. Work one coherent PR slice at a time; independent read-only investigation may continue in parallel.
6. Update `AUTOPILOT_STATE.json` when current task, queue, blocker, exact SHA or verification evidence materially changes.
7. Before release work, confirm the exact deployed and last-known-good identity; do not infer production state from `main` or a successful workflow alone.

The hourly ChatGPT automation is a watchdog/resume mechanism, not the intended development cadence.

## Limitation

GitHub workflow completion can persist and classify state immediately, but it cannot currently trigger a new ChatGPT development turn instantly in this environment. The assistant resumes on an active user turn or watchdog automation. Within an active turn it should continue through multiple CI/fix/verification steps whenever possible.
