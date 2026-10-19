#!/usr/bin/env python3
"""Offline MATCH receipt recovery. No network, DB access, or acceptance decisions.

A terminal stopped operation stays stopped/no-replay. Independently completed
checkpoints may contribute evidence; accepted local anchors are NOT SAFE mappings.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import zipfile
from collections import Counter
from pathlib import Path, PurePosixPath
from typing import Any

MAX_BYTES = 64 * 1024 * 1024
ID = re.compile(r"^[A-Za-z0-9_.-]{1,32}$")
DIGEST = re.compile(r"^[a-f0-9]{64}$")


def require(ok: bool, reason: str) -> None:
    if not ok:
        raise ValueError(reason)


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def encoded(value: Any) -> bytes:
    # Request params are ASCII scalar values, serialized with PHP JSON_PRETTY_PRINT.
    return (json.dumps(value, ensure_ascii=False, indent=4) + "\n").encode()


def unique_object(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        require(key not in result, "duplicate_json_key")
        result[key] = value
    return result


def parse(raw: bytes) -> Any:
    return json.loads(raw, object_pairs_hook=unique_object)


def archive(path: Path, digest: str) -> dict[str, bytes]:
    require(bool(DIGEST.fullmatch(digest)), "invalid_archive_digest")
    require(path.stat().st_size <= MAX_BYTES, "archive_too_large")
    require(sha(path.read_bytes()) == digest, "archive_digest_mismatch")
    with zipfile.ZipFile(path) as z:
        names = z.namelist()
        require(len(names) == len(set(names)), "duplicate_zip_member")
        require(sum(x.file_size for x in z.infolist()) <= MAX_BYTES, "expanded_archive_too_large")
        for name in names:
            require(not name.startswith("/") and ".." not in PurePosixPath(name).parts,
                    "unsafe_zip_member")
        # Read only; never extract archive paths or execute retained source files.
        return {name: z.read(name) for name in names if name.endswith(".json")}


def pair(fact: dict[str, Any]) -> tuple[str, str, str]:
    return tuple(str(fact[k]) for k in ("operator_key", "native_hotel_id", "andromeda_hotel_id"))


def verify_receipt(files: dict[str, bytes], prefix: str = "") -> dict[str, Any]:
    raw = files[prefix + "result.json"]
    result = parse(raw)
    receipt = parse(files[prefix + "receipt.json"])
    for key in ("operation_id", "source_sha", "state"):
        require(receipt.get(key) == result.get(key), "receipt_identity_mismatch")
    require(receipt.get("result_sha256") == sha(raw), "receipt_digest_mismatch")
    require(receipt.get("readback_verified") is True, "unverified_receipt")
    require(receipt.get("no_replay") is True and result.get("no_replay") is True, "no_replay_missing")
    require(result.get("database_writes") == receipt.get("database_writes") == 0, "not_read_only")
    return result


def recover(evidence: dict[str, bytes], current_files: dict[str, bytes]) -> dict[str, Any]:
    result = verify_receipt(evidence)
    current = verify_receipt(current_files, "server/")
    require(result["state"] in ("completed", "stopped_no_retry"), "unknown_terminal_result")
    require(current["state"] == "completed_read_only" and current.get("supplier_calls") == 0,
            "current_snapshot_contract")
    require(all(result.get(k) == 0 for k in ("mapping_writes", "tourvisor_calls", "all_calls", "booking_calls")),
            "unexpected_external_side_effect")
    reservation = parse(evidence["reservation.json"])
    plan = parse(evidence["plan.json"])
    require(evidence["reserved-plan.json"] == evidence["plan.json"], "plan_changed_after_reservation")
    require(reservation.get("plan_sha256") == sha(evidence["plan.json"]), "plan_digest_mismatch")
    require(reservation.get("operation_id") == result["operation_id"] and
            reservation.get("source_sha") == result["source_sha"], "reservation_identity_mismatch")
    require(plan.get("current_result_sha256") == sha(current_files["server/result.json"]), "wrong_current_snapshot")
    require(result["plan_summary"].get("current_result_sha256") == plan["current_result_sha256"], "result_snapshot_mismatch")

    facts: dict[tuple[str, str, str], dict[str, Any]] = {}
    for fact in plan["saved_facts"]:
        require(pair(fact) not in facts, "duplicate_saved_pair")
        facts[pair(fact)] = fact
    saved = set(facts)
    require(len(saved) == plan["saved_covered_count"], "saved_count_mismatch")
    requests = {n: parse(v) for n, v in evidence.items() if re.fullmatch(r"request-\d+\.json", n)}
    pages: list[dict[str, Any]] = []
    completed_requests: set[str] = set()
    for name in sorted(evidence):
        if not re.fullmatch(r"response-\d+\.json", name):
            continue
        checkpoint = parse(evidence[name])
        request_name = name.replace("response-", "request-", 1)
        require(request_name in requests, "checkpoint_without_reservation")
        request = requests[request_name]
        require(request.get("operation_id") == result["operation_id"] and
                request.get("state") == "reserved_before_supplier_access", "request_reservation_contract")
        require(request["params"] == checkpoint["params"], "request_context_mismatch")
        require(request["request_sha256"] == checkpoint["request_sha256"] == sha(encoded(request["params"])),
                "request_digest_mismatch")
        require(DIGEST.fullmatch(checkpoint["response_sha256"]) is not None, "missing_response_provenance")
        params = checkpoint["params"]
        require(str(params["OPERATORS"]) == str(checkpoint["operator_key"]), "operator_context_mismatch")
        require(set(params["HOTELS"].split(",")) == set(checkpoint["hotel_ids"]), "hotel_context_mismatch")
        observed = {(str(o["operatorKey"]), str(o["original"]["hotelKey"]), str(o["hotelKey"]))
                    for o in checkpoint["observations"] if o.get("isOperatorHotelKey") in (0, False)}
        require(checkpoint["valid_rows"] == len(checkpoint["facts"]), "checkpoint_count_mismatch")
        for fact in checkpoint["facts"]:
            key = pair(fact)
            require(key in observed, "fact_without_original_observation")
            require(key[0] == str(checkpoint["operator_key"]) and key[2] in checkpoint["hotel_ids"], "fact_outside_requested_scope")
            require(ID.fullmatch(key[1]) is not None and fact.get("is_operator_hotel_key") is False, "native_identity_semantics")
            require(fact["country_id"] == checkpoint["country_id"], "fact_country_mismatch")
            require(fact["request_sha256"] == checkpoint["request_sha256"] and
                    fact["response_sha256"] == checkpoint["response_sha256"], "fact_provenance_mismatch")
            require(key not in facts or facts[key] == fact, "contradictory_fact_provenance")
            facts[key] = fact
        pages.append({k: v for k, v in checkpoint.items() if k not in ("facts", "observations")})
        completed_requests.add(request_name)
    require(pages == result["pages"], "result_checkpoint_mismatch")
    terminal = {pair(f): f for f in result["facts"]}
    require(len(terminal) == len(result["facts"]) == result["unique_pair_count"], "duplicate_terminal_pair")
    require(terminal == facts, "terminal_union_mismatch")
    require(len(requests) == result["price_calls"], "reserved_request_count_mismatch")
    unfinished = sorted(set(requests) - completed_requests)
    require(not unfinished or result["state"] == "stopped_no_retry", "completed_with_unknown_request")

    targets = {str(t["andromeda_hotel_id"]): t for t in current["targets"]}
    existing = {(r["supplier_namespace"], str(r["external_hotel_id"])): r for r in current["existing_operator_rows"]}
    require(len(targets) == len(current["targets"]) == current["target_count"], "duplicate_current_target")
    require(len(existing) == len(current["existing_operator_rows"]), "duplicate_current_operator")
    rows = []
    counts: Counter[str] = Counter()
    for key, fact in sorted(facts.items()):
        target = targets.get(key[2])
        prior = existing.get(("operator_" + key[0], key[1]))
        if not target:
            bucket = "missing_anchor"
        elif target["country_id"] != fact["country_id"]:
            bucket = "country_conflict"
        elif target["decision_status"] != "accepted" or not target["local_hotel_id"]:
            bucket = "anchor_not_accepted"
        elif prior and prior["decision_status"] == "accepted":
            bucket = "already_linked_same_target" if prior["local_hotel_id"] == target["local_hotel_id"] else "accepted_target_conflict"
        elif prior:
            bucket = "existing_nonaccepted_requires_current_guards"
        else:
            bucket = "missing_native_link_requires_current_guards"
        counts[bucket] += 1
        rows.append({"fact": fact, "new_checkpoint_fact": key not in saved,
                     "relation_only_bucket": bucket, "snapshot_anchor": target,
                     "snapshot_existing_native": prior, "auto_accept": False})
    rows.sort(key=lambda r: (-int((r["snapshot_anchor"] or {}).get("frequency", 0)), pair(r["fact"])))
    return {"schema_version": 1, "state": "offline_evidence_handoff_not_apply_manifest",
            "source_operation_id": result["operation_id"], "source_operation_state": result["state"],
            "source_result_sha256": sha(evidence["result.json"]),
            "current_operation_id": current["operation_id"], "current_result_sha256": sha(current_files["server/result.json"]),
            "snapshot_is_not_current_at_apply": True, "pair_count": len(rows),
            "saved_pair_count": len(saved), "recovered_new_pair_count": len(facts.keys() - saved),
            "completed_checkpoint_count": len(pages), "reserved_without_checkpoint": unfinished,
            "relation_only_counts": dict(sorted(counts.items())),
            "source_paid_calls_not_repeated": result["supplier_calls"],
            "verification_scope": "Archive/result/receipt/request/checkpoint/observed-original-ID verification. Raw upstream response bodies are not contained in these artifacts and were not rehashed.",
            "mandatory_before_acceptance": ["server_current_transaction", "manual_decisions_and_pair_exclusions",
                "existing_native_and_direct_mappings", "current_primary_alias_and_geography_guards",
                "coordinate_conflict_guard", "new_immutable_write_reservation", "per_row_post_commit_readback"],
            "auto_accept": False, "apply_manifest": False, "database_writes": 0, "mapping_writes": 0,
            "supplier_calls": 0, "tourvisor_calls": 0, "no_replay": True, "rows": rows}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    for label in ("evidence", "current"):
        parser.add_argument("--" + label, type=Path, required=True)
        parser.add_argument("--" + label + "-sha256", required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    result = recover(archive(args.evidence, args.evidence_sha256), archive(args.current, args.current_sha256))
    result["input_archive_sha256"] = {"evidence": args.evidence_sha256, "current": args.current_sha256}
    args.output.mkdir(parents=True, exist_ok=False)
    raw = encoded(result)
    (args.output / "result.json").write_bytes(raw)
    require(sha((args.output / "result.json").read_bytes()) == sha(raw), "output_readback_failure")
    summary = {k: v for k, v in result.items() if k != "rows"}
    summary.update({"result_sha256": sha(raw), "readback_verified": True})
    (args.output / "receipt.json").write_bytes(encoded(summary))
    print(json.dumps(summary, ensure_ascii=False, sort_keys=True))


if __name__ == "__main__":
    main()
