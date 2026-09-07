#!/usr/bin/env python3
"""Persist a validated ANEX enrichment checkpoint in isolated AnyTour staging tables."""

import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys
import tempfile


ALLOWED_STATUSES = {"strong_candidate", "review", "unmatched"}
MAX_CANDIDATES = 5


def _text(value, limit):
    if value is None:
        return ""
    value = str(value).strip()
    if len(value) > limit:
        raise ValueError("checkpoint text exceeds limit")
    return value


def _number(value):
    return value if type(value) in (int, float) else None


def sanitize_candidate(candidate, rank):
    identifier = candidate.get("id")
    if type(identifier) is not int or identifier <= 0:
        raise ValueError("invalid AnyTour candidate id")
    return {
        "rank": rank,
        "catalog_hotel_id": identifier,
        "name": _text(candidate.get("name"), 255),
        "country": _text(candidate.get("country"), 255),
        "region": _text(candidate.get("region"), 255),
        "town": _text(candidate.get("town"), 255),
        "address": _text(candidate.get("address"), 1024),
        "latitude": _number(candidate.get("latitude")),
        "longitude": _number(candidate.get("longitude")),
        "score": _number(candidate.get("score")),
        "name_similarity": _number(candidate.get("name_similarity")),
        "distance_m": _number(candidate.get("distance_m")),
        "country_match": candidate.get("country_match") if type(candidate.get("country_match")) is bool else None,
        "address_exact": candidate.get("address_exact") if type(candidate.get("address_exact")) is bool else None,
    }


def sanitize_row(row):
    identifier = row.get("external_id")
    fingerprint = row.get("fingerprint")
    status = row.get("status")
    candidates = row.get("candidates")
    if type(identifier) is not int or identifier <= 0:
        raise ValueError("invalid ANEX hotel id")
    if not isinstance(fingerprint, str) or not re.fullmatch(r"[0-9a-f]{24}", fingerprint):
        raise ValueError("invalid ANEX fingerprint")
    if status not in ALLOWED_STATUSES or not isinstance(candidates, list) or len(candidates) > 256:
        raise ValueError("invalid automated match")
    xml = row.get("xml") if isinstance(row.get("xml"), dict) else {}
    api = row.get("api") if isinstance(row.get("api"), dict) else {}
    if api and api.get("id") != identifier:
        raise ValueError("ANEX XML/Online identity conflict")
    clean = {
        "anex_hotel_id": identifier,
        "fingerprint": fingerprint,
        "original_status": _text(row.get("original_status"), 32),
        "automated_status": status,
        "reason": _text(row.get("reason"), 64),
        "api_xml_relation": _text(row.get("api_xml_relation"), 32),
        "candidate_limit": row.get("candidate_limit") if type(row.get("candidate_limit")) is int else None,
        "candidate_count": len(candidates),
        "checked_at": _text(row.get("checked_at"), 64),
        "xml": {
            "id": identifier,
            "name": _text(xml.get("name"), 255),
            "alternate_name": _text(xml.get("alternate_name"), 255),
            "town_id": xml.get("town_id") if type(xml.get("town_id")) is int else None,
        },
        "api": {
            "id": api.get("id") if type(api.get("id")) is int else None,
            "name": _text(api.get("name"), 255),
            "country": _text(api.get("country"), 255),
            "region": _text(api.get("region"), 255),
            "town": _text(api.get("town"), 255),
            "town_id": api.get("town_id") if type(api.get("town_id")) is int else None,
            "address": _text(api.get("address"), 1024),
            "latitude": _number(api.get("latitude")),
            "longitude": _number(api.get("longitude")),
        },
        "candidates": [sanitize_candidate(item, rank + 1)
                       for rank, item in enumerate(candidates[:MAX_CANDIDATES])],
    }
    encoded = json.dumps(clean, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    clean["row_digest"] = hashlib.sha256(encoded.encode("utf-8")).hexdigest()
    return clean


def load_checkpoint(path):
    raw = Path(path).read_bytes()
    checkpoint = json.loads(raw)
    if checkpoint.get("schema_version") != 2 or not isinstance(checkpoint.get("rows"), list):
        raise ValueError("unsupported checkpoint")
    rows = [sanitize_row(row) for row in checkpoint["rows"]]
    identifiers = [row["anex_hotel_id"] for row in rows]
    if len(identifiers) != len(set(identifiers)):
        raise ValueError("duplicate ANEX hotel id")
    counts = {status: sum(row["automated_status"] == status for row in rows)
              for status in sorted(ALLOWED_STATUSES)}
    if checkpoint.get("processed_total") != len(rows) or checkpoint.get("counts") != counts:
        raise ValueError("checkpoint totals do not match rows")
    meta = {
        "type": "meta",
        "protocol_version": 1,
        "checkpoint_digest": hashlib.sha256(raw).hexdigest(),
        "checkpoint_schema_version": checkpoint["schema_version"],
        "source_run_id": int(os.environ.get("GITHUB_RUN_ID", "0") or 0),
        "source_run_attempt": int(os.environ.get("GITHUB_RUN_ATTEMPT", "0") or 0),
        "processed_total": len(rows),
        "remaining": checkpoint.get("remaining") if type(checkpoint.get("remaining")) is int else 0,
        "strong_candidate_count": counts["strong_candidate"],
        "review_count": counts["review"],
        "unmatched_count": counts["unmatched"],
    }
    return meta, rows


def write_protocol(path, meta, rows):
    with Path(path).open("w", encoding="utf-8") as handle:
        handle.write(json.dumps(meta, ensure_ascii=False, separators=(",", ":")) + "\n")
        for row in rows:
            handle.write(json.dumps({"type": "row", "row": row}, ensure_ascii=False,
                                    separators=(",", ":")) + "\n")
        handle.write('{"type":"commit"}\n')


def ssh_import(checkpoint_path):
    names = ("ANYTOOUR_DEPLOY_SSH_KEY", "ANYTOOUR_DEPLOY_HOST", "ANYTOOUR_DEPLOY_USER")
    if any(not os.environ.get(name, "").strip() for name in names):
        raise ValueError("missing SSH configuration")
    host = os.environ["ANYTOOUR_DEPLOY_HOST"].strip()
    user = os.environ["ANYTOOUR_DEPLOY_USER"].strip()
    if host.startswith("-") or any(char.isspace() for char in host + user):
        raise ValueError("invalid SSH target")
    meta, rows = load_checkpoint(checkpoint_path)
    source = Path(__file__).with_name("anex_staging_writer.php").read_text(encoding="utf-8")
    source = source.removeprefix("<?php")
    with tempfile.TemporaryDirectory(prefix="anex-stage-", dir=os.environ.get("RUNNER_TEMP")) as temp:
        key = Path(temp) / "ssh_key"
        key.write_text(os.environ["ANYTOOUR_DEPLOY_SSH_KEY"].rstrip() + "\n", encoding="utf-8")
        key.chmod(0o600)
        protocol = Path(temp) / "checkpoint.ndjson"
        write_protocol(protocol, meta, rows)
        command = [
            "ssh", "-T", "-i", str(key), "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes",
            "-o", "StrictHostKeyChecking=accept-new", "-o", "UserKnownHostsFile=" + str(Path(temp) / "known_hosts"),
            "-o", "ConnectTimeout=15", "-o", "ServerAliveInterval=15", "-o", "ServerAliveCountMax=2",
            "-o", "LogLevel=ERROR", "-l", user, host,
            'cd "$HOME/www/anytoour.ru" && php -r ' + shlex.quote(source),
        ]
        with protocol.open("rb") as handle:
            completed = subprocess.run(command, stdin=handle, capture_output=True, timeout=900,
                                       env={k: v for k, v in os.environ.items() if k not in names})
    if completed.returncode != 0:
        raise RuntimeError("remote staging import failed")
    report = json.loads(completed.stdout.decode("utf-8"))
    if report.get("status") not in ("imported", "already_imported"):
        raise RuntimeError("remote staging import was not confirmed")
    return report


def main():
    try:
        artifact_dir = Path(os.environ["ANEX_CATALOG_ARTIFACT_DIR"])
        checkpoint = artifact_dir / "anex-hotel-geo-enrichment.json"
        report = ssh_import(checkpoint)
        (artifact_dir / "anex-staging-import.json").write_text(
            json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        print(json.dumps(report, sort_keys=True))
        return 0
    except Exception:
        print('{"status":"staging_import_failed"}')
        return 1


if __name__ == "__main__":
    sys.exit(main())
