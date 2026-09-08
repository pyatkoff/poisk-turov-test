#!/usr/bin/env python3
"""Build owner-approved preview mappings from saved catalog and geo classifications.

The 2026-09-08 owner policy includes every verified_auto and strong_candidate
identity. Availability, price evidence and short-name pilot rules do not apply.
This builder has no database access and makes no manual matching decisions.
"""

import argparse
from collections import Counter
import csv
import hashlib
import io
import json
from pathlib import Path
import re


APPROVAL_POLICY = "owner_exact_and_strong_20260908"
ROW_FIELDS = ("anex_hotel_id", "catalog_hotel_id", "match_class", "reason", "source_row_digest")


def positive_id(value):
    if type(value) is not int or not 0 < value <= 2_147_483_647:
        raise ValueError("invalid positive hotel id")
    return value


def source_digest(row):
    return hashlib.sha256(json.dumps(row, ensure_ascii=False, sort_keys=True,
                                     separators=(",", ":"), allow_nan=False).encode("utf-8")).hexdigest()


def index_rows(report, field, version):
    if (not isinstance(report, dict) or report.get("schema_version") != version
            or not isinstance(report.get(field), list) or not isinstance(report.get("counts"), dict)):
        raise ValueError("invalid source report")
    indexed = {}
    for row in report[field]:
        if not isinstance(row, dict) or not isinstance(row.get("status"), str):
            raise ValueError("invalid source row")
        identifier = positive_id(row.get("external_id"))
        if identifier in indexed:
            raise ValueError("duplicate ANEX hotel id")
        indexed[identifier] = row
    counts = Counter(row["status"] for row in indexed.values())
    statuses = set(counts) | ({"verified_auto", "review", "unmatched"} if field == "matches"
                              else {"strong_candidate", "review", "unmatched"})
    for status in statuses:
        count = report["counts"].get(status, 0)
        if type(count) is not int or count != counts[status]:
            raise ValueError("source status count mismatch")
    total = report["counts"].get("anex_hotels") if field == "matches" else report.get("processed_total")
    if type(total) is not int or total != len(indexed):
        raise ValueError("source total count mismatch")
    return indexed


def target_id(row, exact=False):
    candidates = row.get("candidates")
    if not isinstance(candidates, list) or not candidates:
        raise ValueError("missing target candidate")
    candidate_ids = [positive_id(item.get("id")) if isinstance(item, dict)
                     else positive_id(None) for item in candidates]
    target = positive_id(row.get("catalog_hotel_id")) if exact else candidate_ids[0]
    if candidate_ids.count(target) != 1:
        raise ValueError("target must occur once in source candidates")
    if not exact and row.get("catalog_hotel_id") is not None and row["catalog_hotel_id"] != target:
        raise ValueError("strong target conflicts with saved first candidate")
    return target


def build_payload(catalog, geo, sources):
    if not isinstance(sources, dict) or set(sources) != {"catalog_sha256", "geo_sha256"} or any(
            not isinstance(value, str) or not re.fullmatch(r"[0-9a-f]{64}", value)
            for value in sources.values()):
        raise ValueError("invalid source digests")
    if not isinstance(catalog, dict) or catalog.get("provider") != "anex_xml":
        raise ValueError("invalid catalog provider")
    catalog_rows = index_rows(catalog, "matches", 1)
    geo_rows = index_rows(geo, "rows", 2)
    exact_targets = {identifier: target_id(row, exact=True) for identifier, row in catalog_rows.items()
                     if row["status"] == "verified_auto"}
    if catalog["counts"].get("verified_unique_anytour") != len(set(exact_targets.values())):
        raise ValueError("source unique catalog count mismatch")
    for identifier, row in geo_rows.items():
        original = catalog_rows.get(identifier)
        if original is None:
            raise ValueError("geo ANEX identity absent from source catalog")
        if row.get("original_status") != original["status"]:
            raise ValueError("geo original status conflicts with source catalog")
        for field in ("xml", "api"):
            identity = row.get(field)
            if identity and (not isinstance(identity, dict)
                             or positive_id(identity.get("id")) != identifier):
                raise ValueError("geo XML/API identity conflict")
        if (identifier in exact_targets and row["status"] == "strong_candidate"
                and target_id(row) != exact_targets[identifier]):
            raise ValueError("geo target conflicts with older exact target")

    rows = []
    for identifier, original in sorted(catalog_rows.items()):
        # A newer geo classification takes precedence, including review/errors.
        row = geo_rows.get(identifier, original)
        if identifier in geo_rows:
            if row["status"] != "strong_candidate":
                continue
            target, match_class = target_id(row), "strong_candidate"
            reason = row.get("reason")
            if not isinstance(reason, str) or not reason.strip():
                raise ValueError("missing strong candidate reason")
        elif row["status"] == "verified_auto":
            target, match_class, reason = exact_targets[identifier], "exact", "catalog_verified_auto"
        else:
            continue
        rows.append({"anex_hotel_id": identifier, "catalog_hotel_id": target,
                     "match_class": match_class, "reason": reason, "source_row_digest": source_digest(row)})
    counts = Counter(row["match_class"] for row in rows)
    return {"schema_version": 1, "scope": "preview", "approval_policy": APPROVAL_POLICY,
            "sources": dict(sources), "counts": {"exact": counts["exact"],
            "strong": counts["strong_candidate"], "total": len(rows),
            "unique_catalog_hotels": len({row["catalog_hotel_id"] for row in rows})}, "rows": rows}


def csv_bytes(payload):
    output = io.StringIO(newline="")
    writer = csv.DictWriter(output, fieldnames=ROW_FIELDS, lineterminator="\n")
    writer.writeheader()
    writer.writerows(payload["rows"])
    return output.getvalue().encode("utf-8")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--catalog", required=True, type=Path)
    parser.add_argument("--geo", required=True, type=Path)
    parser.add_argument("--output-dir", required=True, type=Path)
    args = parser.parse_args()
    catalog_raw, geo_raw = args.catalog.read_bytes(), args.geo.read_bytes()
    payload = build_payload(json.loads(catalog_raw), json.loads(geo_raw), {
        "catalog_sha256": hashlib.sha256(catalog_raw).hexdigest(),
        "geo_sha256": hashlib.sha256(geo_raw).hexdigest()})
    encoded = (json.dumps(payload, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")
    args.output_dir.mkdir(parents=True, exist_ok=True)
    (args.output_dir / "anex-search-mappings.json").write_bytes(encoded)
    (args.output_dir / "anex-search-mappings.csv").write_bytes(csv_bytes(payload))
    print(json.dumps({"ok": True, "counts": payload["counts"],
                      "mapping_sha256": hashlib.sha256(encoded).hexdigest()}, sort_keys=True))


if __name__ == "__main__":
    main()
