# AUTOPILOT_STATE.json schema

`AUTOPILOT_STATE.json` is the machine-readable development queue and continuation state.

Required top-level fields:
- `schema_version`
- `project`
- `updated_at`
- `mode`
- `phase`
- `current_task`
- `queue`
- `last_ci`
- `last_verified_commit`
- `blocked`
- `protected`
- `continuation_policy`

The assistant updates it whenever current task, blocker, CI classification, or verification evidence materially changes. CI workflows do not commit runtime noise into this file; fresh CI handoff is kept in the `[AUTOPILOT] Runtime state` issue by `.github/workflows/autopilot-runtime-state.yml`.