# AnyTour Autopilot kernel

This directory is the lightweight execution kernel shared by Autopilot and Autopilot 2.0.

The goal is to make autonomous work faster and safer without adding another large orchestration stack.

## One engine, two modes

- **Autopilot**: one ready task at a time, normally one writer.
- **Autopilot 2.0**: the same task contract and acceptance rules, with a small dependency graph and at most 3 parallel writers whose `owns_paths` do not overlap.

## Task contract

Every implementation slice should be expressible with:

- `id`
- `goal`
- `risk`: `safe | medium | high`
- `owns_paths`
- `depends_on`
- `verify`: `none | smoke | targeted | production`
- `done_when`

`owns_paths` is a hard write boundary for delegated writers. If a required change falls outside it, stop that slice and expand/replan ownership instead of silently editing neighboring areas.

## Verification budget

Verification belongs to the task, not automatically to the whole repository.

- **SAFE**: `none`, syntax, or the smallest targeted smoke. Browser evidence only when the change is actually user-visible.
- **MEDIUM**: focused deterministic checks plus one relevant browser/user-flow check. Test only breakpoints affected by the change; default visual pair is mobile 375 + desktop 1440, adding 430/768/1024 only when breakpoint/layout behavior is in scope.
- **HIGH**: explicit owner authorization when required by `AGENTS.md`, targeted regression, independent review where useful, and production smoke for protected live contracts.

Scheduled/live audits, broad five-width sweeps, whole-project rereads and maintenance audits are not default blocking checks for an unrelated SAFE task.

Do not create a new workflow when an existing check can own the behavior. Prefer one primary verification owner per behavior.

## Progressive acceptance

A task may be accepted as soon as its own dependencies, ownership and required verification are satisfied. Independent sibling tasks do not wait for the slowest lane or for unrelated CI.

Acceptance is a handoff point, not a default stop condition. After accepting a task, the controller should immediately pick the next ready in-scope task when the current run still has execution budget. A completed commit or PR must not end the run by itself. Continue through additional safe ready work until a genuine blocker, required approval, exhausted run budget, or empty ready queue is reached.

## Outcome receipt

Every delegated task should finish with a small machine-readable outcome equivalent to:

```json
{
  "task": "example",
  "status": "accepted",
  "attempts": 1,
  "changed_files": [],
  "verification": {},
  "failure_class": null,
  "remaining_risk": "none"
}
```

The outcome is the task-level truth. `AUTOPILOT_STATE.json` remains run/project-level state and should not accumulate verbose per-task history.

## Typed recovery

Classify failures before retrying:

- `writer_failed`
- `verification_failed`
- `verification_missing`
- `owns_violation`
- `merge_conflict`
- `external_service_failed`
- `production_failed`
- `blocked_by_dependency`

Do not rerun a writer for missing CI wiring or an external-service failure. Retry/rework code only when evidence points to the implementation.

## Controller contract

The controller can remain small:

`pick ready task -> check dependencies -> assign writer -> enforce owns_paths -> run task verification -> write outcome -> accept/block -> pick next ready task while run budget remains`

No separate database, dashboard, mandatory worktree, or large supervisor hierarchy is required. Use a worktree only for genuinely parallel or risky work where isolation provides value.
