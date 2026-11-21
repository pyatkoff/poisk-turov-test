#!/usr/bin/env python3
"""Offline contract tests for source inventory and conservative refusal paths."""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts/build/search3_production_inventory.py"
ENTRY = "v2/visual-search/index.php"
RENDERER = "v2/visual-search/entry-v1.php"
HTML = "v2/visual-search/index.html"
APP = "v2/visual-search/app.js"
PHP = """<?php
$html = file_get_contents(__DIR__ . '/index.html');
$scripts = ['../prototype-search/config.js', '../runtime-v3.js', './app.js'];
$publicConfigScript = './public-config-v1.js';
$publicAnalyticsScript = '../analytics-v4.js';
$publicBeforeApp = ['./public-url-v1.js', './public-bootstrap-v1.js', './public-analytics-v1.js'];
require_once $analytics;
$popularitySource = dirname(__DIR__) . '/data/hotel-popularity-v1.php';
if (is_file($popularitySource)) { require_once $popularitySource; }
require_once dirname(__DIR__) . '/seo-config.php';
"""
PAGE = """<!doctype html><html>
<head data-offer-list-src="./offer-list-v1.js" data-hotel-details-src="./hotel-details-v1.js" data-flight-picker-src="./flight-picker-ui-v1.js">
<link rel="stylesheet" href="./styles.css"><link rel="stylesheet" href="./mobile-controls-v1.css">
<link rel="icon" href="./assets/favicon.png"></head><body>
<img src="./assets/logo.svg"><script src="./fixture-data.js"></script><script src="./app.js"></script>
</body></html>
"""
APP_CODE = """const operatorAssets={'ANEX':'anex.svg','Coral Travel':'coral.png'};
const logo = visualResource('assets/operators/'+file);
const fallback = visualResource('assets/photo-unavailable.svg');
const hotelDetailsAsset=document.head.dataset.hotelDetailsSrc;
{ const script=document.createElement('script');script.src=hotelDetailsAsset; }
{ const script=document.createElement('script');script.src=document.head.dataset.flightPickerSrc||'./flight-picker-ui-v1.js'; }
const offerListAsset=document.head.dataset.offerListSrc;
{ const script=document.createElement('script');script.src=offerListAsset; }
"""

# The positive fixture is the accepted shared loader and its three adapters,
# including the different captured/current source policies. It is not executed.
SHARED_FACTORY = """function createPresentationLoader(owner,source,errors,async=false){
 let load=null;
 return ()=>{
  const available=owner();if(available)return Promise.resolve(available);
  if(load)return load;
  load=new Promise((resolve,reject)=>{
   const script=document.createElement('script');let finished=false;
   const finish=error=>{if(finished)return;finished=true;clearTimeout(timer);script.onload=script.onerror=null;script.remove();if(error){load=null;reject(error);}else resolve(owner());};
   const timer=setTimeout(()=>finish(new Error(errors.timeout)),15000);
   script.onload=()=>finish(owner()?null:new Error(errors.missing));
   script.onerror=()=>finish(new Error(errors.failed));
   script.src=source();if(async)script.async=true;document.head.append(script);
  });
  return load;
 };
}
"""
SHARED_ADAPTERS = """const hotelDetailsAsset=document.head.dataset.hotelDetailsSrc;
const loadHotelDetails=createPresentationLoader(
 ()=>window.AnyTourHotelDetails?.create?window.AnyTourHotelDetails:null,
 ()=>hotelDetailsAsset,
 {timeout:'Hotel details load timed out',missing:'Hotel details owner missing',failed:'Hotel details load failed'}
);
const loadFlightPicker=createPresentationLoader(
 ()=>window.AnyTourFlightPickerUIV1,
 ()=>document.head.dataset.flightPickerSrc||'./flight-picker-ui-v1.js',
 {timeout:'Не удалось загрузить варианты перелёта.',missing:'Варианты перелёта недоступны.',failed:'Не удалось загрузить варианты перелёта.'},true
);
const offerListAsset=document.head.dataset.offerListSrc;
const loadOfferList=createPresentationLoader(
 ()=>window.AnyTourOfferList?.create?window.AnyTourOfferList:null,
 ()=>offerListAsset,
 {timeout:'Offer list load timed out',missing:'Offer list owner missing',failed:'Offer list load failed'}
);
loadHotelDetails();loadFlightPicker();loadFlightPicker();loadOfferList();
"""
SHARED_APP_CODE = APP_CODE.split("const hotelDetailsAsset=", 1)[0] + SHARED_FACTORY + SHARED_ADAPTERS


class ProductionInventoryTest(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.base = Path(self.tmp.name)
        self.repo = self.base / "repo"
        self.repo.mkdir()
        self.git("init", "--quiet")
        self.git("config", "user.name", "Offline Test")
        self.git("config", "user.email", "offline@example.invalid")
        self.write("v2/runtime-v3.js", "const runtime = 'main';\n")
        self.write("v2/visual-search/styles.css", "@font-face{src:url('./assets/inter-latin.woff2')}\n")
        self.write("v2/unrelated.php", "main unrelated\n")
        self.write("v2/main-only.php", "must be preserved\n")
        self.write("v2/mode-only.js", "same bytes\n")
        self.main_sha = self.commit()
        self.files = {
            ENTRY: "<?php require __DIR__ . '/entry-v1.php';\n", RENDERER: PHP, HTML: PAGE, APP: APP_CODE,
            "v2/prototype-search/config.js": "const config = 'preview';\n",
            "scripts/build/search3-js/visual-entry.cjs": "// fixture declaration owner\n",
            "v2/runtime-v3.js": "const runtime = 'candidate';\n",
            "v2/visual-search/styles.css": "@font-face{src:url('./assets/inter-latin.woff2')}\n",
            "v2/visual-search/mobile-controls-v1.css": "body{background:url('data:image/svg+xml,%3Csvg/%3E')}\n",
            "v2/visual-search/offer-list-v1.js": "const offers = true;\n",
            "v2/visual-search/hotel-details-v1.js": "const details = true;\n",
            "v2/visual-search/flight-picker-ui-v1.js": "const flights = true;\n",
            "v2/visual-search/fixture-data.js": "// offline only, must not become a production prerequisite\n",
            "v2/data/hotel-popularity-v1.php": "<?php require_once 'private-db.php'; // explicit unmapped boundary\n",
            "v2/visual-search/assets/favicon.png": b"png\x00",
            "v2/visual-search/assets/logo.svg": "<svg/>\n",
            "v2/visual-search/assets/inter-latin.woff2": b"font\x00",
            "v2/visual-search/assets/inter-LICENSE.txt": "Font license\n",
            "v2/visual-search/assets/operators/anex.svg": "<svg id='anex'/>\n",
            "v2/visual-search/assets/operators/coral.png": b"coral\x00",
            "v2/visual-search/assets/photo-unavailable.svg": "<svg id='fallback'/>\n",
            "v2/unrelated.php": "candidate unrelated\n",
        }
        for path, content in self.files.items():
            self.write(path, content)
        (self.repo / "v2/main-only.php").unlink()
        os.chmod(self.repo / "v2/mode-only.js", 0o755)
        self.candidate_sha = self.commit()

    def git(self, *args: str) -> str:
        result = subprocess.run(["git", "-C", str(self.repo), *args], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        return result.stdout.strip()

    def write(self, path: str, value: str | bytes) -> None:
        target = self.repo / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(value.encode() if isinstance(value, str) else value)

    def commit(self) -> str:
        self.git("add", "--all")
        self.git("commit", "--quiet", "--allow-empty", "-m", "offline fixture")
        return self.git("rev-parse", "HEAD")

    def run_tool(self, *, candidate: str | None = None, main: str | None = None,
                 output: Path | None = None, ok: bool = True) -> subprocess.CompletedProcess:
        result = subprocess.run([
            sys.executable, str(SCRIPT), "--repo", str(self.repo),
            "--candidate-sha", candidate or self.candidate_sha,
            "--main-sha", main or self.main_sha,
            "--output", str(output or self.base / "review"),
        ], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0 if ok else 2, result.stderr)
        return result

    def test_exact_git_source_diff_hashes_and_no_checkout_copy(self) -> None:
        # An uncommitted edit and deleted checkout asset must not affect pinned source.
        self.write(APP, "uncommitted working tree code")
        (self.repo / "v2/visual-search/assets/favicon.png").unlink()
        status_before = self.git("status", "--porcelain")
        self.run_tool()
        output = self.base / "review"
        manifest = json.loads((output / "manifest.json").read_text())
        self.assertEqual(manifest["candidate"], {
            "sha": self.candidate_sha,
            "tree_sha": self.git("rev-parse", self.candidate_sha + "^{tree}"),
        })
        groups = manifest["runtime_comparison"]
        self.assertEqual([r["path"] for r in groups["replace"]], ["v2/runtime-v3.js"])
        self.assertEqual([r["path"] for r in groups["identical"]], ["v2/visual-search/styles.css"])
        added = {r["path"]: r for r in groups["added"]}
        self.assertIn("v2/visual-search/assets/operators/anex.svg", added)
        self.assertIn("v2/visual-search/assets/operators/coral.png", added)
        self.assertIn("v2/visual-search/assets/photo-unavailable.svg", added)
        self.assertIn("font_license", added["v2/visual-search/assets/inter-LICENSE.txt"]["roles"])
        self.assertNotIn("v2/visual-search/fixture-data.js", added)
        expected = self.files[APP].encode()
        self.assertEqual(added[APP]["candidate"]["sha256"], hashlib.sha256(expected).hexdigest())
        self.assertEqual(added[APP]["candidate"]["blob_sha"], self.git("rev-parse", self.candidate_sha + ":" + APP))
        self.assertEqual(added[APP]["candidate"]["mode"], "100644")
        self.assertEqual(added[APP]["candidate"]["size"], len(expected))
        self.assertEqual({r["path"] for r in manifest["all_divergent_shared_v2_paths"]},
                         {"v2/runtime-v3.js", "v2/unrelated.php", "v2/mode-only.js"})
        self.assertEqual([r["path"] for r in manifest["all_main_only_v2_paths"]], ["v2/main-only.php"])
        self.assertFalse(manifest["deployable"])
        self.assertFalse(manifest["server_graph_complete"])
        self.assertIn("not compiled", manifest["bytes_scope"])
        self.assertIn("not merge conflicts", manifest["comparison_scope"])
        self.assertEqual(len(manifest["unmapped_server_boundaries"]), 6)
        self.assertTrue(manifest["client_graph"]["declared_direct_php_dependencies"][0]["optional_at_runtime"])
        self.assertIn(RENDERER, added)
        self.assertEqual(manifest["client_graph"]["public_review"], [
            './public-config-v1.js', './fixture-data.js', './public-url-v1.js', './public-bootstrap-v1.js', './app.js'])
        self.assertNotIn('../analytics-v4.js', manifest["client_graph"]["public_review"])
        self.assertIn('../analytics-v4.js', manifest["client_graph"]["public_live"])
        snapshot = (output / "snapshot-candidate-paths.txt").read_text().splitlines()
        self.assertEqual([p for p in snapshot if not p.startswith("#")], ["runtime-v3.js"])
        self.assertEqual({p.name for p in output.iterdir()}, {"manifest.json", "snapshot-candidate-paths.txt"})
        self.assertEqual(self.git("status", "--porcelain"), status_before)

    def test_rejects_mutable_refs_short_sha_and_non_commit(self) -> None:
        values = ["HEAD", self.candidate_sha[:12], self.git("rev-parse", self.candidate_sha + ":" + APP)]
        for value in values:
            with self.subTest(value=value):
                self.run_tool(candidate=value, ok=False)
                self.assertFalse((self.base / "review").exists())

    def test_rejects_symlink_even_outside_client_graph(self) -> None:
        (self.repo / "v2/unsafe-link").symlink_to("../../outside")
        sha = self.commit()
        result = self.run_tool(candidate=sha, ok=False)
        self.assertIn("symlink or non-regular", result.stderr)
        self.assertFalse((self.base / "review").exists())
        result = self.run_tool(main=sha, ok=False)
        self.assertIn("symlink or non-regular", result.stderr)

    def test_rejects_missing_runtime_static_license_and_direct_php(self) -> None:
        paths = [RENDERER, "v2/visual-search/hotel-details-v1.js", "v2/visual-search/assets/operators/anex.svg",
                 "v2/visual-search/assets/photo-unavailable.svg",
                 "v2/visual-search/assets/inter-latin.woff2", "v2/visual-search/assets/inter-LICENSE.txt",
                 "v2/data/hotel-popularity-v1.php"]
        for path in paths:
            with self.subTest(path=path):
                (self.repo / path).unlink()
                result = self.run_tool(candidate=self.commit(), ok=False)
                self.assertIn("missing declared dependency", result.stderr)
                self.assertFalse((self.base / "review").exists())
                self.write(path, self.files[path])

    def test_rejects_dynamic_or_incomplete_graph_and_unknown_loaders(self) -> None:
        cases = [
            (RENDERER, PHP.replace("'./app.js'", "$dynamic"), "LIVE script declaration"),
            (HTML, PAGE.replace(' data-hotel-details-src="./hotel-details-v1.js"', ""), "incomplete on-demand"),
            (APP, APP_CODE + "import('./extra.js');", "undeclared script loader"),
            (APP, APP_CODE + "const extra=document.createElement('script');extra.src='./extra.js';", "unknown dynamic script owner"),
            (APP, APP_CODE.replace("script.src=hotelDetailsAsset;", "script.src='./extra.js';"), "on-demand script assignments changed"),
            (APP, APP_CODE.replace("'anex.svg'", "computeLogo()"), "dynamic operator asset map"),
            (APP, APP_CODE.replace("'assets/photo-unavailable.svg'", "chooseAsset()"), "dynamic/unknown visualResource"),
            (APP, APP_CODE.replace("'assets/photo-unavailable.svg'", "'assets/' + filename"), "dynamic/unknown visualResource"),
            (APP, APP_CODE.replace("'assets/operators/'+file", "'assets/operators/'+other"), "dynamic/unknown visualResource"),
            (APP, APP_CODE.replace("assets/photo-unavailable.svg", "assets/../photo-unavailable.svg"), "nested traversal"),
            ("v2/visual-search/styles.css", "@import './extra.css';", "CSS imports"),
            (RENDERER, PHP + "require_once $unknown;", "unknown PHP include"),
            (ENTRY, "<?php require __DIR__ . '/another.php';", "unknown preview wrapper dependency"),
            (RENDERER, PHP.replace("'./public-bootstrap-v1.js'", "'./unknown.js'"), "public companion declaration changed"),
            (HTML, PAGE.replace("</body>", '<video src="./assets/movie.mp4"></video></body>'), "unknown embedded resource"),
        ]
        for path, content, message in cases:
            with self.subTest(message=message):
                self.write(path, content)
                result = self.run_tool(candidate=self.commit(), ok=False)
                self.assertIn(message, result.stderr)
                self.assertFalse((self.base / "review").exists())
                self.write(path, self.files[path])

    def test_legacy_relative_static_assets_remain_supported(self) -> None:
        self.write(APP, APP_CODE.replace("visualResource('assets/operators/'+file)",
                                        "`./assets/operators/${file}`")
                   .replace("visualResource('assets/photo-unavailable.svg')",
                            "'./assets/photo-unavailable.svg'"))
        self.run_tool(candidate=self.commit())
        manifest = json.loads((self.base / "review/manifest.json").read_text())
        added = {row["path"] for row in manifest["runtime_comparison"]["added"]}
        self.assertTrue({"v2/visual-search/assets/photo-unavailable.svg",
                         "v2/visual-search/assets/operators/anex.svg",
                         "v2/visual-search/assets/operators/coral.png"} <= added)

    def test_shared_loader_has_the_same_closed_client_graph(self) -> None:
        self.run_tool(output=self.base / "legacy-review")
        self.write(APP, SHARED_APP_CODE)
        self.run_tool(candidate=self.commit(), output=self.base / "shared-review")
        legacy = json.loads((self.base / "legacy-review/manifest.json").read_text())
        shared = json.loads((self.base / "shared-review/manifest.json").read_text())
        self.assertEqual(shared["client_graph"], legacy["client_graph"])
        self.assertEqual({row["path"] for row in shared["runtime_comparison"]["added"]},
                         {row["path"] for row in legacy["runtime_comparison"]["added"]})
        lazy = {row["path"] for row in shared["runtime_comparison"]["added"]
                if "on_demand_script" in row["roles"]}
        self.assertEqual(lazy, {"v2/visual-search/hotel-details-v1.js",
                                "v2/visual-search/offer-list-v1.js",
                                "v2/visual-search/flight-picker-ui-v1.js"})

    def test_shared_loader_rejects_unknown_sources_callers_aliases_and_mutations(self) -> None:
        cases = [
            ("unknown asset", SHARED_APP_CODE.replace("()=>hotelDetailsAsset,", "()=>'./extra.js',")),
            ("dynamic source", SHARED_APP_CODE.replace("()=>hotelDetailsAsset,", "chooseSource,")),
            ("dynamic callback", SHARED_APP_CODE.replace("()=>hotelDetailsAsset,", "()=>chooseSource(),")),
            ("source assignment", SHARED_APP_CODE.replace("script.src=source();", "script.src='./extra.js';")),
            ("factory body", SHARED_APP_CODE.replace("source();if(async)", "source(unknown);if(async)")),
            ("factory argument", SHARED_APP_CODE.replace("},true\n);", "},unknown\n);")),
            ("fourth caller", SHARED_APP_CODE + "const extra=createPresentationLoader(owner,source,errors);\n"),
            ("factory alias", SHARED_APP_CODE + "const alias=createPresentationLoader;alias(owner,source,errors);\n"),
            ("escaped factory alias", SHARED_APP_CODE + r"const alias=creat\u0065PresentationLoader;alias();"),
            ("factory reassignment", SHARED_APP_CODE + "createPresentationLoader=unknown;\n"),
            ("duplicate factory", SHARED_APP_CODE + SHARED_FACTORY),
            ("renamed factory", SHARED_APP_CODE.replace("createPresentationLoader", "anotherLoader")),
            ("captured alias", SHARED_APP_CODE.replace("const hotelDetailsAsset=document.head.dataset.hotelDetailsSrc;", "const hotelDetailsAsset=chooseSource();")),
            ("asset reassignment", SHARED_APP_CODE + "hotelDetailsAsset='./extra.js';\n"),
            ("dataset reassignment", SHARED_APP_CODE + "document.head.dataset.flightPickerSrc='./extra.js';\n"),
            ("returned loader alias", SHARED_APP_CODE + "const alias=loadFlightPicker;alias();\n"),
            ("escaped returned loader alias", SHARED_APP_CODE + r"const alias=\u{6c}oadFlightPicker;alias();"),
            ("returned loader reassignment", SHARED_APP_CODE + "loadHotelDetails=unknown;\n"),
            ("returned loader argument", SHARED_APP_CODE + "loadOfferList(unknown);\n"),
            ("extra script assignment", SHARED_APP_CODE + "script.src='./extra.js';\n"),
            ("extra script owner", SHARED_APP_CODE + "const extra=document.createElement('script');extra.src='./extra.js';\n"),
        ]
        for label, code in cases:
            with self.subTest(mutation=label):
                self.assertNotEqual(code, SHARED_APP_CODE)
                self.write(APP, code)
                self.run_tool(candidate=self.commit(), ok=False)
                self.assertFalse((self.base / "review").exists())

    def test_shared_loader_rejects_changed_entry_asset_bindings(self) -> None:
        self.write(APP, SHARED_APP_CODE)
        self.write("v2/visual-search/unknown.js", "const unknown=true;\n")
        for source in ("./offer-list-v1.js", "./hotel-details-v1.js", "./flight-picker-ui-v1.js"):
            with self.subTest(source=source):
                self.write(HTML, PAGE.replace(source, "./unknown.js"))
                result = self.run_tool(candidate=self.commit(), ok=False)
                self.assertIn("shared presentation loader asset bindings changed", result.stderr)
                self.assertFalse((self.base / "review").exists())

    def test_rejects_unsafe_asset_paths(self) -> None:
        for value in ("../../outside.js", "./sub/../app.js", "/absolute.js", "https://example.invalid/app.js"):
            with self.subTest(reference=value):
                self.write(RENDERER, PHP.replace("'./app.js'", repr(value)))
                self.run_tool(candidate=self.commit(), ok=False)
                self.assertFalse((self.base / "review").exists())

    def test_rejects_nonempty_symlink_and_checkout_outputs_without_overwrite(self) -> None:
        nonempty = self.base / "nonempty"
        nonempty.mkdir()
        (nonempty / "keep.txt").write_text("keep")
        self.run_tool(output=nonempty, ok=False)
        self.assertEqual((nonempty / "keep.txt").read_text(), "keep")
        linked = self.base / "linked"
        linked.symlink_to(nonempty, target_is_directory=True)
        self.run_tool(output=linked / "review", ok=False)
        self.assertFalse((nonempty / "review").exists())
        self.run_tool(output=self.repo / "review", ok=False)
        self.assertFalse((self.repo / "review").exists())
        other_checkout = self.base / "another-checkout"
        other_checkout.mkdir()
        subprocess.run(["git", "init", "--quiet", str(other_checkout)], check=True)
        self.run_tool(output=other_checkout / "review", ok=False)
        self.assertFalse((other_checkout / "review").exists())
        metadata = self.base / "separate-metadata"
        subprocess.run(["git", "init", "--quiet", "--separate-git-dir", str(metadata), str(self.repo)], check=True)
        result = self.run_tool(output=metadata / "review", ok=False)
        self.assertIn("outside Git metadata", result.stderr)
        self.assertFalse((metadata / "review").exists())


if __name__ == "__main__":
    unittest.main()
