#!/usr/bin/env python3
"""Inventory two immutable Git trees for a local Search3 migration review.

No checkout, compilation, network, publication or server operation is performed.
The client graph mirrors the strict declarations in visual-entry.cjs. Direct PHP
dependencies are inventoried; this is deliberately not a complete server graph.
"""
from __future__ import annotations

import argparse
import hashlib
from html.parser import HTMLParser
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys


ENTRY = "v2/visual-search/index.php"
RENDERER = "v2/visual-search/entry-v1.php"
HTML = "v2/visual-search/index.html"
COMPILER = "scripts/build/search3-js/visual-entry.cjs"
STYLE_REFS = ["./styles.css", "./mobile-controls-v1.css"]
ON_DEMAND = ("data-offer-list-src", "data-hotel-details-src", "data-flight-picker-src")
PHP_DIRECT = "v2/data/hotel-popularity-v1.php"
FONT_LICENSE = "v2/visual-search/assets/inter-LICENSE.txt"
SHA = re.compile(r"[0-9a-f]{40}\Z")
ASSET_REF = re.compile(r"\.\.?/[\w./-]+\.(?:js|css)\Z", re.ASCII)
STATIC_REF = re.compile(r"\./assets/[A-Za-z0-9_./-]+\.(?:svg|png|jpe?g|webp|gif|ico|woff2?|ttf)\Z")


class InventoryError(RuntimeError):
    pass


def need(condition: bool, message: str) -> None:
    if not condition:
        raise InventoryError(message)


def safe_path(value: str) -> str:
    need(bool(value) and not value.startswith("/") and "\\" not in value,
         "unsafe repository path")
    need(not any(c in value for c in "\r\n\t\0"), "unsafe repository path")
    need(all(p not in ("", ".", "..") and p == p.strip() for p in value.split("/")),
         "unsafe repository path")
    return value


def resolve_ref(base: str, reference: str) -> str:
    need(bool(ASSET_REF.fullmatch(reference) or STATIC_REF.fullmatch(reference)),
         f"unsupported local reference: {reference}")
    need("/../" not in reference, f"nested traversal in reference: {reference}")
    parts = list(PurePosixPath(base).parent.parts)
    for part in reference.split("/"):
        if part == ".":
            continue
        if part == "..":
            need(len(parts) > 1, "reference escapes v2")
            parts.pop()
        else:
            parts.append(part)
    result = safe_path("/".join(parts))
    need(result.startswith("v2/"), "reference escapes v2")
    return result


class Git:
    def __init__(self, root: Path):
        self.root = root.resolve()
        self.env = dict(os.environ, GIT_NO_LAZY_FETCH="1", GIT_TERMINAL_PROMPT="0",
                        GIT_NO_REPLACE_OBJECTS="1", GIT_CONFIG_NOSYSTEM="1")
        self.command = ["git", "--no-optional-locks", "--no-replace-objects", "-C", str(self.root),
                        "-c", "protocol.allow=never"]
        self.run("rev-parse", "--git-dir")
        self.metadata_dirs = tuple(Path(self.run("rev-parse", "--path-format=absolute", flag)
                                        .decode().strip()).resolve()
                                   for flag in ("--git-dir", "--git-common-dir"))
        self.batch = subprocess.Popen(self.command + ["cat-file", "--batch"], env=self.env,
                                      stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                      stderr=subprocess.DEVNULL)
        self.cache: dict[str, bytes] = {}

    def close(self) -> None:
        if self.batch.stdin:
            self.batch.stdin.close()
        self.batch.wait(timeout=10)

    def run(self, *args: str) -> bytes:
        result = subprocess.run(self.command + list(args), env=self.env, capture_output=True)
        need(result.returncode == 0, "Git read failed: " + " ".join(args[:2]))
        return result.stdout

    def commit(self, value: str) -> dict[str, str]:
        need(bool(SHA.fullmatch(value)), "use an immutable full lowercase commit SHA, not a ref")
        need(self.run("cat-file", "-t", value).strip() == b"commit", "input is not a commit")
        tree = self.run("rev-parse", value + "^{tree}").decode().strip()
        return {"sha": value, "tree_sha": tree}

    def tree(self, commit: str, prefix: str = "v2/") -> dict[str, dict]:
        records = self.run("ls-tree", "-r", "-l", "-z", commit, "--", prefix)
        result = {}
        for record in records.split(b"\0"):
            if not record:
                continue
            header, raw_path = record.split(b"\t", 1)
            mode, kind, oid, size = header.decode("ascii").split()
            path = safe_path(raw_path.decode("utf-8"))
            need(kind == "blob" and mode in ("100644", "100755"),
                 f"symlink or non-regular Git entry rejected: {path}")
            result[path] = {"mode": mode, "blob_sha": oid, "size": int(size)}
        return result

    def blob(self, oid: str) -> bytes:
        if oid not in self.cache:
            need(bool(SHA.fullmatch(oid)), "invalid blob identity")
            self.batch.stdin.write((oid + "\n").encode())
            self.batch.stdin.flush()
            header = self.batch.stdout.readline().decode().strip().split()
            need(len(header) == 3 and header[0] == oid and header[1] == "blob",
                 "Git blob unavailable; no lazy network fetch is allowed")
            data = self.batch.stdout.read(int(header[2]))
            need(len(data) == int(header[2]) and self.batch.stdout.read(1) == b"\n",
                 "incomplete Git blob")
            self.cache[oid] = data
        return self.cache[oid]

    def content(self, tree: dict, path: str) -> str:
        need(path in tree, f"missing declared dependency: {path}")
        try:
            return self.blob(tree[path]["blob_sha"]).decode("utf-8")
        except UnicodeDecodeError as exc:
            raise InventoryError(f"text dependency is not UTF-8: {path}") from exc

    def metadata(self, entry: dict) -> dict:
        data = self.blob(entry["blob_sha"])
        need(len(data) == entry["size"], "Git size mismatch")
        return {**entry, "sha256": hashlib.sha256(data).hexdigest()}


class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.scripts, self.styles, self.static, self.lazy = [], [], [], {}

    def handle_starttag(self, tag: str, attrs: list) -> None:
        names = [name for name, _ in attrs]
        need(len(names) == len(set(names)), "duplicate HTML attribute")
        a = dict(attrs)
        if tag == "script":
            need(bool(a.get("src")), "inline/unknown script graph is not supported")
            self.scripts.append(a["src"])
        if tag == "link":
            if a.get("rel") == "stylesheet":
                self.styles.append(a.get("href", ""))
            elif a.get("rel") == "icon":
                self.static.append(a.get("href", ""))
            elif a.get("href"):
                raise InventoryError("unknown linked resource requires an explicit inventory contract")
        if tag in ("iframe", "object", "embed", "source", "audio", "video"):
            raise InventoryError("unknown embedded resource requires an explicit inventory contract")
        if tag == "img" and a.get("src"):
            self.static.append(a["src"])
        if "srcset" in a:
            raise InventoryError("srcset requires an explicit inventory contract")
        for key in ON_DEMAND:
            if key in a:
                need(key not in self.lazy and tag == "head", "duplicate/non-head on-demand declaration")
                self.lazy[key] = a[key]


def client_graph(git: Git, tree: dict) -> tuple[dict, dict[str, set[str]]]:
    wrapper, php, html = git.content(tree, ENTRY), git.content(tree, RENDERER), git.content(tree, HTML)
    wrapper_requires = re.findall(r"\b(?:require|include)(?:_once)?\s+([^;]+);", wrapper)
    need(wrapper_requires == ["__DIR__ . '/entry-v1.php'"], "unknown preview wrapper dependency")
    arrays = re.findall(r"\$scripts\s*=\s*\[([\s\S]*?)\];", php)
    need(len(arrays) == 1, "expected exactly one explicit LIVE script array")
    live = re.findall(r"'([^']+\.js)'", arrays[0])
    residue = re.sub(r"'[^']+\.js'", "", arrays[0])
    need(live and not re.sub(r"[\s,]", "", residue), "dynamic/incomplete LIVE script declaration")
    need(len(live) == len(set(live)), "duplicate LIVE script")
    page = Page()
    page.feed(html)
    page.close()
    need(page.styles == STYLE_REFS, "expected the two existing stylesheet owners in order")
    need(set(page.lazy) == set(ON_DEMAND), "incomplete on-demand graph")
    need(page.scripts and len(page.scripts) == len(set(page.scripts)), "incomplete/duplicate offline graph")
    for ref in live + page.scripts + list(page.lazy.values()) + page.styles:
        need(bool(ASSET_REF.fullmatch(ref)), f"unbounded script/style reference: {ref}")
    need(len(set(page.lazy.values())) == 3, "three distinct on-demand owners required")
    roles: dict[str, set[str]] = {ENTRY: {"entry"}, RENDERER: {"shared_entry_renderer"}, HTML: {"html_shell"}}

    def add(path: str, role: str) -> None:
        need(path in tree, f"missing declared dependency: {path}")
        roles.setdefault(path, set()).add(role)

    for ref in live:
        add(resolve_ref(HTML, ref), "live_script")
    for ref in page.lazy.values():
        add(resolve_ref(HTML, ref), "on_demand_script")
    for ref in page.styles:
        add(resolve_ref(HTML, ref), "stylesheet")
    for ref in page.static:
        need(bool(STATIC_REF.fullmatch(ref)), f"unsupported HTML static asset: {ref}")
        add(resolve_ref(HTML, ref), "static_asset")

    for ref in page.styles:
        path = resolve_ref(HTML, ref)
        css = re.sub(r"/\*[\s\S]*?\*/", "", git.content(tree, path))
        need(not re.search(r"@import\b", css), "CSS imports require an explicit inventory contract")
        for match in re.finditer(r"url\(\s*(?:\"([^\"]*)\"|'([^']*)'|([^)]*?))\s*\)", css):
            asset = next(x for x in match.groups() if x is not None).strip()
            if asset.startswith("data:"):
                continue
            need(bool(STATIC_REF.fullmatch(asset)), f"unsupported CSS asset: {asset}")
            add(resolve_ref(path, asset), "static_asset")

    for path in list(roles):
        if not path.endswith(".js"):
            continue
        code = git.content(tree, path)
        need(not re.search(r"\bimport\s*(?:\(|[\w{*])|\bimportScripts\s*\(|\bnew\s+(?:Shared)?Worker\s*\(", code),
             f"undeclared script loader: {path}")
        loaders = re.findall(r"\bcreateElement\s*\(\s*(['\"])script\1\s*\)", code)
        if loaders:
            need(path == "v2/visual-search/app.js" and len(loaders) == 3,
                 f"unknown dynamic script owner: {path}")
            expected_sources = ["hotelDetailsAsset", "document.head.dataset.flightPickerSrc||'./flight-picker-ui-v1.js'", "offerListAsset"]
            sources = re.findall(r"\bscript\.src\s*=\s*([^;]+);", code)
            need(sources == expected_sources, "on-demand script assignments changed; review inventory contract")
            for variable, key in (("hotelDetailsAsset", "hotelDetailsSrc"), ("offerListAsset", "offerListSrc")):
                need(f"const {variable}=document.head.dataset.{key};" in code,
                     "on-demand alias declaration changed")
            need(page.lazy["data-flight-picker-src"] == "./flight-picker-ui-v1.js",
                 "flight picker fallback differs from declared owner")
        table = re.search(r"\bconst operatorAssets=\{([^{}]*)\};", code)
        logo_refs = []
        if table:
            pairs = re.findall(r"'[^']+'\s*:\s*'([^']+)'", table[1])
            residue = re.sub(r"'[^']+'\s*:\s*'[^']+'", "", table[1])
            need(pairs and not re.sub(r"[\s,]", "", residue), "dynamic operator asset map")
            logo_refs = ["./assets/operators/" + filename for filename in pairs]
        # The public entry uses the same local assets under an absolute resource
        # base. Accept only literals and the existing finite operator table;
        # every other helper argument needs an explicit inventory review.
        for call in re.finditer(r"\bvisualResource\s*\(", code):
            tail = code[call.end():]
            literal = re.match(r"\s*(['\"])(assets/[A-Za-z0-9_./-]+)\1\s*\)", tail)
            if literal:
                asset = "./" + literal.group(2)
                need(bool(STATIC_REF.fullmatch(asset)), f"unsupported visualResource asset: {asset}")
                add(resolve_ref(path, asset), "static_asset")
                continue
            operator = re.match(r"\s*(['\"])assets/operators/\1\s*\+\s*file\s*\)", tail)
            need(bool(operator) and bool(logo_refs),
                 f"dynamic/unknown visualResource asset: {path}")
        for asset in re.findall(r"\./assets/[^\s\"'`<>]*", code):
            if asset == "./assets/operators/${file}":
                need(bool(logo_refs), "operator image template has no explicit file map")
                continue
            need(bool(STATIC_REF.fullmatch(asset)), f"dynamic/unknown static asset: {asset}")
            add(resolve_ref(path, asset), "static_asset")
        for asset in logo_refs:
            add(resolve_ref(path, asset), "static_asset")

    if any(path.startswith("v2/visual-search/assets/inter-") and path.endswith(".woff2") for path in roles):
        add(FONT_LICENSE, "font_license")

    # This direct PHP consumer is explicit in the accepted entry. Its DB/MATCH
    # transitive dependencies remain a declared server boundary, not a copied graph.
    expected = "$popularitySource = dirname(__DIR__) . '/data/hotel-popularity-v1.php';"
    need(expected in php, "direct PHP dependency declaration changed; review it explicitly")
    need("if (is_file($popularitySource))" in php, "optional PHP dependency guard changed")
    requires = re.findall(r"\b(?:require|include)(?:_once)?\s+([^;]+);", php)
    need(requires == ["$analytics", "$popularitySource", "dirname(__DIR__) . '/seo-config.php'"],
         "unknown PHP include dependency in entry")
    add(PHP_DIRECT, "optional_direct_php_dependency")
    public_arrays = re.findall(r"\$publicBeforeApp\s*=\s*\[([\s\S]*?)\];", php)
    need(len(public_arrays) == 1, "expected one public companion declaration")
    public_before_app = re.findall(r"'([^']+\.js)'", public_arrays[0])
    need(public_before_app == ['./public-url-v1.js', './public-bootstrap-v1.js', './public-analytics-v1.js']
         and not re.sub(r"[\s,]", "", re.sub(r"'[^']+\.js'", "", public_arrays[0])),
         "public companion declaration changed")
    need(php.count("$publicConfigScript = './public-config-v1.js';") == 1
         and php.count("$publicAnalyticsScript = '../analytics-v4.js';") == 1,
         "public config/analytics owner declaration changed")
    need(live[0:2] == ['../prototype-search/config.js', '../runtime-v3.js'] and live[-1] == './app.js',
         "public graph derivation requires existing config/runtime/app order")
    public_live = ['./public-config-v1.js', live[1], '../analytics-v4.js', *live[2:-1], *public_before_app, './app.js']
    public_review = ['./public-config-v1.js', *page.scripts[:-1], *public_before_app[:2], './app.js']
    need(page.scripts[0] == './fixture-data.js' and page.scripts[-1] == './app.js', "incomplete offline owner span")
    need(all(len(g) == len(set(g)) for g in [public_live, public_review]), "duplicate public graph owner")
    graph = {"live": live, "on_demand": page.lazy, "styles": page.styles,
             "public_live": public_live, "public_review": public_review,
             "public_graph_scope": "Derived declarations only; public entry/config/server dependencies are not admitted by this NEXT inventory.",
             "excluded_offline_scripts": [x for x in page.scripts if x not in live],
             "declared_direct_php_dependencies": [{"path": PHP_DIRECT, "optional_at_runtime": True,
                 "transitive_graph_complete": False}]}
    return graph, roles


def build_manifest(git: Git, candidate_sha: str, main_sha: str) -> tuple[dict, list[str]]:
    candidate, main = git.commit(candidate_sha), git.commit(main_sha)
    source_tree, main_tree = git.tree(candidate_sha), git.tree(main_sha)
    compiler_tree = git.tree(candidate_sha, COMPILER)
    need(COMPILER in compiler_tree, "missing existing visual graph compiler")
    graph, roles = client_graph(git, source_tree)

    def row(path: str, include_roles: bool = False) -> dict:
        result = {"path": path, "production_relative_path": path.removeprefix("v2/")}
        result["candidate"] = git.metadata(source_tree[path]) if path in source_tree else None
        result["main"] = git.metadata(main_tree[path]) if path in main_tree else None
        if include_roles:
            result["roles"] = sorted(roles[path])
        return result

    groups = {"added": [], "identical": [], "replace": []}
    for path in sorted(roles):
        action = ("added" if path not in main_tree else
                  "identical" if source_tree[path] == main_tree[path] else "replace")
        groups[action].append(row(path, True))
    shared = sorted(source_tree.keys() & main_tree.keys())
    divergent = [row(p) for p in shared if source_tree[p] != main_tree[p]]
    main_only = [row(p) for p in sorted(main_tree.keys() - source_tree.keys())]
    blockers = [
        {"boundary": "production_entry_and_config", "status": "unmapped",
         "reason": "Preview source guards/configuration are not production route approval."},
        {"boundary": "private_tourvisor_anex_messenger", "status": "unmapped",
         "reason": "Private supplier/session/signed-handoff endpoints need exact installed INT admission."},
        {"boundary": "local_catalog_and_calendar", "status": "unmapped",
         "reason": "Local data endpoints, database/bootstrap and MATCH dependencies are outside this client graph."},
        {"boundary": "popularity_php_transitive_dependencies", "status": "unmapped",
         "reason": "Only the direct entry consumer is declared; DB/MATCH/runtime configuration are not copied."},
        {"boundary": "lead_receiver_and_analytics", "status": "unmapped",
         "reason": "Preserve and verify current production contracts; preview receipts cannot admit delivery."},
        {"boundary": "live_predecessor_and_rollback", "status": "unmeasured",
         "reason": "Git main is not a live server inventory; snapshot paths require actual existence/hash readback."},
    ]
    manifest = {
        "schema_version": 1, "purpose": "local_source_inventory_for_review_only",
        "deployable": False, "network_used": False, "checkout_changed": False,
        "candidate": candidate, "main": main,
        "bytes_scope": "Git source blobs; not compiled or installed resource hashes",
        "graph_contract": {"method": "strict grammar mirror of inventory()/stylesheetInventory()",
                           "source_path": COMPILER, **git.metadata(compiler_tree[COMPILER])},
        "client_graph": graph, "client_graph_files": len(roles),
        "server_graph_complete": False, "runtime_comparison": groups,
        "all_divergent_shared_v2_paths": divergent, "all_main_only_v2_paths": main_only,
        "comparison_scope": "Pairwise Git tree differences, not merge conflicts. Main-only paths are absent from candidate; this is not the set of main-only changes since a common ancestor.",
        "all_v2_counts": {"candidate": len(source_tree), "main": len(main_tree),
                          "shared": len(shared), "divergent_shared": len(divergent),
                          "main_only": len(main_only)},
        "shared_runtime_choices_requiring_review": [r["path"] for r in groups["replace"]
                                                    if not r["path"].startswith("v2/visual-search/")],
        "unmapped_server_boundaries": blockers,
        "snapshot_list_scope": "source-level replace candidates only; neither a live inventory nor a deploy allowlist",
    }
    snapshot_paths = [r["production_relative_path"] for r in groups["replace"]]
    return manifest, snapshot_paths


def output_path(raw: Path, repo: Path, git_dirs: tuple[Path, ...] = ()) -> Path:
    absolute = raw.absolute()
    for part in [absolute, *absolute.parents]:
        need(not part.is_symlink(), "output path contains a symlink")
    destination = absolute.resolve()
    need(not destination.is_relative_to(repo.resolve()), "output must be outside the repository checkout")
    need(not any(destination.is_relative_to(path) for path in git_dirs),
         "output must be outside Git metadata directories")
    need(not any((part / ".git").exists() or (part / ".git").is_symlink()
                 for part in [destination, *destination.parents]),
         "output must be outside every Git checkout")
    need(not destination.exists() or destination.is_dir() and not any(destination.iterdir()),
         "output must be a new or empty directory")
    return destination


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo", type=Path, required=True)
    parser.add_argument("--candidate-sha", required=True)
    parser.add_argument("--main-sha", required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    git = None
    try:
        git = Git(args.repo)
        destination = output_path(args.output, args.repo, git.metadata_dirs)
        manifest, snapshot_paths = build_manifest(git, args.candidate_sha, args.main_sha)
        # Analysis/validation completes before either output is written.
        destination.mkdir(parents=True, exist_ok=True)
        (destination / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        (destination / "snapshot-candidate-paths.txt").write_text(
            "# Source-level replacement candidates; verify live existence/hashes before using the existing snapshot tool.\n"
            + "".join(path + "\n" for path in snapshot_paths), encoding="utf-8")
        print(json.dumps({"status": "source_inventory_written", "deployable": False,
                          "candidate_sha": args.candidate_sha, "main_sha": args.main_sha,
                          "runtime_files": manifest["client_graph_files"],
                          "runtime_counts": {key: len(value) for key, value in manifest["runtime_comparison"].items()},
                          "all_v2_counts": manifest["all_v2_counts"]}))
        return 0
    except (InventoryError, OSError, UnicodeError, subprocess.SubprocessError) as exc:
        print("SEARCH3_INVENTORY_ERROR: " + str(exc), file=sys.stderr)
        return 2
    finally:
        if git:
            git.close()


if __name__ == "__main__":
    raise SystemExit(main())
