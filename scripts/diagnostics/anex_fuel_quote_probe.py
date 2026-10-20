#!/usr/bin/env python3
"""One bounded ANEX price/access audit; never saves/initializes a booking.

Uses the existing ANEX transport on the authorized AnyTour SSH host. Supplier
references and raw responses remain in memory. The calculation capability call
has no claim identifier: it can establish a dispatch/access error, not a quote.
"""
from __future__ import annotations
import base64
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys
import tempfile
import zlib

ALLOWED = frozenset({"SearchTour_TOWNFROMS", "SearchTour_STATES", "SearchTour_CHECKIN",
                     "SearchTour_CURRENCIES", "SearchTour_NIGHTS", "SearchTour_PRICES",
                     "Booking_CalcClaim"})
LIMIT = 7


def clean_text(value, token, maximum=140):
    if not isinstance(value, str) or not value or len(value) > maximum:
        return None
    decoded = value
    import urllib.parse
    for _ in range(8):
        if token in decoded or re.search(r"[\x00-\x1f\x7f<>]|https?:|oauth_token", decoded, re.I):
            return None
        next_value = urllib.parse.unquote(decoded)
        if next_value == decoded:
            return value
        decoded = next_value
    return None


def money(value):
    if isinstance(value, bool) or not isinstance(value, (int, float, str)):
        return None
    value = str(value)
    return value if re.fullmatch(r"(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?", value) else None


def run_remote(token, transport):
    report = {"schema_version": 1, "status": "selection_not_completed", "checks": [],
              "final_price_verified": False, "fuel_inclusion": "unknown",
              "booking_created": False, "claim_initialized": False,
              "database_access": False, "production_changes": False}
    if not isinstance(token, str) or not re.fullmatch(r"[^\s\x00-\x1f\x7f]{1,4096}", token):
        report["status"] = "missing_or_invalid_token"
        return report
    transport.SENSITIVE_VALUES = (token,)
    checks = report["checks"]

    def call(action, params):
        if action not in ALLOWED or len(checks) >= LIMIT:
            raise ValueError("request_boundary")
        if action == "Booking_CalcClaim" and params:
            raise ValueError("claimless_capability_only")
        result, body = transport.request(dict(params, samo_action="api", version="1.0",
                                              type="json", action=action), token)
        item = {"action": action, "status": result.get("status", "invalid_response")}
        for key in ("http_status", "elapsed_ms"):
            value = result.get(key)
            if type(value) is int and 0 <= value <= 1_000_000:
                item[key] = value
        checks.append(item)
        if body is None:
            return None
        try:
            envelope = json.loads(body)
        except (ValueError, UnicodeError):
            item["status"] = "invalid_response"
            return None
        if not isinstance(envelope, dict):
            item["status"] = "invalid_response"
            return None
        data = envelope.get(action)
        error = data if isinstance(data, dict) and "error" in data else envelope
        if "error" in error:
            item["status"] = "supplier_error"
            code = error.get("error")
            if isinstance(code, (str, int)) and not isinstance(code, bool) and re.fullmatch(r"[0-9]{1,9}", str(code)):
                item["supplier_code"] = int(code)
            message = clean_text(error.get("message"), token, 240)
            # Keep only fixed diagnostic classification, never supplier error text.
            if message:
                lowered = message.casefold()
                if re.search(r"not (?:allowed|permitted)|access denied|нет (?:прав|доступа)|доступ.{0,15}запрещ", lowered):
                    item["error_class"] = "access_denied"
                elif re.search(r"unknown (?:method|action)|method.{0,20}not found|неизвестн.{0,15}(?:метод|действ)|метод.{0,15}не найден", lowered):
                    item["error_class"] = "unknown_method"
                elif re.search(r"required|обязател|не задан|не указан|не передан", lowered):
                    item["error_class"] = "parameter_required"
                else:
                    item["error_class"] = "other_supplier_error"
            return None
        if not isinstance(data, (list, dict)):
            item["status"] = "invalid_response"
            return None
        item["status"] = "ok"
        return data

    # First ask only for calculation capability. Missing claim cannot create a
    # reservation, and no init/save/payment method is present in the allowlist.
    call("Booking_CalcClaim", {})
    report["calculation_probe_scope"] = "no_claim_dispatch_only_not_quote"
    try:
        departure = transport.choose_named(call("SearchTour_TOWNFROMS", {}), (r"москва|moscow",))
        if departure is None:
            return report
        params = {"TOWNFROMINC": transport.positive_id(departure)}
        destination = transport.choose_named(call("SearchTour_STATES", params), (r"егип|egypt",))
        if destination is None:
            return report
        params.update(STATEINC=transport.positive_id(destination), ADULT=2, CHILD=0)
        dates = transport.available_dates(call("SearchTour_CHECKIN", params), dt.datetime.now(dt.timezone.utc).date() + dt.timedelta(days=7))
        if not dates:
            return report
        checkin = dates[0][0].strftime("%Y%m%d")
        params.update(CHECKIN_BEG=checkin, CHECKIN_END=checkin)
        currency = transport.choose_named(call("SearchTour_CURRENCIES", params), (r"\brub\b|руб",))
        if currency is None:
            return report
        params["CURRENCY"] = transport.positive_id(currency)
        data = call("SearchTour_NIGHTS", params)
        raw = data if isinstance(data, list) else (data or {}).get("places") or (data or {}).get("nights", [])
        nights = []
        for item in raw:
            value = item.get("id", item.get("nights")) if isinstance(item, dict) else item
            if str(value).isdigit() and 3 <= int(value) <= 14:
                nights.append(int(value))
        if not nights:
            return report
        duration = min(nights, key=lambda n: abs(n - 7))
        params.update(NIGHTS_FROM=duration, NIGHTS_TILL=duration, PARTITION_PRICE=0,
                      FREIGHT=1, FILTER=1, PRICEPAGE=1, SORT="ASC", DYN_SEPARATE=1)
        report["search"] = {"departure": clean_text(departure.get("name"), token),
                            "destination": clean_text(destination.get("name"), token),
                            "checkin": checkin, "nights": duration, "adults": 2, "children": 0}
        data = call("SearchTour_PRICES", params)
        if not isinstance(data, dict) or not isinstance(data.get("prices"), list):
            return report
        report["returned_rows"] = len(data["prices"])
        report["external_search_pending"] = bool(data.get("searchKey"))
        for row in data["prices"][:300]:
            if not isinstance(row, dict) or any(str(row.get(k)) != str(v) for k, v in
                    (("grouped", 0), ("packetType", 0), ("adult", 2), ("child", 0), ("checkIn", checkin), ("nights", duration))):
                continue
            if str(row.get("infant", 0)) != "0":
                continue
            amount = money(row.get("price"))
            curr = row.get("currency")
            ref = row.get("id")
            if amount is None or not isinstance(curr, str) or not re.fullmatch(r"[A-Z]{3}", curr) or not isinstance(ref, str) or not 1 <= len(ref) <= 2048:
                continue
            sample = {"price": {"amount": amount, "currency": curr},
                      "offer_fingerprint": hashlib.sha256(ref.encode()).hexdigest(),
                      "final_price_verified": False, "fuel_inclusion": "unknown"}
            for key in ("hotel", "room", "meal", "checkIn", "checkOut", "convertedPrice"):
                sample[key] = clean_text(str(row[key]), token) if key in row else None
            for key in ("hotelKey", "nights", "adult", "child"):
                value = row.get(key)
                if isinstance(value, (int, str)) and re.fullmatch(r"[0-9]{1,9}", str(value)):
                    sample[key] = int(value)
            # Schema presence is evidence, not an inference that fuel is zero.
            sample["explicit_fuel_field_present"] = any(re.search(r"fuel|топлив", str(k), re.I) for k in row)
            report["sample"] = sample
            report["status"] = "search_observed_quote_not_calculated"
            break
    except Exception:
        report["status"] = "selection_not_completed"
    return report


def ssh_run():
    names = ("ANEX_API_TOKEN", "ANYTOOUR_DEPLOY_SSH_KEY", "ANYTOOUR_DEPLOY_HOST", "ANYTOOUR_DEPLOY_USER")
    if any(not os.environ.get(k, "").strip() for k in names):
        return {"status": "missing_configuration", "final_price_verified": False}
    host, user = (os.environ[k].strip() for k in names[2:])
    if not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9.-]{0,252}", host) or not re.fullmatch(r"[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}", user):
        return {"status": "invalid_ssh_target", "final_price_verified": False}
    root = Path(__file__).resolve().parent
    base = (root / "anex_access_probe.py").read_text(encoding="utf-8").rsplit('\nif __name__ == "__main__":', 1)[0]
    own = Path(__file__).read_text(encoding="utf-8").rsplit('\nif __name__ == "__main__":', 1)[0]
    program = "import types,json,sys\nt=types.ModuleType('anex_transport')\nexec(" + repr(base) + ",t.__dict__)\n"
    program += "n={}\nexec(" + repr(own) + ",n)\nprint(json.dumps(n['run_remote'](json.loads(sys.stdin.read(8192))['token'],t),sort_keys=True))\n"
    encoded = base64.b64encode(zlib.compress(program.encode())).decode()
    command_source = "import base64,zlib;exec(zlib.decompress(base64.b64decode(" + repr(encoded) + ")))"
    with tempfile.TemporaryDirectory(prefix="anex-fuel-", dir=os.environ.get("RUNNER_TEMP")) as temp:
        key = Path(temp) / "key"
        with os.fdopen(os.open(key, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), "w") as f:
            f.write(os.environ[names[1]].rstrip() + "\n")
        command = ["ssh", "-T", "-i", str(key), "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes",
                   "-o", "StrictHostKeyChecking=accept-new", "-o", "UserKnownHostsFile=" + str(Path(temp) / "known_hosts"),
                   "-o", "ConnectTimeout=15", "-o", "ServerAliveInterval=15", "-o", "ServerAliveCountMax=2",
                   "-o", "LogLevel=ERROR", "-l", user, host,
                   'cd "$HOME/www/anytoour.ru" && python3 -B -c ' + shlex.quote(command_source)]
        try:
            result = subprocess.run(command, input=json.dumps({"token": os.environ[names[0]].strip()}),
                capture_output=True, text=True, timeout=210, env={k:v for k,v in os.environ.items() if k not in names})
        except subprocess.TimeoutExpired:
            return {"status": "ssh_timeout_no_retry", "final_price_verified": False}
        if result.returncode or not result.stdout.strip() or len(result.stdout) > 20000:
            return {"status": "ssh_failed_no_retry", "final_price_verified": False}
        output = json.loads(result.stdout)
        encoded_output = json.dumps(output)
        if any(os.environ[k].strip() in encoded_output for k in names):
            return {"status": "redaction_boundary", "final_price_verified": False}
        return output


def main():
    try:
        result = ssh_run()
    except Exception:
        result = {"status": "probe_failed_no_retry", "final_price_verified": False}
    result["source_sha"] = os.environ.get("GITHUB_SHA", "")
    path = Path(os.environ.get("ANEX_FUEL_REPORT", "anex-fuel-quote-report.json"))
    path.write_text(json.dumps(result, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))
    return 0 if result.get("status") == "search_observed_quote_not_calculated" else 1


if __name__ == "__main__":
    sys.exit(main())
