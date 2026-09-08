#!/usr/bin/env python3
"""Offline shortlist of ANEX IDs with observed offers; never accepts mappings."""

import argparse
from collections import Counter
import csv
import hashlib
import json
import math
from pathlib import Path

MATCH_STATUSES = ("exact", "strong_candidate", "review", "errors", "unmatched", "unknown")
PRICE_STATUSES = ("offer_seen", "no_offer", "probe_unavailable")
SEARCH_KEYS = {
    "departure", "departure_id", "destination", "destination_id", "currency",
    "currency_id", "checkin_begin", "checkin_end", "nights_from", "nights_till",
    "adults", "children", "requested_hotels", "price_page",
}


def text(value, limit=200):
    return " ".join(value.split())[:limit] if isinstance(value, str) else ""


def valid_id(value):
    return type(value) is int and 1 <= value <= 999_999_999


def indexed(rows, label):
    if not isinstance(rows, list):
        raise ValueError("invalid " + label)
    result = {}
    for row in rows:
        identifier = row.get("external_id") if isinstance(row, dict) else None
        if not valid_id(identifier) or identifier in result:
            raise ValueError("invalid or duplicate ID in " + label)
        result[identifier] = row
    return result


def candidates(row):
    values = row.get("candidates", [])
    if not isinstance(values, list):
        raise ValueError("invalid candidates")
    seen, result = set(), []
    for item in values:
        identifier = item.get("id") if isinstance(item, dict) else None
        if not valid_id(identifier) or identifier in seen:
            raise ValueError("invalid or duplicate candidate ID")
        seen.add(identifier)
        compact = {"catalog_hotel_id": identifier}
        compact.update({key: text(item.get(key)) for key in ("name", "country", "town", "region")})
        for key in ("score", "distance_m"):
            value = item.get(key)
            compact[key] = value if type(value) in (int, float) and math.isfinite(value) else None
        if len(result) < 5:
            result.append(compact)
    target = row.get("catalog_hotel_id")
    if target is not None and (not valid_id(target) or target not in seen):
        raise ValueError("catalog target is absent from source candidates")
    return result, len(values)


def effective_status(base, enriched):
    row = enriched or base
    status = row.get("status")
    if status == "verified_auto" and enriched is None:
        return "exact", "catalog_verified_auto_rule"
    if status == "review" and row.get("reason") == "details_unavailable":
        return "errors", "details_unavailable"
    return (status if status in MATCH_STATUSES else "unknown"), text(row.get("reason"))


def build_report(catalog, geo, checkpoint, sources=None):
    if not all(isinstance(value, dict) for value in (catalog, geo, checkpoint)):
        raise ValueError("expected JSON objects")
    if (checkpoint.get("schema_version") != 1
            or checkpoint.get("mode") != "price_evidence_checkpoint"
            or checkpoint.get("decision_policy") != "diagnostic_only"):
        raise ValueError("invalid price checkpoint")
    catalog_rows = indexed(catalog.get("matches"), "catalog")
    geo_rows = indexed(geo.get("rows"), "geo")
    price_rows = indexed(checkpoint.get("rows"), "checkpoint")
    if not set(geo_rows) <= set(catalog_rows) or not set(price_rows) <= set(catalog_rows):
        raise ValueError("source ID absent from full catalog")
    full_counts, exact_targets = Counter(), set()
    for identifier, base in catalog_rows.items():
        candidates(base)
        enriched = geo_rows.get(identifier)
        if enriched is not None:
            candidates(enriched)
        status, _ = effective_status(base, enriched)
        full_counts[status] += 1
        if base.get("status") == "verified_auto":
            target = base.get("catalog_hotel_id")
            if not valid_id(target):
                raise ValueError("exact candidate missing target")
            exact_targets.add(target)
    rows, price_counts, deferred_reasons = [], Counter(), Counter()
    for identifier, evidence in price_rows.items():
        status = evidence.get("status")
        if status not in PRICE_STATUSES:
            raise ValueError("invalid checkpoint status")
        price_counts[status] += 1
        if status == "probe_unavailable":
            deferred_reasons[text(evidence.get("reason")) or "unknown"] += 1
        if status != "offer_seen":
            continue
        offers = evidence.get("offer_count")
        if type(offers) is not int or not 1 <= offers <= 10_000:
            raise ValueError("offer_seen requires positive offer count")
        base, enriched = catalog_rows[identifier], geo_rows.get(identifier)
        match_status, reason = effective_status(base, enriched)
        candidate_rows, candidate_count = candidates(enriched or base)
        supplier_details = enriched.get("api", {}) if enriched else {}
        search = evidence.get("search")
        scope = {key: text(value) if isinstance(value, str) else value
                 for key, value in search.items()
                 if key in SEARCH_KEYS and type(value) in (str, int, bool)} if isinstance(search, dict) else {}
        rows.append({
            "external_id": identifier,
            "supplier_name": text(evidence.get("hotel") or base.get("name")),
            "country": text(evidence.get("destination") or base.get("country")),
            "supplier_town": text(supplier_details.get("town") or base.get("town")),
            "supplier_region": text(supplier_details.get("region")),
            "matching_status": match_status,
            "matching_reason": reason,
            "matching_source": "geo" if enriched is not None else "catalog",
            "mapping_decision": "not_accepted_by_export",
            "candidate_count": candidate_count,
            "candidates": candidate_rows,
            "availability_status": "offer_seen",
            "offer_count": offers,
            "observed_at": text(evidence.get("last_checked_at")) or None,
            "scope_status": "recorded_search_only" if scope else "unknown",
            "search": scope,
            "evidence_scope": text(evidence.get("evidence_scope")) or "unknown",
            "external_results_not_loaded": evidence.get("external_results_not_loaded")
            if type(evidence.get("external_results_not_loaded")) is bool else None,
            "global_availability_known": False,
        })
    rows.sort(key=lambda row: (MATCH_STATUSES.index(row["matching_status"]), row["external_id"]))
    return {
        "schema_version": 1,
        "mode": "sales_priority_shortlist",
        "decision_policy": "diagnostic_only",
        "as_of": text(checkpoint.get("updated_at")) or None,
        "sources": dict(sources or {}),
        "coverage": "observed_checkpoint_offers_only; not a full inventory of hotels on sale",
        "no_offer_policy": "retain catalog; no offer observed under tested conditions, not globally unsellable",
        "mapping_policy": "candidate IDs are proposals; accepted decisions were not read or changed",
        "counts": {
            "full_catalog_ids": len(catalog_rows),
            "catalog_verified_auto": sum(row.get("status") == "verified_auto" for row in catalog_rows.values()),
            "catalog_verified_unique_anytour_candidates": len(exact_targets),
            "geo_processed_ids": len(geo_rows),
            "full_catalog_effective_matching": {key: full_counts[key] for key in MATCH_STATUSES},
            "checkpoint_completed_ids": len(price_rows),
            "checkpoint_checked_ids": price_counts["offer_seen"] + price_counts["no_offer"],
            "checkpoint_deferred_ids": price_counts["probe_unavailable"],
            **{key: price_counts[key] for key in PRICE_STATUSES},
            "deferred_by_reason": dict(sorted(deferred_reasons.items())),
            "priority_ids": len(rows),
            "priority_by_matching_status": {key: sum(row["matching_status"] == key for row in rows) for key in MATCH_STATUSES},
            "priority_unknown_search_scope": sum(row["scope_status"] == "unknown" for row in rows),
            "catalog_ids_without_checkpoint": len(catalog_rows) - len(price_rows),
        },
        "rows": rows,
    }


def csv_safe(value):
    value = str(value) if value is not None else ""
    return "'" + value if value.lstrip().startswith(("=", "+", "-", "@")) else value


def write_outputs(report, output_dir):
    target = Path(output_dir)
    target.mkdir(parents=True, exist_ok=True)
    (target / "anex-sales-priority.json").write_text(
        json.dumps(report, ensure_ascii=False, indent=2, allow_nan=False) + "\n", encoding="utf-8")
    columns = ("external_id", "supplier_name", "country", "supplier_town", "matching_status", "matching_reason",
               "candidate_ids", "candidate_count", "offer_count", "observed_at", "scope_status",
               "mapping_decision")
    with (target / "anex-sales-priority.csv").open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=columns)
        writer.writeheader()
        for row in report["rows"]:
            output = {key: csv_safe(row.get(key)) for key in columns}
            output["candidate_ids"] = ",".join(str(item["catalog_hotel_id"]) for item in row["candidates"])
            writer.writerow(output)
    counts = report["counts"]
    lines = ["# ANEX: приоритет по наблюдавшимся предложениям", "",
             "Это диагностическая очередь кандидатов. Принятые связи не читались и не изменялись.", "",
             f"Предложения наблюдались у **{counts['priority_ids']}** ANEX ID; это не полный список продающихся отелей.",
             f"Контрольная точка: {counts['checkpoint_checked_ids']} проверено, {counts['checkpoint_deferred_ids']} отложено.",
             f"Без наблюдавшихся предложений: {counts['no_offer']}. Отели сохраняются в каталоге; отсутствие относится только к проверенным условиям.",
             f"У {counts['priority_unknown_search_scope']} приоритетных ID условия старого поиска не сохранены: scope unknown.", "",
             "| Категория | В полном каталоге с учётом обогащения | С наблюдавшимися предложениями |",
             "| --- | ---: | ---: |"]
    lines.extend(f"| {key} | {counts['full_catalog_effective_matching'][key]} | {counts['priority_by_matching_status'][key]} |" for key in MATCH_STATUSES)
    lines.extend(["", "| ANEX ID | Отель | Курорт ANEX | Кандидаты AnyTour (до 5) |",
                  "| ---: | --- | --- | --- |"])
    for row in report["rows"]:
        # Render supplier text as text, not executable HTML or Markdown links.
        def cell(value):
            return text(value).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("|", "&#124;").replace("[", "&#91;").replace("]", "&#93;")
        candidate_ids = ", ".join(str(item["catalog_hotel_id"]) for item in row["candidates"])
        lines.append(f"| {row['external_id']} | {cell(row['supplier_name'])} | {cell(row['supplier_town'])} | {candidate_ids} |")
    lines.extend(["", f"Исходное правило verified_auto: {counts['catalog_verified_auto']} ANEX ID, {counts['catalog_verified_unique_anytour_candidates']} уникальных кандидатов AnyTour; это не число принятых связей.",
                  "", "Полный список с ANEX ID, кандидатами AnyTour и временем наблюдения сохранён в соседних JSON/CSV.",
                  "", "SHA-256 входных файлов:", ""])
    lines.extend(f"- {key}: `{value}`" for key, value in sorted(report["sources"].items()))
    (target / "anex-sales-priority.md").write_text("\n".join(lines) + "\n", encoding="utf-8")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("catalog", "geo", "checkpoint", "output-dir"):
        parser.add_argument("--" + name, required=True)
    args = parser.parse_args()
    paths = {key: Path(getattr(args, key)) for key in ("catalog", "geo", "checkpoint")}
    output = Path(args.output_dir)
    if any(path.resolve() == (output / name).resolve() for path in paths.values()
           for name in ("anex-sales-priority.json", "anex-sales-priority.csv", "anex-sales-priority.md")):
        raise ValueError("output would overwrite an input")
    raw = {key: path.read_bytes() for key, path in paths.items()}
    inputs = {key: json.loads(value) for key, value in raw.items()}
    sources = {key + "_sha256": hashlib.sha256(value).hexdigest() for key, value in raw.items()}
    report = build_report(**inputs, sources=sources)
    write_outputs(report, output)
    print(json.dumps(report["counts"], ensure_ascii=False, sort_keys=True))


if __name__ == "__main__":
    main()
