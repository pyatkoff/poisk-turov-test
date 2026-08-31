#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "design_system_1_0_site_unification"
EXPECTED_PHASE = "ANYTOUR DESIGN SYSTEM 1.0 / SITE-WIDE VISUAL UNIFICATION"
EXPECTED_STAGE = "DESIGN_SYSTEM_1_0_SITE_UNIFICATION"
EXPECTED_ORDER = [
    "design_system_1_0_site_unification",
    "search_results_selected_tour_regression_preservation",
    "technical_refactor",
    "content_seo",
    "cosmetic_cleanup",
]
errors = []

if owner.get("active_mode") != EXPECTED_MODE:
    errors.append(f"OWNER_PRIORITY active_mode={owner.get('active_mode')!r}, expected {EXPECTED_MODE!r}")
if owner.get("active_phase") != EXPECTED_PHASE:
    errors.append(f"OWNER_PRIORITY active_phase={owner.get('active_phase')!r}, expected {EXPECTED_PHASE!r}")
if owner.get("current_stage") != EXPECTED_STAGE:
    errors.append(f"OWNER_PRIORITY current_stage={owner.get('current_stage')!r}, expected {EXPECTED_STAGE!r}")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order differs from the current explicit owner direction")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match the Design System 1.0 owner direction")
lock = state.get("owner_priority_lock") or {}
if not lock.get("active") or lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner priority lock does not match Design System 1.0")
current_stage = state.get("current_stage") or {}
if current_stage.get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match Design System 1.0")
if "## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0" not in autopilot:
    errors.append("AUTOPILOT.md must declare Design System 1.0 as the current owner-directed phase")
if "375 / 430 / 768 / 1024 / 1440" not in autopilot:
    errors.append("AUTOPILOT.md must retain the five-width visual validation contract")

if errors:
    print("OWNER_PRIORITY_GUARD_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"OWNER_PRIORITY_GUARD_OK mode={EXPECTED_MODE} stage={EXPECTED_STAGE}")
