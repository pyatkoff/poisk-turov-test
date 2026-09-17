#!/usr/bin/env python3
"""Build a deterministic, offline SAMO/Andromeda PRICE acquisition manifest.

This planner performs no network/DB writes. It consumes the immutable CURRENT
frontier reconciliation and the immutable recovered pair audit. Output is only a
query manifest; any supplier executor must separately reserve, re-check the live
request ledger, and fail closed on replay/budget drift before access.
"""
from __future__ import annotations
import argparse, hashlib, json
from collections import Counter, defaultdict
from pathlib import Path

CURRENT_SHA = "c90f87dc1e3ec438501bbba5bcd475d7bc49e884611f76e051e627b59075cc2e"
PAIR_AUDIT_SHA = "d9ee55245c3bc442659d373ff280e773603da74c29e7301b0395abcfa457f1b6"
OPERATION_ID = "hotel-match-frontier-provider-acquisition-plan-1971-20260917-v1"
TARGET_HOLDS = {
    "independent_name_difference_proof_required",
    "independent_name_and_geography_evidence_required",
}
STATE_BY_COUNTRY = {1: 3, 4: 5, 2: 12, 9: 20, 8: 73, 10: 76, 16: 126, 12: 116}
COUNTRY_NAME = {1: "Египет", 4: "Турция", 2: "Таиланд", 9: "ОАЭ", 8: "Мальдивы", 10: "Куба", 16: "Вьетнам", 12: "Шри-Ланка"}
OPERATORS = {"5": "ANEX", "315": "FUN&SUN", "342": "Интурист", "115": "Библио-Глобус"}
PHASES = (("first_pass", "20261020"), ("conditional_second_pass", "20261124"))
MAX_HOTELS_PER_QUERY = 30
MAX_PAGES_PER_QUERY = 2
MAX_PRICE_ALLOWANCE = 1000


def raw_sha(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def canonical(obj) -> bytes:
    return (json.dumps(obj, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode()


def chunks(items, n):
    return [items[i:i+n] for i in range(0, len(items), n)]


def build(current_path: Path, pair_path: Path) -> dict:
    if raw_sha(current_path) != CURRENT_SHA:
        raise SystemExit("CURRENT_SHA_MISMATCH")
    if raw_sha(pair_path) != PAIR_AUDIT_SHA:
        raise SystemExit("PAIR_AUDIT_SHA_MISMATCH")
    current = json.loads(current_path.read_text())
    pair_audit = json.loads(pair_path.read_text())
    if current.get("operation_id") != "hotel-match-frontier-current-reconcile-1971-20260917-v1":
        raise SystemExit("CURRENT_OPERATION_MISMATCH")
    if current.get("examined_dossiers") != 538 or current.get("examined_pairs") != 619:
        raise SystemExit("CURRENT_SCOPE_MISMATCH")
    if current.get("mapping_writes") != 0 or current.get("database_writes") != 0:
        raise SystemExit("CURRENT_NOT_READ_ONLY")

    audit_by_pair = {}
    for p in pair_audit["pairs"]:
        key = (str(p["external_hotel_id"]), int(p["proposed_local_hotel_id"]))
        if key in audit_by_pair:
            raise SystemExit("DUP_PAIR_AUDIT")
        audit_by_pair[key] = p

    targets = []
    seen_external = set()
    for p in current["pairs"]:
        holds = set(p.get("holds", []))
        selected = holds & TARGET_HOLDS
        if p.get("route") != "held" or len(selected) != 1:
            continue
        eid = str(p["external_hotel_id"])
        local_id = int(p["proposed_local_hotel_id"])
        a = audit_by_pair.get((eid, local_id))
        if not a:
            raise SystemExit("MISSING_PAIR_AUDIT")
        if eid in seen_external:
            raise SystemExit("TARGET_DOSSIER_NOT_UNIQUE")
        seen_external.add(eid)
        country = int(p["country_id"])
        if country not in STATE_BY_COUNTRY:
            raise SystemExit("UNKNOWN_COUNTRY")
        targets.append({
            "external_hotel_id": eid,
            "country_id": country,
            "country_name": COUNTRY_NAME[country],
            "candidate_local_hotel_id": local_id,
            "evidence_gap": next(iter(selected)),
            "source_names": a.get("source_names", []),
            "source_evidence_sha256": a.get("source_evidence_sha256"),
            "pair_stage": p.get("stage"),
            "safe_to_write_now": False,
        })

    targets.sort(key=lambda x: (x["country_id"], int(x["external_hotel_id"])))
    if len(targets) != 223:
        raise SystemExit(f"TARGET_COUNT_{len(targets)}")
    gaps = Counter(t["evidence_gap"] for t in targets)
    if gaps != Counter({"independent_name_difference_proof_required": 137,
                        "independent_name_and_geography_evidence_required": 86}):
        raise SystemExit("TARGET_GAP_COUNTS")

    by_country = defaultdict(list)
    for t in targets:
        by_country[t["country_id"]].append(t["external_hotel_id"])

    queries = []
    query_no = 0
    for phase, date in PHASES:
        for country in (1, 4, 9, 2, 8, 10, 16, 12):
            ids = by_country.get(country, [])
            for batch_no, batch in enumerate(chunks(ids, MAX_HOTELS_PER_QUERY), start=1):
                for operator_key, operator_name in OPERATORS.items():
                    params = {
                        "TOWNFROMINC": 1,
                        "STATEINC": STATE_BY_COUNTRY[country],
                        "CHECKIN_BEG": date,
                        "CHECKIN_END": date,
                        "NIGHTS_FROM": 7,
                        "NIGHTS_TILL": 10,
                        "ADULT": 2,
                        "CHILD": 0,
                        "CURRENCYINC": 643,
                        "OPERATORS": operator_key,
                        "HOTELS": ",".join(batch),
                        "GROUP_BY": 32,
                        "PACKETTYPE": 0,
                        "PAGE": 1,
                    }
                    request_sha = hashlib.sha256(canonical(params)).hexdigest()
                    queries.append({
                        "query_no": query_no,
                        "phase": phase,
                        "conditional_on_no_identity_from_prior_phase": phase != "first_pass",
                        "country_id": country,
                        "country_name": COUNTRY_NAME[country],
                        "operator_key": operator_key,
                        "operator_name": operator_name,
                        "batch_no": batch_no,
                        "hotel_count": len(batch),
                        "hotel_ids": batch,
                        "params_page1": params,
                        "page1_request_sha256": request_sha,
                        "max_pages": MAX_PAGES_PER_QUERY,
                    })
                    query_no += 1

    first = [q for q in queries if q["phase"] == "first_pass"]
    second = [q for q in queries if q["phase"] == "conditional_second_pass"]
    if len(first) != 44 or len(second) != 44:
        raise SystemExit(f"QUERY_COUNT_{len(first)}_{len(second)}")
    if len({q["page1_request_sha256"] for q in queries}) != len(queries):
        raise SystemExit("DUP_REQUEST_DIGEST_IN_PLAN")
    max_first_price = len(first) * MAX_PAGES_PER_QUERY
    max_total_price = len(queries) * MAX_PAGES_PER_QUERY
    if max_total_price > MAX_PRICE_ALLOWANCE:
        raise SystemExit("PRICE_ALLOWANCE_EXCEEDED")

    country_counts = Counter(t["country_name"] for t in targets)
    return {
        "schema": "match-frontier-provider-acquisition-plan/1",
        "operation_id": OPERATION_ID,
        "state": "offline_mass_supplier_manifest_only",
        "input_pins": {"current_reconcile_sha256": CURRENT_SHA, "pair_audit_sha256": PAIR_AUDIT_SHA},
        "scope": {
            "current_unresolved_frontier_dossiers": current["remaining_dossiers_needing_evidence"],
            "selected_provider_priority_dossiers": len(targets),
            "evidence_gap_counts": dict(sorted(gaps.items())),
            "country_counts": dict(sorted(country_counts.items())),
        },
        "provider_contract": {
            "api": "SAMO/Andromeda PRICE",
            "hotel_batch_limit": MAX_HOTELS_PER_QUERY,
            "operators": OPERATORS,
            "core8_state_crosswalk": {str(k): v for k, v in STATE_BY_COUNTRY.items()},
            "max_pages_per_query": MAX_PAGES_PER_QUERY,
            "first_pass_logical_queries": len(first),
            "first_pass_max_price_requests": max_first_price,
            "conditional_second_pass_logical_queries": len(second),
            "conditional_second_pass_max_price_requests": len(second) * MAX_PAGES_PER_QUERY,
            "absolute_manifest_max_price_requests": max_total_price,
            "supplier_http_calls_including_one_login_if_fully_consumed": max_total_price + 1,
            "approved_price_allowance_ceiling": MAX_PRICE_ALLOWANCE,
            "pace_requirement_seconds_min": 1.05,
        },
        "execution_guards": [
            "planner_performs_zero_supplier_calls_and_zero_database_writes",
            "executor_must_create_new reservation before any supplier access",
            "executor_must check durable request ledger and skip/fail closed on any prior completed request digest",
            "second phase is conditional and must not run for hotel/operator identities resolved in first phase",
            "page2 only when page1 did not resolve all requested hotel identities and supplier reports another page",
            "provider identity observations are evidence only; never direct mapping authority by themselves",
            "mapping acceptance requires separate CURRENT transaction-time guarded writer and post-COMMIT per-row readback",
            "do not use ANEX photo /oNNN as hotel identity; only supported native hotelCode/original.hotelKey contracts",
        ],
        "targets": targets,
        "queries": queries,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "database_writes": 0,
        "mapping_writes": 0,
        "safe_to_write_now": False,
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--current", required=True)
    ap.add_argument("--pair-audit", required=True)
    ap.add_argument("--output", required=True)
    args = ap.parse_args()
    out = build(Path(args.current), Path(args.pair_audit))
    raw = json.dumps(out, ensure_ascii=False, sort_keys=True, indent=2) + "\n"
    Path(args.output).write_text(raw)
    parsed = json.loads(Path(args.output).read_text())
    if canonical(parsed) != canonical(out):
        raise SystemExit("OUTPUT_READBACK_MISMATCH")
    print(json.dumps({
        "targets": len(out["targets"]),
        "first_queries": out["provider_contract"]["first_pass_logical_queries"],
        "max_first_price": out["provider_contract"]["first_pass_max_price_requests"],
        "max_total_price": out["provider_contract"]["absolute_manifest_max_price_requests"],
        "output_sha256": hashlib.sha256(Path(args.output).read_bytes()).hexdigest(),
    }, ensure_ascii=False, sort_keys=True))

if __name__ == "__main__":
    main()
