"""Offline MATCH dossier join. No supplier requests, DB access or acceptance authority."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import unicodedata
import zipfile
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

PINS = {
    "frontier": {
        "artifact_id": 10447056731,
        "zip_sha256": "53d12863ecf8cfbd78cf702d9d8f5aab9dacfd4ce14af87c41ae724b42e0d58f",
        "result_sha256": "334a4e213f4508e60d6d292f58f9b377046dd1525c79e51e0b41f3ce2b29c819",
        "operation_id": "hotel-match-unseen-native-frontier-1971-20260916-v1",
        "source_sha": "666d0c0f3346b837d57fe42c2b5dbb08fdfd53fa",
    },
    "retained": {
        "artifact_id": 10400444968,
        "zip_sha256": "f3886404254071150160683d93978497562620b4a5cdc75aa6cf16c13f415951",
        "result_sha256": "e5cb4eeed062882779c37059e19f12b4e3e92badb45a6069b86d228d8d5534d7",
        "operation_id": "hotel-match-saved-andromeda-evidence-1971-20260915-v1",
        "source_sha": "6e2e9f06ee0d82390a76c112a53334b5d3aa6200",
    },
}


def require(condition: bool, reason: str) -> None:
    if not condition:
        raise ValueError(reason)


def digest(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def unique_object(pairs: list[tuple[str, Any]]) -> dict:
    out: dict = {}
    for key, value in pairs:
        require(key not in out, "duplicate_json_key")
        out[key] = value
    return out


def decode(raw: bytes) -> dict:
    result = json.loads(raw, object_pairs_hook=unique_object)
    require(isinstance(result, dict), "json_object_required")
    return result


def read_pinned(path: Path, kind: str) -> dict:
    """Read fixed members in place; never extract an archive or execute its source."""
    pin = PINS[kind]
    require(path.stat().st_size <= 16_000_000, "archive_size")
    require(digest(path.read_bytes()) == pin["zip_sha256"], "archive_digest")
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        require(len(names) == len(set(names)) and len(names) <= 40, "archive_members")
        require(all(x.file_size <= 16_000_000 for x in archive.infolist()), "member_size")
        raw = archive.read("server/result.json")
        result = decode(raw)
        receipt = decode(archive.read("server/receipt.json"))
        server = decode(archive.read("server/reservation.json"))
        runner_name = "runner-reservation.json" if kind == "frontier" else "reservation.json"
        runner = decode(archive.read(runner_name))
        require(digest(raw) == pin["result_sha256"] == receipt.get("result_sha256"), "result_digest")
        for document in (result, receipt, server, runner):
            require(document.get("operation_id") == pin["operation_id"], "operation_binding")
            require(document.get("source_sha") == pin["source_sha"], "source_binding")
            require(document.get("no_replay") is True, "no_replay_binding")
        require(result.get("state") == receipt.get("state") == "completed_read_only", "terminal_state")
        require(receipt.get("readback_verified") is True, "receipt_unverified")
        require(result.get("safe_to_write_now") is False, "not_an_apply_input")
        for field in ("database_writes", "mapping_writes", "supplier_calls", "tourvisor_calls", "booking_calls"):
            require(type(result.get(field)) is int and result[field] == 0, "non_read_only_input")
    return result


def hotel_id(value: Any) -> str:
    require(isinstance(value, str) and bool(re.fullmatch(r"[1-9][0-9]{0,19}", value)), "invalid_typed_id")
    return value


def sha256(value: Any) -> str:
    require(isinstance(value, str) and bool(re.fullmatch(r"[a-f0-9]{64}", value)), "invalid_evidence_digest")
    return value


def index_rows(rows: list[dict], key: str) -> dict[str, dict]:
    out = {}
    for row in rows:
        identifier = hotel_id(row[key])
        require(row.get("supplier_namespace") == "andromeda_catalog", "namespace_mismatch")
        require(identifier not in out, "duplicate_identity")
        sha256(row.get("evidence_sha256"))
        out[identifier] = row
    return out


def primary_key(name: str) -> str:
    """Divergence alarm only, not an identity key or an automatically accepted alias."""
    name = unicodedata.normalize("NFKC", name).casefold()
    primary = re.split(r"\b(?:ex|former|formerly)\b\.?", name, maxsplit=1)[0]
    generic = {"hotel", "hotels", "resort", "resorts", "spa"}
    return " ".join(t for t in re.findall(r"[^\W_]+", primary) if t not in generic)


def join(frontier: dict, retained: dict) -> dict:
    current = index_rows(frontier["queue"], "andromeda_hotel_id")
    prior = index_rows(retained["current_pending"], "external_hotel_id")
    require(frontier["queue_count"] == len(current), "frontier_count")
    excluded = {hotel_id(row["andromeda_hotel_id"]) for row in frontier["holds"]}
    require(not excluded.intersection(current), "excluded_reintroduced")
    evidence: dict[str, list[dict]] = defaultdict(list)
    outside = set()
    for row in retained["evidence_rows"]:
        identifier = hotel_id(row["external_hotel_id"])
        require(row.get("safe_to_write_now") is False, "retained_acceptance_flag")
        require(row["source_kind"] in {"saved_HOTELS_TOWNTO", "retained_normalized_PRICE"}, "evidence_kind")
        sha256(row["retained_file_sha256"])
        # A retained normalized document cannot retroactively supply a raw HTTP digest.
        require(row.get("original_http_response_sha256") is None, "unexpected_raw_http_provenance")
        if identifier in current:
            evidence[identifier].append(row)
        else:
            outside.add(identifier)
    dossiers = []
    stats: Counter = Counter()
    countries: Counter = Counter()
    for identifier, row in current.items():
        attached = evidence.get(identifier, [])
        old = prior.get(identifier)
        binding = None if old is None else old["evidence_sha256"] == row["evidence_sha256"]
        flags = set()
        if attached and binding is not True:
            flags.add("retained_source_digest_changed_or_missing")
        documents = set()
        titles = set()
        for item in attached:
            flags.update(item.get("holds", []))
            if item["source_kind"] == "saved_HOTELS_TOWNTO":
                if item.get("country_id") != row["country_id"]:
                    flags.add("retained_country_conflict")
                state = item.get("hotel_fields", {}).get("stateKey")
            else:
                state = item.get("supplier_state_key")
                name = item.get("hotel_fields", {}).get("hotel")
                if isinstance(name, str) and name.strip():
                    titles.add(name)
                documents.add(item["retained_file_sha256"])
                for other in item.get("other_retained_file_sha256", []):
                    documents.add(sha256(other))
            if state is not None and str(state) != str(row["state_key"]):
                flags.add("retained_state_conflict")
            for link in item.get("typed_town_links", {}).values():
                if link.get("namespace") != "andromeda_town" or link.get("country_id") != row["country_id"]:
                    flags.add("typed_geography_context_conflict")
                if link.get("conflict") is True:
                    flags.add("retained_town_dictionary_conflict")
        primary_names = {primary_key(name) for name in titles} - {""}
        if len(primary_names) > 1:
            flags.add("multiple_retained_search_primary_names")
        urls = sum(len(item.get("urls", {})) for item in attached)
        dossier = {
            "supplier_namespace": "andromeda_catalog", "external_hotel_id": identifier,
            "frontier": row, "retained_source": old,
            "retained_source_digest_matches_frontier": binding,
            "retained_evidence": attached,
            "retained_search_observed": bool(documents),
            "retained_search_documents_lower_bound": len(documents) if documents else None,
            "retained_search_document_sha256": sorted(documents),
            "retained_search_names": sorted(titles),
            "evidence_flags": sorted(flags),
            "route": "retained_evidence_attached" if attached else "not_covered_by_this_retained_artifact",
            "safe_to_write_now": False,
        }
        dossiers.append(dossier)
        stats["queue_rows"] += 1
        stats["with_retained_metadata"] += bool(attached)
        stats["unchanged_source_digest"] += bool(attached) and binding is True
        stats["retained_evidence_rows"] += len(attached)
        stats["with_retained_search"] += bool(documents)
        stats["retained_search_document_identity_pairs_lower_bound"] += len(documents)
        stats["retained_url_records"] += urls
        stats["flagged_identities"] += bool(flags)
        stats["current_frequency_unknown"] += row.get("frequency") is None
        if attached:
            countries[row["country_name"]] += 1
    # Priority means historical observation exists, not a fabricated current-demand count.
    dossiers.sort(key=lambda x: (
        not x["retained_search_observed"],
        -(x["retained_search_documents_lower_bound"] or 0), x["external_hotel_id"]))
    stats["without_retained_metadata"] = len(current) - stats["with_retained_metadata"]
    return {
        "schema": "frontier-retained-evidence-join/1", "state": "prepared_evidence_only",
        "input_pins": PINS,
        "frontier_read_at_utc": frontier["read_at_utc"], "retained_read_at_utc": retained["read_at_utc"],
        "summary": dict(sorted(stats.items())), "retained_metadata_by_country": dict(sorted(countries.items())),
        "retired_retained_ids_not_reintroduced": sorted(outside),
        "frontier_holds_preserved": frontier["holds"],
        "limitations": [
            "Not a new CURRENT DB read or an apply manifest.",
            "No match is accepted; all manual, exclusion, country, coordinate and occupancy guards still apply.",
            "Missing retained evidence is not evidence of absent demand or exhausted saved sources.",
            "Document-hash lower bounds are historical observations, not current search frequency or offer counts.",
            "Stored URLs, numeric path segments and supplier star keys do not establish native identity or rating.",
            "Divergent primary names require independent evidence; they are never merged into aliases here.",
        ],
        "database_writes": 0, "mapping_writes": 0, "supplier_calls": 0, "tourvisor_calls": 0,
        "safe_to_write_now": False, "dossiers": dossiers,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("frontier", type=Path)
    parser.add_argument("retained", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    result = join(read_pinned(args.frontier, "frontier"), read_pinned(args.retained, "retained"))
    raw = (json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode("utf-8")
    with args.output.open("xb") as stream:
        stream.write(raw)
    require(args.output.read_bytes() == raw, "output_readback")
    print(json.dumps({"summary": result["summary"], "sha256": digest(raw)}, sort_keys=True))


if __name__ == "__main__":
    main()
