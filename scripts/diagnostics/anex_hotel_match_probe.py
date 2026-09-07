"""Loaded into the existing probe namespace; no standalone side effects.

Select a bounded XML sample, inspect the same supplier IDs through Online API,
then read candidates from AnyTour's existing catalogue. Never apply mappings.
"""

import difflib
import html
import math
import unicodedata

CHECKS.update({"api_hotel_details", "reference_hotels", "catalog"})
STATUSES.update({"catalog_unavailable", "empty_sample"})
MATCH_STATUSES = {"confirmed", "probable", "ambiguous", "unmatched", "geo_conflict"}
RELATIONS = {"same_record", "id_conflict", "name_conflict", "town_conflict", "unverified"}


def norm(value):
    value = unicodedata.normalize("NFKD", html.unescape(str(value or ""))).casefold()
    value = "".join(char for char in value if not unicodedata.combining(char))
    return " ".join(re.findall(r"[^\W_]+", value, re.UNICODE))


def hotel_text(value):
    text = html.unescape(str(value or "")).strip()
    if (not 0 < len(text) <= 300 or re.search(r"[\x00-\x1f<>]|https?://|oauth_token|www\.", text, re.I)
            or any(secret and secret in text for secret in SENSITIVE_VALUES)):
        return ""
    return text


def coordinate(value, limit):
    try:
        number = float(str(value).replace(",", "."))
        return number if math.isfinite(number) and -limit <= number <= limit and number != 0 else None
    except (ValueError, TypeError):
        return None


def distance_m(first, second):
    points = [coordinate(first.get("latitude"), 90), coordinate(first.get("longitude"), 180),
              coordinate(second.get("latitude"), 90), coordinate(second.get("longitude"), 180)]
    if any(point is None for point in points):
        return None
    lat1, lon1, lat2, lon2 = map(math.radians, points)
    term = math.sin((lat2-lat1)/2)**2 + math.cos(lat1)*math.cos(lat2)*math.sin((lon2-lon1)/2)**2
    return round(6_371_000 * 2 * math.asin(min(1, math.sqrt(term))), 1)


def name_score(first, second):
    first, second = norm(first), norm(second)
    if not first or not second:
        return 0.0
    if first == second:
        return 1.0
    stop = {"hotel", "resort", "spa", "the", "and", "ex"}
    a, b = set(first.split())-stop, set(second.split())-stop
    dice = 2 * len(a & b) / (len(a)+len(b)) if a and b else 0
    return round(max(difflib.SequenceMatcher(None, first, second).ratio(), dice), 4)


def country_match(first, second):
    first, second = norm(first), norm(second)
    if not first or not second:
        return None
    if first == second:
        return True
    groups = ("turkey türkiye турция", "egypt египет", "thailand таиланд тайланд",
              "russia россия", "maldives мальдивы", "greece греция", "spain испания",
              "tunisia тунис", "vietnam вьетнам", "uae оаэ", "china китай", "india индия",
              "cuba куба", "cyprus кипр", "indonesia индонезия", "austria австрия",
              "germany германия", "italy италия", "france франция", "georgia грузия",
              "armenia армения", "abkhazia абхазия", "belarus беларусь белоруссия")
    keys = {norm(word): index for index, group in enumerate(groups) for word in group.split()}
    if first in keys and second in keys:
        return keys[first] == keys[second]
    return None


def supplier_record(data):
    return {"id": positive_id(data), "name": hotel_text(data.get("name")),
            "country": hotel_text(data.get("state")), "region": hotel_text(data.get("region")),
            "town": hotel_text(data.get("town")), "address": hotel_text(data.get("address")),
            "latitude": coordinate(data.get("latitude"), 90),
            "longitude": coordinate(data.get("longitude"), 180),
            "town_id": positive_id({"id": data.get("townKey")})}


def xml_relation(xml, api):
    if api.get("id") is None:
        return "unverified"
    if xml["id"] != api["id"]:
        return "id_conflict"
    if max(name_score(xml.get("name"), api.get("name")),
           name_score(xml.get("alternate_name"), api.get("name"))) < 0.8:
        return "name_conflict"
    if xml.get("town_id") and api.get("town_id") and xml["town_id"] != api["town_id"]:
        return "town_conflict"
    return "same_record"


def candidate_rank(api, xml, candidate):
    similarity = max(name_score(name, candidate.get("name")) for name in (
        api.get("name"), xml.get("name"), xml.get("alternate_name")))
    distance = distance_m(api, candidate)
    country = country_match(api.get("country"), candidate.get("country_name"))
    score = 0.65 * similarity + (0.1 if country is True else -0.4 if country is False else 0)
    if distance is not None:
        score += 0.3 if distance <= 200 else 0.15 if distance <= 1000 else -0.3 if distance > 5000 else 0
    return {"id": positive_id(candidate), "name": hotel_text(candidate.get("name")),
            "country": hotel_text(candidate.get("country_name")),
            "region": hotel_text(candidate.get("region_name")),
            "town": hotel_text(candidate.get("subregion_name")),
            "address": hotel_text(candidate.get("address")),
            "latitude": coordinate(candidate.get("latitude"), 90),
            "longitude": coordinate(candidate.get("longitude"), 180),
            "name_similarity": similarity, "distance_m": distance,
            "country_match": country, "score": round(score, 4)}


def classify_candidates(candidates):
    if not candidates or candidates[0]["name_similarity"] < 0.65:
        return "unmatched"
    best = candidates[0]
    if best["country_match"] is False or best["distance_m"] is not None and best["distance_m"] > 5000:
        return "geo_conflict"
    if len(candidates) > 1 and best["score"] - candidates[1]["score"] < 0.1:
        return "ambiguous"
    if best["name_similarity"] >= 0.85 and best["distance_m"] is not None and best["distance_m"] <= 200:
        return "confirmed"
    return "probable"


def read_catalog(queries):
    try:
        result = subprocess.run(["php", "-d", "display_errors=0", "-d", "log_errors=0", "-r", CATALOG_PHP],
                                input=json.dumps({"queries": queries}), text=True,
                                capture_output=True, timeout=45)
        if result.returncode or len(result.stdout) > 300_000:
            return {"status": "catalog_unavailable", "items": []}
        payload = json.loads(result.stdout)
        if payload.get("status") == "ok" and isinstance(payload.get("items"), list):
            return payload
    except (ValueError, OSError, subprocess.TimeoutExpired):
        pass
    return {"status": "catalog_unavailable", "items": []}


def remote_hotel_probe(tokens):
    global SENSITIVE_VALUES
    SENSITIVE_VALUES = tuple(value for raw in tokens.values() if isinstance(raw, str)
                             for value in (raw, raw.strip()) if value)
    checks, rows = [], []
    report = {"mode": "hotels", "checks": checks, "hotels": rows}
    stamp_result, stamp = reference_check(tokens["ANEX_REFERENCE_TOKEN"].strip(), "currentstamp")
    checks.append(stamp_result)
    if not stamp:
        return report
    result, body = request({"samo_action": "reference", "type": "hotel",
                            "laststamp": "0x0000000000000000", "delstamp": stamp},
                           tokens["ANEX_REFERENCE_TOKEN"].strip())
    result["check"] = "reference_hotels"
    checks.append(result)
    if body is None:
        return report
    try:
        if b"<!DOCTYPE" in body.upper() or b"<!ENTITY" in body.upper():
            raise ValueError("unsupported declaration")
        root = ET.fromstring(body)
        if root.tag != "Response" or root.find("Data") is None:
            raise ValueError("invalid envelope")
        hotels = [node for node in root.findall("Data/hotel") if node.get("status", "").upper() != "D"]
        result["count"] = len(hotels)
        for node in hotels:
            identifier = positive_id({"id": node.get("inc")})
            name, alternate = hotel_text(node.get("name")), hotel_text(node.get("lname"))
            if not identifier or not (name or alternate):
                continue
            xml = {"id": identifier, "name": name, "alternate_name": alternate,
                   "town_id": positive_id({"id": node.get("town")})}
            api = {}
            try:
                data = api_data(tokens["ANEX_API_TOKEN"].strip(), "Hotels_DETAILS", {"HOTELINC": identifier}, checks)
                if isinstance(data, dict):
                    api = supplier_record(data)
            except StopProbe:
                pass
            rows.append({"xml": xml, "api": api, "api_xml_relation": xml_relation(xml, api)})
            if len(rows) == 10:
                break
        if not rows:
            checks.append({"check": "selection", "status": "empty_sample"})
            return report
        queries = [{"key": row["xml"]["id"], "names": [row["api"].get("name"), row["xml"].get("name"),
                    row["xml"].get("alternate_name")], "latitude": row["api"].get("latitude"),
                    "longitude": row["api"].get("longitude")} for row in rows]
        catalog = read_catalog(queries)
        checks.append({"check": "catalog", "status": catalog["status"], "count": len(catalog["items"])})
        by_id = {item.get("key"): item.get("candidates", []) for item in catalog["items"] if isinstance(item, dict)}
        for row in rows:
            candidates = [candidate_rank(row["api"], row["xml"], candidate)
                          for candidate in by_id.get(row["xml"]["id"], []) if isinstance(candidate, dict) and positive_id(candidate)]
            candidates.sort(key=lambda candidate: candidate["score"], reverse=True)
            row["candidates"] = candidates[:3]
            row["match_status"] = classify_candidates(candidates)
    except (ET.ParseError, KeyError, TypeError, ValueError):
        checks.append({"check": "selection", "status": "invalid_response"})
    return report


def clean_hotel_report(report):
    checks = []
    for item in report.get("checks", [])[:14]:
        if item.get("check") not in CHECKS or item.get("status") not in STATUSES:
            raise ValueError("invalid check")
        clean = {"check": item["check"], "status": item["status"]}
        for key in ("count", "http_status", "elapsed_ms", "supplier_code"):
            if type(item.get(key)) is int and 0 <= item[key] <= 10_000_000:
                clean[key] = item[key]
        checks.append(clean)

    def clean_record(record):
        clean = {}
        for key in ("id", "town_id"):
            identifier = positive_id({"id": record.get(key)})
            if identifier:
                clean[key] = identifier
        for key in ("name", "alternate_name", "country", "region", "town", "address"):
            if key in record:
                clean[key] = hotel_text(record[key])
        for key, limit in (("latitude", 90), ("longitude", 180)):
            number = coordinate(record.get(key), limit)
            if number is not None:
                clean[key] = number
        return clean

    hotels = []
    for row in report.get("hotels", [])[:10]:
        clean = {"xml": clean_record(row.get("xml", {})), "api": clean_record(row.get("api", {})),
                 "api_xml_relation": row.get("api_xml_relation") if row.get("api_xml_relation") in RELATIONS else "unverified",
                 "match_status": row.get("match_status") if row.get("match_status") in MATCH_STATUSES else "unmatched",
                 "candidates": []}
        for candidate in row.get("candidates", [])[:3]:
            item = clean_record(candidate)
            for key, maximum in (("name_similarity", 1), ("distance_m", 21_000_000), ("score", 2)):
                number = candidate.get(key)
                if type(number) in (float, int) and math.isfinite(number) and -1 <= number <= maximum:
                    item[key] = number
            item["country_match"] = candidate.get("country_match") if type(candidate.get("country_match")) is bool else None
            clean["candidates"].append(item)
        hotels.append(clean)
    return {"mode": "hotels", "ok": len(hotels) == 10 and bool(checks) and all(item["status"] == "ok" for item in checks),
            "checks": checks, "hotels": hotels}
