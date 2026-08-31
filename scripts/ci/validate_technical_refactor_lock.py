#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_OWNER_MODE = "design_system_1_0"
EXPECTED_OWNER_PHASE = "ANYTOUR DESIGN SYSTEM 1.0"
EXPECTED_ORDER = ["ux_visual", "technical_refactor", "content_seo", "cosmetic_cleanup"]
errors = []

if lock.get("active") is not False:
    errors.append("TECHNICAL_REFACTOR_LOCK must be inactive after the newer explicit owner direction")
if lock.get("status") != "SUPERSEDED_BY_NEWER_EXPLICIT_OWNER_DIRECTION":
    errors.append("TECHNICAL_REFACTOR_LOCK must record that it was superseded")
if owner.get("active_mode") != EXPECTED_OWNER_MODE:
    errors.append(f"OWNER_PRIORITY active_mode={owner.get('active_mode')!r}; expected {EXPECTED_OWNER_MODE!r}")
if owner.get("active_phase") != EXPECTED_OWNER_PHASE:
    errors.append(f"OWNER_PRIORITY active_phase={owner.get('active_phase')!r}; expected {EXPECTED_OWNER_PHASE!r}")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order does not match Design System direction")
if state.get("mode") != EXPECTED_OWNER_MODE:
    errors.append(f"AUTOPILOT_STATE mode={state.get('mode')!r}; expected {EXPECTED_OWNER_MODE!r}")
state_lock = state.get("owner_priority_lock") or {}
if not state_lock.get("active") or state_lock.get("planned_phase") != EXPECTED_OWNER_MODE:
    errors.append("AUTOPILOT_STATE owner priority lock does not match Design System direction")
if f"## Current phase — {EXPECTED_OWNER_PHASE}" not in autopilot:
    errors.append("AUTOPILOT.md does not declare ANYTOUR DESIGN SYSTEM 1.0")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_SUPERSEDED_OK")
