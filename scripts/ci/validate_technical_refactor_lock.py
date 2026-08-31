#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_OWNER_MODE = "anytour_2_0_site_9_0"
EXPECTED_OWNER_PHASE = "ANYTOUR 2.0 → SITE 9.0/10"
EXPECTED_STAGE = "DESIGN_2_0_FOUNDATION"
EXPECTED_ORDER = ["anytour_2_0_roadmap", "technical_refactor", "cosmetic_cleanup"]
errors = []

if lock.get("active") is not False:
    errors.append("TECHNICAL_REFACTOR_LOCK must remain inactive under the newer AnyTour 2.0 owner direction")
if lock.get("status") != "SUPERSEDED_BY_NEWER_EXPLICIT_OWNER_DIRECTION":
    errors.append("TECHNICAL_REFACTOR_LOCK must record that it was superseded")
if owner.get("active_mode") != EXPECTED_OWNER_MODE:
    errors.append(f"OWNER_PRIORITY active_mode={owner.get('active_mode')!r}; expected {EXPECTED_OWNER_MODE!r}")
if owner.get("active_phase") != EXPECTED_OWNER_PHASE:
    errors.append(f"OWNER_PRIORITY active_phase={owner.get('active_phase')!r}; expected {EXPECTED_OWNER_PHASE!r}")
if owner.get("current_stage") != EXPECTED_STAGE:
    errors.append(f"OWNER_PRIORITY current_stage={owner.get('current_stage')!r}; expected {EXPECTED_STAGE!r}")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order does not match the AnyTour 2.0 direction")
if state.get("mode") != EXPECTED_OWNER_MODE or state.get("phase") != EXPECTED_OWNER_PHASE:
    errors.append("AUTOPILOT_STATE does not match the AnyTour 2.0 owner direction")
state_lock = state.get("owner_priority_lock") or {}
if not state_lock.get("active") or state_lock.get("planned_phase") != EXPECTED_OWNER_MODE:
    errors.append("AUTOPILOT_STATE owner priority lock does not match the AnyTour 2.0 direction")
current_stage = state.get("current_stage") or {}
if current_stage.get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match Design 2.0 foundation")
if "## North-star — ANYTOUR 2.0 → SITE 9.0/10" not in autopilot:
    errors.append("AUTOPILOT.md does not declare the AnyTour 2.0 / site 9.0 north-star")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_SUPERSEDED_OK owner=anytour_2_0_site_9_0")
