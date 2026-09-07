#!/usr/bin/env python3
"""Read-only ANEX access checks, executed in memory on the AnyTour SSH host.

No application bootstrap, database access, booking, persistent remote files, or
raw supplier responses. Tokens travel in SSH stdin, never in SSH arguments.
"""

import json
import os
from pathlib import Path
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
}
CHECKS = {"api_townfroms", "reference_currentstamp", "reference_states",
          "reference_routes", "configuration", "ssh", "probe"}


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


def clean_report(report):
    """Only bounded, fixed-vocabulary diagnostics may leave either process."""
    if not isinstance(report, dict) or not isinstance(report.get("checks"), list):
        raise ValueError("invalid report")
    if not 1 <= len(report["checks"]) <= 4:
        raise ValueError("invalid checks")
    checks = []
    for item in report["checks"]:
        if item.get("check") not in CHECKS or item.get("status") not in STATUSES:
            raise ValueError("unknown result")
        clean = {"check": item["check"], "status": item["status"]}
        for key in ("count", "http_status", "elapsed_ms"):
            value = item.get(key)
            if type(value) is int and 0 <= value <= 10_000_000:
                clean[key] = value
        checks.append(clean)
    return {"ok": all(item["status"] == "ok" for item in checks), "checks": checks}


def ssh_probe():
    names = ("ANEX_API_TOKEN", "ANEX_REFERENCE_TOKEN", "ANYTOOUR_DEPLOY_SSH_KEY",
             "ANYTOOUR_DEPLOY_HOST", "ANYTOOUR_DEPLOY_USER")
    missing = [name for name in names if not os.environ.get(name, "").strip()]
    if missing:
        # Names are our fixed configuration vocabulary, never secret values.
        print("MISSING_SECRETS: " + ", ".join(missing))
        return {"ok": False, "checks": [
            {"check": "configuration", "status": "missing_secret"}]}
    tokens = {name: os.environ[name] for name in names[:2]}
    host = os.environ["ANYTOOUR_DEPLOY_HOST"].strip()
    user = os.environ["ANYTOOUR_DEPLOY_USER"].strip()
    if host.startswith("-") or any(c.isspace() for c in host + user):
        raise ValueError("invalid SSH target")
    source = Path(__file__).read_text(encoding="utf-8")
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
            'cd "$HOME/www/anytoour.ru" && python3 -B -c ' + shlex.quote(source) + " --remote",
        ]
        child_env = {k: v for k, v in os.environ.items() if k not in names}
        try:
            completed = subprocess.run(command, input=json.dumps(tokens), text=True,
                                       capture_output=True, timeout=110, env=child_env)
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
        report = remote_probe(json.loads(sys.stdin.read(16_384))) if "--remote" in sys.argv else ssh_probe()
        report = clean_report(report)
    except Exception:
        # Do not print exception text: urllib exceptions can include token URLs.
        report = {"ok": False, "checks": [
            {"check": "probe", "status": "unexpected_probe_failure"}]}
    print(json.dumps(report, sort_keys=True))
    return 0 if report["ok"] else 1


if __name__ == "__main__":
    sys.exit(main())
