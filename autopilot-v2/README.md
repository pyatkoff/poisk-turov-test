# AnyTour Autopilot 2.0

Autopilot 2.0 is the parallel/planning mode of the same lightweight execution kernel used by Autopilot. It is not a second infrastructure stack and does not replace production runtime code.

## Shared kernel

The execution contract is:

`task contract -> dependency/ownership check -> writer -> task-scoped verification -> outcome -> acceptance -> release dependents`

Task contracts live in `autopilot-v2/tasks/*.json` and must declare:
- `id`
- `goal`
- `risk`: `SAFE | MEDIUM | HIGH`
- `owns_paths`
- `depends_on`
- `verify.level`: `none | smoke | targeted | production`
- `verify.checks`
- `done_when`

`controller.py` validates contracts, dependency graphs and task outcomes, chooses up to 3 non-overlapping ready SAFE/MEDIUM tasks, and checks changed files against `owns_paths`.

## Autopilot vs Autopilot 2.0

- **Autopilot**: run the same controller with one writer (`plan --max-writers 1`).
- **Autopilot 2.0**: run the same controller with at most three independent writers (`plan --max-writers 3`).

Parallelism is only allowed when task ownership does not overlap. HIGH work remains approval-gated by `AGENTS.md`.

## Verification budget

Verification is attached to each task instead of automatically running the whole repository matrix.

- **SAFE**: none/syntax/smallest smoke; browser only for real user-facing risk.
- **MEDIUM**: focused deterministic checks + one relevant browser/user-flow check.
- **HIGH**: explicit authorization where required, targeted regression, independent review where useful, production smoke for protected live contracts.

Ordinary visual work defaults to 375 + 1440. Add 430/768/1024 only when the affected breakpoint/layout requires it. Deep live/visual/SEO suites are on-demand or scheduled evidence, not normal development blockers.

## Progressive acceptance

A task may be accepted as soon as its own dependencies and required evidence are satisfied. Unrelated sibling tasks do not wait for the slowest lane. Accepted dependencies immediately release downstream work.

## Typed recovery

Use one failure class instead of blind retries:
- `writer_failed`
- `verification_failed`
- `verification_missing`
- `owns_violation`
- `merge_conflict`
- `external_service_failed`
- `production_failed`
- `blocked_by_dependency`

Missing CI wiring or an external outage must not cause the writer to rerun blindly.

## State compatibility

`AGENTS.md` remains the authority for autonomy, hard boundaries and priorities. `AUTOPILOT_STATE.json` remains the project/roadmap resume state. `autopilot-v2/state.json` remains the current execution/pilot state while task contracts are introduced incrementally.

`project-contract.json` remains the project-wide immutable boundary and lean CI policy. The existing agent notes are role guidance only; they are not separate infrastructure services and do not imply four mandatory agents for every task.

## Minimal commands

```bash
python3 autopilot-v2/controller.py validate
python3 autopilot-v2/controller.py plan --max-writers 1
python3 autopilot-v2/controller.py plan --max-writers 3
python3 autopilot-v2/controller.py check-owns --task TASK_ID changed/file.php another/file.css
```

Do not add a dashboard, database, mandatory worktrees or new broad workflows until a concrete limitation of this kernel is proven.
