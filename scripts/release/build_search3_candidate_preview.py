#!/usr/bin/env python3
"""Build and verify a deterministic Search3 preview artifact from exact Git objects."""

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
from typing import Iterable


BUILDER_SCHEMA_VERSION = 2
EVIDENCE_SCHEMA_VERSION = 2
TARGET = "search3-candidate-preview"
SOURCE_ROOT = "v2/_preview/search3-candidate/poisk-turov"
ROUTE = "/_preview/search3-candidate/poisk-turov/"
CANONICAL = "https://anytoour.ru/_preview/search3-candidate/poisk-turov/"
ROBOTS = "noindex,follow,max-image-preview:large"
EVIDENCE_WORKFLOW = ".github/workflows/validate-search3-candidate-scaffold.yml"
ARCHIVE_NAME = "search3-candidate-preview.tar.gz"
BUILD_NAME = "release-build.json"
CHECKSUMS_NAME = "SHA256SUMS"

SOURCE_TO_TARGET = {
    f"{SOURCE_ROOT}/index.php": "poisk-turov/index.php",
    f"{SOURCE_ROOT}/search3-entry-v1.css": "poisk-turov/search3-entry-v1.css",
    f"{SOURCE_ROOT}/search3-entry-v1.js": "poisk-turov/search3-entry-v1.js",
    f"{SOURCE_ROOT}/search3-results-filters-v1.css": "poisk-turov/search3-results-filters-v1.css",
    f"{SOURCE_ROOT}/search3-results-filters-v1.js": "poisk-turov/search3-results-filters-v1.js",
    f"{SOURCE_ROOT}/search3-results-cards-v2.css": "poisk-turov/search3-results-cards-v2.css",
    f"{SOURCE_ROOT}/search3-results-cards-v2.js": "poisk-turov/search3-results-cards-v2.js",
    f"{SOURCE_ROOT}/search3-selected-flow-v2.css": "poisk-turov/search3-selected-flow-v2.css",
    f"{SOURCE_ROOT}/search3-selected-flow-v2.js": "poisk-turov/search3-selected-flow-v2.js",
}

FULL_SHA_RE = re.compile(r"[0-9a-f]{40}")
SHA256_RE = re.compile(r"[0-9a-f]{64}")
ARTIFACT_DIGEST_RE = re.compile(r"sha256:[0-9a-f]{64}")
EXPECTED_BEHAVIOR_STATES = (
    "search-empty",
    "search-timeout",
    "search-upstream-error",
    "flight-empty",
    "flight-timeout",
    "flight-upstream-error",
    "lead-ui-no-delivery",
)
EXPECTED_PRESENTATION_SCREENSHOT_COUNT = 6
EXPECTED_PRESENTATION_FILES = {
    "search3-entry-v1.css",
    "search3-entry-v1.js",
    "search3-results-cards-v2.css",
    "search3-results-cards-v2.js",
    "search3-results-filters-v1.css",
    "search3-results-filters-v1.js",
    "search3-selected-flow-v2.css",
    "search3-selected-flow-v2.js",
}


class CandidateArtifactError(RuntimeError):
    """Raised when an artifact cannot satisfy the exact-source contract."""


def canonical_json(value: object) -> bytes:
    return (
        json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    ).encode("utf-8")


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def validate_full_sha(value: str, label: str = "SHA") -> str:
    if not FULL_SHA_RE.fullmatch(value):
        raise CandidateArtifactError(
            f"{label} must be exactly 40 lowercase hexadecimal characters"
        )
    return value


def validate_sha256(value: str, label: str = "SHA-256") -> str:
    if not SHA256_RE.fullmatch(value):
        raise CandidateArtifactError(
            f"{label} must be exactly 64 lowercase hexadecimal characters"
        )
    return value


def validate_artifact_digest(value: str) -> str:
    if not ARTIFACT_DIGEST_RE.fullmatch(value):
        raise CandidateArtifactError(
            "evidence artifact digest must be sha256: followed by 64 lowercase hex characters"
        )
    return value


def positive_integer(value: object, label: str) -> int:
    if isinstance(value, bool):
        raise CandidateArtifactError(f"{label} must be a positive integer")
    text = str(value)
    if not re.fullmatch(r"[1-9][0-9]*", text):
        raise CandidateArtifactError(f"{label} must be a positive integer")
    return int(text)


def validate_relative_path(value: str, label: str = "path") -> PurePosixPath:
    if (
        not value
        or "\\" in value
        or any(ord(char) < 33 or ord(char) == 127 for char in value)
    ):
        raise CandidateArtifactError(f"{label} contains an unsafe character")
    parts = value.split("/")
    if any(part in {"", ".", ".."} for part in parts):
        raise CandidateArtifactError(f"{label} is not a safe relative POSIX path")
    path = PurePosixPath(value)
    if path.is_absolute():
        raise CandidateArtifactError(f"{label} must be relative")
    return path


def validate_prefix_free_paths(paths: Iterable[str], label: str) -> None:
    seen: set[str] = set()
    for raw in sorted(paths):
        path = validate_relative_path(raw, label)
        normalized = path.as_posix()
        if normalized in seen:
            raise CandidateArtifactError(f"duplicate {label}: {normalized}")
        for parent in path.parents:
            parent_name = parent.as_posix()
            if parent_name != "." and parent_name in seen:
                raise CandidateArtifactError(
                    f"{label} file/descendant collision: {parent_name} and {normalized}"
                )
        seen.add(normalized)


def run_git(repo: Path, *arguments: str) -> bytes:
    process = subprocess.run(
        ["git", "-C", os.fspath(repo), *arguments],
        check=False,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    if process.returncode != 0:
        detail = process.stderr.decode("utf-8", errors="replace").strip()
        raise CandidateArtifactError(
            f"git {' '.join(arguments)} failed: {detail or process.returncode}"
        )
    return process.stdout


def ensure_clean_worktree(repo: Path) -> None:
    status = run_git(repo, "status", "--porcelain=v1", "--untracked-files=all")
    if status:
        first = status.decode("utf-8", errors="replace").splitlines()[0]
        raise CandidateArtifactError(f"repository worktree is dirty: {first}")


def resolve_candidate(repo: Path, candidate_sha: str) -> tuple[str, str]:
    candidate_sha = validate_full_sha(candidate_sha, "candidate SHA")
    resolved = run_git(repo, "rev-parse", "--verify", f"{candidate_sha}^{{commit}}")
    if resolved.decode("ascii").strip() != candidate_sha:
        raise CandidateArtifactError("candidate SHA resolved to a different commit")
    tree_sha = run_git(repo, "rev-parse", f"{candidate_sha}^{{tree}}").decode("ascii").strip()
    subtree_sha = run_git(repo, "rev-parse", f"{candidate_sha}:{SOURCE_ROOT}").decode("ascii").strip()
    validate_full_sha(tree_sha, "repository tree SHA")
    validate_full_sha(subtree_sha, "candidate subtree SHA")
    return tree_sha, subtree_sha


def candidate_entries(repo: Path, candidate_sha: str) -> dict[str, tuple[str, str]]:
    raw = run_git(repo, "ls-tree", "-r", "-z", candidate_sha, "--", SOURCE_ROOT)
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
            raise CandidateArtifactError("unable to parse git ls-tree output") from exc
        validate_relative_path(source_path, "source path")
        if source_path in entries:
            raise CandidateArtifactError(f"duplicate source path: {source_path}")
        if object_type != b"blob" or mode not in {b"100644", b"100755"}:
            raise CandidateArtifactError(
                f"candidate source must be a regular Git blob: {source_path} "
                f"mode={mode.decode('ascii', errors='replace')} "
                f"type={object_type.decode('ascii', errors='replace')}"
            )
        if not FULL_SHA_RE.fullmatch(oid):
            raise CandidateArtifactError(f"invalid Git blob OID for {source_path}")
        entries[source_path] = (mode.decode("ascii"), oid)
    expected = set(SOURCE_TO_TARGET)
    actual = set(entries)
    if actual != expected:
        missing = sorted(expected - actual)
        extra = sorted(actual - expected)
        raise CandidateArtifactError(
            f"candidate route differs from the exact source allowlist: missing={missing} extra={extra}"
        )
    return entries


def read_git_blobs(repo: Path, entries: dict[str, tuple[str, str]]) -> dict[str, bytes]:
    blobs: dict[str, bytes] = {}
    for source_path in sorted(entries):
        _mode, oid = entries[source_path]
        blobs[source_path] = run_git(repo, "cat-file", "blob", oid)
    return blobs


def mode_for_git(source_mode: str) -> int:
    return 0o755 if source_mode == "100755" else 0o644


def file_records(
    entries: dict[str, tuple[str, str]], blobs: dict[str, bytes]
) -> tuple[list[dict[str, object]], dict[str, tuple[bytes, int]]]:
    validate_prefix_free_paths(SOURCE_TO_TARGET.values(), "target path")
    records: list[dict[str, object]] = []
    payload: dict[str, tuple[bytes, int]] = {}
    for source_path, target_path in sorted(
        SOURCE_TO_TARGET.items(), key=lambda item: item[1]
    ):
        source_mode, blob_oid = entries[source_path]
        data = blobs[source_path]
        mode = mode_for_git(source_mode)
        records.append(
            {
                "mode": f"{mode:04o}",
                "path": target_path,
                "sha256": sha256_bytes(data),
                "size": len(data),
                "source_blob_oid": blob_oid,
                "source_path": source_path,
            }
        )
        payload[target_path] = (data, mode)
    return records, payload


def load_evidence(
    path: Path,
    candidate_sha: str,
    records: list[dict[str, object]],
    artifact_id: object,
    artifact_digest: str,
) -> dict[str, object]:
    try:
        evidence_bytes = path.read_bytes()
        raw = json.loads(evidence_bytes)
    except (OSError, json.JSONDecodeError) as exc:
        raise CandidateArtifactError(f"unable to read evidence manifest: {exc}") from exc
    if not isinstance(raw, dict):
        raise CandidateArtifactError("evidence manifest must be a JSON object")
    if (
        type(raw.get("schemaVersion")) is not int
        or raw.get("schemaVersion") != EVIDENCE_SCHEMA_VERSION
    ):
        raise CandidateArtifactError("evidence manifest schema mismatch")
    source_sha = raw.get("sourceSha")
    tested_sha = raw.get("testedSha")
    if source_sha != candidate_sha or tested_sha != candidate_sha or source_sha != tested_sha:
        raise CandidateArtifactError(
            "evidence sourceSha and testedSha must both equal the exact candidate SHA"
        )
    run_id = positive_integer(raw.get("workflowRunId"), "evidence run ID")
    run_attempt = positive_integer(
        raw.get("workflowRunAttempt"), "evidence run attempt"
    )
    artifact_id_int = positive_integer(artifact_id, "evidence artifact ID")
    artifact_digest = validate_artifact_digest(artifact_digest)
    if raw.get("route") != ROUTE or raw.get("canonical") != CANONICAL:
        raise CandidateArtifactError("evidence route or canonical URL mismatch")
    if raw.get("visualTier") != "candidate":
        raise CandidateArtifactError("evidence visualTier must be exactly candidate")
    screenshots = raw.get("screenshots")
    presentation_screenshots = raw.get("presentationScreenshots")
    if not isinstance(screenshots, list) or len(screenshots) != 15:
        raise CandidateArtifactError("evidence must contain exactly 15 lifecycle screenshots")
    if (
        not isinstance(presentation_screenshots, list)
        or len(presentation_screenshots) != EXPECTED_PRESENTATION_SCREENSHOT_COUNT
    ):
        raise CandidateArtifactError(
            f"evidence must contain exactly {EXPECTED_PRESENTATION_SCREENSHOT_COUNT} "
            "presentation screenshots"
        )
    behavior = raw.get("behaviorStates")
    if not isinstance(behavior, list) or tuple(
        item.get("name") if isinstance(item, dict) else None for item in behavior
    ) != EXPECTED_BEHAVIOR_STATES:
        raise CandidateArtifactError("evidence behavior state list mismatch")
    if any(item.get("passed") is not True for item in behavior):
        raise CandidateArtifactError("evidence contains a failed behavior state")

    presentation_assets = raw.get("presentationAssets")
    if not isinstance(presentation_assets, list):
        raise CandidateArtifactError("evidence presentationAssets must be a list")
    by_file: dict[str, dict[str, object]] = {}
    for item in presentation_assets:
        if not isinstance(item, dict) or not isinstance(item.get("file"), str):
            raise CandidateArtifactError("invalid evidence presentation asset")
        filename = str(item["file"])
        if filename in by_file:
            raise CandidateArtifactError(f"duplicate evidence presentation asset: {filename}")
        by_file[filename] = item
    if set(by_file) != EXPECTED_PRESENTATION_FILES:
        raise CandidateArtifactError("evidence presentation asset allowlist mismatch")
    record_by_name = {PurePosixPath(str(item["path"])).name: item for item in records}
    for filename in sorted(EXPECTED_PRESENTATION_FILES):
        evidence_asset = by_file[filename]
        record = record_by_name[filename]
        if evidence_asset.get("sha256") != record["sha256"]:
            raise CandidateArtifactError(f"evidence asset SHA-256 mismatch: {filename}")
        if evidence_asset.get("bytes") != record["size"]:
            raise CandidateArtifactError(f"evidence asset size mismatch: {filename}")

    return {
        "artifact_digest": artifact_digest,
        "artifact_id": artifact_id_int,
        "manifest_sha256": sha256_bytes(evidence_bytes),
        "run_attempt": run_attempt,
        "run_id": run_id,
        "source_sha": candidate_sha,
        "tested_sha": candidate_sha,
        "visual_tier": "candidate",
        "workflow": EVIDENCE_WORKFLOW,
    }


def payload_checksums(records: Iterable[dict[str, object]]) -> bytes:
    return (
        "".join(f"{item['sha256']}  ./{item['path']}\n" for item in records)
    ).encode("ascii")


def make_manifest(
    candidate_sha: str,
    tree_sha: str,
    subtree_sha: str,
    records: list[dict[str, object]],
    evidence: dict[str, object],
) -> tuple[dict[str, object], bytes]:
    manifest: dict[str, object] = {
        "candidate_sha": candidate_sha,
        "canonical": CANONICAL,
        "content_digest_sha256": sha256_bytes(canonical_json(records)),
        "evidence": evidence,
        "files": records,
        "invariants": {
            "metrika_counter": 0,
            "production_lead_delivery": False,
            "robots": ROBOTS,
        },
        "repository_tree_sha": tree_sha,
        "route": ROUTE,
        "schema_version": BUILDER_SCHEMA_VERSION,
        "source_subtree": {"path": SOURCE_ROOT, "tree_sha": subtree_sha},
        "target": TARGET,
    }
    return manifest, canonical_json(manifest)


def archive_members(
    payload: dict[str, tuple[bytes, int]], control: dict[str, bytes]
) -> dict[str, tuple[bytes, int]]:
    members = {f"payload/{name}": value for name, value in payload.items()}
    for name, data in control.items():
        validate_relative_path(name, "control path")
        members[f"control/{name}"] = (data, 0o644)
    validate_prefix_free_paths(members, "archive member")
    return members


def write_deterministic_tar_gz(
    destination: Path, members: dict[str, tuple[bytes, int]]
) -> None:
    directories = {"payload", "control"}
    for name in members:
        path = validate_relative_path(name, "archive member")
        directories.update(
            parent.as_posix() for parent in path.parents if parent.as_posix() != "."
        )
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary = destination.with_name(destination.name + ".tmp")
    with temporary.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0, compresslevel=9) as gz:
            with tarfile.open(fileobj=gz, mode="w", format=tarfile.USTAR_FORMAT) as archive:
                for directory in sorted(directories):
                    info = tarfile.TarInfo(directory + "/")
                    info.type = tarfile.DIRTYPE
                    info.mode = 0o755
                    info.mtime = 0
                    info.uid = info.gid = 0
                    info.uname = info.gname = ""
                    archive.addfile(info)
                for name in sorted(members):
                    data, mode = members[name]
                    info = tarfile.TarInfo(name)
                    info.size = len(data)
                    info.mode = mode
                    info.mtime = 0
                    info.uid = info.gid = 0
                    info.uname = info.gname = ""
                    archive.addfile(info, io.BytesIO(data))
    temporary.replace(destination)


def read_archive(path: Path) -> dict[str, tuple[bytes, int]]:
    files: dict[str, tuple[bytes, int]] = {}
    directories: set[str] = set()
    seen: set[str] = set()
    try:
        with tarfile.open(path, mode="r:gz") as archive:
            for member in archive.getmembers():
                name = member.name.removesuffix("/")
                validate_relative_path(name, "archive member")
                if name in seen:
                    raise CandidateArtifactError(f"duplicate archive member: {name}")
                seen.add(name)
                if not (name == "payload" or name.startswith("payload/") or name == "control" or name.startswith("control/")):
                    raise CandidateArtifactError(f"archive member escaped allowed roots: {name}")
                if member.mtime != 0 or member.uid != 0 or member.gid != 0 or member.uname or member.gname:
                    raise CandidateArtifactError(f"non-deterministic archive metadata: {name}")
                if member.isdir():
                    if member.mode != 0o755:
                        raise CandidateArtifactError(f"archive directory mode mismatch: {name}")
                    directories.add(name)
                    continue
                if not member.isfile() or member.mode not in {0o644, 0o755}:
                    raise CandidateArtifactError(f"unsupported archive member: {name}")
                extracted = archive.extractfile(member)
                if extracted is None:
                    raise CandidateArtifactError(f"unable to read archive member: {name}")
                files[name] = (extracted.read(), member.mode)
    except (OSError, tarfile.TarError) as exc:
        raise CandidateArtifactError(f"invalid candidate archive: {exc}") from exc
    expected_directories = {"payload", "payload/poisk-turov", "control"}
    if directories != expected_directories:
        raise CandidateArtifactError(
            f"archive directories mismatch: expected={sorted(expected_directories)} actual={sorted(directories)}"
        )
    validate_prefix_free_paths(files, "archive file")
    return files


def parse_json(data: bytes, label: str) -> dict[str, object]:
    try:
        value = json.loads(data)
    except json.JSONDecodeError as exc:
        raise CandidateArtifactError(f"invalid {label}: {exc}") from exc
    if not isinstance(value, dict):
        raise CandidateArtifactError(f"{label} must be a JSON object")
    return value


def verify_manifest_shape(
    manifest: dict[str, object], candidate_sha: str, tree_sha: str, subtree_sha: str
) -> tuple[list[dict[str, object]], dict[str, object]]:
    expected = {
        "candidate_sha": candidate_sha,
        "canonical": CANONICAL,
        "repository_tree_sha": tree_sha,
        "route": ROUTE,
        "schema_version": BUILDER_SCHEMA_VERSION,
        "target": TARGET,
    }
    if type(manifest.get("schema_version")) is not int:
        raise CandidateArtifactError("manifest schema_version must be an integer")
    for key, wanted in expected.items():
        if manifest.get(key) != wanted:
            raise CandidateArtifactError(f"manifest {key} mismatch")
    if manifest.get("source_subtree") != {"path": SOURCE_ROOT, "tree_sha": subtree_sha}:
        raise CandidateArtifactError("manifest candidate subtree mismatch")
    if manifest.get("invariants") != {
        "metrika_counter": 0,
        "production_lead_delivery": False,
        "robots": ROBOTS,
    }:
        raise CandidateArtifactError("manifest preview invariant mismatch")
    records = manifest.get("files")
    evidence = manifest.get("evidence")
    if not isinstance(records, list) or not all(isinstance(item, dict) for item in records):
        raise CandidateArtifactError("manifest files must be an object list")
    if not isinstance(evidence, dict):
        raise CandidateArtifactError("manifest evidence must be an object")
    if manifest.get("content_digest_sha256") != sha256_bytes(canonical_json(records)):
        raise CandidateArtifactError("manifest content digest mismatch")
    return records, evidence


def build_release(
    repo: Path,
    candidate_sha: str,
    evidence_manifest: Path,
    evidence_artifact_id: object,
    evidence_artifact_digest: str,
    output_dir: Path,
) -> dict[str, object]:
    repo = repo.resolve()
    output_dir = output_dir.resolve()
    ensure_clean_worktree(repo)
    tree_sha, subtree_sha = resolve_candidate(repo, candidate_sha)
    entries = candidate_entries(repo, candidate_sha)
    blobs = read_git_blobs(repo, entries)
    records, payload = file_records(entries, blobs)
    evidence = load_evidence(
        evidence_manifest,
        candidate_sha,
        records,
        evidence_artifact_id,
        evidence_artifact_digest,
    )
    manifest, manifest_bytes = make_manifest(
        candidate_sha, tree_sha, subtree_sha, records, evidence
    )
    checksum_bytes = payload_checksums(records)

    output_dir.mkdir(parents=True, exist_ok=False)
    archive_path = output_dir / ARCHIVE_NAME
    write_deterministic_tar_gz(
        archive_path,
        archive_members(
            payload,
            {"manifest.json": manifest_bytes, "payload.sha256": checksum_bytes},
        ),
    )
    archive_sha = sha256_file(archive_path)
    sidecar = output_dir / f"{ARCHIVE_NAME}.sha256"
    sidecar.write_text(f"{archive_sha}  {ARCHIVE_NAME}\n", encoding="ascii")
    summary: dict[str, object] = {
        "archive": ARCHIVE_NAME,
        "archive_sha256": archive_sha,
        "candidate_sha": candidate_sha,
        "candidate_subtree_sha": subtree_sha,
        "content_digest_sha256": manifest["content_digest_sha256"],
        "evidence": evidence,
        "file_count": len(records),
        "manifest_sha256": sha256_bytes(manifest_bytes),
        "payload_checksums_sha256": sha256_bytes(checksum_bytes),
        "repository_tree_sha": tree_sha,
        "schema_version": BUILDER_SCHEMA_VERSION,
        "target": TARGET,
    }
    build_path = output_dir / BUILD_NAME
    build_path.write_bytes(canonical_json(summary))
    checksums = "".join(
        f"{sha256_file(output_dir / name)}  {name}\n"
        for name in (ARCHIVE_NAME, f"{ARCHIVE_NAME}.sha256", BUILD_NAME)
    )
    (output_dir / CHECKSUMS_NAME).write_text(checksums, encoding="ascii")
    return summary


def verify_release_set(
    repo: Path,
    artifact_dir: Path,
    candidate_sha: str,
    *,
    expected_tree_sha: str,
    expected_subtree_sha: str,
    expected_evidence_run_id: object,
    expected_evidence_run_attempt: object,
    expected_evidence_artifact_id: object,
    expected_evidence_artifact_digest: str,
    expected_evidence_manifest_sha256: str,
) -> dict[str, object]:
    repo = repo.resolve()
    artifact_dir = artifact_dir.resolve()
    ensure_clean_worktree(repo)
    expected_tree_sha = validate_full_sha(expected_tree_sha, "expected repository tree SHA")
    expected_subtree_sha = validate_full_sha(expected_subtree_sha, "expected candidate subtree SHA")
    expected_digest = validate_artifact_digest(expected_evidence_artifact_digest)
    expected_manifest_sha = validate_sha256(
        expected_evidence_manifest_sha256, "expected evidence manifest SHA-256"
    )
    tree_sha, subtree_sha = resolve_candidate(repo, candidate_sha)
    if tree_sha != expected_tree_sha or subtree_sha != expected_subtree_sha:
        raise CandidateArtifactError("candidate repository or subtree tree mismatch")

    expected_names = {
        ARCHIVE_NAME,
        f"{ARCHIVE_NAME}.sha256",
        BUILD_NAME,
        CHECKSUMS_NAME,
    }
    root_entries = list(artifact_dir.iterdir())
    if any(not path.is_file() or path.is_symlink() for path in root_entries):
        raise CandidateArtifactError("artifact root must contain regular files only")
    actual_names = {path.name for path in root_entries}
    if actual_names != expected_names:
        raise CandidateArtifactError(
            f"artifact file set mismatch: expected={sorted(expected_names)} actual={sorted(actual_names)}"
        )
    archive_path = artifact_dir / ARCHIVE_NAME
    archive_sha = sha256_file(archive_path)
    if (artifact_dir / f"{ARCHIVE_NAME}.sha256").read_text(encoding="ascii") != f"{archive_sha}  {ARCHIVE_NAME}\n":
        raise CandidateArtifactError("archive digest sidecar mismatch")
    expected_checksums = "".join(
        f"{sha256_file(artifact_dir / name)}  {name}\n"
        for name in (ARCHIVE_NAME, f"{ARCHIVE_NAME}.sha256", BUILD_NAME)
    )
    if (artifact_dir / CHECKSUMS_NAME).read_text(encoding="ascii") != expected_checksums:
        raise CandidateArtifactError("release SHA256SUMS mismatch")

    files = read_archive(archive_path)
    expected_members = {
        "control/manifest.json",
        "control/payload.sha256",
        *(f"payload/{target}" for target in SOURCE_TO_TARGET.values()),
    }
    if set(files) != expected_members:
        raise CandidateArtifactError("archive file allowlist mismatch")
    if files["control/manifest.json"][1] != 0o644 or files["control/payload.sha256"][1] != 0o644:
        raise CandidateArtifactError("archive control mode mismatch")
    manifest_bytes = files["control/manifest.json"][0]
    manifest = parse_json(manifest_bytes, "candidate manifest")
    records, evidence = verify_manifest_shape(
        manifest, candidate_sha, tree_sha, subtree_sha
    )
    expected_evidence = {
        "artifact_digest": expected_digest,
        "artifact_id": positive_integer(
            expected_evidence_artifact_id, "expected evidence artifact ID"
        ),
        "manifest_sha256": expected_manifest_sha,
        "run_attempt": positive_integer(
            expected_evidence_run_attempt, "expected evidence run attempt"
        ),
        "run_id": positive_integer(expected_evidence_run_id, "expected evidence run ID"),
        "source_sha": candidate_sha,
        "tested_sha": candidate_sha,
        "visual_tier": "candidate",
        "workflow": EVIDENCE_WORKFLOW,
    }
    if evidence != expected_evidence:
        raise CandidateArtifactError("manifest evidence identity mismatch")

    if files["control/payload.sha256"][0] != payload_checksums(records):
        raise CandidateArtifactError("payload checksum manifest mismatch")
    record_by_path = {str(item.get("path")): item for item in records}
    if list(record_by_path) != sorted(SOURCE_TO_TARGET.values()):
        raise CandidateArtifactError("manifest file record order or allowlist mismatch")

    entries = candidate_entries(repo, candidate_sha)
    blobs = read_git_blobs(repo, entries)
    expected_records, expected_payload = file_records(entries, blobs)
    if records != expected_records:
        raise CandidateArtifactError("manifest file records differ from exact Git objects")
    for target_path, (expected_data, expected_mode) in expected_payload.items():
        actual_data, actual_mode = files[f"payload/{target_path}"]
        if actual_data != expected_data or actual_mode != expected_mode:
            raise CandidateArtifactError(
                f"archive payload differs from exact Git object: {target_path}"
            )

    summary = parse_json((artifact_dir / BUILD_NAME).read_bytes(), "release build summary")
    expected_summary = {
        "archive": ARCHIVE_NAME,
        "archive_sha256": archive_sha,
        "candidate_sha": candidate_sha,
        "candidate_subtree_sha": subtree_sha,
        "content_digest_sha256": manifest["content_digest_sha256"],
        "evidence": evidence,
        "file_count": len(records),
        "manifest_sha256": sha256_bytes(manifest_bytes),
        "payload_checksums_sha256": sha256_bytes(files["control/payload.sha256"][0]),
        "repository_tree_sha": tree_sha,
        "schema_version": BUILDER_SCHEMA_VERSION,
        "target": TARGET,
    }
    if summary != expected_summary:
        raise CandidateArtifactError("release build summary mismatch")
    return summary


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    build = commands.add_parser("build")
    build.add_argument("--repo", type=Path, required=True)
    build.add_argument("--candidate-sha", required=True)
    build.add_argument("--evidence-manifest", type=Path, required=True)
    build.add_argument("--evidence-artifact-id", required=True)
    build.add_argument("--evidence-artifact-digest", required=True)
    build.add_argument("--output-dir", type=Path, required=True)

    verify = commands.add_parser("verify")
    verify.add_argument("--repo", type=Path, required=True)
    verify.add_argument("--artifact-dir", type=Path, required=True)
    verify.add_argument("--candidate-sha", required=True)
    verify.add_argument("--repository-tree-sha", required=True)
    verify.add_argument("--candidate-subtree-tree-sha", required=True)
    verify.add_argument("--evidence-run-id", required=True)
    verify.add_argument("--evidence-run-attempt", required=True)
    verify.add_argument("--evidence-artifact-id", required=True)
    verify.add_argument("--evidence-artifact-digest", required=True)
    verify.add_argument("--evidence-manifest-sha256", required=True)
    return parser.parse_args()


def main() -> int:
    arguments = parse_arguments()
    try:
        if arguments.command == "build":
            summary = build_release(
                arguments.repo,
                arguments.candidate_sha,
                arguments.evidence_manifest,
                arguments.evidence_artifact_id,
                arguments.evidence_artifact_digest,
                arguments.output_dir,
            )
            print(
                "SEARCH3_CANDIDATE_ARTIFACT_BUILT",
                f"sha={summary['candidate_sha']}",
                f"archive_sha256={summary['archive_sha256']}",
                "files=3",
            )
        else:
            summary = verify_release_set(
                arguments.repo,
                arguments.artifact_dir,
                arguments.candidate_sha,
                expected_tree_sha=arguments.repository_tree_sha,
                expected_subtree_sha=arguments.candidate_subtree_tree_sha,
                expected_evidence_run_id=arguments.evidence_run_id,
                expected_evidence_run_attempt=arguments.evidence_run_attempt,
                expected_evidence_artifact_id=arguments.evidence_artifact_id,
                expected_evidence_artifact_digest=arguments.evidence_artifact_digest,
                expected_evidence_manifest_sha256=arguments.evidence_manifest_sha256,
            )
            print(
                "SEARCH3_CANDIDATE_ARTIFACT_VERIFIED",
                f"sha={summary['candidate_sha']}",
                f"archive_sha256={summary['archive_sha256']}",
                "files=3",
            )
    except (CandidateArtifactError, OSError) as exc:
        parser = argparse.ArgumentParser(add_help=False)
        parser.error(str(exc))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
