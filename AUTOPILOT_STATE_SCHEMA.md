# AUTOPILOT_STATE.json schema

Current schema version: `21`

`AUTOPILOT_STATE.json` is the machine-readable development queue and
continuation state. It records current repository evidence, not an inferred
production release. Production/LKG identity must remain explicitly unknown
until verified.

## Required top-level fields

- `schema_version` — integer `21`;
- `project` — `pyatkoff/poisk-turov-test`;
- `updated_at` — timezone-aware ISO-8601 timestamp;
- `mode`, `phase`, `canonical_design_system` — current owner direction;
- `owner_priority_source` — existing owner-priority JSON path;
- `program_source` — existing active master-plan path;
- `current_stage` — object with non-empty `id`, `name`, `status`;
- `current_task` — object with non-empty `id`, `category`, `title`, `status`,
  `objective`, `next_action`;
- `queue` — non-empty array of unique task objects containing non-empty `id`,
  `status`, `title`; it must contain `current_task` with the same status;
- `last_ci` — object containing `workflow`, 40-hex `head_sha`, `conclusion` and
  `classification`; nullable provider ID and a numeric run number are allowed;
- `last_verified_commit` — 40-hex commit audited by the current state;
- `verification_context` — object explaining what that commit proves and
  explicitly recording production/LKG identity when known or unknown;
- `blocked` — array of objects with non-empty `id`, `blocks`, `status` and an
  optional non-empty `resolution_task`;
- `protected` — object containing current non-negotiable contract locks;
- `continuation_policy` — non-empty object describing failure, blocker,
  user-facing and release continuation behavior.

`protected.search3_production` must remain
`LOCKED_UNTIL_EXACT_CANDIDATE_GATES_AND_OWNER_APPROVAL` until an explicit owner
decision updates the release state. `current_task.id` must appear in
`START_HERE.md`, and every queued task ID must appear in `program_source`.

## Update policy

Update the state whenever current task/status, queue, blocker, exact SHA or
verification evidence materially changes. A branch under review records its
actual current task; do not pre-advance the state in anticipation of merge.
After merge, advance it in a separate explicit state update.

CI workflows do not commit runtime noise into this file. Fresh CI handoff is
kept in the `[AUTOPILOT] Runtime state` issue by
`.github/workflows/autopilot-runtime-state.yml`. The repository validators
enforce structural integrity and owner-direction locks; they do not turn CI or
preview evidence into a production-deployment claim.
