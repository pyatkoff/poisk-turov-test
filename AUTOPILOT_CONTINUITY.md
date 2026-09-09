# Continuous development model

The project uses a hybrid continuity model:

1. GitHub Actions run immediately on repository events and persist CI outcomes.
2. `AUTOPILOT_STATE.json` stores the active development task and queue.
3. `[AUTOPILOT] Runtime state` stores the latest key workflow completion signal.
4. An active ChatGPT development turn should execute multiple consecutive safe steps rather than one commit per turn.
5. The hourly ChatGPT automation is only a watchdog/resume mechanism because this environment cannot invoke a new ChatGPT turn directly from a GitHub workflow completion event.

The intended cadence is therefore event-driven inside GitHub and long-running when the assistant is active, with hourly recovery if no active turn exists.