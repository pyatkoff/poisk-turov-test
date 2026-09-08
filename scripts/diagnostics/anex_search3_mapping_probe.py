#!/usr/bin/env python3
"""Run one initial supplier search through the deployed ANEX preview mapping path."""

import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import tempfile


def run_probe():
    names = ("ANYTOOUR_DEPLOY_SSH_KEY", "ANYTOOUR_DEPLOY_HOST", "ANYTOOUR_DEPLOY_USER")
    if any(not os.environ.get(name, "").strip() for name in names):
        raise ValueError("missing SSH configuration")
    host, user = (os.environ[name].strip() for name in names[1:])
    if host.startswith("-") or any(char.isspace() for char in host + user):
        raise ValueError("invalid SSH target")
    source = Path(__file__).with_name("anex_search3_mapping_runner.php").read_text(encoding="utf-8").removeprefix("<?php")
    with tempfile.TemporaryDirectory(prefix="anex-search3-probe-", dir=os.environ.get("RUNNER_TEMP")) as temp:
        key = Path(temp) / "ssh_key"
        key.write_text(os.environ[names[0]].rstrip() + "\n", encoding="utf-8")
        key.chmod(0o600)
        command = ["ssh", "-T", "-i", str(key), "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes",
                   "-o", "StrictHostKeyChecking=accept-new", "-o", "UserKnownHostsFile=" + str(Path(temp) / "known_hosts"),
                   "-o", "ConnectTimeout=15", "-o", "ServerAliveInterval=15", "-o", "ServerAliveCountMax=2",
                   "-o", "LogLevel=ERROR", "-l", user, host,
                   'cd "$HOME/www/anytoour.ru/_preview/search3-anex-candidate" && '
                   'php -d display_errors=0 -d log_errors=0 -r ' + shlex.quote(source)]
        completed = subprocess.run(command, capture_output=True, timeout=240,
                                   env={k: v for k, v in os.environ.items()
                                        if k not in names + ("ANEX_API_TOKEN", "ANEX_REFERENCE_TOKEN")})
    if len(completed.stdout) > 100000:
        raise ValueError("invalid probe output")
    report = json.loads(completed.stdout)
    if (not isinstance(report, dict) or report.get("schema_version") != 1 or report.get("scope") != "preview"
            or report.get("status") not in ("ok", "empty", "no_mapped_offers")
            and not re.fullmatch(r"ANEX_[A-Z_]{1,70}", str(report.get("status", "")))):
        raise ValueError("invalid probe report")
    if completed.returncode and report.get("ok"):
        raise ValueError("unconfirmed probe result")
    return report


def main():
    output = Path(os.environ["ANEX_CATALOG_ARTIFACT_DIR"]) / "anex-search3-mapping-probe.json"
    try:
        report = run_probe()
    except Exception:
        report = {"schema_version": 1, "scope": "preview", "ok": False,
                  "status": "ANEX_SEARCH3_PROBE_ERROR", "mapping_coverage_observed": False}
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(report, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, sort_keys=True))
    return 0 if report.get("ok") is True else 1


if __name__ == "__main__":
    raise SystemExit(main())
