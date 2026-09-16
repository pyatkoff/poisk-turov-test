"""Offline identity/provenance tests; no provider or application DB is accessed."""
import copy
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest

SOURCE = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/hotel_match_frontier_retained_join.py'
spec = importlib.util.spec_from_file_location('retained_join', SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


def fixture(count=1):
    queue = [{'supplier_namespace': 'andromeda_catalog', 'andromeda_hotel_id': str(1000+i),
              'country_id': 1, 'country_name': 'Egypt', 'state_key': 3,
              'evidence_sha256': f'{i+1:064x}', 'names': [f'Example {i} Beach'],
              'frequency': None, 'safe_to_write_now': False} for i in range(count)]
    prior = [{'supplier_namespace': 'andromeda_catalog', 'external_hotel_id': r['andromeda_hotel_id'],
              'evidence_sha256': r['evidence_sha256']} for r in queue]
    f = {'queue': queue, 'queue_count': count, 'holds': [], 'read_at_utc': '2026-09-16T12:44:11Z'}
    s = {'current_pending': prior, 'evidence_rows': [], 'read_at_utc': '2026-09-15T14:15:22Z'}
    return f, s


def evidence(identifier='1000', title='Example Beach', kind='saved_HOTELS_TOWNTO', file_hash='a'*64):
    return {'external_hotel_id': identifier, 'source_kind': kind, 'country_id': 1,
            'supplier_state_key': '3', 'retained_file_sha256': file_hash,
            'original_http_response_sha256': None,
            'hotel_fields': {'name': title, 'hotel': title, 'stateKey': '3'},
            'urls': [], 'typed_town_links': {}, 'holds': [], 'safe_to_write_now': False}


class JoinTests(unittest.TestCase):
    def test_mass_2000_rows_not_truncated(self):
        f, s = fixture(2000)
        s['evidence_rows'] = [evidence(r['andromeda_hotel_id']) for r in f['queue'][::2]]
        r = m.join(f, s)
        self.assertEqual((r['summary']['queue_rows'], r['summary']['with_retained_metadata']), (2000, 1000))
        self.assertEqual({d['external_hotel_id'] for d in r['dossiers']}, {x['andromeda_hotel_id'] for x in f['queue']})
        self.assertTrue(all(d['safe_to_write_now'] is False for d in r['dossiers']))

    def test_missing_evidence_keeps_frequency_unknown(self):
        f, s = fixture()
        d = m.join(f, s)['dossiers'][0]
        self.assertIsNone(d['frontier']['frequency'])
        self.assertIsNone(d['retained_search_documents_lower_bound'])
        self.assertEqual(d['route'], 'not_covered_by_this_retained_artifact')

    def test_explicit_zero_and_unknown_are_distinct(self):
        f, s = fixture(2)
        f['queue'][0]['frequency'] = 0
        r = m.join(f, s)
        self.assertEqual(r['summary']['current_frequency_unknown'], 1)
        self.assertEqual(r['dossiers'][0]['frontier']['frequency'], 0)

    def test_changed_digest_is_held_not_accepted(self):
        f, s = fixture(); s['current_pending'][0]['evidence_sha256'] = 'b'*64
        s['evidence_rows'] = [evidence()]
        d = m.join(f, s)['dossiers'][0]
        self.assertIn('retained_source_digest_changed_or_missing', d['evidence_flags'])
        self.assertIs(d['safe_to_write_now'], False)

    def test_duplicate_frontier_is_rejected(self):
        f, s = fixture(); f['queue'].append(copy.deepcopy(f['queue'][0]))
        with self.assertRaisesRegex(ValueError, 'duplicate_identity'): m.join(f, s)

    def test_duplicate_saved_identity_is_rejected(self):
        f, s = fixture(); s['current_pending'] += copy.deepcopy(s['current_pending'])
        with self.assertRaisesRegex(ValueError, 'duplicate_identity'): m.join(f, s)

    def test_count_mismatch_is_rejected(self):
        f, s = fixture(); f['queue_count'] = 2
        with self.assertRaisesRegex(ValueError, 'frontier_count'): m.join(f, s)

    def test_typed_namespace_is_required(self):
        f, s = fixture(); f['queue'][0]['supplier_namespace'] = 'operator_5'
        with self.assertRaisesRegex(ValueError, 'namespace_mismatch'): m.join(f, s)

    def test_excluded_identity_never_reintroduced(self):
        f, s = fixture(); f['holds'] = [{'andromeda_hotel_id': '1000', 'route': 'foreign_claim'}]
        with self.assertRaisesRegex(ValueError, 'excluded_reintroduced'): m.join(f, s)

    def test_retired_metadata_never_grows_frontier(self):
        f, s = fixture(); s['evidence_rows'] = [evidence('9000')]
        r = m.join(f, s)
        self.assertEqual(r['retired_retained_ids_not_reintroduced'], ['9000'])
        self.assertEqual([x['external_hotel_id'] for x in r['dossiers']], ['1000'])

    def test_raw_digest_is_not_invented(self):
        f, s = fixture(); x = evidence(); x['original_http_response_sha256'] = 'f'*64
        s['evidence_rows'] = [x]
        with self.assertRaisesRegex(ValueError, 'unexpected_raw_http_provenance'): m.join(f, s)

    def test_document_lower_bound_deduplicates_metadata_variants(self):
        f, s = fixture(); a = evidence(kind='retained_normalized_PRICE')
        b = copy.deepcopy(a); a['other_retained_file_sha256'] = ['b'*64]
        b['other_retained_file_sha256'] = ['b'*64, 'c'*64]
        a['observation_count'] = 900; b['observation_count'] = 900
        s['evidence_rows'] = [a, b]
        d = m.join(f, s)['dossiers'][0]
        self.assertEqual(d['retained_search_documents_lower_bound'], 3)
        self.assertIsNone(d['frontier']['frequency'])

    def test_former_suffix_does_not_create_false_primary_divergence(self):
        f, s = fixture(); s['evidence_rows'] = [
            evidence(title='Swissotel Resort El Quseir', kind='retained_normalized_PRICE'),
            evidence(title='Swissotel Resort El Quseir (ex. Radisson Blu)', kind='retained_normalized_PRICE')]
        self.assertNotIn('multiple_retained_search_primary_names', m.join(f, s)['dossiers'][0]['evidence_flags'])

    def test_unrelated_search_names_remain_separate(self):
        f, s = fixture(); s['evidence_rows'] = [
            evidence(title='Swissotel Resort El Quseir', kind='retained_normalized_PRICE'),
            evidence(title='Reef Oasis Suakin Resort & SPA', kind='retained_normalized_PRICE')]
        d = m.join(f, s)['dossiers'][0]
        self.assertIn('multiple_retained_search_primary_names', d['evidence_flags'])
        self.assertEqual(len(d['retained_evidence']), 2)

    def test_qualifiers_and_numbers_are_not_erased(self):
        for a, b in [('Example North', 'Example South'), ('Example Annex', 'Example Beach'), ('Example 1', 'Example 2')]:
            self.assertNotEqual(m.primary_key(a), m.primary_key(b))

    def test_original_holds_and_typed_geo_conflicts_are_preserved(self):
        f, s = fixture(); a = evidence(); a['holds'] = ['manual_review_required']
        a['typed_town_links'] = {'townKey': {'namespace': 'andromeda_town', 'country_id': 4, 'conflict': True}}
        s['evidence_rows'] = [a]
        self.assertEqual(m.join(f, s)['dossiers'][0]['evidence_flags'], [
            'manual_review_required', 'retained_town_dictionary_conflict', 'typed_geography_context_conflict'])

    def test_country_and_state_drift_are_flagged(self):
        f, s = fixture(); a = evidence(); a['country_id'] = 4; a['hotel_fields']['stateKey'] = '5'
        s['evidence_rows'] = [a]
        self.assertEqual(m.join(f, s)['dossiers'][0]['evidence_flags'], ['retained_country_conflict', 'retained_state_conflict'])

    def test_inputs_are_not_mutated(self):
        f, s = fixture(); s['evidence_rows'] = [evidence()]
        before = copy.deepcopy((f, s)); m.join(f, s)
        self.assertEqual((f, s), before)

    def test_duplicate_json_keys_rejected(self):
        with self.assertRaisesRegex(ValueError, 'duplicate_json_key'): m.decode(b'{"a":1,"a":2}')

    def test_invalid_ids_and_hashes_rejected(self):
        for value in [True, 1000, '0', '01', '-1', 'x', '1'*21]:
            with self.assertRaises(ValueError): m.hotel_id(value)
        with self.assertRaises(ValueError): m.sha256('missing')

    def test_wrong_archive_rejected_before_read(self):
        with tempfile.TemporaryDirectory() as temp:
            p = Path(temp)/'wrong.zip'; p.write_bytes(b'wrong')
            with self.assertRaisesRegex(ValueError, 'archive_digest'): m.read_pinned(p, 'frontier')

    @unittest.skipUnless(os.environ.get('MATCH_FRONTIER') and os.environ.get('MATCH_RETAINED'), 'pinned artifacts not supplied')
    def test_actual_1321_row_receipted_inputs(self):
        f = m.read_pinned(Path(os.environ['MATCH_FRONTIER']), 'frontier')
        s = m.read_pinned(Path(os.environ['MATCH_RETAINED']), 'retained')
        r = m.join(f, s)
        self.assertEqual(r['summary'], {
            'current_frequency_unknown': 1321, 'flagged_identities': 1, 'queue_rows': 1321,
            'retained_evidence_rows': 539, 'retained_search_document_identity_pairs_lower_bound': 23,
            'retained_url_records': 12, 'unchanged_source_digest': 527, 'with_retained_metadata': 527,
            'with_retained_search': 9, 'without_retained_metadata': 794})
        flagged = [x for x in r['dossiers'] if x['evidence_flags']]
        self.assertEqual([x['external_hotel_id'] for x in flagged], ['13293'])
        self.assertEqual(flagged[0]['evidence_flags'], ['multiple_retained_search_primary_names'])
        self.assertTrue(all(x['safe_to_write_now'] is False for x in r['dossiers']))
        self.assertEqual({x['external_hotel_id'] for x in r['dossiers']}, {x['andromeda_hotel_id'] for x in f['queue']})
        self.assertEqual(m.digest((json.dumps(r, ensure_ascii=False, sort_keys=True, indent=2)+'\n').encode()),
                         'd2eff538a7587406460bea41cdd39aff7f6e25cc2cd87ec14d37fa918e63490d')


if __name__ == '__main__':
    unittest.main()
