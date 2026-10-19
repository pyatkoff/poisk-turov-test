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
    def test_workflow_runs_browser_only_by_explicit_opt_in_and_releases_candidate_only(self):
        repository = Path(__file__).resolve().parents[1]
        workflow = (
            repository / ".github/workflows/validate-search3-candidate-scaffold.yml"
        ).read_text(encoding="utf-8")
        self.assertNotIn("\n  push:\n", workflow)
        self.assertNotIn("- 'v2/**'", workflow)
        self.assertIn("- 'v2/_preview/search3-candidate/poisk-turov/**'", workflow)
        self.assertIn("default: smoke", workflow)
        self.assertIn("startsWith(github.head_ref, 'visual/search3-smoke-')", workflow)
        self.assertIn("startsWith(github.head_ref, 'visual/search3-candidate-')", workflow)
        self.assertIn(
            "SEARCH3_EXACT_SHA: ${{ github.event.pull_request.head.sha || github.sha }}",
            workflow,
        )
        self.assertIn(
            "github.event_name == 'workflow_dispatch' && inputs.visual_tier == 'candidate'",
            workflow,
        )
        self.assertIn(
            "github.event_name == 'pull_request' && startsWith(github.head_ref, 'visual/search3-candidate-')",
            workflow,
        )

    def test_visual_tiers_keep_smoke_small_and_candidate_exhaustive(self):
        repository = Path(__file__).resolve().parents[1]
        fixture = json.loads(
            (repository / "tests/fixtures/search3-candidate-scaffold.json").read_text(encoding="utf-8")
        )
        self.assertEqual(fixture["schemaVersion"], 2)
        canonical = [375, 430, 768, 1024, 1440]
        expected_widths = {"smoke": [375, 1440], "candidate": canonical}
        expected_capture_counts = {"smoke": 12, "candidate": 21}
        for name, tier in fixture["visualTiers"].items():
            assigned = tier["lifecycleWidths"] + tier["finalOnlyWidths"]
            self.assertEqual(sorted(assigned), expected_widths[name], name)
            self.assertEqual(len(assigned), len(set(assigned)), name)
            screenshots = len(tier["lifecycleWidths"]) * 3 + len(tier["finalOnlyWidths"])
            screenshots += len(tier["presentationCaptures"])
            self.assertEqual(screenshots, expected_capture_counts[name], name)
            exhaustive = name == "candidate"
            self.assertEqual(tier["runResponsiveBoundaries"], exhaustive, name)
            self.assertEqual(tier["runRaces"], exhaustive, name)
            self.assertEqual(tier["runFailureStates"], exhaustive, name)

    def test_renders_verified_review_indexes(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            capture = root / "375-final-100.png"
            capture.write_bytes(b"deterministic-png-fixture")
            digest = hashlib.sha256(capture.read_bytes()).hexdigest()
            manifest = root / "manifest.json"
            manifest.write_text(json.dumps({
                "schemaVersion": 2,
                "visualTier": "smoke",
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
            self.assertIn("tier=smoke", html_path.read_text(encoding="utf-8"))
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
