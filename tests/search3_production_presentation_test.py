"""Verify Search3 activation without changing runtime, lead or analytics contracts."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = json.loads((ROOT / 'docs/project/search3-production-import.json').read_text())


class Search3ProductionPresentationTest(unittest.TestCase):
    def test_reviewed_assets_and_protected_runtime(self):
        self.assertEqual(len(MANIFEST['assets']), 8)
        for name, values in MANIFEST['assets'].items():
            self.assertEqual(hashlib.sha256((ROOT / 'v2' / name).read_bytes()).hexdigest(), values['productionSha256'], name)
        for name, digest in MANIFEST['protectedSha256'].items():
            self.assertEqual(hashlib.sha256((ROOT / 'v2' / name).read_bytes()).hexdigest(), digest, name)

    def test_preview_controls_are_absent_from_production_assets(self):
        for name in MANIFEST['assets']:
            source = (ROOT / 'v2' / name).read_text()
            for marker in ('search3:preview-lead-state', '__search3CandidateNativeMatchMedia', '?lead=disabled', 'PREVIEW_LEAD_DISABLED'):
                self.assertNotIn(marker, source, name)

    def test_supplier_party_is_not_mislabeled_as_search_input(self):
        source = (ROOT / 'v2' / 'search3-results-filters-v1.js').read_text()
        self.assertIn('Состав размещения у туроператора', source)
        self.assertIn('Для выбранного варианта', source)
        self.assertNotIn('Состав поездки из поиска', source)

    @unittest.skipUnless(shutil.which('php'), 'PHP rendering requires the existing CI runtime')
    def test_canonical_and_compatibility_rendering(self):
        def render(host, entry, enabled=None):
            code = "define('METRIKA_COUNTER_ID',123456);"
            code += "$_SERVER['HTTP_HOST']=" + json.dumps(host) + ";"
            code += "$_SERVER['SCRIPT_NAME']='/poisk-turov/index.php';"
            code += "$_SERVER['REQUEST_URI']='/poisk-turov/';"
            if enabled is not None:
                code += "define('V2_SEARCH3_PRESENTATION'," + ('true' if enabled else 'false') + ");"
            code += "require " + json.dumps(str(ROOT / 'v2' / entry)) + ";"
            return subprocess.check_output(['php', '-r', code], text=True)

        canonical = render('anytoour.ru', 'poisk-turov/index.php')
        self.assertIn('<body class="search3-candidate">', canonical)
        for kind, suffix in [('css', 'style'), ('js', 'script')]:
            positions = []
            for name in ['search3-results-filters-v1', 'search3-entry-v1', 'search3-results-cards-v2', 'search3-selected-flow-v2']:
                marker = 'id="' + name + '-' + suffix + '"'
                self.assertEqual(canonical.count(marker), 1)
                self.assertIn('/' + name + '.' + kind + '?v=', canonical)
                positions.append(canonical.index(marker))
            self.assertEqual(positions, sorted(positions))
        for marker in ('leadApi:"/lead-adapter-v2.php"', 'api:"/api-v2.php"', 'metrikaCounter:123456', 'href="https://anytoour.ru/poisk-turov/"'):
            self.assertIn(marker, canonical)
        self.assertNotIn('?lead=disabled', canonical)
        legacy = render('anytoour.ru', 'poisk-turov-old/index.php')
        self.assertNotIn('id="search3-entry-v1-style"', legacy)
        self.assertIn('content="noindex,follow', legacy)
        self.assertIn('href="https://anytoour.ru/poisk-turov/"', legacy)
        self.assertIn('leadApi:"/lead-adapter-v2.php"', legacy)
        for html in [render('anytour.online', 'poisk-turov/index.php'), render('anytoour.ru', 'index.php'), render('anytoour.ru', 'poisk-turov/index.php', False)]:
            self.assertNotIn('<body class="search3-candidate">', html)
            self.assertNotIn('id="search3-entry-v1-style"', html)
            self.assertIn('metrikaCounter:123456', html)


if __name__ == '__main__':
    unittest.main(verbosity=2)
