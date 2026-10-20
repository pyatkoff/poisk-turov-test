#!/usr/bin/env python3
"""MATCH #1971 targeted read-only discovery for an old Tourvisor integration.

The operation makes zero supplier/Tourvisor calls and zero writes. It scans only
production-search/config paths on the deployed AnyTour site and emits safe marker
metadata; source contents and credential values are never persisted.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
from collections import Counter, deque
from pathlib import Path
from typing import Any

CORE8 = {1, 2, 4, 8, 9, 10, 12, 16}
TARGET_DIRS = ("poisk-turov", "data", "rb", "cgi-bin", "hot", "feed")
MAX_FILES = 20000
MAX_DIRS = 6000
MAX_BYTES = 2_000_000
TEXT_SUFFIXES = {".php", ".inc", ".env", ".ini", ".json", ".js", ".ts", ".py", ".txt", ".conf", ".yml", ".yaml"}
PRUNE_NAMES = {".git", "node_modules", "vendor", "upload", "uploads", "images", "image", "cache", "logs", "log", "tmp", "managed_cache", "stack_cache", "_preview"}
MARKERS = {
    "legacy_base": b"tourvisor.ru/xml",
    "authlogin": b"authlogin",
    "authpass": b"authpass",
    "legacy_search": b"/xml/search.php",
    "legacy_result": b"/xml/result.php",
    "legacy_actualize": b"/xml/actualize.php",
}


def dump(v: Any) -> str:
    return json.dumps(v, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def write_exclusive(path: Path, raw: bytes) -> str:
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as fh:
            fh.write(raw); fh.flush(); os.fsync(fh.fileno())
    finally:
        os.close(fd)
    if path.read_bytes() != raw:
        raise RuntimeError("durable_readback_failed")
    return sha(raw)


def php_reader() -> str:
    return r'''<?php
    declare(strict_types=1); error_reporting(0); ob_start();
    function q(PDO $db,string $sql): array { if(!str_starts_with(ltrim($sql),'SELECT ') || str_contains($sql,';')) throw new RuntimeException('select_only'); $r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC); if(count($r)>100000) throw new RuntimeException('row_cap'); return $r; }
    try {
      $root=realpath(getcwd()); if(!$root || basename($root)!=='anytoour.ru') throw new RuntimeException('root_guard');
      require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
      $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
      $out=[];
      $out['obs']=q($db,'SELECT anex_hotel_id,country_id,search_count FROM anex_search_hotel_observations ORDER BY search_count DESC,anex_hotel_id');
      $out['map']=q($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id');
      $out['manual']=q($db,'SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id');
      $db->exec('ROLLBACK'); ob_end_clean(); echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); exit(0);
    } catch(Throwable $e) { try { if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK'); }catch(Throwable $x){} ob_end_clean(); fwrite(STDERR,'read_failed\n'); exit(2); }
    '''


def current_summary() -> dict[str, Any]:
    p = subprocess.run(["php", "-d", "allow_url_fopen=0", "-d", "display_errors=0", "-r", php_reader().replace("<?php", "", 1)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=120)
    if p.returncode != 0:
        raise RuntimeError("current_db_read_failed")
    doc = json.loads(p.stdout)
    mapped = {int(x["anex_hotel_id"]) for x in doc.get("map", []) if x.get("anex_hotel_id")}
    manual = {int(x["anex_hotel_id"]) for x in doc.get("manual", []) if x.get("anex_hotel_id")}
    rows = []
    for x in doc.get("obs", []):
        aid, cid, cnt = int(x.get("anex_hotel_id") or 0), int(x.get("country_id") or 0), int(x.get("search_count") or 0)
        if aid > 0 and cid in CORE8 and cnt > 0 and aid not in mapped and aid not in manual:
            rows.append((aid, cid, cnt))
    cc = Counter(cid for _, cid, _ in rows)
    return {"live_unresolved_anex_count": len(rows), "live_unresolved_anex_search_weight": sum(x[2] for x in rows), "country_counts": {str(k): cc[k] for k in sorted(cc)}}


def boundary_ok(path: Path) -> bool:
    try:
        return "anytoour.ru" in path.resolve(strict=True).parts
    except (OSError, RuntimeError):
        return False


def safe_identifiers(text: str) -> list[str]:
    names: set[str] = set()
    patterns = (
        r"define\s*\(\s*['\"]([A-Za-z_][A-Za-z0-9_]*)['\"]",
        r"\b(?:getenv|env)\s*\(\s*['\"]([A-Za-z_][A-Za-z0-9_]*)['\"]",
        r"(?m)^\s*([A-Z][A-Z0-9_]{2,})\s*=",
        r"\$([A-Za-z_][A-Za-z0-9_]*)\s*=",
    )
    for pat in patterns:
        for m in re.finditer(pat, text):
            n = m.group(1)[:120]; low = n.casefold()
            if "tourvisor" in low or low.startswith("tv_") or "authlogin" in low or "authpass" in low:
                names.add(n)
    return sorted(names)


def is_login_identifier(name: str) -> bool:
    low = name.casefold()
    return any(x in low for x in ("login", "user", "authlogin"))


def is_pass_identifier(name: str) -> bool:
    low = name.casefold()
    return any(x in low for x in ("pass", "password", "authpass", "secret"))


def scan_file(path: Path, rel: str, state: dict[str, Any]) -> None:
    if state["files_examined"] >= MAX_FILES:
        return
    if path.suffix.casefold() not in TEXT_SUFFIXES and path.name not in {".env", "config.php"}:
        return
    if not boundary_ok(path):
        state["boundary_rejected"] += 1; return
    try:
        size = path.stat().st_size
    except OSError:
        state["scan_errors"] += 1; return
    if size < 0 or size > MAX_BYTES:
        state["files_skipped_large"] += 1; return
    try:
        raw = path.read_bytes()
    except OSError:
        state["scan_errors"] += 1; return
    state["files_examined"] += 1
    low = raw.lower()
    flags = {k: (v in low) for k, v in MARKERS.items()}
    text = raw.decode("utf-8", "ignore")
    ids = safe_identifiers(text)
    state["identifier_names"].update(ids)
    if not any(flags.values()) and not ids:
        return
    for k, yes in flags.items():
        if yes: state["marker_counts"][k] += 1
    if flags["authlogin"] and flags["authpass"]:
        state["candidate_pair_files"] += 1
    state["findings"].append({"path": rel, "markers": flags, "identifier_names": ids})


def targeted_discover(root: Path) -> dict[str, Any]:
    state: dict[str, Any] = {
        "files_examined": 0, "dirs_examined": 0, "files_skipped_large": 0, "scan_errors": 0, "boundary_rejected": 0,
        "marker_counts": Counter(), "identifier_names": set(), "candidate_pair_files": 0, "findings": [], "targets": {},
    }
    # Root regular/config files only; do not descend globally.
    try:
        root_entries = sorted(root.iterdir(), key=lambda p: p.name.casefold())
    except OSError:
        raise RuntimeError("root_list_failed")
    for e in root_entries:
        try:
            if e.is_file(): scan_file(e, e.name, state)
        except OSError:
            state["scan_errors"] += 1

    visited: set[str] = set()
    for target_name in TARGET_DIRS:
        target = root / target_name
        if not target.exists() or not target.is_dir():
            state["targets"][target_name] = {"present": False, "files": 0, "dirs": 0}
            continue
        before_f, before_d = state["files_examined"], state["dirs_examined"]
        q: deque[tuple[Path, str]] = deque([(target, target_name)])
        while q and state["files_examined"] < MAX_FILES and state["dirs_examined"] < MAX_DIRS:
            d, rel = q.popleft()
            if not boundary_ok(d):
                state["boundary_rejected"] += 1; continue
            try:
                real = str(d.resolve(strict=True))
            except (OSError, RuntimeError):
                state["scan_errors"] += 1; continue
            if real in visited: continue
            visited.add(real); state["dirs_examined"] += 1
            try:
                entries = sorted(d.iterdir(), key=lambda p: p.name.casefold())
            except OSError:
                state["scan_errors"] += 1; continue
            for e in entries:
                child_rel = rel + "/" + e.name
                if e.name in PRUNE_NAMES: continue
                try:
                    if e.is_dir(): q.append((e, child_rel))
                    elif e.is_file(): scan_file(e, child_rel, state)
                except OSError:
                    state["scan_errors"] += 1
        state["targets"][target_name] = {"present": True, "files": state["files_examined"] - before_f, "dirs": state["dirs_examined"] - before_d}
        if state["files_examined"] >= MAX_FILES or state["dirs_examined"] >= MAX_DIRS:
            break

    ids = sorted(state["identifier_names"])
    markers = dict(sorted(state["marker_counts"].items()))
    has_login_id = any(is_login_identifier(x) for x in ids)
    has_pass_id = any(is_pass_identifier(x) for x in ids)
    legacy_code = bool(markers.get("legacy_base") or markers.get("legacy_search") or markers.get("legacy_result") or markers.get("legacy_actualize"))
    credential_candidate = state["candidate_pair_files"] > 0 or (legacy_code and has_login_id and has_pass_id)
    limits_hit = state["files_examined"] >= MAX_FILES or state["dirs_examined"] >= MAX_DIRS
    complete = not limits_hit and state["scan_errors"] == 0
    findings = sorted(state["findings"], key=lambda x: (not (x["markers"]["authlogin"] and x["markers"]["authpass"]), x["path"]))
    return {
        "files_examined": state["files_examined"], "dirs_examined": state["dirs_examined"], "files_skipped_large": state["files_skipped_large"],
        "scan_errors": state["scan_errors"], "boundary_rejected": state["boundary_rejected"], "file_limit_reached": state["files_examined"] >= MAX_FILES,
        "dir_limit_reached": state["dirs_examined"] >= MAX_DIRS, "complete": complete, "targets": state["targets"], "marker_counts": markers,
        "identifier_names": ids, "candidate_pair_files": state["candidate_pair_files"], "legacy_code_present": legacy_code,
        "credential_candidate_present": credential_candidate, "matching_files": len(findings), "findings": findings[:200], "findings_truncated": len(findings) > 200,
    }


def self_test() -> int:
    assert safe_identifiers("define('TOURVISOR_LOGIN','x'); $TV_PASSWORD='y';") == ["TOURVISOR_LOGIN", "TV_PASSWORD"]
    src = php_reader().upper()
    for w in ("INSERT ", "UPDATE ", "DELETE ", "REPLACE ", "ALTER ", "DROP ", "CREATE ", "TRUNCATE "):
        assert w not in src
    print("legacy-tourvisor-targeted-v3 self-test: PASS")
    return 0


def main() -> int:
    if "--self-test" in sys.argv: return self_test()
    op = os.environ.get("OPERATION_ID", ""); source_sha = os.environ.get("MATCH_SOURCE_SHA", "")
    if op != "hotel-match-legacy-tv-discovery-1971-20260914-v3": raise RuntimeError("operation_id_required")
    if not re.fullmatch(r"[0-9a-f]{40}", source_sha): raise RuntimeError("source_sha_required")
    root = Path.cwd().resolve(); home = Path.home().resolve()
    if root.name != "anytoour.ru" or "anytoour.ru" not in root.parts: raise RuntimeError("root_guard")
    base = home / ".anytoour-match" / "operations"
    if not base.is_dir(): raise RuntimeError("operations_root_missing")
    out = base / op; out.mkdir(mode=0o700, exist_ok=False)
    reservation = {"operation_id": op, "source_sha": source_sha, "state": "reserved_before_db_access", "read_only": True, "supplier_calls": 0, "tourvisor_calls": 0, "database_writes": 0, "mapping_writes": 0, "no_replay": True}
    write_exclusive(out / "reservation.json", (dump(reservation) + "\n").encode())
    try:
        current = current_summary(); discovery = targeted_discover(root)
        if discovery["credential_candidate_present"]:
            gate = "legacy_credential_candidate_present"
        elif discovery["legacy_code_present"] and discovery["complete"]:
            gate = "legacy_code_present_credentials_not_located"
        elif discovery["complete"]:
            gate = "legacy_production_path_not_located"
        else:
            gate = "targeted_discovery_inconclusive"
        result = {"operation_id": op, "source_sha": source_sha, "status": "read_only_complete", "read_only": True, "database_writes": 0, "mapping_writes": 0, "supplier_calls": 0, "tourvisor_calls": 0, "current": current, "discovery": discovery, "next_gate": gate, "no_replay": True}
        raw = (dump(result) + "\n").encode(); result_hash = write_exclusive(out / "result.json", raw)
        receipt = {"operation_id": op, "source_sha": source_sha, "status": "complete", "result_sha256": result_hash, "readback_verified": sha((out / "result.json").read_bytes()) == result_hash, "supplier_calls": 0, "tourvisor_calls": 0, "database_writes": 0, "mapping_writes": 0, "no_replay": True}
        write_exclusive(out / "receipt.json", (dump(receipt) + "\n").encode())
        print(dump({"status": "read_only_complete", "next_gate": gate, "files_examined": discovery["files_examined"], "dirs_examined": discovery["dirs_examined"], "matching_files": discovery["matching_files"], "credential_candidate_present": discovery["credential_candidate_present"], "live_unresolved_anex_count": current["live_unresolved_anex_count"]}))
        return 0
    except Exception as exc:
        try: write_exclusive(out / "failure.json", (dump({"operation_id": op, "source_sha": source_sha, "status": "failed_closed", "reason": type(exc).__name__, "supplier_calls": 0, "tourvisor_calls": 0, "database_writes": 0, "mapping_writes": 0, "no_replay": True}) + "\n").encode())
        except Exception: pass
        raise


if __name__ == "__main__": raise SystemExit(main())
