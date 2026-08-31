#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "technical_refactor_pass"
EXPECTED_PHASE = "TECHNICAL REFACTOR PASS"
EXPECTED_STAGE = "TECHNICAL_REFACTOR_PASS"
errors = []

if lock.get("active") is not True or lock.get("status") != "ACTIVE_EXPLICIT_OWNER_DIRECTION":
    errors.append("TECHNICAL_REFACTOR_LOCK must be active under the current explicit owner direction")
if lock.get("mode") != EXPECTED_MODE or lock.get("phase") != EXPECTED_PHASE:
    errors.append("TECHNICAL_REFACTOR_LOCK mode/phase mismatch")
if owner.get("active_mode") != EXPECTED_MODE or owner.get("active_phase") != EXPECTED_PHASE:
    errors.append("OWNER_PRIORITY does not match technical-refactor direction")
if owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY current stage does not match technical refactor")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match technical refactor")
if (state.get("owner_priority_lock") or {}).get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner-priority lock does not match technical refactor")
if (state.get("current_stage") or {}).get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match technical refactor")
if "## Current owner-directed phase — TECHNICAL REFACTOR PASS" not in autopilot:
    errors.append("AUTOPILOT.md does not declare technical refactor as current")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK owner=technical_refactor_pass")
