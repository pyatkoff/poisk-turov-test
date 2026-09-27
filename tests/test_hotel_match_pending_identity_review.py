"""Regression checks: reviewing an automatic pending row never authorizes writes."""
from __future__ import annotations
from copy import deepcopy
from hashlib import sha256
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import hotel_match_pending_identity_review as review


class PrimitiveTests(unittest.TestCase):
    def test_duplicate_json_rejected(self):
        with self.assertRaisesRegex(ValueError, 'duplicate_json_key'):
            review.decode(b'{"a":1,"a":2}')

    def test_nonfinite_json_rejected(self):
        with self.assertRaisesRegex(ValueError, 'nonfinite_json'):
            review.decode(b'{"a":NaN}')

    def test_positive_identifier(self):
        self.assertEqual(review.identifier('2000040084'), '2000040084')

    def test_boolean_not_identifier(self):
        with self.assertRaises(ValueError): review.identifier(True)

    def test_negative_not_coerced(self):
        with self.assertRaises(ValueError): review.identifier('-99')

    def test_canonical_key_order(self):
        self.assertEqual(review.digest({'b': 2, 'a': 1}), review.digest({'a': 1, 'b': 2}))

    def test_pointer_escaped_keys(self):
        self.assertEqual(review.pointer({'a/b': {'~x': [4]}}, '/a~1b/~0x/0'), 4)

    def test_pointer_negative_index(self):
        with self.assertRaisesRegex(ValueError, 'invalid_pointer_index'):
            review.pointer([4], '/-1')

    def test_provenance_wrong_report_digest(self):
        with tempfile.TemporaryDirectory() as d:
            p = Path(d)/'report.json'; p.write_text('{}')
            with self.assertRaisesRegex(ValueError, 'report_digest'):
                review.load_inputs(p, Path(d))

    def test_symlink_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            p = Path(d)/'source'; p.write_text('{}')
            link = Path(d)/'link'; link.symlink_to(p)
            with self.assertRaisesRegex(ValueError, 'input_not_regular_file'): review.checked_file(link)


class PinnedRealInputTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        path = os.environ.get('MATCH_DUAL_REPORT')
        directory = os.environ.get('MATCH_INPUT_DIR')
        if not path or not directory:
            raise unittest.SkipTest('Set MATCH_DUAL_REPORT and MATCH_INPUT_DIR for real-artifact checks')
        cls.report, cls.docs = review.load_inputs(Path(path), Path(directory))
        cls.base_model = review.model(cls.report, cls.docs)
        cls.base_pair = next(p for p in cls.report['catalog_pairs'] if p['local_hotel_id'] == 55945)
        cls.complete = review.build_review(cls.report, cls.docs)

    def setUp(self):
        self.pair = deepcopy(self.base_pair)
        self.m = dict(self.base_model)
        for name in ('source', 'target', 'evidence', 'by_native', 'by_hotel', 'hotels', 'documents'):
            self.m[name] = self.base_model[name].copy()
        self.local, self.catalog = '55945', '2000037585'
        self.key = ('andromeda_catalog', self.catalog)
        self.m['source'][self.key] = deepcopy(self.base_model['source'][self.key])
        self.m['evidence'][self.key] = deepcopy(self.base_model['evidence'][self.key])
        self.m['hotels'][self.local] = deepcopy(self.base_model['hotels'][self.local])

    def assess(self):
        return review.assess_pair(self.pair, self.m)

    def expect_hold(self, reason):
        result = self.assess()
        self.assertEqual(result['status'], 'hold')
        self.assertIn(reason, result['reasons'])
        self.assertFalse(result['safe_to_write_now'])
        self.assertFalse(result['existing_hold_cleared'])

    def test_eight_dossiers_not_acceptance(self):
        self.assertEqual(self.complete['summary']['pending_transition_dossiers'], 8)
        self.assertEqual(self.complete['summary']['newly_accepted_identities'], 0)
        for r in self.complete['pending_transition_dossiers']:
            self.assertFalse(r['safe_to_write_now']); self.assertFalse(r['existing_hold_cleared'])

    def test_full_24_hold_partition(self):
        self.assertEqual(self.complete['summary']['catalog_holds_reviewed'], 24)
        self.assertEqual(self.complete['summary']['other_holds_preserved'], 16)

    def test_full125_included(self):
        rows = self.complete['unmapped_samo_queue']
        self.assertEqual(len(rows), 125)
        self.assertEqual(len({r['andromeda_catalog_id'] for r in rows}), 125)
        self.assertTrue(all(r['local_mapping_required_to_plan_native_lookup'] is False for r in rows))

    def test_no_acquisition_scheduled(self):
        self.assertTrue(all(r['provider_calls_scheduled'] == 0 and
                            r['fresh_availability_context_bound'] is False and
                            r['provider_execution_authorized'] is False
                            for r in self.complete['unmapped_samo_queue']))

    def test_two_real_conflicts_excluded_from_acquisition(self):
        rows = [r for r in self.complete['unmapped_samo_queue'] if r['next_step'] == 'protected_conflict_no_automatic_acquisition']
        self.assertEqual({r['andromeda_catalog_id'] for r in rows}, {'150867', '2000073714'})

    def test_operator_only_signed_ids_preserved(self):
        self.assertEqual(self.complete['samo_operator_only_queue'], self.report['samo_operator_only'])
        self.assertEqual(len(self.complete['samo_operator_only_queue']), 283)

    def test_prior83_unchanged(self):
        self.assertEqual(self.complete['prior83_status'], 'unchanged_proposed_not_accepted')
        self.assertEqual(sum(p['status'] == 'candidate_requires_fresh_current' for p in self.report['direct_anex_candidates']), 82)

    def test_geography_not_falsely_complete(self):
        self.assertEqual(self.complete['summary']['additional_geographic_review_count'], 2)

    def test_anex_needed_for_full_triple_gain(self):
        self.m['hotels'][self.local]['current_anex_ids'] = []
        self.assertFalse(self.assess()['would_complete_tv_triple_if_accepted_and_unchanged'])

    def test_one_operator_is_sufficient_for_review(self):
        out = self.assess()
        self.assertEqual(out['exact_operator_count'], 1)
        self.assertEqual(out['status'], 'pending_transition_review_not_writer_ready')

    def test_missing_pending_row(self):
        self.m['source'][self.key] = []
        self.expect_hold('requires_one_existing_pending_row')

    def test_multiple_pending_rows(self):
        self.m['source'][self.key] *= 2
        self.expect_hold('requires_one_existing_pending_row')

    def test_accepted_source_preserved(self):
        self.m['source'][self.key][0]['decision_status'] = 'accepted'
        self.expect_hold('accepted_conflicting_invalid_or_nonpending_source')

    def test_rejected_source_preserved(self):
        self.m['source'][self.key][0]['decision_status'] = 'rejected'
        self.expect_hold('accepted_conflicting_invalid_or_nonpending_source')

    def test_conflict_source_preserved(self):
        self.m['source'][self.key][0]['decision_status'] = 'conflict'
        self.expect_hold('accepted_conflicting_invalid_or_nonpending_source')

    def test_pending_assigned_target_preserved(self):
        self.m['source'][self.key][0]['local_hotel_id'] = 123
        self.expect_hold('accepted_conflicting_invalid_or_nonpending_source')

    def test_invalid_evidence_preserved(self):
        self.m['source'][self.key][0]['evidence_valid'] = False
        self.expect_hold('accepted_conflicting_invalid_or_nonpending_source')

    def test_historical_geo_conflict_preserved(self):
        self.pair['reasons'].append('historical:coordinate_conflict')
        self.expect_hold('independent_historical_or_current_hold')

    def test_historical_category_conflict_preserved(self):
        self.pair['reasons'].append('historical:category_conflict')
        self.expect_hold('independent_historical_or_current_hold')

    def test_missing_prior_evidence(self):
        self.m['evidence'][self.key] = []
        self.expect_hold('missing_unique_saved_prior_evidence')

    def test_manual_reason_preserved(self):
        self.m['evidence'][self.key][0]['hotel_evidence']['reason'] = 'manual_review_required'
        self.expect_hold('pending_reason_requires_manual_review')

    def test_extra_manual_field_preserved(self):
        self.m['evidence'][self.key][0]['hotel_evidence']['manual'] = True
        self.expect_hold('extra_prior_evidence_requires_review')

    def test_evidence_digest_drift(self):
        self.m['evidence'][self.key][0]['raw_evidence_sha256'] = 'a'*64
        self.expect_hold('prior_identity_digest_binding')

    def test_source_catalog_substitution(self):
        self.m['evidence'][self.key][0]['hotel_evidence']['source']['id'] = 123
        self.expect_hold('source_catalog_binding')

    def test_target_inactive(self):
        self.m['hotels'][self.local]['hotel']['is_active'] = 0
        self.expect_hold('missing_active_valid_current_target')

    def test_missing_target(self):
        del self.m['hotels'][self.local]
        self.expect_hold('missing_active_valid_current_target')

    def test_target_catalog_occupied(self):
        self.m['target'][self.local, 'andromeda_catalog'] = [{'external_hotel_id': '99'}]
        self.expect_hold('target_catalog_occupied')

    def test_country_mismatch(self):
        self.m['hotels'][self.local]['hotel']['country_name'] = 'Другая страна'
        self.expect_hold('country_conflict')

    def test_country_missing(self):
        self.m['hotels'][self.local]['hotel']['country_name'] = None
        self.expect_hold('missing_country_binding')

    def test_no_name_only_acceptance(self):
        self.pair['proofs'] = []
        self.expect_hold('no_exact_same_operator_proof')

    def test_samo_proof_missing(self):
        self.pair['proofs'][0]['samo'] = []
        self.expect_hold('missing_either_source_proof')

    def test_tv_proof_missing(self):
        self.pair['proofs'][0]['tv'] = []
        self.expect_hold('missing_either_source_proof')

    def test_global_native_collision(self):
        self.m['by_native']['tv', 'operator_315', '295561'] = {'55945', '999'}
        self.expect_hold('global_native_or_hotel_collision')

    def test_global_catalog_collision(self):
        self.m['by_native']['samo', 'operator_315', '295561'] = {'2000037585', '999'}
        self.expect_hold('global_native_or_hotel_collision')

    def test_tv_multi_native(self):
        self.m['by_hotel']['tv', self.local, 'operator_315'] = {'295561', '999'}
        self.expect_hold('global_native_or_hotel_collision')

    def test_operator_occupied(self):
        self.m['source']['operator_315', '295561'] = [{'decision_status': 'accepted', 'evidence_valid': True, 'local_hotel_id': 999}]
        self.expect_hold('operator_identity_occupied_or_protected')

    def test_proof_hash_substitution(self):
        self.pair['proofs'][0]['tv'][0]['row_sha256'] = '0'*64
        with self.assertRaisesRegex(ValueError, 'proof_row_digest'): self.assess()

    def test_proof_archive_substitution(self):
        self.pair['proofs'][0]['samo'][0]['artifact_id'] = 123
        with self.assertRaisesRegex(ValueError, 'proof_archive_binding'): self.assess()

    def test_proof_hotel_substitution(self):
        self.pair['proofs'][0]['samo'][0]['hotel_id'] = '123'
        with self.assertRaisesRegex(ValueError, 'proof_identity_binding'): self.assess()

    def test_pointer_substitution(self):
        self.pair['proofs'][0]['samo'][0]['json_pointer'] = '/dossiers/105/candidates/0/lanes/operator_315'
        with self.assertRaisesRegex(ValueError, 'proof_row_digest'): self.assess()

    def test_cross_namespace_numeric_not_accepted(self):
        self.pair['proofs'][0]['namespace'] = 'operator_5'
        self.expect_hold('cross_namespace_not_authority')

    def test_no_input_mutation(self):
        before = review.digest(self.pair)
        old = review.digest(self.m['source'][self.key])
        self.assess()
        self.assertEqual(review.digest(self.pair), before)
        self.assertEqual(review.digest(self.m['source'][self.key]), old)


if __name__ == '__main__':
    unittest.main()
