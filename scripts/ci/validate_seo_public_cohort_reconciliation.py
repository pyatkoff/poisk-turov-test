#!/usr/bin/env python3
"""Fail-closed validation for the review-only SEO public-cohort dossier."""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path
from typing import Any


MONTHS = {
    "january", "february", "march", "april", "may", "june",
    "july", "august", "september", "october", "november", "december",
}
EXPECTED_FILES = {
    "docs/seo-foundation-baseline.json": "4b3a1f01b934056d91abb8ff301feb8ca0d365ebcb6704997c6e117e5cd8d379",
    "v2/seo-launch-slice-v1.php": "03238f3e98a10092384416e6585b088362c36efab1f99b4f0c259dce1f8ee071",
    "v2/seo-publication-manifest-v1.php": "d18280435b6766474bd29d887c45909541067e14b25d41541bc4789016260b37",
    "v2/seo-sitemap-candidates-v1.php": "f27ca5deb6cdb2ab7d1774ebf1e59b1f90b65f1423ec9b3a9e7e714ae3ead99d",
    "v2/seo-production-identity-collector-v1.php": "a3f7f7f6bc15fb4e1dd58fe9d7b29b3f33028acef73c71b3d9a6ef5fe0d4757a",
    "v2/seo-production-identity-registry-v1.php": "fbdd2d8b8310a791c18fd5c6b4fea33442dd0874dd5828478a01e0aa8ac8ced2",
    "v2/seo-core-resort-launch-state-v1.php": "b636eb8d76072c6afe710e93228f78f82a0cb6fbac8ef125365a42ddadb94960",
    "v2/seo-postlaunch-feedback-v1.php": "ed30779f41c7ba7ea06ab045d548669b7c22eb1cf8c04cd510f15f2150215b2b",
}
BASE_COMMIT = "7feb3c6a965ddeee4f77893536ea31965821a5c4"
EXPECTED_SITEMAP_SHA256 = "cf61ea3d1d86e6d727c4a0eecc23dd24517950d19a2ac9fde359eea011365b87"
EXPECTED_SITEMAP_URL_LIST_SHA256 = "9e480e824bf465b9a6a6f1db5499b1114956effd35ae5786256907d6a18c96aa"
ALLOWED_DIFF_FILES = {
    ".github/workflows/validate-seo-public-cohort-reconciliation.yml",
    "docs/seo-public-cohort-reconciliation.json",
    "docs/seo-public-cohort-reconciliation.md",
    "scripts/ci/validate_seo_public_cohort_reconciliation.py",
    "tests/seo-public-cohort-reconciliation-test.py",
}
WORKFLOW_PATHS = [
    "docs/seo-public-cohort-reconciliation.json",
    "docs/seo-public-cohort-reconciliation.md",
    "docs/seo-foundation-baseline.json",
    "scripts/ci/validate_seo_public_cohort_reconciliation.py",
    "tests/seo-public-cohort-reconciliation-test.py",
    ".github/workflows/validate-seo-public-cohort-reconciliation.yml",
    "v2/seo-core-resort-launch-state-v1.php",
    "v2/seo-launch-slice-v1.php",
    "v2/seo-postlaunch-feedback-v1.php",
    "v2/seo-production-identity-collector-v1.php",
    "v2/seo-production-identity-registry-v1.php",
    "v2/seo-publication-manifest-v1.php",
    "v2/seo-sitemap-candidates-v1.php",
    "v2/sitemap.xml",
]
FORBIDDEN_WORKFLOW_TOKENS = (
    "github.token", "id-token", "secrets.", "ssh ", "git push", "deploy",
    "workflow_run", "curl ", "wget ", "http://", "https://",
)
EXPECTED_ROOT_KEYS = {
    "schema_version", "artifact", "status", "scope", "source_evidence", "reconciliation",
    "measurement", "commercial_hypotheses", "guardrails",
}


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def fail(errors: list[str], code: str) -> None:
    errors.append(code)


def object_with_exact_keys(value: Any, expected: set[str], label: str, errors: list[str]) -> dict[str, Any]:
    if not isinstance(value, dict):
        fail(errors, f"{label}_type")
        return {}
    keys = set(value)
    if keys != expected:
        fail(errors, f"{label}_keys")
    return value


def classify(url: str) -> str | None:
    prefix = "https://anytoour.ru"
    if not url.startswith(prefix):
        return None
    path = url[len(prefix):]
    if path == "/poisk-turov/":
        return "search"
    parts = [part for part in path.split("/") if part]
    if len(parts) >= 3 and parts[0] == "country" and parts[2] == "hotel":
        return "hotel_tours"
    if len(parts) == 2 and parts[0] == "country":
        return "country"
    if len(parts) == 3 and parts[0] == "country":
        return "seasonal" if parts[2] in MONTHS else "resort"
    if len(parts) == 4 and parts[0] == "country" and parts[3] in MONTHS:
        return "seasonal"
    return None


def load_static_sitemap(repo: Path, errors: list[str]) -> tuple[list[str], dict[str, int]]:
    sitemap = repo / "v2/sitemap.xml"
    try:
        root = ET.parse(sitemap).getroot()
        urls = [(node.text or "").strip() for node in root.iter("{http://www.sitemaps.org/schemas/sitemap/0.9}loc")]
    except (ET.ParseError, OSError) as exc:
        fail(errors, f"static_sitemap_unreadable:{exc.__class__.__name__}")
        return [], {}
    if len(urls) != len(set(urls)):
        fail(errors, "static_sitemap_duplicates")
    urls = sorted(set(urls))
    counts = {"country": 0, "resort": 0, "seasonal": 0, "hotel_tours": 0, "search": 0}
    for url in urls:
        kind = classify(url)
        if kind is None:
            fail(errors, f"static_sitemap_unclassified:{url}")
        else:
            counts[kind] += 1
    return urls, counts


def validate_diff_scope(repo: Path) -> list[str]:
    """Allow this stacked review slice to contain exactly its five new files."""
    commands = (
        ["git", "-C", str(repo), "cat-file", "-e", f"{BASE_COMMIT}^{{commit}}"],
        ["git", "-C", str(repo), "merge-base", "--is-ancestor", BASE_COMMIT, "HEAD"],
    )
    for command in commands:
        if subprocess.run(command, check=False, capture_output=True).returncode != 0:
            return ["scope_base_unavailable_or_not_ancestor"]
    changed = subprocess.run(
        ["git", "-C", str(repo), "diff", "--name-only", f"{BASE_COMMIT}..HEAD"],
        check=False, capture_output=True, text=True,
    )
    if changed.returncode != 0:
        return ["scope_diff_unavailable"]
    paths = {line.strip() for line in changed.stdout.splitlines() if line.strip()}
    errors: list[str] = []
    if paths != ALLOWED_DIFF_FILES:
        errors.append("scope_changed_files")
    if any(path == "v2" or path.startswith("v2/") for path in paths):
        errors.append("scope_runtime_change")
    return errors


def expected_workflow() -> str:
    """Render the complete approved workflow contract using only the stdlib."""
    paths = "\n".join(f"      - '{path}'" for path in WORKFLOW_PATHS)
    return f"""name: Validate SEO public cohort reconciliation

on:
  pull_request:
    branches: [main]
    paths:
{paths}
  push:
    branches: [main]
    paths:
{paths}
  workflow_dispatch:

permissions:
  contents: read

concurrency:
  group: seo-public-cohort-reconciliation-${{{{ github.ref }}}}
  cancel-in-progress: true

jobs:
  review-only-cohort:
    runs-on: ubuntu-latest
    timeout-minutes: 5
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - name: Validate review-only cohort dossier
        shell: bash
        run: |
          set -euo pipefail
          if [ "${{{{ github.event_name }}}}" = "pull_request" ]; then
            python3 scripts/ci/validate_seo_public_cohort_reconciliation.py --enforce-diff
          else
            python3 scripts/ci/validate_seo_public_cohort_reconciliation.py
          fi
          python3 tests/seo-public-cohort-reconciliation-test.py
          git diff --check
"""


def validate_workflow(repo: Path, errors: list[str]) -> None:
    workflow = repo / ".github/workflows/validate-seo-public-cohort-reconciliation.yml"
    try:
        raw = workflow.read_text(encoding="utf-8")
    except OSError as exc:
        fail(errors, f"workflow_unreadable:{exc.__class__.__name__}")
        return
    if raw != expected_workflow():
        fail(errors, "workflow_exact_contract")
    lower = raw.lower()
    if any(token in lower for token in FORBIDDEN_WORKFLOW_TOKENS):
        fail(errors, "workflow_side_effect_token")


def validate(repo: Path, artifact_path: Path) -> list[str]:
    errors: list[str] = []
    try:
        artifact = json.loads(artifact_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        return [f"artifact_unreadable:{exc.__class__.__name__}"]
    if not isinstance(artifact, dict):
        return ["artifact_root_type"]
    object_with_exact_keys(artifact, EXPECTED_ROOT_KEYS, "artifact_root", errors)

    if artifact.get("schema_version") != 1:
        fail(errors, "schema_version")
    if artifact.get("artifact") != "anytour_public_seo_cohort_reconciliation":
        fail(errors, "artifact_name")
    if artifact.get("status") != "review_only_measurement_pending":
        fail(errors, "review_only_status")
    scope = object_with_exact_keys(artifact.get("scope"), {
        "domain", "base_commit", "base_tree", "publication_side_effects", "runtime_changes",
        "indexation_changes", "canonical_changes", "sitemap_changes", "route_changes", "schema_changes",
        "metrika_changes", "tourvisor_changes", "lead_changes", "price_or_offer_changes",
    }, "scope", errors)
    if scope.get("domain") != "anytoour.ru":
        fail(errors, "scope_domain")
    if scope.get("base_commit") != BASE_COMMIT:
        fail(errors, "base_commit")
    if scope.get("base_tree") != "8a17fbfb1762f3ffa3dc7341be3539161e5eab86":
        fail(errors, "base_tree")
    for key in (
        "publication_side_effects", "runtime_changes", "indexation_changes", "canonical_changes",
        "sitemap_changes", "route_changes", "schema_changes", "metrika_changes", "tourvisor_changes",
        "lead_changes", "price_or_offer_changes",
    ):
        if scope.get(key) is not False:
            fail(errors, f"scope_must_be_false:{key}")

    evidence = object_with_exact_keys(artifact.get("source_evidence"), {
        "owner_sha256", "static_sitemap", "dynamic_materializer", "fresh_production_identity", "historical_reference",
    }, "source_evidence", errors)
    owner_hashes = object_with_exact_keys(evidence.get("owner_sha256"), set(EXPECTED_FILES), "owner_hashes", errors)
    if owner_hashes != EXPECTED_FILES:
        fail(errors, "owner_hash_registry")
    for relative, expected_hash in EXPECTED_FILES.items():
        source = repo / relative
        if not source.is_file() or sha256(source) != expected_hash:
            fail(errors, f"source_hash_drift:{relative}")

    sitemap_path = repo / "v2/sitemap.xml"
    urls, counts = load_static_sitemap(repo, errors)
    static = object_with_exact_keys(evidence.get("static_sitemap"), {
        "path", "file_sha256", "canonical_url_list_sha256", "url_count", "type_counts", "duplicates",
    }, "static_sitemap", errors)
    actual_sitemap_sha = sha256(sitemap_path) if sitemap_path.is_file() else ""
    if actual_sitemap_sha != EXPECTED_SITEMAP_SHA256:
        fail(errors, "static_sitemap_source_hash_drift")
    if static.get("path") != "v2/sitemap.xml" or static.get("file_sha256") != EXPECTED_SITEMAP_SHA256:
        fail(errors, "static_sitemap_file_hash")
    list_hash = hashlib.sha256(("\n".join(urls) + "\n").encode("utf-8")).hexdigest()
    if list_hash != EXPECTED_SITEMAP_URL_LIST_SHA256:
        fail(errors, "static_sitemap_source_url_list_drift")
    if static.get("canonical_url_list_sha256") != EXPECTED_SITEMAP_URL_LIST_SHA256:
        fail(errors, "static_sitemap_url_list_hash")
    type_counts = object_with_exact_keys(static.get("type_counts"), {"country", "resort", "seasonal", "hotel_tours", "search"}, "static_sitemap_type_counts", errors)
    if static.get("url_count") != len(urls) or static.get("duplicates") != 0 or type_counts != counts:
        fail(errors, "static_sitemap_counts")
    if counts != {"country": 3, "resort": 5, "seasonal": 96, "hotel_tours": 0, "search": 0}:
        fail(errors, "static_sitemap_expected_boundary")

    dynamic = object_with_exact_keys(evidence.get("dynamic_materializer"), {
        "source_owner", "source_owner_sha256", "runtime_artifact", "status", "included_in_static_sitemap_count",
    }, "dynamic_materializer", errors)
    if dynamic != {
        "source_owner": "v2/seo-core-resort-launch-state-v1.php",
        "source_owner_sha256": "b636eb8d76072c6afe710e93228f78f82a0cb6fbac8ef125365a42ddadb94960",
        "runtime_artifact": "v2/data/generated/seo-core-resort-review-routes-v1.json",
        "status": "requires_fresh_artifact_for_live_reconciliation",
        "included_in_static_sitemap_count": False,
    }:
        fail(errors, "dynamic_materializer_boundary")

    live = object_with_exact_keys(evidence.get("fresh_production_identity"), {
        "status", "required_workflow", "required_artifact", "required_fields", "maximum_age_seconds",
    }, "fresh_production_identity", errors)
    required_live = {"successful_run_url", "run_id", "head_sha", "observed_at_utc", "artifact_sha256", "identity_registry_sha256"}
    if live.get("status") != "not_available_in_checkout" or live.get("maximum_age_seconds") != 86400:
        fail(errors, "fresh_live_status")
    required_fields = live.get("required_fields")
    if not isinstance(required_fields, list) or not all(isinstance(value, str) for value in required_fields) or set(required_fields) != required_live:
        fail(errors, "fresh_live_required_fields")
    if live.get("required_workflow") != ".github/workflows/collect-seo-production-evidence.yml" or live.get("required_artifact") != "seo-production-identity.json":
        fail(errors, "fresh_live_owner")
    historic = object_with_exact_keys(evidence.get("historical_reference"), {
        "source_owner", "source_owner_sha256", "available_in_checkout", "eligible_for_current_reconciliation",
        "reason", "identity_registry_sha256",
    }, "historical_reference", errors)
    if historic.get("source_owner") != "v2/seo-postlaunch-feedback-v1.php" or historic.get("source_owner_sha256") != "ed30779f41c7ba7ea06ab045d548669b7c22eb1cf8c04cd510f15f2150215b2b":
        fail(errors, "historical_reference_hash")
    if historic.get("eligible_for_current_reconciliation") is not False or historic.get("available_in_checkout") is not True:
        fail(errors, "historical_reference_boundary")

    reconciliation = object_with_exact_keys(artifact.get("reconciliation"), {
        "source_candidate_set", "live_identity_set", "sitemap_set", "excluded_set", "difference_set",
        "unexplained_differences", "next_action",
    }, "reconciliation", errors)
    expected_exclusions = ["/poisk-turov/", "hotel_tours", "query_parameters", "fragments", "external_urls"]
    if reconciliation.get("excluded_set") != expected_exclusions:
        fail(errors, "excluded_set")
    if reconciliation.get("source_candidate_set") != "static_sitemap_only" or reconciliation.get("live_identity_set") != "pending_fresh_production_identity":
        fail(errors, "reconciliation_state")
    if reconciliation.get("difference_set") != "not_computed_without_fresh_production_identity_and_dynamic_materializer_artifact":
        fail(errors, "difference_set")

    measurement = object_with_exact_keys(artifact.get("measurement"), {
        "status", "aggregate_exports_present", "direct_metrika_access", "user_level_data_present",
        "allowed_future_aggregate_fields", "prohibited_interpretation",
    }, "measurement", errors)
    if measurement.get("status") != "measurement_pending" or measurement.get("aggregate_exports_present") is not False:
        fail(errors, "measurement_pending")
    if measurement.get("direct_metrika_access") is not False or measurement.get("user_level_data_present") is not False:
        fail(errors, "measurement_privacy_boundary")
    future_fields = measurement.get("allowed_future_aggregate_fields")
    if not isinstance(future_fields, list) or not all(isinstance(value, str) for value in future_fields) or set(future_fields) != {"impressions", "clicks", "average_position", "organic_sessions", "search_starts", "completed_leads"}:
        fail(errors, "measurement_allowed_fields")
    if any(key in measurement for key in ("metrics", "demand_score", "opportunity_score", "ranking_claim")):
        fail(errors, "invented_measurement")

    hypotheses = artifact.get("commercial_hypotheses")
    if not isinstance(hypotheses, list) or len(hypotheses) != 3:
        fail(errors, "commercial_hypotheses")
        hypotheses = []
    normalized_hypotheses = [object_with_exact_keys(row, {"page_type", "intent", "conversion_hypothesis", "measurement_status"}, "commercial_hypothesis", errors) for row in hypotheses]
    if [row.get("page_type") for row in normalized_hypotheses] != ["country", "resort", "seasonal"]:
        fail(errors, "commercial_hypotheses")
    if any(row.get("measurement_status") != "pending" for row in normalized_hypotheses):
        fail(errors, "hypothesis_measurement_status")

    guardrails = object_with_exact_keys(artifact.get("guardrails"), {
        "automatic_execution_allowed", "publication_allowed", "indexation_allowed", "sitemap_allowed",
        "canonical_allowed", "route_allowed", "hotel_tours_eligible", "search_route_eligible", "recommendations_only",
    }, "guardrails", errors)
    for key in ("automatic_execution_allowed", "publication_allowed", "indexation_allowed", "sitemap_allowed", "canonical_allowed", "route_allowed", "hotel_tours_eligible", "search_route_eligible"):
        if guardrails.get(key) is not False:
            fail(errors, f"guardrail_must_be_false:{key}")
    if guardrails.get("recommendations_only") is not True:
        fail(errors, "recommendations_only")

    validate_workflow(repo, errors)
    return errors


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--repo", type=Path, default=Path(__file__).resolve().parents[2])
    parser.add_argument("--artifact", type=Path)
    parser.add_argument("--enforce-diff", action="store_true")
    args = parser.parse_args()
    repo = args.repo.resolve()
    artifact = args.artifact.resolve() if args.artifact else repo / "docs/seo-public-cohort-reconciliation.json"
    errors = validate(repo, artifact)
    if args.enforce_diff:
        errors.extend(validate_diff_scope(repo))
    if errors:
        print("SEO_PUBLIC_COHORT_RECONCILIATION_FAIL", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1
    print("SEO_PUBLIC_COHORT_RECONCILIATION_OK static=104 country=3 resort=5 seasonal=96 live=measurement_pending exclusions=preserved")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
