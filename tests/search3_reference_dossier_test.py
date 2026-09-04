#!/usr/bin/env python3
"""Adversarial checks for the Search3 reference dossier guard."""

from __future__ import annotations

import copy
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
VALIDATOR_PATH = ROOT / "scripts/ci/validate_search3_reference_dossier.py"
MANIFEST_PATH = ROOT / "docs/project/search3-reference-dossier.json"
DOCUMENT_PATH = ROOT / "docs/project/SEARCH3_REFERENCE_DOSSIER.md"

SPEC = importlib.util.spec_from_file_location("search3_reference_validator", VALIDATOR_PATH)
assert SPEC and SPEC.loader
VALIDATOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(VALIDATOR)


class Search3ReferenceDossierTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.manifest = json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
        cls.document = DOCUMENT_PATH.read_text(encoding="utf-8")

    def validate_mutation(self, mutate) -> list[str]:
        data = copy.deepcopy(self.manifest)
        mutate(data)
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            manifest = root / "manifest.json"
            document = root / "dossier.md"
            manifest.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
            document.write_text(self.document, encoding="utf-8")
            return VALIDATOR.validate(manifest, document)

    def test_checked_in_dossier_is_valid(self) -> None:
        self.assertEqual(VALIDATOR.validate(MANIFEST_PATH, DOCUMENT_PATH), [])

    def test_rejects_missing_approved_layout(self) -> None:
        errors = self.validate_mutation(lambda data: data["layouts"].pop())
        self.assertTrue(any("exactly the eight" in error for error in errors))

    def test_rejects_mutable_protected_contract(self) -> None:
        errors = self.validate_mutation(
            lambda data: data["protected_contracts"][0].update(change_allowed=True)
        )
        self.assertTrue(any("became mutable" in error for error in errors))

    def test_rejects_search3_as_canonical_runtime_owner(self) -> None:
        errors = self.validate_mutation(
            lambda data: data["ownership"][0].update(canonical_owner="feature/search3-preview")
        )
        self.assertTrue(any("lost main ownership" in error for error in errors))

    def test_rejects_overstated_artifact_durability(self) -> None:
        errors = self.validate_mutation(
            lambda data: data["run_467"].update(artifact_bytes_vendored=True)
        )
        self.assertTrue(any("durability" in error for error in errors))

    def test_rejects_changed_frozen_source_digest(self) -> None:
        errors = self.validate_mutation(
            lambda data: data["source_identity"]["source_files"][0].update(sha256="0" * 64)
        )
        self.assertTrue(any("source identity drift" in error for error in errors))

    def test_rejects_unlocked_production_deploy(self) -> None:
        errors = self.validate_mutation(
            lambda data: data["release_lock"].update(production_deploy="ALLOWED")
        )
        self.assertTrue(any("deploy lock" in error for error in errors))


if __name__ == "__main__":
    unittest.main()
