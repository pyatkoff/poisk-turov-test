#!/usr/bin/env python3
"""Execute guarded Search3 PHP entries in isolated fixtures, with network disabled.

Source-only checks do not replace this suite. Missing PHP returns 77, not success.
No production configuration, supplier operation or application is used.
"""
from __future__ import annotations

import hashlib
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php")
BASELINE = ROOT / "tests/fixtures/search3-preview-entry-before-public.php"
BOOT = """
$_SERVER['SCRIPT_NAME'] = $argv[2];
$_SERVER['HTTP_HOST'] = 'anytoour.ru';
$_SERVER['DOCUMENT_ROOT'] = $argv[4];
parse_str($argv[3], $_GET);
register_shutdown_function(function () {
    fwrite(STDERR, json_encode(['status'=>http_response_code() ?: 200,
        'site_config_loaded'=>!empty($GLOBALS['TEST_SITE_CONFIG_LOADED']),
        'counter_loaded'=>!empty($GLOBALS['TEST_COUNTER_LOADED'])]));
});
require $argv[1];
"""


class Page(HTMLParser):
    def __init__(self, html: str):
        super().__init__(convert_charrefs=True)
        self.scripts, self.resources, self.configs, self.nodes = [], [], [], []
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        self.nodes.append((tag, values))
        if tag == "script":
            self.scripts.append(values.get("src"))
        for key in ("src", "href", "data-offer-list-src", "data-hotel-details-src", "data-flight-picker-src"):
            if key in values:
                self.resources.append(values[key])
        if "data-search3-public-config" in values:
            self.configs.append(json.loads(values["data-search3-public-config"]))


@unittest.skipUnless(PHP, "A real PHP CLI is unavailable; entry rendering is not verified")
class PublicEntryTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix="search3-public-entry-")
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name) / "site"
        self.visual = self.root / "visual-search"
        self.visual.mkdir(parents=True)
        self.docroot = Path(self.tmp.name) / "production-document-root"
        self.docroot.mkdir()
        for name in ("index.php", "index.html", "entry-v1.php", "public-entry-v1.php"):
            shutil.copyfile(ROOT / "v2/visual-search" / name, self.visual / name)
        (self.root / "poisk-turov").mkdir()
        shutil.copyfile(self.visual / "public-entry-v1.php", self.root / "poisk-turov/index.php")
        html = (self.visual / "index.html").read_text()
        php = (self.visual / "entry-v1.php").read_text()
        refs = set(re.findall(r'''(?:src|href|data-[a-z-]+-src)="(\.\.?/[^"?]+)"''', html))
        refs.update(re.findall(r"'(\.\.?/[^']+\.js)'", php))
        for ref in refs:
            target = self.visual / ref
            target.parent.mkdir(parents=True, exist_ok=True)
            if not target.exists():
                target.write_text("fixture resource: " + ref)
        # These are test-only dependencies, never the developer's private configuration.
        for name in ("site-header-v2.php", "phone-value.php", "asset-version-v1.php", "site-path-v1.php"):
            shutil.copyfile(ROOT / "v2" / name, self.root / name)
        (self.root / "analytics-config.php").write_text("<?php function v2_metrika_counter_id(){ $GLOBALS['TEST_COUNTER_LOADED']=true; return 12345; }")
        shutil.copyfile(ROOT / "v2/seo-config.php", self.root / "seo-config.php")
        (self.docroot / "site_conf.php").write_text("<?php $GLOBALS['TEST_SITE_CONFIG_LOADED']=true; $params=['SEO_INDEXABLE'=>true,'SEO_INDEXABLE_PATHS'=>['/poisk-turov/']];")
        prolog = self.docroot / "bitrix/modules/main/include/prolog_before.php"
        prolog.parent.mkdir(parents=True)
        prolog.write_text("<?php throw new RuntimeException('A second Bitrix bootstrap is forbidden');")
        self.profile()

    def profile(self, profile="review", scenario="family", **extra):
        (self.visual / "public-build.json").write_text(json.dumps({"schemaVersion": 1, "profile": profile, "scenario": scenario, **extra}))

    def render(self, entry=None, route="/poisk-turov/", query=""):
        result = subprocess.run([
            PHP, "-d", "allow_url_fopen=0", "-d", "allow_url_include=0", "-d", "display_errors=stderr",
            "-r", BOOT, str(entry or self.root / "poisk-turov/index.php"), route, query, str(self.docroot),
        ], text=True, capture_output=True, timeout=10)
        self.assertEqual(result.returncode, 0, result.stderr)
        return result.stdout, json.loads(result.stderr)

    def test_review_profile_controls_graph_and_visible_disclosure_not_query(self):
        trap = "<?php throw new RuntimeException('Review must not load production configuration');"
        for target in (self.root / "analytics-config.php", self.root / "seo-config.php", self.docroot / "site_conf.php"):
            target.write_text(trap)
        (self.root / "data").mkdir()
        (self.root / "data/hotel-popularity-v1.php").write_text(trap)
        for route, prefix in (("/poisk-turov/", ""), ("/poisk-turov/index.php", ""),
                              ("/_preview/search3-site-candidate/poisk-turov/", "/_preview/search3-site-candidate")):
            with self.subTest(route=route):
                html, status = self.render(route=route, query="profile=live&scenario=live&leadApi=/lead-adapter-v2.php&from=2&origin=Москва&country=1")
                self.assertEqual(status, {"status": 200, "site_config_loaded": False, "counter_loaded": False})
                page = Page(html)
                self.assertEqual(len(page.configs), 1)
                cfg = page.configs[0]
                self.assertEqual((cfg["profile"], cfg["scenario"], cfg["metrikaCounter"]), ("review", "family", 0))
                self.assertEqual(cfg["resourceBase"], prefix + "/visual-search/")
                self.assertEqual(cfg["leadApi"], "/_preview/search3-next-candidate/preview-lead-disabled.php")
                self.assertEqual(len(page.scripts), 11)
                self.assertTrue(page.scripts[0].startswith(prefix + "/visual-search/public-config-v1.js?v="))
                self.assertTrue(page.scripts[-1].startswith(prefix + "/visual-search/app.js?v="))
                self.assertFalse(any("analytics" in src or "live-bridge" in src for src in page.scripts))
                self.assertIn("Проверочная версия: сохранённые/тестовые данные, заявки не отправляются.", html)
                self.assertNotIn('id="fixture-scenario"', html)
                self.assertNotIn('class="private-badge"', html)
                self.assertNotIn("<base", html.lower())
                self.assertFalse(any(ref.startswith(("./", "../")) for ref in page.resources))
                self.assertEqual(html.count('type="button" data-action="top" aria-current="page"'), 2)
                self.assertEqual(len(page.scripts), len(set(page.scripts)))

    def test_canonical_header_navigation_and_search_controls(self):
        for profile, prefix in (("live", ""), ("review", "/_preview/search3-site-candidate")):
            with self.subTest(profile=profile):
                self.profile(profile)
                html, status = self.render(route=prefix + "/poisk-turov/")
                self.assertEqual(status["status"], 200)
                self.assertNotIn("data-next-inline-pickers", html)
                page = Page(html)
                headers = [attrs for tag, attrs in page.nodes if tag == "header"]
                self.assertEqual([attrs.get("class") for attrs in headers], ["at-global-header header"])
                self.assertNotIn("data-site-header-slot", html)
                self.assertNotIn("data-anytour-favicon-bootstrap", html)
                self.assertNotIn('class="at-global-header__cta"', html, "Search3 must not repeat Find above its form")
                for href in ("/country/", "/hot/", "/rb/", "/how-to-buy/", "/contacts/"):
                    self.assertEqual(html.count('href="' + prefix + href + '"'), 2)
                current = [attrs for tag, attrs in page.nodes if tag == "button" and attrs.get("aria-current") == "page"]
                self.assertEqual(len(current), 2)
                self.assertTrue(all(attrs.get("data-action") == "top" and attrs.get("type") == "button" for attrs in current))
                self.assertEqual(html.count('href="tel:88001006150"'), 2)
                self.assertEqual(html.count('href="' + prefix + '/" aria-label="AnyTour —'), 2, "header and footer logos go home")
                self.assertNotIn('class="compact-brand"', html)
                for hook in ("compact-route", "compact-details", "favorites-nav", "selected-tour-nav"):
                    self.assertEqual(html.count('id="' + hook + '"'), 1)
                styles = [attrs["href"] for tag, attrs in page.nodes if tag == "link" and attrs.get("rel") == "stylesheet"]
                self.assertEqual(len(styles), 3)
                self.assertTrue(styles[-1].startswith(prefix + "/site-header-v2.css?v="))

    def test_ordinary_header_retains_routes_phone_cta_and_preview_prefix(self):
        source = """
$_SERVER['SCRIPT_NAME']=$argv[2];
require $argv[1];
v2_render_site_header('8 (800) 100-61-50', '+78001006150', $argv[3]);
"""
        for prefix in ("", "/_preview/search3-site-candidate"):
            result = subprocess.run([PHP, "-d", "allow_url_fopen=0", "-d", "allow_url_include=0", "-r", source,
                                     str(self.root / "site-header-v2.php"), prefix + "/country/turkey/", "/country/turkey/"],
                                    text=True, capture_output=True, timeout=10)
            self.assertEqual(result.returncode, 0, result.stderr)
            html = result.stdout
            for href in ("/country/", "/hot/", "/rb/", "/how-to-buy/", "/contacts/"):
                self.assertEqual(html.count('href="' + prefix + href + '"'), 2)
            self.assertEqual(html.count('href="' + prefix + '/poisk-turov/"'), 3, "two menu routes and the quick CTA")
            self.assertEqual(html.count('href="' + prefix + '/country/" aria-current="page"'), 2)
            self.assertEqual(html.count('class="at-global-header__cta"'), 1)
            self.assertEqual(html.count('href="tel:+78001006150"'), 2)
            self.assertNotIn('data-action="top"', html)
            self.assertIn('data-anytour-favicon-bootstrap', html)

    def test_live_capability_is_explicit_and_reuses_existing_helpers(self):
        self.profile("live")
        html, status = self.render(query="profile=review&scenario=family&leadApi=/lead-adapter-v2.php")
        self.assertEqual(status, {"status": 200, "site_config_loaded": True, "counter_loaded": True})
        page = Page(html); cfg = page.configs[0]
        self.assertEqual((cfg["profile"], cfg["leadApi"], cfg["metrikaCounter"]),
                         ("live", "/_preview/search3-next-candidate/preview-lead-disabled.php", 12345))
        self.assertEqual(cfg["anexApi"], "/_preview/search3-anex-candidate/api-anex-search3-preview.php")
        self.assertEqual(len(page.scripts), 22)
        self.assertEqual(sum(src.startswith("/analytics-v4.js?") for src in page.scripts), 1)
        self.assertFalse(any("fixture-data" in src or "preview-lead.js" in src or "bundle-v1" in src for src in page.scripts))
        self.assertNotIn("Проверочная версия:", html)
        self.assertIn('href="https://anytoour.ru/poisk-turov/"', html)
        _, denied = self.render(route="/_preview/search3-site-candidate/poisk-turov/")
        self.assertEqual(denied, {"status": 403, "site_config_loaded": False, "counter_loaded": False})

    def test_guards_and_invalid_builds_fail_before_loading_production_dependencies(self):
        cases = [(self.root / "poisk-turov/index.php", "/other/"),
                 (self.visual / "public-entry-v1.php", "/visual-search/public-entry-v1.php"),
                 (self.visual / "index.php", "/poisk-turov/"),
                 (self.visual / "entry-v1.php", "/visual-search/entry-v1.php")]
        for entry, route in cases:
            _, status = self.render(entry, route)
            self.assertEqual(status, {"status": 403, "site_config_loaded": False, "counter_loaded": False})
        for build in ("{}", '{"schemaVersion":1,"profile":"other","scenario":"family"}',
                      '{"schemaVersion":1,"profile":"review","scenario":"live"}',
                      '{"schemaVersion":1,"profile":"review","scenario":"family","api":"/other"}', "broken"):
            (self.visual / "public-build.json").write_text(build)
            _, status = self.render(query="profile=live&scenario=family")
            self.assertEqual(status, {"status": 503, "site_config_loaded": False, "counter_loaded": False})
        (self.visual / "public-build.json").unlink()
        _, status = self.render(); self.assertEqual(status["status"], 503)

    def test_review_missing_resource_fails_closed_and_hashes_served_bytes(self):
        html, status = self.render()
        self.assertEqual(status["status"], 200)
        asset = self.visual / "app.js"
        version = hashlib.sha256(asset.read_bytes()).hexdigest()[:12]
        self.assertIn('/visual-search/app.js?v=' + version, html)
        asset.unlink()
        html, status = self.render()
        self.assertEqual(status["status"], 503)
        self.assertNotIn("<script", html)

    def test_preview_retains_body_and_graph_with_shared_header_and_next_form_hook(self):
        self.assertEqual(hashlib.sha256(BASELINE.read_bytes()).hexdigest(), "159060195fa7a41e6a95f5e8e0fb302f04c6c94c9e49b5c1845eee9210626454",
                         "retained entry fixture is exact 189d1eac source, not a reimplementation")
        baseline = Path(self.tmp.name) / "baseline"
        shutil.copytree(self.root, baseline)
        shutil.copyfile(BASELINE, baseline / "visual-search/index.php")
        for query in ("", "scenario=family", "scenario=snapshot", "scenario=recorded"):
            before, old_status = self.render(baseline / "visual-search/index.php", "/_preview/search3-next-candidate/visual-search/index.php", query)
            after, new_status = self.render(self.visual / "index.php", "/_preview/search3-next-candidate/visual-search/index.php", query)
            self.assertEqual(new_status, old_status)
            normalized, count = re.subn(r'<header class="at-global-header header">.*?</header>',
                                        '<header class="header" data-site-header-slot></header>', after, count=1, flags=re.S)
            self.assertEqual(count, 1)
            form = '<form id="search-form" class="search-box">'
            next_form = '<form id="search-form" class="search-box" data-next-inline-pickers>'
            self.assertEqual(before.count(form), 1)
            self.assertEqual(after.count(next_form), 1)
            expected = before.replace(form, next_form, 1)
            self.assertEqual(normalized, expected, "unexpected preview markup change for " + query)


if __name__ == "__main__":
    result = unittest.main(verbosity=2, exit=False).result
    raise SystemExit(77 if result.skipped else (0 if result.wasSuccessful() else 1))
