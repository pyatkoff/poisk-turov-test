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
    def validate_mutation(self, relative_path: str, old: str, new: str) -> list[str]:
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            shutil.copytree(ROOT / ".codex", root / ".codex")
            (root / "docs/project").mkdir(parents=True)
            shutil.copy2(ROOT / "docs/project/PARALLEL_DELIVERY.md", root / "docs/project/PARALLEL_DELIVERY.md")
            path = root / relative_path
            text = path.read_text(encoding="utf-8")
            self.assertIn(old, text)
            path.write_text(text.replace(old, new, 1), encoding="utf-8")
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


if __name__ == "__main__":
    unittest.main()
