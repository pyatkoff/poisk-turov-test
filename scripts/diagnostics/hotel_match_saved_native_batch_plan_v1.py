#!/usr/bin/env python3
"""Plan all saved SAMO/TV native-ID candidates without DB, HTTP or acceptance.

Input: the existing MATCH unresolved SAMO and TV nontriple CSV exports. Names,
prices and ranks never create a native pair. The optional supporting name review
returns a separate shortlist and never changes the native plan or grants admission.
Uniqueness is only within the supplied
snapshots; every candidate still needs raw proof and CURRENT writer checks.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import io
import json
import re
from collections import Counter, defaultdict
from pathlib import Path

LANES = ("anex", "bg", "funsun", "intourist")
PROTECTED_CATALOG_IDS = frozenset({"2000086118"})
MAX_BYTES = 64 * 1024 * 1024
MAX_ROWS = 50_000
RETAINED_OPERATION = 'hotel-match-live30-retained-native-union-1971-20260927-v77'
RETAINED_SHA256 = 'd40fbe2e0240a5df194ac838376a3425a8f4e80f2426a757560fe369011107f7'
NAMESPACE_LANES = {'operator_5': 'anex', 'operator_115': 'bg',
                   'operator_315': 'funsun', 'operator_342': 'intourist'}
# Canonical fixed v76/v78/PM1 scopes are not fresh candidates from a stale v77.
# Membership means terminal review is required, not that every member committed.
PRIOR_FIXED_WRITER_SCOPE = frozenset({
    '309768', '2000037585', '2000093384', '2000055490', '2000087342', '269426',
    '2000103169', '2000090159', '650', '3060', '67775', '201859', '218356',
    '240679', '2000023232', '2000050217', '2000065684', '2000067202', '2000073592',
    '9501', '2000034238', '3126',
})
ID = re.compile(r"[1-9][0-9]{0,31}\Z")
HASH = re.compile(r"[a-f0-9]{64}\Z")
CURRENT_CHECKS = [
    "raw_samo_proof", "independent_tv_operator_proof", "global_native_uniqueness",
    "canonical_local_target", "source_history_and_revision", "manual_and_exclusions",
    "source_and_target_occupancy", "active_country_category_geography",
    "durable_reservation_and_no_replay", "transaction_and_post_commit_readback",
]
NAME_GENERIC_TOKENS = frozenset(('hotel hotels otel resort resorts spa beach palace '
    'royal grand golden garden gardens club luxury deluxe collection boutique '
    'apart apartment apartments suites residence international the and only adults '
    'all inclusive ex former formerly').split())


def supporting_name_review(saved_rows: list[dict], target_summary: dict) -> dict:
    """Index retained target names for review, never for acceptance or acquisition.

    The caller must bind both immutable inputs by hash. Target ownership is an
    observation at captured_at_utc, not a new CURRENT check. Original names and
    every rare-token match (including former names and occupied targets) survive.
    There is no best-match selection, country inference or native-ID projection.
    """
    if (not isinstance(target_summary, dict)
            or target_summary.get('state') != 'completed_tv_live30_target_catalog_v2'
            or target_summary.get('operation') != 'int-andromeda-match-live30-target-catalog-v2-20261001-v1'
            or target_summary.get('no_replay') is not True
            or target_summary.get('safe_to_write_now') is not False
            or any(type(target_summary.get(k)) is not int or target_summary[k] != 0
                   for k in ('provider_http_calls', 'database_writes', 'mapping_writes'))
            or not isinstance(target_summary.get('rows'), list)
            or not isinstance(saved_rows, list)
            or len(saved_rows) > MAX_ROWS or len(target_summary['rows']) > MAX_ROWS
            or target_summary.get('row_count') != len(target_summary['rows'])):
        raise ValueError('name_review_input_contract')

    def tokens(name):
        return {t for t in re.findall(r'[^\W_]+', name.casefold())
                if len(t) >= 5 and not t.isdigit() and t not in NAME_GENERIC_TOKENS}

    index = defaultdict(set)
    targets = {}
    for row in target_summary['rows']:
        if (not isinstance(row, dict) or type(row.get('id')) is not int
                or not ID.fullmatch(str(row['id'])) or row['id'] in targets
                or not isinstance(row.get('name'), str)
                or not isinstance(row.get('accepted_samo_ids'), list)
                or any(type(x) not in (int, str) or not ID.fullmatch(str(x))
                       for x in row['accepted_samo_ids'])
                or any(type(row.get(k)) is not bool
                       for k in ('is_active', 'manual_hold', 'exclusion_hold'))):
            raise ValueError('name_review_target_shape')
        targets[row['id']] = row
        for token in tokens(row['name']):
            index[token].add(row['id'])
    out = []
    source_ids = set()
    for source in saved_rows:
        if (not isinstance(source, dict) or not isinstance(source.get('samo_catalog_id'), str)
                or not ID.fullmatch(source['samo_catalog_id'])
                or source['samo_catalog_id'] in source_ids
                or not isinstance(source.get('source_names'), str)
                or not isinstance(source.get('reasons'), list)):
            raise ValueError('name_review_source_shape')
        catalog = source['samo_catalog_id']
        source_ids.add(catalog)
        hits = defaultdict(set)
        for token in tokens(source['source_names']):
            # Frequency selects review signals only; it cannot establish identity.
            if 0 < len(index[token]) <= 8:
                for target in index[token]:
                    hits[target].add(token)
        candidates = []
        for target in sorted(hits):
            row = targets[target]
            holds = []
            if row['accepted_samo_ids']: holds.append('target_occupied_in_retained_catalog')
            if row['manual_hold']: holds.append('target_manual_hold_in_retained_catalog')
            if row['exclusion_hold']: holds.append('target_exclusion_in_retained_catalog')
            if not row['is_active']: holds.append('target_inactive_in_retained_catalog')
            candidates.append({'tv_hotel_id': str(target), 'target_name': row['name'],
                'country_name': row.get('country_name'), 'region_name': row.get('region_name'),
                'subregion_name': row.get('subregion_name'), 'shared_rare_tokens': sorted(hits[target]),
                'accepted_samo_ids': list(row['accepted_samo_ids']), 'holds': holds,
                'signal': 'name_only', 'safe_to_write_now': False})
        source_holds = list(source['reasons'])
        if catalog in PROTECTED_CATALOG_IDS and 'protected_catalog_id' not in source_holds:
            source_holds.append('protected_catalog_id')
        out.append({'samo_catalog_id': catalog, 'source_names': source['source_names'],
            'source_holds': sorted(source_holds), 'source_country_verified': False,
            'name_review_candidates': candidates, 'safe_to_write_now': False})
    out.sort(key=lambda row: int(row['samo_catalog_id']))
    return {'schema': 'hotel-match-supporting-name-review/1',
        'state': 'completed_supporting_name_review', 'rows': out,
        'target_observed_at_utc': target_summary.get('captured_at_utc'),
        'source_rows': len(out), 'target_rows': len(targets),
        'name_signal_source_rows': sum(bool(r['name_review_candidates']) for r in out),
        'provider_http_calls': 0, 'database_reads': 0, 'database_writes': 0, 'mapping_writes': 0,
        'fresh_current_census': False, 'acceptance_policy_defined': False,
        'acquisition_authorized': False, 'safe_to_write_now': False}


def ids(value: str) -> tuple[list[str], bool]:
    """Keep identifier strings exact; malformed tokens remain a visible HOLD."""
    if not value:
        return [], False
    tokens = value.split("|")
    return sorted({n for n in tokens if ID.fullmatch(n)}, key=int), any(
        not ID.fullmatch(n) for n in tokens
    )


def tri_bool(value: str) -> bool | None:
    return {"True": True, "true": True, "1": True,
            "False": False, "false": False, "0": False}.get(value)


def load_csv(path: Path, role: str) -> tuple[list[dict], dict]:
    if path.is_symlink() or not path.is_file() or not 0 < path.stat().st_size <= MAX_BYTES:
        raise ValueError("input_file_size_or_type")
    raw = path.read_bytes()
    if len(raw) > MAX_BYTES:
        raise ValueError("input_file_size_or_type")
    reader = csv.DictReader(io.StringIO(raw.decode("utf-8-sig"), newline=""))
    required = {"samo_catalog_id", "source_decision_status_v77", "source_local_hotel_id_v77",
                "source_catalog_sha256", "source_evidence_sha256"} if role == "samo" else {
                    "tv_hotel_id", "has_samo_mapping", "samo_catalog_ids_accepted"}
    required |= {f"{role}_{lane}_native_ids" for lane in LANES}
    if not required <= set(reader.fieldnames or []):
        raise ValueError("input_columns_missing")
    rows = []
    for index, row in enumerate(reader):
        if len(rows) >= MAX_ROWS or None in row or any(v is None for v in row.values()):
            raise ValueError("input_row_limit_or_shape")
        rows.append(dict(row, _csv_record=index + 2))
    return rows, {"name": path.name, "sha256": hashlib.sha256(raw).hexdigest(),
                  "bytes": len(raw), "row_count": len(rows)}


def plan(samo: list[dict], tv: list[dict], global_sources: dict | None = None) -> dict:
    # Each lane retains its own namespace. BG's owner-requested prefix rule
    # selects candidates only and never manufactures proof or write authority.
    source_index = {lane: defaultdict(set) for lane in LANES}
    if global_sources is not None:
        for lane, native_sources in global_sources.items():
            if lane not in LANES:
                raise ValueError('retained_global_namespace')
            for native, catalogs in native_sources.items():
                if not ID.fullmatch(native) or any(not ID.fullmatch(c) for c in catalogs):
                    raise ValueError('retained_global_identity')
                source_index[lane][native].update(catalogs)
    target_index = {lane: defaultdict(set) for lane in LANES}
    source_ids = Counter(r.get("samo_catalog_id", "") for r in samo)
    tv_rows = defaultdict(list)
    target_errors = defaultdict(set)
    row_facts = []
    for row in tv:
        target = row.get("tv_hotel_id", "")
        if not ID.fullmatch(target):
            raise ValueError("invalid_tv_hotel_id")
        tv_rows[target].append(row)
        for lane in LANES:
            native, bad = ids(row.get(f"tv_{lane}_native_ids", ""))
            if bad:
                target_errors[target].add("malformed_tv_native_id")
            if len(native) > 1:
                target_errors[target].add("multiple_tv_native_ids_same_operator")
            for n in native:
                target_index[lane][n].add(target)
    for row in samo:
        catalog = row.get("samo_catalog_id", "")
        if not ID.fullmatch(catalog):
            raise ValueError("invalid_samo_catalog_id")
        native_by_lane, errors = {}, []
        for lane in LANES:
            native, bad = ids(row.get(f"samo_{lane}_native_ids", ""))
            native_by_lane[lane] = native
            if bad:
                errors.append("malformed_samo_native_id")
            if len(native) > 1:
                errors.append("multiple_samo_native_ids_same_operator")
            for n in native:
                source_index[lane][n].add(catalog)
        row_facts.append((row, native_by_lane, errors))

    out = []
    for row, native_by_lane, errors in row_facts:
        catalog = row["samo_catalog_id"]
        reasons = list(errors)
        if source_ids[catalog] != 1:
            reasons.append("duplicate_samo_catalog_rows")
        if row.get("source_decision_status_v77") != "pending":
            reasons.append("source_decision_protected_or_unknown")
        if row.get("source_local_hotel_id_v77") or row.get("current_accepted_locals_in_frontier_v77"):
            reasons.append("source_already_mapped")
        for field in ("source_catalog_sha256", "source_evidence_sha256"):
            if not HASH.fullmatch(row.get(field, "")):
                reasons.append("source_digest_missing_or_invalid")
        if catalog in PROTECTED_CATALOG_IDS:
            reasons.append("protected_catalog_id")
        hits = []
        for lane in LANES:
            for n in native_by_lane[lane]:
                key = "102" + n if lane == "bg" else n
                targets = target_index[lane].get(key, set())
                if len(source_index[lane][n]) != 1:
                    reasons.append("native_multiple_samo_sources_in_inputs")
                if len(targets) > 1:
                    reasons.append("native_multiple_tv_targets_in_inputs")
                for target in sorted(targets, key=int):
                    target_records = tv_rows[target]
                    hits.append({
                        "operator": lane, "samo_native_id": n, "tv_native_id": key,
                        "tv_hotel_id": target,
                        "rule": "owner_bg_102_prefix_candidate" if lane == "bg" else "exact_same_operator_native_candidate",
                        "source_csv_record": row.get("_csv_record"),
                        "target_csv_records": [r.get("_csv_record") for r in target_records],
                        "source_raw_evidence_refs": row.get(f"{lane}_evidence_refs", ""),
                        "tv_known_exact_in_snapshot": all(
                            tri_bool(r.get(f"{lane}_known_exact", "")) is True for r in target_records),
                    })
        targets = sorted({h["tv_hotel_id"] for h in hits}, key=int)
        if len(targets) > 1:
            reasons.append("operator_targets_disagree")
        occupied = []
        target_names = []
        for target in targets:
            records = tv_rows[target]
            if len(records) != 1:
                reasons.append("duplicate_tv_hotel_rows")
            reasons.extend(target_errors[target])
            for record in records:
                target_names.append({"tv_hotel_id": target, "name": record.get("hotel_name", ""),
                                     "country": record.get("country", ""), "region": record.get("region", "")})
                flag = tri_bool(record.get("has_samo_mapping", ""))
                if flag is None:
                    reasons.append("target_occupancy_unknown")
                if flag is True or record.get("samo_catalog_ids_accepted", ""):
                    occupied.append({"tv_hotel_id": target,
                                     "accepted_samo_ids_raw": record.get("samo_catalog_ids_accepted", "")})
        if occupied:
            reasons.append("target_existing_samo_mapping")
        local_anchors = []
        for lane in LANES:
            anchors, bad = ids(row.get(f"linked_local_ids_via_{lane}_native", ""))
            if bad:
                reasons.append("malformed_saved_local_anchor")
            for local in anchors:
                local_anchors.append({"operator": lane, "local_hotel_id": local,
                                      "source_csv_record": row.get("_csv_record")})
            if anchors and not native_by_lane[lane]:
                reasons.append("local_anchor_without_source_native")
        local_ids = sorted({a["local_hotel_id"] for a in local_anchors}, key=int)
        if len(local_ids) > 1:
            reasons.append("saved_local_anchors_disagree")
        reasons = sorted(set(reasons))
        out.append({
            "samo_catalog_id": catalog, "source_names": row.get("hotel_names_seen", ""),
            "source_csv_record": row.get("_csv_record"),
            "observed_offers_raw": row.get("observed_offers", ""),
            "source_catalog_sha256": row.get("source_catalog_sha256", ""),
            "source_evidence_sha256": row.get("source_evidence_sha256", ""),
            "native_by_operator": native_by_lane, "candidate_tv_ids": targets,
            "target_names": target_names, "native_matches": hits,
            # These are independent local IDs, never relabelled as TV hotel IDs.
            "saved_local_anchors": local_anchors, "candidate_local_ids": local_ids,
            "existing_target_mappings": occupied, "reasons": reasons,
            "safe_to_write_now": False,
        })
    # Check collisions over the entire input, including held/occupied sources.
    # Never let projection, priority or batch caps hide a competing catalog ID.
    target_sources = defaultdict(set)
    if global_sources is not None:
        # A competing source may use a different operator/native than the
        # selected source while still pointing to the same TV target. Include
        # it before projecting the frontier, not just in same-native checks.
        for lane, native_sources in source_index.items():
            for native, catalogs in native_sources.items():
                key = '102' + native if lane == 'bg' else native
                for target in target_index[lane].get(key, set()):
                    target_sources[target].update(catalogs)
    for row in out:
        for target in row["candidate_tv_ids"]:
            target_sources[target].add(row["samo_catalog_id"])
    for row in out:
        if any(len(target_sources[t]) > 1 for t in row["candidate_tv_ids"]):
            row["reasons"] = sorted(set(row["reasons"] + ["target_multiple_samo_candidates_in_inputs"]))
        reasons = row["reasons"]
        if "protected_catalog_id" in reasons:
            state = "protected"
        elif any(r in reasons for r in (
                "operator_targets_disagree", "native_multiple_samo_sources_in_inputs",
                "native_multiple_tv_targets_in_inputs", "target_multiple_samo_candidates_in_inputs")):
            state = "conflict_review"
        elif reasons:
            state = "occupied_review" if "target_existing_samo_mapping" in reasons else "source_or_evidence_review"
        elif row["candidate_local_ids"] and not row["candidate_tv_ids"]:
            state = "saved_local_anchor_current_checks_required"
        elif not any(row["native_by_operator"].values()):
            state = "missing_native_id"
        elif not row["candidate_tv_ids"]:
            state = "no_target_in_saved_slice"
        else:
            state = "candidate_current_checks_required"
        row["state"] = state
        row["required_before_acceptance"] = CURRENT_CHECKS if (
            row["candidate_tv_ids"] or row["candidate_local_ids"]) else []
    out.sort(key=lambda row: (row["state"], int(row["samo_catalog_id"]), row.get("source_csv_record") or 0))
    counts = Counter(r["state"] for r in out)
    hits = Counter(h["operator"] for r in out for h in r["native_matches"])
    return {
        "schema": "hotel-match-saved-native-batch-plan/1", "state": "completed_saved_only_plan",
        "provider_http_calls": 0, "database_reads": 0, "database_writes": 0, "mapping_writes": 0,
        "safe_to_write_now": False,
        "scope": {"samo_rows": len(samo), "tv_rows": len(tv),
                  "uniqueness": "supplied_snapshot_only", "fresh_current_census": False},
        "counts": dict(sorted(counts.items())), "operator_native_match_counts": dict(sorted(hits.items())),
        "unique_target_candidates": sum(len(r["candidate_tv_ids"]) == 1 for r in out),
        "required_before_acceptance": CURRENT_CHECKS, "rows": out,
    }


def load_retained(path: Path) -> tuple[dict, dict]:
    if path.is_symlink() or not path.is_file() or not 0 < path.stat().st_size <= MAX_BYTES:
        raise ValueError('retained_file_size_or_type')
    raw = path.read_bytes()
    if len(raw) > MAX_BYTES or hashlib.sha256(raw).hexdigest() != RETAINED_SHA256:
        raise ValueError('retained_digest')
    doc = json.loads(raw)
    if (doc.get('operation') != RETAINED_OPERATION
            or doc.get('state') != 'completed_retained_native_scan'
            or any(type(doc.get(k)) is not int or doc[k] != 0 for k in ('database_writes', 'mapping_writes', 'provider_http_calls'))
            or any(not isinstance(doc.get(k), list) or len(doc[k]) != n for k, n in (
                ('native_facts', 3262), ('source_frontier', 1895), ('current_identities', 15042)))):
        raise ValueError('retained_producer_contract')
    return doc, {'name': path.name, 'sha256': RETAINED_SHA256, 'bytes': len(raw),
                 'operation': RETAINED_OPERATION, 'original_pages_present': False}


def retained_plan(doc: dict, tv: list[dict], covered_catalog_ids: set[str] | None = None) -> dict:
    """Index the entire saved user-search frontier; never claim fresh CURRENT.

    All native facts seed collision checks, including sources outside the selected
    frontier. A source already covered by a prior report or fixed writer remains
    visible and cannot be promoted to an additional available batch.
    """
    covered = covered_catalog_ids or set()
    global_sources = {lane: defaultdict(set) for lane in LANES}
    facts = defaultdict(list)
    identities = defaultdict(list)
    for key in ('native_facts', 'current_identities', 'source_frontier'):
        if not isinstance(doc.get(key), list) or len(doc[key]) > MAX_ROWS:
            raise ValueError('retained_inventory_shape')
    def identifier(value):
        if type(value) not in (str, int) or not ID.fullmatch(str(value)):
            raise ValueError('retained_identity')
        return str(value)
    for fact in doc['native_facts']:
        if not isinstance(fact, dict) or fact.get('supplier_namespace') not in NAMESPACE_LANES:
            raise ValueError('retained_native_namespace')
        cat, native = identifier(fact.get('catalog_id')), identifier(fact.get('native_id'))
        lane = NAMESPACE_LANES[fact['supplier_namespace']]
        global_sources[lane][native].add(cat)
        if not isinstance(fact.get('evidence'), list) or len(fact['evidence']) > MAX_ROWS:
            raise ValueError('retained_evidence_shape')
        for ref in fact['evidence']:
            if (not isinstance(ref, dict) or not HASH.fullmatch(str(ref.get('sha256', '')))
                    or not isinstance(ref.get('source_file'), str)
                    or not re.fullmatch(r'operations/hotel-match-[a-zA-Z0-9_-]+/(?:evidence-private/)?[a-zA-Z0-9_.-]+\.json', ref['source_file'])
                    or not isinstance(ref.get('json_pointer'), str)
                    or not re.fullmatch(r'/PRICES/[0-9]{1,8}', ref['json_pointer'])):
                raise ValueError('retained_evidence_pointer')
        facts[cat].append((lane, native, fact['evidence']))
    for identity in doc['current_identities']:
        if not isinstance(identity, dict):
            raise ValueError('retained_identity_shape')
        if identity.get('supplier_namespace') == 'andromeda_catalog':
            identities[identifier(identity.get('external_hotel_id'))].append(identity)
    sources = []
    for frontier in doc['source_frontier']:
        if not isinstance(frontier, dict):
            raise ValueError('retained_frontier_shape')
        cat = identifier(frontier.get('catalog_id'))
        matches = identities[cat]
        identity = matches[0] if len(matches) == 1 else {}
        accepted = frontier.get('current_accepted_locals')
        if not isinstance(accepted, list):
            raise ValueError('retained_frontier_ownership')
        row = {'samo_catalog_id': cat, 'source_decision_status_v77': identity.get('decision_status', 'unknown'),
               'source_local_hotel_id_v77': '' if identity.get('local_hotel_id') is None else identifier(identity['local_hotel_id']),
               'source_catalog_sha256': identity.get('catalog_sha256', ''),
               'source_evidence_sha256': identity.get('evidence_sha256', ''),
               'current_accepted_locals_in_frontier_v77': '|'.join(identifier(x) for x in accepted),
               'observed_offers': str(frontier.get('observed_offers', ''))}
        for lane in LANES:
            row['samo_'+lane+'_native_ids'] = '|'.join(sorted({n for op, n, _ in facts[cat] if op == lane}, key=int))
        sources.append(row)
    result = plan(sources, tv, global_sources)
    counts = Counter()
    for row in result['rows']:
        cat = row['samo_catalog_id']
        row['source_identity_snapshot'] = [{k: identity.get(k) for k in (
            'supplier_namespace', 'external_hotel_id', 'local_hotel_id', 'decision_status',
            'catalog_sha256', 'evidence_sha256')} for identity in identities[cat]]
        row['retained_native_evidence'] = [dict(operator=lane, native_id=n, references=refs,
                                              original_pages_verified=False) for lane, n, refs in facts[cat]]
        row['covered_in_saved110'] = cat in covered
        row['prior_fixed_writer_scope'] = cat in PRIOR_FIXED_WRITER_SCOPE
        if cat in PROTECTED_CATALOG_IDS:
            state = 'protected'
        elif cat in covered:
            state = 'already_covered_saved110'
        elif cat in PRIOR_FIXED_WRITER_SCOPE:
            state = 'prior_fixed_writer_terminal_review'
        elif row['state'] == 'candidate_current_checks_required':
            state = 'additional_saved_candidate_current_review'
        else:
            state = row['state']
        row['retained_frontier_state'] = state
        # A review plan does not define/expand the eventual acceptance policy.
        row['required_before_acceptance'] = []
        row['required_before_current_review'] = ['raw_samo_hash_pointer_original_hotelKey',
            'independent_operator_evidence_and_namespace', 'fresh_authorized_current_contract',
            'confirmed_owner_acceptance_policy', 'terminal_and_ownership_review']
        counts[state] += 1
    result.update(schema='hotel-match-retained-frontier-plan/1', state='completed_saved_retained_frontier_plan',
                  retained_frontier_counts=dict(sorted(counts.items())), required_before_acceptance=[],
                  retained_native_facts=len(doc['native_facts']), source_identity_metadata=len(doc['current_identities']),
                  acceptance_policy_defined=False)
    result['scope'].update(retained_operation=RETAINED_OPERATION, uniqueness='all_saved_union_native_facts_and_tv_slice',
                           original_pages_verified=False, fresh_current_census=False)
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    input_group = parser.add_mutually_exclusive_group(required=True)
    input_group.add_argument("--samo-csv", type=Path)
    input_group.add_argument("--retained-native-json", type=Path)
    parser.add_argument("--covered-samo-csv", type=Path)
    parser.add_argument("--tv-csv", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    tv, target = load_csv(args.tv_csv, "tv")
    if args.retained_native_json:
        doc, retained = load_retained(args.retained_native_json)
        covered, provenance = load_csv(args.covered_samo_csv, 'samo') if args.covered_samo_csv else ([], None)
        result = retained_plan(doc, tv, {r['samo_catalog_id'] for r in covered})
        result['inputs'] = {'retained_native': retained, 'tv': target, 'previously_covered_samo': provenance}
    else:
        if args.covered_samo_csv:
            parser.error('--covered-samo-csv requires --retained-native-json')
        samo, source = load_csv(args.samo_csv, "samo")
        result = plan(samo, tv)
        result["inputs"] = {"samo": source, "tv": target}
    # Exclusive output never overwrites an earlier checkpoint or receipt.
    with args.output.open("x", encoding="utf-8") as handle:
        json.dump(result, handle, ensure_ascii=False, indent=2)
        handle.write("\n")
    print(json.dumps({key: value for key, value in result.items() if key != "rows"}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
