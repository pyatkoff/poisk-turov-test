#!/usr/bin/env python3
"""Validate the project-scoped AnyTour agent topology and its safety boundary."""

from __future__ import annotations

import argparse
import re
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
CONFIG_KEYS = {"agents"}
AGENT_CONFIG_KEYS = {
    "enabled",
    "max_concurrent_threads_per_session",
    "default_subagent_model",
    "default_subagent_reasoning_effort",
    "interrupt_message",
}
CUSTOM_AGENT_KEYS = {
    "name",
    "description",
    "model",
    "model_reasoning_effort",
    "sandbox_mode",
    "developer_instructions",
}
IMPLEMENTATION_WORKERS = {
    "search_contract_worker",
    "search_ui_worker",
    "seo_foundation_worker",
}
READ_ONLY_AGENTS = {
    "anytour_release_architect",
    "visual_evidence_reviewer",
    "integration_reviewer",
}
REQUIRED_AGENT_PHRASES = {
    "anytour_orchestrator": (
        "accountable day-to-day AnyTour program orchestrator",
        "allow at most three concurrent write-heavy lanes",
        "Use search_ui_worker only after",
        "Escalate cross-program architecture",
        "Never infer merge or deploy authority",
    ),
    "anytour_release_architect": (
        "Stay read-only",
        "last-known-good target",
        "serialized deployment writers",
        "Never merge or deploy",
    ),
    "search_contract_worker": (
        "assigned worktree and branch",
        "Do not change v2 runtime behavior",
        "stop and report the collision",
        "Never merge or deploy",
    ),
    "search_ui_worker": (
        "assigned worktree and branch",
        "approved reference identity",
        "protected contract baseline",
        "Stop on a file-owner collision",
        "Never merge or deploy",
    ),
    "seo_foundation_worker": (
        "assigned worktree and branch",
        "never duplicate Tourvisor",
        "Do not change public routes",
        "Avoid files owned by active Search3",
        "Never merge or deploy",
    ),
    "visual_evidence_reviewer": (
        "Stay read-only",
        "Verify 375, 430, 768, 1024, and 1440",
        "Never claim missing or expiring pixels",
        "do not edit product code",
    ),
    "integration_reviewer": (
        "declared exact base",
        "another active draft PR touches the same files",
        "Never mark a branch ready solely because CI is green",
        "Never merge or deploy",
    ),
}
REQUIRED_DOCUMENT_CLAUSES = (
    "Status: execution governance only. It does not authorize merge, preview deployment, or production deployment.",
    "up to three concurrent write-heavy worktrees",
    "Keep no more than four active change lanes plus one parked HIGH-review lane.",
    "The currently recorded user authority is `push + draft PR`, with no merge or deploy.",
    "no workflow may be dispatched and no branch that auto-deploys may be pushed.",
    "#1295 project definition",
    "#1296 release baseline",
    "#1297 exact-SHA containment",
    "#1298 Search3 reference dossier",
)


def workflow_policy_errors(workflow: str) -> list[str]:
    """Validate the small security-relevant YAML surface without a YAML dependency."""
    errors: list[str] = []
    code_lines = [line.split("#", 1)[0].rstrip() for line in workflow.splitlines()]

    if any(re.search(r"\bworkflow_dispatch\b", line) for line in code_lines):
        errors.append("orchestration validation workflow must not be dispatchable")

    headers: list[tuple[int, int, str]] = []
    permission_pattern = re.compile(r"^(\s*)[\"']?permissions[\"']?\s*:\s*(.*?)\s*$")
    for index, line in enumerate(code_lines):
        match = permission_pattern.match(line)
        if match:
            headers.append((index, len(match.group(1)), match.group(2)))

    if len(headers) != 1:
        errors.append("workflow must declare exactly one top-level permissions block")
        return errors

    index, indent, inline = headers[0]
    if indent != 0 or inline:
        errors.append("orchestration workflow permissions must be a top-level mapping")
        return errors

    entries: list[tuple[str, str]] = []
    entry_pattern = re.compile(r"^\s{2}([A-Za-z0-9_-]+)\s*:\s*([^\s]+)\s*$")
    for line in code_lines[index + 1 :]:
        if not line.strip():
            continue
        current_indent = len(line) - len(line.lstrip())
        if current_indent == 0:
            break
        match = entry_pattern.match(line)
        if not match:
            errors.append("orchestration workflow permissions block has unsupported structure")
            return errors
        entries.append((match.group(1), match.group(2)))

    if entries != [("contents", "read")]:
        errors.append(f"orchestration workflow permissions must remain only contents: read, got {entries}")
    return errors
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

    unknown_config = set(config) - CONFIG_KEYS
    if unknown_config:
        errors.append(f"unsupported project config keys: {sorted(unknown_config)}")

    agents = config.get("agents") or {}
    unknown_agent_config = set(agents) - AGENT_CONFIG_KEYS
    if unknown_agent_config:
        errors.append(f"unsupported agents config keys: {sorted(unknown_agent_config)}")
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
        unknown_custom = set(item) - CUSTOM_AGENT_KEYS
        if unknown_custom:
            errors.append(f"unsupported custom agent keys in {path.name}: {sorted(unknown_custom)}")
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
        instructions = str(item.get("developer_instructions", ""))
        for phrase in REQUIRED_AGENT_PHRASES.get(name, ()):
            if phrase not in instructions:
                errors.append(f"{name} missing required safeguard: {phrase}")

    ultra_agents = [name for name, item in found.items() if item.get("model_reasoning_effort") == "ultra"]
    if ultra_agents != ["anytour_release_architect"]:
        errors.append(f"Ultra must be reserved for the release architect, got {ultra_agents}")

    for reviewer in sorted(READ_ONLY_AGENTS):
        if (found.get(reviewer) or {}).get("sandbox_mode") != "read-only":
            errors.append(f"{reviewer} must remain read-only")

    if len(IMPLEMENTATION_WORKERS) != 3 or not IMPLEMENTATION_WORKERS.issubset(found):
        errors.append("implementation writer set must remain exactly three bounded roles")
    for worker in sorted(IMPLEMENTATION_WORKERS):
        if (found.get(worker) or {}).get("sandbox_mode") == "read-only":
            errors.append(f"{worker} must remain an implementation-capable bounded worker")

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
    for clause in REQUIRED_DOCUMENT_CLAUSES:
        if clause not in document:
            errors.append(f"parallel delivery policy clause missing: {clause}")

    workflow_path = root / ".github/workflows/validate-anytour-agent-orchestration.yml"
    try:
        workflow = workflow_path.read_text(encoding="utf-8")
    except OSError as exc:
        errors.append(f"orchestration workflow unreadable: {exc}")
    else:
        errors.extend(workflow_policy_errors(workflow))

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
