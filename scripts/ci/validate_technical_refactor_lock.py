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
EXPECTED_STAGE = "TECHNICAL_REFACTOR_CONSOLIDATION"
EXPECTED_ORDER = ["technical_refactor", "ux_visual", "content_seo", "cosmetic_cleanup"]
EXPECTED_SEQUENCE = [
    "architecture_and_source_of_truth",
    "file_inventory_and_dependency_map",
    "ci_workflow_audit_and_proof_based_deduplication",
    "safe_directory_ownership",
    "shared_template_consolidation",
    "ux_visual",
]
errors = []

if lock.get("active") is not True:
    errors.append("TECHNICAL_REFACTOR_LOCK must remain active under the current explicit owner direction")
if lock.get("status") != "ACTIVE_EXPLICIT_OWNER_DIRECTION":
    errors.append("TECHNICAL_REFACTOR_LOCK status must record the active explicit owner direction")
if lock.get("mode") != EXPECTED_MODE or lock.get("phase") != EXPECTED_PHASE:
    errors.append("TECHNICAL_REFACTOR_LOCK mode/phase differs from the active technical refactor pass")
if lock.get("required_sequence") != EXPECTED_SEQUENCE:
    errors.append("TECHNICAL_REFACTOR_LOCK required sequence differs from the explicit refactor order")
if owner.get("active_mode") != EXPECTED_MODE or owner.get("active_phase") != EXPECTED_PHASE:
    errors.append("OWNER_PRIORITY does not match the technical refactor owner direction")
if owner.get("current_stage") != EXPECTED_STAGE:
    errors.append("OWNER_PRIORITY current stage does not match technical refactor consolidation")
if owner.get("priority_after_emergency_overrides") != EXPECTED_ORDER:
    errors.append("OWNER_PRIORITY priority order does not match technical-refactor-first direction")
if state.get("mode") != EXPECTED_MODE or state.get("phase") != EXPECTED_PHASE:
    errors.append("AUTOPILOT_STATE does not match the technical refactor owner direction")
state_lock = state.get("owner_priority_lock") or {}
if not state_lock.get("active") or state_lock.get("planned_phase") != EXPECTED_MODE:
    errors.append("AUTOPILOT_STATE owner priority lock does not match technical refactor")
current_stage = state.get("current_stage") or {}
if current_stage.get("id") != EXPECTED_STAGE:
    errors.append("AUTOPILOT_STATE current stage does not match technical refactor consolidation")
if "## Current owner-directed phase — TECHNICAL REFACTOR PASS" not in autopilot:
    errors.append("AUTOPILOT.md does not declare the technical refactor pass")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print("TECHNICAL_REFACTOR_LOCK_OK owner=technical_refactor_pass")
