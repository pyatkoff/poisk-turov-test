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
EXPECTED_ORDER = [
    "technical_refactor",
    "ux_visual",
    "content_seo",
    "cosmetic_cleanup",
]
EXPECTED_SEQUENCE = [
    "architecture_source_of_truth",
    "inventory_dependency_map",
    "github_actions_audit",
    "safe_directory_ownership",
    "shared_template_consolidation",
    "ux_visual_after_technical_consolidation",
]

errors = []

if lock.get("active_mode") != EXPECTED_MODE:
    errors.append("TECHNICAL_REFACTOR_LOCK active_mode must remain technical_refactor_pass")
if lock.get("active_phase") != EXPECTED_PHASE:
    errors.append("TECHNICAL_REFACTOR_LOCK active_phase must remain TECHNICAL REFACTOR PASS")
if lock.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("TECHNICAL_REFACTOR_LOCK priority order changed")
if lock.get("required_sequence") != EXPECTED_SEQUENCE:
    errors.append("TECHNICAL_REFACTOR_LOCK required sequence changed")

if owner.get("active_mode") != EXPECTED_MODE:
    errors.append(f"OWNER_PRIORITY active_mode={owner.get('active_mode')!r}; expected {EXPECTED_MODE!r}")
if owner.get("active_phase") != EXPECTED_PHASE:
    errors.append(f"OWNER_PRIORITY active_phase={owner.get('active_phase')!r}; expected {EXPECTED_PHASE!r}")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order does not match technical refactor lock")

if state.get("mode") != EXPECTED_MODE:
    errors.append(f"AUTOPILOT_STATE mode={state.get('mode')!r}; expected {EXPECTED_MODE!r}")
state_lock = state.get("owner_priority_lock") or {}
if not state_lock.get("active"):
    errors.append("AUTOPILOT_STATE owner_priority_lock.active must be true")
if state_lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner_priority_lock.planned_phase does not match technical refactor lock")

if f"## Current phase — {EXPECTED_PHASE}" not in autopilot:
    errors.append("AUTOPILOT.md does not declare TECHNICAL REFACTOR PASS")
if "technical_refactor → ux_visual → content_seo → cosmetic_cleanup" not in autopilot:
    errors.append("AUTOPILOT.md does not contain the canonical technical-refactor priority order")
for required in [
    "ARCHITECTURE.md",
    "TEST_MATRIX.md",
    "DEPENDENCY_MAP.md",
    "CI_WORKFLOW_AUDIT.md",
    "one concept → one implementation",
]:
    if required not in autopilot:
        errors.append(f"AUTOPILOT.md missing required technical source-of-truth reference: {required}")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK")
