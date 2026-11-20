#!/usr/bin/env python3
"""Build a local, non-deployable Search3 public candidate from Git blobs.

The existing visual compiler runs in a disposable full-source stage. Only its
selected REVIEW or explicit LIVE closure is added to a staged main site. The public route is
replaced; the known old-route predecessor may receive its page-alias correction.
This is not an installer, a server snapshot, or production admission. No network,
PHP server, supplier request, or lead request is started.
"""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess
import sys
import tempfile

# Reading a helper must not create bytecode in the user's checkout.
sys.dont_write_bytecode = True
from search3_production_inventory import Git, need, output_path, safe_path


COMPILER_DIR = "scripts/build/search3-js"
INVENTORY = "scripts/build/search3_production_inventory.py"
SNAPSHOT = "scripts/deploy/search3_production_snapshot.py"
PUBLIC_WRAPPER = "v2/visual-search/public-entry-v1.php"
PUBLIC_ROUTE = "poisk-turov/index.php"
BUILD_PROFILE = "visual-search/public-build.json"
SAVED_FIXTURE = "v2/visual-search/fixtures/live-search-2026-09-23.json"
FAMILY_PHOTOS = ("v2/visual-search/assets/photo-1.jpg", "v2/visual-search/assets/photo-2.jpg")
OLD_ROUTE = "v2/poisk-turov-old/index.php"
# Exact old-route bytes from accepted release 63844d4, before the standalone
# alias fix. Other existing old-route owners must be reviewed, never overwritten.
OLD_ROUTE_PREDECESSOR_BLOB = "f32aaf03833be6123da10d93b3136098709ce19f"
OLD_ROUTE_CORRECTION_BLOB = "e15a18cf3330ae0799c27c2a7c7c638163a62f6a"
# These shared runtime contracts are deliberately served from main, even when
# the separately compiled candidate has a shorter representation of the same JS.
RETAIN_MAIN = {"runtime-v3.js", "lead-search-context.js", "analytics-v4.js"}
LIVE_MAIN_DEPENDENCIES = (*sorted(RETAIN_MAIN), "analytics-config.php", "seo-config.php")
OPTIONAL_POPULARITY = "data/hotel-popularity-v1.php"
CANONICAL_INPUTS = ("v2/tour-controller-v4.js", "v2/lead-form-guard-v1.js")
ALIASES = (("search-page-v2.php", "index.php"), ("index.php", "home-entry-v1.php"))


def load_module(path: Path, name: str):
    spec = importlib.util.spec_from_file_location(name, path)
    need(spec is not None and spec.loader is not None, "cannot load pinned build helper")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def file_metadata(path: Path) -> dict:
    info = path.lstat()
    need(stat.S_ISREG(info.st_mode), "artifact contains a non-regular file")
    data = path.read_bytes()
    return {"size": len(data), "mode": f"{stat.S_IMODE(info.st_mode):04o}",
            "sha256": hashlib.sha256(data).hexdigest()}


def files_metadata(root: Path) -> dict[str, dict]:
    result = {}
    for path in sorted(root.rglob("*")):
        need(not path.is_symlink(), "artifact contains a symlink")
        if not path.is_dir():
            relative = safe_path(path.relative_to(root).as_posix())
            result[relative] = file_metadata(path)
    return result


def write_blob(git: Git, tree: dict, source: str, target: Path) -> None:
    need(source in tree, f"missing pinned source: {source}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(git.blob(tree[source]["blob_sha"]))
    os.chmod(target, int(tree[source]["mode"][-3:], 8))


def materialize(git: Git, tree: dict, destination: Path, prefix: str = "") -> None:
    for source in sorted(tree):
        need(source.startswith(prefix), "source escaped materialization prefix")
        write_blob(git, tree, source, destination / source.removeprefix(prefix))


def stage_main(git: Git, tree: dict, site: Path) -> dict[str, str]:
    materialize(git, tree, site, "v2/")
    owners = {path.removeprefix("v2/"): path for path in tree}
    # The established page alias order matters: preserve search before replacing
    # the source index with the main homepage. Lead aliases are not inferred.
    for target, source in ALIASES:
        need(source in owners, f"main standalone alias source is missing: {source}")
        shutil.copy2(site / source, site / target)
        owners[target] = owners[source]
    need(PUBLIC_ROUTE in owners, "main public search route is missing")
    return owners


def candidate_paths(graph: dict, roles: dict, source_tree: dict, resolver,
                    profile: str, scenario: str) -> list[str]:
    initial = graph.get("public_" + profile)
    need(isinstance(initial, list) and initial, "pinned source has no selected public graph")
    need(initial[0] == "./public-config-v1.js" and initial[-1] == "./app.js",
         "public configuration must precede the selected graph and app")
    need("./public-url-v1.js" in initial and "./public-bootstrap-v1.js" in initial,
         "public URL/bootstrap owners are missing")
    if profile == "review":
        need("./public-analytics-v1.js" not in initial, "review must not load public analytics")
    else:
        need(initial[1:3] == ["../runtime-v3.js", "../analytics-v4.js"]
             and "./public-analytics-v1.js" in initial
             and "./live-bridge.js" in initial
             and "../prototype-search/messenger-flow-v1.js" in initial,
             "public LIVE runtime/analytics/messenger graph is incomplete")
        need(not set(initial) & set(graph["excluded_offline_scripts"]),
             "public LIVE graph includes an offline owner")
    need(len(initial) == len(set(initial)), "duplicate public script")
    paths = {resolver("v2/visual-search/index.html", src)
             for src in initial + list(graph["on_demand"].values()) + graph["styles"]}
    paths.update(path for path, labels in roles.items()
                 if set(labels) & {"static_asset", "font_license"})
    paths.update({"v2/visual-search/index.php", "v2/visual-search/entry-v1.php",
                  "v2/visual-search/index.html", OLD_ROUTE})
    # These are the local demo photos returned by the fixed family adapter.
    # Snapshot hotels use the captured HTTPS URLs; journey-only photos are not
    # part of either allowed build profile. The test executes the served adapter.
    if profile == "review":
        paths.add(SAVED_FIXTURE)
        if scenario == "family":
            paths.update(FAMILY_PHOTOS)
    need(PUBLIC_WRAPPER in source_tree, "public wrapper is missing")
    for path in paths:
        need(path in source_tree, f"missing public dependency: {path}")
    need(PUBLIC_WRAPPER not in paths, "public wrapper must only replace the public route")
    return sorted(paths)


def check_collisions(git: Git, source_tree: dict, main_tree: dict,
                     owners: dict, paths: list[str]) -> set[str]:
    targets = {path.removeprefix("v2/"): path for path in paths}
    targets[BUILD_PROFILE] = None
    replacements = {PUBLIC_ROUTE}
    for target, source in targets.items():
        for parent in Path(target).parents:
            need(parent.as_posix() not in owners, f"main file blocks review directory: {target}")
        if target not in owners:
            need(not any(path.startswith(target + "/") for path in owners),
                 f"review file would replace a main directory: {target}")
            continue
        need(source is not None, "main already owns the review build profile")
        same = git.metadata(source_tree[source]) == git.metadata(main_tree[owners[target]])
        if target == OLD_ROUTE.removeprefix("v2/") and not same:
            if main_tree[owners[target]]["blob_sha"] == OLD_ROUTE_CORRECTION_BLOB:
                continue  # Already corrected: retain its main mode as well.
            need(main_tree[owners[target]]["blob_sha"] == OLD_ROUTE_PREDECESSOR_BLOB,
                 f"review would overwrite an unknown old-route owner: {target}")
            replacements.add(target)
            continue
        need(same or target in RETAIN_MAIN,
             f"review would overwrite a protected main file: {target}")
    return replacements


NODE_BUILD = r"""
const fs=require('node:fs'),path=require('node:path'),{createRequire}=require('node:module');
const compiler=path.resolve(process.argv[1]),root=path.resolve(process.argv[2]);
const modules=fs.realpathSync(process.argv[3]),owned=createRequire(compiler);
const pkg=JSON.parse(fs.readFileSync(path.join(path.dirname(compiler),'package.json'),'utf8'));
const versions={};
if(Number(process.versions.node.split('.')[0])<18)throw Error('Node 18 or newer is required');
for(const [name,version] of Object.entries(pkg.dependencies)){
 const resolved=fs.realpathSync(owned.resolve(name));
 if(!resolved.startsWith(modules+path.sep))throw Error('compiler dependency outside selected node_modules: '+name);
 let dir=path.dirname(resolved),found;
 while(dir!==path.dirname(dir)){
  const p=path.join(dir,'package.json');
  if(fs.existsSync(p)){const candidate=JSON.parse(fs.readFileSync(p,'utf8'));if(candidate.name===name){found=candidate;break;}}
  dir=path.dirname(dir);
 }
 if(!found||found.version!==version)throw Error('compiler dependency version mismatch: '+name);
 versions[name]=version;
}
owned(compiler).build(root).then(()=>process.stdout.write(JSON.stringify({node:process.versions.node,dependencies:versions})))
 .catch(e=>{console.error(e);process.exitCode=1;});
"""


def compile_source(stage: Path, node: str, node_modules: Path) -> tuple[dict, dict]:
    need(node_modules.is_dir(), "existing local compiler node_modules is required; no install is performed")
    env = dict(os.environ, NODE_PATH=str(node_modules.resolve()))
    env.pop("NODE_OPTIONS", None)
    result = subprocess.run([node, "-e", NODE_BUILD,
                             str(stage / COMPILER_DIR / "visual-entry.cjs"),
                             str(stage / "v2"), str(node_modules.resolve())],
                            env=env, capture_output=True, text=True)
    need(result.returncode == 0, "pinned visual compiler failed: " + result.stderr[-2500:])
    manifest = json.loads((stage / "v2/visual-search/asset-size.json").read_text())
    return manifest, json.loads(result.stdout)


def remove_listed_additions(root: Path, added: list[dict]) -> None:
    """Only used on our disposable rehearsal copy; preflight every file first."""
    seen = set()
    for row in added:
        relative = safe_path(row["path"])
        need(relative not in seen, "duplicate rehearsal addition")
        seen.add(relative)
        target = root / relative
        need(not any(parent.is_symlink() for parent in [target, *target.parents]),
             "rehearsal addition contains a symlink")
        need(target.is_file() and file_metadata(target) == row["served"],
             f"rehearsal addition changed; refusing cleanup: {relative}")
    # A changed/missing later addition never causes an earlier one to be deleted.
    for relative in sorted(seen, reverse=True):
        (root / relative).unlink()
    # Empty directories are harmless; no unlisted directory/file is removed.


def rehearse(snapshot_helper, snapshot_dir: Path, site: Path, baseline: dict,
             groups: dict, work: Path) -> dict:
    trial, restored = work / "rehearsal-site", work / "restored-route"
    shutil.copytree(site, trial)
    expected = {row["path"]: row["served"] for rows in groups.values() for row in rows}
    need(files_metadata(trial) == expected, "candidate changed before rollback rehearsal")
    snapshot_helper.restore(snapshot_dir, restored)
    replaced = {row["path"] for row in groups["replaced"]}
    need(set(files_metadata(restored)) == replaced, "rollback snapshot exceeded the replaced route boundary")
    remove_listed_additions(trial, groups["added"])
    for relative in sorted(replaced):
        shutil.copy2(restored / relative, trial / relative)
    need(files_metadata(trial) == baseline, "local rollback did not restore exact main bytes and modes")
    return {"status": "local_main_routes_restored", "scope": "disposable local artifact, not production",
            "routes": sorted(replaced), "restored": {path: baseline[path] for path in sorted(replaced)},
            "main_file_count": len(baseline), "removed_additions": len(groups["added"]),
            "all_main_bytes_and_modes_restored": True}


def build(args) -> dict:
    git = Git(args.repo)
    try:
        destination = output_path(args.output, args.repo, git.metadata_dirs)
        candidate, main = git.commit(args.candidate_sha), git.commit(args.main_sha)
        need(args.profile in ("review", "live"), "only explicit review/live profiles are supported")
        need(args.scenario in ("family", "snapshot"), "only fixed family/snapshot review scenarios are supported")
        need(args.profile != "live" or args.scenario == "family", "scenario selection applies only to review")
        source_tree, main_tree = git.tree(candidate["sha"]), git.tree(main["sha"])
        helpers = {}
        for prefix in (COMPILER_DIR + "/", INVENTORY, SNAPSHOT):
            helpers.update(git.tree(candidate["sha"], prefix))
        for path in (INVENTORY, SNAPSHOT, COMPILER_DIR + "/visual-entry.cjs",
                     COMPILER_DIR + "/package.json", COMPILER_DIR + "/package-lock.json"):
            need(path in helpers, f"missing pinned build helper: {path}")
        for path in CANONICAL_INPUTS:
            need(path in source_tree, f"missing canonical compiler input: {path}")
        need(source_tree.get(OLD_ROUTE, {}).get("blob_sha") == OLD_ROUTE_CORRECTION_BLOB,
             "retained old-route correction source changed; explicit review is required")
        destination.parent.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(prefix="search3-public-build-", dir=destination.parent) as raw:
            work = Path(raw)
            source, package, site = work / "source", work / "artifact", work / "artifact/site"
            materialize(git, source_tree, source)
            materialize(git, helpers, source)
            inventory = load_module(source / INVENTORY, "search3_pinned_inventory")
            snapshot_helper = load_module(source / SNAPSHOT, "search3_pinned_snapshot")
            graph, roles = inventory.client_graph(git, source_tree)
            graph_key = "public_" + args.profile
            paths = candidate_paths(graph, roles, source_tree, inventory.resolve_ref,
                                    args.profile, args.scenario)
            owners = stage_main(git, main_tree, site)
            baseline = files_metadata(site)
            if args.profile == "live":
                for path in LIVE_MAIN_DEPENDENCIES:
                    need(path in baseline, f"public LIVE requires an existing main dependency: {path}")
            replacements = check_collisions(git, source_tree, main_tree, owners, paths)

            # Keep rollback material outside the served root. It contains only
            # replaced versioned routes, never installed configuration/credentials.
            inventory_file = package / "rollback-paths.txt"
            inventory_file.write_text("".join(path + "\n" for path in sorted(replacements)))
            snapshot_dir = snapshot_helper.snapshot(site, package / "rollback", inventory_file,
                                                     "main-routes", main["sha"])
            compiled, versions = compile_source(source, args.node, args.node_modules)
            need(compiled["graphs"].get(graph_key) == graph[graph_key],
                 "compiler and strict inventory disagree about the selected public graph")
            groups = {"added": [], "replaced": [], "protected": []}
            retained_compiled = [{"path": row["target"],
                                  "compiled_candidate": file_metadata(source / "v2" / row["target"]),
                                  "retained_main": baseline[row["target"]]}
                                 for row in compiled["files"] if row["target"] in baseline]
            for path in paths:
                relative = path.removeprefix("v2/")
                compiled_path = source / path
                need(compiled_path.is_file(), f"compiled public dependency missing: {path}")
                if relative in baseline and relative not in replacements:
                    continue
                target = site / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(compiled_path, target)
                row = {"path": relative, "source_path": path,
                       "source": git.metadata(source_tree[path]), "served": file_metadata(target)}
                if relative in replacements:
                    row["before"] = baseline[relative]
                groups["replaced" if relative in replacements else "added"].append(row)
            write_blob(git, source_tree, PUBLIC_WRAPPER, site / PUBLIC_ROUTE)
            groups["replaced"].append({"path": PUBLIC_ROUTE, "source_path": PUBLIC_WRAPPER,
                                       "source": git.metadata(source_tree[PUBLIC_WRAPPER]),
                                       "before": baseline[PUBLIC_ROUTE],
                                       "served": file_metadata(site / PUBLIC_ROUTE)})
            profile = {"schemaVersion": 1, "profile": args.profile, "scenario": args.scenario}
            (site / BUILD_PROFILE).write_text(json.dumps(profile, separators=(",", ":")) + "\n")
            os.chmod(site / BUILD_PROFILE, 0o644)
            groups["added"].append({"path": BUILD_PROFILE, "source_path": None,
                                    "source": "explicit builder profile; never query-selected",
                                    "served": file_metadata(site / BUILD_PROFILE)})
            for path in sorted(baseline):
                if path in replacements:
                    continue
                need(file_metadata(site / path) == baseline[path], f"protected main file changed: {path}")
                groups["protected"].append({"path": path, "source_path": owners[path],
                                            "source": git.metadata(main_tree[owners[path]]),
                                            "served": baseline[path]})
            for rows in groups.values():
                rows.sort(key=lambda row: row["path"])
            overlay = package / "overlay"
            for row in groups["added"] + groups["replaced"]:
                target = overlay / row["path"]
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(site / row["path"], target)
            need(files_metadata(overlay) == {row["path"]: row["served"]
                                            for row in groups["added"] + groups["replaced"]},
                 "overlay contains a missing or protected main file")
            rollback = rehearse(snapshot_helper, snapshot_dir, site, baseline, groups, work)
            manifest = {
                "schema_version": 1,
                "purpose": "local_public_review_candidate" if args.profile == "review" else "local_public_live_install_candidate",
                "deployable": False, "profile": profile,
                "network_used": False, "checkout_changed": False, "published": False,
                "candidate": candidate, "main": main,
                "source_scope": "immutable Git source; main is not the installed predecessor",
                "served_root": "site", "overlay_root": "overlay",
                "overlay_scope": "bounded route replacements and listed additions only; not authorized for installation",
                "resource_base": "/visual-search/", "public_path": "/poisk-turov/",
                "aliases": [{"target": target, "source": source} for target, source in ALIASES],
                args.profile + "_graph": graph[graph_key],
                "graph": {"initial": graph[graph_key], "on_demand": graph["on_demand"], "styles": graph["styles"]},
                "compilation": {"versions": versions,
                                "inputs": {p: git.metadata(helpers[p]) for p in sorted(helpers)},
                                "canonical_inputs": {p: git.metadata(source_tree[p]) for p in CANONICAL_INPUTS},
                                "manifest": "compiler-output.json",
                                "manifest_scope": "separate source compilation, not final served hashes",
                                "retained_main_instead_of_compiled": retained_compiled},
                "files": groups, "rollback": rollback,
                "boundaries": ["profile is selected at build time; query cannot change it",
                               "no PHP execution, browser/server check, supplier measurement or lead delivery is claimed",
                               "main homepage, SEO, price, data and lead owners are retained; installed configuration is not copied",
                               "only the two page aliases are staged; installed standalone bridge/receiver configuration is not inferred",
                               "LIVE payload completeness does not verify installed INT/LOCAL/signer/bot dependencies or authorize a production switch"],
            }
            if args.profile == "live":
                manifest["server_dependencies"] = {
                    "status": "unverified; no server or configuration was read",
                    "retained_main": {path: baseline[path] for path in LIVE_MAIN_DEPENDENCIES},
                    "optional_popularity": {"path": OPTIONAL_POPULARITY,
                                            "present_in_main": OPTIONAL_POPULARITY in baseline,
                                            "candidate_copied": False,
                                            "transitive_dependencies_verified": False},
                    "endpoint_admission": "existing INT/LOCAL paths, session, signer and bot consumer require installed verification",
                    "rollback_admission": "a fresh server-local predecessor snapshot and restore drill are still required",
                }
            for name, value in (("manifest.json", manifest), ("compiler-output.json", compiled),
                                ("rollback-rehearsal.json", rollback)):
                (package / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n")
            # Recheck immediately before publishing only to a new/empty local
            # folder. Never clear an existing artifact or a checkout.
            output_path(destination, args.repo, git.metadata_dirs)
            if destination.exists():
                destination.rmdir()
            package.rename(destination)
            return manifest
    finally:
        git.close()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo", type=Path, required=True)
    parser.add_argument("--candidate-sha", required=True)
    parser.add_argument("--main-sha", required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--profile", choices=("review", "live"), default="review",
                        help="offline artifact profile; live does not authorize or perform installation")
    parser.add_argument("--scenario", choices=("family", "snapshot"), default="family",
                        help="REVIEW fixture selection; LIVE does not include fixture data")
    parser.add_argument("--node", default="node")
    parser.add_argument("--node-modules", type=Path,
                        default=Path(__file__).resolve().parent / "search3-js/node_modules")
    args = parser.parse_args()
    try:
        result = build(args)
        print(json.dumps({"status": "local_public_" + args.profile + "_written", "deployable": False,
                          "candidate_sha": result["candidate"]["sha"], "main_sha": result["main"]["sha"],
                          "profile": result["profile"],
                          "files": {key: len(rows) for key, rows in result["files"].items()},
                          "rollback": result["rollback"]["status"]}))
        return 0
    except (RuntimeError, OSError, ValueError, KeyError, subprocess.SubprocessError) as exc:
        print("SEARCH3_PUBLIC_CANDIDATE_ERROR: " + str(exc), file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
