#!/usr/bin/env python3
import datetime, hashlib, json, os, pathlib, tarfile

CURRENT_OP = "hotel-match-tv-anex-missing-samo-frontier-1971-20260920-v4"
CURRENT_RESULT_SHA = "356b4394fe29a6600a5c9d80d3ab06253cbbc439c65702e295e4bb5543175fda"
OPS = [
    CURRENT_OP,
    "hotel-match-live143-moscow-anex-samo-1971-20260919-v2",
    "hotel-match-live20-nonmoscow-anex-samo-1971-20260919-v1",
    "hotel-match-userseen153-anex-samo-1971-20260919-v1",
    "hotel-match-refresh82-samo-native-1971-20260919-v5",
]

ID_KEYS = ("tv_hotel_id","target_local_id","target_hotel_id","catalog_hotel_id","local_hotel_id")
CTX_KEYS = ("context","live_context","latest_future_context","search_context")

def context_key(x):
    if not isinstance(x, dict):
        return None
    d = x.get("departure_date") or x.get("date") or x.get("checkin")
    vals = [x.get("departure_id"), x.get("country_id"), d, x.get("nights"), x.get("adults"),
            x.get("children_count", x.get("children"))]
    if any(v is None or v == "" for v in vals):
        return None
    return (int(vals[0]), int(vals[1]), str(vals[2])[:10], int(vals[3]), int(vals[4]), int(vals[5]),
            str(x.get("child_ages_signature") or ""))

def load_op(root, op):
    p = root / (op + ".tgz")
    out = {}
    with tarfile.open(p, "r:gz") as t:
        for m in t.getmembers():
            if not m.isfile() or m.name not in ("result.json","receipt.json") or m.size >= 64_000_000:
                raise RuntimeError("archive_shape")
            out[m.name] = t.extractfile(m).read()
    if set(out) != {"result.json","receipt.json"}:
        raise RuntimeError("archive_files")
    r = json.loads(out["result.json"])
    q = json.loads(out["receipt.json"])
    h = hashlib.sha256(out["result.json"]).hexdigest()
    if q.get("result_sha256") != h:
        raise RuntimeError("receipt_hash")
    return r, h

def collect_history(node, touched_ids, touched_ctx):
    if isinstance(node, dict):
        ids = []
        for k in ID_KEYS:
            v = node.get(k)
            if isinstance(v, (int, str)) and str(v).isdigit() and int(v) > 0:
                ids.append(int(v))
        contexts = [context_key(node)] + [context_key(node.get(k)) for k in CTX_KEYS]
        for i in ids:
            touched_ids.add(i)
            for c in contexts:
                if c:
                    touched_ctx.add((i, c))
        for v in node.values():
            collect_history(v, touched_ids, touched_ctx)
    elif isinstance(node, list):
        for v in node:
            collect_history(v, touched_ids, touched_ctx)

def main():
    root = pathlib.Path(os.environ.get("MATCH_HISTORY_DIR","old"))
    data = {op: load_op(root, op) for op in OPS}
    current, current_sha = data[CURRENT_OP]
    if current_sha != CURRENT_RESULT_SHA or current.get("state") != "completed_read_only":
        raise RuntimeError("current_authority")
    ready = [x for x in current.get("frontier",[]) if x.get("route") == "query_ready_user_seen_anex"]
    if len(ready) != 75:
        raise RuntimeError("ready75_cardinality")

    touched_ids, touched_ctx = set(), set()
    for op in OPS[1:]:
        collect_history(data[op][0], touched_ids, touched_ctx)

    fresh, new_context, held = [], [], []
    cutoff = datetime.datetime.fromisoformat("2026-09-20T00:00:00+00:00")
    for x in ready:
        hid = int(x["tv_hotel_id"])
        c = x.get("latest_future_anex_context") or {}
        ck = context_key(c)
        row = {
            "tv_hotel_id": hid,
            "hotel_name": x.get("hotel_name"),
            "country_id": x.get("country_id"),
            "country_name": x.get("country_name"),
            "anex_hotel_ids": x.get("anex_hotel_ids"),
            "context": c,
            "safe_to_write_now": False,
        }
        if hid not in touched_ids:
            row["history_class"] = "fresh_candidate"
            fresh.append(row)
            continue
        if ck and (hid, ck) in touched_ctx:
            row["history_class"] = "touched_same_context_hold"
            held.append(row)
            continue
        observed = str(c.get("observed_at") or "")
        try:
            dt = datetime.datetime.fromisoformat(observed.replace("Z","+00:00"))
        except Exception:
            dt = None
        if ck and dt and dt >= cutoff:
            row["history_class"] = "new_context_candidate"
            new_context.append(row)
        else:
            row["history_class"] = "touched_target_conservative_hold"
            held.append(row)

    result = {
        "state": "completed_read_only_history_partition",
        "current_result_sha256": current_sha,
        "ready_input_count": 75,
        "fresh_candidate_count": len(fresh),
        "new_context_candidate_count": len(new_context),
        "touched_hold_count": len(held),
        "queryable_count": len(fresh) + len(new_context),
        "fresh_candidates": fresh,
        "new_context_candidates": new_context,
        "touched_holds": held,
        "provider_calls": 0,
        "database_reads": 0,
        "database_writes": 0,
        "mapping_writes": 0,
        "no_replay": True,
    }
    out = pathlib.Path(os.environ.get("MATCH_HISTORY_OUTPUT","result.json"))
    out.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({k: result[k] for k in ("ready_input_count","fresh_candidate_count","new_context_candidate_count","touched_hold_count","queryable_count")}, ensure_ascii=False))

if __name__ == "__main__":
    main()
