#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

errors = []
if lock.get("active") is not False:
    errors.append("TECHNICAL_REFACTOR_LOCK must remain inactive after owner reprioritized Tour Data Platform")
if lock.get("status") != "SUPERSEDED_BY_OWNER_TOUR_DATA_PLATFORM_PRIORITY":
    errors.append("TECHNICAL_REFACTOR_LOCK status must record the superseding owner direction")
if owner.get("active_mode") != "tour_data_platform":
    errors.append("OWNER_PRIORITY must keep Tour Data Platform active")
if state.get("mode") != "tour_data_platform":
    errors.append("AUTOPILOT_STATE must keep Tour Data Platform active")
if "## Current owner-directed phase — TOUR DATA PLATFORM" not in autopilot:
    errors.append("AUTOPILOT.md must keep Tour Data Platform active")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK status=superseded owner=tour_data_platform")
