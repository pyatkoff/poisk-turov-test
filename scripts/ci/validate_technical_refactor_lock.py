#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "ux_visual"
EXPECTED_PHASE = "ANYTOUR_DESIGN_SYSTEM_1_SITE_UNIFICATION"
EXPECTED_STAGE = "COUNTRY_DESTINATION_UNIFICATION"
EXPECTED_DESIGN_SYSTEM = "ANYTOUR DESIGN SYSTEM 1.0"
errors = []

if lock.get("active") is not False or lock.get("status") != "INACTIVE_SUPERSEDED_BY_OWNER_DIRECTION":
    errors.append("TECHNICAL_REFACTOR_LOCK must remain inactive under the current owner visual direction")
if lock.get("canonical_design_system") != EXPECTED_DESIGN_SYSTEM:
    errors.append("TECHNICAL_REFACTOR_LOCK metadata must reflect Design System 1.0")
if owner.get("active_mode") != EXPECTED_MODE or owner.get("active_phase") != EXPECTED_PHASE:
    errors.append("OWNER_PRIORITY does not match visual-unification direction")
if owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY current stage does not match country/destination unification")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match visual-unification direction")
if state.get("canonical_design_system") != EXPECTED_DESIGN_SYSTEM:
    errors.append("AUTOPILOT_STATE must keep Design System 1.0 canonical")
if (state.get("owner_priority_lock") or {}).get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner-priority lock does not match visual-unification direction")
if (state.get("current_stage") or {}).get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match country/destination unification")
if "## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0" not in autopilot:
    errors.append("AUTOPILOT.md does not declare the current Design System 1.0 phase")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK status=inactive owner_mode=ux_visual")
