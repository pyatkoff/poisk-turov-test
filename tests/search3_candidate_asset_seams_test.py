#!/usr/bin/env python3
"""Static ownership and load-order guards for Search3 candidate overlay seams."""

from __future__ import annotations

import hashlib
import importlib.util
import json
from pathlib import Path
import unittest


REPO = Path(__file__).resolve().parents[1]
ROUTE_ROOT = REPO / "v2/_preview/search3-candidate/poisk-turov"
BUILDER_PATH = REPO / "scripts/release/build_search3_candidate_preview.py"
FIXTURE_PATH = REPO / "tests/fixtures/search3-candidate-scaffold.json"

SPEC = importlib.util.spec_from_file_location("search3_candidate_builder", BUILDER_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("unable to load Search3 candidate artifact builder")
builder = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(builder)

CSS_LOAD_ORDER = (
    "search3-results-filters-v1.css",
    "search3-entry-v1.css",
    "search3-results-cards-v2.css",
    "search3-selected-flow-v2.css",
)
JS_LOAD_ORDER = (
    "search3-results-filters-v1.js",
    "search3-entry-v1.js",
    "search3-results-cards-v2.js",
    "search3-selected-flow-v2.js",
)
FROZEN_BASE_SHA256 = {
    "search3-results-filters-v1.css": "056ca312c4f5356e1d3b8cc95e90355a0420420501e6467ae014e917df39fe7b",
    "search3-results-filters-v1.js": "9c0f5cf1ac6d84c655ec3d50a3c8f4c6aa5eb0bb52695942b32e17315b2c7ac2",
}
PROTECTED_RUNTIME_MARKERS = (
    "/api-v2.php",
    "/lead-adapter-v2.php",
    "leadApi",
    "metrikaCounter",
)


class Search3CandidateAssetSeamsTest(unittest.TestCase):
    def test_fixture_and_builder_use_the_exact_overlay_allowlist(self) -> None:
        fixture = json.loads(FIXTURE_PATH.read_text(encoding="utf-8"))
        expected = CSS_LOAD_ORDER + JS_LOAD_ORDER
        self.assertEqual(tuple(fixture["presentation"]["assets"]), expected)
        self.assertEqual(builder.EXPECTED_PRESENTATION_FILES, set(expected))
        self.assertEqual(len(builder.SOURCE_TO_TARGET), 9)
        self.assertEqual(
            {Path(path).name for path in builder.SOURCE_TO_TARGET},
            {"index.php", *expected},
        )

    def test_index_declares_fixed_base_entry_results_selected_order(self) -> None:
        source = (ROUTE_ROOT / "index.php").read_text(encoding="utf-8")
        for load_order in (CSS_LOAD_ORDER, JS_LOAD_ORDER):
            positions = []
            for filename in load_order:
                self.assertEqual(source.count(filename), 1, filename)
                positions.append(source.index(filename))
            self.assertEqual(positions, sorted(positions), load_order)

    def test_compatibility_base_is_frozen_and_slots_are_nonempty(self) -> None:
        for filename, expected_sha256 in FROZEN_BASE_SHA256.items():
            data = (ROUTE_ROOT / filename).read_bytes()
            self.assertEqual(hashlib.sha256(data).hexdigest(), expected_sha256, filename)
        for filename in CSS_LOAD_ORDER[1:] + JS_LOAD_ORDER[1:]:
            self.assertGreater((ROUTE_ROOT / filename).stat().st_size, 0, filename)

    def test_overlay_slots_do_not_own_protected_runtime_configuration(self) -> None:
        for filename in CSS_LOAD_ORDER[1:] + JS_LOAD_ORDER[1:]:
            source = (ROUTE_ROOT / filename).read_text(encoding="utf-8")
            for marker in PROTECTED_RUNTIME_MARKERS:
                self.assertNotIn(marker, source, f"{filename}: {marker}")


if __name__ == "__main__":
    unittest.main(verbosity=2)
