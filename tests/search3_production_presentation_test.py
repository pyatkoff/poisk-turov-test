"""Verify Search3 activation without changing runtime, lead or analytics contracts."""
import hashlib
import importlib.util
import json
import re
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = json.loads((ROOT / 'docs/project/search3-production-import.json').read_text())


class Search3ProductionPresentationTest(unittest.TestCase):
    @unittest.skipUnless(shutil.which('node'), 'Node required for summary event regression')
    def test_booking_summary_event_bursts(self):
        for name in ('search3-booking-summary.cjs', 'search3-results-scheduler.cjs', 'search3-selected-flow-scheduler.cjs', 'search3-entry-summary.cjs'):
            subprocess.run(['node', str(ROOT / 'tests' / name)], check=True)

    @unittest.skipUnless(shutil.which('node'), 'Node required for filter ownership regression')
    def test_filter_rail_ownership(self):
        subprocess.run(['node', str(ROOT / 'tests' / 'search3-filter-rail-ownership.cjs')], check=True)

    @unittest.skipUnless(shutil.which('node'), 'Node required for price input regression')
    def test_filter_rail_price_input(self):
        subprocess.run(['node', str(ROOT / 'tests' / 'search3-filter-rail-price-input.cjs')], check=True)

    @unittest.skipUnless(shutil.which('node'), 'Node required for mobile toolbar ownership regression')
    def test_mobile_toolbar_ownership(self):
        subprocess.run(['node', str(ROOT / 'tests' / 'search3-mobile-toolbar-ownership.cjs')], check=True)

    def test_cascade_compatibility_section_contract(self):
        subprocess.run([
            'python3',
            str(ROOT / 'scripts/build/search3_cascade_sections.py'),
            '--check',
        ], check=True)

    def test_cascade_has_no_empty_media_rules(self):
        contract = json.loads((ROOT / 'docs/project/search3-cascade-sections.json').read_text())
        for name in contract['sections']:
            with self.subTest(name=name):
                source = (ROOT / contract['source_root'] / name).read_text()
                # Strip complete CSS comments without crossing their closing delimiter.
                source = re.sub(r'/\*[^*]*\*+(?:[^/*][^*]*\*+)*/', '', source)
                self.assertNotRegex(source, r'(?m)^[ \t]*@media[^{};]+\{\s*\}')

    def test_cascade_module_eof_contract(self):
        contract = json.loads((ROOT / 'docs/project/search3-cascade-sections.json').read_text())
        for name in contract['sections']:
            with self.subTest(name=name):
                raw = (ROOT / contract['source_root'] / name).read_bytes()
                self.assertTrue(raw.endswith(b'\n'), name)
                self.assertFalse(raw.endswith(b'\n\n'), name)

    def test_cascade_split_rejects_byte_drift(self):
        spec = importlib.util.spec_from_file_location(
            'search3_cascade_sections', ROOT / 'scripts/build/search3_cascade_sections.py')
        inspector = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(inspector)
        contract = json.loads(inspector.CONTRACT.read_text())
        # Mutate copies only; regression checks must never rewrite checked-in CSS.
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / contract['source_root']
            shutil.copytree(ROOT / contract['source_root'], source)
            contract_path = root / 'contract.json'
            contract_path.write_bytes(inspector.CONTRACT.read_bytes())
            with patch.object(inspector, 'ROOT', root), patch.object(inspector, 'CONTRACT', contract_path):
                _, raw, rows = inspector.inspect_sections()
                self.assertEqual(len(raw), contract['combined_bytes'])
                self.assertEqual(inspector.git_blob_sha(raw), contract['combined_git_blob_sha'])
                self.assertEqual([row['name'] for row in rows], contract['sections'])
                self.assertEqual(rows[-1]['endByte'], len(raw))
                path = source / contract['sections'][1]
                original = path.read_bytes()
                self.assertTrue(original.endswith(b'\n'))
                mutations = (
                    ('lost seam newline', original[:-1], 'byte count changed'),
                    ('CRLF conversion', original.replace(b'\n', b'\r\n'), 'byte count changed'),
                    ('same-length CSS corruption', original.replace(b'!important', b'!importanx', 1), 'blob changed'),
                )
                for name, changed, message in mutations:
                    with self.subTest(name=name):
                        self.assertNotEqual(changed, original)
                        path.write_bytes(changed)
                        try:
                            with self.assertRaisesRegex(ValueError, message):
                                inspector.inspect_sections()
                        finally:
                            path.write_bytes(original)
                # Restoration is exact; inspection remains read-only.
                self.assertEqual(inspector.inspect_sections()[1], raw)

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
