"""Offline typed-town geography evidence, never a hotel matcher or live executor."""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
import re
import unicodedata
import zipfile
from collections import Counter, defaultdict
from pathlib import Path

PINS = {
    "dossier": {"artifact_id": 10447857357,
        "zip_sha256": "70b469858217c61132656c7c7f05fa7ec5eb3a9273814a58a2dff00a7a47ddbc",
        "member": "match-frontier-retained-20260916.json",
        "result_sha256": "d2eff538a7587406460bea41cdd39aff7f6e25cc2cd87ec14d37fa918e63490d"},
    "census": {"artifact_id": 10394524643,
        "zip_sha256": "6da39881ac362b1ad433bc6613baf6f97bdc6c396d89ffa49eec517e5edaa2fc",
        "member": "server/result.json",
        "result_sha256": "f135bb42d40b0f3134309b24f5bdffcca8f2e62fa9511d36dfa14c04d96ccd1b",
        "operation_id": "hotel-match-core8-residual-current-1971-20260915-v1",
        "source_sha": "3e7d975c12d146da276e76e45ea702898db9f92a"},
}


def require(ok: bool, reason: str) -> None:
    if not ok:
        raise ValueError(reason)


def canonical(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False) + "\n").encode()


def digest(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def decode(raw: bytes) -> dict:
    def unique(pairs):
        result = {}
        for key, value in pairs:
            require(key not in result, "duplicate_json_key")
            result[key] = value
        return result
    def invalid(_):
        raise ValueError("nonfinite_json")
    value = json.loads(raw, object_pairs_hook=unique, parse_constant=invalid)
    require(isinstance(value, dict), "json_object_required")
    return value


def read_input(path: Path, kind: str) -> dict:
    pin = PINS[kind]
    require(path.stat().st_size <= 16_000_000, "archive_size")
    require(digest(path.read_bytes()) == pin["zip_sha256"], "archive_digest")
    with zipfile.ZipFile(path) as archive:
        infos = archive.infolist()
        require(len(infos) <= 40 and len(infos) == len({x.filename for x in infos}), "archive_members")
        require(sum(x.file_size for x in infos) <= 64_000_000, "expanded_size")
        raw = archive.read(pin["member"])
        require(digest(raw) == pin["result_sha256"], "result_digest")
        result = decode(raw)
        if kind == "census":
            receipt = decode(archive.read("server/receipt.json"))
            reservation = decode(archive.read("server/reservation.json"))
            require(archive.read("reservation.json") == archive.read("server/reservation.json"), "reservation_bytes")
            for document in (result, receipt, reservation):
                require(document.get("operation_id") == pin["operation_id"], "operation_binding")
                require(document.get("source_sha") == pin["source_sha"], "source_binding")
                require(document.get("no_replay") is True, "no_replay_binding")
            require(receipt.get("result_sha256") == pin["result_sha256"], "receipt_digest")
            require(result.get("state") == receipt.get("state") == "completed_read_only", "terminal_state")
            require(receipt.get("readback_verified") is True, "readback_unverified")
            for key in ("db_writes", "mapping_writes", "supplier_calls", "tourvisor_calls"):
                require(type(result.get(key)) is int and result[key] == 0, "input_side_effects")
        else:
            require(result.get("schema") == "frontier-retained-evidence-join/1", "dossier_schema")
            require(result.get("state") == "prepared_evidence_only" and result.get("safe_to_write_now") is False, "dossier_state")
            require(result.get("input_pins", {}).get("frontier", {}).get("result_sha256") ==
                "334a4e213f4508e60d6d292f58f9b377046dd1525c79e51e0b41f3ce2b29c819", "frontier_binding")
    return result


def text_key(value: str) -> str:
    """Geographic text only: no transliteration, prefix deletion or hotel-name fuzzy."""
    value = unicodedata.normalize("NFKD", value.casefold().replace("ё", "е").replace("ı", "i"))
    value = "".join(x for x in value if not unicodedata.combining(x))
    return " ".join(re.findall(r"[^\W_]+", value))


def positive(value) -> bool:
    return type(value) is int and value > 0


def identifier(value) -> str:
    require(isinstance(value, str) and re.fullmatch(r"[1-9][0-9]{0,19}", value) is not None, "typed_identifier")
    return value


def links(dossier: dict) -> list[dict]:
    out = []
    frontier = dossier["frontier"]
    for row in dossier["retained_evidence"]:
        if row.get("source_kind") != "saved_HOTELS_TOWNTO":
            continue
        for field, link in row.get("typed_town_links", {}).items():
            fields = row.get("hotel_fields", {})
            linked_id = link.get("external_id")
            valid = (link.get("namespace") == "andromeda_town"
                and type(link.get("country_id")) is int and type(row.get("country_id")) is int
                and link.get("country_id") == row.get("country_id") == frontier["country_id"]
                and str(fields.get("stateKey")) == str(frontier["state_key"])
                and isinstance(linked_id, str) and re.fullmatch(r"[1-9][0-9]{0,19}", linked_id) is not None
                and field in {"townKey", "town_key", "town", "townId", "town_id", "townToKey", "townToId"}
                and str(fields.get(field)) == linked_id
                and row.get("external_hotel_id") == dossier["external_hotel_id"])
            dictionary = link.get("dictionary_fields")
            if isinstance(dictionary, dict):
                for country_field in ("stateName", "countryName"):
                    country_text = dictionary.get(country_field)
                    if country_text is not None and (not isinstance(country_text, str)
                            or text_key(country_text) != text_key(frontier["country_name"])):
                        valid = False
            out.append({"key": (frontier["country_id"], "andromeda_town", linked_id),
                "valid": valid, "conflict": link.get("conflict") is not False,
                "dictionary": link.get("dictionary_fields"), "source_field": field,
                "retained_file_sha256": row.get("retained_file_sha256")})
    return out


def enrich(dossiers: dict, census: dict) -> dict:
    rows = dossiers["dossiers"]
    require(len(rows) == dossiers["summary"]["queue_rows"], "queue_count")
    seen = set()
    for row in rows:
        key = identifier(row["external_hotel_id"])
        require(key not in seen, "duplicate_frontier_id")
        seen.add(key)
        require(row.get("supplier_namespace") == row["frontier"].get("supplier_namespace") == "andromeda_catalog", "namespace")
        require(row["frontier"].get("andromeda_hotel_id") == key, "frontier_identity")
        require(row.get("safe_to_write_now") is False and row["frontier"].get("safe_to_write_now") is False, "acceptance_flag")
        require(isinstance(row["frontier"].get("evidence_sha256"), str) and re.fullmatch(r"[a-f0-9]{64}", row["frontier"]["evidence_sha256"]) is not None, "source_digest")
        require(positive(row["frontier"].get("country_id")) and positive(row["frontier"].get("state_key")), "country_state")
    excluded = {identifier(r["andromeda_hotel_id"]) for r in dossiers["frontier_holds_preserved"]}
    require(not excluded.intersection(seen), "excluded_reintroduced")

    index = defaultdict(set)
    definitions = defaultdict(set)
    for hotel in census["local_hotels"].values():
        country = hotel["country_id"]
        require(positive(country), "local_country")
        for scope in ("region", "subregion"):
            scope_id, name = hotel.get(scope + "_id"), hotel.get(scope + "_name")
            if not positive(scope_id) or not isinstance(name, str) or not text_key(name):
                continue
            anchor = (country, scope, scope_id)
            parent = hotel.get("region_id") if scope == "subregion" else None
            definitions[anchor].add((text_key(name), parent))
            index[(country, text_key(name))].add(anchor)
    index_rows = [{"country_id": a[0], "scope": a[1], "scope_id": a[2],
                   "definitions": sorted(definitions[a], key=repr)} for a in sorted(definitions)]
    index_sha = digest(canonical(index_rows))
    invalid_local = {a for a, meanings in definitions.items() if len(meanings) != 1}
    old_rows = {}
    for values in census["routes"].values():
        for row in values:
            key = identifier(row["external_hotel_id"])
            require(key not in old_rows, "duplicate_census_id")
            old_rows[key] = row

    parsed = {row["external_hotel_id"]: links(row) for row in rows}
    dictionary_versions = defaultdict(set)
    for values in parsed.values():
        for link in values:
            if link["valid"] and isinstance(link["dictionary"], dict):
                dictionary_versions[link["key"]].add(digest(canonical(link["dictionary"])))
    conflicting = {key for key, versions in dictionary_versions.items() if len(versions) > 1}
    result_rows, summary, countries = [], Counter(), Counter()
    for original in rows:
        row = copy.deepcopy(original)
        key = row["external_hotel_id"]
        frontier, source_links = row["frontier"], parsed[key]
        holds = set(row["evidence_flags"])
        if source_links and row["retained_source_digest_matches_frontier"] is not True:
            holds.add("source_evidence_changed")
        candidates, proof = set(), []
        for link in source_links:
            if not link["valid"] or link["conflict"] or not isinstance(link["dictionary"], dict):
                holds.add("typed_dictionary_context_or_conflict")
                continue
            if link["key"] in conflicting:
                holds.add("dictionary_versions_conflict")
            for field in ("name", "lName"):
                text = link["dictionary"].get(field)
                if not isinstance(text, str) or not text_key(text):
                    continue
                matches = index[(frontier["country_id"], text_key(text))]
                candidates.update(matches)
                for anchor in sorted(matches):
                    proof.append({"town_namespace": "andromeda_town", "town_id": link["key"][2],
                        "source_field": link["source_field"], "dictionary_name_field": field,
                        "dictionary_text": text, "retained_file_sha256": link["retained_file_sha256"],
                        "local_country_id": anchor[0], "local_scope": anchor[1], "local_scope_id": anchor[2],
                        "local_geography_index_sha256": index_sha})
        if candidates.intersection(invalid_local):
            holds.add("inconsistent_local_geography_definition")
        if len(candidates) > 1:
            holds.add("ambiguous_local_geography")
        prior = old_rows.get(key)
        prior_same = bool(prior and prior["evidence_sha256"] == frontier["evidence_sha256"])
        prior_anchors = prior.get("geo_anchors", []) if prior_same else []
        if prior_same and prior.get("country_id") != frontier["country_id"]:
            holds.add("earlier_country_disagreement")
        # A new textual correspondence must not silently replace an older contradictory learned anchor.
        if len(candidates) == 1:
            country, scope, scope_id = next(iter(candidates))
            for earlier in prior_anchors:
                if earlier.get("scope") == scope and earlier.get("scope_id") != scope_id:
                    holds.add("prior_same_scope_disagreement")
                elif earlier.get("scope") != scope:
                    holds.add("prior_cross_scope_requires_parent_readback")
        supported = len(candidates) == 1 and not holds
        if holds:
            route = "held_geography_or_inherited_evidence"
        elif supported:
            route = "unique_dictionary_geography_support"
        elif source_links:
            route = "no_exact_local_geography"
        else:
            route = "no_retained_town_dictionary"
        new = supported and prior_same and not prior_anchors
        row["geography_evidence"] = {"route": route, "holds": sorted(holds),
            "candidate_scopes": [{"country_id": a[0], "scope": a[1], "scope_id": a[2]} for a in sorted(candidates)],
            "support": proof, "earlier_source_digest_matches": prior_same,
            "earlier_geo_anchors": copy.deepcopy(prior_anchors), "new_vs_earlier_missing_anchor": new,
            "historical_geography_only": True, "safe_to_write_now": False}
        result_rows.append(row)
        summary[route] += 1
        summary["queue_rows"] += 1
        summary["with_typed_dictionary"] += bool(source_links)
        summary["new_vs_earlier_missing_anchor"] += new
        summary["unknown_frequency_preserved"] += frontier.get("frequency") is None
        if supported:
            countries[frontier["country_name"]] += 1
    return {"schema": "frontier-retained-geography-evidence/1", "state": "prepared_geography_only",
        "input_pins": PINS, "input_frontier_read_at_utc": dossiers["frontier_read_at_utc"],
        "historical_local_census_at_utc": census["created_at"], "local_geography_index_sha256": index_sha,
        "summary": dict(sorted(summary.items())), "supported_by_country": dict(sorted(countries.items())),
        "frontier_holds_preserved": copy.deepcopy(dossiers["frontier_holds_preserved"]),
        "limitations": ["Not CURRENT DB, not hotel identities or accepted mappings.",
            "Geography text supports a place, not a physical hotel; no hotel-name fuzzy or alias acceptance is performed.",
            "Prior name conflicts and exclusions are preserved. Later matching still requires all CURRENT guards.",
            "Same-name scopes and conflicting dictionary/local definitions are held, never inferred from equal numeric IDs.",
            "This does not recreate or execute the denied supplier collector. Approved PRICE budget is not consumed."],
        "database_writes": 0, "mapping_writes": 0, "supplier_calls": 0, "tourvisor_calls": 0,
        "safe_to_write_now": False, "dossiers": result_rows}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dossier_zip", type=Path)
    parser.add_argument("census_zip", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    report = enrich(read_input(args.dossier_zip, "dossier"), read_input(args.census_zip, "census"))
    raw = canonical(report)
    with args.output.open("xb") as handle:
        require(handle.write(raw) == len(raw), "output_write")
    require(args.output.read_bytes() == raw, "output_readback")
    print(json.dumps({"summary": report["summary"], "sha256": digest(raw)}, sort_keys=True))


if __name__ == "__main__":
    main()
