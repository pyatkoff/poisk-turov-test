#!/usr/bin/env python3
"""Validate the project-scoped AnyTour agent topology and its safety boundary."""

from __future__ import annotations

import argparse
import tomllib
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
CONFIG = ROOT / ".codex/config.toml"
AGENTS_DIR = ROOT / ".codex/agents"
DOCUMENT = ROOT / "docs/project/PARALLEL_DELIVERY.md"

EXPECTED = {
    "anytour_orchestrator": ("gpt-5.6-sol", "high", None),
    "anytour_release_architect": ("gpt-5.6-sol", "ultra", "read-only"),
    "search_contract_worker": ("gpt-5.6-sol", "high", None),
    "search_ui_worker": ("gpt-5.6-sol", "high", None),
    "seo_foundation_worker": ("gpt-5.6-terra", "high", None),
    "visual_evidence_reviewer": ("gpt-5.6-terra", "high", "read-only"),
    "integration_reviewer": ("gpt-5.6-sol", "high", "read-only"),
}
PROTECTED_TERMS = (
    "Tourvisor",
    "lead payload/field mapping/delivery",
    "Metrika",
    "pricing",
    "Never merge or deploy",
)


def validate(root: Path = ROOT) -> list[str]:
    errors: list[str] = []
    try:
        config = tomllib.loads((root / CONFIG.relative_to(ROOT)).read_text(encoding="utf-8"))
    except (OSError, tomllib.TOMLDecodeError) as exc:
        return [f"config unreadable: {exc}"]

    agents = config.get("agents") or {}
    if agents.get("enabled") is not True:
        errors.append("subagents must be explicitly enabled")
    if agents.get("max_concurrent_threads_per_session") != 6:
        errors.append("thread cap must be 6")
    if agents.get("default_subagent_model") != "gpt-5.6-terra":
        errors.append("default worker model must be gpt-5.6-terra")
    if agents.get("default_subagent_reasoning_effort") != "medium":
        errors.append("default worker reasoning must be medium")

    found: dict[str, dict] = {}
    for path in sorted((root / AGENTS_DIR.relative_to(ROOT)).glob("*.toml")):
        try:
            item = tomllib.loads(path.read_text(encoding="utf-8"))
        except tomllib.TOMLDecodeError as exc:
            errors.append(f"invalid agent TOML {path.name}: {exc}")
            continue
        name = item.get("name")
        if not name:
            errors.append(f"agent without name: {path.name}")
            continue
        if name in found:
            errors.append(f"duplicate agent name: {name}")
        found[name] = item

    if set(found) != set(EXPECTED):
        errors.append(f"agent set drift: expected {sorted(EXPECTED)}, got {sorted(found)}")

    for name, expected in EXPECTED.items():
        item = found.get(name) or {}
        actual = (item.get("model"), item.get("model_reasoning_effort"), item.get("sandbox_mode"))
        if actual != expected:
            errors.append(f"{name} model/reasoning/sandbox drift: expected {expected}, got {actual}")
        if not item.get("description") or not item.get("developer_instructions"):
            errors.append(f"{name} missing description or developer instructions")

    ultra_agents = [name for name, item in found.items() if item.get("model_reasoning_effort") == "ultra"]
    if ultra_agents != ["anytour_release_architect"]:
        errors.append(f"Ultra must be reserved for the release architect, got {ultra_agents}")

    for reviewer in ("anytour_release_architect", "visual_evidence_reviewer", "integration_reviewer"):
        if (found.get(reviewer) or {}).get("sandbox_mode") != "read-only":
            errors.append(f"{reviewer} must remain read-only")

    try:
        document = (root / DOCUMENT.relative_to(ROOT)).read_text(encoding="utf-8")
    except OSError as exc:
        return errors + [f"parallel delivery document unreadable: {exc}"]
    for term in PROTECTED_TERMS:
        if term not in document and not any(term in str(item.get("developer_instructions", "")) for item in found.values()):
            errors.append(f"missing protected boundary: {term}")
    for authority in ("push", "draft PR", "merge", "preview deploy", "production deploy"):
        if authority not in document:
            errors.append(f"permission level missing from document: {authority}")

    return errors


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=ROOT)
    args = parser.parse_args()
    errors = validate(args.root.resolve())
    if errors:
        print("ANYTOUR_AGENT_ORCHESTRATION_FAIL")
        for error in errors:
            print(f"- {error}")
        return 1
    print("ANYTOUR_AGENT_ORCHESTRATION_OK agents=7 max_threads=6 writers=3 reviewers=3 ultra=release_architect")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
