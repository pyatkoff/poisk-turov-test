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
    errors.append("TECHNICAL_REFACTOR_LOCK status must record active explicit owner direction")
if lock.get("mode") != EXPECTED_MODE or lock.get("phase") != EXPECTED_PHASE:
    errors.append("TECHNICAL_REFACTOR_LOCK mode/phase does not match explicit technical-refactor direction")
if owner.get("active_mode") != EXPECTED_MODE:
    errors.append(f"OWNER_PRIORITY active_mode={owner.get('active_mode')!r}; expected {EXPECTED_MODE!r}")
if owner.get("active_phase") != EXPECTED_PHASE:
    errors.append(f"OWNER_PRIORITY active_phase={owner.get('active_phase')!r}; expected {EXPECTED_PHASE!r}")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order does not match technical-refactor direction")
if state.get("mode") != EXPECTED_MODE:
    errors.append(f"AUTOPILOT_STATE mode={state.get('mode')!r}; expected {EXPECTED_MODE!r}")
state_lock = state.get("owner_priority_lock") or {}
if not state_lock.get("active") or state_lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner priority lock does not match technical-refactor direction")
if f"## Current phase — {EXPECTED_PHASE}" not in autopilot:
    errors.append("AUTOPILOT.md does not declare TECHNICAL REFACTOR PASS")
for required in ["ARCHITECTURE.md", "TEST_MATRIX.md", "DEPENDENCY_MAP.md", "CI_WORKFLOW_AUDIT.md", "one concept → one implementation"]:
    if required not in autopilot:
        errors.append(f"AUTOPILOT.md missing technical source-of-truth reference: {required}")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK")
