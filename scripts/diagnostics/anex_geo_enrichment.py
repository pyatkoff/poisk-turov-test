"""Bounded second-pass evidence; never writes hotel identities or server data.

Loaded after the access, pilot matching and full-catalog helpers.
"""


import hashlib


def geo_fingerprint(match):
    fields = [match.get(k, "") for k in ("external_id", "name", "alternate_name", "country", "town")]
    return hashlib.sha256(json.dumps(fields, ensure_ascii=False).encode()).hexdigest()[:24]


def load_geo_checkpoint(directory):
    path = Path(directory) / "anex-hotel-geo-enrichment.json"
    if not path.exists():
        return {"rows": []}
    checkpoint = json.loads(path.read_text(encoding="utf-8"))
    if checkpoint.get("schema_version") not in (1, 2) or not isinstance(checkpoint.get("rows"), list):
        raise ValueError("invalid geographic checkpoint")
    if checkpoint["schema_version"] == 1:
        previous = json.loads((Path(directory) / "anex-hotel-catalog-match.json").read_text(encoding="utf-8"))
        matches = {r["external_id"]: r for r in previous["matches"]}
        for row in checkpoint["rows"]:
            row["fingerprint"] = geo_fingerprint(matches[row["external_id"]])
    identifiers = set()
    for row in checkpoint["rows"]:
        identifier = row.get("external_id")
        if (type(identifier) is not int or identifier <= 0 or identifier in identifiers
                or not re.fullmatch(r"[0-9a-f]{24}", row.get("fingerprint", ""))):
            raise ValueError("invalid geographic checkpoint row")
        identifiers.add(identifier)
    return checkpoint


def geo_completed_map(checkpoint):
    # Revisit only records whose previous candidate page was truncated under
    # the smaller reader limit. Other historical results keep their progress.
    return {str(r["external_id"]): r["fingerprint"] for r in checkpoint["rows"]
            if not (r.get("reason") == "candidate_limit_reached"
                    and r.get("candidate_limit", 64) < 256)}


def merge_geo_checkpoint(previous, batch):
    rows = {r["external_id"]: r for r in previous["rows"]}
    rows.update({r["external_id"]: r for r in batch["rows"]})
    return dict(batch, rows=[rows[key] for key in sorted(rows)], processed_total=len(rows),
                counts={s: sum(r["status"] == s for r in rows.values())
                        for s in ("strong_candidate", "review", "unmatched")},
                batch_counts=batch["counts"])


def geo_decision(api, candidates, relation, candidate_set_complete=False):
    if relation != "same_record":
        return "review", "supplier_identity_unverified"
    if not candidates:
        return "unmatched", "no_candidates"
    best = candidates[0]
    if best["country_match"] is False:
        return "review", "country_conflict"
    if best["distance_m"] is not None and best["distance_m"] > 5000:
        return "review", "coordinate_conflict"
    # An ordinary 256-row page cannot prove uniqueness. Only the independently
    # validated complete-review path supplies an exhausted-set proof.
    if len(candidates) >= 256 and candidate_set_complete is not True:
        return "review", "candidate_limit_reached"
    if len(candidates) > 1 and best["score"] - candidates[1]["score"] < 0.1:
        return "review", "competing_candidates"
    qualifiers = {"beach", "garden", "palace", "park", "annex", "adults", "family", "harem"}
    if (set(norm(api.get("name")).split()) & qualifiers) != (set(norm(best["name"]).split()) & qualifiers):
        return "review", "hotel_section_difference"
    if (best["country_match"] is True and best["name_similarity"] >= 0.9
            and best["distance_m"] is not None and best["distance_m"] <= 200):
        return "strong_candidate", "name_country_coordinates"
    return "review", "insufficient_independent_evidence"


def enrich_geo_sample(tokens, matches, hotels, run_deadline=None):
    completed = tokens.get("geo_completed", {})
    pending = sorted((r for r in matches if r["status"] == "review"
                      and completed.get(str(r["external_id"])) != geo_fingerprint(r)),
                     key=lambda r: r["external_id"])
    selected = pending[:30]
    deadline = time.monotonic() + 240
    if run_deadline is not None:
        deadline = min(deadline, run_deadline)
    xml_by_id = {positive_id({"id": h.get("inc")}): h for h in hotels}
    rows, checks = [], []
    for match in selected:
        if time.monotonic() >= deadline:
            break
        identifier = match["external_id"]
        raw = xml_by_id.get(identifier, {})
        xml = {"id": identifier, "name": match["name"], "alternate_name": match["alternate_name"],
               "town_id": positive_id({"id": raw.get("town")})}
        row = {"external_id": identifier, "fingerprint": geo_fingerprint(match),
               "candidate_limit": 256, "checked_at": dt.datetime.now(dt.timezone.utc).isoformat(), "original_status": match["status"], "xml": xml,
               "status": "review", "reason": "details_unavailable", "candidates": []}
        rows.append(row)
        try:
            data = api_data(tokens["ANEX_API_TOKEN"].strip(), "Hotels_DETAILS", {"HOTELINC": identifier}, checks)
            if not isinstance(data, dict):
                continue
            api = supplier_record(data)
            row["api"] = api
            relation = xml_relation(xml, api)
            if country_match(match["country"], api["country"]) is False:
                relation = "country_conflict"
            row["api_xml_relation"] = relation
            if relation != "same_record":
                row["reason"] = "supplier_identity_unverified"
                continue
            catalog = read_catalog([{"key": identifier, "names": [api["name"], xml["name"], xml["alternate_name"]],
                                     "latitude": api["latitude"], "longitude": api["longitude"]}], candidate_limit=256)
            if catalog["status"] != "ok":
                row["reason"] = "catalog_unavailable"
                continue
            candidates = [candidate_rank(api, xml, candidate) for item in catalog["items"]
                          if item.get("key") == identifier for candidate in item.get("candidates", [])]
            candidates.sort(key=lambda c: (-c["score"], c["id"]))
            for candidate in candidates:
                candidate["address_exact"] = bool(norm(api["address"])) and norm(api["address"]) == norm(candidate["address"])
            row["status"], row["reason"] = geo_decision(api, candidates, relation)
            row["candidates"] = candidates
        except StopProbe:
            continue
    return {"schema_version": 2, "preview_only": True, "selection": "pending_review_by_external_id",
            "remaining": len(pending) - len(rows), "batch_limit": 30,
            "selected": len(rows), "counts": {s: sum(r["status"] == s for r in rows)
            for s in ("strong_candidate", "review", "unmatched")}, "rows": rows}



def enrich_geo_run(tokens, matches, hotels):
    """Reuse one reference snapshot for up to ten sequential 30-hotel batches."""
    state = dict(tokens, geo_completed=dict(tokens.get("geo_completed", {})))
    deadline = time.monotonic() + 600
    rows, batches = [], 0
    remaining = sum(r["status"] == "review" and
                    state["geo_completed"].get(str(r["external_id"])) != geo_fingerprint(r)
                    for r in matches)
    stop_reason = "run_limit"
    while remaining and batches < 10:
        if time.monotonic() >= deadline:
            stop_reason = "time_budget"
            break
        batch = enrich_geo_sample(state, matches, hotels, run_deadline=deadline)
        if not batch["rows"]:
            stop_reason = "no_progress"
            break
        batches += 1
        rows.extend(batch["rows"])
        state["geo_completed"].update({str(r["external_id"]): r["fingerprint"] for r in batch["rows"]})
        remaining = batch["remaining"]
        # Do not hammer a unavailable supplier/catalogue for the rest of the run.
        if all(r["reason"] in ("details_unavailable", "catalog_unavailable") for r in batch["rows"]):
            stop_reason = "batch_without_details"
            break
    if not remaining:
        stop_reason = "queue_complete"
    return {"schema_version": 2, "preview_only": True, "selection": "pending_review_by_external_id",
            "remaining": remaining, "batch_limit": 30, "run_limit": 300, "batches": batches,
            "stop_reason": stop_reason, "selected": len(rows),
            "counts": {s: sum(r["status"] == s for r in rows)
                       for s in ("strong_candidate", "review", "unmatched")}, "rows": rows}
