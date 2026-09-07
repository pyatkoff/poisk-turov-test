"""Full ANEX reference download and conservative read-only AnyTour matching."""
import csv
import difflib
import io
import unicodedata

CATALOG_PAGE_LIMIT = 500
CATALOG_MAX_PAGES = 300
CATALOG_ALLOWED_FIELDS = {
    "hotel": ("inc", "name", "lname", "town", "star", "status", "stamp"),
    "town": ("inc", "name", "lname", "state", "status", "stamp"),
    "state": ("inc", "name", "lname", "status", "stamp"),
    "star": ("inc", "name", "lname", "status", "stamp"),
}


def catalog_text(value, limit=300):
    value = str(value or "").strip()
    if (not value or len(value) > limit or re.search(r"[\x00-\x1f\x7f]|https?:|oauth_token|[<>]", value, re.I)
            or any(secret and secret in value for secret in SENSITIVE_VALUES)):
        return ""
    return value


def catalog_stamp(value):
    value = str(value or "")
    if not re.fullmatch(r"0x[0-9A-Fa-f]{16}", value):
        return None
    return int(value[2:], 16)


def catalog_page(token, kind, laststamp, delstamp):
    result, body = request({"samo_action": "reference", "type": kind,
                            "laststamp": laststamp, "delstamp": delstamp}, token)
    if result.get("status") != "ok" or body is None:
        raise StopProbe()
    if b"<!DOCTYPE" in body.upper() or b"<!ENTITY" in body.upper():
        raise ValueError("unsupported XML")
    root = ET.fromstring(body)
    data = root.find("Data")
    if root.tag != "Response" or data is None or any(node.tag.lower() == "error" for node in root.iter()):
        raise ValueError("invalid XML")
    nodes = data.findall(kind)
    rows = []
    stamps = []
    for node in nodes:
        item = {key: catalog_text(node.get(key), 600 if key in ("name", "lname") else 120)
                for key in CATALOG_ALLOWED_FIELDS[kind] if node.get(key) is not None}
        stamp = catalog_stamp(node.get("stamp"))
        if stamp is not None:
            stamps.append(stamp)
        rows.append(item)
    cursor_candidates = stamps
    for node in (data, root):
        for key in ("laststamp", "stamp", "nextstamp"):
            stamp = catalog_stamp(node.get(key))
            if stamp is not None:
                cursor_candidates.append(stamp)
    return rows, max(cursor_candidates) if cursor_candidates else None


def catalog_all(token, kind, delstamp):
    cursor = "0x0000000000000000"
    cursor_int = 0
    rows, seen = [], set()
    for page in range(1, CATALOG_MAX_PAGES + 1):
        batch, next_cursor = catalog_page(token, kind, cursor, delstamp)
        new_count = 0
        for item in batch:
            key = item.get("inc") or (item.get("town", "") + ":" + item.get("state", ""))
            if not key or key in seen:
                continue
            seen.add(key)
            rows.append(item)
            new_count += 1
        if len(batch) < CATALOG_PAGE_LIMIT:
            return rows, page
        if next_cursor is None or next_cursor <= cursor_int or new_count == 0:
            raise ValueError("catalog pagination unavailable")
        cursor_int = next_cursor
        cursor = "0x%016X" % cursor_int
    raise ValueError("catalog page limit")


def catalog_static(token, kind):
    result, body = request({"samo_action": "reference", "type": kind}, token)
    if result.get("status") != "ok" or body is None:
        raise StopProbe()
    root = ET.fromstring(body)
    data = root.find("Data")
    if root.tag != "Response" or data is None:
        raise ValueError("invalid XML")
    return [{key: catalog_text(node.get(key), 120) for key in ("town", "state")}
            for node in data.findall(kind)]


def catalog_norm(value):
    value = unicodedata.normalize("NFKD", str(value or "").casefold())
    value = "".join(ch for ch in value if not unicodedata.combining(ch))
    value = re.sub(r"\b(?:ex|former|formerly|hotel|hotels|resort|spa|the|отель|бывш)\b", " ", value)
    return " ".join(re.findall(r"[\w]+", value, re.UNICODE))


def catalog_country(value):
    value = catalog_norm(value)
    aliases = {
        "россия": "russia", "russian federation": "russia", "russia": "russia",
        "турция": "turkey", "turkiye": "turkey", "turkey": "turkey",
        "египет": "egypt", "egypt": "egypt",
        "оаэ": "uae", "united arab emirates": "uae", "uae": "uae",
        "таиланд": "thailand", "thailand": "thailand",
    }
    return aliases.get(value, value)


def catalog_town_match(supplier, local):
    supplier = catalog_norm(supplier)
    local = catalog_norm(local)
    if not supplier or not local:
        return False
    if supplier == local:
        return True
    left, right = set(supplier.split()), set(local.split())
    return bool(left and right and (left <= right or right <= left))


def catalog_similarity(left, right):
    left, right = catalog_norm(left), catalog_norm(right)
    return difflib.SequenceMatcher(None, left, right).ratio() if left and right else 0.0


def catalog_local_data():
    hotels, seen = [], set()
    after_id = 0
    for _page in range(100):
        payload = json.dumps({"after_id": after_id, "limit": 5000}, separators=(",", ":"))
        completed = subprocess.run(
            ["php", "-d", "display_errors=0", "-d", "log_errors=0", "-r", CATALOG_BULK_PHP],
            input=payload, text=True, capture_output=True, timeout=60,
        )
        if completed.returncode or not completed.stdout or len(completed.stdout) > 12_582_912:
            raise StopProbe()
        data = json.loads(completed.stdout)
        batch = data.get("hotels")
        next_after_id = data.get("next_after_id")
        if (data.get("status") != "ok" or not isinstance(batch, list)
                or not isinstance(data.get("has_more"), bool)
                or not isinstance(next_after_id, int) or next_after_id < after_id):
            raise StopProbe()
        for hotel in batch:
            hotel_id = positive_id(hotel)
            if hotel_id is None or hotel_id in seen or hotel_id <= after_id:
                raise StopProbe()
            seen.add(hotel_id)
            hotels.append(hotel)
        if not data["has_more"]:
            return hotels
        if not batch or next_after_id <= after_id:
            raise StopProbe()
        after_id = next_after_id
    raise StopProbe()


def catalog_candidate(local, score):
    return {"id": positive_id(local), "name": catalog_text(local.get("name"), 600),
            "country": catalog_text(local.get("country_name")),
            "region": catalog_text(local.get("region_name")),
            "town": catalog_text(local.get("subregion_name")), "score": round(score, 4)}


def match_catalog(hotels, towns, states, townstates, local_hotels):
    state_names = {row.get("inc"): row.get("name", "") for row in states}
    town_names = {row.get("inc"): row.get("name", "") for row in towns}
    town_state = {row.get("town"): row.get("state") for row in townstates}
    exact, tokens = {}, {}
    for local in local_hotels:
        for value in (local.get("name"), local.get("normalized_name")):
            name = catalog_norm(value)
            if name:
                exact.setdefault(name, {})[local["id"]] = local
                for token in {word for word in name.split() if len(word) >= 4}:
                    if len(tokens.setdefault(token, {})) < 2000:
                        tokens[token][local["id"]] = local
    output = []
    for hotel in hotels:
        external_id = positive_id({"id": hotel.get("inc")})
        if external_id is None:
            continue
        name, alternate = catalog_text(hotel.get("name"), 600), catalog_text(hotel.get("lname"), 600)
        status = "deleted" if hotel.get("status", "").upper() == "D" else "active"
        town_id = str(hotel.get("town", ""))
        supplier_town = town_names.get(town_id, "")
        supplier_country = state_names.get(town_state.get(town_id), "")
        names = {catalog_norm(value) for value in (name, alternate) if catalog_norm(value)}
        candidate_map = {}
        for normalized in names:
            candidate_map.update(exact.get(normalized, {}))
        ranked = []
        for local in candidate_map.values():
            same_country = bool(supplier_country) and catalog_country(supplier_country) == catalog_country(local.get("country_name"))
            same_town = catalog_town_match(supplier_town, local.get("subregion_name")) or catalog_town_match(supplier_town, local.get("region_name"))
            similarity = max(catalog_similarity(value, local.get("name")) for value in (name, alternate))
            ranked.append((1.0 + (0.2 if same_country else -0.3) + (0.2 if same_town else 0), local, same_country, same_town, similarity))
        if not ranked and status == "active":
            pool = {}
            words = sorted({word for normalized in names for word in normalized.split() if len(word) >= 4}, key=len, reverse=True)[:3]
            for word in words:
                pool.update(tokens.get(word, {}))
            for local in pool.values():
                same_country = bool(supplier_country) and catalog_country(supplier_country) == catalog_country(local.get("country_name"))
                if supplier_country and not same_country:
                    continue
                similarity = max(catalog_similarity(value, local.get("name")) for value in (name, alternate))
                same_town = catalog_town_match(supplier_town, local.get("subregion_name")) or catalog_town_match(supplier_town, local.get("region_name"))
                if similarity >= 0.72:
                    ranked.append((0.65 * similarity + (0.2 if same_country else 0) + (0.15 if same_town else 0), local, same_country, same_town, similarity))
        ranked.sort(key=lambda item: (-item[0], item[1]["id"]))
        exact_geo = [item for item in ranked if item[4] >= 0.999 and item[2] and item[3]]
        match_status = "deleted" if status == "deleted" else "verified_auto" if len(exact_geo) == 1 else "review" if ranked else "unmatched"
        chosen = exact_geo[0][1]["id"] if match_status == "verified_auto" else None
        output.append({"provider": "anex_xml", "external_id": external_id, "name": name,
                       "alternate_name": alternate, "country": catalog_text(supplier_country),
                       "town": catalog_text(supplier_town), "status": match_status,
                       "catalog_hotel_id": chosen,
                       "candidates": [catalog_candidate(item[1], item[0]) for item in ranked[:3]]})
    return output


def remote_full_catalog_probe(tokens):
    global SENSITIVE_VALUES
    SENSITIVE_VALUES = tuple(value for raw in tokens.values() if isinstance(raw, str)
                             for value in (raw, raw.strip()) if value)
    token = tokens.get("ANEX_REFERENCE_TOKEN", "").strip()
    if not token:
        return {"mode": "full_catalog", "ok": False, "status": "missing_secret"}
    stage = "currentstamp"
    progress = {}
    try:
        stamp_result, stamp = reference_check(token, "currentstamp")
        if stamp_result.get("status") != "ok" or not stamp:
            raise StopProbe()
        stage = "states"
        states, state_pages = catalog_all(token, "state", stamp)
        progress["states"] = len(states)
        stage = "towns"
        towns, town_pages = catalog_all(token, "town", stamp)
        progress["towns"] = len(towns)
        stage = "stars"
        stars, star_pages = catalog_all(token, "star", stamp)
        progress["stars"] = len(stars)
        stage = "hotels"
        hotels, hotel_pages = catalog_all(token, "hotel", stamp)
        progress["hotels"] = len(hotels)
        stage = "townstate"
        townstates = catalog_static(token, "townstate")
        progress["townstate"] = len(townstates)
        stage = "anytour_catalog"
        local_hotels = catalog_local_data()
        progress["anytour_hotels"] = len(local_hotels)
        stage = "matching"
        matches = match_catalog(hotels, towns, states, townstates, local_hotels)
        counts = {key: sum(row["status"] == key for row in matches)
                  for key in ("verified_auto", "review", "unmatched", "deleted")}
        return {"mode": "full_catalog", "ok": True, "status": "ok", "reference_stamp": stamp,
                "counts": {"anex_hotels": len(matches), "anytour_hotels": len(local_hotels),
                           "states": len(states), "towns": len(towns), "stars": len(stars),
                           "townstate_links": len(townstates), **counts},
                "pages": {"hotels": hotel_pages, "towns": town_pages, "states": state_pages, "stars": star_pages},
                "matches": matches}
    except StopProbe:
        return {"mode": "full_catalog", "ok": False, "status": "catalog_unavailable", "failed_stage": stage, "progress": progress}
    except (ET.ParseError, ValueError, TypeError, KeyError, subprocess.TimeoutExpired):
        return {"mode": "full_catalog", "ok": False, "status": "invalid_catalog", "failed_stage": stage, "progress": progress}


def save_full_catalog_artifacts(report, directory):
    directory = Path(directory)
    directory.mkdir(parents=True, exist_ok=True)
    safe = {"schema_version": 1, "provider": "anex_xml", "generated_at": dt.datetime.now(dt.timezone.utc).isoformat(),
            "reference_stamp": report["reference_stamp"], "counts": report["counts"], "pages": report["pages"],
            "matches": report["matches"]}
    (directory / "anex-hotel-catalog-match.json").write_text(json.dumps(safe, ensure_ascii=False, indent=2) + "\n")
    with (directory / "anex-hotel-review.csv").open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["anex_id", "anex_name", "country", "town", "status", "candidate_id", "candidate_name", "candidate_country", "candidate_region", "candidate_town", "score"])
        for row in report["matches"]:
            if row["status"] not in ("review", "unmatched"):
                continue
            candidates = row["candidates"] or [{}]
            for candidate in candidates:
                writer.writerow([row["external_id"], row["name"], row["country"], row["town"], row["status"],
                                 candidate.get("id", ""), candidate.get("name", ""), candidate.get("country", ""),
                                 candidate.get("region", ""), candidate.get("town", ""), candidate.get("score", "")])
    with (directory / "anex-hotel-verified.csv").open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["provider", "external_hotel_id", "catalog_hotel_id", "status", "anex_name", "country", "town"])
        for row in report["matches"]:
            if row["status"] == "verified_auto":
                writer.writerow([row["provider"], row["external_id"], row["catalog_hotel_id"], row["status"], row["name"], row["country"], row["town"]])


def catalog_summary(report):
    if not report.get("ok"):
        return {"mode": "full_catalog", "ok": False, "status": report.get("status", "invalid_catalog"),
                "failed_stage": report.get("failed_stage", "unknown"), "progress": report.get("progress", {})}
    return {"mode": "full_catalog", "ok": True, "status": "ok", "counts": report["counts"], "pages": report["pages"]}
