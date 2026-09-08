#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("builder", ROOT / "scripts/diagnostics/anex_exact_registry_builder.py")
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class ExactRegistryBuilder(unittest.TestCase):
    def fixture(self, name="Distinctive Palace", candidate="DISTINCTIVE PALACE"):
        return {"schema_version": 1, "provider": "anex_xml", "generated_at": "2026-09-07T18:16:46.1+00:00",
                "reference_stamp": "0x0000000000000001", "matches": [{
                    "provider": "anex_xml", "external_id": 77, "name": name, "alternate_name": "",
                    "country": "Турция", "town": "Белек", "status": "verified_auto",
                    "catalog_hotel_id": 99, "candidates": [{"id": 99, "name": candidate,
                    "country": "Турция", "region": "Анталья", "town": "Белек", "score": 1.4}]}]}

    def evidence(self):
        return {"source_sha": "f" * 40, "run_url": "https://example/run",
                "artifact_url": "https://example/artifact", "artifact_digest": "sha256:" + "a" * 64}

    def test_distinctive_exact_identity_is_emitted(self):
        manifest, data = builder.build_registry(self.fixture(), self.evidence())
        self.assertEqual(manifest["mapping_count"], 1)
        self.assertEqual(manifest["deferred_short_name_count"], 0)
        self.assertEqual(data, b"anex_xml_id,catalog_hotel_id\n77,99\n")

    def test_short_single_word_identity_is_deferred(self):
        manifest, data = builder.build_registry(self.fixture("Nika", "NIKA HOTEL"), self.evidence())
        self.assertEqual(manifest["mapping_count"], 0)
        self.assertEqual(manifest["deferred_short_name_count"], 1)
        self.assertEqual(data, b"anex_xml_id,catalog_hotel_id\n")

    def test_geography_or_selected_candidate_mismatch_fails_closed(self):
        report = self.fixture()
        report["matches"][0]["candidates"][0]["country"] = "Египет"
        with self.assertRaisesRegex(ValueError, "strict invariant failed"):
            builder.build_registry(report, self.evidence())


if __name__ == "__main__":
    unittest.main()
