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
EXPECTED_ORDER = ["technical_refactor", "ux_visual", "content_seo", "cosmetic_cleanup"]
errors = []

if lock.get("active") is not True:
    errors.append("TECHNICAL_REFACTOR_LOCK must be active")
if lock.get("status") != "ACTIVE_EXPLICIT_OWNER_DIRECTION":
    errors.append("TECHNICAL_REFACTOR_LOCK must record the active explicit owner direction")
if lock.get("mode") != EXPECTED_MODE or lock.get("phase") != EXPECTED_PHASE:
    errors.append("TECHNICAL_REFACTOR_LOCK mode/phase differs from the technical refactor direction")
if owner.get("active_mode") != EXPECTED_MODE or owner.get("active_phase") != EXPECTED_PHASE:
    errors.append("OWNER_PRIORITY does not match the technical refactor direction")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order does not match the technical refactor direction")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match the technical refactor direction")
state_lock = state.get("owner_priority_lock") or {}
if not state_lock.get("active") or state_lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner priority lock does not match the technical refactor direction")
if f"## Current phase — {EXPECTED_PHASE}" not in autopilot:
    errors.append("AUTOPILOT.md does not declare the technical refactor phase")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK owner=technical_refactor_pass")
