#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_OWNER_MODE = "design_system_1_0_site_unification"
EXPECTED_OWNER_PHASE = "ANYTOUR DESIGN SYSTEM 1.0 / SITE-WIDE VISUAL UNIFICATION"
EXPECTED_STAGE = "DESIGN_SYSTEM_1_0_SITE_UNIFICATION"
errors = []

if lock.get("active") is not False:
    errors.append("TECHNICAL_REFACTOR_LOCK must be inactive under the current Design System 1.0 owner direction")
if lock.get("status") != "SUPERSEDED_BY_NEWER_EXPLICIT_OWNER_DIRECTION":
    errors.append("TECHNICAL_REFACTOR_LOCK must record that refactor-first was superseded")
if owner.get("active_mode") != EXPECTED_OWNER_MODE or owner.get("active_phase") != EXPECTED_OWNER_PHASE:
    errors.append("OWNER_PRIORITY does not match the Design System 1.0 owner direction")
if owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY current stage does not match Design System 1.0")
if state.get("mode") != EXPECTED_OWNER_MODE or state.get("phase") != EXPECTED_OWNER_PHASE:
    errors.append("AUTOPILOT_STATE does not match Design System 1.0")
state_lock = state.get("owner_priority_lock") or {}
if not state_lock.get("active") or state_lock.get("planned_phase") != EXPECTED_OWNER_MODE:
    errors.append("AUTOPILOT_STATE owner priority lock does not match Design System 1.0")
current_stage = state.get("current_stage") or {}
if current_stage.get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match Design System 1.0")
if "## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0" not in autopilot:
    errors.append("AUTOPILOT.md does not declare Design System 1.0 as current")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_SUPERSEDED_OK owner=design_system_1_0_site_unification")
