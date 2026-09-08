import copy
import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import anex_search3_cached_review as review


class CachedReviewTests(unittest.TestCase):
    def setUp(self):
        self.row = {'external_id': 12, 'api': {'id': 12, 'name': 'Hotel Example', 'country': 'Turkey',
                    'latitude': 36.5, 'longitude': 30.5},
                    'xml': {'id': 12, 'name': 'Hotel Example', 'alternate_name': ''},
                    'live_row_sha256': 'unchanged-error', 'geo_row_sha256': 'cached-geo', 'audit_row_sha256': 'audit'}
        self.candidate = {'id': 70, 'name': 'Hotel Example', 'country_name': 'Turkey',
                          'latitude': 36.5, 'longitude': 30.5}
        self.item = {'key': 12, 'candidates': [self.candidate], 'candidate_set_complete': True,
                     'fetch_limit': 4097, 'query_scope': 'active_country_name_or_geobox'}

    def test_complete_set_reproduces_strict_proposal_without_acceptance(self):
        before = copy.deepcopy(self.row)
        result = review.analyze(self.row, self.item)
        self.assertEqual(result['proposal_status'], 'strong_candidate')
        self.assertEqual(result['best']['id'], 70)
        self.assertEqual(result['inserted'], 0)
        self.assertEqual(self.row, before)
        self.item['candidates'].append(dict(self.candidate, id=71))
        self.assertEqual(review.analyze(self.row, self.item)['proposal_reason'], 'competing_candidates')
        self.item['candidates'] = [dict(self.candidate, name='Hotel Example Beach')]
        self.assertEqual(review.analyze(self.row, self.item)['proposal_reason'], 'hotel_section_difference')

    def test_sentinel_cannot_be_marked_complete_or_accepted(self):
        self.item['candidates'] = [dict(self.candidate, id=i) for i in range(1, 4098)]
        with self.assertRaisesRegex(ValueError, 'completeness'):
            review.analyze(self.row, self.item)
        self.item['candidate_set_complete'] = False
        self.assertEqual(review.analyze(self.row, self.item)['proposal_reason'], 'candidate_limit_reached')

    def test_query_requires_matching_country_and_exact_identity(self):
        observed = {'anex_hotel_id': 12, 'country_id': 4, 'country_name': 'Турция'}
        query = review.query_for(self.row, observed)
        self.assertEqual(query['key'], 12)
        self.assertEqual(query['country_id'], 4)
        with self.assertRaisesRegex(ValueError, 'country'):
            review.query_for(self.row, dict(observed, country_name='Египет'))
        with self.assertRaisesRegex(ValueError, 'country'):
            review.query_for(self.row, dict(observed, anex_hotel_id=13))

    def test_old_reservations_and_completed_results_are_not_replayed(self):
        completed = {'state': 'completed', 'results': ['saved']}
        cp = {'reserved_by': ['1', '1'], 'batches': [copy.deepcopy(completed),
              {'state': 'reserved', 'request': {'queries': [{'key': 12}]}},
              {'state': 'in_flight', 'request': {'queries': [{'key': 13}]}}]}
        self.assertEqual(review.recover(cp, ['1', '2']), [12, 13])
        self.assertEqual(cp['batches'][0], completed)
        with patch.object(review, 'load', return_value=(cp, {})), patch.object(review.owner, 'ssh_php') as ssh:
            self.assertEqual(review.run(Path('/unused'))['new_catalog_reads'], 0)
            ssh.assert_not_called()

    def test_unpinned_audit_and_missing_checkpoint_cannot_reset(self):
        with patch.object(review.live, 'restore') as restore:
            with self.assertRaisesRegex(ValueError, 'unchecked'):
                review.source_rows(Path('/unused'), '{}')
            restore.assert_not_called()
        with tempfile.TemporaryDirectory() as temp, patch.object(review.live, 'snapshot') as snapshot:
            path = Path(temp)
            (path / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': -1}))
            with self.assertRaisesRegex(ValueError, 'refusing reset'):
                review.prepare(path)
            snapshot.assert_not_called()

    def acceptance_fixture(self, directory):
        rows = {12: copy.deepcopy(self.row), 13: dict(copy.deepcopy(self.row), external_id=13,
                api=dict(self.row['api'], id=13), xml=dict(self.row['xml'], id=13))}
        sources = {'catalog_sha256': 'a' * 64, 'geo_sha256': 'b' * 64}
        admissions = [{'anex_hotel_id': i, 'country_id': 4, 'country_name': 'Турция'} for i in rows]
        request = {'mode': 'complete_review', 'queries': [review.query_for(rows[r['anex_hotel_id']], r) for r in admissions]}
        competing = dict(copy.deepcopy(self.item), key=13, candidates=[dict(self.candidate, name='Hotel Example Beach')])
        results = [review.analyze(rows[12], self.item), review.analyze(rows[13], competing)]
        checkpoint = {'schema_version': 1, 'scope': 'preview', 'kind': 'cached_source_error_complete_reviews',
                      'sources': sources, 'audit_sha256': review.PINNED_AUDIT_SHA,
                      'source_artifact_id': review.BOOTSTRAP_ARTIFACT,
                      'source_digests': {str(i): review.gaps.digest(row) for i, row in rows.items()},
                      'source_audit_raw': 'fixture', 'reserved_by': ['1', '1'], 'admissions': admissions,
                      'batches': [{'ids': [12, 13], 'request': request, 'request_sha256': review.gaps.digest(request),
                                   'protected_ids': [], 'state': 'completed', 'results': results,
                                   'results_sha256': review.gaps.digest(results)}]}
        review.owner.save(directory / review.CHECKPOINT, checkpoint)
        return rows, sources

    def test_acceptance_pins_result_reproduces_scores_and_excludes_non_strong(self):
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            rows, sources = self.acceptance_fixture(directory)
            path = directory / review.CHECKPOINT
            with patch.object(review, 'source_rows', return_value=(rows, sources)):
                with patch.object(review, 'CHECKED_CHECKPOINT_SHA', None):
                    with self.assertRaisesRegex(ValueError, 'unchecked cached-review checkpoint'):
                        review.approved_delta(path)
                with patch.object(review, 'CHECKED_CHECKPOINT_SHA', review.file_sha(path)):
                    delta = review.approved_delta(path)
                    self.assertEqual(delta['counts']['strong'], 1)
                    self.assertEqual(delta['rows'][0]['anex_hotel_id'], 12)
                    self.assertEqual(delta['rows'][0]['reason'], 'observed_cached_review:name_country_coordinates')
                    cp, _ = review.load(directory)
                    result = cp['batches'][0]['results'][0]
                    result['raw_candidates'][0]['name'] = 'Completely Different Hotel'
                    result['raw_candidates_sha256'] = review.gaps.digest(result['raw_candidates'])
                    cp['batches'][0]['results_sha256'] = review.gaps.digest(cp['batches'][0]['results'])
                    review.owner.save(path, cp)
                    with self.assertRaisesRegex(ValueError, 'unchecked cached-review checkpoint'):
                        review.approved_delta(path)
                with patch.object(review, 'CHECKED_CHECKPOINT_SHA', review.file_sha(path)):
                    with self.assertRaisesRegex(ValueError, 'score reproduction'):
                        review.approved_delta(path)

    def test_finalized_acceptance_has_no_database_or_supplier_calls_and_routes_are_exclusive(self):
        import anex_search_mapping_import as importer
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            rows, sources = self.acceptance_fixture(directory)
            path = directory / review.CHECKPOINT
            with patch.object(review, 'source_rows', return_value=(rows, sources)), \
                    patch.object(review, 'CHECKED_CHECKPOINT_SHA', review.file_sha(path)):
                delta = review.approved_delta(path)
                review.owner.save(directory / review.ACCEPTANCE, {'state': 'finalized', 'delta_sha256': review.gaps.digest(delta)})
                with patch.object(review.live, 'snapshot') as snapshot, patch.object(importer, 'ssh_import') as sql, \
                        patch.object(review.gaps, 'ssh_batch') as batch, patch.object(review.owner, 'ssh_php') as source:
                    result = review.accept(directory)
                    self.assertEqual(result['status'], 'already_finalized')
                    self.assertEqual(result['inserted'], 0)
                    snapshot.assert_not_called()
                    sql.assert_not_called()
                    batch.assert_not_called()
                    source.assert_not_called()
            with self.assertRaisesRegex(ValueError, 'one independent checkpoint'):
                importer.ssh_import(directory / 'mapping.json', saved_review_checkpoint=path,
                                    cached_review_checkpoint=path)

    def test_checkpoint_roundtrip_and_partial_registry_protection(self):
        rows = {12: self.row, 13: dict(self.row, external_id=13,
                                      api=dict(self.row['api'], id=13), xml=dict(self.row['xml'], id=13))}
        snapshot = {'pending': [{'anex_hotel_id': 12, 'country_id': 4, 'country_name': 'Turkey'}]}
        with tempfile.TemporaryDirectory() as temp, patch.object(review, 'source_rows', return_value=(rows, {})), \
                patch.object(review.live, 'snapshot', return_value=snapshot), \
                patch.object(review, 'protected', return_value={'history': 'same'}), \
                patch.object(review.owner, 'ssh_php', return_value={'status': 'ok', 'items': [self.item]}) as ssh:
            path = Path(temp)
            (path / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': review.BOOTSTRAP_ARTIFACT}))
            (path / review.audit.REPORT).write_text('retained audit bytes')
            prepared = review.prepare(path)
            self.assertEqual(prepared['reserved_ids'], [12])
            self.assertEqual(prepared['protected_ids'], [13])
            self.assertEqual(review.run(path)['new_catalog_reads'], 1)
            self.assertEqual(review.finalize(path)['checked_ids'], 1)
            self.assertEqual(review.run(path)['new_catalog_reads'], 0)
            ssh.assert_called_once()
            cp, _ = review.load(path)
            self.assertEqual(cp['source_audit_raw'], 'retained audit bytes')
            self.assertEqual(cp['batches'][0]['results'][0]['best']['id'], 70)


if __name__ == '__main__':
    unittest.main()
