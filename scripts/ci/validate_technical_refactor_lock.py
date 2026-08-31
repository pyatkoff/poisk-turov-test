#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "design_system_1"
EXPECTED_PHASE = "ANYTOUR DESIGN SYSTEM 1.0"
EXPECTED_STAGE = "SITE_WIDE_VISUAL_UNIFICATION"
errors = []

if lock.get("active") is not False or lock.get("status") != "INACTIVE_OWNER_DIRECTION_CHANGED":
    errors.append("obsolete TECHNICAL_REFACTOR_LOCK must remain inactive after owner changed direction")
if lock.get("mode") != EXPECTED_MODE or lock.get("phase") != EXPECTED_PHASE:
    errors.append("TECHNICAL_REFACTOR_LOCK must record the superseding Design System 1.0 direction")
if owner.get("active_mode") != EXPECTED_MODE or owner.get("active_phase") != EXPECTED_PHASE:
    errors.append("OWNER_PRIORITY does not match Design System 1.0 direction")
if owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY current stage does not match site-wide visual unification")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match Design System 1.0 direction")
if (state.get("owner_priority_lock") or {}).get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner-priority lock does not match Design System 1.0 direction")
if (state.get("current_stage") or {}).get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match site-wide visual unification")
if "## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0" not in autopilot:
    errors.append("AUTOPILOT.md does not declare Design System 1.0 as current")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK status=inactive superseded_by=design_system_1")
