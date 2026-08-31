#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "technical_refactor_pass"
EXPECTED_PHASE = "TECHNICAL REFACTOR PASS"
EXPECTED_STAGE = "TECHNICAL_REFACTOR_PASS"
EXPECTED_ORDER = ["technical_refactor", "ux_visual", "content_seo", "cosmetic_cleanup"]
errors = []

if owner.get("active_mode") != EXPECTED_MODE:
    errors.append("OWNER_PRIORITY active_mode differs from explicit technical-refactor direction")
if owner.get("active_phase") != EXPECTED_PHASE or owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY phase/stage differs from technical-refactor direction")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY order differs from technical_refactor -> ux_visual -> content_seo -> cosmetic_cleanup")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match technical-refactor direction")
lock = state.get("owner_priority_lock") or {}
if not lock.get("active") or lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE priority lock does not match technical-refactor direction")
if (state.get("current_stage") or {}).get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match technical refactor")
if "## Current owner-directed phase — TECHNICAL REFACTOR PASS" not in autopilot:
    errors.append("AUTOPILOT.md must declare technical refactor as current owner-directed phase")

if errors:
    print("OWNER_PRIORITY_GUARD_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"OWNER_PRIORITY_GUARD_OK mode={EXPECTED_MODE} stage={EXPECTED_STAGE}")
