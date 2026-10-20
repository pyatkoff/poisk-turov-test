#!/usr/bin/env python3

import hashlib
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from scripts.ci.render_search3_visual_summary import render


class Search3VisualSummaryTest(unittest.TestCase):
    def test_visual_tiers_cover_every_canonical_width_once(self):
        repository = Path(__file__).resolve().parents[1]
        fixture = json.loads(
            (repository / "tests/fixtures/search3-candidate-scaffold.json").read_text(encoding="utf-8")
        )
        self.assertEqual(fixture["schemaVersion"], 2)
        canonical = [375, 430, 768, 1024, 1440]
        expected_capture_counts = {"pr": 12, "candidate": 18}
        for name, tier in fixture["visualTiers"].items():
            assigned = tier["lifecycleWidths"] + tier["finalOnlyWidths"]
            self.assertEqual(sorted(assigned), canonical, name)
            self.assertEqual(len(assigned), len(set(assigned)), name)
            screenshots = len(tier["lifecycleWidths"]) * 3 + len(tier["finalOnlyWidths"])
            screenshots += len(tier["presentationCaptures"])
            self.assertEqual(screenshots, expected_capture_counts[name], name)

    def test_renders_verified_review_indexes(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            capture = root / "375-final-100.png"
            capture.write_bytes(b"deterministic-png-fixture")
            digest = hashlib.sha256(capture.read_bytes()).hexdigest()
            manifest = root / "manifest.json"
            manifest.write_text(json.dumps({
                "schemaVersion": 2,
                "visualTier": "pr",
                "sourceSha": "a" * 40,
                "testedSha": "a" * 40,
                "visualBaseline": {"ownerVisualApproval": False},
                "screenshots": [{
                    "file": capture.name,
                    "sha256": digest,
                    "geometry": {"viewportWidth": 375, "state": "final-100"},
                }],
                "presentationScreenshots": [],
                "behaviorStates": [{"name": "search-empty", "passed": True}],
            }), encoding="utf-8")

            html_path, markdown_path = render(manifest, root)
            self.assertIn("tier=pr", html_path.read_text(encoding="utf-8"))
            self.assertIn("375-final-100.png", html_path.read_text(encoding="utf-8"))
            self.assertIn("Owner visual approval: `False`", markdown_path.read_text(encoding="utf-8"))

    def test_rejects_capture_path_traversal(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            manifest = root / "manifest.json"
            manifest.write_text(json.dumps({
                "schemaVersion": 2,
                "visualTier": "candidate",
                "sourceSha": "a" * 40,
                "testedSha": "a" * 40,
                "screenshots": [{"file": "../escape.png", "sha256": "0" * 64}],
                "presentationScreenshots": [],
                "behaviorStates": [],
            }), encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "unsafe capture name"):
                render(manifest, root)


if __name__ == "__main__":
    unittest.main()
