#!/usr/bin/env python3
"""Import the owner's exact/strong preview links through the existing AnyTour DB helper."""

import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys
import tempfile


APPROVAL_POLICY = "owner_exact_and_strong_20260908"
MAX_ID = 2_147_483_647
MAX_ROWS = 100_000
ROW_KEYS = {"anex_hotel_id", "catalog_hotel_id", "match_class", "reason", "source_row_digest"}
SOURCE_FILES = {"catalog_sha256": "anex-hotel-catalog-match.json",
                "geo_sha256": "anex-hotel-geo-enrichment.json"}


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"),
                      allow_nan=False).encode("utf-8")


def positive_id(value):
    if type(value) is not int or not 0 < value <= MAX_ID:
        raise ValueError("invalid hotel id")
    return value


def valid_digest(value):
    return isinstance(value, str) and re.fullmatch(r"[0-9a-f]{64}", value) is not None


def sanitize_row(row):
    if not isinstance(row, dict) or set(row) != ROW_KEYS:
        raise ValueError("invalid mapping row schema")
    positive_id(row["anex_hotel_id"])
    positive_id(row["catalog_hotel_id"])
    if row["match_class"] not in ("exact", "strong_candidate"):
        raise ValueError("unapproved mapping class")
    if (not isinstance(row["reason"], str) or not row["reason"]
            or len(row["reason"].encode("utf-8")) > 512
            or not valid_digest(row["source_row_digest"])):
        raise ValueError("invalid mapping evidence")
    return dict(row)


def validate_sources(path, document):
    sources = document["sources"]
    documents = {}
    for key, filename in SOURCE_FILES.items():
        raw = Path(path).with_name(filename).read_bytes()
        if hashlib.sha256(raw).hexdigest() != sources[key]:
            raise ValueError("mapping source digest mismatch")
        documents[key] = json.loads(raw)
    # Reuse the deterministic builder to validate source totals, provenance,
    # chosen targets and the full approved set, including newer geo overrides.
    builder_path = Path(__file__).with_name("anex_search_mapping_builder.py")
    spec = importlib.util.spec_from_file_location("anex_mapping_builder", builder_path)
    builder = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(builder)
    expected = builder.build_payload(documents["catalog_sha256"], documents["geo_sha256"], sources)
    if document != expected:
        raise ValueError("mapping disagrees with approved source evidence")


def load_mapping(path):
    raw = Path(path).read_bytes()
    document = json.loads(raw)
    if (not isinstance(document, dict) or type(document.get("schema_version")) is not int
            or document["schema_version"] != 1 or document.get("scope") != "preview"
            or document.get("approval_policy") != APPROVAL_POLICY
            or not isinstance(document.get("rows"), list)
            or not 0 < len(document["rows"]) <= MAX_ROWS):
        raise ValueError("unsupported mapping schema or policy")
    sources = document.get("sources")
    if (not isinstance(sources, dict) or set(sources) != set(SOURCE_FILES)
            or not all(valid_digest(value) for value in sources.values())):
        raise ValueError("invalid mapping source hashes")
    rows = sorted((sanitize_row(row) for row in document["rows"]), key=lambda row: row["anex_hotel_id"])
    if len({row["anex_hotel_id"] for row in rows}) != len(rows):
        raise ValueError("duplicate ANEX hotel id")
    counts = {"exact": sum(row["match_class"] == "exact" for row in rows),
              "strong": sum(row["match_class"] == "strong_candidate" for row in rows),
              "total": len(rows), "unique_catalog_hotels": len({row["catalog_hotel_id"] for row in rows})}
    supplied_counts = document.get("counts")
    if (not isinstance(supplied_counts, dict) or supplied_counts != counts
            or any(type(value) is not int for value in supplied_counts.values())):
        raise ValueError("mapping totals do not match rows")
    validate_sources(path, document)
    rows_digest = hashlib.sha256(b"".join(canonical(row) + b"\n" for row in rows)).hexdigest()
    meta = {"type": "meta", "protocol_version": 1, "schema_version": 1, "scope": "preview",
            "approval_policy": APPROVAL_POLICY, "mapping_digest": hashlib.sha256(raw).hexdigest(),
            "rows_digest": rows_digest, "sources": sources, "counts": counts}
    return meta, rows


def write_protocol(path, meta, rows):
    with Path(path).open("wb") as handle:
        handle.write(canonical(meta) + b"\n")
        for row in rows:
            handle.write(canonical({"type": "row", "row": row}) + b"\n")
        handle.write(canonical({"type": "commit", "mapping_digest": meta["mapping_digest"],
                                "rows_digest": meta["rows_digest"]}) + b"\n")


def ssh_import(mapping_path, gap_checkpoint=None, observed_checkpoint=None, complete_review_checkpoint=None,
               saved_review_checkpoint=None, cached_review_checkpoint=None,
               cached_detail_alias_checkpoint=None, link_review_checkpoint=None):
    checkpoints = (gap_checkpoint, observed_checkpoint, complete_review_checkpoint,
                   saved_review_checkpoint, cached_review_checkpoint, cached_detail_alias_checkpoint,
                   link_review_checkpoint)
    if sum(p is not None for p in checkpoints) > 1:
        raise ValueError('choose one independent checkpoint')
    if all(p is None for p in checkpoints):
        meta, rows = load_mapping(mapping_path)
    else:
        if link_review_checkpoint is not None:
            from anex_tourvisor_link_import import approved_delta
            document = approved_delta(link_review_checkpoint)
        elif cached_detail_alias_checkpoint is not None:
            from anex_search3_cached_detail_alias_review import approved_delta
            document = approved_delta(cached_detail_alias_checkpoint)
        elif cached_review_checkpoint is not None:
            from anex_search3_cached_review import approved_delta
            document = approved_delta(cached_review_checkpoint)
        elif saved_review_checkpoint is not None:
            from anex_search3_saved_review import approved_delta
            document = approved_delta(saved_review_checkpoint)
        elif complete_review_checkpoint is not None:
            from anex_search3_complete_review import approved_delta
            document = approved_delta(complete_review_checkpoint)
        elif observed_checkpoint is not None:
            from anex_search3_observed_queue import approved_delta
            document = approved_delta(observed_checkpoint)
        else:
            from anex_search3_gap_queue import approved_delta
            document = approved_delta(gap_checkpoint)
        rows = [sanitize_row(r) for r in document['rows']]
        Path(mapping_path).write_bytes(canonical(document) + b'\n')
        if not rows:
            return {'status': 'no_new_strong_candidates', 'inserted': 0, 'updated': 0, 'input_count': 0}
        meta = {k: v for k, v in document.items() if k != 'rows'}
        meta.update(type='meta', protocol_version=1, mapping_digest=hashlib.sha256(Path(mapping_path).read_bytes()).hexdigest(),
                    rows_digest=hashlib.sha256(b''.join(canonical(row) + b'\n' for row in rows)).hexdigest())
    names = ("ANYTOOUR_DEPLOY_SSH_KEY", "ANYTOOUR_DEPLOY_HOST", "ANYTOOUR_DEPLOY_USER")
    if any(not os.environ.get(name, "").strip() for name in names):
        raise ValueError("missing SSH configuration")
    host, user = (os.environ[name].strip() for name in names[1:])
    if host.startswith("-") or user.startswith("-") or any(char.isspace() for char in host + user):
        raise ValueError("invalid SSH target")
    source = Path(__file__).with_name("anex_search_mapping_writer.php").read_text(encoding="utf-8").removeprefix("<?php")
    with tempfile.TemporaryDirectory(prefix="anex-mapping-", dir=os.environ.get("RUNNER_TEMP")) as temp:
        key = Path(temp) / "ssh_key"
        key.write_text(os.environ[names[0]].rstrip() + "\n", encoding="utf-8")
        key.chmod(0o600)
        protocol = Path(temp) / "mapping.ndjson"
        write_protocol(protocol, meta, rows)
        command = ["ssh", "-T", "-i", str(key), "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes",
                   "-o", "StrictHostKeyChecking=accept-new", "-o", "UserKnownHostsFile=" + str(Path(temp) / "known_hosts"),
                   "-o", "ConnectTimeout=15", "-o", "ServerAliveInterval=15", "-o", "ServerAliveCountMax=2",
                   "-o", "LogLevel=ERROR", "-l", user, host,
                   'cd "$HOME/www/anytoour.ru" && php -r ' + shlex.quote(source)]
        with protocol.open("rb") as handle:
            completed = subprocess.run(command, stdin=handle, capture_output=True, timeout=900,
                                       env={k: v for k, v in os.environ.items()
                                            if k not in names and not k.startswith("ANEX_")})
    if completed.returncode != 0:
        raise RuntimeError("remote mapping import failed")
    report = json.loads(completed.stdout.decode("utf-8"))
    if (not isinstance(report, dict) or report.get("status") not in ("imported", "already_imported")
            or report.get("mapping_digest") != meta["mapping_digest"]
            or report.get("input_count") != len(rows)):
        raise RuntimeError("remote mapping import was not confirmed")
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--mapping", type=Path)
    parser.add_argument("--report", type=Path)
    parser.add_argument("--gap-checkpoint", type=Path)
    parser.add_argument("--observed-checkpoint", type=Path)
    args = parser.parse_args()
    try:
        artifact_dir = Path(os.environ.get("ANEX_CATALOG_ARTIFACT_DIR", "."))
        mapping = args.mapping or artifact_dir / "anex-search-mappings.json"
        report = ssh_import(mapping, args.gap_checkpoint, args.observed_checkpoint)
        (args.report or mapping.with_name("anex-search-mapping-import.json")).write_text(
            json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        print(json.dumps(report, sort_keys=True))
        return 0
    except Exception:
        print('{"status":"mapping_import_failed"}')
        return 1


if __name__ == "__main__":
    sys.exit(main())
