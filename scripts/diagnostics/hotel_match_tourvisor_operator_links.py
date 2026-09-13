#!/usr/bin/env python3
"""MATCH #1971: convert saved Tourvisor ANEX-only search rows into operator-link evidence.

Offline/read-only. No network, credentials, database or mapping writes. Input must be a
saved one-day Tourvisor search result plus the current MATCH unresolved queue. Output is
NOT mapping authority; it only binds a Tourvisor hotel/tour/operatorLink to an expected
MATCH local hotel so the existing ANEX card/hotelCode stage can continue.
"""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
from urllib.parse import urlparse, parse_qsl

ANEX_NAMES = {"anex", "anex tour", "anextour", "анекс", "анекс тур"}
CORE8_IDS = {1, 2, 4, 8, 9, 10, 12, 16}


def canon(value) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def sha(value) -> str:
    return hashlib.sha256(canon(value).encode("utf-8")).hexdigest()


def norm_name(value) -> str:
    import re
    text = str(value or "").strip().lower().replace("ё", "е")
    text = re.sub(r"[^a-zа-я0-9]+", " ", text)
    return " ".join(text.split())


def positive_int(value):
    if isinstance(value, bool):
        return None
    try:
        out = int(str(value))
    except (TypeError, ValueError):
        return None
    return out if out > 0 else None


def safe_operator_link(value: object) -> str | None:
    if not isinstance(value, str) or value != value.strip() or len(value) > 4096:
        return None
    p = urlparse(value)
    if p.scheme != "https" or not p.hostname or p.username or p.password or p.fragment:
        return None
    host = p.hostname.lower()
    if host not in {"agent.anextour.ru", "anextour.ru", "www.anextour.ru"}:
        return None
    for key, _ in parse_qsl(p.query, keep_blank_values=True):
        if any(part in key.lower() for part in ("token", "auth", "password", "secret", "session", "signature", "api_key", "apikey")):
            return None
    return value


def operator_is_anex(operator: object) -> bool:
    if not isinstance(operator, dict):
        return False
    names = [operator.get("name"), operator.get("fullName"), operator.get("russianName")]
    return any(norm_name(name) in ANEX_NAMES for name in names if name)


def country_id(value: object) -> int | None:
    return positive_int(value.get("id") if isinstance(value, dict) else value)


def flatten_results(payload: object) -> list[dict]:
    """Expand native hotel/tours groups without replacing a child's hotel identity.

    Legacy flat saved rows remain supported. An ambiguous envelope or malformed
    group is not an empty search and must stop before producing any evidence.
    """
    if isinstance(payload, dict):
        envelopes = [payload[key] for key in ("hotels", "results", "tours", "items", "data")
                     if key in payload]
        if len(envelopes) != 1 or not isinstance(envelopes[0], list):
            raise ValueError("UNSUPPORTED_TOURVISOR_RESULTS_SHAPE")
        payload = envelopes[0]
    if not isinstance(payload, list):
        raise ValueError("UNSUPPORTED_TOURVISOR_RESULTS_SHAPE")
    rows = []
    for row in payload:
        if not isinstance(row, dict):
            continue
        if "tours" not in row:
            rows.append(row)
            continue
        if "hotel" in row or positive_int(row.get("id")) is None or not isinstance(row["tours"], list):
            raise ValueError("INVALID_TOURVISOR_HOTEL_GROUP")
        hotel = {key: value for key, value in row.items() if key != "tours"}
        for tour in row["tours"]:
            if not isinstance(tour, dict) or "hotel" in tour:
                raise ValueError("AMBIGUOUS_GROUPED_TOUR_HOTEL")
            if "country" in tour and (country_id(tour["country"]) is None
                                      or country_id(tour["country"]) != country_id(hotel.get("country"))):
                raise ValueError("GROUPED_TOUR_COUNTRY_CONFLICT")
            rows.append(dict(tour, hotel=hotel))
    return rows


def coordinates(hotel: dict) -> tuple[object, object]:
    """Keep an actual complete pair, not one latitude/longitude from each source.

    Top-level coordinates are native search evidence; common is a legacy source.
    Conflicting sources are withheld, not silently resolved by field precedence.
    """
    import math
    common = hotel.get("common") if isinstance(hotel.get("common"), dict) else {}
    pairs = []
    for source in (hotel, common):
        raw = (source.get("latitude"), source.get("longitude"))
        if all(v is None or v == "" for v in raw):
            continue
        try:
            if any(isinstance(v, bool) for v in raw):
                raise ValueError()
            lat, lon = map(float, raw)
            if not math.isfinite(lat) or not math.isfinite(lon) or not -90 <= lat <= 90 or not -180 <= lon <= 180:
                raise ValueError()
        except (ValueError, TypeError, OverflowError):
            raise ValueError("invalid_coordinates") from None
        pairs.append((raw, (lat, lon)))
    if len(pairs) == 2 and any(not math.isclose(a, b, rel_tol=0, abs_tol=1e-6)
                               for a, b in zip(pairs[0][1], pairs[1][1])):
        raise ValueError("coordinate_source_conflict")
    return pairs[0][0] if pairs else (None, None)


def build(queue: dict, tv_payload: object, expected_date: str) -> dict:
    if not isinstance(queue, dict) or not isinstance(queue.get("rows"), list):
        raise ValueError("INVALID_MATCH_QUEUE")
    import re, datetime as dt
    if not isinstance(expected_date, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", expected_date):
        raise ValueError("INVALID_EXPECTED_DATE")
    dt.date.fromisoformat(expected_date)

    targets = {}
    for row in queue["rows"]:
        if not isinstance(row, dict):
            continue
        anex_id = positive_int(row.get("anex_hotel_id"))
        local_id = positive_int(row.get("proposed_local_id") or row.get("local_hotel_id"))
        target_country_id = positive_int(row.get("country_id"))
        if anex_id is None or local_id is None:
            continue
        if target_country_id not in CORE8_IDS:
            continue
        targets.setdefault(local_id, []).append({
            "anex_hotel_id": anex_id,
            "local_hotel_id": local_id,
            "country_id": target_country_id,
            "expected_name": row.get("target_name") or row.get("hotel_name"),
            "search_count": int(row.get("search_count") or 0),
        })

    rows = flatten_results(tv_payload)
    captures = []
    rejected = []
    seen = set()
    for result in rows:
        hotel = result.get("hotel")
        operator = result.get("operator")
        if not isinstance(hotel, dict) or not operator_is_anex(operator):
            continue
        local_id = positive_int(hotel.get("id"))
        if local_id is None or local_id not in targets:
            continue
        tv_country_id = country_id(hotel.get("country"))
        result_country_id = country_id(result.get("country"))
        coordinate_error = None
        try:
            latitude, longitude = coordinates(hotel)
        except ValueError as exc:
            latitude = longitude = None
            coordinate_error = str(exc)
        link = safe_operator_link(result.get("operatorLink"))
        tour_id = str(result.get("id") or "").strip()
        for target in targets[local_id]:
            reason = None
            if (tv_country_id != target["country_id"]
                    or ("country" in result and result_country_id != tv_country_id)):
                reason = "country_conflict"
            elif coordinate_error:
                reason = coordinate_error
            elif not tour_id:
                reason = "missing_tour_id"
            elif link is None:
                reason = "missing_or_invalid_operator_link"
            key = (target["anex_hotel_id"], local_id, tour_id, link)
            if reason is None and key in seen:
                continue
            if reason is not None:
                rejected.append({"anex_hotel_id": target["anex_hotel_id"], "local_hotel_id": local_id, "reason": reason})
                continue
            seen.add(key)
            region = hotel.get("region") if isinstance(hotel.get("region"), dict) else {}
            sub = hotel.get("subRegion") if isinstance(hotel.get("subRegion"), dict) else {}
            captures.append({
                "anex_hotel_id": target["anex_hotel_id"],
                "local_hotel_id": local_id,
                "country_id": tv_country_id,
                "date_from": expected_date,
                "date_to": expected_date,
                "operator": "ANEX",
                "operator_filter": "ANEX",
                "tour_id": tour_id,
                "operator_url": link,
                "tourvisor_hotel_name": hotel.get("name"),
                "tourvisor_region_id": positive_int(region.get("id")),
                "tourvisor_region_name": region.get("name"),
                "tourvisor_subregion_id": positive_int(sub.get("id")),
                "tourvisor_subregion_name": sub.get("name"),
                "tourvisor_latitude": latitude,
                "tourvisor_longitude": longitude,
                "search_count": target["search_count"],
                "status": "operator_link_ready_for_anex_card_hotelcode",
                "not_write_authority": True,
            })

    by_anex = {}
    for capture in captures:
        by_anex.setdefault(capture["anex_hotel_id"], set()).add((capture["local_hotel_id"], capture["operator_url"]))
    ambiguous = {aid for aid, values in by_anex.items() if len(values) > 1}
    final = [row for row in captures if row["anex_hotel_id"] not in ambiguous]
    for row in captures:
        if row["anex_hotel_id"] in ambiguous:
            rejected.append({"anex_hotel_id": row["anex_hotel_id"], "local_hotel_id": row["local_hotel_id"], "reason": "ambiguous_operator_links"})

    final.sort(key=lambda r: (-r["search_count"], r["anex_hotel_id"], r["local_hotel_id"], r["tour_id"]))
    rejected.sort(key=lambda r: (r["anex_hotel_id"], r["local_hotel_id"], r["reason"]))
    return {
        "schema": "hotel-match-tourvisor-operator-links/1",
        "status": "prepared_only",
        "not_write_authority": True,
        "database_writes": 0,
        "mapping_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "expected_date": expected_date,
        "source_queue_sha256": sha(queue),
        "source_results_sha256": sha(tv_payload),
        "input_result_rows": len(rows),
        "queue_targets": sum(len(v) for v in targets.values()),
        "operator_link_ready": len(final),
        "ambiguous_anex_ids": len(ambiguous),
        "rejected_count": len(rejected),
        "captures_sha256": sha(final),
        "captures": final,
        "rejected": rejected,
    }


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--queue", required=True)
    p.add_argument("--tourvisor-results", required=True)
    p.add_argument("--date", required=True)
    p.add_argument("--output", required=True)
    args = p.parse_args()
    queue = json.loads(Path(args.queue).read_text(encoding="utf-8"))
    tv = json.loads(Path(args.tourvisor_results).read_text(encoding="utf-8"))
    report = build(queue, tv, args.date)
    with Path(args.output).open("x", encoding="utf-8") as fh:
        fh.write(json.dumps(report, ensure_ascii=False, sort_keys=True, indent=2) + "\n")
    print(json.dumps({k: report[k] for k in ("operator_link_ready", "ambiguous_anex_ids", "rejected_count", "captures_sha256")}, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
