import copy
import json
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import andromeda_hotel_candidates as subject


def evidence():
    rows = [{'status': 'catalog_found', 'supplier_namespace': 'andromeda_catalog',
             'external_hotel_id': str(i), 'local_hotel_id': None, 'decision_status': 'unreviewed',
             'catalog': {'name': 'Hotel ' + str(i), 'state_key': '3'}, 'observed_names': []}
            for i in range(14)]
    return {'state': 'completed', 'provider': 'andromeda', 'country_id': '3',
            'accepted_mappings': 0, 'selection_enabled': False,
            'rows': rows + [{'status': 'operator_key_excluded'}] * 2}


def response(request):
    return {'status': 'ok', 'items': [
        {'key': q['key'], 'candidates': [{'id': 100 + q['key'], 'name': 'Candidate'}],
         'candidate_set_complete': True, 'alias_set_complete': True} for q in request['queries']]}


class CandidatesTest(unittest.TestCase):
    def test_scoped_queries_exclude_operator_ids(self):
        result = subject.plan(evidence())
        self.assertEqual(len(result['batches']), 7)
        self.assertEqual([q['key'] for b in result['batches'] for q in b['request']['queries']], list(range(1, 15)))
        self.assertTrue(all(q['country_id'] == 1 for b in result['batches'] for q in b['request']['queries']))

    def test_wrong_country_and_accepted_identity_rejected(self):
        for field, value in [('local_hotel_id', 123), ('supplier_namespace', 'operator_5')]:
            source = evidence()
            source['rows'][0][field] = value
            with self.assertRaises(ValueError): subject.plan(source)
        source = evidence()
        source['rows'][0]['catalog']['state_key'] = '4'
        with self.assertRaises(ValueError): subject.plan(source)

    def test_duplicate_identity_rejected(self):
        source = evidence()
        source['rows'][1] = copy.deepcopy(source['rows'][0])
        with self.assertRaises(ValueError): subject.plan(source)

    def test_capture_preserves_results_without_accepting(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'checkpoint.json'
            subject.save(path, subject.plan(evidence()), exclusive=True)
            result = subject.capture(path, response)
            self.assertEqual(len(result['review_rows']), 14)
            self.assertEqual(result['accepted_mappings'], 0)
            self.assertFalse(result['selection_enabled'])
            self.assertEqual(json.loads(path.read_bytes()), result)
            with self.assertRaises(ValueError): subject.capture(path, lambda _: self.fail('replay'))

    def test_interruption_preserves_completed_and_unknown(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'checkpoint.json'
            subject.save(path, subject.plan(evidence()), exclusive=True)
            calls = []
            def reader(request):
                calls.append(request)
                if len(calls) == 2: raise TimeoutError('sensitive transport details')
                return response(request)
            with self.assertRaisesRegex(ValueError, 'no retry'): subject.capture(path, reader)
            result = json.loads(path.read_bytes())
            self.assertEqual([b['state'] for b in result['batches']], ['completed', 'unknown'] + ['reserved'] * 5)
            self.assertNotIn('sensitive', path.read_text())
            with self.assertRaises(ValueError): subject.capture(path, lambda _: self.fail('replay'))

    def test_truncated_sets_remain_visible_for_review(self):
        request = subject.plan(evidence())['batches'][0]['request']
        result = response(request)
        result['items'][0]['candidate_set_complete'] = False
        self.assertFalse(subject.validate_response(request, result)['items'][0]['candidate_set_complete'])

    def test_mismatched_response_rejected(self):
        request = subject.plan(evidence())['batches'][0]['request']
        result = response(request)
        result['items'][1]['key'] = result['items'][0]['key']
        with self.assertRaises(ValueError): subject.validate_response(request, result)

    def test_reservation_cannot_overwrite(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'checkpoint.json'
            subject.save(path, {'state': 'completed'}, exclusive=True)
            with self.assertRaises(FileExistsError): subject.save(path, {}, exclusive=True)


if __name__ == '__main__':
    unittest.main()
