#!/usr/bin/env python3
"""Offline mass SAMO/Andromeda acquisition planner for MATCH #1971.

No network, supplier, DB or mapping writes. A later executor must reserve
separately and check the durable request ledger before supplier access.
"""
from __future__ import annotations
import argparse, hashlib, json
from collections import Counter, defaultdict
from pathlib import Path

CURRENT_SHA = "c90f87dc1e3ec438501bbba5bcd475d7bc49e884611f76e051e627b59075cc2e"
PAIR_AUDIT_SHA = "d9ee55245c3bc442659d373ff280e773603da74c29e7301b0395abcfa457f1b6"
OPERATION_ID = "hotel-match-frontier-provider-acquisition-plan-1971-20260917-v1"
TARGET_HOLDS = {"independent_name_difference_proof_required", "independent_name_and_geography_evidence_required"}
STATE_BY_COUNTRY = {1:3, 4:5, 2:12, 9:20, 8:73, 10:76, 16:126, 12:116}
COUNTRY_NAME = {1:"Египет", 4:"Турция", 2:"Таиланд", 9:"ОАЭ", 8:"Мальдивы", 10:"Куба", 16:"Вьетнам", 12:"Шри-Ланка"}
COUNTRY_ORDER = (1,4,9,2,8,10,16,12)
OPERATORS = {"5":"ANEX", "315":"FUN&SUN", "342":"Интурист", "115":"Библио-Глобус"}
PHASES = (("first_pass","20261020",False),("conditional_second_pass","20261124",True))
MAX_HOTELS = 30
MAX_PAGES = 2
MAX_PRICE_ALLOWANCE = 1000


def sha(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def chunks(xs, n):
    return [xs[i:i+n] for i in range(0, len(xs), n)]


def build(current_path: Path, pair_path: Path) -> dict:
    if sha(current_path) != CURRENT_SHA: raise SystemExit("CURRENT_SHA_MISMATCH")
    if sha(pair_path) != PAIR_AUDIT_SHA: raise SystemExit("PAIR_AUDIT_SHA_MISMATCH")
    current = json.loads(current_path.read_text())
    audit = json.loads(pair_path.read_text())
    if current.get("operation_id") != "hotel-match-frontier-current-reconcile-1971-20260917-v1": raise SystemExit("CURRENT_OPERATION_MISMATCH")
    if (current.get("examined_dossiers"), current.get("examined_pairs")) != (538,619): raise SystemExit("CURRENT_SCOPE_MISMATCH")
    if current.get("database_writes") or current.get("mapping_writes"): raise SystemExit("CURRENT_NOT_READ_ONLY")
    audit_pairs={(str(p["external_hotel_id"]),int(p["proposed_local_hotel_id"])):p for p in audit["pairs"]}
    by_country=defaultdict(list); gaps=Counter(); seen=set()
    for p in current["pairs"]:
        selected=set(p.get("holds",[])) & TARGET_HOLDS
        if p.get("route") != "held" or len(selected) != 1: continue
        eid=str(p["external_hotel_id"]); local=int(p["proposed_local_hotel_id"]); country=int(p["country_id"])
        if (eid,local) not in audit_pairs: raise SystemExit("MISSING_PAIR_AUDIT")
        if eid in seen: raise SystemExit("TARGET_DOSSIER_NOT_UNIQUE")
        if country not in STATE_BY_COUNTRY: raise SystemExit("UNKNOWN_COUNTRY")
        seen.add(eid); by_country[country].append(eid); gaps[next(iter(selected))]+=1
    for ids in by_country.values(): ids.sort(key=int)
    if len(seen) != 223: raise SystemExit(f"TARGET_COUNT_{len(seen)}")
    if gaps != Counter({"independent_name_difference_proof_required":137,"independent_name_and_geography_evidence_required":86}): raise SystemExit("GAP_COUNTS")
    batches=[]
    for country in COUNTRY_ORDER:
        for no,batch in enumerate(chunks(by_country[country],MAX_HOTELS),1):
            batches.append({"batch_no":no,"country_id":country,"country_name":COUNTRY_NAME[country],"hotel_ids":batch,"stateinc":STATE_BY_COUNTRY[country]})
    if len(batches) != 11: raise SystemExit("BATCH_COUNT")
    first_queries=len(batches)*len(OPERATORS); second_queries=first_queries
    first_max=first_queries*MAX_PAGES; total_max=(first_queries+second_queries)*MAX_PAGES
    if (first_queries,first_max,total_max) != (44,88,176): raise SystemExit("REQUEST_COUNTS")
    if total_max > MAX_PRICE_ALLOWANCE: raise SystemExit("PRICE_ALLOWANCE")
    country_counts={COUNTRY_NAME[c]:len(by_country[c]) for c in COUNTRY_ORDER}
    return {
      "database_writes":0,
      "execution_guards":[
        "planner_performs_zero_supplier_calls_and_zero_database_writes",
        "executor_must_create_new reservation before any supplier access",
        "executor_must check durable request ledger and skip/fail closed on any prior completed request digest",
        "second phase is conditional and must not run for hotel/operator identities resolved in first phase",
        "page2 only when page1 did not resolve all requested hotel identities and supplier reports another page",
        "provider identity observations are evidence only; never direct mapping authority by themselves",
        "mapping acceptance requires separate CURRENT transaction-time guarded writer and post-COMMIT per-row readback",
        "do not use ANEX photo /oNNN as hotel identity; only supported native hotelCode/original.hotelKey contracts"
      ],
      "hotel_batches":batches,
      "input_pins":{"current_reconcile_sha256":CURRENT_SHA,"pair_audit_sha256":PAIR_AUDIT_SHA},
      "mapping_writes":0,
      "operation_id":OPERATION_ID,
      "provider_contract":{
        "absolute_manifest_max_price_requests":total_max,"api":"SAMO/Andromeda PRICE","approved_price_allowance_ceiling":MAX_PRICE_ALLOWANCE,
        "conditional_second_pass_logical_queries":second_queries,"conditional_second_pass_max_price_requests":second_queries*MAX_PAGES,
        "core8_state_crosswalk":{str(k):v for k,v in STATE_BY_COUNTRY.items()},"first_pass_logical_queries":first_queries,
        "first_pass_max_price_requests":first_max,"hotel_batch_limit":MAX_HOTELS,"max_pages_per_query":MAX_PAGES,"operators":OPERATORS,
        "pace_requirement_seconds_min":1.05,"params_template":{"ADULT":2,"CHILD":0,"CURRENCYINC":643,"GROUP_BY":32,"NIGHTS_FROM":7,"NIGHTS_TILL":10,"PACKETTYPE":0,"TOWNFROMINC":1},
        "phases":[{"checkin":d,"conditional":cond,"name":name} for name,d,cond in PHASES],
        "supplier_http_calls_including_one_login_if_fully_consumed":total_max+1
      },
      "safe_to_write_now":False,"schema":"match-frontier-provider-acquisition-plan-summary/1",
      "scope":{"country_counts":country_counts,"current_unresolved_frontier_dossiers":current["remaining_dossiers_needing_evidence"],"evidence_gap_counts":dict(sorted(gaps.items())),"selected_provider_priority_dossiers":len(seen)},
      "state":"offline_mass_supplier_manifest_only","supplier_calls":0,"tourvisor_calls":0
    }


def main():
    ap=argparse.ArgumentParser(); ap.add_argument("--current",required=True); ap.add_argument("--pair-audit",required=True); ap.add_argument("--output",required=True); a=ap.parse_args()
    out=build(Path(a.current),Path(a.pair_audit)); raw=json.dumps(out,ensure_ascii=False,sort_keys=True,indent=2)+"\n"; Path(a.output).write_text(raw)
    if json.loads(Path(a.output).read_text()) != out: raise SystemExit("OUTPUT_READBACK")
    print(json.dumps({"targets":out["scope"]["selected_provider_priority_dossiers"],"batches":len(out["hotel_batches"]),"first_queries":out["provider_contract"]["first_pass_logical_queries"],"max_first_price":out["provider_contract"]["first_pass_max_price_requests"],"max_total_price":out["provider_contract"]["absolute_manifest_max_price_requests"],"output_sha256":sha(Path(a.output))},ensure_ascii=False,sort_keys=True))

if __name__ == "__main__": main()
