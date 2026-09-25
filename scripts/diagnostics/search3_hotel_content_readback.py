#!/usr/bin/env python3
"""Bounded, read-only Search3 content triage. No supplier, DB or image requests.

Compare the existing saved-source and canonical-profile HTTP contracts for 1..20
explicit legacy IDs. Presence is not proof of image reachability or DOM rendering.
An unavailable/malformed response is UNKNOWN, never an empty hotel catalogue.
"""
from __future__ import annotations

import argparse
from collections import Counter
from datetime import datetime, timezone
import hashlib
import json
import re
import sys
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import HTTPRedirectHandler, Request, build_opener

ENDPOINT = "https://anytoour.ru/_preview/search3-local-candidate/data/hotel-details-read-v1.php"
MAX_IDS = 20
MAX_BYTES = 4 * 1024 * 1024
MAX_ID = 2**53 - 1


class InvalidSnapshot(ValueError):
    """A response cannot safely be attributed to the requested hotels."""


def hotel_id(value: Any) -> int:
    if type(value) not in (int, str) or not re.fullmatch(r"[1-9][0-9]*", str(value)):
        raise InvalidSnapshot("invalid_id")
    result = int(value)
    if result > MAX_ID:
        raise InvalidSnapshot("unsafe_id")
    return result


def ids(values: Any) -> list[int]:
    if not isinstance(values, list) or not 1 <= len(values) <= MAX_IDS:
        raise InvalidSnapshot("expected_1_to_20_ids")
    result = [hotel_id(value) for value in values]
    if len(set(result)) != len(result):
        raise InvalidSnapshot("duplicate_id")
    return sorted(result)


def _array(value: Any) -> list[Any]:
    if not isinstance(value, list):
        raise InvalidSnapshot("expected_array")
    return value


def validate(payload: Any, requested: list[int], canonical: bool) -> dict[int, dict[str, Any]]:
    """Validate full batch identity/coverage before reporting any row as missing."""
    if not isinstance(payload, dict) or payload.get("ok") is not True:
        raise InvalidSnapshot("response_not_ok")
    expected_source = "anytour-canonical-catalog" if canonical else "anytour-local-hotel"
    if payload.get("source") != expected_source:
        raise InvalidSnapshot("wrong_source")
    request_key = "requestedLegacyIds" if canonical else "requestedIds"
    if [hotel_id(v) for v in _array(payload.get(request_key))] != requested:
        raise InvalidSnapshot("wrong_requested_ids")
    profiles: dict[int, dict[str, Any]] = {}
    for row in _array(payload.get("items")):
        if not isinstance(row, dict):
            raise InvalidSnapshot("invalid_profile")
        key = hotel_id(row.get("id"))
        if key in profiles:
            raise InvalidSnapshot("duplicate_profile")
        if canonical and (row.get("catalog") != "anytour"
                          or type(row.get("revision")) is not int or row["revision"] < 1
                          or not isinstance(row.get("name"), str) or not row["name"].strip()):
            raise InvalidSnapshot("invalid_canonical_profile")
        profiles[key] = row
    mapped: dict[int, dict[str, Any]] = {}
    if canonical:
        if payload.get("catalog") != "anytour":
            raise InvalidSnapshot("wrong_catalog")
        used: set[int] = set()
        for link in _array(payload.get("links")):
            if not isinstance(link, dict):
                raise InvalidSnapshot("invalid_link")
            old, own = hotel_id(link.get("legacyHotelId")), hotel_id(link.get("anytourHotelId"))
            if old not in requested or old in mapped or own not in profiles:
                raise InvalidSnapshot("invalid_link")
            mapped[old] = profiles[own]
            used.add(own)
        if used != set(profiles):
            raise InvalidSnapshot("unlinked_profile")
    else:
        mapped = profiles
    missing_key = "missingLegacyIds" if canonical else "missingIds"
    missing = [hotel_id(v) for v in _array(payload.get(missing_key))]
    if (len(set(missing)) != len(missing) or set(mapped) & set(missing)
            or set(mapped) | set(missing) != set(requested)):
        raise InvalidSnapshot("incomplete_or_conflicting_batch")
    return mapped


def content(row: dict[str, Any]) -> dict[str, Any]:
    """Inspect the existing sanitized DTO; do not create another media sanitizer."""
    description, primary, gallery = row.get("description"), row.get("primaryImage"), row.get("images", [])
    if (description is not None and not isinstance(description, str)
            or primary is not None and not isinstance(primary, str)
            or not isinstance(gallery, list)
            or any(not isinstance(value, str) for value in gallery)):
        raise InvalidSnapshot("content_field_shape")
    refs = {value.strip() for value in [primary, *gallery] if isinstance(value, str) and value.strip()}
    return {
        "descriptionPresent": bool(description and description.strip()),
        "descriptionCharacters": len((description or "").strip()),
        "primaryImagePresent": bool(primary and primary.strip()),
        "imageReferenceCount": len(refs),
        # These are nonempty references, NOT verified/decoded photographs.
        "imageReferencesPresent": bool(refs),
    }


def analyze(requested_values: list[Any], saved: Any, canonical: Any,
            read_errors: dict[str, str] | None = None) -> dict[str, Any]:
    requested = ids(requested_values)
    errors = dict(read_errors or {})
    batches: dict[str, dict[int, dict[str, Any]]] = {}
    for kind, payload, own in (("saved", saved, False), ("canonical", canonical, True)):
        if kind in errors:
            continue
        try:
            batches[kind] = validate(payload, requested, own)
            # Invalid field types invalidate the response, not an apparent empty value.
            for row in batches[kind].values():
                content(row)
        except (InvalidSnapshot, TypeError, KeyError) as exc:
            errors[kind] = "invalid_snapshot:" + (str(exc) if isinstance(exc, InvalidSnapshot) else "shape")
            batches.pop(kind, None)
    rows = []
    for old in requested:
        src = batches.get("saved", {}).get(old)
        own = batches.get("canonical", {}).get(old)
        row: dict[str, Any] = {"legacyHotelId": old, "anytourHotelId": own["id"] if own else None}
        row["saved"] = content(src) if src else None
        row["canonical"] = content(own) if own else None
        row["canonicalRevision"] = own.get("revision") if own else None
        reasons = {}
        for field, key in (("description", "descriptionPresent"), ("images", "imageReferencesPresent")):
            if "canonical" in errors:
                reason = "canonical_read_unknown"
            elif own is None:
                reason = "canonical_profile_unavailable"
            elif row["canonical"][key]:
                reason = "canonical_present_check_display"
            elif "saved" in errors:
                reason = "saved_read_unknown"
            elif src is not None and row["saved"][key]:
                reason = "saved_present_canonical_missing"
            else:
                reason = "saved_content_missing_requires_source_review"
            reasons[field] = reason
        row["reasons"] = reasons
        rows.append(row)
    return {
        "schemaVersion": 1,
        "status": "readback_complete" if not errors else "readback_partial" if batches else "readback_failed",
        "requestedLegacyIds": requested,
        "errors": errors,
        "rows": rows,
        "summary": {field: dict(Counter(row["reasons"][field] for row in rows))
                    for field in ("description", "images")},
        "limits": {"maximumIds": MAX_IDS, "maximumHttpReads": 2},
        "mutationsRequested": 0,
        "supplierRequestsRequested": 0,
        "imageReachability": "not_checked",
        "search3Dom": "not_checked",
        "installedSourceSha": "not_measured",
        "note": "Presence in an API response is not proof of fresh supplier content, a successful image load or UI display.",
    }


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise HTTPError(req.full_url, code, "redirect_not_allowed", headers, fp)


def read_batch(requested: list[int], canonical: bool) -> tuple[Any, str | None, str | None]:
    requested = ids(requested)
    query = [("catalog", "anytour")] if canonical else []
    query += [("legacyHotelIds[]" if canonical else "hotelIds[]", str(key)) for key in requested]
    request = Request(ENDPOINT + "?" + urlencode(query), headers={"Accept": "application/json"}, method="GET")
    try:
        with build_opener(NoRedirect()).open(request, timeout=15) as response:
            if response.status != 200:
                return None, "http_" + str(response.status), None
            raw = response.read(MAX_BYTES + 1)
            if len(raw) > MAX_BYTES:
                return None, "response_too_large", None
        digest = hashlib.sha256(raw).hexdigest()
        return json.loads(raw), None, digest
    except HTTPError as exc:
        return None, "http_" + str(exc.code), None
    except (URLError, TimeoutError, OSError):
        return None, "transport_unavailable", None
    except (ValueError, UnicodeError):
        return None, "invalid_json", None


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--legacy-ids", required=True, help="1..20 explicit historical catalogue IDs, comma-separated; never AnyTour IDs guessed as TV IDs")
    args = parser.parse_args()
    try:
        requested = ids(args.legacy_ids.split(","))
    except InvalidSnapshot as exc:
        parser.error(str(exc))
    payloads, errors, hashes = {}, {}, {}
    for kind in ("canonical", "saved"):
        payloads[kind], error, digest = read_batch(requested, kind == "canonical")
        if error:
            errors[kind] = error
        if digest:
            hashes[kind] = digest
    report = analyze(requested, payloads["saved"], payloads["canonical"], errors)
    report.update({"basis": "paired_http_readback", "observedAt": datetime.now(timezone.utc).isoformat(),
                   "responseSha256": hashes, "httpReadAttempts": 2})
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0 if report["status"] == "readback_complete" else 2


if __name__ == "__main__":
    sys.exit(main())
