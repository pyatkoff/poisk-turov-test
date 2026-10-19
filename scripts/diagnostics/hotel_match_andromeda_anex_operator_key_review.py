#!/usr/bin/env python3
"""Read-only MATCH evidence extractor for saved Andromeda ANEX price JSON.

No network, no DB, no writes outside the requested output file.
`original.hotelKey` is treated as an observed operator hotel key only when the row
is ANEX (operatorKey=5). `isOperatorHotelKey=1` rows are quarantined because the
top-level hotelKey is already operator-scoped and catalog bridging semantics differ.
"""
from __future__ import annotations
import argparse, hashlib, json, re
from pathlib import Path

IMG = re.compile(r"/(\d+)\.(\d+)\.(\d+)\.jpg(?:\?|$)")


def review(doc: dict) -> dict:
    rows = list((doc.get("payload") or {}).get("PRICES") or [])
    out = []
    counts = {
        "rows": len(rows), "anex_rows": 0, "direct_bridge_rows": 0,
        "operator_key_rows_quarantined": 0, "image_pattern_rows": 0,
        "image_operator_key_matches": 0, "image_andromeda_key_matches": 0,
        "hotel_url_rows": 0,
    }
    for row in rows:
        if str(row.get("operatorKey")) != "5" or str(row.get("operator", "")).casefold() != "anex tour":
            continue
        counts["anex_rows"] += 1
        original = row.get("original") if isinstance(row.get("original"), dict) else {}
        operator_hotel_key = original.get("hotelKey")
        andromeda_hotel_key = row.get("hotelKey")
        operator_scoped = int(row.get("isOperatorHotelKey") or 0) == 1
        image = str(row.get("hotelImage") or "")
        url = str(row.get("hotelUrl") or "")
        m = IMG.search(image)
        if url:
            counts["hotel_url_rows"] += 1
        evidence = {
            "hotel": row.get("hotel"), "town": row.get("town"), "star": row.get("star"),
            "andromeda_hotel_key": andromeda_hotel_key,
            "anex_operator_hotel_key": operator_hotel_key,
            "hotel_url": url or None, "hotel_image": image or None,
            "is_operator_hotel_key": operator_scoped,
            "status": "quarantine_operator_scoped" if operator_scoped else "direct_operator_bridge",
        }
        if operator_scoped:
            counts["operator_key_rows_quarantined"] += 1
        elif operator_hotel_key not in (None, "") and andromeda_hotel_key not in (None, ""):
            counts["direct_bridge_rows"] += 1
        if m:
            counts["image_pattern_rows"] += 1
            image_operator, image_anex, image_and = map(int, m.groups())
            evidence["image_parts"] = {"operator": image_operator, "middle": image_anex, "last": image_and}
            if operator_hotel_key not in (None, "") and image_anex == int(operator_hotel_key):
                counts["image_operator_key_matches"] += 1
                evidence["image_confirms_anex_key"] = True
            if andromeda_hotel_key not in (None, "") and image_and == int(andromeda_hotel_key):
                counts["image_andromeda_key_matches"] += 1
                evidence["image_confirms_andromeda_key"] = True
        out.append(evidence)
    return {
        "schema_version": 1,
        "lane": "MATCH",
        "method": "saved_andromeda_anex_price_original_hotel_key",
        "counts": counts,
        "rows": out,
        "database_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "acceptance_note": "Evidence only. Current DB/manual/exclusion/conflict guards and direct ANEX/Tourvisor corroboration remain required before mapping write.",
    }


def main() -> None:
    p = argparse.ArgumentParser()
    p.add_argument("input", type=Path)
    p.add_argument("output", type=Path)
    a = p.parse_args()
    raw = a.input.read_bytes()
    doc = json.loads(raw)
    result = review(doc)
    result["input_sha256"] = hashlib.sha256(raw).hexdigest()
    a.output.write_text(json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2) + "\n")
    print(json.dumps(result["counts"], sort_keys=True))


if __name__ == "__main__":
    main()
