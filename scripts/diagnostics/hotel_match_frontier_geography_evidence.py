"""Offline geography and hotel-name evidence review; never a live resolver or writer."""
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



def parent_index(hotels: dict) -> dict:
    """All observed parents, including missing/invalid entries; never majority-vote."""
    out = defaultdict(set)
    for h in hotels.values():
        if positive(h.get("country_id")) and positive(h.get("subregion_id")):
            parent = h.get("region_id")
            out[(h["country_id"], h["subregion_id"])].add(parent if positive(parent) else None)
    return out


def parent_proof(country: int, first: dict, second: dict, parents: dict) -> dict | None:
    """Different scope is compatible ONLY through a unique same-country parent."""
    scopes = {first.get("scope"), second.get("scope")}
    if scopes != {"region", "subregion"}:
        return None
    region, subregion = (first, second) if first["scope"] == "region" else (second, first)
    rid, sid = region.get("scope_id"), subregion.get("scope_id")
    if not positive(rid) or not positive(sid):
        return None
    if any(a.get("country_id", country) != country for a in (first, second)):
        return None
    if parents.get((country, sid)) != {rid}:
        return None
    return {"country_id": country, "subregion_id": sid, "region_id": rid,
            "census_result_sha256": PINS["census"]["result_sha256"], "current_revalidation_required": True}


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
    parents = parent_index(census["local_hotels"])
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
        parent_support = []
        # A new textual correspondence must not silently replace an older contradictory learned anchor.
        if len(candidates) == 1:
            country, scope, scope_id = next(iter(candidates))
            for earlier in prior_anchors:
                if earlier.get("scope") == scope and earlier.get("scope_id") != scope_id:
                    holds.add("prior_same_scope_disagreement")
                elif earlier.get("scope") != scope:
                    relation = parent_proof(country, {"scope": scope, "scope_id": scope_id}, earlier, parents)
                    if relation is None:
                        holds.add("prior_cross_scope_requires_parent_readback")
                    else:
                        parent_support.append(relation)
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
            "support": proof, "parent_support": parent_support, "earlier_source_digest_matches": prior_same,
            "earlier_geo_anchors": copy.deepcopy(prior_anchors), "new_vs_earlier_missing_anchor": new,
            "historical_geography_only": True, "safe_to_write_now": False}
        result_rows.append(row)
        summary[route] += 1
        summary["queue_rows"] += 1
        summary["with_typed_dictionary"] += bool(source_links)
        summary["new_vs_earlier_missing_anchor"] += new
        summary["parent_compatible_dossiers"] += supported and bool(parent_support)
        summary["unknown_frequency_preserved"] += frontier.get("frequency") is None
        if supported:
            countries[frontier["country_name"]] += 1
    return {"schema": "frontier-retained-geography-evidence/2", "state": "prepared_geography_only",
        "input_pins": PINS, "input_frontier_read_at_utc": dossiers["frontier_read_at_utc"],
        "historical_local_census_at_utc": census["created_at"], "local_geography_index_sha256": index_sha,
        "summary": dict(sorted(summary.items())), "supported_by_country": dict(sorted(countries.items())),
        "frontier_holds_preserved": copy.deepcopy(dossiers["frontier_holds_preserved"]),
        "limitations": ["Not CURRENT DB, not hotel identities or accepted mappings.",
            "Geography text supports a place, not a physical hotel; it cannot authorize hotel acceptance.",
            "Prior name conflicts and exclusions are preserved. Later matching still requires all CURRENT guards.",
            "Same-name scopes and conflicting dictionary/local definitions are held, never inferred from equal numeric IDs.",
            "This does not recreate or execute the denied supplier collector. Approved PRICE budget is not consumed."],
        "database_writes": 0, "mapping_writes": 0, "supplier_calls": 0, "tourvisor_calls": 0,
        "safe_to_write_now": False, "dossiers": result_rows}



# Pure evidence functions. None of these functions grants CURRENT/write authority.
GENERIC = frozenset({'hotel', 'hotels', 'resort', 'resorts', 'spa', 'отель'})
QUALIFIERS = frozenset('annex annexe beach garden gardens north south east west mountain posh family junior deluxe aqua park palace royal grand premium select bay island village pool sea adult adults sun moon main'.split())


def name_forms(value: str) -> list[dict]:
    value = unicodedata.normalize('NFKC', value).casefold().replace("'", '').replace('’', '')
    value = re.sub(r'\baquapark\b', 'aqua park', value)
    out = []
    for part in re.split(r'\b(?:ex|former|formerly)\b\.?', value):
        tokens = tuple(t for t in re.findall(r'[^\W_]+', part) if t not in GENERIC)
        if tokens:
            out.append({'compact': ''.join(tokens), 'tokens': tokens, 'raw': part.strip(),
                        'qualifiers': tuple(sorted(t for t in tokens if t in QUALIFIERS)),
                        'numbers': tuple(re.findall(r'\d+', ' '.join(tokens)))})
    return out


def lev_distance(a: str, b: str) -> int:
    """Bit-parallel Levenshtein, tested against exhaustive scalar DP fixtures."""
    if not a:
        return len(b)
    masks = {}
    for i, ch in enumerate(a):
        masks[ch] = masks.get(ch, 0) | (1 << i)
    vp, vn, score, high = (1 << len(a)) - 1, 0, len(a), 1 << (len(a) - 1)
    for ch in b:
        x = masks.get(ch, 0) | vn
        d = (((x & vp) + vp) ^ vp) | x
        hn, hp = vp & d, vn | ~(vp | d)
        score += bool(hp & high) - bool(hn & high)
        x = (hp << 1) | 1
        vn, vp = x & d, (hn << 1) | ~(x | d)
    return score


def compatible_forms(a: dict, b: dict) -> bool:
    return a['qualifiers'] == b['qualifiers'] and a['numbers'] == b['numbers']


def informative(form: dict) -> bool:
    # Compound spelling (Yaman Life / Yamanlife) is not a loss of information.
    return len(form['compact']) >= 8 and any(t not in QUALIFIERS and not t.isdigit() for t in form['tokens'])


def one_token_edit(a: dict, b: dict) -> bool:
    aa, bb = a['tokens'], b['tokens']
    if len(aa) < 2 or len(aa) != len(bb) or not compatible_forms(a, b):
        return False
    changed = [(x, y) for x, y in zip(aa, bb) if x != y]
    return (len(changed) == 1 and all(re.fullmatch('[a-z]{4,}', x) and x not in QUALIFIERS for x in changed[0])
            and lev_distance(*changed[0]) == 1)


def build_name_index(census: dict) -> tuple[dict, dict]:
    exact, holes = defaultdict(dict), defaultdict(list)
    for lid, h in census['local_hotels'].items():
        names = set(census.get('local_alias_forms', {}).get(str(lid), [])) | {h.get('name', '')}
        for raw in sorted(names):
            for form in name_forms(raw):
                key = (h['country_id'], form['compact'])
                exact[key].setdefault(int(lid), []).append(form)
                if len(form['tokens']) >= 2:
                    for i, token in enumerate(form['tokens']):
                        if token not in QUALIFIERS and re.fullmatch('[a-z]{4,}', token):
                            signature = form['tokens'][:i] + ('*',) + form['tokens'][i + 1:]
                            holes[(h['country_id'], signature)].append((int(lid), form))
    return exact, holes


def name_review(names: list[str], country: int, exact: dict, holes: dict) -> dict:
    source = [f for name in names for f in name_forms(name)]
    matches = defaultdict(list)
    # ALL compact-exact competitors participate, including short/qualifier forms.
    for sf in source:
        for lid, local in exact.get((country, sf['compact']), {}).items():
            matches[lid].extend((sf, lf) for lf in local)
    if matches:
        ids = sorted(matches)
        good = [(sf, lf) for sf, lf in matches[ids[0]] if informative(sf) and informative(lf) and compatible_forms(sf, lf)]
        hold = 'ambiguous_countrywide_exact' if len(ids) != 1 else ('short_or_qualifier_identity' if not good else None)
        return {'route': 'held' if hold else 'name_proof_candidate', 'holds': [hold] if hold else [],
                'candidate_ids': ids, 'target': ids[0] if len(ids) == 1 else None,
                'method': 'exact_compact_primary_or_explicit_alias',
                'forms': good[:1] if good else matches[ids[0]][:1], 'score': 1.0,
                'compound_segmentation': bool(good and good[0][0]['tokens'] != good[0][1]['tokens'])}
    bounded = {}
    for sf in source:
        for i, token in enumerate(sf['tokens']):
            signature = sf['tokens'][:i] + ('*',) + sf['tokens'][i + 1:]
            for lid, lf in holes.get((country, signature), []):
                score = 1 - lev_distance(sf['compact'], lf['compact']) / max(len(sf['compact']), len(lf['compact']))
                if score >= 0.94 and one_token_edit(sf, lf) and informative(sf) and informative(lf):
                    if lid not in bounded or score > bounded[lid][0]:
                        bounded[lid] = (score, sf, lf)
    if not bounded:
        return {'route': 'needs_additional_name_evidence', 'holds': ['no_exact_or_bounded_spelling'],
                'candidate_ids': [], 'target': None}
    # Retrieval is narrow; margin is NOT. Compare every saved country form.
    rank = defaultdict(float)
    for (cid, compact), local in exact.items():
        if cid != country:
            continue
        best = max((1 - lev_distance(sf['compact'], compact) / max(len(sf['compact']), len(compact))
                    for sf in source if abs(len(sf['compact']) - len(compact)) <= 0.2 * max(len(sf['compact']), len(compact))), default=0.0)
        if best >= 0.8:
            for lid in local:
                rank[lid] = max(rank[lid], best)
    order = sorted(rank, key=lambda lid: (-rank[lid], lid))
    lid = max(bounded, key=lambda k: (bounded[k][0], -k))
    score, sf, lf = bounded[lid]
    runner = max([0.8] + [v for k, v in rank.items() if k != lid])
    good = bool(order and order[0] == lid and score - runner >= 0.12 - 1e-12)
    return {'route': 'name_proof_candidate' if good else 'held', 'holds': [] if good else ['countrywide_fuzzy_margin'],
            'candidate_ids': sorted(bounded), 'target': lid, 'forms': [(sf, lf)], 'score': score,
            'runner_up_upper_bound': runner, 'margin_lower_bound': score - runner,
            'method': 'single_nonqualifier_letter', 'compound_segmentation': False}


def point(value: dict) -> tuple | None:
    import math
    try:
        a, b = float(value.get('latitude', value.get('lat'))), float(value.get('longitude', value.get('lon', value.get('lng'))))
        if math.isfinite(a) and math.isfinite(b) and abs(a) <= 90 and abs(b) <= 180 and (a or b):
            return a, b
    except (ValueError, TypeError):
        pass
    return None


def distance_km(a: tuple, b: tuple) -> float:
    import math
    lat1, lon1, lat2, lon2 = map(math.radians, (*a, *b))
    h = math.sin((lat2 - lat1) / 2)**2 + math.cos(lat1) * math.cos(lat2) * math.sin((lon2 - lon1) / 2)**2
    return 6371.0088 * 2 * math.asin(math.sqrt(min(1.0, max(0.0, h))))


def review_hotels(report: dict, census: dict) -> dict:
    """One complete core8 frontier pass; prepared evidence never means accepted."""
    result = copy.deepcopy(report)
    exact, holes = build_name_index(census)
    prior = {r['external_hotel_id']: r for group in census['routes'].values() for r in group}
    for d in result['dossiers']:
        f, geo = d['frontier'], d['geography_evidence']
        review = name_review(f['names'], f['country_id'], exact, holes)
        holds = set(review['holds']) | set(geo['holds']) | set(d['evidence_flags'])
        old = prior.get(d['external_hotel_id'])
        same = bool(old and old.get('evidence_sha256') == f['evidence_sha256'] and old.get('country_id') == f['country_id'])
        anchors = copy.deepcopy(geo['earlier_geo_anchors']) if same else []
        if geo['route'] == 'unique_dictionary_geography_support':
            anchors.extend(geo['candidate_scopes'])
        target = census['local_hotels'].get(str(review['target']))
        distances = []
        if not anchors:
            holds.add('independent_geography_missing')
        if old and old.get('route') == 'hard_conflict':
            holds.add('inherited_hard_conflict')
        if target:
            place_tokens = {t for key in ('country_name', 'region_name', 'subregion_name') for t in text_key(str(target.get(key) or '')).split()}
            if review.get('forms') and all(set(sf['tokens']) <= place_tokens for sf, lf in review['forms']):
                holds.add('geography_only_name')
            if target['country_id'] != f['country_id']:
                holds.add('target_country_conflict')
            for a in anchors:
                if a.get('country_id', f['country_id']) != f['country_id'] or a.get('scope') not in {'region', 'subregion'} or not positive(a.get('scope_id')):
                    holds.add('geography_context_invalid')
                elif target.get(a['scope'] + '_id') != a['scope_id']:
                    holds.add('target_geography_conflict')
            points = [p for raw in (old.get('points', []) if same else []) if (p := point(raw)) is not None]
            for evidence in d['retained_evidence']:
                p = point(evidence.get('hotel_fields', {}))
                if p is not None:
                    points.append(p)
            tp = point(target)
            if points and tp is None:
                holds.add('target_coordinate_missing')
            elif tp is not None:
                distances = [distance_km(p, tp) for p in points]
                if any(v > 5 for v in distances):
                    holds.add('coordinate_conflict_gt5km')
        review.update(holds=sorted(holds), effective_geo_anchors=anchors, distances_km=distances,
                      route='prepared_for_current_review' if not holds and review['route'] == 'name_proof_candidate' else 'held',
                      safe_to_write_now=False, current_validation_required=True)
        d['hotel_identity_review'] = review
    # Preserve unresolved duplicate-target uncertainty; never let iteration order win.
    occupied = Counter(d['hotel_identity_review']['target'] for d in result['dossiers']
                       if d['hotel_identity_review']['target'] is not None)
    for d in result['dossiers']:
        r = d['hotel_identity_review']
        if occupied[r['target']] > 1:
            r['holds'] = sorted(set(r['holds']) | {'duplicate_frontier_target'})
            r['route'] = 'held'
    prepared = [d for d in result['dossiers'] if d['hotel_identity_review']['route'] == 'prepared_for_current_review']
    result['identity_summary'] = {'examined': len(result['dossiers']), 'local_hotels': len(census['local_hotels']),
        'prepared_for_current_review': len(prepared), 'held': len(result['dossiers']) - len(prepared),
        'prepared_by_country': dict(sorted(Counter(d['frontier']['country_name'] for d in prepared).items())),
        'compound_prepared': sum(d['hotel_identity_review'].get('compound_segmentation', False) for d in prepared),
        'holds_overlapping': dict(sorted(Counter(h for d in result['dossiers'] for h in d['hotel_identity_review']['holds']).items()))}
    result['prepared_candidates'] = [{'external_hotel_id': d['external_hotel_id'], 'supplier_namespace': d['supplier_namespace'],
        'proposed_local_hotel_id': d['hotel_identity_review']['target'], 'evidence_sha256': d['frontier']['evidence_sha256'],
        'country_id': d['frontier']['country_id'], 'safe_to_write_now': False} for d in prepared]
    result['state'] = 'prepared_hotel_identity_evidence_only'
    result['limitations'].append('Full-frontier names/aliases reviewed without geographic prefilter. No current manual/exclusion/occupancy read or database write.')
    return result



def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dossier_zip", type=Path)
    parser.add_argument("census_zip", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--review-hotels", action="store_true", help="Review all frontier hotel names without live access")
    args = parser.parse_args()
    census = read_input(args.census_zip, "census")
    report = enrich(read_input(args.dossier_zip, "dossier"), census)
    if args.review_hotels:
        report = review_hotels(report, census)
    raw = canonical(report)
    with args.output.open("xb") as handle:
        require(handle.write(raw) == len(raw), "output_write")
    require(args.output.read_bytes() == raw, "output_readback")
    print(json.dumps({"summary": report["summary"], "identity_summary": report.get("identity_summary"), "sha256": digest(raw)}, sort_keys=True))


if __name__ == "__main__":
    main()
