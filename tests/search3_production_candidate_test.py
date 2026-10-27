"""Offline production candidate derivation, boundaries and retained-byte checks."""
from __future__ import annotations

import hashlib
import importlib.util
import json
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import unittest
from urllib.parse import urljoin, urlsplit


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location('search3_production_candidate', ROOT / 'scripts/build/search3_production_candidate.py')
CANDIDATE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CANDIDATE)
SHA = '1' * 40
TREE = '2' * 40


class ProductionCandidateTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.base = Path(self.temp.name).resolve()
        self.source = self.base / 'source'
        visual = self.source / 'v2/visual-search'
        visual.mkdir(parents=True)
        # Actual entry/templates exercise the current source shape. API transports
        # are inert local fixtures; the builder must retain their bytes verbatim.
        for name in ('index.php', 'index.html'):
            (visual / name).write_bytes((ROOT / 'v2/visual-search' / name).read_bytes())
        for ref in CANDIDATE.LIVE + CANDIDATE.PRODUCTION_ONLY + CANDIDATE.COLD:
            name, _, _ = CANDIDATE.script_path(ref)
            path = self.source / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('// inert source: ' + ref + '\n')
        (self.source / 'v2/prototype-search/config.js').write_text(
            "const api={api:'/api-v2.php',anexApi:'/_preview/search3-anex-candidate/api-anex-search3-preview.php'};\n"
            "const search3Production=true;const search3PhoneLeadEnabled=false;\n")
        (visual / 'styles.css').write_text('@font-face{src:url(./assets/inter-latin.woff2)}\n')
        (visual / 'mobile-controls-v1.css').write_text('.control{min-height:44px}\n')
        for name, data in {'anytour-logo.svg': b'<svg/>', 'favicon.png': b'fixture-icon', 'inter-latin.woff2': b'fixture-font', 'inter-LICENSE.txt': b'license'}.items():
            path = visual / 'assets' / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(data)
        # Files which a broad v2 copy would leak must never enter this payload.
        for name in ('v2/config.php', 'v2/api-v2.php', 'v2/lead-adapter-v2.php', 'v2/visual-search/fixture-data.js', 'v2/visual-search/preview-lead.js', 'v2/visual-search/recorded-data.js'):
            path = self.source / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('not part of candidate')

    def tearDown(self):
        self.temp.cleanup()

    def build(self, name='candidate'):
        output = self.base / name
        manifest = CANDIDATE.build(self.source, output, SHA, TREE)
        return output, manifest

    def source_bytes(self):
        return {path.relative_to(self.source).as_posix(): path.read_bytes() for path in self.source.rglob('*') if path.is_file()}

    def test_deterministic_retained_artifact_and_untouched_sources(self):
        before = self.source_bytes()
        first, manifest = self.build('first')
        second, again = self.build('second')
        self.assertEqual(manifest, again)
        self.assertEqual((first / CANDIDATE.ARCHIVE).read_bytes(), (second / CANDIDATE.ARCHIVE).read_bytes())
        self.assertEqual(self.source_bytes(), before)
        self.assertEqual(manifest['state'], 'PREPARED_NOT_ACTIVATED')
        self.assertFalse(manifest['activation_performed'])
        self.assertIn('live predecessor', manifest['unverified'])
        self.assertIn('production activation', manifest['unverified'])
        self.assertEqual(manifest['source_tree_sha'], TREE)
        self.assertEqual(CANDIDATE.verify(first), manifest)
        with tarfile.open(first / CANDIDATE.ARCHIVE) as archive:
            members = archive.getmembers()
            self.assertTrue(all(member.isfile() and member.mode == 0o644 and member.mtime == 0 and member.uid == member.gid == 0 for member in members))
            self.assertEqual({member.name for member in members}, {'control/manifest.json', 'control/payload.sha256'} | {'payload/' + row['path'] for row in manifest['files']})
            self.assertTrue(all(not member.name.startswith('/') and '..' not in Path(member.name).parts for member in members))

    def test_exact_live_order_no_offline_entry_and_resolved_relative_assets(self):
        output, manifest = self.build()
        entry = (output / 'payload/poisk-turov/index.php').read_text()
        html = entry.split('?>\n', 1)[1]
        parser = CANDIDATE.References()
        parser.feed(html)
        self.assertEqual([urlsplit(src).path for src in parser.scripts], manifest['script_order'])
        self.assertEqual([urlsplit(src).path for src in parser.scripts][-2:], ['./production-query-v1.js', './app.js'])
        self.assertNotIn('production-query-v1.js', (self.source / 'v2/visual-search/index.php').read_text())
        self.assertEqual(len(parser.cold), 3)
        self.assertFalse(parser.base)
        for ref in parser.resources:
            resolved = urlsplit(urljoin(CANDIDATE.CANONICAL, ref))
            self.assertEqual(resolved.netloc, 'anytoour.ru')
            self.assertTrue((output / 'payload' / resolved.path.lstrip('/')).is_file(), ref)
        for marker in ('fixture-data.js', 'recorded-data.js', 'preview-lead.js', 'prototype-tools', 'prototype-version', 'recording-file', 'noindex', 'Версия для проверки', 'NEXT ·'):
            self.assertNotIn(marker, html)
        self.assertIn('data-search3-production="enabled"', html)
        self.assertIn('<link rel="canonical" href="https://anytoour.ru/poisk-turov/">', html)
        self.assertIn('href="#search"', html)
        self.assertIn('href="#results"', html)
        self.assertEqual([row['path'] for row in manifest['files'] if row['path'].endswith('.php')], ['poisk-turov/index.php'])
        for row in manifest['files']:
            if row['source']:
                self.assertEqual((output / 'payload' / row['path']).read_bytes(), (self.source / row['source']).read_bytes())

    def test_untouched_external_dependencies_are_explicit(self):
        _, manifest = self.build()
        paths = {item['path'] for item in manifest['external_dependencies']}
        self.assertEqual({CANDIDATE.API, CANDIDATE.INT_API,
            '/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php',
            '/data/departures-v1.php',
            '/_preview/search3-local-candidate/data/search3-destination-read-v1.php',
            '/_preview/search3-local-candidate/data/search3-local-results-read-v1.php',
            '/_preview/search3-local-candidate/data/hotel-details-read-v1.php',
            '/data/hotel-search-v1.php'}, paths)
        self.assertTrue(all(item['same_origin'] and 'not included or modified' in item['action'] for item in manifest['external_dependencies']))
        self.assertNotIn('live_backup', manifest)
        self.assertNotIn('previous_source_sha', manifest)

    def test_existing_output_and_symlink_parents_are_refused_without_overwrite(self):
        output = self.base / 'existing'
        output.mkdir()
        (output / 'keep').write_text('untouched')
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'output_must_not_exist'):
            CANDIDATE.build(self.source, output, SHA)
        self.assertEqual((output / 'keep').read_text(), 'untouched')
        (self.base / 'alias').symlink_to(self.source, target_is_directory=True)
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'unsafe_output_parent'):
            CANDIDATE.build(self.source, self.base / 'alias/output', SHA)
        self.assertFalse((self.source / 'output').exists())

    def test_unknown_missing_and_traversal_graphs_fail_before_output(self):
        php = self.source / 'v2/visual-search/index.php'
        original = php.read_text()
        for i, replacement in enumerate(('../runtime-v3.js', '../../outside.js', '../api.php')):
            php.write_text(original.replace('../lead-search-context.js', replacement))
            output = self.base / f'unsafe-{i}'
            with self.assertRaises(CANDIDATE.CandidateError):
                CANDIDATE.build(self.source, output, SHA)
            self.assertFalse(output.exists())
        php.write_text(original)
        (self.source / 'v2/runtime-v3.js').unlink()
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'missing'):
            self.build()

    def test_unknown_template_or_api_cannot_silently_derive(self):
        html = self.source / 'v2/visual-search/index.html'
        original = html.read_text()
        html.write_text(original.replace('<head ', '<head><base href="https://elsewhere.invalid/"><head '))
        with self.assertRaises(CANDIDATE.CandidateError):
            self.build('bad-template')
        html.write_text(original)
        config = self.source / 'v2/prototype-search/config.js'
        config.write_text(config.read_text().replace("api:'/api-v2.php'", "api:'https://elsewhere.invalid/api.php'"))
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'unreviewed_api_endpoint'):
            self.build('bad-api')

    def test_missing_or_external_stylesheet_assets_are_refused(self):
        css = self.source / 'v2/visual-search/styles.css'
        for index, value in enumerate(('./assets/missing.woff2', '../private/font.woff2', 'https://elsewhere.invalid/font.woff2')):
            css.write_text('@font-face{src:url("' + value + '")}')
            with self.assertRaises(CANDIDATE.CandidateError):
                self.build(f'bad-css-{index}')

    def test_input_and_asset_symlinks_are_not_followed(self):
        runtime = self.source / 'v2/runtime-v3.js'
        external = self.base / 'external.js'
        external.write_text('private outside source')
        runtime.unlink()
        runtime.symlink_to(external)
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'symlink'):
            self.build('bad-source-link')
        runtime.unlink()
        runtime.write_text('// restored')
        (self.source / 'v2/visual-search/assets/linked').symlink_to(self.base, target_is_directory=True)
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'symlink'):
            self.build('bad-asset-link')

    def test_tampered_missing_extra_and_mode_changed_payloads_fail_verification(self):
        for name in ('tamper', 'missing', 'extra', 'mode', 'symlink'):
            output, _ = self.build(name)
            target = output / 'payload/poisk-turov/app.js'
            if name == 'tamper':
                target.write_text('// altered')
            elif name == 'missing':
                target.unlink()
            elif name == 'extra':
                (output / 'payload/poisk-turov/api.php').write_text('unexpected backend')
            elif name == 'mode':
                target.chmod(0o600)
            else:
                target.unlink()
                target.symlink_to(self.source / 'v2/visual-search/app.js')
            with self.assertRaises(CANDIDATE.CandidateError):
                CANDIDATE.verify(output)

    def test_manifest_pin_and_archive_inventory_are_checked(self):
        output, _ = self.build()
        pin = hashlib.sha256((output / 'control/manifest.json').read_bytes()).hexdigest()
        CANDIDATE.verify(output, pin)
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'manifest_pin_mismatch'):
            CANDIDATE.verify(output, '0' * 64)
        (output / CANDIDATE.ARCHIVE).write_bytes(b'wrong archive')
        with self.assertRaisesRegex(CANDIDATE.CandidateError, 'archive_digest'):
            CANDIDATE.verify(output, pin)

    @unittest.skipUnless(shutil.which('php'), 'native PHP required; exercised by CI')
    def test_generated_php_route_and_offline_rejection(self):
        output, _ = self.build()
        entry = output / 'payload/poisk-turov/index.php'
        subprocess.run(['php', '-l', str(entry)], check=True, capture_output=True)
        cases = [('/poisk-turov/', '', True), ('/poisk-turov/index.php', 'scenario=live', True),
                 ('/poisk-turov/', '%73cenario=live', True), ('/poisk-turov/', 's3=1&origin=1', True),
                 ('/other/', '', False), ('/poisk-turov/nested/index.php', '', False),
                 ('/poisk-turov/', 'scenario=family', False), ('/poisk-turov/', 'scenario', False),
                 ('/poisk-turov/', 'scenario=demo&scenario=live', False),
                 ('/poisk-turov/', 'scenario=live&scenario=demo', False),
                 ('/poisk-turov/', 'scenario=live&scenario=live', False),
                 ('/poisk-turov/', '%73cenario=demo&scenario=live', False),
                 ('/poisk-turov/', 'scenario=live&%73cenario=live', False),
                 ('/poisk-turov/', 'scenario%5B%5D=live', False),
                 ('/poisk-turov/', 'scenario=live&scenario%5B%5D=live', False),
                 ('/poisk-turov/', 'scenario%5B0%5D=demo&scenario=live', False),
                 ('/poisk-turov/', 'scenario%00x=live', False),
                 ('/poisk-turov/', 'scenario=live%26scenario%3Dfamily', False)]
        for route, query, allowed in cases:
            # Parse actual raw query bytes through PHP itself. A hand-built
            # $_GET fixture cannot reproduce duplicate-key last-value loss.
            php = ('http_response_code(200);register_shutdown_function(static function(){fwrite(STDERR,"STATUS:".http_response_code());});'
                   '$_SERVER["SCRIPT_NAME"]=$argv[1];$_SERVER["QUERY_STRING"]=$argv[2];'
                   'parse_str($argv[2],$_GET);include $argv[3];')
            result = subprocess.run(['php', '-r', php, route, query, str(entry)], check=True, capture_output=True, text=True)
            self.assertEqual('<!DOCTYPE html>' in result.stdout, allowed, (route, query))
            self.assertNotIn('Warning', result.stdout)
            self.assertEqual(result.stderr, 'STATUS:200' if allowed else 'STATUS:403', (route, query))
            if not allowed:
                self.assertIn('unavailable', result.stdout)

    def test_actual_checkout_candidate_uses_only_reviewed_frontend_graph(self):
        # No PHP/API is executed: this exercises the actual source inventory and
        # production guards rather than merely the small synthetic input fixture.
        config = (ROOT / 'v2/prototype-search/config.js').read_text()
        if 'search3PhoneLeadEnabled=false' not in config:
            self.skipTest('production policy patch is not present in this checkout')
        output = self.base / 'actual'
        manifest = CANDIDATE.build(ROOT, output, SHA)
        self.assertGreater(manifest['file_count'], 30)
        self.assertEqual(CANDIDATE.verify(output), manifest)
        self.assertEqual((output / 'payload/poisk-turov/assets/anytour-logo.svg').read_bytes(), (ROOT / 'v2/visual-search/assets/anytour-logo.svg').read_bytes())


if __name__ == '__main__':
    unittest.main(verbosity=2)
