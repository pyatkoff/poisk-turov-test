"""Offline tests; MATCH_HISTORY_DIR supplies already downloaded immutable archives."""
import copy
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest

PATH = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/hotel_match_retained_history_crosscheck.py'
SPEC = importlib.util.spec_from_file_location('history_screen', PATH)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


def dossier(local=7, native='40'):
    return {'local_hotel_id': local, 'hotel_name': 'Hotel', 'status': 'strict_multi_lane_candidate',
            'strict_catalog_id': '99', 'direct_anex_ids': [native]}


def candidate(catalog='99', native=40):
    return {'catalog_id': catalog, 'names': ['Candidate'], 'lane_count': 2,
            'lanes': {ns: {'native_ids': [native] if ns == 'operator_5' else []} for ns in M.NS}}


def indexes(rows=None, plans=None, holds=None):
    return M.mass_indexes({'rows': rows or []}, {'new_candidate_plans': plans or [], 'edges': []}, {'holds': holds or []})


def source(local=8, state='accepted', namespace='andromeda_catalog'):
    return {'supplier_namespace': namespace, 'external_hotel_id': '99', 'local_hotel_id': local, 'decision_status': state}


def wrap(*sources):
    return [{'tv_hotel_id': 100, 'samo_hotel_id': '99', 'current_samo_identity': list(sources)}]


class HistoryTests(unittest.TestCase):
    def test_global_source_occupancy_is_not_restricted_to_old_tv_target(self):
        row = M.mass_assess(dossier(), candidate(), indexes(wrap(source())), {})
        self.assertIn('historical_source_accepted_other_target', row['historical_hold_reasons'])
        self.assertFalse(row['safe_to_write_now'])

    def test_duplicate_historical_rows_do_not_overwrite_conflict(self):
        row = M.mass_assess(dossier(), candidate(), indexes(wrap(source(), source(7))), {})
        self.assertIn('historical_source_accepted_other_target', row['historical_hold_reasons'])

    def test_historical_same_owner_never_approves_current_write(self):
        row = M.mass_assess(dossier(), candidate(), indexes(wrap(source(7))), {})
        self.assertEqual(row['historical_hold_reasons'], [])
        self.assertFalse(row['safe_to_write_now'])

    def test_pending_source_is_reconciliation_not_blind_insert(self):
        row = M.mass_assess(dossier(), candidate(), indexes(wrap(source(None, 'pending'))), {})
        self.assertEqual(row['historical_hold_reasons'], ['historical_source_pending'])

    def test_source_namespace_not_inferred_from_numeric_id(self):
        row = M.mass_assess(dossier(), candidate(), indexes(wrap(source(namespace='operator_115'))), {})
        self.assertEqual(row['historical_hold_reasons'], [])

    def test_category_discrepancy_is_exact_local_catalog_pair(self):
        plans = [{'tv_hotel_id': 7, 'external_hotel_id': '98', 'reasons': ['category_discrepancy']}]
        self.assertEqual(M.mass_assess(dossier(), candidate(), indexes(plans=plans), {})['historical_hold_reasons'], [])
        plans[0]['external_hotel_id'] = '99'
        self.assertIn('historical_canonical_category_discrepancy', M.mass_assess(dossier(), candidate(), indexes(plans=plans), {})['historical_hold_reasons'])

    def test_coordinate_hold_requires_exact_local_and_native_pair(self):
        holds = [{'local_hotel_id': 7, 'native_anex_hotel_id': 41, 'reason': 'coordinate_conflict_gt5km', 'distance_m': 9000}]
        self.assertEqual(M.mass_assess(dossier(), candidate(), indexes(holds=holds), {})['historical_hold_reasons'], [])
        holds[0]['native_anex_hotel_id'] = 40
        row = M.mass_assess(dossier(), candidate(), indexes(holds=holds), {})
        self.assertIn('historical_direct_anex_coordinate_conflict_gt5km', row['historical_hold_reasons'])
        self.assertFalse(row['historical_evidence'][0]['distance_inputs_independently_verified'])

    def test_coordinate_hold_does_not_taint_another_local(self):
        holds = [{'local_hotel_id': 8, 'native_anex_hotel_id': 40, 'reason': 'coordinate_conflict_gt5km'}]
        self.assertEqual(M.mass_assess(dossier(), candidate(), indexes(holds=holds), {})['historical_hold_reasons'], [])

    def test_no_anex_observation_is_not_numeric_equality(self):
        holds = [{'local_hotel_id': 7, 'native_anex_hotel_id': 40, 'reason': 'coordinate_conflict_gt5km'}]
        c = candidate(); c['lanes']['operator_5']['native_ids'] = []
        self.assertEqual(M.mass_assess(dossier(), c, indexes(holds=holds), {})['historical_hold_reasons'], [])

    def test_selected_catalog_collision_preserved(self):
        row = M.mass_assess(dossier(), candidate(), indexes(), {'99': {7, 8}})
        self.assertIn('v65_selected_catalog_multiple_targets', row['historical_hold_reasons'])

    def test_historical_absence_is_never_write_permission(self):
        self.assertFalse(M.mass_assess(dossier(), candidate(), indexes(), {})['safe_to_write_now'])

    def test_invalid_identifiers_rejected(self):
        for value in (True, False, 1.0, '01', '-1', '', None, '1e3'):
            with self.subTest(value=value), self.assertRaises(ValueError):
                M.identifier(value)

    def test_duplicate_json_keys_rejected(self):
        with self.assertRaisesRegex(ValueError, 'duplicate_json_key'):
            M.json_value('{"id":1,"id":2}')

    def test_wrong_zip_rejected_before_parse(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'wrong.zip'; path.write_bytes(b'bad')
            with self.assertRaisesRegex(ValueError, 'input_hash'):
                M.load_zip(path, 'samo')

    def test_input_symlink_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'target'; path.write_bytes(b'example')
            link = Path(directory) / 'link'; link.symlink_to(path)
            with self.assertRaisesRegex(ValueError, 'input_file'):
                M.read_pinned(link, M.sh(b'example'))

    @unittest.skipUnless(os.environ.get('MATCH_HISTORY_DIR'), 'pinned fixtures unavailable')
    def test_all175_real_dossiers_and_provenance(self):
        root = Path(os.environ['MATCH_HISTORY_DIR'])
        data = {key: M.load_zip(root / filename, key) for key, filename in {
            'direct27': 'match_direct27_current.zip', 'canonical': 'match_samo34_canonical.zip',
            'anex': 'match_anex_details_history.zip', 'samo': 'match_v65_result.zip'}.items()}
        result = M.analyse_mass(data['samo'], data['direct27'], data['canonical'], data['anex'])
        self.assertEqual((result['input_dossiers'], result['candidate_pairs_reviewed']), (175, 161))
        self.assertEqual((result['risk_candidate_pairs'], result['risk_dossiers']), (15, 14))
        self.assertEqual(result['original_selected49_risk_ids'], [125, 1181, 1299, 1772, 1773, 65341, 76753, 108356])
        self.assertEqual(len(result['original_selected49_other_unvalidated_ids']), 41)
        self.assertEqual({key: len(value) for key, value in result['dossier_ids_by_disposition'].items()}, {
            'historical_or_batch_risk_requires_review': 14, 'no_candidate_in_retained_response': 57,
            'no_risk_detected_in_limited_history_not_validated': 104})
        for row in result['rows']:
            self.assertFalse(row['safe_to_write_now'])
            for evidence in row['historical_evidence']:
                raw = data[evidence['kind']]
                for part in evidence['row_path'].split('/'):
                    raw = raw[int(part)] if isinstance(raw, list) else raw[part]
                self.assertEqual(M.digest(raw), evidence['row_sha256'])
        self.assertNotIn('https://', json.dumps(result))
        self.assertNotIn('operatorLink', json.dumps(result))
        self.assertFalse(result['fresh_current_validation_performed'])
        self.assertEqual(result['accepted_mapping_count'], 0)
        again = M.analyse_mass(copy.deepcopy(data['samo']), data['direct27'], data['canonical'], data['anex'])
        self.assertEqual(result, again)
        changed = copy.deepcopy(data['samo']); changed['dossiers'].append(changed['dossiers'][0])
        with self.assertRaisesRegex(ValueError, 'duplicate_dossier'):
            M.analyse_mass(changed, data['direct27'], data['canonical'], data['anex'])

    @unittest.skipUnless(os.environ.get('MATCH_HISTORY_DIR'), 'pinned fixtures unavailable')
    def test_original_three_example_mode_preserved(self):
        root = Path(os.environ['MATCH_HISTORY_DIR'])
        report = M.load_report(PATH.parents[2] / 'reports/hotel-match-retained175-review-20260926.json')
        result = M.analyse(report, M.load_zip(root / 'match_direct27_current.zip', 'direct27'),
            M.load_zip(root / 'match_samo34_canonical.zip', 'canonical'), M.load_zip(root / 'match_anex_details_history.zip', 'anex'))
        self.assertEqual(result['historical_hold_count'], 3)
        self.assertEqual({row['local_hotel_id'] for row in result['rows']}, {1299, 1540, 148230})
        self.assertEqual(set(result['historical_input_zip_pins']), {'direct27', 'canonical', 'anex'})


if __name__ == '__main__':
    unittest.main()
