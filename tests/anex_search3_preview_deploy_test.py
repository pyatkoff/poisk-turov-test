import importlib.util
import json
from pathlib import Path
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("preview_deploy", ROOT / "scripts/diagnostics/anex_search3_preview_deploy.py")
deploy = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(deploy)
SHA = "a" * 40


class PreviewDeploymentTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.repo = self.root / "repo"
        self.repo.mkdir()
        self.write("v2/index.php", """<?php
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$bitrixProlog = $docRoot . '/bitrix/modules/main/include/prolog_before.php';
if ($docRoot !== '' && is_file($bitrixProlog)) require($bitrixProlog);
$siteConf = $docRoot . '/site_conf.php';
if ($docRoot !== '' && is_file($siteConf)) require_once($siteConf);
$metrikaCounter=v2_metrika_counter_id();
?><body><script src="https://app.anytoour.ru/web-consultant/widget.js" async></script></body>""")
        self.write("v2/home-entry-v1.php", """<?php
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$siteConf = $docRoot . '/site_conf.php';
if ($docRoot !== '' && is_file($siteConf)) require_once $siteConf;
?>home""")
        self.write("v2/site-page-shell-v1.php", """<?php
  $docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),'/');
  $siteConf=$docRoot.'/site_conf.php'; if($docRoot!==''&&is_file($siteConf)) require $siteConf;
""")
        self.write("v2/poisk-turov-old/index.php", """<?php
define('V2_PUBLIC_BASE_PATH', '');
require dirname(__DIR__) . '/index.php';
""")
        for name in ("poisk-turov/index.php", "api-anex-search3-preview.php", "preview-lead-disabled.php"):
            self.write("v2/" + name, "<?php // fixture\n")
        self.write("v2/anex-search3-preview-v1.js", "window.fixture = true;\n")
        self.write("v2/site-path-v1.php", (ROOT / "v2/site-path-v1.php").read_text())
        self.write("app/integrations/anex-search-mapping-registry.php", "<?php // registry\n")
        self.write("scripts/build/search3_site_preview_isolation.py", (ROOT / "scripts/build/search3_site_preview_isolation.py").read_text())

    def write(self, relative, content):
        path = self.repo / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)

    def test_payload_isolates_copy_without_touching_source(self):
        original = (self.repo / "v2/index.php").read_bytes()
        self.write("v2/api-v2.php", "PRODUCTION_API")
        self.write("v2/lead-adapter-v2.php", "PRODUCTION_LEADS")
        self.write("v2/.env", "SECRET_TOKEN")
        self.write("v2/_preview/other/index.php", "OTHER_PREVIEW")
        payload = self.root / "payload"
        report = deploy.build_payload(self.repo, payload, SHA)
        self.assertEqual(original, (self.repo / "v2/index.php").read_bytes())
        for forbidden in ("api-v2.php", "lead-adapter-v2.php", ".env", "_preview"):
            self.assertFalse((payload / forbidden).exists())
        search = (payload / "search-page-v2.php").read_text()
        self.assertIn("$metrikaCounter=0;", search)
        self.assertNotIn("bitrixProlog", search)
        self.assertNotIn("web-consultant", search)
        self.assertIn(deploy.PREVIEW_ROUTE + "anex-search3-preview-v1.js", search)
        self.assertIn("#^(/_preview/search3-anex-candidate)(?:/|$)#", (payload / "site-path-v1.php").read_text())
        self.assertIn("#^(/_preview/search3-site-candidate)(?:/|$)#", (self.repo / "v2/site-path-v1.php").read_text())
        self.assertEqual(report["source_sha"], SHA)
        self.assertEqual(report["mapping_scope"], "preview")
        self.assertFalse(report["production_entry_changes"])
        self.assertFalse(report["production_lead_delivery"])
        serialized = json.dumps(report)
        self.assertNotIn("SECRET_TOKEN", serialized)
        self.assertNotIn("ANEX_API_TOKEN", (payload / ".anex-private.php").read_text())
        self.assertIn("Require all denied", (payload / "app/.htaccess").read_text())

    def test_symbolic_links_and_existing_payload_are_rejected(self):
        (self.repo / "v2/linked.js").symlink_to(self.repo / "v2/anex-search3-preview-v1.js")
        with self.assertRaisesRegex(ValueError, "symbolic link"):
            deploy.build_payload(self.repo, self.root / "payload", SHA)
        with self.assertRaisesRegex(ValueError, "new directory"):
            deploy.build_payload(self.repo, self.root / "payload", SHA)

    def test_source_drift_and_invalid_sha_fail_before_deployment(self):
        with self.assertRaisesRegex(ValueError, "full checked source SHA"):
            deploy.build_payload(self.repo, self.root / "bad-sha", "main")
        self.write("v2/index.php", "<?php // changed source\n")
        with self.assertRaisesRegex(ValueError, "source drift"):
            deploy.build_payload(self.repo, self.root / "drift", SHA)

    def test_ssh_script_has_only_owned_target_and_retains_backup(self):
        script = deploy.remote_script(SHA + "-" + "b" * 12)
        self.assertIn('target="$project/_preview/search3-anex-candidate"', script)
        self.assertNotIn("search3-site-candidate", script)
        self.assertNotIn("rm -", script)
        self.assertIn('test -f "$target/anex-preview-manifest.json"', script)
        self.assertIn('mv "$target" "$backup"', script)
        self.assertIn('mv "$backup" "$target"', script)
        self.assertIn('chmod 600 "$work/search3-preview.php"', script)
        self.assertIn('tar -xzf - -C "$work"', script)
        with self.assertRaises(ValueError):
            deploy.remote_script("../production")

    def test_private_config_uses_literal_encoded_token(self):
        token = "secret'$(echo danger)\nline"
        source = deploy.private_config(token, SHA)
        self.assertNotIn(token, source)
        self.assertIn("base64_decode('", source)
        self.assertIn("ANYTOUR_ANEX_PREVIEW_ENABLED', true", source)
        with self.assertRaises(ValueError):
            deploy.private_config("", SHA)


if __name__ == "__main__":
    unittest.main()
