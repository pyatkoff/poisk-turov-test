#!/usr/bin/env python3
"""Offline package/rollback regression using real compiler and canonical owners.

The disposable Git repository has a synthetic main baseline with visibly
different protected contracts. Its candidate contains the actual checked-out v2
and build helpers. This tests packaging, not supplier speed or installed PHP.
"""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parents[1]
SOURCE_ROOT = ROOT
NODE_MODULES = ROOT / "scripts/build/search3-js/node_modules"
SCRIPT = ROOT / "scripts/build/search3_public_candidate.py"
PUBLIC_ROUTE = "poisk-turov/index.php"
WRAPPER = "v2/visual-search/public-entry-v1.php"
COMPILER_DIR = "scripts/build/search3-js"


class PublicCandidateTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        if not (SOURCE_ROOT / WRAPPER).is_file():
            raise RuntimeError("actual public entry source is required; use --source-root for an isolated source assembly")
        cls.tmp = tempfile.TemporaryDirectory(prefix="search3-public-test-")
        cls.addClassCleanup(cls.tmp.cleanup)
        cls.base = Path(cls.tmp.name)
        cls.repo = cls.base / "repo"
        cls.repo.mkdir()
        cls.git("init", "--quiet")
        cls.git("config", "user.name", "Offline Package Test")
        cls.git("config", "user.email", "offline@example.invalid")
        cls.main_files = {
            "index.php": b"<?php /* MAIN_SEARCH_SOURCE */\n",
            "home-entry-v1.php": b"<?php /* MAIN_HOMEPAGE */\n",
            PUBLIC_ROUTE: b"<?php /* MAIN_PUBLIC_ROUTE */\n",
            "config.php": b"<?php /* MAIN_VERSIONED_CONFIG_FIXTURE */\n",
            "analytics-config.php": b"<?php /* MAIN_ANALYTICS_CONFIG_OWNER */\n",
            "seo-config.php": b"<?php /* MAIN_SEO_OWNER */\n",
            "lead-price-v1.php": b"<?php /* MAIN_PRICE_OWNER */\n",
            "lead-adapter-v2.php": b"<?php /* MAIN_ADAPTER_SOURCE_NOT_INSTALLED_BRIDGE */\n",
            "lead-bridge-v1.php": b"<?php /* MAIN_BRIDGE_MUST_NOT_REPLACE_ADAPTER */\n",
            "lead-receiver-v1.php": b"<?php /* MAIN_RECEIVER_OWNER */\n",
            "tour-controller-v4.js": b"/* MAIN_CONTROLLER_MUST_NOT_BE_USED_AS_CANONICAL_COMPILER_INPUT */\n",
            "lead-form-guard-v1.js": b"/* MAIN_GUARD_MUST_NOT_BE_USED_AS_CANONICAL_COMPILER_INPUT */\n",
            "data/db-v1.php": b"<?php /* MAIN_DATA_OWNER */\n",
            "main-only.txt": b"MAIN_ONLY_FILE_MUST_SURVIVE\n",
        }
        for name in ("runtime-v3.js", "lead-search-context.js", "analytics-v4.js"):
            cls.main_files[name] = (SOURCE_ROOT / "v2" / name).read_bytes() + b"\n/* main fixture revision */\n"
        for relative, data in cls.main_files.items():
            cls.write("v2/" + relative, data)
        os.chmod(cls.repo / "v2" / PUBLIC_ROUTE, 0o755)
        os.chmod(cls.repo / "v2/lead-receiver-v1.php", 0o755)
        cls.main_sha = cls.commit()
        shutil.copytree(SOURCE_ROOT / "v2", cls.repo / "v2", dirs_exist_ok=True)
        shutil.copytree(SOURCE_ROOT / COMPILER_DIR, cls.repo / COMPILER_DIR,
                        ignore=shutil.ignore_patterns("node_modules", "__pycache__"))
        for relative in ("scripts/build/search3_production_inventory.py",
                         "scripts/deploy/search3_production_snapshot.py"):
            cls.write(relative, (SOURCE_ROOT / relative).read_bytes())
        # Test input ownership is an immutable fixture commit, never this working
        # tree. This includes the real compiler, not a substitute test printer.
        cls.candidate_sha = cls.commit()
        cls.review = cls.base / "family-review"
        result = cls.run_tool(output=cls.review)
        if result.returncode:
            raise RuntimeError(result.stderr)
        cls.manifest = json.loads((cls.review / "manifest.json").read_text())
        cls.live = cls.base / "live-candidate"
        result = cls.run_tool(output=cls.live, extra=("--profile", "live"))
        if result.returncode:
            raise RuntimeError(result.stderr)
        cls.live_manifest = json.loads((cls.live / "manifest.json").read_text())
        sys.path.insert(0, str(SCRIPT.parent))
        spec = importlib.util.spec_from_file_location("search3_candidate_under_test", SCRIPT)
        cls.tool = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(cls.tool)

    @classmethod
    def git(cls, *args: str) -> str:
        result = subprocess.run(["git", "-C", str(cls.repo), *args], capture_output=True, text=True)
        if result.returncode:
            raise RuntimeError(result.stderr)
        return result.stdout.strip()

    @classmethod
    def write(cls, path: str, value: bytes) -> None:
        target = cls.repo / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(value)

    @classmethod
    def commit(cls) -> str:
        cls.git("add", "--all")
        cls.git("commit", "--quiet", "--allow-empty", "-m", "offline package fixture")
        return cls.git("rev-parse", "HEAD")

    @classmethod
    def run_tool(cls, *, candidate: str | None = None, main: str | None = None,
                 output: Path | None = None, extra: tuple[str, ...] = ()) -> subprocess.CompletedProcess:
        return subprocess.run([
            sys.executable, str(SCRIPT), "--repo", str(cls.repo),
            "--candidate-sha", candidate or cls.candidate_sha,
            "--main-sha", main or cls.main_sha,
            "--output", str(output or cls.base / "other-review"),
            "--node-modules", str(NODE_MODULES), *extra,
        ], capture_output=True, text=True, timeout=90)

    def setUp(self) -> None:
        # This repository was created by this test; no shared checkout is reset.
        self.git("reset", "--hard", self.candidate_sha)
        self.git("clean", "-fd", "--quiet")

    def assert_refused(self, result, output: Path, fragment: str) -> None:
        self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
        self.assertIn(fragment, result.stderr)
        self.assertFalse(output.exists(), "a refused build left an artifact")

    def test_compiled_review_overlay_and_all_main_contracts(self) -> None:
        manifest = self.manifest
        site, overlay = self.review / "site", self.review / "overlay"
        self.assertEqual(manifest["profile"], {"schemaVersion": 1, "profile": "review", "scenario": "family"})
        self.assertFalse(manifest["deployable"])
        self.assertFalse(manifest["network_used"])
        self.assertFalse(manifest["published"])
        self.assertIn("not the installed predecessor", manifest["source_scope"])
        self.assertEqual(manifest["candidate"]["sha"], self.candidate_sha)
        self.assertEqual(manifest["main"]["sha"], self.main_sha)
        self.assertEqual([row["path"] for row in manifest["files"]["replaced"]], [PUBLIC_ROUTE])
        for relative, expected in self.main_files.items():
            if relative in (PUBLIC_ROUTE, "index.php"):
                continue
            self.assertEqual((site / relative).read_bytes(), expected, relative)
        self.assertEqual((site / "index.php").read_bytes(), self.main_files["home-entry-v1.php"])
        self.assertEqual((site / "search-page-v2.php").read_bytes(), self.main_files["index.php"])
        self.assertEqual((site / "lead-receiver-v1.php").stat().st_mode & 0o777, 0o755)
        wrapper = subprocess.check_output(["git", "-C", str(self.repo), "show", self.candidate_sha + ":" + WRAPPER])
        self.assertEqual((site / PUBLIC_ROUTE).read_bytes(), wrapper)
        self.assertFalse((site / "visual-search/public-entry-v1.php").exists())
        self.assertTrue((site / "poisk-turov-old/index.php").is_file())
        self.assertTrue((site / "visual-search/fixtures/live-search-2026-09-23.json").is_file())
        self.assertFalse((site / "visual-search/public-analytics-v1.js").exists())
        self.assertFalse((overlay / "data/db-v1.php").exists())
        self.assertFalse((overlay / "data/hotel-popularity-v1.php").exists())
        self.assertFalse((overlay / "runtime-v3.js").exists())
        self.assertFalse((overlay / "lead-adapter-v2.php").exists())
        self.assertFalse((site / "visual-search/asset-size.json").exists())
        self.assertEqual(manifest["review_graph"][0], "./public-config-v1.js")
        self.assertEqual(manifest["review_graph"][-1], "./app.js")
        for rows in manifest["files"].values():
            for row in rows:
                self.assertEqual(self.tool.file_metadata(site / row["path"]), row["served"])
        self.assertEqual(self.tool.files_metadata(overlay),
                         {row["path"]: row["served"]
                          for row in manifest["files"]["added"] + manifest["files"]["replaced"]
                          if not row.get("candidate_only")})
        compiled = json.loads((self.review / "compiler-output.json").read_text())
        app = next(row for row in compiled["files"] if row["target"] == "visual-search/app.js")
        self.assertLess(app["served"], app["raw"])
        self.assertEqual(hashlib.sha256((site / app["target"]).read_bytes()).hexdigest(), app["sha256"])
        protected_compiled = {row["path"]: row for row in manifest["compilation"]["retained_main_instead_of_compiled"]}
        for name in ("runtime-v3.js", "lead-search-context.js", "analytics-v4.js"):
            self.assertIn(name, protected_compiled)
            row = protected_compiled[name]
            self.assertNotEqual(row["compiled_candidate"]["sha256"], row["retained_main"]["sha256"])
            self.assertEqual(self.tool.file_metadata(site / name), row["retained_main"])

    def test_actual_snapshot_restore_rehearsal_preserves_route_mode(self) -> None:
        snapshot = json.loads((self.review / "rollback/main-routes/manifest.json").read_text())
        self.assertEqual(snapshot["source_sha"], self.main_sha)
        self.assertEqual([row["path"] for row in snapshot["files"]], [PUBLIC_ROUTE])
        self.assertEqual(snapshot["files"][0]["mode"], "0755")
        self.assertEqual((self.review / "rollback/main-routes/payload" / PUBLIC_ROUTE).read_bytes(),
                         self.main_files[PUBLIC_ROUTE])
        proof = json.loads((self.review / "rollback-rehearsal.json").read_text())
        self.assertEqual(proof["status"], "local_main_routes_restored")
        self.assertTrue(proof["all_main_bytes_and_modes_restored"])
        self.assertEqual(proof["restored"][PUBLIC_ROUTE]["mode"], "0755")
        self.assertEqual(proof["removed_additions"], len(self.manifest["files"]["added"]))

    def test_explicit_live_closure_retains_main_and_has_no_offline_or_php_expansion(self) -> None:
        manifest, site = self.live_manifest, self.live / "site"
        self.assertEqual(manifest["purpose"], "local_public_live_install_candidate")
        self.assertEqual(manifest["profile"], {"schemaVersion": 1, "profile": "live", "scenario": "family"})
        self.assertFalse(manifest["deployable"])
        self.assertFalse(manifest["network_used"])
        self.assertFalse(manifest["published"])
        self.assertEqual(len(manifest["live_graph"]), 22)
        self.assertEqual(manifest["graph"]["initial"], manifest["live_graph"])
        self.assertEqual(manifest["live_graph"][:3],
                         ["./public-config-v1.js", "../runtime-v3.js", "../analytics-v4.js"])
        self.assertIn("./public-analytics-v1.js", manifest["live_graph"])
        self.assertIn("./live-bridge.js", manifest["live_graph"])
        self.assertIn("../prototype-search/messenger-flow-v1.js", manifest["live_graph"])
        for ref in [*manifest["live_graph"], *manifest["graph"]["on_demand"].values(), *manifest["graph"]["styles"]]:
            self.assertTrue((site / "visual-search" / ref).is_file(), ref)
        for absent in ("fixture-data.js", "recorded-data.js", "preview-lead.js", "local-db-parser.js",
                       "fixtures/live-search-2026-09-23.json", "assets/photo-1.jpg", "assets/photo-2.jpg"):
            self.assertFalse((site / "visual-search" / absent).exists(), absent)
        self.assertFalse((site / "prototype-search/config.js").exists(), "only the public config selects LIVE")
        overlay = self.live / "overlay"
        self.assertFalse((overlay / "data").exists(), "LIVE must not add PHP/DB dependencies")
        self.assertFalse((overlay / "app").exists(), "LIVE must not copy a supplier or MATCH implementation")
        self.assertFalse((overlay / "api-v2.php").exists())
        self.assertEqual([row["path"] for row in manifest["files"]["replaced"]], [PUBLIC_ROUTE])
        for relative, expected in self.main_files.items():
            if relative not in (PUBLIC_ROUTE, "index.php"):
                self.assertEqual((site / relative).read_bytes(), expected, relative)
        for name in ("runtime-v3.js", "lead-search-context.js", "analytics-v4.js"):
            self.assertFalse((overlay / name).exists(), name)
            self.assertEqual(manifest["server_dependencies"]["retained_main"][name],
                             self.tool.file_metadata(site / name))
        popularity = manifest["server_dependencies"]["optional_popularity"]
        self.assertFalse(popularity["present_in_main"])
        self.assertFalse(popularity["candidate_copied"])
        self.assertFalse(popularity["transitive_dependencies_verified"])
        for rows in manifest["files"].values():
            for row in rows:
                self.assertEqual(self.tool.file_metadata(site / row["path"]), row["served"])
        self.assertEqual(self.tool.files_metadata(overlay),
                         {row["path"]: row["served"]
                          for row in manifest["files"]["added"] + manifest["files"]["replaced"]
                          if not row.get("candidate_only")})
        compiled = json.loads((self.live / "compiler-output.json").read_text())
        additions = {row["path"] for row in manifest["files"]["added"]}
        for row in compiled["files"]:
            if row["target"] in additions:
                self.assertEqual(self.tool.file_metadata(site / row["target"])["sha256"], row["sha256"])
        proof = manifest["rollback"]
        self.assertTrue(proof["all_main_bytes_and_modes_restored"])
        self.assertEqual(proof["restored"][PUBLIC_ROUTE]["mode"], "0755")
        self.assertEqual(proof["removed_additions"], len(manifest["files"]["added"]))

    def test_live_refuses_missing_retained_main_dependencies_and_new_owner_collisions(self) -> None:
        for name in self.tool.LIVE_MAIN_DEPENDENCIES:
            with self.subTest(dependency=name):
                self.git("reset", "--hard", self.main_sha)
                (self.repo / "v2" / name).unlink()
                output = self.base / ("missing-live-" + name)
                result = self.run_tool(main=self.commit(), output=output, extra=("--profile", "live"))
                self.assert_refused(result, output, "requires an existing main dependency: " + name)
        self.git("reset", "--hard", self.main_sha)
        self.write("v2/prototype-search/data.js", b"DIFFERENT_MAIN_DATA_OWNER\n")
        output = self.base / "live-owner-collision"
        self.assert_refused(self.run_tool(main=self.commit(), output=output, extra=("--profile", "live")),
                            output, "overwrite a protected main file: prototype-search/data.js")

    def test_actual_served_family_adapter_local_photos_are_in_payload(self) -> None:
        # The previous live-only asset closure omitted the demo photos even
        # though the actual offline adapter returned their URLs. Execute that
        # served owner with a filesystem-only fixture read, then resolve every
        # local photo against the final document root (no HTTP/browser needed).
        program = r"""
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const root=path.resolve(process.argv[1]),reads=[];
const context={structuredClone,URLSearchParams,location:{search:'?scenario=journey&profile=live'},
 AnyTourPublicConfigV1:{valid:true,profile:'review',asset:name=>'/visual-search/'+name},
 V2_CONFIG:{search3ReviewScenario:'family'},
 fetch:async url=>{reads.push(url);assert.equal(url,'/visual-search/fixtures/live-search-2026-09-23.json');
  return {ok:true,json:async()=>JSON.parse(fs.readFileSync(root+url,'utf8'))};}};
context.window=context;
vm.runInNewContext(fs.readFileSync(path.join(root,'visual-search/fixture-data.js'),'utf8'),context);
(async()=>{const adapter=context.AnyTourPrototypeData;await adapter.init();let hotels;
 await adapter.search(adapter.initialSearch,event=>{if(event.type==='results')hotels=event.hotels;});
 assert.equal(adapter.scenario,'family');assert.equal(hotels.length,1);
 const photos=hotels.flatMap(hotel=>hotel.photos);assert(photos.length>0);
 for(const url of photos){assert(url.startsWith('/visual-search/assets/'));
  assert(fs.statSync(root+url).isFile(),'missing adapter photo '+url);}
 assert(fs.existsSync(path.join(root,'visual-search/assets/photo-unavailable.svg')),'missing real app photo fallback');
 process.stdout.write(JSON.stringify({photos,reads}));
})().catch(error=>{console.error(error);process.exitCode=1;});
"""
        result = subprocess.run(["node", "-e", program, str(self.review / "site")], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        checked = json.loads(result.stdout)
        self.assertEqual(len(checked["photos"]), 2)
        self.assertEqual(checked["reads"], ["/visual-search/fixtures/live-search-2026-09-23.json"])

    def test_shared_home_header_closure_and_local_only_canonical_logo(self) -> None:
        for artifact, manifest in ((self.review, self.manifest), (self.live, self.live_manifest)):
            with self.subTest(profile=manifest["profile"]["profile"]):
                self.assertTrue(manifest["shared_ui"]["enabled"])
                self.assertEqual(manifest["graph"]["styles"],
                                 ["./styles.css", "./mobile-controls-v1.css", "../site-header-v2.css"])
                site, overlay = artifact / "site", artifact / "overlay"
                for name in ("home-v1.php", "home-v1.css", "home-hero-v1.jpg", "site-header-v2.php",
                             "phone-value.php", "asset-version-v1.php", "site-path-v1.php"):
                    expected = subprocess.check_output([
                        "git", "-C", str(self.repo), "show", self.candidate_sha + ":v2/" + name])
                    self.assertEqual((site / name).read_bytes(), expected, name)
                compiled = json.loads((artifact / "compiler-output.json").read_text())
                header_css = next(row for row in compiled["css"]["files"] if row["target"] == "site-header-v2.css")
                self.assertEqual(self.tool.file_metadata(site / "site-header-v2.css")["sha256"], header_css["sha256"])
                alias = manifest["candidate_only_aliases"]
                self.assertEqual(len(alias), 1)
                self.assertEqual(alias[0]["path"], "images/logo.svg")
                self.assertTrue(alias[0]["candidate_only"])
                self.assertEqual(alias[0]["served"]["sha256"], self.tool.LOCAL_LOGO_SHA256)
                self.assertEqual((site / "images/logo.svg").read_bytes(),
                                 (site / "visual-search/assets/anytour-logo.svg").read_bytes())
                self.assertFalse((overlay / "images/logo.svg").exists(), "review alias cannot overwrite installed artwork")
                for protected in ("seo-config.php", "analytics-config.php", "lead-price-v1.php", "data/db-v1.php"):
                    self.assertEqual((site / protected).read_bytes(), self.main_files[protected])
                    self.assertFalse((overlay / protected).exists())

    def test_shared_ui_overwrite_admits_only_four_inspected_preimages_and_modes(self) -> None:
        # Exercise the collision boundary directly without manufacturing bytes
        # for the known Git blob identities. The full exact-MAIN candidate build
        # separately verifies these identities against real immutable blobs.
        class MetadataOnlyGit:
            @staticmethod
            def metadata(entry):
                return entry
        git = MetadataOnlyGit()
        for target, blob in self.tool.SHARED_UI_PREDECESSORS.items():
            with self.subTest(target=target):
                source = "v2/" + target
                candidate = {source: {"blob_sha": "a" * 40, "mode": "100644", "size": 1}}
                main = {source: {"blob_sha": blob, "mode": "100644", "size": 2}}
                owners = {target: source}
                self.assertEqual(self.tool.check_collisions(git, candidate, main, owners, [source], True),
                                 {PUBLIC_ROUTE, target})
                with self.assertRaisesRegex(RuntimeError, "protected main file"):
                    self.tool.check_collisions(git, candidate, main, owners, [source])
                for changed in ({"blob_sha": "b" * 40}, {"mode": "100755"}):
                    bad = {source: {**main[source], **changed}}
                    with self.assertRaisesRegex(RuntimeError, "unknown shared UI owner"):
                        self.tool.check_collisions(git, candidate, bad, owners, [source], True)

    def test_shared_ui_unknown_predecessors_and_canonical_logo_drift_are_refused(self) -> None:
        for target in (*self.tool.SHARED_UI_PREDECESSORS, "home-hero-v1.jpg", "site-path-v1.php"):
            with self.subTest(unknown_owner=target):
                self.git("reset", "--hard", self.main_sha)
                self.write("v2/" + target, b"UNREVIEWED_MAIN_OWNER\n")
                output = self.base / ("unknown-shared-" + target)
                result = self.run_tool(main=self.commit(), output=output)
                expected = "unknown shared UI owner" if target in self.tool.SHARED_UI_PREDECESSORS else "protected main file"
                self.assert_refused(result, output, expected)
        self.git("reset", "--hard", self.candidate_sha)
        self.write(self.tool.LOCAL_LOGO_SOURCE, b"<svg>changed artwork</svg>\n")
        output = self.base / "changed-logo"
        self.assert_refused(self.run_tool(candidate=self.commit(), output=output), output,
                            "canonical local logo source changed")

    def test_dirty_checkout_is_ignored_and_snapshot_is_fixed_build_profile(self) -> None:
        self.write("v2/visual-search/app.js", b"THIS_DIRTY_CHECKOUT_MUST_NOT_COMPILE\n")
        (self.repo / WRAPPER).unlink()
        before = self.git("status", "--porcelain")
        output = self.base / "snapshot-review"
        result = self.run_tool(output=output, extra=("--scenario", "snapshot"))
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.git("status", "--porcelain"), before)
        profile = json.loads((output / "site/visual-search/public-build.json").read_text())
        self.assertEqual(profile, {"schemaVersion": 1, "profile": "review", "scenario": "snapshot"})
        self.assertEqual((output / "site/visual-search/app.js").read_bytes(),
                         (self.review / "site/visual-search/app.js").read_bytes())
        self.assertEqual((output / "site" / PUBLIC_ROUTE).read_bytes(),
                         (self.review / "site" / PUBLIC_ROUTE).read_bytes())

    def test_refuses_addition_collisions_instead_of_replacing_an_owner(self) -> None:
        self.write("v2/visual-search/app.js", b"MAIN_HAS_ANOTHER_APP_OWNER\n")
        different_main = self.commit()
        output = self.base / "collision-review"
        result = self.run_tool(main=different_main, output=output)
        self.assert_refused(result, output, "overwrite a protected main file: visual-search/app.js")

    def test_refuses_changed_canonical_compiler_input(self) -> None:
        original = (self.repo / "v2/tour-controller-v4.js").read_bytes()
        self.write("v2/tour-controller-v4.js", original + b"\n/* changed compiler owner */\n")
        output = self.base / "canonical-review"
        result = self.run_tool(candidate=self.commit(), output=output)
        self.assert_refused(result, output, "pinned visual compiler failed")

    def test_known_old_route_correction_is_snapshotted_unknown_owner_is_refused(self) -> None:
        predecessor = b"""<?php
/** Previous search presentation; same maintained search and lead runtime. */
define('V2_SEARCH3_PRESENTATION', false);
define('V2_LEGACY_SEARCH', true);
define('V2_FORCE_SEARCH_PAGE', true);
define('V2_PUBLIC_BASE_PATH', '');
define('V2_CANONICAL_PATH', '/poisk-turov/');
header('X-Robots-Tag: noindex, follow');
require dirname(__DIR__) . '/index.php';
"""
        self.git("reset", "--hard", self.main_sha)
        self.write("v2/poisk-turov-old/index.php", predecessor)
        old_main = self.commit()
        output = self.base / "old-route-review"
        result = self.run_tool(main=old_main, output=output)
        self.assertEqual(result.returncode, 0, result.stderr)
        manifest = json.loads((output / "manifest.json").read_text())
        self.assertEqual({row["path"] for row in manifest["files"]["replaced"]},
                         {PUBLIC_ROUTE, "poisk-turov-old/index.php"})
        self.assertEqual((output / "rollback/main-routes/payload/poisk-turov-old/index.php").read_bytes(), predecessor)
        self.assertTrue(manifest["rollback"]["all_main_bytes_and_modes_restored"])
        fixed = (output / "site/poisk-turov-old/index.php").read_text()
        self.assertIn("$searchEntry = $root . '/search-page-v2.php';", fixed)
        self.write("v2/poisk-turov-old/index.php", predecessor + b"/* another main owner change */\n")
        output = self.base / "unknown-old-route-review"
        self.assert_refused(self.run_tool(main=self.commit(), output=output), output,
                            "overwrite an unknown old-route owner")

    def test_cleanup_preflights_all_hashes_and_never_deletes_unlisted_files(self) -> None:
        trial = self.base / "cleanup-trial"
        shutil.copytree(self.review / "site", trial)
        added = self.manifest["files"]["added"]
        first, last = added[0]["path"], added[-1]["path"]
        (trial / "unlisted-note.txt").write_text("unrelated local work")
        (trial / last).write_bytes(b"changed after the manifest")
        with self.assertRaisesRegex(RuntimeError, "refusing cleanup"):
            self.tool.remove_listed_additions(trial, added)
        self.assertTrue((trial / first).exists(), "preflight failure caused partial cleanup")
        self.assertEqual((trial / last).read_bytes(), b"changed after the manifest")
        shutil.copy2(self.review / "site" / last, trial / last)
        self.tool.remove_listed_additions(trial, added)
        self.assertTrue((trial / "unlisted-note.txt").exists())
        self.assertTrue((trial / "lead-price-v1.php").exists())
        self.assertTrue((trial / PUBLIC_ROUTE).exists())
        self.assertTrue(all(not (trial / row["path"]).exists() for row in added))

    def test_refuses_unsafe_sources_and_mutable_refs_without_output(self) -> None:
        for candidate in ("HEAD", self.candidate_sha[:12], self.git("rev-parse", self.candidate_sha + ":" + WRAPPER)):
            with self.subTest(candidate=candidate):
                output = self.base / "bad-ref-review"
                result = self.run_tool(candidate=candidate, output=output)
                self.assert_refused(result, output, "SEARCH3_PUBLIC_CANDIDATE_ERROR")
        (self.repo / "v2/unsafe-symlink").symlink_to("../../unrelated")
        output = self.base / "symlink-review"
        result = self.run_tool(candidate=self.commit(), output=output)
        self.assert_refused(result, output, "symlink or non-regular")

    def test_refuses_unsafe_outputs_without_changing_existing_files(self) -> None:
        marker = self.base / "existing-review"
        marker.mkdir()
        (marker / "keep.txt").write_text("keep")
        result = self.run_tool(output=marker)
        self.assertEqual(result.returncode, 2)
        self.assertEqual((marker / "keep.txt").read_text(), "keep")
        for output in (self.repo / "review", self.repo / ".git/review"):
            self.assert_refused(self.run_tool(output=output), output, "output must be outside")
        linked = self.base / "linked-output"
        linked.symlink_to(marker, target_is_directory=True)
        result = self.run_tool(output=linked / "review")
        self.assertEqual(result.returncode, 2)
        self.assertIn("symlink", result.stderr)
        self.assertFalse((marker / "review").exists())

    def test_no_implicit_profile_or_dependency_install_fallback(self) -> None:
        output = self.base / "invalid-profile"
        result = self.run_tool(output=output, extra=("--profile", "automatic"))
        self.assertEqual(result.returncode, 2)
        self.assertIn("invalid choice", result.stderr)
        self.assertFalse(output.exists())
        output = self.base / "live-snapshot"
        result = self.run_tool(output=output, extra=("--profile", "live", "--scenario", "snapshot"))
        self.assert_refused(result, output, "scenario selection applies only to review")
        output = self.base / "missing-deps-review"
        result = self.run_tool(output=output, extra=("--node-modules", str(self.base / "absent-dependencies")))
        self.assert_refused(result, output, "no install is performed")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(add_help=False)
    parser.add_argument("--source-root", type=Path, default=ROOT)
    parser.add_argument("--node-modules", type=Path)
    options, remaining = parser.parse_known_args()
    SOURCE_ROOT = options.source_root.resolve()
    NODE_MODULES = (options.node_modules or SOURCE_ROOT / "scripts/build/search3-js/node_modules").resolve()
    unittest.main(argv=[sys.argv[0], *remaining])
