# Continuous development model

The project uses one lightweight execution kernel for both Autopilot and Autopilot 2.0.

1. `autopilot-v2/tasks/*.json` is the authoritative queue.
2. `autopilot-v2/outcomes/*.json` stores accepted/blocked/failed terminal results.
3. `controller.py status` derives current runtime state; `plan --max-writers 1` is Autopilot and `plan --max-writers 3` is Autopilot 2.0.
4. GitHub Actions persist only the smallest relevant CI evidence and runtime signals.
5. `[AUTOPILOT] Runtime state` is a CI/event receipt, not a parallel queue.
6. An active ChatGPT development turn should execute multiple consecutive safe steps rather than one commit per turn.
7. Broad audits and live checks stay non-blocking unless the selected task contract explicitly requires them.

Legacy state/roadmap documents remain readable during migration but no longer need step-by-step synchronization with the execution queue.

The intended cadence is therefore event-driven inside GitHub and long-running while the assistant is active, with any configured hourly recovery acting only as a watchdog.
