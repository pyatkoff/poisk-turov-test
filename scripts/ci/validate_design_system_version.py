#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parents[2]
legacy = "1" + ".0"
needles = (f"Design System {legacy}", f"DESIGN SYSTEM {legacy}", "design_system_" + "1_0")
skip = {".git"}
violations = []
for path in root.rglob("*"):
    if not path.is_file() or any(part in skip for part in path.parts):
        continue
    try:
        text = path.read_text(encoding="utf-8")
    except (UnicodeDecodeError, OSError):
        continue
    for needle in needles:
        if needle in text:
            violations.append(f"{path.relative_to(root)}: {needle}")
if violations:
    print("DESIGN_SYSTEM_VERSION_GUARD_FAIL")
    print("\n".join(violations))
    raise SystemExit(1)
print("DESIGN_SYSTEM_VERSION_GUARD_OK version=2.0")
