#!/usr/bin/env python3
"""Focused positive and fail-closed checks for the Search3 mockup manifest."""

from __future__ import annotations

import copy
import json
import tempfile
import unittest
from pathlib import Path

from scripts.ci.validate_search3_approved_mockups import MANIFEST, validate


class ApprovedMockupsTest(unittest.TestCase):
    def setUp(self) -> None:
        self.data = json.loads(MANIFEST.read_text(encoding="utf-8"))

    def validate_data(self, data: dict) -> list[str]:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "manifest.json"
            path.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
            return validate(path)

    def test_canonical_manifest_is_valid(self) -> None:
        self.assertEqual(validate(), [])

    def test_rejects_public_redistribution(self) -> None:
        data = copy.deepcopy(self.data)
        data["public_redistribution_authorized"] = True
        self.assertTrue(self.validate_data(data))

    def test_rejects_claimed_approval(self) -> None:
        data = copy.deepcopy(self.data)
        data["owner_visual_approval"] = True
        self.assertTrue(self.validate_data(data))

    def test_rejects_export_identity_drift(self) -> None:
        data = copy.deepcopy(self.data)
        data["exports"][0]["sha256"] = "0" * 64
        self.assertTrue(self.validate_data(data))

    def test_rejects_reordered_layouts(self) -> None:
        data = copy.deepcopy(self.data)
        data["exports"][0], data["exports"][1] = data["exports"][1], data["exports"][0]
        self.assertTrue(self.validate_data(data))


if __name__ == "__main__":
    unittest.main()
