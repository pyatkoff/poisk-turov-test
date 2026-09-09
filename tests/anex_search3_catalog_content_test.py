import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest
from types import SimpleNamespace
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import anex_search3_catalog_content as content


class ContentSources(unittest.TestCase):
    def test_explicit_plan_is_bounded_and_cannot_reuse_pilot_keys(self):
        plan = content.batch_plan()
        self.assertEqual(plan['limit'], 25)
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / 'plan.json'
            for change in ({'limit': 26}, {'limit': True}, {'batch_key': 'owner_content_pilot_20260909'},
                           {'photos_batch_key': 'owner_content_photos_pilot_20260909'}):
                path.write_text(json.dumps(dict(plan, **change)))
                with self.assertRaises(ValueError):
                    content.batch_plan(path)

    def test_only_confirmed_same_identity_is_eligible(self):
        good = {'external_id': 17, 'api_xml_relation': 'same_record', 'api': {'id': 17, 'name': 'Hotel'}}
        rows = [good, good, {'external_id': 19, 'api_xml_relation': 'same_record', 'api': {'id': 18, 'name': 'Wrong'}},
                {'external_id': 20, 'api_xml_relation': 'unknown', 'api': {'id': 20, 'name': 'Unknown'}},
                {'external_id': 21, 'status': 'source_error'}]
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            for name in ('anex-hotel-geo-enrichment.json', 'anex-initial-search-checkpoint.json', 'anex-observed-hotel-checkpoint.json'):
                (directory / name).write_text(json.dumps({'rows': rows}))
            self.assertEqual(content.verified_ids(directory), [17])

    def test_no_verified_source_does_not_start_a_new_queue(self):
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            for name in ('anex-hotel-geo-enrichment.json', 'anex-initial-search-checkpoint.json', 'anex-observed-hotel-checkpoint.json'):
                (directory / name).write_text(json.dumps({'rows': []}))
            with self.assertRaises(ValueError):
                content.verified_ids(directory)

    def test_remote_only_reads_preserved_content(self):
        calls = []
        def run(command, **kwargs):
            calls.append(command[-1])
            return SimpleNamespace(returncode=0, stdout=json.dumps({'status': 'ok',
                'read_only': True, 'supplier_requests': 0, 'rows': []}))
        with patch.object(content, 'INSPECT_PHP', 'saved-only-reader', create=True), \
                patch.object(content.subprocess, 'run', side_effect=run):
            report = content.remote_run({'source_sha': 'a' * 40})
        self.assertEqual(calls, ['saved-only-reader'])
        self.assertEqual(report['supplier_requests'], 0)
        self.assertTrue(report['read_only'])



if __name__ == '__main__':
    unittest.main()
