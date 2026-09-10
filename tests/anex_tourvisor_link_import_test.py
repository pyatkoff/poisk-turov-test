#!/usr/bin/env python3
"""Checked real report + synthetic transport; no application credentials or network."""
import hashlib
import json
import os
from pathlib import Path
import stat
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import anex_tourvisor_link_import as links
import anex_search_mapping_import as importer

REPORT = Path(os.environ['ANEX_LINK_REVIEW_REPORT'])


def result_fixture():
    return {'status': 'imported', 'append_only': True, 'input_count': 4,
            'inserted': 4, 'updated': 0, 'readback_verified': True,
            'catalog_country_guard': 4, 'link_readback': [
                {'anex_hotel_id': a, 'catalog_hotel_id': t, 'status': 'verified_policy_mapping'}
                for a, t in links.PAIRS.items()]}


class LinkImportTests(unittest.TestCase):
    def test_only_four_checked_pairs(self):
        document = links.approved_delta(REPORT)
        self.assertTrue(document['append_only'])
        self.assertEqual(document['counts'], {'exact': 0, 'strong': 4, 'total': 4, 'unique_catalog_hotels': 4})
        self.assertEqual({r['anex_hotel_id']: r['catalog_hotel_id'] for r in document['rows']}, links.PAIRS)
        for row in document['rows']:
            self.assertEqual(importer.sanitize_row(row), row)

    def test_report_tampering_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / 'report.json'
            for raw in (REPORT.read_bytes() + b' ', b'{}', b'{"rows":[]}'):
                path.write_bytes(raw)
                with self.assertRaises(ValueError):
                    links.approved_delta(path)

    def test_receipt_before_transport_and_completed_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            receipt = Path(tmp) / 'receipt.json'
            def remote(*args, **kwargs):
                self.assertEqual(json.loads(receipt.read_bytes())['state'], 'reserved')
                self.assertEqual(stat.S_IMODE(receipt.stat().st_mode), 0o600)
                self.assertEqual(kwargs['link_review_checkpoint'], REPORT)
                return result_fixture()
            with patch.object(importer, 'ssh_import', side_effect=remote) as transport:
                self.assertEqual(links.apply(REPORT, receipt)['inserted'], 4)
                self.assertEqual(links.apply(REPORT, receipt)['status'], 'already_finalized')
                self.assertEqual(transport.call_count, 1)
            saved = json.loads(receipt.read_bytes())
            self.assertEqual(saved['state'], 'finalized')
            self.assertEqual(saved['result_sha256'], links.digest(saved['result']))

    def test_lost_response_is_never_replayed(self):
        with tempfile.TemporaryDirectory() as tmp:
            receipt = Path(tmp) / 'receipt.json'
            with patch.object(importer, 'ssh_import', side_effect=TimeoutError) as transport:
                with self.assertRaises(TimeoutError):
                    links.apply(REPORT, receipt)
                with self.assertRaisesRegex(ValueError, 'do_not_replay'):
                    links.apply(REPORT, receipt)
                self.assertEqual(transport.call_count, 1)
            self.assertEqual(json.loads(receipt.read_bytes())['state'], 'reserved')

    def test_bad_readback_does_not_finalize(self):
        with tempfile.TemporaryDirectory() as tmp:
            receipt = Path(tmp) / 'receipt.json'
            bad = result_fixture()
            bad['link_readback'][0]['catalog_hotel_id'] = 99
            with patch.object(importer, 'ssh_import', return_value=bad):
                with self.assertRaises(ValueError):
                    links.apply(REPORT, receipt)
            self.assertEqual(json.loads(receipt.read_bytes())['state'], 'reserved')

    def test_checkpoint_modes_are_exclusive(self):
        with self.assertRaisesRegex(ValueError, 'one independent checkpoint'):
            importer.ssh_import('unused', gap_checkpoint='unused', link_review_checkpoint=REPORT)

    def test_actual_importer_protocol_path(self):
        with tempfile.TemporaryDirectory() as tmp:
            mapping = Path(tmp) / 'mapping.json'
            def transport(command, **kwargs):
                messages = [json.loads(line) for line in kwargs['stdin'].read().splitlines()]
                meta = messages[0]
                rows = [m['row'] for m in messages[1:-1]]
                self.assertEqual({r['anex_hotel_id']: r['catalog_hotel_id'] for r in rows}, links.PAIRS)
                self.assertEqual(meta['sources']['gap_sha256'], links.REPORT_SHA256)
                self.assertTrue(meta['append_only'])
                self.assertEqual(command[0], 'ssh')
                self.assertEqual(meta['rows_digest'], hashlib.sha256(b''.join(importer.canonical(r)+b'\n' for r in rows)).hexdigest())
                self.assertFalse(any(k.startswith('ANYTOOUR_DEPLOY_') for k in kwargs['env']))
                result = result_fixture()
                result['mapping_digest'] = meta['mapping_digest']
                return SimpleNamespace(returncode=0, stdout=json.dumps(result).encode())
            env = {'ANYTOOUR_DEPLOY_SSH_KEY': 'synthetic-test-key', 'ANYTOOUR_DEPLOY_HOST': 'example.invalid',
                   'ANYTOOUR_DEPLOY_USER': 'synthetic-test'}
            with patch.dict(os.environ, env), patch.object(importer.subprocess, 'run', side_effect=transport) as mock:
                result = importer.ssh_import(mapping, link_review_checkpoint=REPORT)
                links.validate_result(result)
                self.assertEqual(mock.call_count, 1)

    def local_fixture(self, directory):
        root = Path(directory).resolve() / 'anytoour.ru'
        (root / 'data').mkdir(parents=True, mode=0o700)
        (root / 'data/db-v1.php').write_text('<?php // no database in this fixture\n')
        return root, root.parent / 'receipt.json'

    def test_local_protocol_receipt_and_completed_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            root, receipt = self.local_fixture(tmp)
            def run(command, **kwargs):
                self.assertEqual(command[0], 'php')
                self.assertEqual(Path(command[-1]).name, 'anex_search_mapping_writer.php')
                self.assertEqual(kwargs['cwd'], root)
                reservation = json.loads(receipt.read_bytes())
                self.assertEqual(reservation['state'], 'reserved')
                self.assertEqual(reservation['local_root'], str(root))
                messages = [json.loads(line) for line in kwargs['stdin'].read().splitlines()]
                self.assertEqual({m['row']['anex_hotel_id']: m['row']['catalog_hotel_id'] for m in messages[1:-1]}, links.PAIRS)
                self.assertTrue(messages[0]['append_only'])
                self.assertEqual(messages[-1]['mapping_digest'], messages[0]['mapping_digest'])
                self.assertEqual(messages[0]['sources']['gap_sha256'], links.REPORT_SHA256)
                return SimpleNamespace(returncode=0, stdout=json.dumps(dict(result_fixture(),
                    mapping_digest=messages[0]['mapping_digest'])).encode())
            with patch.object(links.subprocess, 'run', side_effect=run) as process, patch.object(importer, 'ssh_import') as ssh:
                self.assertEqual(links.apply(REPORT, receipt, root)['inserted'], 4)
                self.assertEqual(links.apply(REPORT, receipt, root)['status'], 'already_finalized')
                self.assertEqual(process.call_count, 1)
                ssh.assert_not_called()
            self.assertEqual(stat.S_IMODE(receipt.stat().st_mode), 0o600)

    def test_local_timeout_never_replays(self):
        with tempfile.TemporaryDirectory() as tmp:
            root, receipt = self.local_fixture(tmp)
            with patch.object(links.subprocess, 'run', side_effect=TimeoutError) as process:
                with self.assertRaises(TimeoutError): links.apply(REPORT, receipt, root)
                with self.assertRaisesRegex(ValueError, 'do_not_replay'): links.apply(REPORT, receipt, root)
                self.assertEqual(process.call_count, 1)
            self.assertEqual(json.loads(receipt.read_bytes())['state'], 'reserved')

    def test_local_unknown_cannot_be_retried_through_ssh(self):
        with tempfile.TemporaryDirectory() as tmp:
            root, receipt = self.local_fixture(tmp)
            with patch.object(links.subprocess, 'run', side_effect=TimeoutError), patch.object(importer, 'ssh_import') as ssh:
                with self.assertRaises(TimeoutError): links.apply(REPORT, receipt, root)
                with self.assertRaisesRegex(ValueError, 'do_not_replay'): links.apply(REPORT, receipt)
                ssh.assert_not_called()

    def test_local_root_and_receipt_boundaries_before_any_write(self):
        with tempfile.TemporaryDirectory() as tmp:
            root, receipt = self.local_fixture(tmp)
            wrong = root.parent / 'other-project'; wrong.mkdir()
            linked = root.parent / 'linked-site'; linked.symlink_to(root, target_is_directory=True)
            public = root.parent / 'public-state'; public.mkdir(mode=0o755); public.chmod(0o755)
            with patch.object(links.subprocess, 'run') as process:
                for target, path in [(wrong, receipt), (linked, receipt), (root, root/'receipt.json'), (root, public/'receipt.json')]:
                    with self.subTest(target=target, path=path), self.assertRaises(ValueError):
                        links.apply(REPORT, path, target)
                    self.assertFalse(path.exists())
                process.assert_not_called()

    def test_local_missing_or_external_db_helper_refused(self):
        with tempfile.TemporaryDirectory() as tmp:
            root, receipt = self.local_fixture(tmp)
            helper = root / 'data/db-v1.php'; helper.unlink()
            with self.assertRaisesRegex(ValueError, 'database_helper'): links.apply(REPORT, receipt, root)
            outside = root.parent / 'other.php'; outside.write_text('<?php')
            helper.symlink_to(outside)
            with self.assertRaisesRegex(ValueError, 'database_helper'): links.apply(REPORT, receipt, root)
            self.assertFalse(receipt.exists())

    def test_local_wrong_digest_leaves_unknown(self):
        with tempfile.TemporaryDirectory() as tmp:
            root, receipt = self.local_fixture(tmp)
            response = SimpleNamespace(returncode=0, stdout=json.dumps(dict(result_fixture(), mapping_digest='0'*64)).encode())
            with patch.object(links.subprocess, 'run', return_value=response) as process:
                with self.assertRaisesRegex(ValueError, 'digest_unconfirmed'): links.apply(REPORT, receipt, root)
                with self.assertRaisesRegex(ValueError, 'do_not_replay'): links.apply(REPORT, receipt, root)
                self.assertEqual(process.call_count, 1)


if __name__ == '__main__':
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(LinkImportTests)
    success = unittest.TextTestRunner(verbosity=2).run(suite).wasSuccessful()
    if success:
        document = links.approved_delta(REPORT)
        raw = importer.canonical(document) + b'\n'
        meta = {k: v for k, v in document.items() if k != 'rows'}
        meta.update(type='meta', protocol_version=1, mapping_digest=hashlib.sha256(raw).hexdigest(),
                    rows_digest=hashlib.sha256(b''.join(importer.canonical(r)+b'\n' for r in document['rows'])).hexdigest())
        importer.write_protocol(REPORT.with_name('protocol.ndjson'), meta, document['rows'])
    sys.exit(0 if success else 1)
