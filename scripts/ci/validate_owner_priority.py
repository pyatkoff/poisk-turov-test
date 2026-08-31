#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "tour_data_platform"
EXPECTED_PHASE = "TOUR DATA PLATFORM"
EXPECTED_STAGE = "SCHEDULED_COLLECTION_AND_COVERAGE"
EXPECTED_ORDER = [
    "tour_data_platform",
    "collector_normalization_history",
    "price_intelligence",
    "hot_tours_price_calendar_seo_search",
    "ux_visual",
    "cosmetic_cleanup",
]
errors = []

if owner.get("active_mode") != EXPECTED_MODE:
    errors.append("OWNER_PRIORITY active_mode differs from explicit Tour Data Platform direction")
if owner.get("active_phase") != EXPECTED_PHASE or owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY phase/stage differs from Tour Data Platform direction")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY order differs from the owner-directed data-platform sequence")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match Tour Data Platform direction")
lock = state.get("owner_priority_lock") or {}
if not lock.get("active") or lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE priority lock does not match Tour Data Platform direction")
if (state.get("current_stage") or {}).get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match scheduled collection/coverage")
if "## Current owner-directed phase — TOUR DATA PLATFORM" not in autopilot:
    errors.append("AUTOPILOT.md must declare TOUR DATA PLATFORM as current owner-directed phase")

if errors:
    print("OWNER_PRIORITY_GUARD_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"OWNER_PRIORITY_GUARD_OK mode={EXPECTED_MODE} stage={EXPECTED_STAGE}")
