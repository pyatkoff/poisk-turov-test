#!/usr/bin/env python3
import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
TASK_DIR = ROOT / "tasks"
OUTCOME_DIR = ROOT / "outcomes"
VALID_RISK = {"SAFE", "MEDIUM", "HIGH"}
VALID_VERIFY = {"none", "smoke", "targeted", "production"}
TERMINAL = {"accepted", "blocked", "failed"}
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


def ready_tasks(tasks, limit=3):
    ready = []
    for task in tasks.values():
        if task_runtime_status(task, tasks) != "ready":
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
    tasks = load_tasks()
    validate_graph(tasks)
    for task_id, task in tasks.items():
        outcome = outcome_for(task_id)
        if outcome:
            validate_outcome(outcome, task)
    print(f"AUTOPILOT_CONTROLLER_OK tasks={len(tasks)}")


def cmd_status(_args):
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
    print(json.dumps({"tasks": rows}, ensure_ascii=False, indent=2))


def cmd_plan(args):
    tasks = load_tasks()
    validate_graph(tasks)
    ready = ready_tasks(tasks, limit=args.max_writers)
    plan = {
        "max_writers": args.max_writers,
        "ready": [
            {
                "id": t["id"],
                "risk": t["risk"],
                "owns_paths": t["owns_paths"],
                "verify": t["verify"],
            }
            for t in ready
        ],
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
