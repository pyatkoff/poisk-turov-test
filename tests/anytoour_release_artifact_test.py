#!/usr/bin/env python3
"""Integration tests for deterministic AnyTour release artifacts."""

from __future__ import annotations

import importlib.util
import gzip
import io
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import tempfile
import unittest


sys.dont_write_bytecode = True
REPO = Path(__file__).resolve().parents[1]
BUILDER_PATH = REPO / "scripts/release/build_anytoour_release.py"
SPEC = importlib.util.spec_from_file_location("anytoour_release_builder", BUILDER_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("unable to load release builder")
builder = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(builder)


def git_text(*args: str) -> str:
    return subprocess.check_output(
        ["git", "-C", str(REPO), *args], text=True
    ).strip()


def git_bytes(*args: str) -> bytes:
    return subprocess.check_output(["git", "-C", str(REPO), *args])


class AnyTourReleaseArtifactTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.release_sha = git_text("rev-parse", "HEAD")
        cls.tree_sha = git_text("rev-parse", "HEAD^{tree}")
        cls.v2_tree_sha = git_text("rev-parse", "HEAD:v2")
        cls.temporary = tempfile.TemporaryDirectory(prefix="anytoour-release-test-")
        cls.root = Path(cls.temporary.name)
        cls.first = cls.root / "first"
        cls.second = cls.root / "second"
        builder.build_release(REPO, cls.release_sha, cls.first)
        builder.build_release(REPO, cls.release_sha, cls.second)

    @classmethod
    def tearDownClass(cls) -> None:
        cls.temporary.cleanup()

    def archive_files(self, output: Path, name: str) -> dict[str, bytes]:
        return {
            path: data
            for path, (data, _mode) in builder.read_archive_files(output / name).items()
        }

    def make_coherent_public_forgery(self, destination: Path) -> None:
        shutil.copytree(self.second, destination)
        archive_path = destination / "anytoour-public.tar.gz"
        members = builder.read_archive_files(archive_path)
        manifest = json.loads(members["control/manifest.json"][0])
        identity = json.loads(members["control/release.json"][0])
        record = next(
            item for item in manifest["files"] if item["path"] == "SEO_CONTENT_CATALOG.md"
        )
        member_name = "payload/SEO_CONTENT_CATALOG.md"
        forged = members[member_name][0] + b"\nFORGED_NOT_IN_GIT\n"
        members[member_name] = (forged, members[member_name][1])
        record["sha256"] = builder.sha256_bytes(forged)
        record["size"] = len(forged)
        manifest["content_digest_sha256"] = builder.sha256_bytes(
            builder.canonical_json(manifest["files"])
        )
        checksum_bytes = builder.payload_checksum_manifest(manifest["files"])
        manifest_bytes = builder.canonical_json(manifest)
        identity["content_digest_sha256"] = manifest["content_digest_sha256"]
        identity["manifest_sha256"] = builder.sha256_bytes(manifest_bytes)
        identity["payload_checksums_sha256"] = builder.sha256_bytes(checksum_bytes)
        identity_bytes = builder.canonical_json(identity)
        members["control/manifest.json"] = (manifest_bytes, 0o644)
        members["control/payload.sha256"] = (checksum_bytes, 0o644)
        members["control/release.json"] = (identity_bytes, 0o644)
        builder.write_deterministic_tar_gz(archive_path, members)

        archive_digest = builder.sha256_file(archive_path)
        (destination / "anytoour-public.tar.gz.sha256").write_text(
            f"{archive_digest}  anytoour-public.tar.gz\n", encoding="ascii"
        )
        summary = json.loads((destination / "release-build.json").read_text())
        public = next(
            item for item in summary["artifacts"] if item["target"] == builder.PUBLIC_TARGET
        )
        public.update(
            {
                "archive_sha256": archive_digest,
                "content_digest_sha256": manifest["content_digest_sha256"],
                "manifest_sha256": builder.sha256_bytes(manifest_bytes),
                "payload_checksums_sha256": builder.sha256_bytes(checksum_bytes),
                "release_identity_sha256": builder.sha256_bytes(identity_bytes),
            }
        )
        (destination / "release-build.json").write_bytes(builder.canonical_json(summary))
        archives = ("anytoour-legacy-lead.tar.gz", "anytoour-public.tar.gz")
        (destination / "SHA256SUMS").write_text(
            "".join(
                f"{builder.sha256_file(destination / name)}  {name}\n"
                for name in archives
            ),
            encoding="ascii",
        )

    def test_repeated_builds_are_byte_identical(self) -> None:
        expected_names = {
            "SHA256SUMS",
            "anytoour-legacy-lead.tar.gz",
            "anytoour-legacy-lead.tar.gz.sha256",
            "anytoour-public.tar.gz",
            "anytoour-public.tar.gz.sha256",
            "release-build.json",
        }
        self.assertEqual({path.name for path in self.first.iterdir()}, expected_names)
        self.assertEqual({path.name for path in self.second.iterdir()}, expected_names)
        for name in sorted(expected_names):
            self.assertEqual(
                (self.first / name).read_bytes(),
                (self.second / name).read_bytes(),
                name,
            )
        for archive_name in ("anytoour-public.tar.gz", "anytoour-legacy-lead.tar.gz"):
            header = (self.first / archive_name).read_bytes()[:10]
            self.assertEqual(header[:2], b"\x1f\x8b")
            self.assertEqual(header[3] & 0x08, 0, "gzip filename flag must be absent")
            self.assertEqual(header[4:8], b"\0\0\0\0", "gzip mtime must be zero")

    def test_dirty_worktree_cannot_enter_exact_git_artifact(self) -> None:
        fixture = self.root / "dirty-clone"
        output = self.root / "dirty-clone-output"
        subprocess.run(
            ["git", "clone", "-q", "--no-hardlinks", str(REPO), str(fixture)],
            check=True,
        )
        self.assertEqual(
            subprocess.check_output(
                ["git", "-C", str(fixture), "rev-parse", "HEAD"], text=True
            ).strip(),
            self.release_sha,
        )
        catalog = fixture / "v2/SEO_CONTENT_CATALOG.md"
        catalog.write_bytes(catalog.read_bytes() + b"\nDIRTY_WORKTREE_ONLY\n")
        (fixture / "v2/untracked-release-probe.txt").write_text(
            "must not enter artifact\n", encoding="utf-8"
        )
        builder.build_release(fixture, self.release_sha, output)
        for expected in self.first.iterdir():
            self.assertEqual(
                expected.read_bytes(),
                (output / expected.name).read_bytes(),
                expected.name,
            )

    def test_complete_set_verifies_against_exact_sha_and_tree(self) -> None:
        result = builder.verify_release_set(
            self.first,
            self.release_sha,
            repo=REPO,
            expected_tree_sha=self.tree_sha,
        )
        self.assertEqual(result["release_sha"], self.release_sha)
        self.assertEqual(result["repository_tree_sha"], self.tree_sha)
        self.assertEqual(result["source_subtree"]["tree_sha"], self.v2_tree_sha)
        counts = {entry["target"]: entry["file_count"] for entry in result["artifacts"]}
        tracked_v2 = git_text("ls-tree", "-r", "--name-only", "HEAD", "--", "v2").splitlines()
        self.assertEqual(counts[builder.PUBLIC_TARGET], len(tracked_v2) + 1)
        self.assertEqual(counts[builder.LEGACY_TARGET], 4)

    def test_coherent_rehashed_forgery_is_rejected_by_git_source_proof(self) -> None:
        forged = self.root / "coherent-forgery"
        self.make_coherent_public_forgery(forged)
        builder.verify_target_archive(
            path=forged / "anytoour-public.tar.gz",
            target=builder.PUBLIC_TARGET,
            release_sha=self.release_sha,
            tree_sha=self.tree_sha,
            v2_tree_sha=self.v2_tree_sha,
        )
        with self.assertRaisesRegex(
            builder.ReleaseBuildError, "differ.*exact Git source"
        ):
            builder.verify_release_set(
                forged,
                self.release_sha,
                repo=REPO,
                expected_tree_sha=self.tree_sha,
            )

    def test_public_target_is_final_layout_before_upload(self) -> None:
        public = self.archive_files(self.first, "anytoour-public.tar.gz")
        self.assertEqual(
            public["payload/lead-adapter-v2.php"],
            git_bytes("show", f"{self.release_sha}:v2/lead-bridge-v1.php"),
        )
        self.assertNotEqual(
            public["payload/lead-adapter-v2.php"],
            git_bytes("show", f"{self.release_sha}:v2/lead-adapter-v2.php"),
        )
        self.assertIn(b"v2-hmac-bridge-bitrix-lead", public["payload/lead-adapter-v2.php"])
        self.assertNotIn(b"v2-direct-bitrix-lead", public["payload/lead-adapter-v2.php"])
        self.assertEqual(
            public["payload/search-page-v2.php"],
            git_bytes("show", f"{self.release_sha}:v2/index.php"),
        )
        self.assertEqual(
            public["payload/index.php"],
            git_bytes("show", f"{self.release_sha}:v2/home-entry-v1.php"),
        )
        self.assertNotIn("payload/config.php", public)
        self.assertNotIn("payload/.anytoour-bridge-secret", public)

        manifest = json.loads(public["control/manifest.json"])
        identity = json.loads(public["control/release.json"])
        self.assertEqual(manifest["release_sha"], self.release_sha)
        self.assertEqual(manifest["repository_tree_sha"], self.tree_sha)
        self.assertEqual(manifest["source_subtree"]["tree_sha"], self.v2_tree_sha)
        self.assertEqual(identity["state"], "active")
        self.assertEqual(
            identity["manifest_file"],
            f".anytoour-public-release-manifest-{self.release_sha}.json",
        )
        mappings = {
            entry["target_path"]: entry["source_path"]
            for entry in manifest["layout_overrides"]
        }
        self.assertEqual(mappings, builder.PUBLIC_OVERRIDES)

    def test_legacy_target_is_exact_four_file_allowlist(self) -> None:
        legacy = self.archive_files(self.first, "anytoour-legacy-lead.tar.gz")
        payload_names = {name.removeprefix("payload/") for name in legacy if name.startswith("payload/")}
        self.assertEqual(
            payload_names,
            {path.removeprefix("v2/") for path in builder.LEGACY_FILES},
        )
        self.assertEqual(
            legacy["payload/lead-adapter-v2.php"],
            git_bytes("show", f"{self.release_sha}:v2/lead-adapter-v2.php"),
        )
        self.assertIn(b"v2-direct-bitrix-lead", legacy["payload/lead-adapter-v2.php"])
        self.assertNotIn("payload/.anytoour-bridge-secret", legacy)
        self.assertNotIn("payload/config.php", legacy)

    def test_archive_members_are_safe_regular_files_or_directories(self) -> None:
        for archive_name in ("anytoour-public.tar.gz", "anytoour-legacy-lead.tar.gz"):
            with tarfile.open(self.first / archive_name, "r:gz") as archive:
                names: set[str] = set()
                for member in archive.getmembers():
                    self.assertNotIn(member.name, names)
                    names.add(member.name)
                    builder.validate_relative_path(member.name.removesuffix("/"))
                    self.assertTrue(member.isfile() or member.isdir())
                    self.assertEqual(member.mtime, 0)
                    self.assertEqual(member.uid, 0)
                    self.assertEqual(member.gid, 0)
                    self.assertEqual(member.uname, "")
                    self.assertEqual(member.gname, "")

    def test_release_control_binds_run_to_release_sha(self) -> None:
        control_dir = self.root / "with-control"
        shutil.copytree(self.second, control_dir)
        control = {
            "control_sha": self.release_sha,
            "release_sha": self.release_sha,
            "run_attempt": 3,
            "run_id": 123456,
            "schema_version": builder.SCHEMA_VERSION,
            "workflow": ".github/workflows/build-anytoour-release.yml",
        }
        (control_dir / "release-control.json").write_bytes(builder.canonical_json(control))
        builder.verify_release_set(
            control_dir,
            self.release_sha,
            repo=REPO,
            expected_tree_sha=self.tree_sha,
            expected_control_sha=self.release_sha,
            expected_run_id="123456",
            expected_run_attempt="3",
        )
        with self.assertRaises(builder.ReleaseBuildError):
            builder.verify_release_set(
                control_dir,
                self.release_sha,
                repo=REPO,
                expected_tree_sha=self.tree_sha,
                expected_control_sha=self.release_sha,
                expected_run_id="123457",
                expected_run_attempt="3",
            )

    def test_malformed_sha_and_unsafe_paths_are_rejected(self) -> None:
        for value in (
            "",
            self.release_sha[:12],
            self.release_sha.upper(),
            "g" * 40,
            self.release_sha + "\n",
            "$(touch-pwned)".ljust(40, "0"),
        ):
            with self.subTest(value=value):
                with self.assertRaises(builder.ReleaseBuildError):
                    builder.validate_full_sha(value)
        for value in (
            "/absolute",
            "../escape",
            "a/../../escape",
            "a\nfile",
            "a file",
            "a\\file",
            "a\x01file",
            "./file",
        ):
            with self.subTest(value=value):
                with self.assertRaises(builder.ReleaseBuildError):
                    builder.validate_relative_path(value)

    def test_release_control_namespace_and_path_prefix_collisions_are_rejected(self) -> None:
        entries = {"v2/source.txt": ("100644", "a" * 40)}
        blobs = {"v2/source.txt": b"source"}
        for target in (
            "release.json",
            "release.json/child",
            "release-manifest-note.json",
            "release-manifest-note.json/child",
        ):
            with self.subTest(target=target):
                with self.assertRaises(builder.ReleaseBuildError):
                    builder.make_file_record(target, "v2/source.txt", entries, blobs)
        with self.assertRaises(builder.ReleaseBuildError):
            builder.validate_prefix_free_paths(
                ("search-page-v2.php", "search-page-v2.php/child.txt"),
                "synthetic target",
            )

    def test_normalized_tar_file_directory_collision_is_rejected(self) -> None:
        destination = self.root / "file-directory-collision.tar.gz"
        with destination.open("wb") as raw:
            with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0) as zipped:
                with tarfile.open(fileobj=zipped, mode="w") as archive:
                    directory = tarfile.TarInfo("payload/conflict/")
                    directory.type = tarfile.DIRTYPE
                    directory.mtime = 0
                    archive.addfile(directory)
                    regular = tarfile.TarInfo("payload/conflict")
                    regular.size = 1
                    regular.mtime = 0
                    archive.addfile(regular, io.BytesIO(b"x"))
        with self.assertRaises(builder.ReleaseBuildError):
            builder.verify_archive_structure(destination)

    def test_tar_directory_cannot_descend_from_a_file(self) -> None:
        destination = self.root / "file-ancestor-collision.tar.gz"
        with destination.open("wb") as raw:
            with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0) as zipped:
                with tarfile.open(fileobj=zipped, mode="w") as archive:
                    regular = tarfile.TarInfo("payload/conflict")
                    regular.size = 1
                    regular.mtime = 0
                    archive.addfile(regular, io.BytesIO(b"x"))
                    directory = tarfile.TarInfo("payload/conflict/child/")
                    directory.type = tarfile.DIRTYPE
                    directory.mtime = 0
                    archive.addfile(directory)
        with self.assertRaises(builder.ReleaseBuildError):
            builder.verify_archive_structure(destination)

    def test_unexpected_empty_archive_directory_is_rejected(self) -> None:
        source = self.first / "anytoour-public.tar.gz"
        destination = self.root / "unexpected-empty-directory.tar.gz"
        with tarfile.open(source, mode="r:gz") as original:
            with destination.open("wb") as raw:
                with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0) as zipped:
                    with tarfile.open(
                        fileobj=zipped, mode="w", format=tarfile.USTAR_FORMAT
                    ) as forged:
                        for member in original.getmembers():
                            extracted = original.extractfile(member) if member.isfile() else None
                            forged.addfile(member, extracted)
                        extra = tarfile.TarInfo("payload/unexpected-empty/")
                        extra.type = tarfile.DIRTYPE
                        extra.mode = 0o755
                        extra.mtime = 0
                        extra.uid = 0
                        extra.gid = 0
                        forged.addfile(extra)
        with self.assertRaisesRegex(builder.ReleaseBuildError, "directories"):
            builder.verify_target_archive(
                path=destination,
                target=builder.PUBLIC_TARGET,
                release_sha=self.release_sha,
                tree_sha=self.tree_sha,
                v2_tree_sha=self.v2_tree_sha,
            )

    def test_executable_source_mode_is_preserved(self) -> None:
        record, _data = builder.make_file_record(
            "tool.sh",
            "v2/tool.sh",
            {"v2/tool.sh": ("100755", "b" * 40)},
            {"v2/tool.sh": b"#!/bin/sh\n"},
        )
        self.assertEqual(record["mode"], "0755")

    def test_tampered_archive_fails_before_use(self) -> None:
        tampered = self.root / "tampered"
        shutil.copytree(self.second, tampered)
        archive = tampered / "anytoour-public.tar.gz"
        data = bytearray(archive.read_bytes())
        data[len(data) // 2] ^= 0x01
        archive.write_bytes(data)
        with self.assertRaises(builder.ReleaseBuildError):
            builder.verify_release_set(
                tampered,
                self.release_sha,
                repo=REPO,
                expected_tree_sha=self.tree_sha,
            )

    def test_git_symlink_source_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory(prefix="anytoour-release-symlink-") as directory:
            fixture = Path(directory)
            subprocess.run(["git", "init", "-q", str(fixture)], check=True)
            subprocess.run(
                ["git", "-C", str(fixture), "config", "user.email", "ci@example.invalid"],
                check=True,
            )
            subprocess.run(
                ["git", "-C", str(fixture), "config", "user.name", "CI"], check=True
            )
            (fixture / "v2").mkdir()
            (fixture / "v2/index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")
            (fixture / "v2/link.php").symlink_to("index.php")
            subprocess.run(["git", "-C", str(fixture), "add", "v2"], check=True)
            subprocess.run(
                ["git", "-C", str(fixture), "commit", "-q", "-m", "fixture"], check=True
            )
            fixture_sha = subprocess.check_output(
                ["git", "-C", str(fixture), "rev-parse", "HEAD"], text=True
            ).strip()
            with self.assertRaises(builder.ReleaseBuildError):
                builder.list_v2_blobs(fixture, fixture_sha)


if __name__ == "__main__":
    unittest.main(verbosity=2)
