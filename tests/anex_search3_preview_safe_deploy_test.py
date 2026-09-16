import base64
import importlib.util
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest import mock


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_search3_preview_deploy_safe",
    ROOT / "scripts/diagnostics/anex_search3_preview_deploy_safe.py",
)
safe = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(safe)
SHA = "a" * 40
RELEASE = SHA + "-" + "b" * 12


class SafePreviewDeploymentTest(unittest.TestCase):
    def test_private_config_contains_both_encoded_tokens_without_raw_values(self):
        api = "fixture-api-token"
        b2b = "fixture-b2b-token_12345"
        text = safe.private_config(api, SHA, b2b)
        self.assertNotIn(api, text)
        self.assertNotIn(b2b, text)
        self.assertIn(base64.b64encode(api.encode()).decode(), text)
        self.assertIn(base64.b64encode(b2b.encode()).decode(), text)
        self.assertEqual(text.count("ANEX_B2B_TOKEN"), 1)
        with tempfile.TemporaryDirectory() as directory:
            config = Path(directory) / "config.php"
            config.write_text(text, encoding="utf-8")
            check = subprocess.run(
                [
                    "php", "-d", "display_errors=0", "-d", "log_errors=0", "-r",
                    "require $argv[1];"
                    "if (!defined('ANEX_B2B_TOKEN') || ANEX_B2B_TOKEN !== 'fixture-b2b-token_12345') exit(2);"
                    "if (!defined('ANEX_API_TOKEN') || ANEX_API_TOKEN !== 'fixture-api-token') exit(3);"
                    "if (!defined('ANEX_PREVIEW_SOURCE_SHA') || ANEX_PREVIEW_SOURCE_SHA !== '" + SHA + "') exit(4);",
                    str(config),
                ],
                text=True,
                capture_output=True,
                timeout=30,
            )
            self.assertEqual(check.returncode, 0, check.stderr)
            self.assertEqual(check.stdout, "")

    def test_missing_or_invalid_github_b2b_secret_is_rejected(self):
        for token in ("", " Bearer-token", "Bearer fixture", "fixture token", "fixture\nvalue", "x" * 16385):
            with self.subTest(token=token[:30]):
                with self.assertRaisesRegex(ValueError, "ANEX B2B token"):
                    safe.private_config("fixture-api-token", SHA, token)

    def test_main_requires_and_removes_b2b_secret_before_base_publisher_runs(self):
        seen = {}

        def fake_main():
            seen["env_has_b2b"] = "ANEX_B2B_TOKEN" in os.environ
            seen["config"] = safe.base.private_config("fixture-api-token", SHA)
            return 0

        with mock.patch.dict(os.environ, {"ANEX_B2B_TOKEN": "fixture-b2b-token_12345"}, clear=False):
            with mock.patch.object(safe.base, "main", side_effect=fake_main):
                self.assertEqual(safe.main(), 0)
                self.assertNotIn("ANEX_B2B_TOKEN", os.environ)
        self.assertFalse(seen["env_has_b2b"])
        self.assertIn("ANEX_B2B_TOKEN", seen["config"])
        self.assertIsNone(safe._B2B_TOKEN)
        self.assertIs(safe.base.private_config, safe._BASE_PRIVATE_CONFIG)

    def test_main_fails_before_base_publisher_when_b2b_secret_missing(self):
        with mock.patch.dict(os.environ, {}, clear=False):
            os.environ.pop("ANEX_B2B_TOKEN", None)
            with mock.patch.object(safe.base, "main") as base_main:
                with self.assertRaisesRegex(ValueError, "ANEX B2B token"):
                    safe.main()
                base_main.assert_not_called()

    def test_remote_script_keeps_isolated_target_and_rollback_without_server_token_copy_into_new_config(self):
        script = safe.remote_script(RELEASE)
        self.assertIn('target="$project/_preview/search3-anex-candidate"', script)
        self.assertNotIn('target="$project/_preview/search3-site-candidate"', script)
        self.assertNotIn("ANEX_PRIVATE_CONFIG_READY", script)
        self.assertNotIn("ANEX_B2B_TOKEN", script)
        self.assertIn('cp -p "$private/search3-preview.php" "$work/previous-search3-preview.php"', script)
        switch_at = script.index('mv "$work/search3-preview.php" "$private/search3-preview.php"')
        target_at = script.index('mv "$stage" "$target"')
        self.assertLess(switch_at, target_at)


if __name__ == "__main__":
    unittest.main()
