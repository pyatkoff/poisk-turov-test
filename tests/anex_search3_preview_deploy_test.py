import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("preview_deploy", ROOT / "scripts/diagnostics/anex_search3_preview_deploy.py")
deploy = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(deploy)
SHA = "a" * 40
RUNTIME_NAMES = (
    "andromeda-normalizer.php",
    "andromeda-hotel-resolver.php",
    "andromeda-search.php",
    "andromeda-hotel-observations.php",
    "andromeda-offer-store.php",
)


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
        for name in ("poisk-turov/index.php", "api-anex-search3-preview.php",
                     "api-andromeda-search3-preview.php", "preview-lead-disabled.php"):
            self.write("v2/" + name, "<?php // fixture\n")
        self.write("v2/anex-search3-preview-v1.js", "window.fixture = true;\n")
        self.write("v2/site-path-v1.php", (ROOT / "v2/site-path-v1.php").read_text())
        self.write("app/integrations/anex-search-mapping-registry.php", "<?php // registry\n")
        self.write("app/integrations/anex-additional-prices-client.php", "<?php // APD client\n")
        self.write("scripts/build/search3_site_preview_isolation.py", (ROOT / "scripts/build/search3_site_preview_isolation.py").read_text())

    def write(self, relative, content):
        path = self.repo / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)

    def runtime_fixture(self, case, missing=None, current=None, linked=None, private=True, stage_private=False):
        home = self.root / case
        project = home / "www/anytoour.ru"
        target = project / "_preview/search3-anex-candidate/app/integrations"
        stage = project / "_preview" / (".search3-anex-" + SHA + "-" + "b" * 12)
        stage_runtime = stage / "app/integrations"
        target.mkdir(parents=True)
        stage_runtime.mkdir(parents=True)
        for name in RUNTIME_NAMES:
            if name == missing or name == linked:
                continue
            (target / name).write_text("<?php // installed " + name + "\n")
        if linked is not None:
            real = target / (linked + ".real")
            real.write_text("<?php // linked source\n")
            (target / linked).symlink_to(real)
        if current is not None:
            (stage_runtime / current).write_text("<?php // current source wins\n")
        if private:
            config = target.parent.parent / ".andromeda-private.php"
            config.write_text("<?php return ['enabled'=>true,'token'=>'SERVER_ONLY_PRIVATE_SENTINEL'];\n")
            config.chmod(0o600)
        if stage_private:
            injected = stage / ".andromeda-private.php"
            injected.write_text("<?php return ['enabled'=>true,'token'=>'PAYLOAD_MUST_NOT_OWN_THIS'];\n")
            injected.chmod(0o600)
        return home, project, stage, target, stage_runtime

    def run_runtime_preserver(self, home, project, stage):
        env = os.environ.copy()
        env["HOME"] = str(home)
        return subprocess.run(
            ["php", str(ROOT / "scripts/diagnostics/anex_review_owner_preserve.php"), str(project), str(stage)],
            text=True, capture_output=True, env=env, timeout=30)

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
        access = (payload / ".htaccess").read_text()
        self.assertIn('<Files "api-anex-search3-preview.php">', access)
        self.assertIn('<Files "api-andromeda-search3-preview.php">', access)
        self.assertTrue((payload / "app/integrations/anex-additional-prices-client.php").is_file())
        self.assertFalse((payload / ".andromeda-private.php").exists())

    def test_provider_runtime_dependencies_are_required(self):
        (self.repo / "app/integrations/anex-additional-prices-client.php").unlink()
        with self.assertRaisesRegex(ValueError, "runtime dependency"):
            deploy.build_payload(self.repo, self.root / "missing-apd", SHA)
        self.write("app/integrations/anex-additional-prices-client.php", "<?php // APD client\n")
        (self.repo / "v2/api-andromeda-search3-preview.php").unlink()
        with self.assertRaisesRegex(ValueError, "runtime dependency"):
            deploy.build_payload(self.repo, self.root / "missing-andromeda", SHA)

    def test_installed_andromeda_runtime_is_preserved_but_current_source_wins(self):
        home, project, stage, target, stage_runtime = self.runtime_fixture("runtime-ok", current=RUNTIME_NAMES[0])
        current = (stage_runtime / RUNTIME_NAMES[0]).read_bytes()
        result = self.run_runtime_preserver(home, project, stage)
        self.assertEqual(result.returncode, 0, result.stderr)
        value = json.loads(result.stdout)
        self.assertEqual(value["owner_panel"], "not_installed")
        self.assertEqual(value["andromeda_private_config"], {"status": "preserved", "mode": "0600"})
        overlay = value["runtime_overlay"]
        self.assertEqual(overlay["status"], "preserved")
        self.assertEqual(set(overlay["sha256"]), set(RUNTIME_NAMES))
        self.assertEqual(overlay["sources"][RUNTIME_NAMES[0]], "current_source")
        self.assertEqual((stage_runtime / RUNTIME_NAMES[0]).read_bytes(), current)
        for name in RUNTIME_NAMES[1:]:
            self.assertEqual(overlay["sources"][name], "installed_preview")
            self.assertEqual((stage_runtime / name).read_bytes(), (target / name).read_bytes())
            self.assertEqual(overlay["sha256"][name], hashlib.sha256((target / name).read_bytes()).hexdigest())
            self.assertEqual(overlay["bytes"][name], (target / name).stat().st_size)

    def test_andromeda_private_config_is_server_only_and_preserved_without_provenance_leak(self):
        home, project, stage, target, _ = self.runtime_fixture("private-preserve")
        source = target.parent.parent / ".andromeda-private.php"
        secret = "<?php return ['enabled'=>true,'token'=>'VERY_PRIVATE_SENTINEL_123'];\n"
        source.write_text(secret)
        source.chmod(0o644)
        result = self.run_runtime_preserver(home, project, stage)
        self.assertEqual(result.returncode, 0, result.stderr)
        destination = stage / ".andromeda-private.php"
        self.assertEqual(destination.read_text(), secret)
        self.assertEqual(destination.stat().st_mode & 0o777, 0o600)
        value = json.loads(result.stdout)
        self.assertEqual(value["andromeda_private_config"], {"status": "preserved", "mode": "0600"})
        self.assertNotIn("VERY_PRIVATE_SENTINEL_123", result.stdout)
        self.assertNotIn(hashlib.sha256(secret.encode()).hexdigest(), result.stdout)
        self.assertNotIn("bytes", json.dumps(value["andromeda_private_config"]))
        self.assertNotIn("sha256", json.dumps(value["andromeda_private_config"]))

    def test_absent_andromeda_private_config_stays_fail_closed_and_payload_owned_copy_is_rejected(self):
        home, project, stage, _, _ = self.runtime_fixture("private-absent", private=False)
        result = self.run_runtime_preserver(home, project, stage)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(json.loads(result.stdout)["andromeda_private_config"], {"status": "not_installed", "mode": None})
        self.assertFalse((stage / ".andromeda-private.php").exists())

        home, project, stage, _, _ = self.runtime_fixture("private-injected", stage_private=True)
        injected = self.run_runtime_preserver(home, project, stage)
        self.assertNotEqual(injected.returncode, 0)
        self.assertEqual(injected.stderr, "OWNER_PANEL_PRESERVATION_FAILED\n")

    def test_missing_or_linked_installed_andromeda_runtime_is_rejected(self):
        home, project, stage, _, _ = self.runtime_fixture("runtime-missing", missing=RUNTIME_NAMES[-1])
        missing = self.run_runtime_preserver(home, project, stage)
        self.assertNotEqual(missing.returncode, 0)
        self.assertEqual(missing.stderr, "OWNER_PANEL_PRESERVATION_FAILED\n")
        home, project, stage, _, _ = self.runtime_fixture("runtime-linked", linked=RUNTIME_NAMES[-1])
        linked = self.run_runtime_preserver(home, project, stage)
        self.assertNotEqual(linked.returncode, 0)
        self.assertEqual(linked.stderr, "OWNER_PANEL_PRESERVATION_FAILED\n")

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
        self.assertIn('test -f "$target/api-anex-search3-preview.php"', script)
        self.assertIn('test -f "$target/api-andromeda-search3-preview.php"', script)
        self.assertIn('test -f "$target/app/integrations/anex-additional-prices-client.php"', script)
        self.assertIn('owner-panel-preserve.php', script)
        self.assertLess(script.index('owner-panel-preserve.php'), script.index('mv "$stage" "$target"'))
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
