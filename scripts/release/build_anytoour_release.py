#!/usr/bin/env python3
"""Build deterministic, target-specific AnyTour release artifacts from one Git SHA."""

from __future__ import annotations

import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import tarfile
import tempfile
from typing import Iterable


SCHEMA_VERSION = 1
FULL_SHA_RE = re.compile(r"[0-9a-f]{40}")
HEX_256_RE = re.compile(r"[0-9a-f]{64}")

PUBLIC_TARGET = "anytoour-public"
LEGACY_TARGET = "anytoour-legacy-lead"

PUBLIC_OVERRIDES = {
    "lead-adapter-v2.php": "v2/lead-bridge-v1.php",
    "search-page-v2.php": "v2/index.php",
    "index.php": "v2/home-entry-v1.php",
}

LEGACY_FILES = (
    "v2/lead-receiver-v1.php",
    "v2/lead-adapter-v2.php",
    "v2/lead-price-v1.php",
    "v2/lead-idempotency-v1.php",
)

FORBIDDEN_TARGET_PARTS = {
    ".anytoour-bridge-secret",
    "config.php",
    "site_conf.php",
}

RESERVED_PUBLIC_CONTROL_NAMES = {
    "release.json",
}


class ReleaseBuildError(RuntimeError):
    """Raised when an artifact cannot be built without weakening its contract."""


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def canonical_json(value: object) -> bytes:
    return (
        json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    ).encode("utf-8")


def validate_full_sha(value: str, label: str = "release SHA") -> str:
    if not FULL_SHA_RE.fullmatch(value):
        raise ReleaseBuildError(f"{label} must be exactly 40 lowercase hex characters")
    return value


def validate_sha256(value: str, label: str = "SHA-256") -> str:
    if not HEX_256_RE.fullmatch(value):
        raise ReleaseBuildError(f"{label} must be exactly 64 lowercase hex characters")
    return value


def validate_relative_path(value: str, label: str = "path") -> PurePosixPath:
    if (
        not value
        or "\\" in value
        or any(ord(char) < 32 or ord(char) == 127 or char.isspace() for char in value)
    ):
        raise ReleaseBuildError(f"{label} contains an empty/unsafe character path")
    raw_parts = value.split("/")
    if any(part in {"", ".", ".."} for part in raw_parts):
        raise ReleaseBuildError(f"{label} is not a safe relative POSIX path: {value!r}")
    path = PurePosixPath(value)
    if path.is_absolute():
        raise ReleaseBuildError(f"{label} is not a safe relative POSIX path: {value!r}")
    return path


def run_git(repo: Path, *args: str) -> bytes:
    process = subprocess.run(
        ["git", "-C", os.fspath(repo), *args],
        check=False,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    if process.returncode != 0:
        detail = process.stderr.decode("utf-8", errors="replace").strip()
        raise ReleaseBuildError(f"git {' '.join(args)} failed: {detail}")
    return process.stdout


def resolve_release(repo: Path, release_sha: str) -> tuple[str, str]:
    release_sha = validate_full_sha(release_sha)
    resolved = run_git(repo, "rev-parse", "--verify", f"{release_sha}^{{commit}}")
    resolved_sha = resolved.decode("ascii").strip()
    if resolved_sha != release_sha:
        raise ReleaseBuildError(
            f"release SHA resolved to a different commit: {resolved_sha}"
        )
    tree_sha = run_git(repo, "rev-parse", f"{release_sha}^{{tree}}").decode("ascii").strip()
    v2_tree_sha = run_git(repo, "rev-parse", f"{release_sha}:v2").decode("ascii").strip()
    validate_full_sha(tree_sha, "repository tree SHA")
    validate_full_sha(v2_tree_sha, "v2 tree SHA")
    return tree_sha, v2_tree_sha


def list_v2_blobs(repo: Path, release_sha: str) -> dict[str, tuple[str, str]]:
    raw = run_git(repo, "ls-tree", "-r", "-z", release_sha, "--", "v2")
    entries: dict[str, tuple[str, str]] = {}
    for row in raw.split(b"\0"):
        if not row:
            continue
        try:
            metadata, raw_path = row.split(b"\t", 1)
            mode, object_type, raw_oid = metadata.split(b" ")
            source_path = raw_path.decode("utf-8")
            oid = raw_oid.decode("ascii")
        except (ValueError, UnicodeDecodeError) as exc:
            raise ReleaseBuildError("unable to parse git ls-tree output") from exc
        validate_relative_path(source_path, "source path")
        if not source_path.startswith("v2/"):
            raise ReleaseBuildError(f"source escaped v2/: {source_path}")
        if object_type != b"blob" or mode not in {b"100644", b"100755"}:
            raise ReleaseBuildError(
                f"unsupported source entry {source_path}: mode={mode!r} type={object_type!r}"
            )
        if source_path in entries:
            raise ReleaseBuildError(f"duplicate source entry: {source_path}")
        entries[source_path] = (mode.decode("ascii"), oid)
    if not entries:
        raise ReleaseBuildError("release has no tracked v2 files")
    return entries


def read_git_blobs(repo: Path, entries: dict[str, tuple[str, str]]) -> dict[str, bytes]:
    process = subprocess.Popen(
        ["git", "-C", os.fspath(repo), "cat-file", "--batch"],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    assert process.stdin is not None
    assert process.stdout is not None
    blobs: dict[str, bytes] = {}
    try:
        for source_path in sorted(entries):
            _mode, oid = entries[source_path]
            process.stdin.write((oid + "\n").encode("ascii"))
            process.stdin.flush()
            header = process.stdout.readline().decode("ascii", errors="strict").strip().split()
            if len(header) != 3 or header[0] != oid or header[1] != "blob":
                raise ReleaseBuildError(f"unexpected git cat-file header for {source_path}: {header}")
            try:
                size = int(header[2])
            except ValueError as exc:
                raise ReleaseBuildError(
                    f"invalid git blob size for {source_path}: {header[2]!r}"
                ) from exc
            data = process.stdout.read(size)
            terminator = process.stdout.read(1)
            if len(data) != size or terminator != b"\n":
                raise ReleaseBuildError(f"truncated git blob for {source_path}")
            blobs[source_path] = data
        process.stdin.close()
        stderr = process.stderr.read() if process.stderr is not None else b""
        return_code = process.wait()
        if return_code != 0:
            raise ReleaseBuildError(
                "git cat-file failed: " + stderr.decode("utf-8", errors="replace").strip()
            )
    finally:
        if process.stdin is not None and not process.stdin.closed:
            process.stdin.close()
        if process.poll() is None:
            process.kill()
            process.wait()
        process.stdout.close()
        if process.stderr is not None:
            process.stderr.close()
    return blobs


def target_mode(source_mode: str) -> int:
    return 0o755 if source_mode == "100755" else 0o644


def validate_prefix_free_paths(paths: Iterable[str], label: str) -> None:
    """Reject duplicate paths and file/descendant collisions."""
    seen: set[str] = set()
    for value in sorted(paths):
        path = validate_relative_path(value, label)
        normalized = path.as_posix()
        if normalized in seen:
            raise ReleaseBuildError(f"duplicate {label}: {normalized}")
        for parent in path.parents:
            parent_name = parent.as_posix()
            if parent_name != "." and parent_name in seen:
                raise ReleaseBuildError(
                    f"{label} file/descendant collision: {parent_name} and {normalized}"
                )
        seen.add(normalized)


def validate_public_control_collision(target: PurePosixPath) -> None:
    name = target.parts[0]
    if name in RESERVED_PUBLIC_CONTROL_NAMES or (
        name.startswith("release-manifest-") and name.endswith(".json")
    ):
        raise ReleaseBuildError(
            f"public payload collides with release control file: {name}"
        )


def make_file_record(
    target_path: str,
    source_path: str,
    entries: dict[str, tuple[str, str]],
    blobs: dict[str, bytes],
) -> tuple[dict[str, object], bytes]:
    target = validate_relative_path(target_path, "target path")
    validate_public_control_collision(target)
    if any(part in FORBIDDEN_TARGET_PARTS for part in target.parts):
        raise ReleaseBuildError(f"forbidden persistent file entered artifact: {target_path}")
    if source_path not in entries or source_path not in blobs:
        raise ReleaseBuildError(f"missing source blob: {source_path}")
    source_mode, source_oid = entries[source_path]
    data = blobs[source_path]
    mode = target_mode(source_mode)
    record: dict[str, object] = {
        "mode": f"{mode:04o}",
        "path": target_path,
        "sha256": sha256_bytes(data),
        "size": len(data),
        "source_blob_oid": source_oid,
        "source_path": source_path,
    }
    return record, data


def public_payload(
    entries: dict[str, tuple[str, str]], blobs: dict[str, bytes]
) -> tuple[list[dict[str, object]], dict[str, tuple[bytes, int]]]:
    mapping = {source_path.removeprefix("v2/"): source_path for source_path in entries}
    mapping.update(PUBLIC_OVERRIDES)
    validate_prefix_free_paths(mapping, "public target path")
    records: list[dict[str, object]] = []
    payload: dict[str, tuple[bytes, int]] = {}
    for target_path in sorted(mapping):
        record, data = make_file_record(target_path, mapping[target_path], entries, blobs)
        records.append(record)
        payload[target_path] = (data, int(str(record["mode"]), 8))
    return records, payload


def legacy_payload(
    entries: dict[str, tuple[str, str]], blobs: dict[str, bytes]
) -> tuple[list[dict[str, object]], dict[str, tuple[bytes, int]]]:
    records: list[dict[str, object]] = []
    payload: dict[str, tuple[bytes, int]] = {}
    validate_prefix_free_paths(
        (source_path.removeprefix("v2/") for source_path in LEGACY_FILES),
        "legacy target path",
    )
    for source_path in LEGACY_FILES:
        target_path = source_path.removeprefix("v2/")
        record, data = make_file_record(target_path, source_path, entries, blobs)
        records.append(record)
        payload[target_path] = (data, int(str(record["mode"]), 8))
    records.sort(key=lambda item: str(item["path"]))
    return records, payload


def payload_checksum_manifest(records: Iterable[dict[str, object]]) -> bytes:
    lines = [f"{record['sha256']}  ./{record['path']}" for record in records]
    return ("\n".join(lines) + "\n").encode("utf-8")


def make_manifest(
    *,
    target: str,
    release_sha: str,
    tree_sha: str,
    v2_tree_sha: str,
    records: list[dict[str, object]],
    overrides: dict[str, str],
) -> tuple[dict[str, object], bytes]:
    content_digest = sha256_bytes(canonical_json(records))
    manifest: dict[str, object] = {
        "content_digest_sha256": content_digest,
        "files": records,
        "layout_overrides": [
            {"source_path": source, "target_path": target_path}
            for target_path, source in sorted(overrides.items())
        ],
        "release_sha": release_sha,
        "repository_tree_sha": tree_sha,
        "schema_version": SCHEMA_VERSION,
        "source_subtree": {"path": "v2", "tree_sha": v2_tree_sha},
        "target": target,
    }
    return manifest, canonical_json(manifest)


def make_release_identity(
    *,
    target: str,
    release_sha: str,
    tree_sha: str,
    v2_tree_sha: str,
    manifest_bytes: bytes,
    checksum_bytes: bytes,
    content_digest: str,
) -> bytes:
    identity: dict[str, object] = {
        "content_digest_sha256": validate_sha256(content_digest, "content digest"),
        "manifest_sha256": sha256_bytes(manifest_bytes),
        "payload_checksums_sha256": sha256_bytes(checksum_bytes),
        "release_sha": release_sha,
        "repository_tree_sha": tree_sha,
        "schema_version": SCHEMA_VERSION,
        "source_subtree": {"path": "v2", "tree_sha": v2_tree_sha},
        "state": "active",
        "target": target,
    }
    if target == PUBLIC_TARGET:
        identity["manifest_file"] = (
            f".anytoour-public-release-manifest-{release_sha}.json"
        )
    return canonical_json(identity)


def archive_members(
    payload: dict[str, tuple[bytes, int]], control: dict[str, bytes]
) -> dict[str, tuple[bytes, int]]:
    members: dict[str, tuple[bytes, int]] = {}
    for target_path, (data, mode) in payload.items():
        members[f"payload/{target_path}"] = (data, mode)
    for name, data in control.items():
        validate_relative_path(name, "control path")
        members[f"control/{name}"] = (data, 0o644)
    return members


def write_deterministic_tar_gz(
    destination: Path, members: dict[str, tuple[bytes, int]]
) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    directory_names: set[str] = {"payload", "control"}
    for name in members:
        path = validate_relative_path(name, "archive member")
        for parent in path.parents:
            parent_name = parent.as_posix()
            if parent_name != ".":
                directory_names.add(parent_name)

    temporary = destination.with_name(destination.name + ".tmp")
    with temporary.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0, compresslevel=9) as gz:
            with tarfile.open(fileobj=gz, mode="w", format=tarfile.USTAR_FORMAT) as archive:
                for directory in sorted(directory_names):
                    info = tarfile.TarInfo(directory + "/")
                    info.type = tarfile.DIRTYPE
                    info.mode = 0o755
                    info.mtime = 0
                    info.uid = 0
                    info.gid = 0
                    info.uname = ""
                    info.gname = ""
                    archive.addfile(info)
                for name in sorted(members):
                    data, mode = members[name]
                    info = tarfile.TarInfo(name)
                    info.size = len(data)
                    info.mode = mode
                    info.mtime = 0
                    info.uid = 0
                    info.gid = 0
                    info.uname = ""
                    info.gname = ""
                    archive.addfile(info, io.BytesIO(data))
    temporary.replace(destination)


def verify_archive_structure(path: Path) -> set[str]:
    seen: set[str] = set()
    file_names: set[str] = set()
    directory_names: set[str] = set()
    with tarfile.open(path, mode="r:gz") as archive:
        for member in archive.getmembers():
            name = member.name.removesuffix("/")
            validate_relative_path(name, "archive member")
            if not (name == "payload" or name.startswith("payload/") or name == "control" or name.startswith("control/")):
                raise ReleaseBuildError(f"archive member escaped allowed roots: {member.name}")
            normalized = name
            if normalized in seen:
                raise ReleaseBuildError(f"duplicate archive member: {normalized}")
            seen.add(normalized)
            if not (member.isfile() or member.isdir()):
                raise ReleaseBuildError(f"unsupported archive member type: {member.name}")
            if member.isfile():
                file_names.add(normalized)
            else:
                if member.mode != 0o755:
                    raise ReleaseBuildError(
                        f"archive directory mode is not 0755: {member.name}"
                    )
                directory_names.add(normalized)
            if member.mtime != 0 or member.uid != 0 or member.gid != 0:
                raise ReleaseBuildError(f"non-deterministic archive metadata: {member.name}")
    if file_names & directory_names:
        collision = sorted(file_names & directory_names)[0]
        raise ReleaseBuildError(f"archive path is both file and directory: {collision}")
    for directory_name in directory_names:
        directory = PurePosixPath(directory_name)
        for parent in directory.parents:
            parent_name = parent.as_posix()
            if parent_name in file_names:
                raise ReleaseBuildError(
                    "archive file/directory descendant collision: "
                    f"{parent_name} and {directory_name}"
                )
    validate_prefix_free_paths(file_names, "archive file path")
    return directory_names


def read_archive_files(path: Path) -> dict[str, tuple[bytes, int]]:
    verify_archive_structure(path)
    files: dict[str, tuple[bytes, int]] = {}
    with tarfile.open(path, mode="r:gz") as archive:
        for member in archive.getmembers():
            if not member.isfile():
                continue
            extracted = archive.extractfile(member)
            if extracted is None:
                raise ReleaseBuildError(f"unable to read archive member: {member.name}")
            files[member.name] = (extracted.read(), member.mode)
    return files


def parse_json_bytes(data: bytes, label: str) -> dict[str, object]:
    try:
        value = json.loads(data.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise ReleaseBuildError(f"invalid {label} JSON") from exc
    if not isinstance(value, dict):
        raise ReleaseBuildError(f"{label} must be a JSON object")
    return value


def verify_payload_records(
    *,
    target: str,
    archive_files: dict[str, tuple[bytes, int]],
    manifest: dict[str, object],
) -> dict[str, bytes]:
    raw_records = manifest.get("files")
    if not isinstance(raw_records, list) or not raw_records:
        raise ReleaseBuildError(f"{target} manifest has no files")
    records: list[dict[str, object]] = []
    payload: dict[str, bytes] = {}
    previous_path = ""
    for raw_record in raw_records:
        if not isinstance(raw_record, dict):
            raise ReleaseBuildError(f"{target} manifest contains a non-object file record")
        record = raw_record
        path_value = record.get("path")
        if not isinstance(path_value, str):
            raise ReleaseBuildError(f"{target} manifest has a file without a path")
        validate_relative_path(path_value, f"{target} payload path")
        if path_value <= previous_path:
            raise ReleaseBuildError(f"{target} manifest paths are not unique and sorted")
        previous_path = path_value
        member_name = f"payload/{path_value}"
        if member_name not in archive_files:
            raise ReleaseBuildError(f"{target} archive is missing {member_name}")
        data, archive_mode = archive_files[member_name]
        expected_mode = record.get("mode")
        expected_size = record.get("size")
        expected_digest = record.get("sha256")
        source_path = record.get("source_path")
        source_oid = record.get("source_blob_oid")
        if expected_mode not in {"0644", "0755"}:
            raise ReleaseBuildError(f"{target} has invalid mode for {path_value}")
        if archive_mode != int(str(expected_mode), 8):
            raise ReleaseBuildError(f"{target} archive mode mismatch for {path_value}")
        if expected_size != len(data):
            raise ReleaseBuildError(f"{target} size mismatch for {path_value}")
        if not isinstance(expected_digest, str) or sha256_bytes(data) != expected_digest:
            raise ReleaseBuildError(f"{target} SHA-256 mismatch for {path_value}")
        if not isinstance(source_path, str) or not source_path.startswith("v2/"):
            raise ReleaseBuildError(f"{target} source path mismatch for {path_value}")
        validate_relative_path(source_path, f"{target} source path")
        if not isinstance(source_oid, str) or not FULL_SHA_RE.fullmatch(source_oid):
            raise ReleaseBuildError(f"{target} source blob OID mismatch for {path_value}")
        records.append(record)
        payload[path_value] = data

    expected_members = {f"payload/{record['path']}" for record in records}
    actual_members = {name for name in archive_files if name.startswith("payload/")}
    if actual_members != expected_members:
        raise ReleaseBuildError(f"{target} payload members do not match its manifest")

    expected_content_digest = manifest.get("content_digest_sha256")
    if not isinstance(expected_content_digest, str) or (
        sha256_bytes(canonical_json(records)) != expected_content_digest
    ):
        raise ReleaseBuildError(f"{target} content digest mismatch")

    expected_checksums = payload_checksum_manifest(records)
    checksum_member = archive_files.get("control/payload.sha256")
    if checksum_member is None or checksum_member[0] != expected_checksums:
        raise ReleaseBuildError(f"{target} payload checksum manifest mismatch")
    return payload


def verify_target_archive(
    *,
    path: Path,
    target: str,
    release_sha: str,
    tree_sha: str,
    v2_tree_sha: str,
) -> dict[str, object]:
    archive_directories = verify_archive_structure(path)
    archive_files = read_archive_files(path)
    expected_directories = {"payload", "control"}
    for member_name in archive_files:
        for parent in PurePosixPath(member_name).parents:
            parent_name = parent.as_posix()
            if parent_name != ".":
                expected_directories.add(parent_name)
    if archive_directories != expected_directories:
        raise ReleaseBuildError(f"{target} archive directories are incomplete or unexpected")
    expected_control = {
        "control/manifest.json",
        "control/payload.sha256",
        "control/release.json",
    }
    actual_control = {name for name in archive_files if name.startswith("control/")}
    if actual_control != expected_control:
        raise ReleaseBuildError(f"{target} control members are incomplete or unexpected")
    if any(archive_files[name][1] != 0o644 for name in expected_control):
        raise ReleaseBuildError(f"{target} control member mode mismatch")

    manifest_bytes = archive_files["control/manifest.json"][0]
    checksum_bytes = archive_files["control/payload.sha256"][0]
    identity_bytes = archive_files["control/release.json"][0]
    manifest = parse_json_bytes(manifest_bytes, f"{target} manifest")
    identity = parse_json_bytes(identity_bytes, f"{target} release identity")

    for document_name, document in (("manifest", manifest), ("identity", identity)):
        if document.get("schema_version") != SCHEMA_VERSION:
            raise ReleaseBuildError(f"{target} {document_name} schema mismatch")
        if document.get("target") != target:
            raise ReleaseBuildError(f"{target} {document_name} target mismatch")
        if document.get("release_sha") != release_sha:
            raise ReleaseBuildError(f"{target} {document_name} release SHA mismatch")
        if document.get("repository_tree_sha") != tree_sha:
            raise ReleaseBuildError(f"{target} {document_name} repository tree mismatch")
        subtree = document.get("source_subtree")
        if subtree != {"path": "v2", "tree_sha": v2_tree_sha}:
            raise ReleaseBuildError(f"{target} {document_name} v2 tree mismatch")

    if identity.get("state") != "active":
        raise ReleaseBuildError(f"{target} identity is not active")
    if identity.get("manifest_sha256") != sha256_bytes(manifest_bytes):
        raise ReleaseBuildError(f"{target} identity manifest digest mismatch")
    if identity.get("payload_checksums_sha256") != sha256_bytes(checksum_bytes):
        raise ReleaseBuildError(f"{target} identity checksum digest mismatch")
    if identity.get("content_digest_sha256") != manifest.get("content_digest_sha256"):
        raise ReleaseBuildError(f"{target} identity content digest mismatch")

    payload = verify_payload_records(
        target=target,
        archive_files=archive_files,
        manifest=manifest,
    )
    if target == PUBLIC_TARGET:
        if identity.get("manifest_file") != (
            f".anytoour-public-release-manifest-{release_sha}.json"
        ):
            raise ReleaseBuildError("public release manifest filename mismatch")
        required = {
            "lead-adapter-v2.php",
            "lead-bridge-v1.php",
            "index.php",
            "home-entry-v1.php",
            "search-page-v2.php",
        }
        if not required.issubset(payload):
            raise ReleaseBuildError("public artifact is missing mapped entrypoints")
        if payload["lead-adapter-v2.php"] != payload["lead-bridge-v1.php"]:
            raise ReleaseBuildError("public lead adapter differs from the bridge")
        if b"v2-hmac-bridge-bitrix-lead" not in payload["lead-adapter-v2.php"]:
            raise ReleaseBuildError("public lead adapter lost the bridge marker")
        if b"v2-direct-bitrix-lead" in payload["lead-adapter-v2.php"]:
            raise ReleaseBuildError("public lead adapter contains the direct marker")
        if payload["index.php"] != payload["home-entry-v1.php"]:
            raise ReleaseBuildError("public homepage entrypoint mapping mismatch")
        by_path = {str(record["path"]): record for record in manifest["files"]}  # type: ignore[index]
        for target_path, source_path in PUBLIC_OVERRIDES.items():
            if by_path[target_path].get("source_path") != source_path:
                raise ReleaseBuildError(f"public source mapping mismatch for {target_path}")
        expected_overrides = [
            {"source_path": source, "target_path": target_path}
            for target_path, source in sorted(PUBLIC_OVERRIDES.items())
        ]
        if manifest.get("layout_overrides") != expected_overrides:
            raise ReleaseBuildError("public layout override declaration mismatch")
    elif target == LEGACY_TARGET:
        expected_paths = {path.removeprefix("v2/") for path in LEGACY_FILES}
        if set(payload) != expected_paths:
            raise ReleaseBuildError("legacy executable payload is not the exact allowlist")
        if b"v2-direct-bitrix-lead" not in payload["lead-adapter-v2.php"]:
            raise ReleaseBuildError("legacy artifact lost the direct adapter marker")
        if "manifest_file" in identity:
            raise ReleaseBuildError("legacy identity unexpectedly declares a public manifest file")
        if manifest.get("layout_overrides") != []:
            raise ReleaseBuildError("legacy artifact unexpectedly declares layout overrides")
    else:
        raise ReleaseBuildError(f"unknown release target: {target}")
    return {
        "archive_sha256": sha256_file(path),
        "content_digest_sha256": manifest["content_digest_sha256"],
        "file_count": len(payload),
        "manifest_sha256": sha256_bytes(manifest_bytes),
        "payload_checksums_sha256": sha256_bytes(checksum_bytes),
        "release_identity_sha256": sha256_bytes(identity_bytes),
        "target": target,
    }


def verify_release_set(
    artifact_dir: Path,
    release_sha: str,
    *,
    repo: Path,
    expected_tree_sha: str | None = None,
    expected_control_sha: str | None = None,
    expected_run_id: str | None = None,
    expected_run_attempt: str | None = None,
) -> dict[str, object]:
    artifact_dir = artifact_dir.resolve()
    repo = repo.resolve()
    release_sha = validate_full_sha(release_sha)
    git_tree_sha, git_v2_tree_sha = resolve_release(repo, release_sha)
    base_files = {
        "SHA256SUMS",
        "anytoour-legacy-lead.tar.gz",
        "anytoour-legacy-lead.tar.gz.sha256",
        "anytoour-public.tar.gz",
        "anytoour-public.tar.gz.sha256",
        "release-build.json",
    }
    control_expected = any(
        value is not None
        for value in (expected_control_sha, expected_run_id, expected_run_attempt)
    )
    expected_files = base_files | ({"release-control.json"} if control_expected else set())
    actual_files = {path.name for path in artifact_dir.iterdir() if path.is_file()}
    if actual_files != expected_files or any(path.is_dir() for path in artifact_dir.iterdir()):
        raise ReleaseBuildError("release artifact directory contains missing or unexpected entries")
    summary_path = artifact_dir / "release-build.json"
    if not summary_path.is_file():
        raise ReleaseBuildError("release-build.json is missing")
    summary = parse_json_bytes(summary_path.read_bytes(), "release build summary")
    if summary_path.read_bytes() != canonical_json(summary):
        raise ReleaseBuildError("release-build.json is not canonical")
    if summary.get("schema_version") != SCHEMA_VERSION:
        raise ReleaseBuildError("release build schema mismatch")
    if summary.get("release_sha") != release_sha:
        raise ReleaseBuildError("release build SHA mismatch")
    tree_sha = summary.get("repository_tree_sha")
    subtree = summary.get("source_subtree")
    if not isinstance(tree_sha, str):
        raise ReleaseBuildError("release build repository tree is missing")
    validate_full_sha(tree_sha, "repository tree SHA")
    if tree_sha != git_tree_sha:
        raise ReleaseBuildError("release build repository tree differs from exact Git SHA")
    if expected_tree_sha is not None and tree_sha != validate_full_sha(
        expected_tree_sha, "expected repository tree SHA"
    ):
        raise ReleaseBuildError("release build repository tree differs from expected")
    if not isinstance(subtree, dict) or subtree.get("path") != "v2":
        raise ReleaseBuildError("release build v2 subtree is missing")
    v2_tree_sha = subtree.get("tree_sha")
    if not isinstance(v2_tree_sha, str):
        raise ReleaseBuildError("release build v2 tree is missing")
    validate_full_sha(v2_tree_sha, "v2 tree SHA")
    if v2_tree_sha != git_v2_tree_sha:
        raise ReleaseBuildError("release build v2 tree differs from exact Git SHA")

    expected_artifacts = {
        PUBLIC_TARGET: "anytoour-public.tar.gz",
        LEGACY_TARGET: "anytoour-legacy-lead.tar.gz",
    }
    raw_artifacts = summary.get("artifacts")
    if not isinstance(raw_artifacts, list) or len(raw_artifacts) != 2:
        raise ReleaseBuildError("release build must describe exactly two artifacts")
    summary_by_target: dict[str, dict[str, object]] = {}
    for raw_item in raw_artifacts:
        if not isinstance(raw_item, dict) or not isinstance(raw_item.get("target"), str):
            raise ReleaseBuildError("release build artifact record is invalid")
        summary_by_target[str(raw_item["target"])] = raw_item
    if set(summary_by_target) != set(expected_artifacts):
        raise ReleaseBuildError("release build targets mismatch")

    verified: list[dict[str, object]] = []
    for target, archive_name in expected_artifacts.items():
        archive_path = artifact_dir / archive_name
        sidecar_path = artifact_dir / f"{archive_name}.sha256"
        if not archive_path.is_file() or not sidecar_path.is_file():
            raise ReleaseBuildError(f"{target} archive or sidecar is missing")
        digest = sha256_file(archive_path)
        expected_sidecar = f"{digest}  {archive_name}\n"
        if sidecar_path.read_text(encoding="ascii") != expected_sidecar:
            raise ReleaseBuildError(f"{target} archive sidecar mismatch")
        result = verify_target_archive(
            path=archive_path,
            target=target,
            release_sha=release_sha,
            tree_sha=tree_sha,
            v2_tree_sha=v2_tree_sha,
        )
        described = summary_by_target[target]
        for key in (
            "archive_sha256",
            "content_digest_sha256",
            "file_count",
            "manifest_sha256",
            "payload_checksums_sha256",
            "release_identity_sha256",
            "target",
        ):
            if described.get(key) != result.get(key):
                raise ReleaseBuildError(f"{target} build summary mismatch for {key}")
        if described.get("archive") != archive_name or described.get("sidecar") != sidecar_path.name:
            raise ReleaseBuildError(f"{target} build summary filename mismatch")
        verified.append(result)

    sums_path = artifact_dir / "SHA256SUMS"
    expected_sums = "".join(
        f"{sha256_file(artifact_dir / name)}  {name}\n"
        for name in sorted(expected_artifacts.values())
    )
    if not sums_path.is_file() or sums_path.read_text(encoding="ascii") != expected_sums:
        raise ReleaseBuildError("top-level SHA256SUMS mismatch")

    control_path = artifact_dir / "release-control.json"
    expected_control_values = (
        expected_control_sha,
        expected_run_id,
        expected_run_attempt,
    )
    if control_expected:
        if any(value is None for value in expected_control_values):
            raise ReleaseBuildError("all expected workflow control values are required together")
        if not control_path.is_file():
            raise ReleaseBuildError("release-control.json is missing")
        if not str(expected_run_id).isdigit() or not str(expected_run_attempt).isdigit():
            raise ReleaseBuildError("workflow run id and attempt must be decimal integers")
        control = parse_json_bytes(control_path.read_bytes(), "release control")
        if control != {
            "control_sha": validate_full_sha(str(expected_control_sha), "control SHA"),
            "release_sha": release_sha,
            "run_attempt": int(str(expected_run_attempt)),
            "run_id": int(str(expected_run_id)),
            "schema_version": SCHEMA_VERSION,
            "workflow": ".github/workflows/build-anytoour-release.yml",
        }:
            raise ReleaseBuildError("release control mapping mismatch")
        if control_path.read_bytes() != canonical_json(control):
            raise ReleaseBuildError("release-control.json is not canonical")

    entries = list_v2_blobs(repo, release_sha)
    required_sources = set(PUBLIC_OVERRIDES.values()) | set(LEGACY_FILES)
    missing_sources = sorted(required_sources - set(entries))
    if missing_sources:
        raise ReleaseBuildError(
            "release is missing required source blobs: " + ", ".join(missing_sources)
        )
    blobs = read_git_blobs(repo, entries)
    exact_targets = {
        PUBLIC_TARGET: (*public_payload(entries, blobs), PUBLIC_OVERRIDES),
        LEGACY_TARGET: (*legacy_payload(entries, blobs), {}),
    }
    for target, (records, payload, overrides) in exact_targets.items():
        manifest, manifest_bytes = make_manifest(
            target=target,
            release_sha=release_sha,
            tree_sha=git_tree_sha,
            v2_tree_sha=git_v2_tree_sha,
            records=records,
            overrides=overrides,
        )
        checksum_bytes = payload_checksum_manifest(records)
        identity_bytes = make_release_identity(
            target=target,
            release_sha=release_sha,
            tree_sha=git_tree_sha,
            v2_tree_sha=git_v2_tree_sha,
            manifest_bytes=manifest_bytes,
            checksum_bytes=checksum_bytes,
            content_digest=str(manifest["content_digest_sha256"]),
        )
        expected_members = archive_members(
            payload,
            {
                "manifest.json": manifest_bytes,
                "payload.sha256": checksum_bytes,
                "release.json": identity_bytes,
            },
        )
        archive_name = expected_artifacts[target]
        actual_members = read_archive_files(artifact_dir / archive_name)
        if actual_members != expected_members:
            raise ReleaseBuildError(
                f"{target} archive members differ from exact Git source"
            )
    return {
        "artifacts": verified,
        "release_sha": release_sha,
        "repository_tree_sha": tree_sha,
        "schema_version": SCHEMA_VERSION,
        "source_subtree": {"path": "v2", "tree_sha": v2_tree_sha},
    }


def build_target(
    *,
    output_dir: Path,
    archive_name: str,
    target: str,
    release_sha: str,
    tree_sha: str,
    v2_tree_sha: str,
    records: list[dict[str, object]],
    payload: dict[str, tuple[bytes, int]],
    overrides: dict[str, str],
) -> dict[str, object]:
    manifest, manifest_bytes = make_manifest(
        target=target,
        release_sha=release_sha,
        tree_sha=tree_sha,
        v2_tree_sha=v2_tree_sha,
        records=records,
        overrides=overrides,
    )
    checksum_bytes = payload_checksum_manifest(records)
    identity_bytes = make_release_identity(
        target=target,
        release_sha=release_sha,
        tree_sha=tree_sha,
        v2_tree_sha=v2_tree_sha,
        manifest_bytes=manifest_bytes,
        checksum_bytes=checksum_bytes,
        content_digest=str(manifest["content_digest_sha256"]),
    )
    archive_path = output_dir / archive_name
    write_deterministic_tar_gz(
        archive_path,
        archive_members(
            payload,
            {
                "manifest.json": manifest_bytes,
                "payload.sha256": checksum_bytes,
                "release.json": identity_bytes,
            },
        ),
    )
    verify_archive_structure(archive_path)
    archive_digest = sha256_file(archive_path)
    sidecar_path = archive_path.with_name(archive_path.name + ".sha256")
    sidecar_path.write_text(f"{archive_digest}  {archive_path.name}\n", encoding="ascii")
    return {
        "archive": archive_path.name,
        "archive_sha256": archive_digest,
        "content_digest_sha256": manifest["content_digest_sha256"],
        "file_count": len(records),
        "manifest_sha256": sha256_bytes(manifest_bytes),
        "payload_checksums_sha256": sha256_bytes(checksum_bytes),
        "release_identity_sha256": sha256_bytes(identity_bytes),
        "sidecar": sidecar_path.name,
        "target": target,
    }


def build_release(repo: Path, release_sha: str, output_dir: Path) -> dict[str, object]:
    repo = repo.resolve()
    output_dir = output_dir.resolve()
    tree_sha, v2_tree_sha = resolve_release(repo, release_sha)
    entries = list_v2_blobs(repo, release_sha)
    required_sources = set(PUBLIC_OVERRIDES.values()) | set(LEGACY_FILES)
    missing_sources = sorted(required_sources - set(entries))
    if missing_sources:
        raise ReleaseBuildError(
            "release is missing required source blobs: " + ", ".join(missing_sources)
        )
    blobs = read_git_blobs(repo, entries)

    public_records, public_files = public_payload(entries, blobs)
    legacy_records, legacy_files = legacy_payload(entries, blobs)

    bridge = blobs["v2/lead-bridge-v1.php"]
    direct = blobs["v2/lead-adapter-v2.php"]
    if public_files["lead-adapter-v2.php"][0] != bridge or bridge == direct:
        raise ReleaseBuildError("public lead adapter is not the immutable bridge mapping")
    if b"v2-hmac-bridge-bitrix-lead" not in bridge or b"v2-direct-bitrix-lead" in bridge:
        raise ReleaseBuildError("public bridge implementation marker contract failed")
    if legacy_files["lead-adapter-v2.php"][0] != direct:
        raise ReleaseBuildError("legacy artifact lost the direct adapter mapping")
    if b"v2-direct-bitrix-lead" not in direct:
        raise ReleaseBuildError("legacy direct adapter implementation marker is missing")

    if output_dir.exists() and any(output_dir.iterdir()):
        raise ReleaseBuildError(f"output directory is not empty: {output_dir}")
    output_dir.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(
        prefix="anytoour-release-build-", dir=output_dir.parent
    ) as temporary_name:
        temporary_dir = Path(temporary_name)
        artifacts = [
            build_target(
                output_dir=temporary_dir,
                archive_name="anytoour-public.tar.gz",
                target=PUBLIC_TARGET,
                release_sha=release_sha,
                tree_sha=tree_sha,
                v2_tree_sha=v2_tree_sha,
                records=public_records,
                payload=public_files,
                overrides=PUBLIC_OVERRIDES,
            ),
            build_target(
                output_dir=temporary_dir,
                archive_name="anytoour-legacy-lead.tar.gz",
                target=LEGACY_TARGET,
                release_sha=release_sha,
                tree_sha=tree_sha,
                v2_tree_sha=v2_tree_sha,
                records=legacy_records,
                payload=legacy_files,
                overrides={},
            ),
        ]
        summary: dict[str, object] = {
            "artifacts": sorted(artifacts, key=lambda item: str(item["target"])),
            "release_sha": release_sha,
            "repository_tree_sha": tree_sha,
            "schema_version": SCHEMA_VERSION,
            "source_subtree": {"path": "v2", "tree_sha": v2_tree_sha},
        }
        (temporary_dir / "release-build.json").write_bytes(canonical_json(summary))
        checksum_lines = [
            f"{item['archive_sha256']}  {item['archive']}"
            for item in sorted(artifacts, key=lambda item: str(item["archive"]))
        ]
        (temporary_dir / "SHA256SUMS").write_text(
            "\n".join(checksum_lines) + "\n", encoding="ascii"
        )
        for source in sorted(temporary_dir.iterdir()):
            destination = output_dir / source.name
            os.replace(source, destination)
    return summary


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    build = commands.add_parser("build", help="build both deterministic target artifacts")
    build.add_argument("--repo", type=Path, default=Path.cwd())
    build.add_argument("--release-sha", required=True)
    build.add_argument("--output-dir", required=True, type=Path)
    verify = commands.add_parser("verify", help="verify a complete release artifact set")
    verify.add_argument("--repo", required=True, type=Path)
    verify.add_argument("--artifact-dir", required=True, type=Path)
    verify.add_argument("--release-sha", required=True)
    verify.add_argument("--repository-tree-sha")
    verify.add_argument("--control-sha")
    verify.add_argument("--run-id")
    verify.add_argument("--run-attempt")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    try:
        if args.command == "build":
            summary = build_release(args.repo, args.release_sha, args.output_dir)
            message = (
                "ANYTOOUR_RELEASE_BUILD_OK "
                f"sha={summary['release_sha']} "
                "targets=anytoour-public,anytoour-legacy-lead"
            )
        else:
            summary = verify_release_set(
                args.artifact_dir,
                args.release_sha,
                repo=args.repo,
                expected_tree_sha=args.repository_tree_sha,
                expected_control_sha=args.control_sha,
                expected_run_id=args.run_id,
                expected_run_attempt=args.run_attempt,
            )
            message = (
                "ANYTOOUR_RELEASE_VERIFY_OK "
                f"sha={summary['release_sha']} "
                "targets=anytoour-public,anytoour-legacy-lead"
            )
    except (OSError, ReleaseBuildError, tarfile.TarError) as exc:
        print(f"ANYTOOUR_RELEASE_BUILD_FAILED: {exc}", file=os.sys.stderr)
        return 1
    print(message)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
