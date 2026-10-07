#!/usr/bin/env python3
"""Synthetic actual-CLI/retention tests; no supplier or CURRENT identity proof."""
import contextlib
import copy
import hashlib
import importlib
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
module = importlib.import_module('hotel_match_native_absent3_retained_fields_readonly_v1')
SOURCE = 'a' * 40


class NativeAbsent3Fields(unittest.TestCase):
    def make_case(self, root, change=None, absent=None):
        home = Path(root) / 'home'
        project = home / 'www/anytoour.ru'
        project.mkdir(parents=True)
        cache = home / '.fixture-cache'
        opdir = home / '.anytoour-match/operations' / module.OP
        opdir.mkdir(parents=True, mode=0o700)
        fixture = copy.deepcopy(module.manifest())
        raws = []
        for i, spec in enumerate(fixture['rows']):
            row = {'provider': 'andromeda', 'supplier_namespace': 'andromeda_catalog',
                'external_hotel_id': spec['catalog_id'], 'local_hotel_id': None, **spec['expected_offer'],
                'hotel_content': {'hotel_url': 'https://www.bgoperator.ru/price.shtml?code=102616053529',
                    'region': 'Alanya', 'category': 4, 'image_url': 'https://example.org/photo.jpg', 'source': 'andromeda'}}
            if change:
                change(i, row)
            index = int(spec['json_pointer'].split('/')[-1])
            offers = [{'unrelated_private': 'preserve-raw-only'} for _ in range(index + 1)]
            offers[index] = row
            data = {'criteria': spec['expected_criteria'], 'store': {'created_at': spec['created_at'],
                'criteria': spec['expected_criteria'], 'snapshot': {'offers': offers}},
                'private_test_value': 'never-public'}
            raw = module.retained.private_bytes(data)
            spec['sha256'] = hashlib.sha256(raw).hexdigest()
            target = cache / spec['source_file']
            target.parent.mkdir(parents=True, exist_ok=True)
            if absent != i:
                target.write_bytes(raw)
            raws.append(raw)
        fixture_path = Path(root) / 'fixture.json'
        encoded = module.n.enc(fixture)
        fixture_path.write_bytes(encoded)
        module.n.save(opdir / 'reservation.json', {'operation': module.OP, 'batch': module.BATCH,
            'source_sha': SOURCE, 'provider_http_calls': 0, 'maximum_writes': 0,
            'state': 'reserved_before_retained_read'})
        return dict(project=project, cache=cache, opdir=opdir, fixture=fixture,
                    fixture_path=fixture_path, digest=hashlib.sha256(encoded).hexdigest(), raws=raws)

    @contextlib.contextmanager
    def bound(self, case):
        with patch.object(module, 'FIXTURE', case['fixture_path']), patch.object(module, 'MANIFEST_SHA', case['digest']), patch.dict(os.environ, {'MATCH_SOURCE_SHA': SOURCE}):
            # Default argument of manifest was bound at import; the fixed file is explicitly supplied.
            original = module.manifest
            with patch.object(module, 'manifest', side_effect=lambda path=None: original(case['fixture_path'] if path is None else path)):
                yield

    def run_case(self, case):
        with self.bound(case), contextlib.redirect_stdout(io.StringIO()):
            module.execute(case['project'], case['opdir'], case['cache'], case['fixture_path'])
        return json.loads((case['opdir'] / 'result.json').read_bytes())

    def test_original_fixture_and_cli_disabled_without_exact_action(self):
        self.assertEqual(module.manifest()['requested_catalog_candidates'], 3)
        runner = ROOT / 'scripts/diagnostics/hotel_match_native_absent3_retained_fields_readonly_v1.py'
        call = subprocess.run([sys.executable, str(runner), '--self-test'], capture_output=True, text=True)
        self.assertEqual(call.returncode, 0, call.stderr)
        bad = subprocess.run([sys.executable, str(runner), '--execute', 'extra'], capture_output=True, text=True)
        self.assertNotEqual(bad.returncode, 0)

    def test_actual_execute_cli_uses_fixed_snapshots_and_complete_receipt(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            stage = Path(root) / 'stage/scripts/diagnostics'
            (stage / 'fixtures').mkdir(parents=True)
            for name in ('hotel_match_operator115_only2_retained_fields_readonly_v1.py',
                         'hotel_match_nonbg7_unexported_fields_readonly_v1.py',
                         'hotel_match_bg5_unexported_fields_readonly_v1.py'):
                shutil.copyfile(ROOT / 'scripts/diagnostics' / name, stage / name)
            runner = stage / 'hotel_match_native_absent3_retained_fields_readonly_v1.py'
            source = (ROOT / 'scripts/diagnostics' / runner.name).read_text()
            runner.write_text(source.replace(module.MANIFEST_SHA, case['digest']))
            fixture = stage / 'fixtures' / case['fixture_path'].name
            fixture = fixture.with_name('hotel_match_native_absent3_retained_fields_readonly_v1.json')
            shutil.copyfile(case['fixture_path'], fixture)
            env = {'PATH': os.environ['PATH'], 'MATCH_SOURCE_SHA': SOURCE,
                   'ANYTOUR_ROOT': str(case['project']), 'MATCH_OPERATION_DIR': str(case['opdir']),
                   'MATCH_CACHE_ROOT': str(case['cache']), 'MATCH_MANIFEST_PATH': str(fixture)}
            run = subprocess.run([sys.executable, str(runner), '--execute'], env=env, capture_output=True, text=True)
            self.assertEqual(run.returncode, 0, run.stderr)
            stdout = json.loads(run.stdout)
            self.assertEqual(stdout['rows_examined'], 13)
            receipt = json.loads((case['opdir'] / 'receipt.json').read_bytes())
            result = json.loads((case['opdir'] / 'result.json').read_bytes())
            private = json.loads((case['opdir'] / 'current-input.json').read_bytes())
            with self.bound(case):
                module.validate_result(result, receipt, SOURCE, private)
            self.assertEqual(set(receipt), set(module.RECEIPT_KEYS) | {'result_sha256'})

    def test_original_capture_precedes_optional_projection_and_fsync_is_required(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            real_field = module.field
            def field(row, name):
                self.assertTrue(all((case['opdir'] / f'retained-{i:02d}.json').exists() for i in range(13)))
                self.assertTrue((case['opdir'] / 'current-input.json').exists())
                return real_field(row, name)
            with patch.object(module, 'field', side_effect=field):
                self.run_case(case)
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            with patch.object(os, 'fsync', side_effect=OSError('test-only fsync failure')), self.assertRaises(OSError):
                self.run_case(case)
            self.assertFalse((case['opdir'] / 'result.json').exists())

    def test_invalid_unicode_optional_field_is_private_and_individual_hold(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root, lambda i, row: row['hotel_content'].update(region='\ud800') if i == 0 else None)
            data = self.run_case(case)
            self.assertEqual(data['raw_references_verified'], 13)
            self.assertEqual(data['rows'][0]['fields'][4]['hold'], 'optional_field_invalid_unicode')
            self.assertTrue(all(r['fields'][4]['hold'] is None for r in data['rows'][1:]))

    def test_all_original_bytes_before_filtering_raw_names_and_zero_authority(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            data = self.run_case(case)
            self.assertEqual(data['raw_references_verified'], 13)
            self.assertEqual(data['requested_catalog_candidates'], 3)
            self.assertEqual(data['rows'][0]['fields'][0]['value'], 'A11 Hotel Obakoy ')
            self.assertEqual(data['rows'][0]['independent_tv_hotel_id'], None)
            for i, raw in enumerate(case['raws']):
                target = case['opdir'] / f'retained-{i:02d}.json'
                self.assertEqual(target.read_bytes(), raw)
                self.assertEqual(target.stat().st_mode & 0o777, 0o600)
            self.assertNotIn('never-public', json.dumps(data))
            for key in module.ZERO:
                self.assertEqual(data[key], 0)
                self.assertIs(type(data[key]), int)
            for key in module.FALSE:
                self.assertIs(data[key], False)
            self.assertIs(data['original_supplier_response_proven'], False)

    def test_bad_optional_field_keeps_12_independent_rows_and_original_object(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root, lambda i, row: row['hotel_content'].update(hotel_url={'private': 'keep-exact'}) if i == 0 else None)
            data = self.run_case(case)
            self.assertEqual(data['state'], module.STATES[1])
            self.assertEqual(data['raw_references_verified'], 13)
            self.assertTrue(data['rows'][0]['fields'][3]['hold'])
            self.assertTrue(all(row['fields'][3]['hold'] is None for row in data['rows'][1:]))
            self.assertIn('keep-exact', (case['opdir'] / 'current-input.json').read_text())
            self.assertNotIn('keep-exact', json.dumps(data))

    def test_one_missing_file_is_one_hold_not_global_absence(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root, absent=0)
            data = self.run_case(case)
            self.assertEqual(data['raw_references_verified'], 12)
            self.assertFalse(data['rows'][0]['raw_verified'])
            self.assertIn(module.ROW_FAILURES[0], data['rows'][0]['holds'])
            self.assertIs(data['acceptance_evaluated'], False)

    def test_digest_and_identity_changes_never_become_verified(self):
        for kind in ('digest', 'identity'):
            with self.subTest(kind=kind), tempfile.TemporaryDirectory() as root:
                case = self.make_case(root, lambda i, row: row.update(external_hotel_id='999') if kind == 'identity' and i == 0 else None)
                if kind == 'digest':
                    path = case['cache'] / case['fixture']['rows'][0]['source_file']
                    path.write_bytes(path.read_bytes() + b' ')
                data = self.run_case(case)
                self.assertEqual(data['raw_references_verified'], 12)
                self.assertFalse(data['rows'][0]['raw_verified'])

    def test_public_private_rehash_cannot_replace_protected_original(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            self.run_case(case)
            private = json.loads((case['opdir'] / 'current-input.json').read_bytes())
            private['rows'][0]['original_row']['hotel_content']['hotel_url'] = 'https://example.org/forged'
            with self.bound(case), self.assertRaisesRegex(ValueError, 'original_row_binding'):
                module.verify_originals(case['opdir'], private, module.manifest())

    def test_boolean_counter_and_readiness_forgery_rejected(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            data = self.run_case(case)
            private = json.loads((case['opdir'] / 'current-input.json').read_bytes())
            for key, value in [('provider_http_calls', False), ('namespace_bridge_verified', True), ('original_supplier_response_proven', True)]:
                forged = copy.deepcopy(data)
                forged[key] = value
                with self.bound(case), self.assertRaises(ValueError):
                    module.validate_result(forged, expected_source=SOURCE, private_input=private)

    def test_no_replay_even_when_outputs_are_removed(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            self.run_case(case)
            for name in ('current-input.json', 'result.json', 'receipt.json'):
                (case['opdir'] / name).unlink()
            with self.assertRaisesRegex(ValueError, 'operation_consumed_no_replay'):
                self.run_case(case)

    def test_snapshot_symlink_is_held_and_read_error_does_not_leak(self):
        with tempfile.TemporaryDirectory() as root:
            case = self.make_case(root)
            path = case['cache'] / case['fixture']['rows'][0]['source_file']
            backup = path.with_suffix('.private')
            path.rename(backup)
            path.symlink_to(backup)
            data = self.run_case(case)
            self.assertFalse(data['rows'][0]['raw_verified'])
            self.assertNotIn(str(backup), json.dumps(data))


if __name__ == '__main__':
    unittest.main()
