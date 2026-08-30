#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

expected_mode = owner["active_mode"]
expected_phase = owner["active_phase"]

errors = []
if state.get("mode") != expected_mode:
    errors.append(f"AUTOPILOT_STATE mode={state.get('mode')!r}, expected {expected_mode!r}")
lock = state.get("owner_priority_lock") or {}
if not lock.get("active"):
    errors.append("AUTOPILOT_STATE owner_priority_lock.active must be true")
if lock.get("planned_phase") != expected_mode:
    errors.append(
        f"AUTOPILOT_STATE owner_priority_lock.planned_phase={lock.get('planned_phase')!r}, expected {expected_mode!r}"
    )
if f"## Current phase — {expected_phase}" not in autopilot:
    errors.append(f"AUTOPILOT.md must declare current phase {expected_phase!r}")
if "technical refactor → UX/visual" not in autopilot:
    errors.append("AUTOPILOT.md must keep technical refactor ahead of UX/visual after emergency overrides")

if errors:
    print("OWNER_PRIORITY_GUARD_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"OWNER_PRIORITY_GUARD_OK mode={expected_mode}")
