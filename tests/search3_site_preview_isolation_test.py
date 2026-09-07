"""Check transformations against the real tracked runtime without editing it."""
import importlib.util
from pathlib import Path
import shutil
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("isolation", ROOT / "scripts/build/search3_site_preview_isolation.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class IsolationTest(unittest.TestCase):
    def test_exact_transform_and_source_drift(self):
        with tempfile.TemporaryDirectory() as tmp:
            payload = Path(tmp)
            for name in ("index.php", "home-entry-v1.php", "site-page-shell-v1.php", "poisk-turov-old/index.php"):
                target = payload / name
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(ROOT / "v2" / name, target)
            shutil.copyfile(payload / "index.php", payload / "search-page-v2.php")
            shutil.copyfile(payload / "home-entry-v1.php", payload / "index.php")
            module.isolate(payload)
            search = (payload / "search-page-v2.php").read_text()
            for forbidden in ("$bitrixProlog", "$siteConf", "v2_metrika_counter_id()", "web-consultant/widget.js"):
                self.assertNotIn(forbidden, search)
            self.assertIn("$metrikaCounter=0;", search)
            self.assertIn("v2_public_path('api-v2.php')", search)
            self.assertIn("v2_public_path('lead-adapter-v2.php')", search)
            for name in ("index.php", "home-entry-v1.php", "site-page-shell-v1.php"):
                self.assertNotIn("$siteConf", (payload / name).read_text())
            legacy = (payload / "poisk-turov-old/index.php").read_text()
            self.assertIn("'/search-page-v2.php'", legacy)
            self.assertIn("preview-lead-disabled.php", legacy)
            self.assertIn("V2_SEARCH3_PRESENTATION', false", legacy)
            with self.assertRaises(ValueError):
                module.isolate(payload)


if __name__ == "__main__":
    unittest.main()
