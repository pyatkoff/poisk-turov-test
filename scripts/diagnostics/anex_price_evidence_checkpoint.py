#!/usr/bin/env python3
"""Merge one bounded ANEX price-evidence report into a resumable checkpoint."""

import argparse
import datetime as dt
import json
import os
from pathlib import Path
import tempfile

MAX_HOTELS = 30
MAX_ID = 999_999_999
FAILURE_STATUSES = {
    "http_error", "network_error", "tls_error", "timeout", "response_too_large",
    "invalid_response", "supplier_error", "no_departures", "no_destinations",
    "no_dates", "no_currency", "no_nights", "no_prices", "request_limit",
    "ssh_failed", "ssh_timeout", "unexpected_probe_failure",
}
STATUSES = {"offer_seen", "no_offer", "probe_unavailable"}


def utc_now():
    return dt.datetime.now(dt.timezone.utc).isoformat()


def bounded_text(value, limit=200):
    if not isinstance(value, str):
        return ""
    return " ".join(value.split())[:limit]


def valid_id(value):
    return type(value) is int and 1 <= value <= MAX_ID


def unique_ids(values, label):
    if not isinstance(values, list) or len(values) > MAX_HOTELS:
        raise ValueError("invalid " + label)
    if any(not valid_id(value) for value in values) or len(set(values)) != len(values):
        raise ValueError("invalid " + label)
    return values


def empty_checkpoint():
    return {
        "schema_version": 1,
        "mode": "price_evidence_checkpoint",
        "decision_policy": "diagnostic_only",
        "rows": [],
    }


def load_checkpoint(path):
    target = Path(path)
    if not target.exists():
        return empty_checkpoint()
    value = json.loads(target.read_text(encoding="utf-8"))
    if (not isinstance(value, dict) or value.get("schema_version") != 1
            or value.get("mode") != "price_evidence_checkpoint"
            or value.get("decision_policy") != "diagnostic_only"
            or not isinstance(value.get("rows"), list)):
        raise ValueError("invalid price evidence checkpoint")
    seen = set()
    for row in value["rows"]:
        if (not isinstance(row, dict) or not valid_id(row.get("external_id"))
                or row.get("status") not in STATUSES or row["external_id"] in seen):
            raise ValueError("invalid price evidence checkpoint row")
        if (row["status"] == "probe_unavailable"
                and row.get("reason") not in FAILURE_STATUSES):
            raise ValueError("invalid price evidence failure reason")
        seen.add(row["external_id"])
    return value


def validate_report(report):
    if (not isinstance(report, dict) or report.get("mode") != "price_evidence"
            or report.get("ok") is not True):
        raise ValueError("invalid price evidence report")
    requested = unique_ids(report.get("requested_hotel_ids"), "requested hotel ids")
    if not requested:
        raise ValueError("empty price evidence report")
    returned = unique_ids(report.get("returned_hotel_ids"), "returned hotel ids")
    missing = unique_ids(report.get("missing_hotel_ids"), "missing hotel ids")
    requested_set, returned_set, missing_set = set(requested), set(returned), set(missing)
    if (returned_set & missing_set or returned_set | missing_set != requested_set
            or not returned_set <= requested_set):
        raise ValueError("price evidence ids do not partition requested ids")
    search = report.get("search")
    destination = bounded_text(search.get("destination")) if isinstance(search, dict) else ""
    if not destination or search.get("requested_hotels") != len(requested):
        raise ValueError("invalid price evidence search")
    evidence_by_id = {}
    evidence = report.get("evidence")
    if not isinstance(evidence, list) or len(evidence) > MAX_HOTELS:
        raise ValueError("invalid price evidence")
    for item in evidence:
        identifier = item.get("external_id") if isinstance(item, dict) else None
        offers = item.get("offer_count") if isinstance(item, dict) else None
        if (identifier not in returned_set or identifier in evidence_by_id
                or type(offers) is not int or not 1 <= offers <= 10_000):
            raise ValueError("invalid price evidence item")
        evidence_by_id[identifier] = {
            "offer_count": offers,
            "hotel": bounded_text(item.get("hotel")),
            "star": bounded_text(item.get("star"), 80),
            "rooms": [bounded_text(value) for value in item.get("rooms", [])[:5]
                      if bounded_text(value)],
            "meals": [bounded_text(value) for value in item.get("meals", [])[:5]
                      if bounded_text(value)],
        }
    if set(evidence_by_id) != returned_set:
        raise ValueError("price evidence does not cover returned ids")
    return requested, returned_set, destination, evidence_by_id


def validate_failure(report, queue):
    if (not isinstance(report, dict) or report.get("mode") != "price_evidence"
            or report.get("ok") is not False or not isinstance(report.get("checks"), list)):
        raise ValueError("invalid failed price evidence report")
    reasons = [item.get("status") for item in report["checks"]
               if isinstance(item, dict) and item.get("status") != "ok"]
    if not reasons or reasons[-1] not in FAILURE_STATUSES:
        raise ValueError("invalid failed price evidence status")
    if (not isinstance(queue, dict) or queue.get("schema_version") != 1
            or queue.get("mode") != "price_evidence_queue"
            or queue.get("decision_policy") != "diagnostic_only"
            or not isinstance(queue.get("batches"), list) or not queue["batches"]):
        raise ValueError("invalid price evidence queue")
    batch = queue["batches"][0]
    if not isinstance(batch, dict):
        raise ValueError("invalid price evidence batch")
    requested = unique_ids(batch.get("hotel_ids"), "queued hotel ids")
    destination = bounded_text(batch.get("destination"))
    if not requested or not destination:
        raise ValueError("invalid price evidence batch")
    return requested, destination, reasons[-1]


def merge_failure_checkpoint(checkpoint, report, queue, checked_at=None):
    requested, destination, reason = validate_failure(report, queue)
    checked_at = checked_at or utc_now()
    previous = {row["external_id"]: row for row in checkpoint["rows"]}
    new_ids = sum(identifier not in previous for identifier in requested)
    for identifier in requested:
        old = previous.get(identifier, {})
        previous[identifier] = {
            "external_id": identifier,
            "destination": destination,
            "status": "probe_unavailable",
            "reason": reason,
            "first_checked_at": old.get("first_checked_at", checked_at),
            "last_checked_at": checked_at,
        }
    rows = [previous[key] for key in sorted(previous)]
    status_counts = {status: sum(row["status"] == status for row in rows)
                     for status in sorted(STATUSES)}
    return {
        "schema_version": 1,
        "mode": "price_evidence_checkpoint",
        "decision_policy": "diagnostic_only",
        "updated_at": checked_at,
        "counts": {
            "completed_ids": len(rows),
            "checked_ids": status_counts["offer_seen"] + status_counts["no_offer"],
            "deferred_ids": status_counts["probe_unavailable"],
            "new_ids": new_ids,
            "repeated_ids": len(requested) - new_ids,
            **status_counts,
        },
        "last_batch": {
            "destination": destination,
            "requested_ids": len(requested),
            "probe_status": reason,
        },
        "rows": rows,
    }


def merge_checkpoint(checkpoint, report, checked_at=None):
    requested, returned, destination, evidence = validate_report(report)
    checked_at = checked_at or utc_now()
    previous = {row["external_id"]: row for row in checkpoint["rows"]}
    new_ids = sum(identifier not in previous for identifier in requested)
    for identifier in requested:
        old = previous.get(identifier, {})
        row = {
            "external_id": identifier,
            "destination": destination,
            "status": "offer_seen" if identifier in returned else "no_offer",
            "first_checked_at": old.get("first_checked_at", checked_at),
            "last_checked_at": checked_at,
        }
        if identifier in evidence:
            row.update(evidence[identifier])
        previous[identifier] = row
    rows = [previous[key] for key in sorted(previous)]
    status_counts = {status: sum(row["status"] == status for row in rows) for status in sorted(STATUSES)}
    return {
        "schema_version": 1,
        "mode": "price_evidence_checkpoint",
        "decision_policy": "diagnostic_only",
        "updated_at": checked_at,
        "counts": {
            "completed_ids": len(rows),
            "checked_ids": status_counts["offer_seen"] + status_counts["no_offer"],
            "deferred_ids": status_counts["probe_unavailable"],
            "new_ids": new_ids,
            "repeated_ids": len(requested) - new_ids,
            **status_counts,
        },
        "last_batch": {
            "destination": destination,
            "requested_ids": len(requested),
            "returned_ids": len(returned),
            "missing_ids": len(requested) - len(returned),
            "unexpected_offer_count": report.get("unexpected_offer_count", 0),
            "external_results_not_loaded": report.get("external_results_not_loaded") is True,
        },
        "rows": rows,
    }


def write_atomic(path, value):
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    fd, temporary = tempfile.mkstemp(prefix=target.name + ".", dir=target.parent)
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as handle:
            json.dump(value, handle, ensure_ascii=False, indent=2)
            handle.write("\n")
        os.replace(temporary, target)
    except Exception:
        try:
            os.unlink(temporary)
        except FileNotFoundError:
            pass
        raise


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--checkpoint", required=True)
    parser.add_argument("--report", required=True)
    parser.add_argument("--queue")
    args = parser.parse_args()
    checkpoint = load_checkpoint(args.checkpoint)
    report = json.loads(Path(args.report).read_text(encoding="utf-8"))
    if report.get("ok") is True:
        result = merge_checkpoint(checkpoint, report)
    else:
        if not args.queue:
            raise ValueError("failed report requires queue")
        queue = json.loads(Path(args.queue).read_text(encoding="utf-8"))
        result = merge_failure_checkpoint(checkpoint, report, queue)
    write_atomic(args.checkpoint, result)
    print(json.dumps(result["counts"], ensure_ascii=False, sort_keys=True))


if __name__ == "__main__":
    main()
