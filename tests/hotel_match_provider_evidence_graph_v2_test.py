#!/usr/bin/env python3
import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "scripts" / "diagnostics" / "hotel_match_provider_evidence_graph_v2.py"
spec = importlib.util.spec_from_file_location("provider_evidence_graph_v2", SCRIPT)
mod = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = mod
assert spec.loader is not None
spec.loader.exec_module(mod)


def rec(src_ns, src_id, dst_ns, dst_id, authority, evidence_type="fixture", polarity="support"):
    return {
        "source": {"namespace": src_ns, "kind": "hotel", "id": str(src_id)},
        "target": {"namespace": dst_ns, "kind": "hotel", "id": str(dst_id)},
        "evidence_type": evidence_type,
        "authority": authority,
        "polarity": polarity,
        "provenance": {"fixture": True},
        "attributes": {},
    }


class ProviderEvidenceGraphV2Test(unittest.TestCase):
    def edge(self, *args, **kwargs):
        return mod.edge_from_record(rec(*args, **kwargs))

    def graph(self, *edges):
        return mod.EvidenceGraph(edges)

    def test_accepted_provider_to_current_tourvisor_target_is_accepted(self):
        edge = self.edge("operator_315", 30752, "tourvisor", 1221, "accepted", "accepted_mapping")
        result = self.graph(edge).resolve_provider_node("operator_315:hotel:30752")
        self.assertEqual("accepted", result["classification"])
        self.assertEqual("tourvisor", result["target_space"])
        self.assertEqual("tourvisor:hotel:1221", result["targets"][0]["target"])

    def test_direct_provider_to_current_tourvisor_target_is_safe_candidate(self):
        edge = self.edge("operator_315", 30752, "tourvisor", 1221, "direct", "native_query_id")
        result = self.graph(edge).resolve_provider_node("operator_315:hotel:30752")
        self.assertEqual("safe_candidate", result["classification"])
        self.assertEqual("tourvisor", result["target_space"])

    def test_passive_presence_to_tourvisor_never_authorizes(self):
        edge = mod.edge_from_record({
            "source": {"namespace": "tourvisor_operator_25", "kind": "presence", "id": "hotel_1221"},
            "target": {"namespace": "tourvisor", "kind": "hotel", "id": "1221"},
            "evidence_type": "passive_presence",
            "authority": "observation",
            "polarity": "support",
            "provenance": {"fixture": True},
            "attributes": {},
        })
        result = self.graph(edge).resolve_provider_node("tourvisor_operator_25:presence:hotel_1221")
        self.assertEqual("needs_evidence", result["classification"])
        self.assertEqual("tourvisor", result["target_space"])

    def test_numeric_equality_does_not_create_anytour_target(self):
        edge = self.edge("operator_315", 1221, "tourvisor", 1221, "direct", "native_query_id")
        result = self.graph(edge).resolve_provider_node("operator_315:hotel:1221")
        self.assertEqual("tourvisor", result["target_space"])
        self.assertEqual("tourvisor:hotel:1221", result["targets"][0]["target"])

    def test_future_authoritative_anytour_bridge_becomes_preferred_target(self):
        native = self.edge("operator_315", 30752, "tourvisor", 1221, "direct", "native_query_id")
        canonical = self.edge("tourvisor", 1221, "anytour", 900001, "accepted", "canonical_route")
        result = self.graph(native, canonical).resolve_provider_node("operator_315:hotel:30752")
        self.assertEqual("safe_candidate", result["classification"])
        self.assertEqual("anytour", result["target_space"])
        self.assertEqual("anytour:hotel:900001", result["targets"][0]["target"])

    def test_accepted_provider_mapping_stays_accepted_through_canonical_bridge(self):
        accepted = self.edge("operator_315", 30752, "tourvisor", 1221, "accepted", "accepted_mapping")
        canonical = self.edge("tourvisor", 1221, "anytour", 900001, "accepted", "canonical_route")
        result = self.graph(accepted, canonical).resolve_provider_node("operator_315:hotel:30752")
        self.assertEqual("accepted", result["classification"])
        self.assertEqual("anytour", result["target_space"])

    def test_corroboration_only_anytour_mention_does_not_override_tourvisor_space(self):
        accepted = self.edge("operator_315", 30752, "tourvisor", 1221, "accepted", "accepted_mapping")
        weak = self.edge("operator_315", 30752, "anytour", 999999, "corroboration", "weak_candidate")
        result = self.graph(accepted, weak).resolve_provider_node("operator_315:hotel:30752")
        self.assertEqual("accepted", result["classification"])
        self.assertEqual("tourvisor", result["target_space"])
        self.assertEqual("tourvisor:hotel:1221", result["targets"][0]["target"])

    def test_public_geo_top_local_is_tourvisor_not_anytour(self):
        with tempfile.TemporaryDirectory() as td:
            path = Path(td) / "public.tsv"
            path.write_text(
                "# MATCH public brand/geo evidence v2\n"
                "input_run=35243466737 input_result_sha256=abc\n"
                "namespace\texternal_id\tandromeda_id\tsource_name\tofficial_name\tofficial_geo\t"
                "top_local_id\ttop_local_name\tgeo_relation\tevidence_sha256\tofficial_url\n"
                "operator_315\t778871\t2000084135\tSide Sunberk Hotel\tSunberk Hotel & Resort\tAntalya / Side / Manavgat\t"
                "2263\tSUNBERK HOTEL\tmatch\tmsha\thttps://example.com\n",
                encoding="utf-8",
            )
            edges = list(mod.load_public_geo_tsv(path))
            self.assertEqual(1, len(edges))
            self.assertEqual("tourvisor:hotel:2263", edges[0].target)
            self.assertEqual("corroboration", edges[0].authority)
            result = self.graph(*edges).resolve_provider_node("operator_315:hotel:778871")
            self.assertEqual("needs_evidence", result["classification"])
            self.assertEqual("tourvisor", result["target_space"])

    def test_protection_still_vetoes_current_target(self):
        direct = self.edge("operator_5", 18819, "tourvisor", 56551, "direct", "native")
        protect = self.edge("operator_5", 18819, "tourvisor", 56551, "protected", "manual_hold", "protect")
        result = self.graph(direct, protect).resolve_provider_node("operator_5:hotel:18819")
        self.assertEqual("hold", result["classification"])


if __name__ == "__main__":
    unittest.main()
