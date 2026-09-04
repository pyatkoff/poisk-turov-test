#!/usr/bin/env python3
"""Adversarial tests for the public SEO editorial/link readiness validator."""

from __future__ import annotations

import importlib.util
import json
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path


sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
VALIDATOR = ROOT / "scripts/ci/validate_seo_public_cohort_editorial_link_readiness.py"
ARTIFACT = ROOT / "docs/seo-public-cohort-editorial-link-readiness.json"


def validator_module():
    spec = importlib.util.spec_from_file_location("editorial_link_validator", VALIDATOR)
    if spec is None or spec.loader is None:
        raise AssertionError("validator module unavailable")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def sibling_evidence(repo: Path) -> tuple[dict[str, tuple[str, ...]], list[str]]:
    module = validator_module()
    errors: list[str] = []
    urls = module.static_urls(repo, errors)
    entrypoints = module.static_entrypoints(repo, urls, errors)
    seasonal_paths = {url.removeprefix("https://anytoour.ru") for url in urls if module.classify(url) == "seasonal"}
    evidence = {
        path: module.rendered_sibling_month_urls(repo, seasonal_paths, path, entrypoints[path], errors)
        for path in sorted(seasonal_paths)
        if path in entrypoints
    }
    return evidence, errors


def run(artifact: Path, repo: Path = ROOT) -> subprocess.CompletedProcess[str]:
    return subprocess.run([sys.executable, "-S", str(VALIDATOR), "--repo", str(repo), "--artifact", str(artifact)], capture_output=True, text=True, check=False)


def rejected(name: str, change) -> None:
    payload = json.loads(ARTIFACT.read_text(encoding="utf-8"))
    change(payload)
    with tempfile.TemporaryDirectory() as directory:
        candidate = Path(directory) / "artifact.json"
        candidate.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
        if run(candidate).returncode == 0:
            raise AssertionError(f"accepted artifact mutation: {name}")


def repo_clone() -> tuple[tempfile.TemporaryDirectory[str], Path, Path]:
    temp = tempfile.TemporaryDirectory()
    clone = Path(temp.name) / "repo"
    shutil.copytree(ROOT, clone, ignore=shutil.ignore_patterns(".git"))
    return temp, clone, clone / "docs/seo-public-cohort-editorial-link-readiness.json"


def rejected_repo(name: str, change) -> None:
    temp, clone, artifact = repo_clone()
    try:
        change(clone)
        if run(artifact, clone).returncode == 0:
            raise AssertionError(f"accepted repository mutation: {name}")
    finally:
        temp.cleanup()


def main() -> int:
    clean = run(ARTIFACT)
    if clean.returncode != 0:
        raise AssertionError(clean.stderr)
    scoped = subprocess.run([sys.executable, "-S", str(VALIDATOR), "--repo", str(ROOT), "--enforce-diff"], capture_output=True, text=True, check=False)
    if scoped.returncode != 0:
        raise AssertionError(scoped.stderr)
    rejected("unknown_root", lambda p: p.update({"automatic_publish": False}))
    rejected("malformed_scope", lambda p: p.update({"scope": []}))
    rejected("unhashable_owner_value", lambda p: p["source_evidence"]["owner_sha256"].update({"v2/country-page-v1.php": []}))
    rejected("same_count_cohort_lie", lambda p: p["cohort"].update({"node_count": 105}))
    rejected("invented_metric", lambda p: p["measurement"].update({"metrics": {"clicks": 1}}))
    rejected("invented_interpretation", lambda p: p["measurement"].update({"interpretation": "traffic_is_zero"}))
    rejected("fake_live_ready", lambda p: p["production_identity_readiness"].update({"status": "fresh"}))
    rejected("fake_live_fields", lambda p: p["production_identity_readiness"].update({"required_fields": {"run_id": "x"}}))
    rejected("protected_search_node", lambda p: p["cohort"].update({"excluded_nodes": []}))
    rejected("gap_resolved", lambda p: p["structural_graph_contract"]["turkey_country_to_static_resorts"].update({"status": "resolved"}))
    rejected("gap_unhashable", lambda p: p["structural_graph_contract"].update({"turkey_country_to_static_resorts": []}))
    rejected("copy_generation", lambda p: p["editorial_readiness"].update({"automatic_copy_or_link_change_allowed": True}))
    rejected("deploy_permission", lambda p: p["guardrails"].update({"deploy_allowed": True}))
    with tempfile.TemporaryDirectory() as directory:
        malformed = Path(directory) / "artifact.json"
        malformed.write_text("[", encoding="utf-8")
        if run(malformed).returncode == 0:
            raise AssertionError("accepted malformed json")
    rejected_repo("sitemap_substitution", lambda r: (r / "v2/sitemap.xml").write_text((r / "v2/sitemap.xml").read_text(encoding="utf-8").replace("/country/turkey/</loc>", "/country/russia/</loc>"), encoding="utf-8"))
    rejected_repo("missing_entrypoint", lambda r: (r / "v2/country/turkey/index.php").unlink())
    rejected_repo("owner_drift", lambda r: (r / "v2/country-page-v1.php").write_text("<?php\n", encoding="utf-8"))
    rejected_repo("turkey_launch_state_link_drift", lambda r: (r / "v2/seo-core-resort-launch-state-v1.php").write_text("<?php\n", encoding="utf-8"))
    rejected_repo("catalog_dependency_drift", lambda r: (r / "v2/seo-content-catalog-v1.php").write_text("<?php\n", encoding="utf-8"))
    rejected_repo("ds2_dependency_drift", lambda r: (r / "v2/seo-ds2-reference-pages-v1.php").write_text("<?php\n", encoding="utf-8"))
    rejected_repo("pilot_related_link_drift", lambda r: (r / "v2/seo-content-pilot-seasonal-september-v1.php").write_text("<?php\n", encoding="utf-8"))
    owner_hashes = json.loads(ARTIFACT.read_text(encoding="utf-8"))["source_evidence"]["owner_sha256"]
    module = validator_module()
    closure_errors: list[str] = []
    clean_urls = module.static_urls(ROOT, closure_errors)
    clean_entrypoints = module.static_entrypoints(ROOT, clean_urls, closure_errors)
    literal_owners = module.literal_dependency_owners(ROOT, clean_entrypoints, closure_errors)
    if closure_errors or literal_owners != set(owner_hashes) or not {"v2/seo-content-catalog-v1.php", "v2/seo-ds2-reference-pages-v1.php"}.issubset(literal_owners):
        raise AssertionError("literal dependency closure is incomplete")
    evidence, sibling_errors = sibling_evidence(ROOT)
    if sibling_errors or len(evidence) != 96 or any(len(links) != 11 for path, links in evidence.items() if path not in module.PILOT_SEASONAL_PATHS) or any(links for path, links in evidence.items() if path in module.PILOT_SEASONAL_PATHS):
        raise AssertionError("clean sibling rendering evidence is incomplete")
    temp, clone, _ = repo_clone()
    try:
        source = clone / "v2/seo-core-month-content-v1.php"
        source.write_text(source.read_text(encoding="utf-8").replace("'related'=>array_slice($related,0,11)", "'related'=>array_slice($related,0,10)"), encoding="utf-8")
        _, errors = sibling_evidence(clone)
        if not any(error.startswith("seasonal_record:generic_sibling_rendering_changed:") for error in errors):
            raise AssertionError("accepted generic sibling rendering mutation")
    finally:
        temp.cleanup()
    temp, clone, _ = repo_clone()
    try:
        source = clone / "v2/seo-content-pilot-seasonal-september-v1.php"
        source.write_text(source.read_text(encoding="utf-8").replace("['label'=>'Все туры на Мальдивы','href'=>'/country/maldives/'],", "['label'=>'Все туры на Мальдивы','href'=>'/country/maldives/'],\n                ['label'=>'Мальдивы в январе','href'=>'/country/maldives/january/'],"), encoding="utf-8")
        _, errors = sibling_evidence(clone)
        if "pilot_route:unexpected_sibling_month_links:/country/maldives/september/" not in errors:
            raise AssertionError("accepted pilot sibling month link")
    finally:
        temp.cleanup()
    for owner in sorted(owner_hashes):
        rejected_repo(f"pinned_owner_drift:{owner}", lambda r, path=owner: (r / path).write_text("<?php\n", encoding="utf-8"))
    rejected_repo("workflow_write", lambda r: (r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").write_text((r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").read_text(encoding="utf-8").replace("contents: read", "contents: write"), encoding="utf-8"))
    rejected_repo("workflow_target", lambda r: (r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").write_text((r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").read_text(encoding="utf-8").replace("pull_request:", "pull_request_target:"), encoding="utf-8"))
    rejected_repo("workflow_push", lambda r: (r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").write_text((r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").read_text(encoding="utf-8") + "          git push\n", encoding="utf-8"))
    rejected_repo("workflow_entrypoint_coverage", lambda r: (r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").write_text((r / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml").read_text(encoding="utf-8").replace("      - 'v2/country/**/index.php'\n", ""), encoding="utf-8"))
    print("SEO_PUBLIC_COHORT_EDITORIAL_LINK_READINESS_TEST_OK adversarial=28 owner_drift=" + str(len(owner_hashes)) + " literal_closure=1 rendered_sibling_sets=96 stdlib=1")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
