#!/usr/bin/env python3
"""Adversarial tests for the SEO public-cohort reconciliation validator."""

from __future__ import annotations

import json
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
VALIDATOR = ROOT / "scripts/ci/validate_seo_public_cohort_reconciliation.py"
ARTIFACT = ROOT / "docs/seo-public-cohort-reconciliation.json"


def run(artifact: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(VALIDATOR), "--repo", str(ROOT), "--artifact", str(artifact)],
        check=False, capture_output=True, text=True,
    )


def run_with_repo(repo: Path, artifact: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(VALIDATOR), "--repo", str(repo), "--artifact", str(artifact)],
        check=False, capture_output=True, text=True,
    )


def minimal_repo() -> tuple[tempfile.TemporaryDirectory[str], Path, Path]:
    temporary = tempfile.TemporaryDirectory()
    clone = Path(temporary.name) / "repo"
    for relative in (
        "docs/seo-foundation-baseline.json",
        "v2/sitemap.xml",
        "v2/seo-launch-slice-v1.php",
        "v2/seo-publication-manifest-v1.php",
        "v2/seo-sitemap-candidates-v1.php",
        "v2/seo-production-identity-collector-v1.php",
        "v2/seo-production-identity-registry-v1.php",
        "v2/seo-core-resort-launch-state-v1.php",
        "v2/seo-postlaunch-feedback-v1.php",
        ".github/workflows/validate-seo-public-cohort-reconciliation.yml",
    ):
        target = clone / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(ROOT / relative, target)
    candidate = clone / "artifact.json"
    shutil.copy2(ARTIFACT, candidate)
    return temporary, clone, candidate


def expect_rejected(name: str, mutate) -> None:
    payload = json.loads(ARTIFACT.read_text(encoding="utf-8"))
    mutate(payload)
    with tempfile.TemporaryDirectory() as temporary:
        candidate = Path(temporary) / "artifact.json"
        candidate.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
        result = run(candidate)
    if result.returncode == 0:
        raise AssertionError(f"{name}: mutation was accepted")


def expect_malformed_rejected() -> None:
    with tempfile.TemporaryDirectory() as temporary:
        candidate = Path(temporary) / "artifact.json"
        candidate.write_text("[", encoding="utf-8")
        result = run(candidate)
    if result.returncode == 0:
        raise AssertionError("malformed_json: mutation was accepted")


def expect_repo_rejected(name: str, mutate) -> None:
    temporary, clone, candidate = minimal_repo()
    try:
        mutate(clone)
        result = run_with_repo(clone, candidate)
    finally:
        temporary.cleanup()
    if result.returncode == 0:
        raise AssertionError(f"{name}: repository mutation was accepted")


def expect_same_count_url_substitution_rejected() -> None:
    temporary, clone, candidate = minimal_repo()
    try:
        sitemap = clone / "v2/sitemap.xml"
        original = "<loc>https://anytoour.ru/country/turkey/</loc>"
        replacement = "<loc>https://anytoour.ru/country/russia/</loc>"
        text = sitemap.read_text(encoding="utf-8")
        if text.count(original) != 1:
            raise AssertionError("same-count fixture precondition")
        sitemap.write_text(text.replace(original, replacement), encoding="utf-8")
        result = run_with_repo(clone, candidate)
    finally:
        temporary.cleanup()
    if result.returncode == 0:
        raise AssertionError("same_count_url_substitution: mutation was accepted")


def main() -> int:
    clean = run(ARTIFACT)
    if clean.returncode != 0:
        raise AssertionError(clean.stderr)
    scoped = subprocess.run(
        [sys.executable, str(VALIDATOR), "--repo", str(ROOT), "--enforce-diff"],
        check=False, capture_output=True, text=True,
    )
    if scoped.returncode != 0:
        raise AssertionError(scoped.stderr)
    expect_rejected("invented_metrics", lambda p: p["measurement"].update({"metrics": {"clicks": 1}}))
    expect_rejected("measurement_complete", lambda p: p["measurement"].update({"status": "measured"}))
    expect_rejected("hotel_eligible", lambda p: p["guardrails"].update({"hotel_tours_eligible": True}))
    expect_rejected("search_eligible", lambda p: p["guardrails"].update({"search_route_eligible": True}))
    expect_rejected("sitemap_count_drift", lambda p: p["source_evidence"]["static_sitemap"].update({"url_count": 105}))
    expect_rejected("fake_live_claim", lambda p: p["source_evidence"]["fresh_production_identity"].update({"status": "fresh"}))
    expect_rejected("source_hash_drift", lambda p: p["source_evidence"]["owner_sha256"].update({"v2/seo-launch-slice-v1.php": "0" * 64}))
    expect_rejected("root_publication_allowed", lambda p: p.update({"publication_allowed": False}))
    expect_rejected("scope_deploy_now", lambda p: p["scope"].update({"deploy_now": False}))
    expect_rejected("measurement_metrika", lambda p: p["measurement"].update({"metrika": False}))
    expect_rejected("measurement_tourvisor", lambda p: p["measurement"].update({"tourvisor": False}))
    expect_rejected("measurement_lead_payload", lambda p: p["measurement"].update({"lead_payload": {}}))
    expect_rejected("guardrail_auto_deploy", lambda p: p["guardrails"].update({"automatic_deploy_allowed": False}))
    expect_rejected("hypotheses_null", lambda p: p.update({"commercial_hypotheses": None}))
    expect_rejected("required_fields_int", lambda p: p["source_evidence"]["fresh_production_identity"].update({"required_fields": 1}))
    expect_rejected("required_fields_object", lambda p: p["source_evidence"]["fresh_production_identity"].update({"required_fields": [{}]}))
    expect_rejected("future_fields_object", lambda p: p["measurement"].update({"allowed_future_aggregate_fields": [{}]}))
    expect_malformed_rejected()
    expect_same_count_url_substitution_rejected()
    expect_repo_rejected("missing_sitemap", lambda repo: (repo / "v2/sitemap.xml").unlink())
    expect_repo_rejected("workflow_job_permissions", lambda repo: (repo / ".github/workflows/validate-seo-public-cohort-reconciliation.yml").write_text((repo / ".github/workflows/validate-seo-public-cohort-reconciliation.yml").read_text(encoding="utf-8").replace("    runs-on: ubuntu-latest", "    permissions:\n      contents: write\n    runs-on: ubuntu-latest"), encoding="utf-8"))
    expect_repo_rejected("workflow_id_token", lambda repo: (repo / ".github/workflows/validate-seo-public-cohort-reconciliation.yml").write_text((repo / ".github/workflows/validate-seo-public-cohort-reconciliation.yml").read_text(encoding="utf-8").replace("  contents: read", "  contents: read\n  id-token: write"), encoding="utf-8"))
    expect_repo_rejected("workflow_git_push", lambda repo: (repo / ".github/workflows/validate-seo-public-cohort-reconciliation.yml").write_text((repo / ".github/workflows/validate-seo-public-cohort-reconciliation.yml").read_text(encoding="utf-8") + "\n          git push\n", encoding="utf-8"))
    print("SEO_PUBLIC_COHORT_RECONCILIATION_TEST_OK adversarial=22 scope=1 stdlib=1")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
