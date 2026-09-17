#!/usr/bin/env python3
import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "scripts" / "diagnostics" / "hotel_match_provider_evidence_graph_v1.py"
spec = importlib.util.spec_from_file_location("provider_evidence_graph", SCRIPT)
mod = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = mod
assert spec.loader is not None
spec.loader.exec_module(mod)


def rec(
    source_ns,
    source_kind,
    source_id,
    target_ns,
    target_kind,
    target_id,
    evidence_type,
    authority,
    polarity="support",
    provenance=None,
    attributes=None,
):
    return {
        "source": {"namespace": source_ns, "kind": source_kind, "id": source_id},
        "target": {"namespace": target_ns, "kind": target_kind, "id": target_id},
        "evidence_type": evidence_type,
        "authority": authority,
        "polarity": polarity,
        "provenance": provenance or {"operation_id": "fixture"},
        "attributes": attributes or {},
    }


class ProviderEvidenceGraphTest(unittest.TestCase):
    def edge(self, *args, **kwargs):
        return mod.edge_from_record(rec(*args, **kwargs))

    def graph(self, *edges):
        return mod.EvidenceGraph(edges)

    def test_linkless_passive_observation_never_authorizes_mapping(self):
        passive = self.edge(
            "tourvisor_operator_25", "presence", "hotel_1221",
            "tourvisor", "hotel", "1221",
            "passive_search_observation", "observation",
        )
        local = self.edge(
            "tourvisor", "hotel", "1221",
            "anytour", "hotel", "1221",
            "accepted_tv_local_anchor", "accepted",
        )
        graph = self.graph(passive, local)
        result = graph.resolve_provider_node("tourvisor_operator_25:presence:hotel_1221")
        self.assertEqual("needs_evidence", result["classification"])
        self.assertEqual([], result["targets"])

    def test_direct_native_plus_accepted_anchor_is_safe_candidate(self):
        native = self.edge(
            "operator_315", "hotel", "30752",
            "tourvisor", "hotel", "1221",
            "tourvisor_hotels_parameter", "direct",
        )
        local = self.edge(
            "tourvisor", "hotel", "1221",
            "anytour", "hotel", "1221",
            "accepted_tv_local_anchor", "accepted",
        )
        result = self.graph(native, local).resolve_provider_node("operator_315:hotel:30752")
        self.assertEqual("safe_candidate", result["classification"])
        self.assertEqual("anytour:hotel:1221", result["targets"][0]["target"])
        self.assertEqual(2, len(result["targets"][0]["path"]))

    def test_direct_accepted_mapping_is_accepted(self):
        accepted = self.edge(
            "operator_315", "hotel", "30752",
            "anytour", "hotel", "1221",
            "accepted_mapping", "accepted",
        )
        result = self.graph(accepted).resolve_provider_node("operator_315:hotel:30752")
        self.assertEqual("accepted", result["classification"])

    def test_geo_conflict_vetoes_otherwise_authoritative_path(self):
        native = self.edge(
            "operator_315", "hotel", "999",
            "tourvisor", "hotel", "100",
            "tourvisor_hotels_parameter", "direct",
        )
        local = self.edge(
            "tourvisor", "hotel", "100",
            "anytour", "hotel", "100",
            "accepted_tv_local_anchor", "accepted",
        )
        conflict = self.edge(
            "operator_315", "hotel", "999",
            "anytour", "hotel", "100",
            "official_geo_conflict", "corroboration", "conflict",
        )
        result = self.graph(native, local, conflict).resolve_provider_node("operator_315:hotel:999")
        self.assertEqual("hold", result["classification"])
        self.assertIn("conflict:", result["targets"][0]["veto"][0])

    def test_geo_support_does_not_create_authority(self):
        geo = self.edge(
            "operator_342", "hotel", "17345",
            "anytour", "hotel", "21812",
            "official_geo_support", "corroboration",
        )
        result = self.graph(geo).resolve_provider_node("operator_342:hotel:17345")
        self.assertEqual("needs_evidence", result["classification"])

    def test_duplicate_authoritative_targets_are_ambiguous(self):
        edges = [
            self.edge(
                "operator_315", "hotel", "x",
                "tourvisor", "hotel", "1",
                "native_a", "direct",
            ),
            self.edge(
                "tourvisor", "hotel", "1",
                "anytour", "hotel", "10",
                "anchor_a", "accepted",
            ),
            self.edge(
                "operator_315", "hotel", "x",
                "tourvisor", "hotel", "2",
                "native_b", "direct",
            ),
            self.edge(
                "tourvisor", "hotel", "2",
                "anytour", "hotel", "20",
                "anchor_b", "accepted",
            ),
        ]
        result = self.graph(*edges).resolve_provider_node("operator_315:hotel:x")
        self.assertEqual("ambiguous", result["classification"])
        self.assertEqual(
            {"anytour:hotel:10", "anytour:hotel:20"},
            {item["target"] for item in result["targets"]},
        )

    def test_new_direct_evidence_cannot_hide_conflict_with_accepted_mapping(self):
        accepted = self.edge(
            "operator_315", "hotel", "x",
            "anytour", "hotel", "10",
            "accepted_mapping", "accepted",
        )
        native = self.edge(
            "operator_315", "hotel", "x",
            "tourvisor", "hotel", "2",
            "new_direct_native", "direct",
        )
        other = self.edge(
            "tourvisor", "hotel", "2",
            "anytour", "hotel", "20",
            "accepted_tv_anchor", "accepted",
        )
        result = self.graph(accepted, native, other).resolve_provider_node("operator_315:hotel:x")
        self.assertEqual("conflict", result["classification"])
        self.assertEqual(
            {"anytour:hotel:10", "anytour:hotel:20"},
            {item["target"] for item in result["targets"]},
        )

    def test_node_key_rejects_colon_ambiguity(self):
        with self.assertRaises(mod.EvidenceError):
            mod.node_key({"namespace": "operator:315", "kind": "hotel", "id": "1"})

    def test_manual_or_exclusion_protection_vetoes_candidate(self):
        native = self.edge(
            "operator_5", "hotel", "18819",
            "andromeda", "hotel", "2000086116",
            "native_bridge", "direct",
        )
        local = self.edge(
            "andromeda", "hotel", "2000086116",
            "anytour", "hotel", "56551",
            "accepted_andromeda_mapping", "accepted",
        )
        protected = self.edge(
            "operator_5", "hotel", "18819",
            "anytour", "hotel", "56551",
            "manual_exclusion", "protected", "protect",
        )
        result = self.graph(native, local, protected).resolve_provider_node("operator_5:hotel:18819")
        self.assertEqual("hold", result["classification"])

    def test_incremental_recompute_returns_only_changed_component(self):
        a = self.edge(
            "operator_315", "hotel", "1",
            "tourvisor", "hotel", "101",
            "native", "direct",
        )
        b = self.edge(
            "tourvisor", "hotel", "101",
            "anytour", "hotel", "501",
            "anchor", "accepted",
        )
        c = self.edge(
            "operator_342", "hotel", "2",
            "tourvisor", "hotel", "202",
            "native", "direct",
        )
        d = self.edge(
            "tourvisor", "hotel", "202",
            "anytour", "hotel", "502",
            "anchor", "accepted",
        )
        graph = self.graph(a, b, c, d)
        affected = graph.affected_components([a.edge_id])
        self.assertEqual(1, len(affected))
        self.assertEqual(
            {"operator_315:hotel:1", "tourvisor:hotel:101", "anytour:hotel:501"},
            set(affected[0]["nodes"]),
        )
        self.assertNotIn("operator_342:hotel:2", affected[0]["nodes"])

    def test_duplicate_identical_edge_is_idempotent(self):
        edge = self.edge(
            "operator_315", "hotel", "1",
            "tourvisor", "hotel", "2",
            "native", "direct",
        )
        graph = mod.EvidenceGraph()
        self.assertTrue(graph.add(edge))
        self.assertFalse(graph.add(edge))
        self.assertEqual(1, len(graph.edges))

    def test_same_edge_id_with_different_payload_is_rejected(self):
        one = rec(
            "operator_315", "hotel", "1",
            "tourvisor", "hotel", "2",
            "native", "direct",
        )
        one["edge_id"] = "sealed-edge"
        two = dict(one)
        two["evidence_type"] = "different"
        graph = mod.EvidenceGraph([mod.edge_from_record(one)])
        with self.assertRaises(mod.EvidenceError):
            graph.add(mod.edge_from_record(two))

    def test_protected_support_edge_is_invalid(self):
        record = rec(
            "operator_315", "hotel", "1",
            "anytour", "hotel", "2",
            "manual", "protected",
        )
        with self.assertRaises(mod.EvidenceError):
            mod.edge_from_record(record)

    def test_public_geo_packet_adapter_preserves_authority_boundary(self):
        with tempfile.TemporaryDirectory() as td:
            path = Path(td) / "public.tsv"
            path.write_text(
                "# MATCH public brand/geo evidence v2\n"
                "input_run=35243466737 input_result_sha256=abc\n"
                "namespace\texternal_id\tandromeda_id\tsource_name\tofficial_name\tofficial_geo\t"
                "top_local_id\ttop_local_name\tgeo_relation\tevidence_sha256\tofficial_url\n"
                "operator_315\t29619\t9180\tTUI AQI Pegasos Club\tAQI Pegasos Club\tAntalya / Alanya\t"
                "1275\tAQI PEGASOS CLUB\tmatch\tmsha\thttps://example.com/match\n"
                "operator_315\t854061\t2000090661\tWoxx Hotel\tWoxx Hotel\tIstanbul\t"
                "99465\tWOX EW HOTEL\tconflict\tcsha\thttps://example.com/conflict\n"
                "operator_342\t17362\t218356\tDarkhill Hotel\tDarkhill Hotel\tIstanbul\t"
                "102245\tKERTHILL HOTEL\tinsufficient\tisha\thttps://example.com/insufficient\n",
                encoding="utf-8",
            )
            edges = list(mod.load_public_geo_tsv(path))
            self.assertEqual(3, len(edges))
            by_type = {edge.evidence_type: edge for edge in edges}
            self.assertEqual("corroboration", by_type["official_geo_match"].authority)
            self.assertEqual("support", by_type["official_geo_match"].polarity)
            self.assertEqual("corroboration", by_type["official_geo_conflict"].authority)
            self.assertEqual("conflict", by_type["official_geo_conflict"].polarity)
            self.assertEqual("observation", by_type["official_geo_insufficient"].authority)
            graph = mod.EvidenceGraph(edges)
            self.assertEqual(
                "needs_evidence",
                graph.resolve_provider_node("operator_315:hotel:29619")["classification"],
            )
            self.assertEqual(
                "needs_evidence",
                graph.resolve_provider_node("operator_315:hotel:854061")["classification"],
            )
            self.assertEqual("35243466737", by_type["official_geo_match"].provenance["input_run"])

    def test_cli_output_is_deterministic_and_keeps_provenance(self):
        records = [
            rec(
                "operator_315", "hotel", "30752",
                "tourvisor", "hotel", "1221",
                "tourvisor_hotels_parameter", "direct",
                provenance={"operation_id": "sealed-op", "sha256": "abc"},
                attributes={"country_id": 4},
            ),
            rec(
                "tourvisor", "hotel", "1221",
                "anytour", "hotel", "1221",
                "accepted_tv_local_anchor", "accepted",
            ),
        ]
        with tempfile.TemporaryDirectory() as td:
            base = Path(td)
            src = base / "evidence.jsonl"
            out1 = base / "one.json"
            out2 = base / "two.json"
            src.write_text("\n".join(json.dumps(x, sort_keys=True) for x in reversed(records)) + "\n", encoding="utf-8")
            rc1 = mod.main([
                "--input", str(src),
                "--output", str(out1),
                "--resolve-node", "operator_315:hotel:30752",
            ])
            rc2 = mod.main([
                "--input", str(src),
                "--output", str(out2),
                "--resolve-node", "operator_315:hotel:30752",
            ])
            self.assertEqual(0, rc1)
            self.assertEqual(out1.read_bytes(), out2.read_bytes())
            payload = json.loads(out1.read_text(encoding="utf-8"))
            self.assertEqual(mod.SCHEMA_VERSION, payload["schema"])
            self.assertEqual("safe_candidate", payload["resolutions"][0]["classification"])
            direct = next(e for e in payload["edges"] if e["evidence_type"] == "tourvisor_hotels_parameter")
            self.assertEqual("sealed-op", direct["provenance"]["operation_id"])
            self.assertEqual(4, direct["attributes"]["country_id"])


if __name__ == "__main__":
    unittest.main()
