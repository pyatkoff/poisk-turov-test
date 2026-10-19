#!/usr/bin/env python3
"""Tests for the unprivileged exact-Git Search3 preview artifact builder."""

from __future__ import annotations

import gzip
import importlib.util
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
BUILDER_PATH = REPO / "scripts/release/build_search3_candidate_preview.py"
SPEC = importlib.util.spec_from_file_location("search3_candidate_builder", BUILDER_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("unable to load Search3 candidate artifact builder")
builder = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(builder)


def git(repo: Path, *arguments: str, text: bool = True) -> str | bytes:
    output = subprocess.check_output(
        ["git", "-C", str(repo), *arguments], text=text
    )
    return output.strip() if text else output


class Search3CandidateArtifactTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.temporary = tempfile.TemporaryDirectory(prefix="search3-artifact-test-")
        cls.root = Path(cls.temporary.name)
        cls.repo = cls.root / "repo"
        subprocess.run(
            ["git", "clone", "-q", "--no-hardlinks", str(REPO), str(cls.repo)],
            check=True,
        )
        cls.candidate_sha = str(git(cls.repo, "rev-parse", "HEAD"))
        cls.tree_sha = str(git(cls.repo, "rev-parse", "HEAD^{tree}"))
        cls.subtree_sha = str(
            git(cls.repo, "rev-parse", f"HEAD:{builder.SOURCE_ROOT}")
        )
        cls.artifact_id = "9935092860"
        cls.artifact_digest = "sha256:" + "a" * 64
        cls.run_id = "33875228804"
        cls.run_attempt = "1"
        cls.evidence_path = cls.root / "evidence-manifest.json"
        cls.write_evidence(cls.evidence_path)
        cls.evidence_sha = builder.sha256_file(cls.evidence_path)
        cls.first = cls.root / "first"
        cls.second = cls.root / "second"
        builder.build_release(
            cls.repo,
            cls.candidate_sha,
            cls.evidence_path,
            cls.artifact_id,
            cls.artifact_digest,
            cls.first,
        )
        builder.build_release(
            cls.repo,
            cls.candidate_sha,
            cls.evidence_path,
            cls.artifact_id,
            cls.artifact_digest,
            cls.second,
        )

    @classmethod
    def tearDownClass(cls) -> None:
        cls.temporary.cleanup()

    @classmethod
    def evidence(cls, **overrides: object) -> dict[str, object]:
        assets = []
        for filename in sorted(builder.EXPECTED_PRESENTATION_FILES):
            source_path = f"{builder.SOURCE_ROOT}/{filename}"
            data = git(cls.repo, "show", f"{cls.candidate_sha}:{source_path}", text=False)
            assert isinstance(data, bytes)
            assets.append(
                {
                    "bytes": len(data),
                    "file": filename,
                    "sha256": builder.sha256_bytes(data),
                }
            )
        value: dict[str, object] = {
            "schemaVersion": builder.EVIDENCE_SCHEMA_VERSION,
            "sourceSha": cls.candidate_sha,
            "testedSha": cls.candidate_sha,
            "workflowRunId": cls.run_id,
            "workflowRunAttempt": cls.run_attempt,
            "route": builder.ROUTE,
            "canonical": builder.CANONICAL,
            "visualTier": "candidate",
            "presentationAssets": assets,
            "screenshots": [{"file": f"lifecycle-{index}.png"} for index in range(15)],
            "presentationScreenshots": [
                {"file": f"presentation-{index}.png"} for index in range(3)
            ],
            "behaviorStates": [
                {"name": name, "passed": True}
                for name in builder.EXPECTED_BEHAVIOR_STATES
            ],
        }
        value.update(overrides)
        return value

    @classmethod
    def write_evidence(cls, destination: Path, **overrides: object) -> None:
        destination.write_bytes(builder.canonical_json(cls.evidence(**overrides)))

    def verify(self, artifact_dir: Path, **overrides: object) -> dict[str, object]:
        values: dict[str, object] = {
            "expected_tree_sha": self.tree_sha,
            "expected_subtree_sha": self.subtree_sha,
            "expected_evidence_run_id": self.run_id,
            "expected_evidence_run_attempt": self.run_attempt,
            "expected_evidence_artifact_id": self.artifact_id,
            "expected_evidence_artifact_digest": self.artifact_digest,
            "expected_evidence_manifest_sha256": self.evidence_sha,
        }
        values.update(overrides)
        return builder.verify_release_set(
            self.repo, artifact_dir, self.candidate_sha, **values
        )

    def clone_fixture(self, name: str) -> Path:
        destination = self.root / name
        subprocess.run(
            ["git", "clone", "-q", "--no-hardlinks", str(self.repo), str(destination)],
            check=True,
        )
        subprocess.run(
            ["git", "-C", str(destination), "config", "user.email", "ci@example.invalid"],
            check=True,
        )
        subprocess.run(
            ["git", "-C", str(destination), "config", "user.name", "CI"],
            check=True,
        )
        return destination

    def rewrite_artifact_controls(
        self, artifact_dir: Path, files: dict[str, tuple[bytes, int]]
    ) -> None:
        archive_path = artifact_dir / builder.ARCHIVE_NAME
        builder.write_deterministic_tar_gz(archive_path, files)
        archive_sha = builder.sha256_file(archive_path)
        (artifact_dir / f"{builder.ARCHIVE_NAME}.sha256").write_text(
            f"{archive_sha}  {builder.ARCHIVE_NAME}\n", encoding="ascii"
        )
        manifest_bytes = files["control/manifest.json"][0]
        checksum_bytes = files["control/payload.sha256"][0]
        manifest = json.loads(manifest_bytes)
        summary = json.loads((artifact_dir / builder.BUILD_NAME).read_bytes())
        summary.update(
            {
                "archive_sha256": archive_sha,
                "content_digest_sha256": manifest["content_digest_sha256"],
                "manifest_sha256": builder.sha256_bytes(manifest_bytes),
                "payload_checksums_sha256": builder.sha256_bytes(checksum_bytes),
            }
        )
        (artifact_dir / builder.BUILD_NAME).write_bytes(builder.canonical_json(summary))
        checksums = "".join(
            f"{builder.sha256_file(artifact_dir / name)}  {name}\n"
            for name in (
                builder.ARCHIVE_NAME,
                f"{builder.ARCHIVE_NAME}.sha256",
                builder.BUILD_NAME,
            )
        )
        (artifact_dir / builder.CHECKSUMS_NAME).write_text(checksums, encoding="ascii")

    def test_repeated_builds_are_byte_identical_and_exact(self) -> None:
        names = {
            builder.ARCHIVE_NAME,
            f"{builder.ARCHIVE_NAME}.sha256",
            builder.BUILD_NAME,
            builder.CHECKSUMS_NAME,
        }
        self.assertEqual({path.name for path in self.first.iterdir()}, names)
        self.assertEqual({path.name for path in self.second.iterdir()}, names)
        for name in sorted(names):
            self.assertEqual(
                (self.first / name).read_bytes(),
                (self.second / name).read_bytes(),
                name,
            )
        summary = self.verify(self.first)
        self.assertEqual(summary["candidate_sha"], self.candidate_sha)
        self.assertEqual(summary["repository_tree_sha"], self.tree_sha)
        self.assertEqual(summary["candidate_subtree_sha"], self.subtree_sha)
        self.assertEqual(summary["file_count"], 3)

    def test_cli_build_and_verify(self) -> None:
        output = self.root / "cli-output"
        built = subprocess.run(
            [
                sys.executable,
                "-B",
                str(BUILDER_PATH),
                "build",
                "--repo",
                str(self.repo),
                "--candidate-sha",
                self.candidate_sha,
                "--evidence-manifest",
                str(self.evidence_path),
                "--evidence-artifact-id",
                self.artifact_id,
                "--evidence-artifact-digest",
                self.artifact_digest,
                "--output-dir",
                str(output),
            ],
            check=True,
            text=True,
            capture_output=True,
        )
        self.assertIn("SEARCH3_CANDIDATE_ARTIFACT_BUILT", built.stdout)
        verified = subprocess.run(
            [
                sys.executable,
                "-B",
                str(BUILDER_PATH),
                "verify",
                "--repo",
                str(self.repo),
                "--artifact-dir",
                str(output),
                "--candidate-sha",
                self.candidate_sha,
                "--repository-tree-sha",
                self.tree_sha,
                "--candidate-subtree-tree-sha",
                self.subtree_sha,
                "--evidence-run-id",
                self.run_id,
                "--evidence-run-attempt",
                self.run_attempt,
                "--evidence-artifact-id",
                self.artifact_id,
                "--evidence-artifact-digest",
                self.artifact_digest,
                "--evidence-manifest-sha256",
                self.evidence_sha,
            ],
            check=True,
            text=True,
            capture_output=True,
        )
        self.assertIn("SEARCH3_CANDIDATE_ARTIFACT_VERIFIED", verified.stdout)

    def test_manifest_has_exact_identity_without_archive_self_digest(self) -> None:
        files = builder.read_archive(self.first / builder.ARCHIVE_NAME)
        manifest_bytes = files["control/manifest.json"][0]
        manifest = json.loads(manifest_bytes)
        self.assertEqual(manifest["candidate_sha"], self.candidate_sha)
        self.assertEqual(manifest["repository_tree_sha"], self.tree_sha)
        self.assertEqual(
            manifest["source_subtree"],
            {"path": builder.SOURCE_ROOT, "tree_sha": self.subtree_sha},
        )
        self.assertEqual(
            manifest["invariants"],
            {
                "metrika_counter": 0,
                "production_lead_delivery": False,
                "robots": builder.ROBOTS,
            },
        )
        self.assertNotIn("archive_sha256", manifest)
        self.assertNotIn("manifest_sha256", manifest)
        self.assertEqual(
            json.loads((self.first / builder.BUILD_NAME).read_bytes())["archive_sha256"],
            builder.sha256_file(self.first / builder.ARCHIVE_NAME),
        )
        for record in manifest["files"]:
            self.assertRegex(record["source_blob_oid"], r"^[0-9a-f]{40}$")
            self.assertIn(record["mode"], {"0644", "0755"})
            self.assertIsInstance(record["size"], int)
            self.assertRegex(record["sha256"], r"^[0-9a-f]{64}$")
        evidence = manifest["evidence"]
        self.assertEqual(evidence["source_sha"], evidence["tested_sha"])
        self.assertEqual(evidence["artifact_id"], int(self.artifact_id))
        self.assertEqual(evidence["artifact_digest"], self.artifact_digest)
        self.assertEqual(evidence["visual_tier"], "candidate")

    def test_dirty_worktree_is_rejected(self) -> None:
        dirty = self.clone_fixture("dirty")
        path = dirty / builder.SOURCE_ROOT / "search3-results-filters-v1.css"
        path.write_bytes(path.read_bytes() + b"\n/* dirty */\n")
        with self.assertRaisesRegex(builder.CandidateArtifactError, "worktree is dirty"):
            builder.build_release(
                dirty,
                self.candidate_sha,
                self.evidence_path,
                self.artifact_id,
                self.artifact_digest,
                self.root / "dirty-output",
            )

    def test_traversal_and_malformed_sha_are_rejected(self) -> None:
        for value in ("../escape", "/absolute", "a/../../escape", "a\\file", "a file"):
            with self.subTest(path=value):
                with self.assertRaises(builder.CandidateArtifactError):
                    builder.validate_relative_path(value)
        for value in ("", self.candidate_sha[:12], self.candidate_sha.upper(), "g" * 40):
            with self.subTest(sha=value):
                with self.assertRaises(builder.CandidateArtifactError):
                    builder.validate_full_sha(value)
        with self.assertRaises(builder.CandidateArtifactError):
            builder.build_release(
                self.repo,
                "0" * 40,
                self.evidence_path,
                self.artifact_id,
                self.artifact_digest,
                self.root / "wrong-candidate-output",
            )

        archive = self.root / "traversal.tar.gz"
        with archive.open("wb") as raw:
            with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0) as zipped:
                with tarfile.open(fileobj=zipped, mode="w") as tar:
                    info = tarfile.TarInfo("payload/../escape")
                    info.size = 1
                    info.mode = 0o644
                    info.mtime = 0
                    info.uid = info.gid = 0
                    tar.addfile(info, io.BytesIO(b"x"))
        with self.assertRaises(builder.CandidateArtifactError):
            builder.read_archive(archive)

    def test_archive_links_are_rejected(self) -> None:
        for link_type, type_code in (
            ("symlink", tarfile.SYMTYPE),
            ("hardlink", tarfile.LNKTYPE),
        ):
            with self.subTest(link_type=link_type):
                archive = self.root / f"archive-{link_type}.tar.gz"
                with archive.open("wb") as raw:
                    with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0) as zipped:
                        with tarfile.open(fileobj=zipped, mode="w") as tar:
                            info = tarfile.TarInfo(f"payload/{link_type}")
                            info.type = type_code
                            info.linkname = "control/manifest.json"
                            info.mode = 0o644
                            info.mtime = 0
                            info.uid = info.gid = 0
                            tar.addfile(info)
                with self.assertRaisesRegex(builder.CandidateArtifactError, "unsupported"):
                    builder.read_archive(archive)

    def test_symlink_and_submodule_sources_are_rejected(self) -> None:
        symlink_repo = self.clone_fixture("symlink")
        css = symlink_repo / builder.SOURCE_ROOT / "search3-results-filters-v1.css"
        css.unlink()
        css.symlink_to("search3-results-filters-v1.js")
        subprocess.run(["git", "-C", str(symlink_repo), "add", "-A"], check=True)
        subprocess.run(["git", "-C", str(symlink_repo), "commit", "-qm", "symlink"], check=True)
        symlink_sha = str(git(symlink_repo, "rev-parse", "HEAD"))
        with self.assertRaisesRegex(builder.CandidateArtifactError, "regular Git blob"):
            builder.candidate_entries(symlink_repo, symlink_sha)

        submodule_repo = self.clone_fixture("submodule")
        gitlink = f"{builder.SOURCE_ROOT}/unexpected-module"
        subprocess.run(
            [
                "git",
                "-C",
                str(submodule_repo),
                "update-index",
                "--add",
                "--cacheinfo",
                f"160000,{self.candidate_sha},{gitlink}",
            ],
            check=True,
        )
        subprocess.run(["git", "-C", str(submodule_repo), "commit", "-qm", "gitlink"], check=True)
        submodule_sha = str(git(submodule_repo, "rev-parse", "HEAD"))
        with self.assertRaisesRegex(builder.CandidateArtifactError, "regular Git blob"):
            builder.candidate_entries(submodule_repo, submodule_sha)

        extra_repo = self.clone_fixture("extra-source")
        extra = extra_repo / builder.SOURCE_ROOT / "unexpected.txt"
        extra.write_text("not allowlisted\n", encoding="utf-8")
        subprocess.run(["git", "-C", str(extra_repo), "add", str(extra)], check=True)
        subprocess.run(["git", "-C", str(extra_repo), "commit", "-qm", "extra"], check=True)
        extra_sha = str(git(extra_repo, "rev-parse", "HEAD"))
        with self.assertRaisesRegex(builder.CandidateArtifactError, "three-file allowlist"):
            builder.candidate_entries(extra_repo, extra_sha)

    def test_source_tested_sha_and_asset_evidence_must_match_git(self) -> None:
        wrong_schema = self.root / "wrong-schema.json"
        self.write_evidence(wrong_schema, schemaVersion=1)
        with self.assertRaisesRegex(builder.CandidateArtifactError, "schema mismatch"):
            builder.build_release(
                self.repo,
                self.candidate_sha,
                wrong_schema,
                self.artifact_id,
                self.artifact_digest,
                self.root / "wrong-schema-output",
            )

        wrong_tier = self.root / "wrong-tier.json"
        self.write_evidence(wrong_tier, visualTier="pr")
        with self.assertRaisesRegex(builder.CandidateArtifactError, "visualTier"):
            builder.build_release(
                self.repo,
                self.candidate_sha,
                wrong_tier,
                self.artifact_id,
                self.artifact_digest,
                self.root / "wrong-tier-output",
            )

        wrong_screenshot_type = self.root / "wrong-screenshot-type.json"
        self.write_evidence(wrong_screenshot_type, screenshots="x" * 15)
        with self.assertRaisesRegex(builder.CandidateArtifactError, "15 lifecycle"):
            builder.build_release(
                self.repo,
                self.candidate_sha,
                wrong_screenshot_type,
                self.artifact_id,
                self.artifact_digest,
                self.root / "wrong-screenshot-type-output",
            )

        wrong_presentation_type = self.root / "wrong-presentation-type.json"
        self.write_evidence(
            wrong_presentation_type, presentationScreenshots="abc"
        )
        with self.assertRaisesRegex(builder.CandidateArtifactError, "3 presentation"):
            builder.build_release(
                self.repo,
                self.candidate_sha,
                wrong_presentation_type,
                self.artifact_id,
                self.artifact_digest,
                self.root / "wrong-presentation-type-output",
            )

        wrong_tested = self.root / "wrong-tested.json"
        self.write_evidence(wrong_tested, testedSha="0" * 40)
        with self.assertRaisesRegex(builder.CandidateArtifactError, "sourceSha and testedSha"):
            builder.build_release(
                self.repo,
                self.candidate_sha,
                wrong_tested,
                self.artifact_id,
                self.artifact_digest,
                self.root / "wrong-tested-output",
            )

        wrong_asset = self.evidence()
        wrong_asset["presentationAssets"][0]["sha256"] = "0" * 64  # type: ignore[index]
        wrong_asset_path = self.root / "wrong-asset.json"
        wrong_asset_path.write_bytes(builder.canonical_json(wrong_asset))
        with self.assertRaisesRegex(builder.CandidateArtifactError, "asset SHA-256"):
            builder.build_release(
                self.repo,
                self.candidate_sha,
                wrong_asset_path,
                self.artifact_id,
                self.artifact_digest,
                self.root / "wrong-asset-output",
            )

    def test_wrong_tree_and_evidence_identity_are_rejected(self) -> None:
        with self.assertRaisesRegex(builder.CandidateArtifactError, "tree mismatch"):
            self.verify(self.first, expected_tree_sha="0" * 40)
        with self.assertRaisesRegex(builder.CandidateArtifactError, "tree mismatch"):
            self.verify(self.first, expected_subtree_sha="0" * 40)
        with self.assertRaisesRegex(builder.CandidateArtifactError, "evidence identity"):
            self.verify(self.first, expected_evidence_run_id="33875228805")
        with self.assertRaisesRegex(builder.CandidateArtifactError, "evidence identity"):
            self.verify(self.first, expected_evidence_artifact_id="9935092861")
        with self.assertRaisesRegex(builder.CandidateArtifactError, "evidence identity"):
            self.verify(
                self.first,
                expected_evidence_artifact_digest="sha256:" + "b" * 64,
            )
        with self.assertRaisesRegex(builder.CandidateArtifactError, "evidence identity"):
            self.verify(
                self.first,
                expected_evidence_manifest_sha256="b" * 64,
            )
        with self.assertRaises(builder.CandidateArtifactError):
            builder.build_release(
                self.repo,
                self.candidate_sha,
                self.evidence_path,
                self.artifact_id,
                "sha256:short",
                self.root / "bad-digest-output",
            )

    def test_coherent_rehashed_blob_forgery_is_rejected_by_git_source(self) -> None:
        forged = self.root / "forged"
        shutil.copytree(self.first, forged)
        files = builder.read_archive(forged / builder.ARCHIVE_NAME)
        manifest = json.loads(files["control/manifest.json"][0])
        target = "poisk-turov/search3-results-filters-v1.css"
        member = f"payload/{target}"
        forged_data = files[member][0] + b"\n/* forged outside Git */\n"
        files[member] = (forged_data, files[member][1])
        record = next(item for item in manifest["files"] if item["path"] == target)
        record["size"] = len(forged_data)
        record["sha256"] = builder.sha256_bytes(forged_data)
        manifest["content_digest_sha256"] = builder.sha256_bytes(
            builder.canonical_json(manifest["files"])
        )
        manifest_bytes = builder.canonical_json(manifest)
        checksum_bytes = builder.payload_checksums(manifest["files"])
        files["control/manifest.json"] = (manifest_bytes, 0o644)
        files["control/payload.sha256"] = (checksum_bytes, 0o644)
        self.rewrite_artifact_controls(forged, files)
        with self.assertRaisesRegex(builder.CandidateArtifactError, "exact Git"):
            self.verify(forged)


if __name__ == "__main__":
    unittest.main(verbosity=2)
