#!/usr/bin/env python3
"""MATCH #1971 read-only discovery for an already-authorized legacy Tourvisor path.

This operation makes no Tourvisor/supplier call. It reserves an immutable
operation directory, reads a small CURRENT DB summary in a read-only transaction,
and scans the deployed AnyTour tree for legacy Tourvisor integration markers.
Only file paths, marker booleans and identifier/key names are emitted; values and
source lines are never persisted.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
from collections import Counter
from pathlib import Path
from typing import Any

CORE8 = {1, 2, 4, 8, 9, 10, 12, 16}
MAX_FILES = 12000
MAX_BYTES = 2_000_000
TEXT_SUFFIXES = {".php", ".inc", ".env", ".ini", ".json", ".js", ".ts", ".py", ".txt", ".conf", ".yml", ".yaml"}
PRUNE = {".git", "node_modules", "vendor", "upload", "uploads", "images", "image", "cache", "logs", "log", "tmp", "bitrix/cache", "bitrix/managed_cache", "bitrix/stack_cache"}
MARKERS = {
    "legacy_base": b"tourvisor.ru/xml/",
    "authlogin": b"authlogin",
    "authpass": b"authpass",
    "legacy_search": b"search.php",
    "legacy_result": b"result.php",
    "legacy_actualize": b"actualize.php",
}


def dump(obj: Any) -> str:
    return json.dumps(obj, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def write_exclusive(path: Path, raw: bytes) -> str:
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL
    fd = os.open(path, flags, 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as fh:
            fh.write(raw)
            fh.flush()
            os.fsync(fh.fileno())
    finally:
        os.close(fd)
    if path.read_bytes() != raw:
        raise RuntimeError("durable_readback_failed")
    return sha(raw)


def php_reader() -> str:
    return r'''<?php
    declare(strict_types=1);
    error_reporting(0); ob_start();
    function q(PDO $db,string $sql): array {
      if(!str_starts_with(ltrim($sql),'SELECT ') || str_contains($sql,';')) throw new RuntimeException('select_only');
      $r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC); if(count($r)>100000) throw new RuntimeException('row_cap'); return $r;
    }
    try {
      $root=realpath(getcwd()); if(!$root || basename($root)!=='anytoour.ru') throw new RuntimeException('root_guard');
      require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
      $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
      $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
      $out=[];
      $out['obs']=q($db,'SELECT anex_hotel_id,country_id,search_count,last_seen_utc FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id');
      $out['map']=q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id');
      $out['manual']=q($db,'SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id');
      $out['candidates']=q($db,'SELECT anex_hotel_id,catalog_hotel_id,candidate_rank,score FROM anex_hotel_candidates WHERE candidate_rank=1 ORDER BY anex_hotel_id');
      $db->exec('ROLLBACK');
      ob_end_clean(); echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); exit(0);
    } catch(Throwable $e) { try { if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK'); }catch(Throwable $x){} ob_end_clean(); fwrite(STDERR,'read_failed\n'); exit(2); }
    '''


def current_summary() -> dict[str, Any]:
    proc = subprocess.run(
        ["php", "-d", "allow_url_fopen=0", "-d", "display_errors=0", "-r", php_reader().replace("<?php", "", 1)],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
        timeout=120,
    )
    if proc.returncode != 0:
        raise RuntimeError("current_db_read_failed")
    doc = json.loads(proc.stdout)
    if not isinstance(doc, dict):
        raise RuntimeError("current_db_json_invalid")
    mapped = {int(x["anex_hotel_id"]) for x in doc.get("map", []) if x.get("anex_hotel_id")}
    manual = {int(x["anex_hotel_id"]) for x in doc.get("manual", []) if x.get("anex_hotel_id")}
    top = {int(x["anex_hotel_id"]): int(x["catalog_hotel_id"]) for x in doc.get("candidates", []) if x.get("anex_hotel_id") and x.get("catalog_hotel_id")}
    unresolved = []
    for row in doc.get("obs", []):
        aid = int(row.get("anex_hotel_id") or 0)
        cid = int(row.get("country_id") or 0)
        cnt = int(row.get("search_count") or 0)
        if aid <= 0 or cid not in CORE8 or cnt <= 0 or aid in mapped or aid in manual:
            continue
        unresolved.append({"anex_hotel_id": aid, "country_id": cid, "search_count": cnt, "top_local_id": top.get(aid)})
    by_country = Counter(x["country_id"] for x in unresolved)
    return {
        "live_unresolved_anex_count": len(unresolved),
        "live_unresolved_anex_search_weight": sum(x["search_count"] for x in unresolved),
        "live_unresolved_with_top_local_candidate": sum(1 for x in unresolved if x["top_local_id"]),
        "country_counts": {str(k): by_country[k] for k in sorted(by_country)},
        "top_live_targets": unresolved[:40],
    }


def is_pruned(rel: str) -> bool:
    rel = rel.replace("\\", "/").strip("/")
    parts = rel.split("/") if rel else []
    prefixes = ["/".join(parts[:i]) for i in range(1, len(parts) + 1)]
    return any(p in PRUNE or parts[-1:] == [p] for p in prefixes for _ in [0])


def safe_identifiers(text: str) -> list[str]:
    names: set[str] = set()
    for pat in (
        r"define\s*\(\s*['\"]([A-Za-z_][A-Za-z0-9_]*)['\"]",
        r"\b(?:getenv|env)\s*\(\s*['\"]([A-Za-z_][A-Za-z0-9_]*)['\"]",
        r"(?m)^\s*([A-Z][A-Z0-9_]{2,})\s*=",
        r"\$([A-Za-z_][A-Za-z0-9_]*)\s*=",
    ):
        for m in re.finditer(pat, text):
            name = m.group(1)
            low = name.casefold()
            if "tourvisor" in low or low.startswith("tv_") or "authlogin" in low or "authpass" in low:
                names.add(name[:120])
    return sorted(names)


def discover(root: Path) -> dict[str, Any]:
    findings = []
    files_examined = 0
    files_skipped_large = 0
    marker_counts = Counter()
    identifier_names: set[str] = set()
    candidate_pair_files = 0

    for base, dirs, files in os.walk(root):
        relbase = os.path.relpath(base, root).replace("\\", "/")
        if relbase == ".": relbase = ""
        dirs[:] = [d for d in dirs if not is_pruned("/".join(x for x in (relbase, d) if x))]
        for name in files:
            if files_examined >= MAX_FILES:
                break
            path = Path(base) / name
            rel = path.relative_to(root).as_posix()
            if is_pruned(rel):
                continue
            if path.suffix.casefold() not in TEXT_SUFFIXES and name not in {".env", "config.php"}:
                continue
            try:
                size = path.stat().st_size
            except OSError:
                continue
            if size < 0 or size > MAX_BYTES:
                files_skipped_large += 1
                continue
            try:
                raw = path.read_bytes()
            except OSError:
                continue
            files_examined += 1
            low = raw.lower()
            flags = {k: (v in low) for k, v in MARKERS.items()}
            if not any(flags.values()):
                continue
            for k, yes in flags.items():
                if yes: marker_counts[k] += 1
            if flags["authlogin"] and flags["authpass"]:
                candidate_pair_files += 1
            text = raw.decode("utf-8", "ignore")
            ids = safe_identifiers(text)
            identifier_names.update(ids)
            findings.append({"path": rel, "markers": flags, "identifier_names": ids})
        if files_examined >= MAX_FILES:
            break

    findings.sort(key=lambda x: (not (x["markers"]["authlogin"] and x["markers"]["authpass"]), x["path"]))
    return {
        "files_examined": files_examined,
        "files_skipped_large": files_skipped_large,
        "file_limit_reached": files_examined >= MAX_FILES,
        "matching_files": len(findings),
        "candidate_pair_files": candidate_pair_files,
        "marker_counts": dict(sorted(marker_counts.items())),
        "identifier_names": sorted(identifier_names),
        "findings": findings[:200],
        "findings_truncated": len(findings) > 200,
    }


def self_test() -> int:
    assert safe_identifiers("define('TOURVISOR_LOGIN','secret'); $tv_authpass = 'x';") == ["TOURVISOR_LOGIN", "tv_authpass"]
    src = php_reader().upper()
    for word in ("INSERT ", "UPDATE ", "DELETE ", "REPLACE ", "ALTER ", "DROP ", "CREATE ", "TRUNCATE "):
        assert word not in src
    print("legacy-tourvisor-discovery self-test: PASS")
    return 0


def main() -> int:
    if "--self-test" in sys.argv:
        return self_test()
    op = os.environ.get("OPERATION_ID", "")
    source_sha = os.environ.get("MATCH_SOURCE_SHA", "")
    if not re.fullmatch(r"hotel-match-legacy-tv-discovery-1971-20260914-v\d+", op):
        raise RuntimeError("operation_id_required")
    if not re.fullmatch(r"[0-9a-f]{40}", source_sha):
        raise RuntimeError("source_sha_required")
    root = Path.cwd().resolve()
    home = Path.home().resolve()
    if root.name != "anytoour.ru":
        raise RuntimeError("root_guard")
    base = home / ".anytoour-match" / "operations"
    if not base.is_dir():
        raise RuntimeError("operations_root_missing")
    out = base / op
    out.mkdir(mode=0o700, exist_ok=False)
    reservation = {
        "operation_id": op,
        "source_sha": source_sha,
        "state": "reserved_before_db_access",
        "read_only": True,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "database_writes": 0,
        "mapping_writes": 0,
        "no_replay": True,
    }
    write_exclusive(out / "reservation.json", (dump(reservation) + "\n").encode())
    try:
        current = current_summary()
        discovery = discover(root)
        legacy_evidence_present = bool(discovery["marker_counts"].get("legacy_base") or (discovery["marker_counts"].get("authlogin") and discovery["marker_counts"].get("authpass")))
        result = {
            "operation_id": op,
            "source_sha": source_sha,
            "status": "read_only_complete",
            "read_only": True,
            "database_writes": 0,
            "mapping_writes": 0,
            "supplier_calls": 0,
            "tourvisor_calls": 0,
            "legacy_evidence_present": legacy_evidence_present,
            "current": current,
            "discovery": discovery,
            "next_gate": "credentialed_bounded_probe_allowed" if discovery["candidate_pair_files"] > 0 else "authorized_legacy_credential_path_not_located",
            "no_replay": True,
        }
        result_raw = (dump(result) + "\n").encode()
        result_hash = write_exclusive(out / "result.json", result_raw)
        receipt = {
            "operation_id": op,
            "source_sha": source_sha,
            "status": "complete",
            "result_sha256": result_hash,
            "readback_verified": sha((out / "result.json").read_bytes()) == result_hash,
            "supplier_calls": 0,
            "tourvisor_calls": 0,
            "database_writes": 0,
            "mapping_writes": 0,
            "no_replay": True,
        }
        write_exclusive(out / "receipt.json", (dump(receipt) + "\n").encode())
        print(dump({"status": result["status"], "legacy_evidence_present": legacy_evidence_present, "next_gate": result["next_gate"], "live_unresolved_anex_count": current["live_unresolved_anex_count"], "matching_files": discovery["matching_files"], "candidate_pair_files": discovery["candidate_pair_files"]}))
        return 0
    except Exception as exc:
        failure = {"operation_id": op, "source_sha": source_sha, "status": "failed_closed", "reason": type(exc).__name__, "supplier_calls": 0, "tourvisor_calls": 0, "database_writes": 0, "mapping_writes": 0, "no_replay": True}
        try:
            write_exclusive(out / "failure.json", (dump(failure) + "\n").encode())
        except Exception:
            pass
        raise


if __name__ == "__main__":
    raise SystemExit(main())
