#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))
autopilot = (root / "AUTOPILOT.md").read_text(encoding="utf-8")

EXPECTED_MODE = "technical_refactor_pass"
EXPECTED_PHASE = "TECHNICAL REFACTOR PASS"
EXPECTED_ORDER = ["technical_refactor", "ux_visual", "content_seo", "cosmetic_cleanup"]
errors = []

if owner.get("active_mode") != EXPECTED_MODE:
    errors.append(f"OWNER_PRIORITY active_mode={owner.get('active_mode')!r}, expected {EXPECTED_MODE!r}")
if owner.get("active_phase") != EXPECTED_PHASE:
    errors.append(f"OWNER_PRIORITY active_phase={owner.get('active_phase')!r}, expected {EXPECTED_PHASE!r}")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order differs from the current explicit owner direction")
if state.get("mode") != EXPECTED_MODE:
    errors.append(f"AUTOPILOT_STATE mode={state.get('mode')!r}, expected {EXPECTED_MODE!r}")
if state.get("phase") != EXPECTED_PHASE:
    errors.append(f"AUTOPILOT_STATE phase={state.get('phase')!r}, expected {EXPECTED_PHASE!r}")
lock = state.get("owner_priority_lock") or {}
if not lock.get("active"):
    errors.append("AUTOPILOT_STATE owner_priority_lock.active must be true")
if lock.get("planned_phase") != EXPECTED_MODE:
    errors.append(f"AUTOPILOT_STATE owner_priority_lock.planned_phase={lock.get('planned_phase')!r}, expected {EXPECTED_MODE!r}")
if f"## Current phase — {EXPECTED_PHASE}" not in autopilot:
    errors.append("AUTOPILOT.md must declare the technical refactor phase")
if "technical_refactor → ux_visual → content_seo → cosmetic_cleanup" not in autopilot:
    errors.append("AUTOPILOT.md must contain the canonical technical-refactor priority order")

if errors:
    print("OWNER_PRIORITY_GUARD_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"OWNER_PRIORITY_GUARD_OK mode={EXPECTED_MODE}")
