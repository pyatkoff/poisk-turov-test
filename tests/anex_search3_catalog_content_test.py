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

    def test_photo_readback_retains_real_request_count_and_avoids_tourvisor_writes(self):
        calls = []
        plan = content.batch_plan()
        payload = {'source_sha': 'a' * 40, 'verified_ids': [103], 'plan': plan}
        def run(command, **kwargs):
            source = command[-1]
            calls.append((source, json.loads(kwargs['input'])))
            if source == 'snapshot':
                result = {'status': 'ok', 'manual_hash': 'unchanged'}
            elif source == 'audit':
                result = {'status': 'ok'}
            elif source == 'photos':
                self.assertEqual(json.loads(kwargs['input'])['plan'], plan)
                result = {'status': 'completed', 'supplier_requests': 1}
            else:
                prior = sum(c[0] == 'content' for c in calls) > 1
                result = {'status': 'ok', 'supplier_requests': 0 if prior else 25,
                          'cached': prior, 'rows': [{'photos_added': prior}]}
            return SimpleNamespace(returncode=0, stdout=json.dumps(result))
        with patch.multiple(content, SNAPSHOT_PHP='snapshot', AUDIT_PHP='audit',
                            CONTENT_PHP='content', PHOTOS_PHP='photos', create=True), \
                patch.object(content.subprocess, 'run', side_effect=run):
            report = content.remote_run(payload)
        self.assertEqual([c[0] for c in calls], ['snapshot', 'audit', 'content', 'photos', 'content', 'snapshot'])
        self.assertEqual(report['anex']['supplier_requests'], 25)
        self.assertFalse(report['anex']['cached'])
        self.assertTrue(report['anex']['rows'][0]['photos_added'])
        self.assertEqual(report['media_repair']['status'], 'not_run')


if __name__ == '__main__':
    unittest.main()
