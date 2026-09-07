"""Bounded second-pass evidence; never writes hotel identities or server data.

Loaded after the access, pilot matching and full-catalog helpers.
"""


def geo_decision(api, candidates, relation):
    if relation != "same_record":
        return "review", "supplier_identity_unverified"
    if not candidates:
        return "unmatched", "no_candidates"
    best = candidates[0]
    if best["country_match"] is False:
        return "review", "country_conflict"
    if best["distance_m"] is not None and best["distance_m"] > 5000:
        return "review", "coordinate_conflict"
    # The reader returns at most eight rows: a full page cannot prove uniqueness.
    if len(candidates) >= 8:
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


def enrich_geo_sample(tokens, matches, hotels):
    # Ten deterministic review records: reproducible pilot, not a full queue pass.
    selected = sorted((r for r in matches if r["status"] == "review"),
                      key=lambda r: r["external_id"])[:10]
    xml_by_id = {positive_id({"id": h.get("inc")}): h for h in hotels}
    rows, checks = [], []
    for match in selected:
        identifier = match["external_id"]
        raw = xml_by_id.get(identifier, {})
        xml = {"id": identifier, "name": match["name"], "alternate_name": match["alternate_name"],
               "town_id": positive_id({"id": raw.get("town")})}
        row = {"external_id": identifier, "original_status": match["status"], "xml": xml,
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
                                     "latitude": api["latitude"], "longitude": api["longitude"]}])
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
    return {"schema_version": 1, "preview_only": True, "selection": "first_10_review_by_external_id",
            "selected": len(rows), "counts": {s: sum(r["status"] == s for r in rows)
            for s in ("strong_candidate", "review", "unmatched")}, "rows": rows}
