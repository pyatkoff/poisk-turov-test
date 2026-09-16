import base64
import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest


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
    def run_preserver(self, old_text, new_text=None, linked=False):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            old = root / "old.php"
            new = root / "new.php"
            if linked:
                real = root / "old-real.php"
                real.write_text(old_text, encoding="utf-8")
                old.symlink_to(real)
            else:
                old.write_text(old_text, encoding="utf-8")
            if new_text is None:
                new_text = safe.base.private_config("fixture-api-token", SHA)
            new.write_text(new_text, encoding="utf-8")
            before = new.read_bytes()
            result = subprocess.run(
                ["php", "-d", "display_errors=0", "-d", "log_errors=0", "-r", safe.PRESERVE_PHP, str(old), str(new)],
                text=True,
                capture_output=True,
                timeout=30,
            )
            after = new.read_bytes()
            return result, before, after

    def test_remote_script_preserves_b2b_before_private_config_switch(self):
        script = safe.remote_script(RELEASE)
        self.assertIn('target="$project/_preview/search3-anex-candidate"', script)
        self.assertNotIn('target="$project/_preview/search3-site-candidate"', script)
        self.assertNotIn("rm -", script)
        self.assertIn('test -f "$private/search3-preview.php"', script)
        self.assertIn('test ! -L "$private/search3-preview.php"', script)
        self.assertIn("ANEX_PRIVATE_CONFIG_READY", script)
        preserve_at = script.index("private-config-preserve-result.txt")
        switch_at = script.index('mv "$work/search3-preview.php" "$private/search3-preview.php"')
        target_at = script.index('mv "$stage" "$target"')
        self.assertLess(preserve_at, switch_at)
        self.assertLess(switch_at, target_at)

    def test_server_only_preserver_retains_b2b_without_outputting_secret(self):
        token = "fixture-b2b-token_12345"
        old = "<?php\ndefine('ANEX_B2B_TOKEN', '" + token + "');\n"
        result, _, after = self.run_preserver(old)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stdout, "ANEX_PRIVATE_CONFIG_READY\n")
        self.assertNotIn(token, result.stdout + result.stderr)
        text = after.decode("utf-8")
        self.assertNotIn(token, text)
        self.assertIn(base64.b64encode(token.encode()).decode(), text)
        with tempfile.TemporaryDirectory() as directory:
            config = Path(directory) / "config.php"
            config.write_bytes(after)
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

    def test_missing_invalid_or_linked_existing_b2b_fails_without_mutating_new_config(self):
        cases = [
            ("<?php\ndefine('ANEX_API_TOKEN', 'old');\n", False),
            ("<?php\ndefine('ANEX_B2B_TOKEN', 'Bearer fixture');\n", False),
            ("<?php\ndefine('ANEX_B2B_TOKEN', 'fixture token');\n", False),
            ("<?php\ndefine('ANEX_B2B_TOKEN', 'fixture-token');\n", True),
        ]
        for old, linked in cases:
            with self.subTest(old=old, linked=linked):
                result, before, after = self.run_preserver(old, linked=linked)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(result.stdout, "")
                self.assertEqual(before, after)

    def test_preserver_rejects_source_switch_drift(self):
        with self.assertRaisesRegex(ValueError, "private config switch drift"):
            safe._inject_preservation("set -eu\necho changed\n")


if __name__ == "__main__":
    unittest.main()
