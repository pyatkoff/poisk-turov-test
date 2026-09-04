#!/usr/bin/env python3
"""Adversarial tests for project-scoped AnyTour agent configuration."""

from __future__ import annotations

import importlib.util
import shutil
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
VALIDATOR_PATH = ROOT / "scripts/ci/validate_anytour_agent_orchestration.py"
SPEC = importlib.util.spec_from_file_location("agent_orchestration_validator", VALIDATOR_PATH)
assert SPEC and SPEC.loader
VALIDATOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(VALIDATOR)


class AnyTourAgentOrchestrationTest(unittest.TestCase):
    def copied_root(self, temporary: str) -> Path:
        root = Path(temporary)
        shutil.copytree(ROOT / ".codex", root / ".codex")
        (root / "docs/project").mkdir(parents=True)
        shutil.copy2(ROOT / "docs/project/PARALLEL_DELIVERY.md", root / "docs/project/PARALLEL_DELIVERY.md")
        (root / ".github/workflows").mkdir(parents=True)
        shutil.copy2(
            ROOT / ".github/workflows/validate-anytour-agent-orchestration.yml",
            root / ".github/workflows/validate-anytour-agent-orchestration.yml",
        )
        return root

    def validate_mutation(self, relative_path: str, old: str, new: str) -> list[str]:
        with tempfile.TemporaryDirectory() as temporary:
            root = self.copied_root(temporary)
            path = root / relative_path
            text = path.read_text(encoding="utf-8")
            self.assertIn(old, text)
            path.write_text(text.replace(old, new, 1), encoding="utf-8")
            return VALIDATOR.validate(root)

    def validate_append(self, relative_path: str, addition: str) -> list[str]:
        with tempfile.TemporaryDirectory() as temporary:
            root = self.copied_root(temporary)
            path = root / relative_path
            path.write_text(path.read_text(encoding="utf-8") + addition, encoding="utf-8")
            return VALIDATOR.validate(root)

    def test_checked_in_topology_is_valid(self) -> None:
        self.assertEqual(VALIDATOR.validate(ROOT), [])

    def test_rejects_extra_writer_threads(self) -> None:
        errors = self.validate_mutation(
            ".codex/config.toml",
            "max_concurrent_threads_per_session = 6",
            "max_concurrent_threads_per_session = 12",
        )
        self.assertTrue(any("thread cap" in error for error in errors))

    def test_rejects_ultra_worker(self) -> None:
        errors = self.validate_mutation(
            ".codex/agents/seo-foundation-worker.toml",
            'model_reasoning_effort = "high"',
            'model_reasoning_effort = "ultra"',
        )
        self.assertTrue(any("Ultra must be reserved" in error for error in errors))

    def test_rejects_ultra_day_to_day_orchestrator(self) -> None:
        errors = self.validate_mutation(
            ".codex/agents/anytour-orchestrator.toml",
            'model_reasoning_effort = "high"',
            'model_reasoning_effort = "ultra"',
        )
        self.assertTrue(any("Ultra must be reserved" in error for error in errors))

    def test_rejects_writable_integration_reviewer(self) -> None:
        errors = self.validate_mutation(
            ".codex/agents/integration-reviewer.toml",
            'sandbox_mode = "read-only"',
            'sandbox_mode = "workspace-write"',
        )
        self.assertTrue(any("must remain read-only" in error for error in errors))

    def test_rejects_unsupported_project_config_key(self) -> None:
        errors = self.validate_append(".codex/config.toml", '\nunsupported_policy_bypass = true\n')
        self.assertTrue(any("unsupported agents config keys" in error for error in errors))

    def test_rejects_removed_ui_worker_safeguard(self) -> None:
        errors = self.validate_mutation(
            ".codex/agents/search-ui-worker.toml",
            "Stop on a file-owner collision.",
            "Continue through a file-owner collision.",
        )
        self.assertTrue(any("search_ui_worker missing required safeguard" in error for error in errors))

    def test_rejects_authority_inversion(self) -> None:
        errors = self.validate_mutation(
            "docs/project/PARALLEL_DELIVERY.md",
            "The currently recorded user authority is `push + draft PR`, with no merge or deploy.",
            "The currently recorded user authority allows merge, preview deploy, production deploy, and workflow dispatch.",
        )
        self.assertTrue(any("policy clause missing" in error for error in errors))

    def test_rejects_dependency_identity_drift(self) -> None:
        errors = self.validate_mutation(
            "docs/project/PARALLEL_DELIVERY.md",
            "#1295 project definition",
            "#9999 project definition",
        )
        self.assertTrue(any("#1295 project definition" in error for error in errors))

    def test_rejects_dispatchable_orchestration_workflow(self) -> None:
        errors = self.validate_append(
            ".github/workflows/validate-anytour-agent-orchestration.yml",
            "\n# forbidden mutation\nworkflow_dispatch:\n",
        )
        self.assertTrue(any("must not be dispatchable" in error for error in errors))

    def test_rejects_dispatch_key_with_yaml_whitespace(self) -> None:
        errors = self.validate_append(
            ".github/workflows/validate-anytour-agent-orchestration.yml",
            "\n# alternate valid YAML spelling\nworkflow_dispatch :\n",
        )
        self.assertTrue(any("must not be dispatchable" in error for error in errors))

    def test_rejects_job_level_write_permission_override(self) -> None:
        errors = self.validate_mutation(
            ".github/workflows/validate-anytour-agent-orchestration.yml",
            "  validate:\n    runs-on: ubuntu-latest",
            "  validate:\n    permissions:\n      contents: write\n    runs-on: ubuntu-latest",
        )
        self.assertTrue(any("exactly one top-level permissions block" in error for error in errors))


if __name__ == "__main__":
    unittest.main()
