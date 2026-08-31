#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / "TECHNICAL_REFACTOR_LOCK.json").read_text(encoding="utf-8"))
owner = json.loads((root / "OWNER_PRIORITY.json").read_text(encoding="utf-8"))
state = json.loads((root / "AUTOPILOT_STATE.json").read_text(encoding="utf-8"))

errors = []
owner_mode = owner.get("active_mode")
owner_phase = owner.get("active_phase")

if owner_mode == "technical_refactor":
    if lock.get("active") is not True:
        errors.append("TECHNICAL_REFACTOR_LOCK must be active while OWNER_PRIORITY selects technical_refactor")
    if lock.get("mode") != owner_mode or lock.get("phase") != owner_phase:
        errors.append("TECHNICAL_REFACTOR_LOCK must match the active technical-refactor owner phase")
else:
    if lock.get("active") is not False:
        errors.append("TECHNICAL_REFACTOR_LOCK must be inactive when OWNER_PRIORITY selects another phase")
    if lock.get("status") not in {"DEFERRED_BY_OWNER_DIRECTION", "INACTIVE"}:
        errors.append("Inactive TECHNICAL_REFACTOR_LOCK must record a deferred/inactive status")

if state.get("mode") != owner_mode or state.get("phase") != owner_phase:
    errors.append("AUTOPILOT_STATE must match OWNER_PRIORITY before evaluating technical lock")
if lock.get("scope") != "pyatkoff/poisk-turov-test":
    errors.append("TECHNICAL_REFACTOR_LOCK scope must remain inside this repository")

if errors:
    print("TECHNICAL_REFACTOR_LOCK_FAIL")
    for error in errors:
        print(f"- {error}")
    raise SystemExit(1)

print(f"TECHNICAL_REFACTOR_LOCK_OK active={lock.get('active')} owner_mode={owner_mode}")
