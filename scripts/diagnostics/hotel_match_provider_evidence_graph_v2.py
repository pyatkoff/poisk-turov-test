#!/usr/bin/env python3
"""Provider evidence graph v2: current Tourvisor target-space + future AnyTour canonical preference.

v2 is an immutable successor to graph v1. It reuses v1 parsing, evidence authority,
public-geo adapters, veto logic and connected-component mechanics. The only semantic
change is target selection for provider resolution:

1. If a component contains a proven ``anytour:hotel:*`` node, resolve against those
   canonical nodes.
2. Otherwise resolve against ``tourvisor:hotel:*`` nodes, because the historical
   AnyTour MATCH catalog and accepted provider mappings are Tourvisor-ID backed.

No numeric equality creates a canonical edge. An AnyTour canonical node is considered
only when an actual graph edge places it in the connected component.
"""

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
from pathlib import Path
from typing import Any, Sequence

V1_PATH = Path(__file__).with_name("hotel_match_provider_evidence_graph_v1.py")
_spec = importlib.util.spec_from_file_location("hotel_match_provider_evidence_graph_v1", V1_PATH)
if _spec is None or _spec.loader is None:
    raise RuntimeError("graph_v1_import")
v1 = importlib.util.module_from_spec(_spec)
sys.modules[_spec.name] = v1
_spec.loader.exec_module(v1)

SCHEMA_VERSION = "provider-evidence-graph-v2"
EvidenceError = v1.EvidenceError
EvidenceEdge = v1.EvidenceEdge
edge_from_record = v1.edge_from_record
load_jsonl = v1.load_jsonl
load_public_geo_tsv = v1.load_public_geo_tsv


class EvidenceGraph(v1.EvidenceGraph):
    """v1 graph mechanics with explicit current/future resolution target spaces."""

    @staticmethod
    def _resolution_targets(component: set[str]) -> tuple[str, list[str]]:
        anytour = sorted(node for node in component if node.startswith("anytour:hotel:"))
        if anytour:
            return "anytour", anytour
        tourvisor = sorted(node for node in component if node.startswith("tourvisor:hotel:"))
        return "tourvisor", tourvisor

    def resolve_provider_node(self, provider_node: str) -> dict[str, Any]:
        component = self.component(provider_node)
        target_space, targets = self._resolution_targets(component)
        if provider_node not in self.adjacency:
            return {
                "node": provider_node,
                "classification": "unknown",
                "target_space": target_space,
                "targets": [],
                "reason": "node_not_observed",
            }

        qualified: list[dict[str, Any]] = []
        accepted: list[dict[str, Any]] = []
        for target in targets:
            path = self._authoritative_path(provider_node, target)
            if path is None:
                continue
            veto = self._pair_veto(provider_node, target, component)
            path_edges = [self.edges[edge_id] for edge_id in path]
            item = {"target": target, "path": path, "veto": veto}
            qualified.append(item)
            # The first edge leaves the provider node. If that mapping is already
            # accepted, a later accepted canonical bridge must not downgrade it to
            # merely a candidate.
            if path_edges and path_edges[0].authority == "accepted":
                accepted.append(item)

        protected = [item for item in qualified if item["veto"]]
        clean = [item for item in qualified if not item["veto"]]
        accepted_clean = [item for item in accepted if not item["veto"]]

        if len(accepted_clean) == 1:
            accepted_target = accepted_clean[0]["target"]
            competing = [item for item in clean if item["target"] != accepted_target]
            if competing:
                return {
                    "node": provider_node,
                    "classification": "conflict",
                    "target_space": target_space,
                    "targets": [accepted_clean[0], *competing],
                    "component_size": len(component),
                    "reason": "authoritative_evidence_disagrees_with_accepted",
                }
            return {
                "node": provider_node,
                "classification": "accepted",
                "target_space": target_space,
                "targets": accepted_clean,
                "component_size": len(component),
                "reason": "accepted_provider_path",
            }

        if len(accepted_clean) > 1:
            return {
                "node": provider_node,
                "classification": "conflict",
                "target_space": target_space,
                "targets": accepted_clean,
                "component_size": len(component),
                "reason": "multiple_accepted_targets",
            }

        if protected and not clean:
            return {
                "node": provider_node,
                "classification": "hold",
                "target_space": target_space,
                "targets": protected,
                "component_size": len(component),
                "reason": "protected_or_conflicting_evidence",
            }
        if len(clean) == 1:
            return {
                "node": provider_node,
                "classification": "safe_candidate",
                "target_space": target_space,
                "targets": clean,
                "component_size": len(component),
                "reason": "unique_direct_or_accepted_path",
            }
        if len(clean) > 1:
            return {
                "node": provider_node,
                "classification": "ambiguous",
                "target_space": target_space,
                "targets": clean,
                "component_size": len(component),
                "reason": "multiple_authoritative_targets",
            }

        evidence_edges = self.component_edges(component)
        return {
            "node": provider_node,
            "classification": "needs_evidence" if evidence_edges else "unknown",
            "target_space": target_space,
            "targets": protected,
            "component_size": len(component),
            "reason": "no_authoritative_path",
        }

    def as_dict(
        self,
        resolve_nodes: Sequence[str] = (),
        changed_edge_ids: Sequence[str] = (),
    ) -> dict[str, Any]:
        payload = super().as_dict(resolve_nodes=(), changed_edge_ids=changed_edge_ids)
        payload["schema"] = SCHEMA_VERSION
        payload["resolutions"] = [self.resolve_provider_node(node) for node in resolve_nodes]
        return payload


def write_graph(path: Path, graph: EvidenceGraph, resolve_nodes: Sequence[str], changed: Sequence[str]) -> None:
    payload = graph.as_dict(resolve_nodes=resolve_nodes, changed_edge_ids=changed)
    encoded = json.dumps(payload, ensure_ascii=False, sort_keys=True, indent=2) + "\n"
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(encoded, encoding="utf-8")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--input", action="append", default=[], type=Path, help="normalized evidence JSONL")
    parser.add_argument("--public-geo-tsv", action="append", default=[], type=Path, help="retained MATCH public/brand geo TSV")
    parser.add_argument("--output", required=True, type=Path, help="deterministic graph JSON")
    parser.add_argument("--resolve-node", action="append", default=[], help="provider node key to classify")
    parser.add_argument("--changed-edge", action="append", default=[], help="edge_id whose component changed")
    return parser


def main(argv: Sequence[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    if not args.input and not args.public_geo_tsv:
        raise EvidenceError("at least one --input or --public-geo-tsv is required")
    edges = list(load_jsonl(args.input))
    for path in args.public_geo_tsv:
        edges.extend(load_public_geo_tsv(path))
    graph = EvidenceGraph(edges)
    write_graph(args.output, graph, args.resolve_node, args.changed_edge)
    print(
        f"MATCH_PROVIDER_EVIDENCE_GRAPH_V2_OK nodes={len(graph.nodes)} "
        f"edges={len(graph.edges)} resolved={len(args.resolve_node)}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
