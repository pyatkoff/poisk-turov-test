import copy
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('gaps', ROOT / 'scripts/diagnostics/anex_search3_gap_queue.py')
gaps = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gaps)


class GapQueueTests(unittest.TestCase):
    def setUp(self):
        self.queue = gaps.load_queue()
        self.cp = {'schema_version': 1, 'scope': 'preview', 'queue_sha256': gaps.QUEUE_SHA,
                   'rows': [], 'in_flight': [], 'completed_total': 0, 'remaining': 112}

    def test_bootstrap_only_from_reviewed_artifact(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp)
            (path / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': gaps.BOOTSTRAP_ARTIFACT}))
            self.assertEqual(gaps.restore(path, self.queue), self.cp)
            (path / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': gaps.BOOTSTRAP_ARTIFACT + 1}))
            with self.assertRaises(ValueError):
                gaps.restore(path, self.queue)
            gaps.save(path, self.cp, self.queue)
            self.assertEqual(gaps.restore(path, self.queue), self.cp)

    def test_merge_preserves_old_rows_and_rejects_replay(self):
        first, second = [r['anex_hotel_id'] for r in self.queue['rows'][:2]]
        self.cp['in_flight'] = [first]
        row = {'external_id': first, 'status': 'review', 'reason': 'competing_candidates', 'candidates': []}
        after = gaps.merge(self.cp, [row], self.queue)
        after['in_flight'] = [second]
        with self.assertRaises(ValueError):
            gaps.merge(after, [row], self.queue)
        result = gaps.merge(after, [dict(row, external_id=second)], self.queue)
        self.assertEqual(result['rows'][0], row)
        self.assertEqual(result['remaining'], 110)

    def test_inflight_cannot_overlap_completed(self):
        identifier = self.queue['rows'][0]['anex_hotel_id']
        self.cp.update(rows=[{'external_id': identifier, 'status': 'review'}], in_flight=[identifier], completed_total=1, remaining=111)
        with self.assertRaises(ValueError):
            gaps.validate_checkpoint(self.cp, self.queue)

    def test_bad_counts_and_foreign_ids_rejected(self):
        for cp in [dict(self.cp, remaining=0), dict(self.cp, in_flight=[99999999]),
                   dict(self.cp, in_flight=[r['anex_hotel_id'] for r in self.queue['rows'][:31]])]:
            with self.assertRaises(ValueError):
                gaps.validate_checkpoint(cp, self.queue)

    def test_strict_matching_reused(self):
        ns = {}
        exec(gaps.matching_source(), ns)
        api = {'id': 528, 'name': 'Sharm Holiday Resort', 'country': 'Egypt', 'latitude': 27.9, 'longitude': 34.3}
        xml = dict(api, alternate_name='')
        local = {'id': 425, 'name': api['name'], 'country_name': 'Египет', 'latitude': 27.9, 'longitude': 34.3}
        candidate = ns['candidate_rank'](api, xml, local)
        self.assertEqual(ns['geo_decision'](api, [candidate], 'same_record')[0], 'strong_candidate')
        self.assertEqual(ns['geo_decision'](api, [candidate, dict(candidate, id=426)], 'same_record')[0], 'review')
        self.assertEqual(ns['geo_decision'](api, [dict(candidate, distance_m=None)], 'same_record')[0], 'review')
        self.assertEqual(ns['geo_decision'](api, [dict(candidate, country_match=False)], 'same_record')[0], 'review')

    def test_delta_recomputes_evidence_and_rejects_forged_score(self):
        import hashlib
        ns = {}
        exec(gaps.matching_source(), ns)
        identifier = self.queue['rows'][0]['anex_hotel_id']
        original = {'external_id': identifier, 'name': 'Sharm Holiday Resort', 'alternate_name': '', 'country': 'Egypt', 'status': 'review'}
        xml = {'id': identifier, 'name': original['name'], 'alternate_name': '', 'town_id': None}
        api = dict(xml, country='Egypt', latitude=27.9, longitude=34.3)
        candidate = ns['candidate_rank'](api, xml, {'id': 425, 'name': original['name'], 'country_name': 'Egypt', 'latitude': 27.9, 'longitude': 34.3})
        row = {'external_id': identifier, 'status': 'strong_candidate', 'reason': 'name_country_coordinates',
               'xml': xml, 'api': api, 'candidates': [candidate]}
        self.cp.update(rows=[row], completed_total=1, remaining=111)
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            queue = copy.deepcopy(self.queue)
            for key, name, value in [('catalog_sha256', 'anex-hotel-catalog-match.json', {'matches': [original]}),
                                     ('geo_sha256', 'anex-hotel-geo-enrichment.json', {'rows': []})]:
                raw = json.dumps(value).encode()
                (directory / name).write_bytes(raw)
                queue['sources'][key] = hashlib.sha256(raw).hexdigest()
            gaps.save(directory, self.cp, queue)
            with patch.object(gaps, 'load_queue', return_value=queue):
                result = gaps.approved_delta(directory / gaps.CHECKPOINT)
                self.assertEqual(result['rows'][0]['catalog_hotel_id'], 425)
                candidate['distance_m'] = 9999
                gaps.save(directory, self.cp, queue)
                with self.assertRaises(ValueError):
                    gaps.approved_delta(directory / gaps.CHECKPOINT)

    def test_remote_rate_limit_stops_remaining_api_calls(self):
        import ast
        source = Path(gaps.__file__).read_text()
        node = next(n for n in ast.parse(source).body if isinstance(n, ast.FunctionDef) and n.name == 'remote_batch')
        ns = {'time': gaps.time, 'DETAILS_PHP': 'fixed'}
        exec(gaps.matching_source(), ns)
        exec(ast.get_source_segment(source, node), ns)
        selected = self.queue['rows'][:3]
        originals = {str(r['anex_hotel_id']): {'name': r['hotel_name'], 'alternate_name': '', 'status': 'review'} for r in selected}
        response = type('Response', (), {'returncode': 0, 'stdout': '{"status":"source_error","reason":"rate_limited"}'})()
        snapshot = type('Response', (), {'returncode': 0, 'stdout': '{"status":"ok","staging_total":8362}'})()
        with patch.object(ns['subprocess'], 'run', side_effect=[snapshot, response, snapshot]) as run:
            rows = ns['remote_batch'](selected, originals, 1)['rows']
        self.assertEqual(run.call_count, 3)  # two DB snapshots, only one supplier call
        self.assertEqual([r['reason'] for r in rows], ['rate_limited'] * 3)


if __name__ == '__main__':
    unittest.main()
