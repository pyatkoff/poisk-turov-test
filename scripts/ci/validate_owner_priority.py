#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "technical_refactor"
EXPECTED_PHASE = "CI_COST_AUDIT_AND_CONSOLIDATION"
EXPECTED_STAGE = "CI_RESOURCE_CONSOLIDATION"
EXPECTED_DESIGN_SYSTEM = "ANYTOUR DESIGN SYSTEM 2.0"
EXPECTED_ORDER = [
    "technical_refactor",
    "ci_cost_audit",
    "architecture_source_of_truth",
    "ux_visual",
    "tour_data_platform",
    "content_seo",
    "cosmetic_cleanup",
]
errors = []

if owner.get("active_mode") != EXPECTED_MODE:
    errors.append("OWNER_PRIORITY active_mode differs from explicit technical-refactor direction")
if owner.get("active_phase") != EXPECTED_PHASE or owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY phase/stage differs from CI-cost audit direction")
if owner.get("canonical_design_system") != EXPECTED_DESIGN_SYSTEM:
    errors.append("OWNER_PRIORITY must keep AnyTour Design System 2.0 canonical")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY order differs from the owner-directed technical/CI sequence")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match technical-refactor direction")
if state.get("canonical_design_system") != EXPECTED_DESIGN_SYSTEM:
    errors.append("AUTOPILOT_STATE must keep AnyTour Design System 2.0 canonical")
lock = state.get("owner_priority_lock") or {}
if not lock.get("active") or lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE priority lock does not match technical-refactor direction")
if (state.get("current_stage") or {}).get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match CI resource consolidation")
if "## Current owner-directed phase — CI COST AUDIT AND TECHNICAL REFACTOR" not in autopilot:
    errors.append("AUTOPILOT.md must declare the technical CI audit phase as current")
if "AnyTour Design System 2.0" not in autopilot:
    errors.append("AUTOPILOT.md must preserve Design System 2.0 terminology")

if errors:
    print("OWNER_PRIORITY_GUARD_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"OWNER_PRIORITY_GUARD_OK mode={EXPECTED_MODE} stage={EXPECTED_STAGE}")
