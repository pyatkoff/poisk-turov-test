import copy
import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import anex_search3_alias_review as review


class AliasReviewTests(unittest.TestCase):
    def setUp(self):
        self.row = {'external_id': 12,
                    'api': {'id': 12, 'name': 'Grand Pamir Hotel', 'country': 'Turkey',
                            'latitude': 41.0118, 'longitude': 28.9542},
                    'xml': {'id': 12, 'name': 'Grand Pamir Hotel', 'alternate_name': ''},
                    'status': 'review', 'reason': 'candidate_limit_reached'}
        self.candidate = {'id': 70, 'name': 'GRAND PAMIR HOTEL LALELI',
                          'country_name': 'Turkey', 'region_name': 'Istanbul',
                          'subregion_name': 'Laleli', 'latitude': 41.0118, 'longitude': 28.9542,
                          'aliases': [{'alias': 'Grand Pamir Hotel',
                                       'normalized_alias': 'grand pamir hotel', 'source': 'generated'}]}

    def item(self, key=12, candidates=None):
        candidates = copy.deepcopy(candidates if candidates is not None else [self.candidate])
        return {'key': key, 'candidates': candidates, 'candidate_set_complete': True,
                'fetch_limit': 4097, 'alias_set_complete': True, 'alias_fetch_limit': 8193,
                'alias_rows': sum(len(c['aliases']) for c in candidates),
                'query_scope': 'active_country_canonical_alias_or_geobox'}

    def test_generated_alias_can_supply_name_evidence_without_hiding_canonical_name(self):
        result = review.analyze(self.row, self.item())
        self.assertEqual(result['proposal_status'], 'strong_candidate')
        self.assertEqual(result['best']['matched_name_source'], 'generated')
        self.assertEqual(result['best']['matched_name'], 'Grand Pamir Hotel')
        self.assertEqual(result['best']['canonical_name'], 'GRAND PAMIR HOTEL LALELI')
        self.assertEqual(result['best']['name_similarity'], 1)
        self.assertFalse(result['automatic_acceptance'])

    def test_unverified_alias_that_could_compete_blocks_acceptance(self):
        candidate = copy.deepcopy(self.candidate)
        candidate['aliases'][0]['source'] = 'unknown'
        result = review.analyze(self.row, self.item(candidates=[candidate]))
        self.assertEqual(result['proposal_status'], 'review')
        self.assertEqual(result['proposal_reason'], 'unverified_alias_provenance')
        self.assertEqual(len(result['unsafe_aliases']), 1)

    def test_incomplete_candidate_or_alias_sentinel_is_rejected(self):
        item = self.item()
        item['candidate_set_complete'] = False
        with self.assertRaisesRegex(ValueError, 'completeness'):
            review.analyze(self.row, item)
        item = self.item()
        item['alias_set_complete'] = False
        with self.assertRaisesRegex(ValueError, 'completeness'):
            review.analyze(self.row, item)

    def test_section_qualifier_must_match_alias_and_current_canonical_name(self):
        row = copy.deepcopy(self.row)
        row['api']['name'] = 'Grand Pamir Palace Hotel'
        row['xml']['name'] = row['api']['name']
        candidate = copy.deepcopy(self.candidate)
        candidate['aliases'][0] = {'alias': row['api']['name'],
                                   'normalized_alias': 'grand pamir palace hotel', 'source': 'generated'}
        result = review.analyze(row, self.item(candidates=[candidate]))
        self.assertEqual(result['proposal_status'], 'review')
        self.assertEqual(result['proposal_reason'], 'hotel_section_difference')

    def test_checkpoint_roundtrip_uses_six_sequential_batches_and_never_replays(self):
        rows = {}
        pending = []
        for identifier in range(1, 13):
            row = copy.deepcopy(self.row)
            row['external_id'] = identifier
            row['api']['id'] = identifier
            row['xml']['id'] = identifier
            rows[identifier] = row
            pending.append({'anex_hotel_id': identifier, 'country_id': 4, 'country_name': 'Turkey'})
        responses = []
        def respond(source, request, maximum_bytes):
            self.assertLessEqual(len(request['queries']), 2)
            self.assertEqual(request['mode'], 'alias_review')
            self.assertEqual(maximum_bytes, 4000000)
            responses.append([q['key'] for q in request['queries']])
            return {'status': 'ok', 'items': [self.item(q['key']) for q in request['queries']]}
        with tempfile.TemporaryDirectory() as temp, \
                patch.object(review, 'source_rows', return_value=(rows, {'pinned': 'yes'}, [])), \
                patch.object(review.live, 'snapshot', return_value={'pending': pending}), \
                patch.object(review, 'protected', return_value={'history': 'same'}), \
                patch.object(review.owner, 'ssh_php', side_effect=respond) as ssh:
            directory = Path(temp)
            (directory / 'anex-checkpoint-source.json').write_text(json.dumps(
                {'artifact_id': review.BOOTSTRAP_ARTIFACT}))
            prepared = review.prepare(directory)
            self.assertEqual(len(prepared['reserved_ids']), 12)
            self.assertEqual(review.run(directory)['new_catalog_reads'], 12)
            self.assertEqual(ssh.call_count, 6)
            self.assertEqual(responses, [[1,2],[3,4],[5,6],[7,8],[9,10],[11,12]])
            summary = review.finalize(directory)
            self.assertEqual(summary['checked_ids'], 12)
            self.assertEqual(summary['proposal_counts'], {'strong_candidate': 12})
            ssh.reset_mock()
            self.assertEqual(review.run(directory)['new_catalog_reads'], 0)
            ssh.assert_not_called()

    def test_finalize_without_prepare_is_a_safe_noop(self):
        with tempfile.TemporaryDirectory() as temp:
            self.assertEqual(review.finalize(Path(temp)), {
                'status': 'not_prepared', 'checked_ids': 0,
                'supplier_requests': 0, 'new_catalog_reads': 0})

    def test_bootstrap_accepts_only_pinned_source_lineage(self):
        for artifact_id in review.BOOTSTRAP_SOURCE_ARTIFACTS:
            self.assertTrue(review.bootstrap_source_allowed(artifact_id))
        self.assertFalse(review.bootstrap_source_allowed(10080925594))
        self.assertFalse(review.bootstrap_source_allowed(None))


if __name__ == '__main__':
    unittest.main()
