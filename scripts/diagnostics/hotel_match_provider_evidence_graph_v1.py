#!/usr/bin/env python3
"""Compile immutable provider hotel identity evidence into a deterministic graph.

This module is intentionally offline and fail-closed. It does not query providers,
read application DB state, or write mappings. It accepts normalized evidence records
and reports only graph connectivity plus conservative incremental classifications.

Normalized JSONL record shape:
{
  "source": {"namespace": "operator_315", "kind": "hotel", "id": "30752"},
  "target": {"namespace": "tourvisor", "kind": "hotel", "id": "1221"},
  "evidence_type": "operator_link_native",
  "authority": "direct",
  "polarity": "support",
  "provenance": {"operation_id": "...", "sha256": "..."},
  "attributes": {"country_id": 4}
}

Authority is explicit and never inferred from IDs:
observation < corroboration < direct < accepted. ``protected`` is a veto class.
Only support paths composed entirely of direct/accepted edges can produce a
``safe_candidate``. Observation/corroboration edges can connect the graph and carry
evidence, but never authorize a mapping.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
from collections import defaultdict, deque
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable, Iterator, Mapping, Sequence

SCHEMA_VERSION = "provider-evidence-graph-v1"
AUTHORITIES = {"observation", "corroboration", "direct", "accepted", "protected"}
POLARITIES = {"support", "conflict", "protect"}
AUTHORITATIVE = {"direct", "accepted"}


class EvidenceError(ValueError):
    """Raised when normalized evidence violates the graph contract."""


def _clean_atom(value: Any, field: str) -> str:
    text = str(value).strip()
    if not text or len(text) > 240 or "\x00" in text or ":" in text:
        raise EvidenceError(f"invalid {field}")
    return text


def node_key(node: Mapping[str, Any]) -> str:
    namespace = _clean_atom(node.get("namespace", ""), "namespace")
    kind = _clean_atom(node.get("kind", ""), "kind")
    value = _clean_atom(node.get("id", ""), "id")
    return f"{namespace}:{kind}:{value}"


def _canonical(value: Any) -> Any:
    if isinstance(value, dict):
        return {str(k): _canonical(value[k]) for k in sorted(value)}
    if isinstance(value, list):
        return [_canonical(v) for v in value]
    return value


def _digest_payload(payload: Mapping[str, Any]) -> str:
    raw = json.dumps(_canonical(dict(payload)), ensure_ascii=False, separators=(",", ":"))
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


@dataclass(frozen=True)
class EvidenceEdge:
    edge_id: str
    source: str
    target: str
    evidence_type: str
    authority: str
    polarity: str
    provenance: Mapping[str, Any]
    attributes: Mapping[str, Any]

    def as_dict(self) -> dict[str, Any]:
        return {
            "edge_id": self.edge_id,
            "source": self.source,
            "target": self.target,
            "evidence_type": self.evidence_type,
            "authority": self.authority,
            "polarity": self.polarity,
            "provenance": _canonical(dict(self.provenance)),
            "attributes": _canonical(dict(self.attributes)),
        }


def edge_from_record(record: Mapping[str, Any]) -> EvidenceEdge:
    if not isinstance(record, Mapping):
        raise EvidenceError("record must be an object")
    source = node_key(record.get("source") or {})
    target = node_key(record.get("target") or {})
    if source == target:
        raise EvidenceError("self-edge is not valid identity evidence")
    evidence_type = _clean_atom(record.get("evidence_type", ""), "evidence_type")
    authority = _clean_atom(record.get("authority", ""), "authority")
    polarity = _clean_atom(record.get("polarity", "support"), "polarity")
    if authority not in AUTHORITIES:
        raise EvidenceError(f"unsupported authority: {authority}")
    if polarity not in POLARITIES:
        raise EvidenceError(f"unsupported polarity: {polarity}")
    if authority == "protected" and polarity == "support":
        raise EvidenceError("protected authority cannot be a support edge")
    provenance = record.get("provenance") or {}
    attributes = record.get("attributes") or {}
    if not isinstance(provenance, Mapping) or not isinstance(attributes, Mapping):
        raise EvidenceError("provenance/attributes must be objects")
    payload = {
        "source": source,
        "target": target,
        "evidence_type": evidence_type,
        "authority": authority,
        "polarity": polarity,
        "provenance": _canonical(dict(provenance)),
        "attributes": _canonical(dict(attributes)),
    }
    supplied = record.get("edge_id")
    edge_id = _clean_atom(supplied, "edge_id") if supplied is not None else _digest_payload(payload)
    return EvidenceEdge(
        edge_id=edge_id,
        source=source,
        target=target,
        evidence_type=evidence_type,
        authority=authority,
        polarity=polarity,
        provenance=_canonical(dict(provenance)),
        attributes=_canonical(dict(attributes)),
    )


class EvidenceGraph:
    def __init__(self, edges: Iterable[EvidenceEdge] = ()) -> None:
        self.edges: dict[str, EvidenceEdge] = {}
        self.adjacency: dict[str, set[str]] = defaultdict(set)
        for edge in edges:
            self.add(edge)

    def add(self, edge: EvidenceEdge) -> bool:
        existing = self.edges.get(edge.edge_id)
        if existing is not None:
            if existing != edge:
                raise EvidenceError(f"edge_id collision: {edge.edge_id}")
            return False
        self.edges[edge.edge_id] = edge
        self.adjacency[edge.source].add(edge.edge_id)
        self.adjacency[edge.target].add(edge.edge_id)
        return True

    @property
    def nodes(self) -> set[str]:
        return set(self.adjacency)

    def component(self, seed: str) -> set[str]:
        if seed not in self.adjacency:
            return {seed}
        seen = {seed}
        queue = deque([seed])
        while queue:
            node = queue.popleft()
            for edge_id in self.adjacency.get(node, ()):
                edge = self.edges[edge_id]
                other = edge.target if edge.source == node else edge.source
                if other not in seen:
                    seen.add(other)
                    queue.append(other)
        return seen

    def component_edges(self, component: set[str]) -> list[EvidenceEdge]:
        edge_ids: set[str] = set()
        for node in component:
            edge_ids.update(self.adjacency.get(node, ()))
        return sorted((self.edges[eid] for eid in edge_ids), key=lambda edge: edge.edge_id)

    def affected_components(self, changed_edge_ids: Iterable[str]) -> list[dict[str, Any]]:
        seeds: set[str] = set()
        for edge_id in changed_edge_ids:
            edge = self.edges.get(edge_id)
            if edge is None:
                raise EvidenceError(f"unknown changed edge: {edge_id}")
            seeds.update((edge.source, edge.target))
        components: list[set[str]] = []
        consumed: set[str] = set()
        for seed in sorted(seeds):
            if seed in consumed:
                continue
            comp = self.component(seed)
            components.append(comp)
            consumed.update(comp)
        return [
            {
                "nodes": sorted(comp),
                "edge_ids": [edge.edge_id for edge in self.component_edges(comp)],
            }
            for comp in components
        ]

    def _authoritative_path(self, source: str, target: str) -> list[str] | None:
        if source == target:
            return []
        queue = deque([(source, [])])
        seen = {source}
        while queue:
            node, path = queue.popleft()
            for edge_id in sorted(self.adjacency.get(node, ())):
                edge = self.edges[edge_id]
                if edge.polarity != "support" or edge.authority not in AUTHORITATIVE:
                    continue
                other = edge.target if edge.source == node else edge.source
                next_path = path + [edge_id]
                if other == target:
                    return next_path
                if other not in seen:
                    seen.add(other)
                    queue.append((other, next_path))
        return None

    def _pair_veto(self, provider: str, target: str, component: set[str]) -> list[str]:
        reasons: list[str] = []
        for edge in self.component_edges(component):
            touches_pair = edge.source in {provider, target} or edge.target in {provider, target}
            if not touches_pair:
                continue
            if edge.polarity == "protect" or edge.authority == "protected":
                reasons.append(f"protected:{edge.edge_id}")
            elif edge.polarity == "conflict":
                reasons.append(f"conflict:{edge.edge_id}")
        return sorted(set(reasons))

    def resolve_provider_node(self, provider_node: str) -> dict[str, Any]:
        component = self.component(provider_node)
        local_nodes = sorted(node for node in component if node.startswith("anytour:hotel:"))
        if provider_node not in self.adjacency:
            return {
                "node": provider_node,
                "classification": "unknown",
                "targets": [],
                "reason": "node_not_observed",
            }

        accepted_direct: list[str] = []
        qualified: list[dict[str, Any]] = []
        for target in local_nodes:
            path = self._authoritative_path(provider_node, target)
            if path is None:
                continue
            veto = self._pair_veto(provider_node, target, component)
            path_edges = [self.edges[eid] for eid in path]
            if len(path) == 1 and path_edges[0].authority == "accepted":
                accepted_direct.append(target)
            qualified.append({"target": target, "path": path, "veto": veto})

        protected = [item for item in qualified if item["veto"]]
        clean = [item for item in qualified if not item["veto"]]

        if len(accepted_direct) == 1:
            accepted_item = next(item for item in qualified if item["target"] == accepted_direct[0])
            competing_clean = [item for item in clean if item["target"] != accepted_direct[0]]
            if not accepted_item["veto"] and competing_clean:
                return {
                    "node": provider_node,
                    "classification": "conflict",
                    "targets": [accepted_item, *competing_clean],
                    "component_size": len(component),
                    "reason": "authoritative_evidence_disagrees_with_accepted",
                }
            if not accepted_item["veto"]:
                return {
                    "node": provider_node,
                    "classification": "accepted",
                    "targets": [accepted_item],
                    "component_size": len(component),
                }

        if protected and not clean:
            return {
                "node": provider_node,
                "classification": "hold",
                "targets": protected,
                "component_size": len(component),
                "reason": "protected_or_conflicting_evidence",
            }
        if len(clean) == 1:
            return {
                "node": provider_node,
                "classification": "safe_candidate",
                "targets": clean,
                "component_size": len(component),
                "reason": "unique_direct_or_accepted_path",
            }
        if len(clean) > 1:
            return {
                "node": provider_node,
                "classification": "ambiguous",
                "targets": clean,
                "component_size": len(component),
                "reason": "multiple_authoritative_targets",
            }

        evidence_edges = self.component_edges(component)
        classification = "needs_evidence" if evidence_edges else "unknown"
        return {
            "node": provider_node,
            "classification": classification,
            "targets": protected,
            "component_size": len(component),
            "reason": "no_authoritative_path",
        }

    def as_dict(
        self,
        resolve_nodes: Sequence[str] = (),
        changed_edge_ids: Sequence[str] = (),
    ) -> dict[str, Any]:
        return {
            "schema": SCHEMA_VERSION,
            "node_count": len(self.nodes),
            "edge_count": len(self.edges),
            "nodes": sorted(self.nodes),
            "edges": [self.edges[eid].as_dict() for eid in sorted(self.edges)],
            "resolutions": [self.resolve_provider_node(node) for node in resolve_nodes],
            "affected_components": self.affected_components(changed_edge_ids) if changed_edge_ids else [],
        }


def load_jsonl(paths: Sequence[Path]) -> Iterator[EvidenceEdge]:
    for path in paths:
        with path.open("r", encoding="utf-8") as handle:
            for line_no, line in enumerate(handle, 1):
                stripped = line.strip()
                if not stripped or stripped.startswith("#"):
                    continue
                try:
                    record = json.loads(stripped)
                    yield edge_from_record(record)
                except (json.JSONDecodeError, EvidenceError) as exc:
                    raise EvidenceError(f"{path}:{line_no}: {exc}") from exc


PUBLIC_GEO_COLUMNS = {
    "namespace",
    "external_id",
    "andromeda_id",
    "source_name",
    "official_name",
    "official_geo",
    "top_local_id",
    "top_local_name",
    "geo_relation",
    "evidence_sha256",
    "official_url",
}


def load_public_geo_tsv(path: Path) -> Iterator[EvidenceEdge]:
    """Adapt the retained MATCH public/brand geo packet into graph edges.

    This adapter preserves the packet's original authority boundary:
    `match` is corroboration only, `conflict` is a veto, and `insufficient`
    remains a non-authoritative observation. No provider native-ID equivalence
    is inferred from the public page.
    """
    raw_lines = path.read_text(encoding="utf-8").splitlines()
    header_index = next(
        (i for i, line in enumerate(raw_lines) if line.startswith("namespace\texternal_id\t")),
        None,
    )
    if header_index is None:
        raise EvidenceError(f"{path}: missing public-geo TSV header")
    metadata: dict[str, str] = {}
    for line in raw_lines[:header_index]:
        for token in line.split():
            if "=" in token:
                key, value = token.split("=", 1)
                if key and value:
                    metadata[key] = value
    reader = csv.DictReader(raw_lines[header_index:], delimiter="\t")
    if reader.fieldnames is None or not PUBLIC_GEO_COLUMNS.issubset(set(reader.fieldnames)):
        raise EvidenceError(f"{path}: unexpected public-geo TSV columns")
    for row_no, row in enumerate(reader, header_index + 2):
        relation = (row.get("geo_relation") or "").strip().lower()
        if relation not in {"match", "conflict", "insufficient"}:
            raise EvidenceError(f"{path}:{row_no}: unsupported geo_relation {relation!r}")
        authority = "corroboration" if relation in {"match", "conflict"} else "observation"
        polarity = "conflict" if relation == "conflict" else "support"
        record = {
            "source": {
                "namespace": row["namespace"],
                "kind": "hotel",
                "id": row["external_id"],
            },
            "target": {
                "namespace": "anytour",
                "kind": "hotel",
                "id": row["top_local_id"],
            },
            "evidence_type": f"official_geo_{relation}",
            "authority": authority,
            "polarity": polarity,
            "provenance": {
                "source_file": path.name,
                "input_run": metadata.get("input_run"),
                "input_result_sha256": metadata.get("input_result_sha256"),
                "evidence_sha256": row.get("evidence_sha256"),
                "official_url": row.get("official_url"),
            },
            "attributes": {
                "andromeda_id": row.get("andromeda_id"),
                "source_name": row.get("source_name"),
                "official_name": row.get("official_name"),
                "official_geo": row.get("official_geo"),
                "top_local_name": row.get("top_local_name"),
                "geo_relation": relation,
            },
        }
        try:
            yield edge_from_record(record)
        except EvidenceError as exc:
            raise EvidenceError(f"{path}:{row_no}: {exc}") from exc


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
        f"MATCH_PROVIDER_EVIDENCE_GRAPH_OK nodes={len(graph.nodes)} "
        f"edges={len(graph.edges)} resolved={len(args.resolve_node)}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
