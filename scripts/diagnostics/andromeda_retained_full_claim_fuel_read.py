#!/usr/bin/env python3
"""Sanitize retained Andromeda FULL-claim fuel evidence for offline/private reads.

The helper is deliberately supplier-free. It accepts retained JSON on stdin and emits
only a small allowlisted evidence shape. Current services and alternative services are
kept in separate buckets and are never aggregated into one pricing conclusion.
"""

from __future__ import annotations

import hashlib
import json
import re
import sys
from typing import Any, Iterable

MAX_CLAIMS = 3
MAX_TEXT = 160
SCHEMA = "andromeda_retained_full_claim_fuel_evidence_v1"

_ID_KEYS = ("claim_id", "claimId", "selected_claim_id", "selectedClaimId", "id")
_CURRENT_KEYS = ("services", "current_services", "currentServices")
_ALT_KEYS = ("alternative_services", "alternativeServices")
_SCOPE_KEYS = ("claim", "package")
_DESCRIPTOR_KEYS = ("type", "service_type", "serviceType", "code", "name", "title", "category")
_EXPLICIT_FUEL_KEYS = ("is_fuel", "isFuel", "fuel", "fuel_surcharge", "fuelSurcharge")
_REQUIRED_KEYS = ("required", "is_required", "isRequired", "mandatory", "is_mandatory", "isMandatory")
_DEPENDENCY_KEYS = (
    "dependency", "dependent", "depends_on", "dependsOn", "dependency_type", "dependencyType",
)
_PACKET_KEYS = (
    "packet", "package", "is_packet", "isPacket", "in_packet", "inPacket", "package_only", "packageOnly",
)
_INCLUSION_KEYS = (
    "included", "price_included", "priceIncluded", "included_in_price", "includedInPrice", "is_included", "isIncluded",
)
_AMOUNT_KEYS = ("amount", "sum", "value", "price")
_CURRENCY_KEYS = ("currency", "currency_code", "currencyCode")
_UNIT_KEYS = ("unit", "price_unit", "priceUnit", "charge_unit", "chargeUnit", "per")
_CLIENT_KEYS = ("clients", "client_ids", "clientIds", "passengers", "tourists")
_COMMON_KEYS = ("common", "is_common", "isCommon")

_SECRET_RE = re.compile(r"(?i)(authorization|bearer|token|password|passwd|secret|cookie|api[_-]?key|private[_-]?key)")
_TMP_RE = re.compile(r"/tmp/[^\s\"']+")
_FUEL_RE = re.compile(r"(?i)(?:\bfuel\b|fuel[_ -]?surcharge|топлив)")
_EXCLUDED_RE = re.compile(
    r"(?i)(party[_ -]?transport[_ -]?surcharge|minimum[_ -]?(?:flight|air)|min[_ -]?(?:flight|air)|"
    r"(?:flight|air)[_ -]?(?:markup|surcharge)|generic[_ -]?(?:flight|air))"
)


def _json_scalar(value: Any) -> bool:
    return value is None or isinstance(value, (bool, int, float, str))


def _safe_scalar(value: Any) -> Any:
    if value is None or isinstance(value, (bool, int, float)):
        return value
    if not isinstance(value, str):
        return None
    text = value.strip()
    if _SECRET_RE.search(text):
        return "[REDACTED]"
    text = _TMP_RE.sub("/tmp/[redacted]", text)
    if len(text) > MAX_TEXT:
        text = text[:MAX_TEXT] + "…"
    return text


def _claim_ref(claim: dict[str, Any]) -> str | None:
    for key in _ID_KEYS:
        value = claim.get(key)
        if _json_scalar(value) and value not in (None, ""):
            return hashlib.sha256(str(value).encode("utf-8")).hexdigest()[:20]
    for scope_key in _SCOPE_KEYS:
        scope = claim.get(scope_key)
        if isinstance(scope, dict):
            for key in _ID_KEYS:
                value = scope.get(key)
                if _json_scalar(value) and value not in (None, ""):
                    return hashlib.sha256(str(value).encode("utf-8")).hexdigest()[:20]
    return None


def _claim_contexts(payload: Any) -> tuple[list[dict[str, Any]], bool]:
    if isinstance(payload, list):
        raw = payload
    elif isinstance(payload, dict):
        if isinstance(payload.get("claims"), list):
            raw = payload["claims"]
        elif isinstance(payload.get("items"), list):
            raw = payload["items"]
        else:
            raw = [payload]
    else:
        raw = []
    dicts = [item for item in raw if isinstance(item, dict)]
    return dicts[:MAX_CLAIMS], len(dicts) > MAX_CLAIMS


def _looks_like_service(obj: dict[str, Any]) -> bool:
    keys = set(obj)
    return bool(keys.intersection(_DESCRIPTOR_KEYS + _EXPLICIT_FUEL_KEYS + _AMOUNT_KEYS + _INCLUSION_KEYS))


def _expand_services(value: Any) -> list[dict[str, Any]]:
    """Expand one level of service containers without recursively walking the claim."""
    if isinstance(value, dict):
        if _looks_like_service(value):
            return [value]
        nested = value.get("services")
        if isinstance(nested, list):
            return [item for item in nested if isinstance(item, dict)]
        return []
    if not isinstance(value, list):
        return []
    out: list[dict[str, Any]] = []
    for item in value:
        if not isinstance(item, dict):
            continue
        if _looks_like_service(item):
            out.append(item)
            continue
        nested = item.get("services")
        if isinstance(nested, list):
            out.extend(child for child in nested if isinstance(child, dict))
    return out


def _service_buckets(claim: dict[str, Any]) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    current: list[dict[str, Any]] = []
    alternatives: list[dict[str, Any]] = []
    scopes: list[dict[str, Any]] = [claim]
    for scope_key in _SCOPE_KEYS:
        scope = claim.get(scope_key)
        if isinstance(scope, dict):
            scopes.append(scope)
    for scope in scopes:
        for key in _CURRENT_KEYS:
            if key in scope:
                current.extend(_expand_services(scope.get(key)))
        for key in _ALT_KEYS:
            if key in scope:
                alternatives.extend(_expand_services(scope.get(key)))
    return _dedupe_services(current), _dedupe_services(alternatives)


def _dedupe_services(services: Iterable[dict[str, Any]]) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    seen: set[str] = set()
    for service in services:
        try:
            marker = json.dumps(service, sort_keys=True, ensure_ascii=False, separators=(",", ":"))
        except (TypeError, ValueError):
            marker = repr(service)
        digest = hashlib.sha256(marker.encode("utf-8", errors="replace")).hexdigest()
        if digest not in seen:
            seen.add(digest)
            out.append(service)
    return out


def _descriptor_text(service: dict[str, Any]) -> str:
    parts: list[str] = []
    for key in _DESCRIPTOR_KEYS:
        value = service.get(key)
        if isinstance(value, (str, int, float)):
            parts.append(str(value))
    return " ".join(parts)


def _is_fuel(service: dict[str, Any]) -> tuple[bool, str | None]:
    descriptor = _descriptor_text(service)
    if _EXCLUDED_RE.search(descriptor):
        return False, None
    for key in _EXPLICIT_FUEL_KEYS:
        if service.get(key) is True:
            return True, "explicit_fuel_flag"
    if _FUEL_RE.search(descriptor):
        return True, "fuel_descriptor"
    return False, None


def _copy_scalar_keys(source: dict[str, Any], keys: Iterable[str]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key in keys:
        if key not in source or not _json_scalar(source[key]):
            continue
        result[key] = _safe_scalar(source[key])
    return result


def _first_scalar(source: dict[str, Any], keys: Iterable[str]) -> tuple[str, Any] | None:
    for key in keys:
        if key in source and _json_scalar(source[key]) and source[key] is not None:
            return key, _safe_scalar(source[key])
    return None


def _money_evidence(service: dict[str, Any]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    amount = _first_scalar(service, _AMOUNT_KEYS)
    if amount is not None:
        result["amount"] = amount[1]
        result["amount_source"] = amount[0]
    price_obj = service.get("price")
    if isinstance(price_obj, dict):
        if "amount" not in result:
            nested_amount = _first_scalar(price_obj, ("amount", "sum", "value"))
            if nested_amount is not None:
                result["amount"] = nested_amount[1]
                result["amount_source"] = f"price.{nested_amount[0]}"
        nested_currency = _first_scalar(price_obj, _CURRENCY_KEYS)
        if nested_currency is not None:
            result["currency"] = nested_currency[1]
            result["currency_source"] = f"price.{nested_currency[0]}"
        nested_unit = _first_scalar(price_obj, _UNIT_KEYS)
        if nested_unit is not None:
            result["unit"] = nested_unit[1]
            result["unit_source"] = f"price.{nested_unit[0]}"
    currency = _first_scalar(service, _CURRENCY_KEYS)
    if currency is not None:
        result["currency"] = currency[1]
        result["currency_source"] = currency[0]
    unit = _first_scalar(service, _UNIT_KEYS)
    if unit is not None:
        result["unit"] = unit[1]
        result["unit_source"] = unit[0]
    return result


def _clients_evidence(service: dict[str, Any]) -> dict[str, Any] | None:
    for key in _CLIENT_KEYS:
        if key not in service:
            continue
        value = service[key]
        if isinstance(value, (list, tuple, set, dict)):
            return {"source": key, "present": True, "count": len(value)}
        if isinstance(value, bool):
            return {"source": key, "present": value}
        if isinstance(value, (int, float)):
            return {"source": key, "present": True, "count": value}
        if isinstance(value, str):
            return {"source": key, "present": bool(value.strip())}
    return None


def _inclusion_evidence(service: dict[str, Any]) -> tuple[list[dict[str, Any]], str]:
    evidence: list[dict[str, Any]] = []
    for key in _INCLUSION_KEYS:
        if key in service and isinstance(service[key], bool):
            evidence.append({"path": key, "value": service[key]})
    price_obj = service.get("price")
    if isinstance(price_obj, dict):
        for key in _INCLUSION_KEYS:
            if key in price_obj and isinstance(price_obj[key], bool):
                evidence.append({"path": f"price.{key}", "value": price_obj[key]})
    values = {item["value"] for item in evidence}
    if values == {True}:
        state = "included"
    elif values == {False}:
        state = "not_included"
    elif len(values) > 1:
        state = "conflicting"
    else:
        state = "unknown"
    return evidence, state


def _sanitize_fuel_service(service: dict[str, Any], reason: str) -> dict[str, Any]:
    out: dict[str, Any] = {"fuel_match": reason}
    descriptors = _copy_scalar_keys(service, _DESCRIPTOR_KEYS)
    if descriptors:
        out["descriptor"] = descriptors
    flags: dict[str, Any] = {}
    for group, keys in (("required", _REQUIRED_KEYS), ("dependency", _DEPENDENCY_KEYS), ("packet", _PACKET_KEYS)):
        copied = _copy_scalar_keys(service, keys)
        if copied:
            flags[group] = copied
    if flags:
        out["flags"] = flags
    money = _money_evidence(service)
    if money:
        out["money"] = money
    clients = _clients_evidence(service)
    if clients is not None:
        out["clients"] = clients
    common = _copy_scalar_keys(service, _COMMON_KEYS)
    if common:
        out["common"] = common
    inclusion, state = _inclusion_evidence(service)
    out["price_inclusion_state"] = state
    if inclusion:
        out["price_inclusion_evidence"] = inclusion
    return out


def _fuel_bucket(services: list[dict[str, Any]]) -> list[dict[str, Any]]:
    result: list[dict[str, Any]] = []
    for service in services:
        matched, reason = _is_fuel(service)
        if matched and reason is not None:
            result.append(_sanitize_fuel_service(service, reason))
    return result


def sanitize_payload(payload: Any) -> dict[str, Any]:
    claims, truncated = _claim_contexts(payload)
    sanitized_claims: list[dict[str, Any]] = []
    for index, claim in enumerate(claims):
        current, alternatives = _service_buckets(claim)
        current_fuel = _fuel_bucket(current)
        alternative_fuel = _fuel_bucket(alternatives)
        item: dict[str, Any] = {
            "claim_index": index,
            "current_fuel_services": current_fuel,
            "alternative_fuel_services": alternative_fuel,
            "current_fuel_count": len(current_fuel),
            "alternative_fuel_count": len(alternative_fuel),
        }
        ref = _claim_ref(claim)
        if ref is not None:
            item["claim_ref_sha256"] = ref
        sanitized_claims.append(item)
    out: dict[str, Any] = {
        "schema": SCHEMA,
        "claims_processed": len(sanitized_claims),
        "claims_truncated": truncated,
        "claims": sanitized_claims,
    }
    canonical = json.dumps(out, sort_keys=True, ensure_ascii=False, separators=(",", ":"))
    out["evidence_sha256"] = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
    return out


def main() -> int:
    try:
        payload = json.load(sys.stdin)
    except (json.JSONDecodeError, UnicodeDecodeError):
        print(json.dumps({"schema": SCHEMA, "error": "invalid_json"}, sort_keys=True), file=sys.stderr)
        return 2
    result = sanitize_payload(payload)
    print(json.dumps(result, sort_keys=True, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
