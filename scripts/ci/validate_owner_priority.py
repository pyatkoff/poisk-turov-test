#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

errors = []
mode = owner.get("active_mode")
phase = owner.get("active_phase")
stage = owner.get("current_stage")
design_system = owner.get("canonical_design_system")

if not all(isinstance(value, str) and value.strip() for value in [mode, phase, stage, design_system]):
    errors.append("OWNER_PRIORITY must define non-empty active_mode, active_phase, current_stage and canonical_design_system")
if state.get("mode") != mode or state.get("phase") != phase:
    errors.append("AUTOPILOT_STATE mode/phase must match OWNER_PRIORITY")
if state.get("canonical_design_system") != design_system:
    errors.append("AUTOPILOT_STATE canonical_design_system must match OWNER_PRIORITY")
lock = state.get("owner_priority_lock") or {}
if not lock.get("active") or lock.get("planned_phase") != mode:
    errors.append("AUTOPILOT_STATE owner_priority_lock must follow OWNER_PRIORITY active_mode")
if (state.get("current_stage") or {}).get("id") != stage:
    errors.append("AUTOPILOT_STATE current stage must match OWNER_PRIORITY current_stage")
if design_system not in autopilot:
    errors.append("AUTOPILOT.md must name the canonical design system from OWNER_PRIORITY")
if phase not in autopilot.replace(" ", "_").upper() and design_system not in autopilot:
    errors.append("AUTOPILOT.md must describe the active owner-directed phase")

if errors:
    print("OWNER_PRIORITY_GUARD_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"OWNER_PRIORITY_GUARD_OK mode={mode} stage={stage} design_system={design_system}")
