#!/usr/bin/env python3
"""Read-only ANEX access checks, executed in memory on the AnyTour SSH host.

No application bootstrap, database access, booking, persistent remote files, or
raw supplier responses. Tokens travel in SSH stdin, never in SSH arguments.
"""

import json
import datetime as dt
from decimal import Decimal, InvalidOperation
import os
from pathlib import Path
import re
import shlex
import socket
import ssl
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET


ENDPOINT = "https://parser.anextour.ru/export/default.php"
BODY_LIMIT = 2 * 1024 * 1024
TIMEOUT = 20
STATUSES = {
    "ok", "http_error", "network_error", "tls_error", "timeout",
    "response_too_large", "invalid_response", "supplier_error", "skipped",
    "missing_secret", "ssh_failed", "ssh_timeout", "ssh_auth_failed",
    "ssh_host_key_changed", "unexpected_probe_failure",
    "no_departures", "no_destinations", "no_dates", "no_currency",
    "no_nights", "no_prices", "invalid_offer", "request_limit",
}
CHECKS = {"api_townfroms", "reference_currentstamp", "reference_states",
          "reference_routes", "configuration", "ssh", "probe", "selection",
          "api_states", "api_checkin", "api_currencies", "api_nights",
          "api_prices", "api_prices_expanded"}
SENSITIVE_VALUES = ()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        # A reference request carries its token in the documented query format.
        # Never forward it to another URL, even on the same host.
        return None


def request(params, token, post=False):
    values = dict(params, oauth_token=token)
    # Keep the API dispatch parameters in the documented URL; credentials and
    # method parameters can use the POST body, including on legacy dispatchers.
    routing = {key: values.pop(key) for key in ("samo_action", "version")
               if post and key in values}
    encoded = urllib.parse.urlencode(values)
    url = ENDPOINT + "?" + (urllib.parse.urlencode(routing) if post else encoded)
    req = urllib.request.Request(
        url, data=encoded.encode("utf-8") if post else None,
        headers={"Accept": "application/json, application/xml",
                 "User-Agent": "AnyTour-ANEX-access-check/1.0"},
    )
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({}), NoRedirect(),
        urllib.request.HTTPSHandler(context=ssl.create_default_context()),
    )
    started = time.monotonic()
    try:
        with opener.open(req, timeout=TIMEOUT) as response:
            body = response.read(BODY_LIMIT + 1)
            if len(body) > BODY_LIMIT:
                return {"status": "response_too_large"}, None
            return {"status": "ok", "http_status": response.status,
                    "elapsed_ms": int((time.monotonic() - started) * 1000)}, body
    except urllib.error.HTTPError as exc:
        # Exception strings, bodies and URLs may contain credentials.
        return {"status": "http_error", "http_status": exc.code}, None
    except (socket.timeout, TimeoutError):
        return {"status": "timeout"}, None
    except urllib.error.URLError as exc:
        if isinstance(exc.reason, ssl.SSLError):
            status = "tls_error"
        elif isinstance(exc.reason, (socket.timeout, TimeoutError)):
            status = "timeout"
        else:
            status = "network_error"
        return {"status": status}, None


def api_check(token):
    result, body = request(
        {"samo_action": "api", "version": "1.0", "type": "json",
         "action": "SearchTour_TOWNFROMS"}, token,
    )
    result["check"] = "api_townfroms"
    if body is None:
        return result
    try:
        data = json.loads(body)
        items = data.get("SearchTour_TOWNFROMS") if isinstance(data, dict) else None
        if isinstance(items, list) and all(
            isinstance(item, dict) and "id" in item and "name" in item
            for item in items
        ):
            result["count"] = len(items)
        elif isinstance(data, dict) and (
            "error" in data or isinstance(items, dict) and "error" in items
        ):
            result["status"] = "supplier_error"
        else:
            result["status"] = "invalid_response"
    except (ValueError, UnicodeError):
        result["status"] = "invalid_response"
    return result


def reference_check(token, kind, extra=None):
    params = {"samo_action": "reference", "type": kind}
    params.update(extra or {})
    result, body = request(params, token)
    result["check"] = {"currentstamp": "reference_currentstamp",
                       "state": "reference_states",
                       "townstate": "reference_routes"}[kind]
    if body is None:
        return result, None
    try:
        if b"<!DOCTYPE" in body.upper() or b"<!ENTITY" in body.upper():
            raise ValueError("unsupported XML declaration")
        root = ET.fromstring(body)
        if any(node.tag.lower() == "error" for node in root.iter()):
            result["status"] = "supplier_error"
            return result, None
        data = root.find("Data")
        if root.tag != "Response" or data is None:
            raise ValueError("unexpected XML envelope")
        items = data.findall(kind)
        stamp = None
        if kind == "currentstamp":
            stamp = items[0].get("stamp", "") if len(items) == 1 else ""
            if not stamp.startswith("0x") or len(stamp) != 18:
                raise ValueError("invalid stamp")
            int(stamp[2:], 16)
        elif kind == "state":
            if any("inc" not in item.attrib for item in items):
                raise ValueError("invalid state")
        elif any("town" not in item.attrib or "state" not in item.attrib
                 for item in items):
            raise ValueError("invalid route")
        result["count"] = len(items)
        return result, stamp
    except (ET.ParseError, ValueError, UnicodeError):
        result["status"] = "invalid_response"
        return result, None


def remote_probe(tokens):
    missing = [name for name in ("ANEX_API_TOKEN", "ANEX_REFERENCE_TOKEN")
               if not isinstance(tokens.get(name), str)
               or not 1 <= len(tokens[name].strip()) <= 4096]
    if missing:
        return {"ok": False, "checks": [
            {"check": "configuration", "status": "missing_secret"}]}
    api = api_check(tokens["ANEX_API_TOKEN"].strip())
    token = tokens["ANEX_REFERENCE_TOKEN"].strip()
    stamp_result, stamp = reference_check(token, "currentstamp")
    results = [api, stamp_result]
    if stamp:
        states, _ = reference_check(
            token, "state", {"laststamp": "0x0000000000000000", "delstamp": stamp})
        results.append(states)
    else:
        results.append({"check": "reference_states", "status": "skipped"})
    routes, _ = reference_check(token, "townstate")
    results.append(routes)
    return {"ok": all(item["status"] == "ok" for item in results),
            "checks": results}


class StopProbe(Exception):
    pass


def safe_label(value):
    value = str(value or "").strip()
    if (not re.fullmatch(r"[\w .(),+/'&*–—-]{1,140}", value)
            or any(secret and secret in value for secret in SENSITIVE_VALUES)):
        return ""
    return value


def date_value(value):
    value = str(value)
    if not re.fullmatch(r"\d{8}", value):
        raise ValueError("invalid date")
    return dt.datetime.strptime(value, "%Y%m%d").date()


def positive_id(item):
    value = str(item.get("id", ""))
    return int(value) if value.isdigit() and 0 < int(value) < 100_000_000 else None


def api_data(token, action, params, checks, expanded=False):
    allowed = {"SearchTour_TOWNFROMS": "api_townfroms",
               "SearchTour_STATES": "api_states", "SearchTour_CHECKIN": "api_checkin",
               "SearchTour_CURRENCIES": "api_currencies", "SearchTour_NIGHTS": "api_nights",
               "Hotels_DETAILS": "api_hotel_details",
               "SearchTour_PRICES": "api_prices_expanded" if expanded else "api_prices"}
    if action not in allowed or len(checks) >= 12:
        raise ValueError("invalid read method or request budget")
    result, body = request(dict(params, samo_action="api", version="1.0",
                                type="json", action=action), token)
    result["check"] = allowed[action]
    checks.append(result)
    if body is None:
        raise StopProbe()
    try:
        envelope = json.loads(body)
        data = envelope.get(action) if isinstance(envelope, dict) else None
        error = data if isinstance(data, dict) and "error" in data else envelope
        if isinstance(error, dict) and "error" in error:
            result["status"] = "supplier_error"
            code = str(error.get("error", ""))
            if code.isdigit() and 0 <= int(code) <= 10_000_000:
                result["supplier_code"] = int(code)
            raise StopProbe()
        if not isinstance(data, (list, dict)):
            raise ValueError("invalid API envelope")
        if isinstance(data, list):
            result["count"] = len(data)
        return data
    except (ValueError, UnicodeError):
        result["status"] = "invalid_response"
        raise StopProbe()


def choose_named(items, preferred):
    if isinstance(items, dict):
        items = items.get("items", [])
    if not isinstance(items, list):
        return None
    candidates = [item for item in items if isinstance(item, dict) and positive_id(item)]
    for pattern in preferred:
        for item in candidates:
            names = " ".join(str(item.get(key, "")) for key in (
                "name", "nameAlt", "alias", "currencyISO", "stateISO3")).casefold()
            if re.search(pattern, names):
                return item
    return candidates[0] if candidates else None


def available_dates(data, today):
    if not isinstance(data, dict):
        return []
    start = date_value(data["start"])
    valid = str(data.get("valid", ""))
    candidates = [(start + dt.timedelta(days=i), status) for i, status in enumerate(valid)
                  if status in "1235" and today + dt.timedelta(days=2) <= start + dt.timedelta(days=i)
                  <= today + dt.timedelta(days=60)]
    candidates.sort(key=lambda pair: (pair[1] not in "235",
                                     pair[0] < today + dt.timedelta(days=7), pair[0]))
    return candidates


def amount(value):
    text = str(value).replace(" ", "").replace("\u00a0", "").replace(",", ".")
    if not re.fullmatch(r"\d{1,10}(?:\.\d{1,2})?", text):
        return None
    number = Decimal(text)
    return float(number) if 0 < number <= 1_000_000_000 else None


def flag(value):
    if value in (1, "1", True):
        return True
    if value in (0, "0", False):
        return False
    return None


def offer_sample(row, selected):
    if not isinstance(row, dict):
        return None
    try:
        checkin = date_value(row["checkIn"])
        checkout = date_value(row["checkOut"])
        nights = int(row["nights"])
        price = amount(row["price"])
        currency = str(row["currency"])
        if (not row.get("id") or not row.get("hotel") or price is None
                or not re.fullmatch(r"[A-Z]{3}", currency)
                or str(row.get("packetType")) != "0"
                or int(row["adult"]) != selected["adults"] or int(row["child"]) != 0
                or not date_value(selected["checkin_begin"]) <= checkin <= date_value(selected["checkin_end"])
                or not selected["nights_from"] <= nights <= selected["nights_till"]
                or not checkin < checkout):
            return None
        sample = {"hotel": safe_label(row["hotel"]), "star": safe_label(row.get("star")),
                  "meal": safe_label(row.get("meal")), "room": safe_label(row.get("room")),
                  "checkin": checkin.strftime("%Y%m%d"), "checkout": checkout.strftime("%Y%m%d"),
                  "nights": nights, "adults": int(row["adult"]), "children": int(row["child"]),
                  "price": price, "currency": currency,
                  "bookable": flag(row.get("bron")),
                  "grouped": flag(row.get("grouped"))}
        converted = re.fullmatch(r"([\d .,\u00a0]+)\s+([A-Z]{3})", str(row.get("convertedPrice", "")))
        if converted and amount(converted.group(1)):
            sample.update(converted_price=amount(converted.group(1)), converted_currency=converted.group(2))
        availability = str(row.get("hotelAvailability", ""))
        if re.fullmatch(r"[YNFR]{1,8}", availability):
            sample["hotel_availability"] = availability
        freights = row.get("freights", {})
        econom = freights.get("econom", {}) if isinstance(freights, dict) else {}
        for source, target in (("in", "flight_outbound"), ("out", "flight_return")):
            availability_flag = econom.get(source) if isinstance(econom, dict) else None
            if availability_flag in ("Y", "N", "F", "R"):
                sample[target] = availability_flag
        return sample if sample["hotel"] else None
    except (KeyError, TypeError, ValueError, InvalidOperation):
        return None


def remote_price_probe(tokens):
    global SENSITIVE_VALUES
    SENSITIVE_VALUES = tuple(value for raw in tokens.values() if isinstance(raw, str)
                             for value in (raw, raw.strip()) if value)
    checks, selected, samples = [], {}, []
    report = {"mode": "prices", "checks": checks, "search": selected, "samples": samples}
    token = tokens.get("ANEX_API_TOKEN", "").strip()
    if not token:
        checks.append({"check": "configuration", "status": "missing_secret"})
        return report

    def stop(status):
        checks.append({"check": "selection", "status": status})
        raise StopProbe()

    try:
        departure = choose_named(api_data(token, "SearchTour_TOWNFROMS", {}, checks),
                                 (r"москва|moscow",))
        if departure is None:
            stop("no_departures")
        params = {"TOWNFROMINC": positive_id(departure)}
        destination = choose_named(api_data(token, "SearchTour_STATES", params, checks),
                                   (r"турци|turkey|türkiye|\btur\b", r"егип|egypt"))
        if destination is None:
            stop("no_destinations")
        params.update(STATEINC=positive_id(destination), ADULT=2, CHILD=0)
        selected.update(departure=safe_label(departure.get("name")),
                        destination=safe_label(destination.get("name")), adults=2, children=0)
        calendar = api_data(token, "SearchTour_CHECKIN", params, checks)
        dates = available_dates(calendar, dt.datetime.now(dt.timezone.utc).date())
        if not dates:
            stop("no_dates")
        checkin = dates[0][0].strftime("%Y%m%d")
        params.update(CHECKIN_BEG=checkin, CHECKIN_END=checkin)
        selected.update(checkin_begin=checkin, checkin_end=checkin)
        currencies = api_data(token, "SearchTour_CURRENCIES", params, checks)
        currency = choose_named(currencies, (r"\brub\b|руб",))
        if currency is None:
            stop("no_currency")
        params["CURRENCY"] = positive_id(currency)
        selected["currency"] = safe_label(currency.get("alias") or currency.get("currencyISO") or currency.get("name"))
        nights_data = api_data(token, "SearchTour_NIGHTS", params, checks)
        raw_nights = nights_data if isinstance(nights_data, list) else (
            nights_data.get("places") or nights_data.get("nights", []))
        nights = []
        for item in raw_nights:
            value = item.get("id", item.get("nights")) if isinstance(item, dict) else item
            if str(value).isdigit() and 3 <= int(value) <= 14:
                nights.append(int(value))
        if not nights:
            stop("no_nights")
        duration = min(nights, key=lambda value: abs(value - 7))
        params.update(NIGHTS_FROM=duration, NIGHTS_TILL=duration, FREIGHT=1, FILTER=1,
                      PRICEPAGE=1, PARTITION_PRICE=32, SORT="ASC", DYN_SEPARATE=1)
        selected.update(nights_from=duration, nights_till=duration)
        data = api_data(token, "SearchTour_PRICES", params, checks)
        rows = data.get("prices", []) if isinstance(data, dict) else data
        if not isinstance(rows, list):
            checks[-1]["status"] = "invalid_response"
            raise StopProbe()
        checks[-1]["count"] = len(rows)
        report["external_results_not_loaded"] = bool(data.get("searchKey")) if isinstance(data, dict) else False
        valid = [offer_sample(row, selected) for row in rows]
        valid = [sample for sample in valid if sample is not None]
        report["valid_offers"] = len(valid)
        report["bookable_offers"] = sum(sample["bookable"] is True for sample in valid)
        samples.extend(valid[:3])
        if not rows:
            stop("no_prices")
        elif not valid:
            stop("invalid_offer")
        grouped = [row for row in rows if isinstance(row, dict) and flag(row.get("grouped")) is True
                   and offer_sample(row, selected) is not None
                   and positive_id({"id": row.get("hotelKey")})]
        if grouped:
            grouped.sort(key=lambda row: "Y" not in str(row.get("hotelAvailability", ""))
                         or "R" in str(row.get("hotelAvailability", "")))
            chosen = grouped[0]
            expand = dict(params, CATCLAIM=chosen["id"], HOTELS=chosen["hotelKey"])
            expand.pop("PARTITION_PRICE")
            detail = api_data(token, "SearchTour_PRICES", expand, checks, expanded=True)
            detail_rows = detail.get("prices", []) if isinstance(detail, dict) else detail
            if not isinstance(detail_rows, list):
                checks[-1]["status"] = "invalid_response"
                raise StopProbe()
            checks[-1]["count"] = len(detail_rows)
            detailed = [offer_sample(row, selected) for row in detail_rows]
            detailed = [sample for sample in detailed if sample is not None and sample["grouped"] is False]
            report["expanded_offers"] = len(detailed)
            if detailed:
                report["bookable_offers"] = sum(sample["bookable"] is True for sample in detailed)
                samples[:] = detailed[:3]
            else:
                stop("invalid_offer")
    except StopProbe:
        pass
    except (KeyError, TypeError, ValueError):
        checks.append({"check": "selection", "status": "invalid_response"})
    return report


def clean_report(report):
    """Only bounded, fixed-vocabulary diagnostics may leave either process."""
    if isinstance(report, dict) and report.get("mode") == "hotels":
        return clean_hotel_report(report)
    if not isinstance(report, dict) or not isinstance(report.get("checks"), list):
        raise ValueError("invalid report")
    if not 1 <= len(report["checks"]) <= (13 if report.get("mode") == "prices" else 4):
        raise ValueError("invalid checks")
    checks = []
    for item in report["checks"]:
        if item.get("check") not in CHECKS or item.get("status") not in STATUSES:
            raise ValueError("unknown result")
        clean = {"check": item["check"], "status": item["status"]}
        for key in ("count", "http_status", "elapsed_ms", "supplier_code"):
            value = item.get(key)
            if type(value) is int and 0 <= value <= 10_000_000:
                clean[key] = value
        checks.append(clean)
    result = {"ok": all(item["status"] == "ok" for item in checks), "checks": checks}
    if report.get("mode") == "prices":
        result["mode"] = "prices"
        search = report.get("search", {})
        result["search"] = {key: safe_label(search[key]) for key in (
            "departure", "destination", "currency", "checkin_begin", "checkin_end") if key in search}
        for key in ("adults", "children", "nights_from", "nights_till"):
            if type(search.get(key)) is int and 0 <= search[key] <= 100:
                result["search"][key] = search[key]
        for key in ("valid_offers", "bookable_offers", "expanded_offers"):
            if type(report.get(key)) is int and 0 <= report[key] <= 10000:
                result[key] = report[key]
        result["external_results_not_loaded"] = report.get("external_results_not_loaded") is True
        result["samples"] = []
        for sample in report.get("samples", [])[:3]:
            clean = {}
            for key in ("hotel", "star", "meal", "room", "checkin", "checkout", "currency",
                        "converted_currency", "hotel_availability", "flight_outbound", "flight_return"):
                if key in sample:
                    clean[key] = safe_label(sample[key])
            for key in ("nights", "adults", "children", "price", "converted_price"):
                if type(sample.get(key)) in (int, float) and 0 <= sample[key] <= 1_000_000_000:
                    clean[key] = sample[key]
            for key in ("bookable", "grouped"):
                clean[key] = sample.get(key) if type(sample.get(key)) is bool else None
            result["samples"].append(clean)
        result["ok"] = result["ok"] and bool(result["samples"])
    return result


def ssh_probe():
    global SENSITIVE_VALUES
    prices = "--prices" in sys.argv
    hotels = "--hotels" in sys.argv
    names = ("ANEX_API_TOKEN", "ANEX_REFERENCE_TOKEN", "ANYTOOUR_DEPLOY_SSH_KEY",
             "ANYTOOUR_DEPLOY_HOST", "ANYTOOUR_DEPLOY_USER")
    missing = [name for name in names if not os.environ.get(name, "").strip()]
    if missing:
        # Names are our fixed configuration vocabulary, never secret values.
        print("MISSING_SECRETS: " + ", ".join(missing))
        return {"ok": False, "checks": [
            {"check": "configuration", "status": "missing_secret"}]}
    tokens = {name: os.environ[name] for name in names[:2]}
    SENSITIVE_VALUES = tuple(value for name in names
                             for value in (os.environ[name], os.environ[name].strip()) if value)
    host = os.environ["ANYTOOUR_DEPLOY_HOST"].strip()
    user = os.environ["ANYTOOUR_DEPLOY_USER"].strip()
    if host.startswith("-") or any(c.isspace() for c in host + user):
        raise ValueError("invalid SSH target")
    source = Path(__file__).read_text(encoding="utf-8")
    if hotels:
        source = source.rsplit('\nif __name__ == "__main__":', 1)[0]
        source += "\n" + Path(__file__).with_name("anex_hotel_match_probe.py").read_text(encoding="utf-8")
        source += "\nCATALOG_PHP = " + repr(Path(__file__).with_name("anex_catalog_reader.php").read_text(encoding="utf-8").removeprefix("<?php"))
        source += '\nif __name__ == "__main__":\n    sys.exit(main())\n'
    with tempfile.TemporaryDirectory(prefix="anex-probe-", dir=os.environ.get("RUNNER_TEMP")) as temp:
        key = Path(temp) / "ssh_key"
        fd = os.open(str(key), os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "w") as handle:
            handle.write(os.environ["ANYTOOUR_DEPLOY_SSH_KEY"].rstrip() + "\n")
        command = [
            "ssh", "-T", "-i", str(key), "-o", "IdentitiesOnly=yes",
            "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=accept-new",
            "-o", "UserKnownHostsFile=" + str(Path(temp) / "known_hosts"),
            "-o", "ConnectTimeout=15", "-o", "ServerAliveInterval=15",
            "-o", "ServerAliveCountMax=2", "-o", "LogLevel=ERROR",
            "-l", user, host,
            'cd "$HOME/www/anytoour.ru" && python3 -B -c ' + shlex.quote(source)
            + " --remote" + (" --hotels" if hotels else " --prices" if prices else ""),
        ]
        child_env = {k: v for k, v in os.environ.items() if k not in names}
        try:
            completed = subprocess.run(command, input=json.dumps(tokens), text=True,
                                       capture_output=True, timeout=290 if prices or hotels else 110, env=child_env)
        except subprocess.TimeoutExpired:
            return {"ok": False, "checks": [{"check": "ssh", "status": "ssh_timeout"}]}
        if completed.returncode and not completed.stdout.strip():
            status = "ssh_failed"
            if "Permission denied" in completed.stderr:
                status = "ssh_auth_failed"
            elif "REMOTE HOST IDENTIFICATION HAS CHANGED" in completed.stderr:
                status = "ssh_host_key_changed"
            return {"ok": False, "checks": [{"check": "ssh", "status": status}]}
        report = clean_report(json.loads(completed.stdout))
        if completed.returncode and report["ok"]:
            return {"ok": False, "checks": [{"check": "ssh", "status": "ssh_failed"}]}
        return report


def main():
    try:
        if "--hotels" in sys.argv and "remote_hotel_probe" not in globals():
            exec(Path(__file__).with_name("anex_hotel_match_probe.py").read_text(encoding="utf-8"), globals())
        remote = remote_hotel_probe if "--hotels" in sys.argv else remote_price_probe if "--prices" in sys.argv else remote_probe
        report = remote(json.loads(sys.stdin.read(16_384))) if "--remote" in sys.argv else ssh_probe()
        report = clean_report(report)
    except Exception:
        # Do not print exception text: urllib exceptions can include token URLs.
        report = {"ok": False, "checks": [
            {"check": "probe", "status": "unexpected_probe_failure"}]}
    print(json.dumps(report, sort_keys=True))
    return 0 if report["ok"] else 1


if __name__ == "__main__":
    sys.exit(main())
