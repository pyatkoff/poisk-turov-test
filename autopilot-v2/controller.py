#!/usr/bin/env python3
import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
TASK_DIR = ROOT / "tasks"
OUTCOME_DIR = ROOT / "outcomes"
STATE_FILE = ROOT / "state.json"
VALID_RISK = {"SAFE", "MEDIUM", "HIGH"}
VALID_VERIFY = {"none", "smoke", "targeted", "production"}
VALID_VERIFICATION_CLASS = {"LOW", "MEDIUM", "HIGH"}
VALID_ACTIVE_STATES = {
    "discovered",
    "triaged",
    "ready",
    "coding",
    "qa",
    "visual_qa",
    "design_approval_required",
    "deploy",
    "production_qa",
    "done",
    "blocked",
}
TERMINAL = {"accepted", "blocked", "failed"}
ACTIVE_TERMINAL_STATES = {"done", "blocked"}
FAILURE_CLASSES = {
    "writer_failed",
    "verification_failed",
    "verification_missing",
    "owns_violation",
    "merge_conflict",
    "external_service_failed",
    "production_failed",
    "blocked_by_dependency",
}


def load_json(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))


def validate_state(state):
    required = {"schema_version", "mode", "resume", "active_task", "queue", "last_signal"}
    missing = sorted(required - set(state))
    if missing:
        raise ValueError(f"state missing fields: {', '.join(missing)}")
    if state["schema_version"] != 3:
        raise ValueError("state.schema_version must be 3")
    if state["mode"] not in {"dry_run", "active_pilot", "active"}:
        raise ValueError(f"invalid mode: {state['mode']}")

    resume = state["resume"]
    if not isinstance(resume, dict):
        raise ValueError("resume must be an object")
    for key in ("now", "done", "next", "blocked", "lessons"):
        if key not in resume:
            raise ValueError(f"resume missing field: {key}")
    if not isinstance(resume["now"], str) or not isinstance(resume["next"], str):
        raise ValueError("resume.now and resume.next must be strings")
    for key in ("done", "blocked", "lessons"):
        if not isinstance(resume[key], list) or not all(isinstance(item, str) for item in resume[key]):
            raise ValueError(f"resume.{key} must be an array of strings")

    active = state["active_task"]
    if active is not None:
        active_required = {
            "id", "title", "area", "risk", "verification_class", "state",
            "owner", "required_gates", "evidence"
        }
        active_missing = sorted(active_required - set(active))
        if active_missing:
            raise ValueError(f"active_task missing fields: {', '.join(active_missing)}")
        if active["risk"] not in VALID_RISK:
            raise ValueError(f"invalid active_task risk: {active['risk']}")
        if active["verification_class"] not in VALID_VERIFICATION_CLASS:
            raise ValueError(f"invalid verification_class: {active['verification_class']}")
        if active["state"] not in VALID_ACTIVE_STATES:
            raise ValueError(f"invalid active_task state: {active['state']}")

    return state


def load_state():
    if not STATE_FILE.exists():
        raise ValueError("state.json is missing")
    return validate_state(load_json(STATE_FILE))


def task_files():
    return sorted(TASK_DIR.glob("*.json")) if TASK_DIR.exists() else []


def validate_task(task):
    required = {"schema_version", "id", "goal", "risk", "owns_paths", "depends_on", "verify", "done_when"}
    missing = sorted(required - set(task))
    if missing:
        raise ValueError(f"missing fields: {', '.join(missing)}")
    if task["schema_version"] != 1:
        raise ValueError("schema_version must be 1")
    if task["risk"] not in VALID_RISK:
        raise ValueError(f"invalid risk: {task['risk']}")
    if not isinstance(task["owns_paths"], list) or not task["owns_paths"]:
        raise ValueError("owns_paths must be a non-empty array")
    if not isinstance(task["depends_on"], list):
        raise ValueError("depends_on must be an array")
    if not isinstance(task["done_when"], list) or not task["done_when"]:
        raise ValueError("done_when must be a non-empty array")
    verify = task["verify"]
    if not isinstance(verify, dict) or verify.get("level") not in VALID_VERIFY:
        raise ValueError("verify.level must be none|smoke|targeted|production")
    checks = verify.get("checks", [])
    if verify["level"] != "none" and not checks:
        raise ValueError("verify.checks required unless level=none")
    return task


def load_tasks():
    tasks = {}
    for path in task_files():
        task = validate_task(load_json(path))
        task_id = task["id"]
        if task_id in tasks:
            raise ValueError(f"duplicate task id: {task_id}")
        tasks[task_id] = task
    return tasks


def outcome_for(task_id):
    path = OUTCOME_DIR / f"{task_id}.json"
    return load_json(path) if path.exists() else None


def accepted(task_id):
    outcome = outcome_for(task_id)
    return bool(outcome and outcome.get("status") == "accepted")


def ownership_overlap(a, b):
    for pa in a:
        pa = pa.rstrip("/")
        for pb in b:
            pb = pb.rstrip("/")
            if pa == pb or pa.startswith(pb + "/") or pb.startswith(pa + "/"):
                return True
    return False


def task_runtime_status(task, tasks):
    outcome = outcome_for(task["id"])
    if outcome:
        return outcome.get("status", "unknown")
    missing = [dep for dep in task["depends_on"] if dep not in tasks]
    if missing:
        return "invalid_dependency"
    failed_deps = []
    waiting_deps = []
    for dep in task["depends_on"]:
        dep_outcome = outcome_for(dep)
        if dep_outcome and dep_outcome.get("status") in {"blocked", "failed"}:
            failed_deps.append(dep)
        elif not accepted(dep):
            waiting_deps.append(dep)
    if failed_deps:
        return "blocked_by_dependency"
    if waiting_deps:
        return "waiting_dependency"
    if task["risk"] == "HIGH":
        return "approval_required"
    return "ready"


def active_task_contract(state, tasks):
    active = state.get("active_task")
    if not active or active.get("state") in ACTIVE_TERMINAL_STATES:
        return None
    return tasks.get(active.get("id"))


def ready_tasks(tasks, limit=3, reserved_paths=None, exclude_ids=None):
    reserved_paths = reserved_paths or []
    exclude_ids = exclude_ids or set()
    ready = []
    for task in tasks.values():
        if task["id"] in exclude_ids:
            continue
        if task_runtime_status(task, tasks) != "ready":
            continue
        if reserved_paths and ownership_overlap(task["owns_paths"], reserved_paths):
            continue
        if any(ownership_overlap(task["owns_paths"], other["owns_paths"]) for other in ready):
            continue
        ready.append(task)
        if len(ready) >= limit:
            break
    return ready


def validate_graph(tasks):
    for task in tasks.values():
        for dep in task["depends_on"]:
            if dep not in tasks:
                raise ValueError(f"{task['id']}: unknown dependency {dep}")
    visiting, visited = set(), set()

    def visit(task_id):
        if task_id in visiting:
            raise ValueError(f"dependency cycle at {task_id}")
        if task_id in visited:
            return
        visiting.add(task_id)
        for dep in tasks[task_id]["depends_on"]:
            visit(dep)
        visiting.remove(task_id)
        visited.add(task_id)

    for task_id in tasks:
        visit(task_id)


def validate_outcome(data, task=None):
    if data.get("status") not in TERMINAL:
        raise ValueError("outcome status must be accepted|blocked|failed")
    if not isinstance(data.get("attempts"), int) or data["attempts"] < 1:
        raise ValueError("attempts must be >= 1")
    failure = data.get("failure_class")
    if failure is not None and failure not in FAILURE_CLASSES:
        raise ValueError(f"invalid failure_class: {failure}")
    if data["status"] == "accepted" and failure is not None:
        raise ValueError("accepted outcome cannot have failure_class")
    if task and data.get("task") != task["id"]:
        raise ValueError("outcome task id does not match contract")


def cmd_validate(_args):
    state = load_state()
    tasks = load_tasks()
    validate_graph(tasks)
    for task_id, task in tasks.items():
        outcome = outcome_for(task_id)
        if outcome:
            validate_outcome(outcome, task)
    print(f"AUTOPILOT_CONTROLLER_OK state_schema={state['schema_version']} tasks={len(tasks)}")


def cmd_resume(_args):
    state = load_state()
    payload = {
        "mode": state["mode"],
        "now": state["resume"]["now"],
        "done": state["resume"]["done"],
        "next": state["resume"]["next"],
        "blocked": state["resume"]["blocked"],
        "lessons": state["resume"]["lessons"],
        "active_task": state["active_task"],
        "last_signal": state["last_signal"],
    }
    print(json.dumps(payload, ensure_ascii=False, indent=2))


def cmd_status(_args):
    state = load_state()
    tasks = load_tasks()
    validate_graph(tasks)
    rows = []
    for task in tasks.values():
        outcome = outcome_for(task["id"])
        rows.append({
            "id": task["id"],
            "risk": task["risk"],
            "status": task_runtime_status(task, tasks),
            "depends_on": task["depends_on"],
            "verify": task["verify"]["level"],
            "failure_class": outcome.get("failure_class") if outcome else None,
        })
    print(json.dumps({"active_task": state["active_task"], "tasks": rows}, ensure_ascii=False, indent=2))


def cmd_plan(args):
    state = load_state()
    tasks = load_tasks()
    validate_graph(tasks)
    active = state.get("active_task")
    active_contract = active_task_contract(state, tasks)

    if active and active.get("state") == "design_approval_required":
        plan = {
            "max_writers": 0,
            "decision": "DESIGN_APPROVAL_REQUIRED",
            "continue_active": active,
            "ready": [],
            "reason": "The active user-facing redesign is waiting for explicit design approval; implementation must not bypass this gate.",
            "next": state["resume"]["next"],
        }
        print(json.dumps(plan, ensure_ascii=False, indent=2))
        return

    if active and active.get("state") not in ACTIVE_TERMINAL_STATES:
        reserved_paths = active_contract["owns_paths"] if active_contract else []
        remaining_capacity = max(0, args.max_writers - (1 if active.get("owner") == "developer" else 0))
        ready = ready_tasks(
            tasks,
            limit=remaining_capacity,
            reserved_paths=reserved_paths,
            exclude_ids={active.get("id")},
        ) if remaining_capacity else []
        plan = {
            "max_writers": args.max_writers,
            "decision": "CONTINUE_ACTIVE",
            "continue_active": {
                "id": active["id"],
                "state": active["state"],
                "owner": active["owner"],
                "risk": active["risk"],
                "verification_class": active["verification_class"],
                "required_gates": active["required_gates"],
                "owns_paths": reserved_paths,
            },
            "ready": [
                {
                    "id": t["id"],
                    "risk": t["risk"],
                    "owns_paths": t["owns_paths"],
                    "verify": t["verify"],
                }
                for t in ready
            ],
            "continuous_safe_step": bool(ready),
            "next": state["resume"]["next"],
        }
        print(json.dumps(plan, ensure_ascii=False, indent=2))
        return

    ready = ready_tasks(tasks, limit=args.max_writers)
    plan = {
        "max_writers": args.max_writers,
        "decision": "SELECT_NEXT_SAFE_STEP",
        "ready": [
            {
                "id": t["id"],
                "risk": t["risk"],
                "owns_paths": t["owns_paths"],
                "verify": t["verify"],
            }
            for t in ready
        ],
        "continuous_safe_step": bool(ready),
        "next": state["resume"]["next"],
    }
    print(json.dumps(plan, ensure_ascii=False, indent=2))


def cmd_check_owns(args):
    tasks = load_tasks()
    if args.task not in tasks:
        raise ValueError(f"unknown task: {args.task}")
    task = tasks[args.task]
    bad = []
    for changed in args.changed:
        ok = any(
            changed == owned.rstrip("/") or changed.startswith(owned.rstrip("/") + "/")
            for owned in task["owns_paths"]
        )
        if not ok:
            bad.append(changed)
    if bad:
        print(json.dumps({"task": args.task, "failure_class": "owns_violation", "files": bad}, ensure_ascii=False))
        return 2
    print(f"OWNS_PATHS_OK task={args.task} files={len(args.changed)}")
    return 0


def main():
    parser = argparse.ArgumentParser(description="AnyTour lightweight Autopilot controller")
    sub = parser.add_subparsers(dest="command", required=True)
    p_validate = sub.add_parser("validate")
    p_validate.set_defaults(func=cmd_validate)
    p_resume = sub.add_parser("resume")
    p_resume.set_defaults(func=cmd_resume)
    p_status = sub.add_parser("status")
    p_status.set_defaults(func=cmd_status)
    p_plan = sub.add_parser("plan")
    p_plan.add_argument("--max-writers", type=int, default=3, choices=(1, 2, 3))
    p_plan.set_defaults(func=cmd_plan)
    p_owns = sub.add_parser("check-owns")
    p_owns.add_argument("--task", required=True)
    p_owns.add_argument("changed", nargs="+")
    p_owns.set_defaults(func=cmd_check_owns)
    args = parser.parse_args()
    try:
        result = args.func(args)
        return 0 if result is None else result
    except (ValueError, json.JSONDecodeError) as exc:
        print(f"AUTOPILOT_CONTROLLER_ERROR: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
