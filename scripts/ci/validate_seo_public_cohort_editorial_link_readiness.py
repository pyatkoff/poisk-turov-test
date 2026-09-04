#!/usr/bin/env python3
"""Fail-closed validator for the read-only public SEO editorial/link dossier."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path
from typing import Any


BASE_COMMIT = "200c215eafe0e0e74b48609c9673ef1aaa029147"
BASE_TREE = "5ae4ede90959360a1061496895a2a08aa36f5f81"
SITEMAP_SHA = "cf61ea3d1d86e6d727c4a0eecc23dd24517950d19a2ac9fde359eea011365b87"
URL_LIST_SHA = "9e480e824bf465b9a6a6f1db5499b1114956effd35ae5786256907d6a18c96aa"
ENTRYPOINT_SHA = "4612910a9e993236ecb57d730694cb6633a69f2ed8e5086127fe1858636e410f"
PILOT_SEASONAL_PATHS = frozenset({"/country/turkey/antalya/september/", "/country/maldives/september/"})
MONTHS = frozenset("january february march april may june july august september october november december".split())
ALLOWED_FILES = frozenset({
    ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml",
    "docs/seo-public-cohort-editorial-link-readiness.json",
    "docs/seo-public-cohort-editorial-link-readiness.md",
    "scripts/ci/validate_seo_public_cohort_editorial_link_readiness.py",
    "tests/seo-public-cohort-editorial-link-readiness-test.py",
})
OWNER_HASHES = {
    "v2/asset-version-v1.php": "f39fc578b10180a992a4614dc880a0c1da6aaa52b0c1e851d5869096fd5b7950",
    "v2/assets.php": "432bff57cdb360176a9fcc409f2f66bcba97f3571c8d7c06c502b7a5b4f2f076",
    "v2/bundle-manifest-v1.php": "634ed68bb20d0ae93725e591b70901688fecdf5c9c08562025423c50fb0c9a5d",
    "v2/country-page-v1.php": "7a1c91a899a3bac1daa285a07ad6b586604fb08008063b6263a91e6abd51017b",
    "v2/data/db-v1.php": "c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13",
    "v2/data/price-calendar-core-v1.php": "92f0e9df57555425ae1a861255a9346dbb13510dbfe81818486c1a0069659123",
    "v2/data/price-confidence-v1.php": "c8f607ab65f99d2991732dd539b00c4c34ec8cd7e9ac36077232335908e47759",
    "v2/data/price-intelligence-v1.php": "b49f7acf88b06bf3d204b845580f1b93a29e1ec70f4b27d0e1416551d1f3a174",
    "v2/phone-value.php": "cc94788cda8ae7fabd6b3d3964dff2f5b56d9e33cf83bd6289dfda3c60774a40",
    "v2/seo-config.php": "a4776aa4e8f2a139933f453b2525aba56bf35ce523464601c508c79044298a6e",
    "v2/seo-content-catalog-v1.php": "a599fae60fcc66c17212ebbb8b3bcf96590daa89cb65fe67e1d49b1362ea1c6a",
    "v2/seo-content-pilot-alanya-v1.php": "ed59774abe650395b7b967bbdd716233a53abef7a2b6569e7dee27115e513dcf",
    "v2/seo-content-pilot-antalya-v1.php": "705b5160af29eb06d6164c0b1eea5214bec28079c393b08776e78083980d9f96",
    "v2/seo-content-pilot-belek-v1.php": "3633b7c29e8e3e034c6c7f5c1eeb4973d1ea393fcbc2125d1cb62aff7787d9c5",
    "v2/seo-content-pilot-egypt-v1.php": "cb692984dcbeb5fa30309098bfcfb2f2ca4700ec27c1c0387e087ad14882dd7c",
    "v2/seo-content-pilot-egypt-hotels-v1.php": "b40b4b5b4bd5bb70ea9a30c423e136c49b07856f371ca29ca4e1530c88f90fd8",
    "v2/seo-content-pilot-kemer-v1.php": "70ddce168151614a31f497db481192e3a36dc6eac8497253ea811ee857f30315",
    "v2/seo-content-pilot-maldives-v1.php": "5407ef0ac3b6650467d33c561a2eaf894e13eba1ed7ca8bc36c3a5e41a6610e8",
    "v2/seo-content-pilot-maldives-hotels-v1.php": "2363046ca2c2133baf4806019caf922365066fe587ec9bcfe3f87a35abb0848b",
    "v2/seo-content-pilot-seasonal-september-v1.php": "37ac80e880ba9017788855c6513085d677434f9471ea7905c7b1e82fafeb4cb9",
    "v2/seo-content-pilot-side-v1.php": "0c8a6bb3f613206a574050677bf7799fb23382e3f330ee83610dd5c8a26d326d",
    "v2/seo-content-pilot-turkey-v1.php": "86275866eb8427bfdb753208340d137a2b23fd1540ffbe4b7ead0bbf73d5ad0b",
    "v2/seo-core-month-content-v1.php": "8d32c19497bb22ea23b4536e9c637d73fddf185ed45e8c01bace01f5e4eeca33",
    "v2/seo-core-month-matrix-v1.php": "a9876806a7ead25f4420d737129381b2c24cfe0eed0a2d24c6bc9b2c3f68c53a",
    "v2/seo-core-month-navigation-v1.php": "36383bf64d418b70c51f3036ce0dabc230ba4268df9c152a0abe49e284c21212",
    "v2/seo-core-month-route-resolver-v1.php": "29ba28ab118c6c8a53cd292182fd498b3582a967dd4f833cd138ce9e50774884",
    "v2/seo-core-resort-launch-state-v1.php": "b636eb8d76072c6afe710e93228f78f82a0cb6fbac8ef125365a42ddadb94960",
    "v2/seo-ds2-reference-pages-v1.php": "62b3ee4c737151ea6fabcb20b9db03d98b5bcc22baa6a07533081ca4cb542bec",
    "v2/seo-hotel-launch-readiness-v1.php": "c1bf44d35e5758f1cdf91d178d29bc6fb1300eabaaf07efd5597f34e87058302",
    "v2/seo-internal-links-v1.php": "14ce420034764af0b68d64e222a32007bf6042d329ef783173fb7772ebafaa0c",
    "v2/seo-launch-slice-v1.php": "03238f3e98a10092384416e6585b088362c36efab1f99b4f0c259dce1f8ee071",
    "v2/seo-offer-snapshot-v1.php": "673a177ec6da6e75501951d1e1819214eff3a2b7d924a7915c75f66ca090fd24",
    "v2/seo-page-contract-v1.php": "c553d42231d70eb2fb14a2f5c64d60e258db03277e2c548dd4c54ed7499015f6",
    "v2/seo-page-graph-v1.php": "33293bc717f329e67c57fef5b11954e43e9d1f2ac9f4c41c80387847cca1c62b",
    "v2/seo-page-launch-readiness-v1.php": "5a0172a28f304b28d050e4277e5af2a4ad32359b932d41c1a390999cd29aea52",
    "v2/seo-page-primitives-v1.php": "91fd195d006c6579922280d37496e4d9ae523d962936cea088b02a7875574e0a",
    "v2/seo-page-registry-v1.php": "a59a4b178e160d019d18de487e2acd5c9f3b0ae6c506206f4569718bf31eb7e7",
    "v2/seo-page-types-v1.php": "28d3250b7f35762960ec8fcf400c0451ba6d658c05e4aed5462baa177686a7c4",
    "v2/seo-price-calendar-v1.php": "e0d2c0c1ed66cae411950b15a739fbb01ea944224787bc01b6c242f36562fc5f",
    "v2/seo-publication-manifest-v1.php": "d18280435b6766474bd29d887c45909541067e14b25d41541bc4789016260b37",
    "v2/seo-publishability-v1.php": "75fa938a9af159201db964fc358fef034ec65f5f3a71cf5b1d82612025beb597",
    "v2/seo-resort-family-integrity-v1.php": "99d0701a5dc51ab9d050f4ef9e0ccfdd94b4ad2b015b9aa658e6add124e6c673",
    "v2/seo-production-identity-collector-v1.php": "a3f7f7f6bc15fb4e1dd58fe9d7b29b3f33028acef73c71b3d9a6ef5fe0d4757a",
    "v2/seo-production-identity-registry-v1.php": "fbdd2d8b8310a791c18fd5c6b4fea33442dd0874dd5828478a01e0aa8ac8ced2",
    "v2/seo-resort-page-v1.php": "e8b5ee7fd6d38929787b1889506ee7e67be252c6182ab9c3ef68cca89602f2e4",
    "v2/seo-seasonal-family-registry-v1.php": "ce8b3486b2568af1130e9cce2fa24ba129d1f5675d3a0797d30c254529ac6597",
    "v2/seo-seasonal-offer-snapshot-v1.php": "af34b7a9b153c616744e9771d5806f1ede5925a80e0da5eff1031c49d405b56a",
    "v2/seo-seasonal-page-v1.php": "c4dda323f33f80b7b7e969826fed1eb00fdfb9ec33eeedeb20e6d3349a69c067",
    "v2/seo-sitemap-candidates-v1.php": "f27ca5deb6cdb2ab7d1774ebf1e59b1f90b65f1423ec9b3a9e7e714ae3ead99d",
    "v2/seo-structured-data-v1.php": "2976fe0b44e64c3552f1ba929a071408e29b5ff9fb7d902649e9ea591c9d3ec3",
    "v2/site-footer-v1.php": "bf21442d0012437ef35c6a014a232e0e262f958cc238960c3a16b473ff3ba71d",
    "v2/site-header-v2.php": "d7756a4ddd909990c7f7fa5dec1f610ab7931e2a3ecdd2b1151e17d41a8d456b",
    "v2/site-page-shell-v1.php": "16558af3da99070179b2973e9709eda1deace6030ad5fb99c72dd5012f956259",
}
LITERAL_DEPENDENCY_ROOTS = frozenset({"v2/seo-production-identity-collector-v1.php"})
REQUIRE_STATEMENT = re.compile(r"require(?:_once)?\s+[^;]+;", re.IGNORECASE)
LITERAL_PHP_PATH = re.compile(r"['\"](/?[^'\"]+\.php)['\"]")


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def exact_object(value: Any, keys: set[str], name: str, errors: list[str]) -> dict[str, Any]:
    if not isinstance(value, dict):
        errors.append(f"{name}:type")
        return {}
    if set(value) != keys:
        errors.append(f"{name}:keys")
    return value


def static_urls(repo: Path, errors: list[str]) -> list[str]:
    try:
        root = ET.parse(repo / "v2/sitemap.xml").getroot()
        urls = [(node.text or "").strip() for node in root.iter("{http://www.sitemaps.org/schemas/sitemap/0.9}loc")]
    except (OSError, ET.ParseError) as exc:
        errors.append(f"sitemap:unreadable:{exc.__class__.__name__}")
        return []
    if not urls or len(urls) != len(set(urls)):
        errors.append("sitemap:empty_or_duplicates")
    return sorted(set(urls))


def classify(url: str) -> str | None:
    prefix = "https://anytoour.ru"
    if not isinstance(url, str) or not url.startswith(prefix):
        return None
    path = url[len(prefix):]
    if any(ch in path for ch in ("?", "#")):
        return None
    parts = [part for part in path.split("/") if part]
    if len(parts) == 2 and parts[0] == "country":
        return "country"
    if len(parts) == 3 and parts[0] == "country":
        return "seasonal" if parts[2] in MONTHS else "resort"
    if len(parts) == 4 and parts[0] == "country" and parts[3] in MONTHS:
        return "seasonal"
    return None


def entrypoint_digest(repo: Path, urls: list[str], errors: list[str]) -> str:
    rows: list[str] = []
    for url in urls:
        path = url.removeprefix("https://anytoour.ru")
        entrypoint = repo / "v2" / path.strip("/") / "index.php"
        if not entrypoint.is_file():
            errors.append(f"entrypoint:missing:{path}")
            continue
        rows.append(f"{path}\t{sha256(entrypoint)}")
    return hashlib.sha256(("\n".join(sorted(rows)) + "\n").encode("utf-8")).hexdigest()


def static_entrypoints(repo: Path, urls: list[str], errors: list[str]) -> dict[str, Path]:
    entrypoints: dict[str, Path] = {}
    for url in urls:
        path = url.removeprefix("https://anytoour.ru")
        entrypoint = repo / "v2" / path.strip("/") / "index.php"
        if not entrypoint.is_file():
            errors.append(f"entrypoint:missing:{path}")
            continue
        entrypoints[path] = entrypoint
    return entrypoints


def literal_php_dependencies(repo: Path, source: Path, errors: list[str]) -> set[Path]:
    """Resolve only literal __DIR__-based PHP requires; dynamic inputs stay unclaimed."""
    try:
        raw = source.read_text(encoding="utf-8")
    except OSError as exc:
        errors.append(f"literal_dependency:unreadable:{source.name}:{exc.__class__.__name__}")
        return set()
    dependencies: set[Path] = set()
    for statement in REQUIRE_STATEMENT.findall(raw):
        if "__DIR__" not in statement:
            continue
        matches = LITERAL_PHP_PATH.findall(statement)
        if not matches:
            continue
        base = source.parent
        for _ in range(statement.split("__DIR__", 1)[0].count("dirname(")):
            base = base.parent
        target = (base / matches[-1].lstrip("/")).resolve()
        try:
            target.relative_to(repo)
        except ValueError:
            errors.append(f"literal_dependency:outside_repo:{source.relative_to(repo)}")
            continue
        if not target.is_file():
            errors.append(f"literal_dependency:missing:{target.relative_to(repo)}")
            continue
        dependencies.add(target)
    return dependencies


def literal_dependency_owners(repo: Path, entrypoints: dict[str, Path], errors: list[str]) -> set[str]:
    """Compute the exact checked-in literal dependency closure for this dossier."""
    roots = set(entrypoints.values()) | {repo / path for path in LITERAL_DEPENDENCY_ROOTS}
    pending = list(roots)
    closure: set[Path] = set()
    while pending:
        source = pending.pop()
        if source in closure:
            continue
        if not source.is_file():
            errors.append(f"literal_dependency:root_missing:{source.relative_to(repo)}")
            continue
        closure.add(source)
        pending.extend(literal_php_dependencies(repo, source, errors) - closure)
    entrypoint_set = set(entrypoints.values())
    return {str(path.relative_to(repo)) for path in closure - entrypoint_set}


def seasonal_parent(path: str) -> str | None:
    parts = [part for part in path.split("/") if part]
    if len(parts) == 3 and parts[0] == "country" and parts[2] in MONTHS:
        return f"/country/{parts[1]}/"
    if len(parts) == 4 and parts[0] == "country" and parts[3] in MONTHS:
        return f"/country/{parts[1]}/{parts[2]}/"
    return None


def php_function_body(raw: str, function_name: str) -> str | None:
    match = re.search(rf"function\s+{re.escape(function_name)}\s*\([^)]*\)\s*:\s*array\s*\{{", raw)
    if match is None:
        return None
    depth = 1
    cursor = match.end()
    while cursor < len(raw) and depth:
        if raw[cursor] == "{":
            depth += 1
        elif raw[cursor] == "}":
            depth -= 1
        cursor += 1
    return raw[match.end():cursor - 1] if depth == 0 else None


def rendered_sibling_month_urls(repo: Path, seasonal_paths: set[str], path: str, entrypoint: Path, errors: list[str]) -> tuple[str, ...]:
    """Extract the month URLs that the checked-in record passes to the renderer."""
    raw = entrypoint.read_text(encoding="utf-8")
    parent = seasonal_parent(path)
    if parent is None:
        errors.append(f"sibling:invalid_seasonal_path:{path}")
        return ()
    family_months = {candidate for candidate in seasonal_paths if seasonal_parent(candidate) == parent}
    if path in PILOT_SEASONAL_PATHS:
        binding = re.search(r"v2_seo_render_seasonal\(\s*(v2_seo_content_pilot_[a-z_]+)\(\)\s*\)", raw)
        pilot_raw = (repo / "v2/seo-content-pilot-seasonal-september-v1.php").read_text(encoding="utf-8")
        body = php_function_body(pilot_raw, binding.group(1)) if binding else None
        if body is None:
            errors.append(f"pilot_route:invalid_render_binding:{path}")
            return ()
        links = tuple(sorted(set(re.findall(r"['\"]href['\"]\s*=>\s*['\"]([^'\"]+)['\"]", body)) & family_months))
        if links:
            errors.append(f"pilot_route:unexpected_sibling_month_links:{path}")
        return links

    binding = re.search(r"v2_seo_core_month_record_for_path\(\s*['\"]([^'\"]+)['\"]\s*\)", raw)
    core = (repo / "v2/seo-core-month-content-v1.php").read_text(encoding="utf-8")
    renderer = (repo / "v2/seo-seasonal-page-v1.php").read_text(encoding="utf-8")
    if binding is None or binding.group(1) != path:
        errors.append(f"seasonal_route:invalid_record_binding:{path}")
        return ()
    if "foreach($matrix['rows'] as $peer)" not in core or "'related'=>array_slice($related,0,11)" not in core or "foreach($page['related'] as $link)" not in renderer:
        errors.append(f"seasonal_record:generic_sibling_rendering_changed:{path}")
        return ()
    return tuple(sorted(family_months - {path}))


def expected_workflow() -> str:
    paths = [
        ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml",
        "docs/seo-public-cohort-editorial-link-readiness.json",
        "docs/seo-public-cohort-editorial-link-readiness.md",
        "scripts/ci/validate_seo_public_cohort_editorial_link_readiness.py",
        "tests/seo-public-cohort-editorial-link-readiness-test.py",
        "v2/sitemap.xml",
        "v2/country/**/index.php",
        "v2/**/*.php",
    ]
    rendered = "\n".join(f"      - '{path}'" for path in paths)
    return f"""name: Validate SEO public cohort editorial link readiness

on:
  pull_request:
    branches: [main]
    paths:
{rendered}
  push:
    branches: [main]
    paths:
{rendered}

permissions:
  contents: read

concurrency:
  group: seo-public-cohort-editorial-link-readiness-${{{{ github.ref }}}}
  cancel-in-progress: true

jobs:
  review-only-editorial-link-readiness:
    runs-on: ubuntu-latest
    timeout-minutes: 5
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - name: Validate review-only dossier
        shell: bash
        run: |
          set -euo pipefail
          if [ "${{{{ github.event_name }}}}" = "pull_request" ]; then
            python3 -S scripts/ci/validate_seo_public_cohort_editorial_link_readiness.py --enforce-diff
          else
            python3 -S scripts/ci/validate_seo_public_cohort_editorial_link_readiness.py
          fi
          python3 -S tests/seo-public-cohort-editorial-link-readiness-test.py
          git diff --check
"""


def validate_workflow(repo: Path, errors: list[str]) -> None:
    workflow = repo / ".github/workflows/validate-seo-public-cohort-editorial-link-readiness.yml"
    try:
        raw = workflow.read_text(encoding="utf-8")
    except OSError as exc:
        errors.append(f"workflow:unreadable:{exc.__class__.__name__}")
        return
    if raw != expected_workflow():
        errors.append("workflow:exact_contract")
    forbidden = ("pull_request_target", "contents: write", "id-token", "secrets.", "github.token", "git push", "curl ", "wget ", "deploy", "http://", "https://")
    if any(token in raw.lower() for token in forbidden):
        errors.append("workflow:side_effect_or_bypass")


def validate_scope(repo: Path) -> list[str]:
    checks = (
        ["git", "-C", str(repo), "cat-file", "-e", f"{BASE_COMMIT}^{{commit}}"],
        ["git", "-C", str(repo), "merge-base", "--is-ancestor", BASE_COMMIT, "HEAD"],
    )
    if any(subprocess.run(cmd, capture_output=True, check=False).returncode != 0 for cmd in checks):
        return ["scope:base_unavailable_or_not_ancestor"]
    commands = (
        ["git", "-C", str(repo), "diff", "--name-status", f"{BASE_COMMIT}..HEAD"],
        ["git", "-C", str(repo), "diff", "--name-status"],
        ["git", "-C", str(repo), "diff", "--cached", "--name-status"],
        ["git", "-C", str(repo), "ls-files", "--others", "--exclude-standard"],
    )
    results = [subprocess.run(command, capture_output=True, text=True, check=False) for command in commands]
    if any(result.returncode for result in results):
        return ["scope:diff_unavailable"]
    committed_rows: list[tuple[str, str]] = []
    for result in results[:1]:
        for line in result.stdout.splitlines():
            if not line:
                continue
            status, _, path = line.partition("\t")
            committed_rows.append((status, path))
    working_rows: list[tuple[str, str]] = []
    for result in results[1:3]:
        for line in result.stdout.splitlines():
            if not line:
                continue
            status, _, path = line.partition("\t")
            working_rows.append((status, path))
    working_rows.extend(("A", path) for path in results[3].stdout.splitlines() if path)
    committed_paths = {path for _, path in committed_rows}
    working_paths = {path for _, path in working_rows}
    if (committed_paths and (committed_paths != ALLOWED_FILES or any(status != "A" for status, _ in committed_rows))) or (not committed_paths and working_paths != ALLOWED_FILES) or not working_paths.issubset(ALLOWED_FILES):
        return ["scope:not_exactly_five_new_files"]
    return []


def validate(repo: Path, artifact: Path) -> list[str]:
    errors: list[str] = []
    try:
        payload = json.loads(artifact.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        return [f"artifact:unreadable:{exc.__class__.__name__}"]
    root = exact_object(payload, {"schema_version", "artifact", "status", "scope", "source_evidence", "cohort", "structural_graph_contract", "editorial_readiness", "production_identity_readiness", "measurement", "guardrails"}, "root", errors)
    if root.get("schema_version") != 1 or root.get("artifact") != "anytour_public_seo_cohort_editorial_link_readiness" or root.get("status") != "review_only_blocked_on_fresh_production_identity":
        errors.append("root:identity")

    scope = exact_object(root.get("scope"), {"base_commit", "base_tree", "publication_side_effects", "runtime_changes", "indexation_changes", "sitemap_changes", "robots_changes", "canonical_changes", "route_changes", "metrika_changes", "tourvisor_changes", "lead_changes", "deploy_changes"}, "scope", errors)
    if scope.get("base_commit") != BASE_COMMIT or scope.get("base_tree") != BASE_TREE or any(scope.get(k) is not False for k in set(scope) - {"base_commit", "base_tree"}):
        errors.append("scope:boundary")

    evidence = exact_object(root.get("source_evidence"), {"static_sitemap_sha256", "canonical_url_list_sha256", "route_entrypoint_manifest_sha256", "owner_sha256"}, "source_evidence", errors)
    owners = exact_object(evidence.get("owner_sha256"), set(OWNER_HASHES), "owner_sha256", errors)
    if owners != OWNER_HASHES:
        errors.append("owner_sha256:registry")
    for relative, digest in OWNER_HASHES.items():
        source = repo / relative
        if not source.is_file() or sha256(source) != digest:
            errors.append(f"owner_sha256:drift:{relative}")
    urls = static_urls(repo, errors)
    entrypoints = static_entrypoints(repo, urls, errors)
    if literal_dependency_owners(repo, entrypoints, errors) != set(OWNER_HASHES):
        errors.append("owner_sha256:literal_dependency_closure")
    if (repo / "v2/sitemap.xml").is_file() and sha256(repo / "v2/sitemap.xml") != SITEMAP_SHA:
        errors.append("sitemap:source_hash")
    list_digest = hashlib.sha256(("\n".join(urls) + "\n").encode("utf-8")).hexdigest()
    route_digest = entrypoint_digest(repo, urls, errors)
    if evidence.get("static_sitemap_sha256") != SITEMAP_SHA or evidence.get("canonical_url_list_sha256") != URL_LIST_SHA or evidence.get("route_entrypoint_manifest_sha256") != ENTRYPOINT_SHA:
        errors.append("source_evidence:declared_hashes")
    if list_digest != URL_LIST_SHA or route_digest != ENTRYPOINT_SHA:
        errors.append("source_evidence:computed_hashes")

    counts = {"country": 0, "resort": 0, "seasonal": 0}
    seasonal_paths: set[str] = set()
    observed_pilot_paths: set[str] = set()
    for url in urls:
        kind = classify(url)
        if kind is None:
            errors.append(f"cohort:unclassified:{url}")
        else:
            counts[kind] += 1
            if kind == "seasonal":
                path = url.removeprefix("https://anytoour.ru")
                seasonal_paths.add(path)
                entrypoint = entrypoints.get(path)
                raw = entrypoint.read_text(encoding="utf-8") if entrypoint is not None else ""
                is_pilot = "seo-content-pilot-seasonal-september-v1.php" in raw
                if is_pilot:
                    observed_pilot_paths.add(path)
                    if "v2_seo_render_seasonal(v2_seo_content_pilot_" not in raw:
                        errors.append(f"pilot_route:invalid_render_binding:{path}")
                elif "seo-core-month-route-resolver-v1.php" not in raw:
                    errors.append(f"seasonal_route:missing_exact_resolver:{path}")
    if observed_pilot_paths != PILOT_SEASONAL_PATHS:
        errors.append("pilot_route:exact_set")
    cohort = exact_object(root.get("cohort"), {"node_count", "type_counts", "excluded_nodes"}, "cohort", errors)
    type_counts = exact_object(cohort.get("type_counts"), {"country", "resort", "seasonal"}, "cohort.type_counts", errors)
    if cohort.get("node_count") != 104 or type_counts != counts or counts != {"country": 3, "resort": 5, "seasonal": 96} or cohort.get("excluded_nodes") != ["/poisk-turov/", "hotel_tours", "dynamic_egypt_maldives_resorts", "query_parameters", "fragments", "external_urls"]:
        errors.append("cohort:boundary")

    graph = exact_object(root.get("structural_graph_contract"), {"parent_relations", "parent_to_month_relations", "within_family_month_sibling_relations", "special_pilot_month_pages_without_sibling_set", "search_handoff_is_not_a_graph_node", "turkey_country_to_static_resorts"}, "graph", errors)
    gap = exact_object(graph.get("turkey_country_to_static_resorts"), {"source_path", "target_count", "rendered_anchor_count", "status", "remediation"}, "graph.turkey_gap", errors)
    rendered_sibling_edges = 0
    for path in sorted(seasonal_paths):
        entrypoint = entrypoints.get(path)
        if entrypoint is None:
            continue
        links = rendered_sibling_month_urls(repo, seasonal_paths, path, entrypoint, errors)
        expected = set() if path in PILOT_SEASONAL_PATHS else {candidate for candidate in seasonal_paths if seasonal_parent(candidate) == seasonal_parent(path) and candidate != path}
        if set(links) != expected or len(links) != (0 if path in PILOT_SEASONAL_PATHS else 11):
            errors.append(f"sibling:exact_rendered_set:{path}")
        rendered_sibling_edges += len(links)
    computed_parent_relations = len(seasonal_paths) + counts["resort"]
    if graph.get("parent_relations") != computed_parent_relations or graph.get("parent_to_month_relations") != len(seasonal_paths) or graph.get("within_family_month_sibling_relations") != rendered_sibling_edges or graph.get("special_pilot_month_pages_without_sibling_set") != len(PILOT_SEASONAL_PATHS) or graph.get("search_handoff_is_not_a_graph_node") is not True or gap != {"source_path": "/country/turkey/", "target_count": 5, "rendered_anchor_count": 0, "status": "detected_unresolved", "remediation": "separate_runtime_slice_required"}:
        errors.append("graph:boundary")

    editorial = exact_object(root.get("editorial_readiness"), {"structural_identity_status", "semantic_uniqueness_status", "copy_claims_are_not_revalidated_by_this_dossier", "automatic_copy_or_link_change_allowed"}, "editorial", errors)
    if editorial != {"structural_identity_status": "source_anchored", "semantic_uniqueness_status": "pending_render_evidence", "copy_claims_are_not_revalidated_by_this_dossier": True, "automatic_copy_or_link_change_allowed": False}:
        errors.append("editorial:boundary")
    live = exact_object(root.get("production_identity_readiness"), {"status", "required_workflow", "required_artifact", "required_fields", "maximum_age_seconds", "automatic_reconciliation_allowed"}, "production_identity", errors)
    wanted_fields = ["successful_run_url", "run_id", "head_sha", "observed_at_utc", "artifact_sha256", "identity_registry_sha256"]
    if live.get("status") != "not_available_in_checkout" or live.get("required_workflow") != ".github/workflows/collect-seo-production-evidence.yml" or live.get("required_artifact") != "seo-production-identity.json" or live.get("required_fields") != wanted_fields or live.get("maximum_age_seconds") != 86400 or live.get("automatic_reconciliation_allowed") is not False:
        errors.append("production_identity:boundary")
    measurement = exact_object(root.get("measurement"), {"status", "demand_metrics_present", "conversion_metrics_present", "interpretation"}, "measurement", errors)
    if measurement != {"status": "not_collected", "demand_metrics_present": False, "conversion_metrics_present": False, "interpretation": "measurement_not_collected_is_unknown_not_zero"}:
        errors.append("measurement:boundary")
    guardrails = exact_object(root.get("guardrails"), {"publication_allowed", "indexation_allowed", "sitemap_allowed", "robots_allowed", "canonical_allowed", "route_allowed", "metrika_allowed", "tourvisor_allowed", "lead_allowed", "deploy_allowed", "hotel_tours_eligible", "search_route_eligible"}, "guardrails", errors)
    if any(guardrails.get(key) is not False for key in guardrails):
        errors.append("guardrails:boundary")
    validate_workflow(repo, errors)
    return errors


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--repo", type=Path, default=Path(__file__).resolve().parents[2])
    parser.add_argument("--artifact", type=Path)
    parser.add_argument("--enforce-diff", action="store_true")
    args = parser.parse_args()
    repo = args.repo.resolve()
    artifact = args.artifact or repo / "docs/seo-public-cohort-editorial-link-readiness.json"
    errors = validate(repo, artifact)
    if args.enforce_diff:
        errors.extend(validate_scope(repo))
    if errors:
        print("SEO_PUBLIC_COHORT_EDITORIAL_LINK_READINESS_FAIL " + " | ".join(sorted(set(errors))), file=sys.stderr)
        return 1
    print("SEO_PUBLIC_COHORT_EDITORIAL_LINK_READINESS_OK nodes=104 country=3 resort=5 seasonal=96 parent=101 month_nav=96 sibling=1034 pilot_without_siblings=2 turkey_gap=detected_unresolved")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
