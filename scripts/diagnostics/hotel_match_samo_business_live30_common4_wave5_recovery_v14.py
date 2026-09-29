#!/usr/bin/env python3
import argparse
import hashlib
import json
from collections import Counter, defaultdict
from pathlib import Path

OP = "hotel-match-samo-business-live30-common4-wave5-recovery-1971-20260925-v14"
PLAN_OP = "hotel-match-samo-business-live30-common4-plan-1971-20260924-v2"
POST_OP = "hotel-match-samo-business-live30-common4-postwrite-1971-20260925-v7"
FAILED_OP = "hotel-match-samo-business-live30-common4-acquire-1971-20260924-v3-wave5"

PLAN_SHA = "174cdd0bd45f48929fbda2e7c07383fbf81afccd18acccca990aee3ee94e6b23"
POST_SHA = "887d4178abc7921176f28ff5e7758fe8df44148faa3260e626dedd1540a4cd1f"
FAILED_SHA = "f0932896d51d3291f992585d2e51bd6d5168d65de97d5947084b75741583b93a"
EXPECTED_MANIFEST_SHA = "5542a3dc0ba8924f19f108ff7826e1628f66128e328bac0cdfc6f595f7329bc4"

OPS = {5: "operator_5", 115: "operator_115", 315: "operator_315", 342: "operator_342"}


def need(value, reason):
    if not value:
        raise RuntimeError(reason)


def load_exact(path, expected_sha):
    raw = Path(path).read_bytes()
    need(hashlib.sha256(raw).hexdigest() == expected_sha, "sha_" + Path(path).name)
    value = json.loads(raw)
    need(isinstance(value, dict), "json_shape_" + Path(path).name)
    return raw, value


def pos(value):
    s = str(value).strip()
    return s if s.isdigit() and not s.startswith("0") and 1 <= len(s) <= 22 else None


def chunks(ids):
    # All catalog IDs are validated positive decimal strings, so integer order
    # is identical to PHP SORT_NATURAL for this dataset.
    ids = sorted(set(map(str, ids)), key=int)
    out, cur = [], []
    for hotel_id in ids:
        need(pos(hotel_id) is not None, "chunk_id")
        candidate = cur + [hotel_id]
        serialized = ",".join(candidate)
        if cur and (len(candidate) > 12 or len(serialized) > 300):
            need(len(cur) <= 12 and len(",".join(cur)) <= 300, "chunk_guard")
            out.append(cur)
            cur = [hotel_id]
        else:
            cur = candidate
        need(len(cur) <= 12 and len(",".join(cur)) <= 300, "chunk_member_guard")
    if cur:
        out.append(cur)
    return out


def plan_rows(plan):
    need(plan.get("operation") == PLAN_OP and plan.get("state") == "samo_business_live30_common4_plan_ready", "plan_state")
    need(plan.get("catalog_live30_count") == 1887 and plan.get("mapped_source_count") == 1764 and plan.get("mapped_unique_local_count") == 1711, "plan_counts")
    need(plan.get("acquisition_ready_count") == 1753, "plan_ready")
    need(isinstance(plan.get("rows"), list) and len(plan["rows"]) == 1887, "plan_rows")
    rows, seen = [], set()
    for row in plan["rows"]:
        if not isinstance(row, dict) or row.get("acquisition_ready") is not True:
            continue
        catalog_id = pos(row.get("andromeda_catalog_id"))
        stateinc = int(row.get("saved_stateinc") or 0)
        missing = sorted(set(int(x) for x in (row.get("missing_operator_ids") or [])))
        need(catalog_id is not None and stateinc > 0 and missing, "plan_row")
        need(all(x in OPS for x in missing), "plan_operator")
        need(catalog_id not in seen, "plan_source_duplicate")
        seen.add(catalog_id)
        rows.append({
            "catalog_id": catalog_id,
            "stateinc": stateinc,
            "missing_operator_ids": missing,
            "local_hotel_id": row.get("local_hotel_id"),
        })
    need(len(rows) == 1753, "plan_ready_rows")
    return rows


def build_groups(rows):
    buckets = defaultdict(set)
    for row in rows:
        for operator_id in row["missing_operator_ids"]:
            buckets[(row["stateinc"], operator_id)].add(row["catalog_id"])
    groups = []
    for (stateinc, operator_id), ids in buckets.items():
        for part in chunks(ids):
            groups.append({"stateinc": stateinc, "operator_id": operator_id, "hotel_ids": part})
    groups.sort(key=lambda g: (g["stateinc"], g["operator_id"], int(g["hotel_ids"][0])))
    for group in groups:
        need(1 <= len(group["hotel_ids"]) <= 12 and len(",".join(group["hotel_ids"])) <= 300, "group_guard")
    return groups


def recovery_manifest(plan, post, failed):
    need(post.get("operation") == POST_OP and post.get("state") == "samo_business_live30_common4_plan_ready", "post_state")
    need(isinstance(post.get("rows"), list) and len(post["rows"]) == 1887, "post_rows")
    need(failed.get("operation") == FAILED_OP, "failed_operation")
    need(failed.get("state") == "terminal_failed_no_replay" and failed.get("reason") == "ANDROMEDA_SUPPLIER_ERROR", "failed_state")
    need(failed.get("samo_http_calls") == 1 and failed.get("queried_edge_count") == 0, "failed_provider_shape")
    need(failed.get("database_writes") == 0 and failed.get("mapping_writes") == 0, "failed_no_write")

    groups = build_groups(plan_rows(plan))
    need(len(groups) == 502, "global_group_count")
    wave5 = groups[400:502]
    need(len(wave5) == 102, "wave5_group_count")

    planned_edges = [(hotel_id, group["operator_id"], group["stateinc"])
                     for group in wave5 for hotel_id in group["hotel_ids"]]
    post_by_catalog = {str(row["andromeda_catalog_id"]): row for row in post["rows"]}
    need(len(post_by_catalog) == 1887, "post_catalog_unique")

    status_counts = Counter()
    by_operator = defaultdict(Counter)
    pairs = defaultdict(list)
    edges = []
    filled = ambiguous = 0

    for catalog_id, operator_id, stateinc in planned_edges:
        row = post_by_catalog.get(catalog_id)
        need(isinstance(row, dict), "post_catalog_missing")
        local_hotel_id = int(row.get("local_hotel_id") or 0)
        need(local_hotel_id > 0, "post_local_missing")
        lane = (row.get("operator_lanes") or {}).get(OPS[operator_id])
        need(isinstance(lane, dict), "post_lane_missing")
        status = str(lane.get("status") or "")
        need(status in {"missing", "accepted_exact", "accepted_ambiguous"}, "post_lane_status")
        status_counts[status] += 1
        by_operator[operator_id][status] += 1
        if status == "accepted_exact":
            filled += 1
        elif status == "accepted_ambiguous":
            ambiguous += 1
        pairs[(operator_id, local_hotel_id)].append((catalog_id, operator_id, stateinc, local_hotel_id))
        edges.append({
            "catalog_id": catalog_id,
            "operator_id": operator_id,
            "stateinc": stateinc,
            "local_hotel_id": local_hotel_id,
            "current_status": status,
        })

    duplicate_groups = [(key, values) for key, values in pairs.items() if len(values) > 1]
    reps = []
    for values in pairs.values():
        reps.append(sorted(values, key=lambda item: int(item[0]))[0])
    rep_rows = [{"catalog_id": cid, "stateinc": stateinc, "missing_operator_ids": [operator_id]}
                for cid, operator_id, stateinc, _local in reps]
    rep_groups = build_groups(rep_rows)

    missing_by_operator = {}
    for operator_id in sorted(by_operator):
        missing_by_operator[str(operator_id)] = int(by_operator[operator_id].get("missing", 0))

    unique_by_operator = Counter(operator_id for operator_id, _local in pairs)

    manifest = {
        "analysis": "hotel-match-samo-business-live30-common4-wave5-current-missing-recovery-v1",
        "immutable_plan": {
            "operation": PLAN_OP,
            "result_sha256": PLAN_SHA,
            "artifact_id": 10798255813,
        },
        "postwave4": {
            "operation": POST_OP,
            "result_sha256": POST_SHA,
            "artifact_id": 10838795676,
        },
        "failed_wave5": {
            "operation": FAILED_OP,
            "result_sha256": FAILED_SHA,
            "artifact_id": 10837779686,
            "state": "terminal_failed_no_replay",
            "reason": "ANDROMEDA_SUPPLIER_ERROR",
        },
        "global_group_count": len(groups),
        "wave5_group_start": 401,
        "wave5_group_end": 502,
        "wave5_planned_group_count": len(wave5),
        "wave5_planned_edge_count": len(planned_edges),
        "wave5_planned_edges_by_operator": {str(k): v for k, v in sorted(Counter(op for _, op, _ in planned_edges).items())},
        "current_status_counts": dict(sorted(status_counts.items())),
        "current_missing_edge_count": int(status_counts.get("missing", 0)),
        "current_missing_edges_by_operator": missing_by_operator,
        "already_filled_edge_count": filled,
        "ambiguous_edge_count": ambiguous,
        "unique_operator_local_targets": len(pairs),
        "unique_operator_local_targets_by_operator": {str(k): v for k, v in sorted(unique_by_operator.items())},
        "catalog_alias_duplicate_edge_count": len(planned_edges) - len(pairs),
        "catalog_alias_duplicate_operator_local_groups": len(duplicate_groups),
        "max_catalog_aliases_per_operator_local": max((len(values) for _key, values in duplicate_groups), default=1),
        "representative_only_hypothetical_group_count": len(rep_groups),
        "representative_only_hypothetical_edge_count": len(reps),
        "note": "Representative-only counts are analysis only, not provider authorization.",
        "edges": edges,
    }
    return manifest


def encode_manifest(manifest):
    return (json.dumps(manifest, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode()


def self_test():
    sample = chunks(["20", "3", "11", "2"])
    need(sample == [["2", "3", "11", "20"]], "self_natural_sort")
    rows = [
        {"catalog_id": "2", "stateinc": 5, "missing_operator_ids": [315]},
        {"catalog_id": "11", "stateinc": 5, "missing_operator_ids": [315]},
        {"catalog_id": "3", "stateinc": 5, "missing_operator_ids": [342]},
    ]
    groups = build_groups(rows)
    need(groups[0]["operator_id"] == 315 and groups[0]["hotel_ids"] == ["2", "11"], "self_groups")
    need(groups[1]["operator_id"] == 342 and groups[1]["hotel_ids"] == ["3"], "self_groups_2")
    print("MATCH_SAMO_BUSINESS_LIVE30_WAVE5_RECOVERY_V14_SELFTEST_OK")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--plan")
    parser.add_argument("--post")
    parser.add_argument("--failed")
    parser.add_argument("--output")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return
    need(all([args.plan, args.post, args.failed, args.output]), "args")
    _plan_raw, plan = load_exact(args.plan, PLAN_SHA)
    _post_raw, post = load_exact(args.post, POST_SHA)
    _failed_raw, failed = load_exact(args.failed, FAILED_SHA)
    manifest = recovery_manifest(plan, post, failed)
    raw = encode_manifest(manifest)
    digest = hashlib.sha256(raw).hexdigest()
    need(digest == EXPECTED_MANIFEST_SHA, "manifest_sha")
    Path(args.output).write_bytes(raw)
    print(json.dumps({
        "operation": OP,
        "state": "completed_supplier_free_recovery_manifest",
        "manifest_sha256": digest,
        "wave5_planned_groups": manifest["wave5_planned_group_count"],
        "wave5_planned_edges": manifest["wave5_planned_edge_count"],
        "current_missing_edges": manifest["current_missing_edge_count"],
        "by_operator": manifest["current_missing_edges_by_operator"],
        "unique_operator_local_targets": manifest["unique_operator_local_targets"],
        "provider_http_calls": 0,
        "ssh_calls": 0,
        "database_writes": 0,
        "mapping_writes": 0,
    }, sort_keys=True))


if __name__ == "__main__":
    main()
