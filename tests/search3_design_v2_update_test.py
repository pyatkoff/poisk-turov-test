"""Bounded source proof, exact published predecessor and interrupted updates."""
import copy
import io
import json
import os
from pathlib import Path
import shutil
import sys
import tempfile
import unittest
import zipfile
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/deploy'))
import search3_design_v2_preview as creator
import search3_design_v2_preview_remote as create_remote
import search3_design_v2_update as pub
import search3_design_v2_update_remote as remote
from search3_design_v2_preview_test import fixture, request as original_request


class UpdateContracts(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(); self.root = Path(self.tmp.name)
        self.baseline = self.root / 'baseline'; fixture(self.baseline)
        paths = [p for p in remote.SOURCE_CHANGES if p != creator.LEDGER_PATH]
        for i, name in enumerate(paths):
            (self.baseline / 'payload' / f'visual-search/fixture-{i}.js').rename(self.baseline / 'payload' / name)
        for i, name in enumerate(('visual-search/preview-lead.js', 'prototype-search/lead.js',
                'tour-controller-v4.js', 'visual-search/assets/anytour-logo.svg',
                'visual-search/fonts/Aeroport.woff2'), start=len(paths)):
            target = self.baseline / 'payload' / name; target.parent.mkdir(parents=True, exist_ok=True)
            (self.baseline / 'payload' / f'visual-search/fixture-{i}.js').rename(target)
        import posixpath
        for i, src in enumerate(remote.NATIVE_SCRIPTS, start=50):
            name = posixpath.normpath(posixpath.join('visual-search', src)); target = self.baseline / 'payload' / name
            if target.exists() or name in remote.NATIVE_ASSETS: continue
            target.parent.mkdir(parents=True, exist_ok=True)
            (self.baseline / 'payload' / f'visual-search/fixture-{i}.js').rename(target)
        self.original_pin = patch.object(creator, 'SOURCE_LEDGER_SHA256', remote.digest((self.baseline / 'payload' / creator.LEDGER_PATH).read_bytes()))
        self.original_pin.start(); self.addCleanup(self.original_pin.stop)
        creator.derive(self.baseline, self.root / 'installed', original_request(), creator.SOURCE_ZIP_SHA256)
        (self.root / 'installed/payload/visual-search/app.js').write_text('previous published polish')
        self.source = self.root / 'source'; shutil.copytree(self.baseline, self.source)
        (self.source / 'payload/visual-search/app.js').write_text('reviewed explicit nights UX')
        for name in remote.NATIVE_ASSETS:
            path = self.source / 'payload' / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_text('native fixture:' + name)
        html = '<!doctype html><meta name="robots" content="noindex,nofollow,noarchive"><main id="design-v2-native-root"></main>\n'
        html += '\n'.join('<link rel="stylesheet" href="' + src + '">' for src in remote.NATIVE_STYLES) + '\n'
        html += '\n'.join('<script src="' + src + '" defer></script>' for src in remote.NATIVE_SCRIPTS) + '\n'
        (self.source / 'payload/visual-search/index.html').write_text(html)
        (self.source / 'payload/visual-search/index.php').write_text("<?php $entry = '/_preview/search3-design-v2-candidate/visual-search/';\nif ($script !== $entry . 'index.php') { exit; }")
        def row(src):
            name = posixpath.normpath(posixpath.join('visual-search', src)); content = (self.source / 'payload' / name).read_bytes()
            return {'src': src, 'path': name, 'sha256': remote.digest(content), 'bytes': len(content)}
        native = {'schema': 2, 'donorSource': '00fe6734c08acf234756bb59e5b61dbfecf41941', 'donor': remote.NATIVE_DONOR,
            'status': 'native-source-candidate-not-published', 'legacyPresentationLoaded': False, 'mockSimulatorLoaded': False,
            'isolatedOverlay': True, 'canonicalDependenciesRewritten': False,
            'entry': {'html': remote.digest(html.encode()), 'php': remote.digest((self.source / 'payload/visual-search/index.php').read_bytes())},
            'graph': {'scripts': [row(src) for src in remote.NATIVE_SCRIPTS], 'styles': [row(src) for src in remote.NATIVE_STYLES]},
            'files': {Path(name).name: remote.digest((self.source / 'payload' / name).read_bytes()) for name in remote.NATIVE_ASSETS}}
        (self.source / 'payload' / remote.NATIVE_MANIFEST).write_text(json.dumps(native, ensure_ascii=False, indent=2) + '\n')
        self.native = native
        self.native_pin = {'source_sha': 'a' * 40, 'source_tree': 'd' * 40, 'build_run': 123, 'artifact_id': 456,
            'source_ZIP_sha256': 'e' * 64, 'entry_html_sha256': native['entry']['html'], 'entry_php_sha256': native['entry']['php'],
            'source_manifest_sha256': remote.digest((self.source / 'payload' / remote.NATIVE_MANIFEST).read_bytes())}
        for change in (patch.object(remote, 'NATIVE_ACCEPTANCE', self.native_pin),
                       patch.object(remote, 'NATIVE_BRAND', {name: native['files'][name] for name in remote.NATIVE_BRAND})):
            change.start(); self.addCleanup(change.stop)
        m = json.loads((self.source / 'control/manifest.json').read_bytes())
        m.update(source_sha='a' * 40, source_tree_sha='d' * 40)
        (self.source / 'control/manifest.json').write_bytes(remote.json_bytes(m))
        q = {**original_request(), 'source_sha': 'a' * 40, 'release_sha': 'a' * 40,
            'source_tree': 'd' * 40, 'build_run': 123, 'artifact_id': 456, 'deploy_run': 789}
        self.q, self.archive = pub.derive(self.source, self.baseline, self.root / 'derived', q, 'e' * 64)
        site_root = self.root / 'anytoour.ru'; parent = site_root / '_preview'; parent.mkdir(parents=True)
        for name in create_remote.REQUIRED:
            p = site_root / name; p.parent.mkdir(parents=True, exist_ok=True); p.write_text('protected:' + name)
        for name in ('search3-site-candidate', 'search3-local-candidate', 'search3-v17-candidate',
                     'search3-v18-candidate', 'search3-next-candidate', 'search3-anex-candidate'):
            (parent / name).mkdir(); (parent / name / 'keep.txt').write_text('old unchanged:' + name)
        shutil.copytree(self.root / 'installed/payload', parent / 'search3-design-v2-candidate')
        self.digest = remote.digest(remote.json_bytes(remote.inventory(parent / 'search3-design-v2-candidate')))
        self.owner = {**remote.PREDECESSOR_OWNER, 'digest': self.digest}
        self.pins = [patch.object(remote, 'PREDECESSOR_DIGEST', self.digest), patch.object(remote, 'PREDECESSOR_OWNER', self.owner)]
        for pin in self.pins: pin.start(); self.addCleanup(pin.stop)
        self.site = remote.Site(site_root); self.site.owner.write_bytes(remote.json_bytes(self.owner))
        self.uploads = []
        self.env = {'GITHUB_REPOSITORY': pub.REPO, 'GITHUB_REF': 'refs/heads/main',
            'GITHUB_ACTOR': 'pyatkoff', 'GITHUB_TRIGGERING_ACTOR': 'pyatkoff', 'GITHUB_ACTOR_ID': '226193297',
            'GITHUB_RUN_ATTEMPT': '1', 'GITHUB_EVENT_NAME': 'issue_comment', 'GITHUB_RUN_ID': '789'}
        self.event = {'repository': {'full_name': pub.REPO, 'id': 1345518271},
            'sender': {'login': 'pyatkoff', 'id': 226193297}, 'action': 'created', 'issue': {'number': 4217},
            'comment': {'user': {'id': 226193297}, 'author_association': 'OWNER',
                'body': pub.PREFIX + 'a' * 40 + ' ' + 'a' * 40 + ' 123 456'}}

    def tearDown(self):
        for p in self.uploads: p.unlink(missing_ok=True)
        self.tmp.cleanup()

    def staged(self, run=789):
        fd, name = tempfile.mkstemp(prefix='search3-design-v2-update.', suffix='.tar.gz', dir='/tmp'); os.close(fd)
        p = Path(name); p.write_bytes(self.archive); self.uploads.append(p)
        return {**self.q, 'deploy_run': run, 'archive': name, 'before': self.site.snapshot()}

    def review_archive(self, mutate=None):
        contents = {('payload/' + name): (self.source / 'payload' / name).read_bytes()
                    for name in remote.inventory(self.source / 'payload')}
        meta = {'schema': 1, 'source_sha': 'a' * 40, 'source_tree_sha': 'd' * 40, 'base_sha': 'b' * 40,
            'run_id': '123', 'run_attempt': '1', 'donor_source_sha': self.native['donorSource'],
            'intended_route': remote.ROUTE + 'visual-search/', 'status': 'review-artifact-not-published',
            'publication_authority': False, 'canonical_route_rewrites_applied': False,
            'file_count': 855, 'files': [{'path': name, 'sha256': remote.digest(value), 'bytes': len(value)}
                                      for name, value in sorted((name[len('payload/'):], value) for name, value in contents.items())]}
        contents['control/inventory.json'] = remote.json_bytes(meta)
        contents['control/native-build.json'] = remote.json_bytes(self.native)
        proof = {'passiveHydrationSearches': 0, 'formSubmitSearches': 1, 'localFilterExtraSearches': 0,
            'exactLoadedOfferRetained': True, 'flightChoiceIndex': 0, 'selectedFullTotal': 112000,
            'contactPayloadFullTotal': 112000, 'contactPayloadFlightChoiceIndex': 0,
            'contactPreviewCount': 1, 'realLeadSubmissions': 0, 'backForwardRetainedSelection': True,
            'backForwardExtraSearches': 0, 'errorRetainedRows': 4, 'explicitSearchRetryCount': 1,
            'retryRows': 4, 'favoriteStoragePreserved': True}
        report = {'schema': 1, 'sourceSHA': 'a' * 40, 'donorSource': self.native['donorSource'],
            'compiledNativeManifest': self.native, 'status': 'passed', 'requiredWidths': [360,390,430,768,1280],
            'requiredTextPercents': [100,200], 'expectedPairedScreens': ['search','results','hotel','tour','lead'],
            'cases': [], 'failures': []}
        for width in (360,390,430,768,1280):
            for percent in (100,200):
                case = {'width': width, 'textPercent': percent, 'proof': dict(proof), 'errors': [], 'unexpectedRequests': [], 'frames': []}
                for side, screen in [(side,screen) for side in ('native','donor') for screen in ('search','results','hotel','tour','lead')] + [('native','error')]:
                    name = f'{side}-{screen}-{width}-text{percent}.png'; png = b'\x89PNG\r\n\x1a\nfictional offline verifier fixture'
                    contents['evidence/' + name] = png
                    case['frames'].append({'file': name, 'sha256': remote.digest(png), 'side': side,
                        'viewportWidth': width, 'documentWidth': width, 'textPercent': percent, 'overflow': False, 'stageHidden': False,
                        'buttons': [{'text': 'Fixture action', 'clipped': False}],
                        'prices': [{'overflow': False, 'fragmented': False, 'textScaleVerified': True,
                                    'fontSize': 29 * percent / 100, 'baselineFontSize': 29}]})
                report['cases'].append(case)
        contents['evidence/acceptance.json'] = remote.json_bytes(report)
        contents['evidence/fixture.json'] = b'{"scope":"fictional offline verifier fixture"}\n'
        if mutate: mutate(contents)
        output = io.BytesIO()
        with zipfile.ZipFile(output, 'w') as archive:
            for name, value in contents.items(): archive.writestr(name, value)
        return output.getvalue()

    def read_review(self, content, target='review'):
        pin = {**self.native_pin, 'source_ZIP_sha256': remote.digest(content)}
        with patch.object(remote, 'NATIVE_ACCEPTANCE', pin):
            return pub.prepare_native_zip(content, self.q, 'd' * 40, pin['source_ZIP_sha256'], self.root / target)

    def test_owner_command_has_no_target_or_predecessor_argument(self):
        q = pub.checked_request(self.event, self.env)
        self.assertEqual(q['source_sha'], 'a' * 40)
        self.assertEqual(pub.RELEASE, 'release/search3-design-v2')
        self.assertEqual(remote.ROUTE, '/_preview/search3-design-v2-candidate/')
        self.assertEqual(remote.PREDECESSOR_SOURCE, '16196410a8cb90b10e8d06b7ac8b38d4ce7d235d')
        self.assertEqual(remote.PREDECESSOR_RUN, 38207863194)

    def test_preparation_has_no_publication_authority_without_real_pins(self):
        with patch.object(remote, 'NATIVE_ACCEPTANCE', None):
            with self.assertRaisesRegex(ValueError, 'native_acceptance_not_pinned'): pub.checked_request(self.event, self.env)
            with self.assertRaisesRegex(ValueError, 'native_acceptance_not_pinned'): remote.validate(self.q)
            with self.assertRaisesRegex(ValueError, 'native_acceptance_not_pinned'):
                pub.derive(self.source, self.baseline, self.root / 'unpinned', self.q, 'e' * 64)
        self.assertFalse((self.root / 'unpinned').exists())

    def test_complete_review_zip_preserves_exact_native_payload(self):
        files = self.read_review(self.review_archive())
        self.assertEqual(files, remote.inventory(self.source / 'payload'))
        self.assertEqual(remote.inventory(self.root / 'review/release/payload'), files)

    def test_review_zip_rejects_identity_scope_and_byte_drift(self):
        def meta_change(contents, key, value):
            meta = json.loads(contents['control/inventory.json']); meta[key] = value
            contents['control/inventory.json'] = remote.json_bytes(meta)
        mutations = [lambda c: meta_change(c, 'source_sha', 'f' * 40),
            lambda c: meta_change(c, 'publication_authority', True),
            lambda c: meta_change(c, 'run_attempt', '2'),
            lambda c: c.update({'payload/prototype-search/data.js': b'foreign provider byte'}),
            lambda c: c.update({'payload/visual-search/native-v2/unknown.js': b'unapproved'}),
            lambda c: c.update({'control/unknown.py': b'unapproved'}),
            lambda c: c.update({'../escape': b'unapproved'})]
        for i, mutate in enumerate(mutations):
            with self.subTest(index=i), self.assertRaises(ValueError): self.read_review(self.review_archive(mutate), 'bad-zip-' + str(i))

    def test_rendered_evidence_requires_full_matrix_factual_journey_and_hashes(self):
        def report_change(contents, modify):
            report = json.loads(contents['evidence/acceptance.json']); modify(report)
            contents['evidence/acceptance.json'] = remote.json_bytes(report)
        modifications = [lambda r: r.update({'sourceSHA': 'f' * 40}), lambda r: r['cases'].pop(),
            lambda r: r['cases'][0]['proof'].update({'selectedFullTotal': 1000}),
            lambda r: r['cases'][0]['proof'].update({'realLeadSubmissions': 1}),
            lambda r: r['cases'][0]['proof'].update({'localFilterExtraSearches': 1}),
            lambda r: r['cases'][0]['frames'][0].update({'overflow': True}),
            lambda r: r['cases'][0]['frames'][0]['buttons'][0].update({'clipped': True}),
            lambda r: r['cases'][0]['frames'][0]['prices'][0].update({'fragmented': True}),
            lambda r: r['cases'][0]['frames'][0]['prices'][0].update({'textScaleVerified': False}),
            lambda r: r['cases'][1]['frames'][0]['prices'][0].update({'fontSize': 29}),
            lambda r: r['cases'][0]['frames'][0].update({'sha256': 'f' * 64}),
            lambda r: r['cases'][0]['unexpectedRequests'].append('https://external.invalid/api'),
            lambda r: r.update({'failures': ['confirmed failure']})]
        for i, modify in enumerate(modifications):
            with self.subTest(index=i), self.assertRaises(ValueError):
                self.read_review(self.review_archive(lambda c: report_change(c, modify)), 'bad-evidence-' + str(i))

    def test_native_inventory_cannot_accept_extra_or_missing_asset(self):
        path = self.source / 'payload/visual-search/native-v2/not-reviewed.js'; path.write_text('foreign')
        with self.assertRaisesRegex(ValueError, 'source_inventory_drift'):
            pub.derive(self.source, self.baseline, self.root / 'extra-native', self.q, 'e' * 64)
        path.unlink(); (self.source / 'payload' / remote.NATIVE_ASSETS[0]).unlink()
        with self.assertRaisesRegex(ValueError, 'source_inventory_drift'):
            pub.derive(self.source, self.baseline, self.root / 'missing-native', self.q, 'e' * 64)

    def test_native_entry_rejects_legacy_renderer_even_after_manifest_rehash(self):
        payload = self.source / 'payload'; path = payload / 'visual-search/index.html'
        path.write_text(path.read_text() + '<script src="./app.js" defer></script>')
        manifest_path = payload / remote.NATIVE_MANIFEST; native = json.loads(manifest_path.read_bytes())
        native['entry']['html'] = remote.digest(path.read_bytes()); manifest_path.write_text(json.dumps(native, indent=2) + '\n')
        pin = {**self.native_pin, 'entry_html_sha256': native['entry']['html'], 'source_manifest_sha256': remote.digest(manifest_path.read_bytes())}
        with patch.object(remote, 'NATIVE_ACCEPTANCE', pin), self.assertRaisesRegex(ValueError, 'native_exact_entry_graph'):
            remote.native_graph(payload)

    def test_native_checksum_and_route_reversal_protect_original_owner(self):
        manifest = self.root / 'derived/payload' / remote.NATIVE_MANIFEST; native = json.loads(manifest.read_bytes())
        native['graph']['scripts'][0]['sha256'] = 'f' * 64; manifest.write_text(json.dumps(native, indent=2) + '\n')
        with self.assertRaisesRegex(ValueError, 'native_graph_checksum'): remote.native_graph(self.root / 'derived/payload', rewritten=True)

    def test_owner_main_event_and_attempt_guards(self):
        for key, value in [('GITHUB_REF', 'refs/heads/work'), ('GITHUB_ACTOR', 'other'),
            ('GITHUB_TRIGGERING_ACTOR', 'other'), ('GITHUB_ACTOR_ID', '0'), ('GITHUB_RUN_ATTEMPT', '2'),
            ('GITHUB_EVENT_NAME', 'workflow_dispatch'), ('GITHUB_REPOSITORY', 'other/repo')]:
            with self.subTest(key=key), self.assertRaises(ValueError): pub.checked_request(self.event, {**self.env, key: value})
        for key, field, value in [('issue', 'number', 3419), ('issue', 'number', 2530),
            ('issue', 'number', 996), ('issue', 'pull_request', {}), ('sender', 'id', 1),
            ('comment', 'author_association', 'MEMBER'), ('comment', 'user', {'id': 1}), ('repository', 'id', 1)]:
            event = copy.deepcopy(self.event); event[key][field] = value
            with self.subTest(key=key, field=field), self.assertRaises(ValueError): pub.checked_request(event, self.env)

    def test_bad_syntax_and_old_source_rejected(self):
        good = self.event['comment']['body']
        for body in [good + '\n', good + ';id', good + ' /_preview/search3-next-candidate/',
            good.replace(pub.PREFIX, creator.PREFIX), good.replace(' 123 ', '  123 '),
            good.replace('a' * 40, remote.PREDECESSOR_SOURCE),
            good.replace('a' * 40, remote.ORIGINAL_SOURCE), good.replace(' 123 ', ' 0 ')]:
            event = copy.deepcopy(self.event); event['comment']['body'] = body
            with self.subTest(body=body), self.assertRaises(ValueError): pub.checked_request(event, self.env)

    def test_all_completed_sources_rejected_by_both_owners(self):
        self.assertEqual(remote.COMPLETED_SOURCES, (remote.ORIGINAL_SOURCE,
            '950c9b5c0a00430c5b5a711d1b95bc04449d069d',
            '634d1c97fd7bf499107d82036588f4bd77176d93',
            '2eb237639c6c53c38f27d71684de43384842daf7',
            'a0e5f1e7c0083b45678df5837c7faf1cad8751f6', remote.PREDECESSOR_SOURCE))
        for source in remote.COMPLETED_SOURCES:
            event = copy.deepcopy(self.event); event['comment']['body'] = event['comment']['body'].replace('a' * 40, source)
            with self.subTest(source=source), self.assertRaisesRegex(ValueError, 'new_source_required'):
                pub.checked_request(event, self.env)
            with self.subTest(source=source), self.assertRaisesRegex(ValueError, 'new_source_required'):
                remote.validate({**self.q, 'source_sha': source})

    def test_exact_source_native_ci_checks_required(self):
        checks = [{'name': n, 'head_sha': self.q['source_sha'], 'status': 'completed', 'conclusion': 'success',
            'app': {'slug': 'github-actions'}, 'started_at': '2026-10-11T21:30:00Z'} for n in pub.REQUIRED_CHECKS]
        class API:
            def get(self, path): return {'total_count': len(checks), 'check_runs': checks}
        pub.strict_checks(API(), self.q['source_sha'])
        for key, value in [('conclusion', 'skipped'), ('head_sha', 'f' * 40), ('status', 'in_progress'), ('app', {'slug': 'other'})]:
            previous = checks[0][key]; checks[0][key] = value
            with self.subTest(key=key), self.assertRaises(ValueError): pub.strict_checks(API(), self.q['source_sha'])
            checks[0][key] = previous
        checks.pop()
        with self.assertRaisesRegex(ValueError, 'required_visual_checks_missing'): pub.strict_checks(API(), self.q['source_sha'])

    def test_only_eight_reviewed_presentation_paths_can_differ(self):
        self.assertEqual(set(remote.SOURCE_CHANGES), {
            'visual-search/app.js', 'visual-search/asset-size.json',
            'visual-search/hotel-details-v1.js', 'visual-search/offer-list-v1.js',
            'visual-search/index.html', 'visual-search/migration-manifest.json',
            'visual-search/mobile-controls-v1.css', 'visual-search/styles.css'})
        before = remote.inventory(self.source / 'payload'); after = remote.inventory(self.root / 'derived/payload')
        self.assertEqual(set(before), set(after)); self.assertEqual(len(after), 855)
        self.assertEqual({n for n in before if before[n] != after[n]}, set(remote.NATIVE_ROUTE_CHANGES))
        m = json.loads((self.root / 'derived/control/manifest.json').read_bytes())
        self.assertEqual(m['derived_from']['changed_source_payload_files'], sorted(['visual-search/app.js', *remote.NATIVE_REPLACED, *remote.NATIVE_ADDED]))
        q2, a2 = pub.derive(self.source, self.baseline, self.root / 'again', {k: self.q[k] for k in original_request()}, 'e' * 64)
        self.assertEqual(q2, self.q); self.assertEqual(a2, self.archive)

    def test_hotel_and_offer_presentation_files_retain_every_other_payload_byte(self):
        for i, name in enumerate(('visual-search/hotel-details-v1.js', 'visual-search/offer-list-v1.js')):
            source_path = self.source / 'payload' / name; original = source_path.read_bytes()
            source_path.write_bytes(b'reviewed final Sites presentation')
            output = self.root / ('hotel-presentation-' + str(i))
            try:
                q, _ = pub.derive(self.source, self.baseline, output, self.q, 'e' * 64)
                remote.verify(output, q)
                manifest = json.loads((output / 'control/manifest.json').read_bytes())
                self.assertEqual(manifest['derived_from']['changed_source_payload_files'],
                    sorted(['visual-search/app.js', name, *remote.NATIVE_REPLACED, *remote.NATIVE_ADDED]))
                before = remote.inventory(self.source / 'payload'); after = remote.inventory(output / 'payload')
                self.assertEqual({n for n in before if before[n] != after[n]}, set(remote.NATIVE_ROUTE_CHANGES))
                self.assertEqual((output / 'payload' / name).read_bytes(), source_path.read_bytes())
            finally:
                source_path.write_bytes(original)

    def test_original_baseline_remains_independent_of_installed_predecessor(self):
        manifest = json.loads((self.root / 'derived/control/manifest.json').read_bytes())
        self.assertEqual(manifest['derived_from']['baseline'], {
            'source_sha': creator.SOURCE_SHA, 'build_run': creator.BUILD_RUN,
            'artifact_id': creator.ARTIFACT_ID, 'ZIP_sha256': creator.SOURCE_ZIP_SHA256})
        self.assertNotEqual(remote.ORIGINAL_SOURCE, remote.PREDECESSOR_SOURCE)
        with patch('search3_preview_publish.prepare_zip') as prepare_zip, patch.object(pub, 'prepare_native_zip'):
            class API:
                def download(self, artifact_id): return b'synthetic ZIP'
            with patch.object(pub, 'derive', return_value=(self.q, self.archive)):
                pub.prepare_inputs(API(), self.q, 'd' * 40, 'e' * 64, self.root / 'inputs')
        baseline_request = prepare_zip.call_args_list[0].args[1]
        self.assertEqual(baseline_request['source_sha'], remote.ORIGINAL_SOURCE)
        self.assertEqual(baseline_request['release_sha'], remote.ORIGINAL_SOURCE)

    def test_all_completed_publish_runs_cannot_replay(self):
        self.assertEqual(remote.COMPLETED_PUBLISH_RUNS,
            (remote.ORIGINAL_PUBLISH_RUN, 38180461319, 38191104207,
                38197437835, 38203171197, 38207863194))
        for deploy_run in remote.COMPLETED_PUBLISH_RUNS:
            with self.subTest(deploy_run=deploy_run), self.assertRaisesRegex(ValueError, 'no_replay'):
                remote.validate({**self.q, 'deploy_run': deploy_run})

    def test_historical_creation_cannot_replace_current_predecessor(self):
        before = {'target': 'f8e4742f6964dd1237dc86ba27c0d3379a53b57ca4f45d6c16e713f392b43aef',
            'owner': {'run': remote.ORIGINAL_PUBLISH_RUN, 'source': remote.ORIGINAL_SOURCE,
                'digest': 'f8e4742f6964dd1237dc86ba27c0d3379a53b57ca4f45d6c16e713f392b43aef',
                'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_polish_owner_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38180461319,
            'source': '950c9b5c0a00430c5b5a711d1b95bc04449d069d',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_restoration_owner_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38191104207,
            'source': '634d1c97fd7bf499107d82036588f4bd77176d93',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_meal_search_owner_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38197437835,
            'source': '2eb237639c6c53c38f27d71684de43384842daf7',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_supplier_price_lead_logo_font_and_unknown_payload_changes_denied(self):
        for name in ['prototype-search/data.js', 'search-page-v2.php', 'site-path-v1.php',
                'visual-search/preview-lead.js', 'prototype-search/lead.js', 'tour-controller-v4.js',
                'visual-search/assets/anytour-logo.svg', 'visual-search/fonts/Aeroport.woff2']:
            p = self.source / 'payload' / name; original = p.read_bytes(); p.write_bytes(original + b' unreviewed')
            with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'unreviewed_source_delta'):
                pub.derive(self.source, self.baseline, self.root / 'rejected', self.q, 'e' * 64)
            p.write_bytes(original)
        (self.source / 'payload/new-asset.js').write_text('unknown')
        with self.assertRaisesRegex(ValueError, 'source_inventory_drift'): pub.derive(self.source, self.baseline, self.root / 'rejected', self.q, 'e' * 64)

    def test_corrupt_source_ledger_denied(self):
        p = self.source / 'payload' / creator.LEDGER_PATH; ledger = json.loads(p.read_bytes())
        ledger['files'][0]['sha256'] = 'f' * 64; p.write_text(json.dumps(ledger))
        with self.assertRaisesRegex(ValueError, 'source_ledger_accounting'): pub.derive(self.source, self.baseline, self.root / 'rejected', self.q, 'e' * 64)

    def test_exact_predecessor_owner_and_inventory_required(self):
        q = self.staged()
        for field, value in [('source', 'f' * 40), ('run', 123), ('status', 'activated'), ('digest', 'f' * 64)]:
            bad = copy.deepcopy(q); bad['before']['owner'][field] = value
            with self.subTest(field=field), self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'): self.site.activate(bad)
        (self.site.target / 'visual-search/app.js').write_text('different installed preview')
        with self.assertRaisesRegex(ValueError, 'predecessor_changed'): self.site.activate(q)

    def test_absent_unknown_or_symlinked_target_denied(self):
        for before in [{'target': None, 'owner': None}, {'target': self.digest, 'owner': None}]:
            with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'): remote.predecessor(before)
        self.site.target.rename(self.site.parent / 'untrusted'); self.site.target.symlink_to(self.site.parent / 'untrusted')
        with self.assertRaisesRegex(ValueError, 'target_or_owner_link'): remote.Site(self.site.root)

    def test_activate_and_publish_preserve_production_all_old_previews(self):
        q = self.staged(); old = self.site.protected()
        self.assertEqual(self.site.activate(q)['status'], 'activated')
        self.assertEqual(self.site.finish(q)['status'], 'published')
        self.assertEqual(self.site.protected(), old)
        self.assertEqual(remote.digest(remote.json_bytes(remote.inventory(self.site.target))), self.q['target_digest'])
        self.assertEqual(remote.digest(remote.json_bytes(remote.inventory(self.site.receipt(q) / 'predecessor'))), self.digest)
        self.assertEqual(json.loads(self.site.owner.read_bytes())['status'], 'published')
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'): self.site.activate(self.staged(run=790))

    def test_rollback_restores_exact_target_owner_and_old_previews(self):
        q = self.staged(); self.site.activate(q)
        self.assertEqual(self.site.finish(q, True)['status'], 'rolled_back_to_exact_predecessor')
        self.assertEqual(self.site.snapshot(), q['before'])
        self.assertEqual((self.site.target / 'visual-search/app.js').read_text(), 'previous published polish')

    def test_completed_publish_or_rollback_cannot_replay(self):
        q = self.staged(); self.site.activate(q); self.site.finish(q)
        for rollback in (False, True):
            with self.subTest(rollback=rollback), self.assertRaisesRegex(ValueError, 'transaction_already_finished'): self.site.finish(q, rollback)
        self.assertEqual(json.loads(self.site.owner.read_bytes())['status'], 'published')

    def test_interruption_after_old_move_restores_without_guessing(self):
        q = self.staged(); real = Path.rename
        def fail_new_move(path, target):
            if path.name == 'payload' and Path(target) == self.site.target: raise RuntimeError('interrupted install')
            return real(path, target)
        with patch.object(Path, 'rename', fail_new_move), self.assertRaisesRegex(RuntimeError, 'interrupted install'): self.site.activate(q)
        self.assertFalse(self.site.target.exists())
        self.assertEqual(self.site.finish(q, True)['status'], 'rolled_back_to_exact_predecessor')
        self.assertEqual(self.site.snapshot(), q['before'])

    def test_interruption_before_owner_commit_restores_checked_new_payload(self):
        q = self.staged()
        with (patch.object(self.site, 'write_owner', side_effect=RuntimeError('owner interruption')),
             self.assertRaisesRegex(RuntimeError, 'owner interruption')):
            self.site.activate(q)
        self.assertEqual(json.loads(self.site.owner.read_bytes()), self.owner)
        self.assertEqual(self.site.finish(q, True)['status'], 'rolled_back_to_exact_predecessor')
        self.assertEqual(self.site.snapshot(), q['before'])

    def test_validation_failure_before_activation_has_terminal_receipt(self):
        q = self.staged()
        with patch.object(remote, 'verify', side_effect=ValueError('invalid artifact')), self.assertRaisesRegex(ValueError, 'invalid artifact'): self.site.activate(q)
        self.assertEqual(self.site.finish(q, True)['status'], 'not_activated')
        with self.assertRaisesRegex(ValueError, 'transaction_already_finished'): self.site.finish(q, True)
        with self.assertRaisesRegex(ValueError, 'previous_outcome_unknown'): self.site.activate(self.staged())

    def test_modified_new_target_backup_or_owner_stops_rollback(self):
        q = self.staged(); self.site.activate(q)
        (self.site.target / 'visual-search/app.js').write_text('another writer')
        with self.assertRaisesRegex(ValueError, 'modified_target'): self.site.finish(q, True)
        self.assertTrue((self.site.receipt(q) / 'predecessor').is_dir())
        self.assertFalse((self.site.receipt(q) / 'result.json').exists())

    def test_request_or_backup_drift_stops_transaction(self):
        q = self.staged(); self.site.activate(q)
        with self.assertRaisesRegex(ValueError, 'accepted_native_source_only'): self.site.finish({**q, 'artifact_id': 999}, True)
        (self.site.receipt(q) / 'predecessor/visual-search/app.js').write_text('modified backup')
        for rollback in (False, True):
            with self.subTest(rollback=rollback), self.assertRaisesRegex(ValueError, 'backup_changed'): self.site.finish(q, rollback)

    def test_existing_preview_locks_refuse_update(self):
        q = self.staged()
        for name in ('.search3-site-lock', '.search3-local-lock', '.search3-v17-lock', '.search3-v18-lock', '.search3-next-lock'):
            p = self.site.parent / name; p.mkdir()
            with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'existing_preview_busy'): self.site.activate(q)
            p.rmdir()

    def test_request_target_digest_cannot_be_changed(self):
        with self.assertRaisesRegex(ValueError, 'payload_hashes'): remote.verify(self.root / 'derived', {**self.q, 'target_digest': 'f' * 64})
        with self.assertRaisesRegex(ValueError, 'control_hash'): remote.verify(self.root / 'derived', {**self.q, 'manifest_sha256': 'f' * 64})

    def test_trusted_remote_module_bootstrap_is_portable(self):
        code = pub.remote_source()
        self.assertNotIn(b'prototype-search', code.split(b'exec(compile', 1)[0])
        # Compile only, then import both modules without their CLI entry point.
        compile(code, '<trusted-remote>', 'exec')
        namespace = {'__name__': 'portable_test'}; exec(code, namespace)
        self.assertIn('Site', namespace); self.assertEqual(namespace['ROUTE'], remote.ROUTE)


if __name__ == '__main__': unittest.main()
