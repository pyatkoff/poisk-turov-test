"""Supplier-free regression checks for the independent geography evidence tier."""
import copy
import importlib.util
import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'scripts/diagnostics/hotel_match_frontier_geography_evidence.py'
spec = importlib.util.spec_from_file_location('geo_evidence', SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
SHA = 'a' * 64


def fixture(count=1):
    items, priors = [], []
    for i in range(count):
        key = str(i + 1)
        frontier = {'supplier_namespace': 'andromeda_catalog', 'andromeda_hotel_id': key,
                    'country_id': 4, 'country_name': 'Турция', 'state_key': 5,
                    'names': ['HOTEL BEACH NORTH ' + key], 'frequency': None,
                    'evidence_sha256': SHA, 'safe_to_write_now': False}
        item = {'supplier_namespace': 'andromeda_catalog', 'external_hotel_id': key,
                'frontier': frontier, 'evidence_flags': [], 'safe_to_write_now': False,
                'retained_source_digest_matches_frontier': True,
                'retained_evidence': [{'source_kind': 'saved_HOTELS_TOWNTO', 'external_hotel_id': key,
                    'country_id': 4, 'hotel_fields': {'stateKey': '5', 'townKey': '77'},
                    'retained_file_sha256': SHA, 'typed_town_links': {'townKey': {
                        'namespace': 'andromeda_town', 'external_id': '77', 'country_id': 4,
                        'conflict': False, 'dictionary_fields': {'name': 'Бодрум', 'stateName': 'Турция'}}}}]}
        items.append(item)
        priors.append({'supplier_namespace': 'andromeda_catalog', 'external_hotel_id': key,
                       'evidence_sha256': SHA, 'country_id': 4, 'geo_anchors': []})
    report = {'dossiers': items, 'summary': {'queue_rows': count}, 'frontier_holds_preserved': [],
              'frontier_read_at_utc': '2026-09-16T12:44:11+00:00'}
    census = {'local_hotels': {'100': {'id': 100, 'country_id': 4, 'region_id': 10,
               'region_name': 'БОДРУМ', 'subregion_id': None, 'subregion_name': None}},
              'routes': {'needs_extra_evidence': priors}, 'created_at': '2026-09-15T11:54:00+00:00'}
    return report, census


def link(report, index=0):
    return report['dossiers'][index]['retained_evidence'][0]['typed_town_links']['townKey']


class GeographyTests(unittest.TestCase):
    def test_exact_typed_country_evidence_not_identity(self):
        d, c = fixture()
        out = m.enrich(d, c)
        self.assertEqual(out['summary']['unique_dictionary_geography_support'], 1)
        self.assertEqual(out['summary']['new_vs_earlier_missing_anchor'], 1)
        self.assertFalse(out['safe_to_write_now'])
        self.assertNotIn('local_hotel_id', out['dossiers'][0]['geography_evidence'])

    def test_complete_2000_rows_and_immutable_input(self):
        d, c = fixture(2000)
        before = m.canonical([d, c])
        out = m.enrich(d, c)
        self.assertEqual(len(out['dossiers']), 2000)
        self.assertEqual(out['summary']['new_vs_earlier_missing_anchor'], 2000)
        self.assertEqual(m.canonical([d, c]), before)

    def test_meaningful_words_numbers_not_removed(self):
        self.assertNotEqual(m.text_key('North Bay 2'), m.text_key('South Bay 2'))
        self.assertNotEqual(m.text_key('Beach Annex'), m.text_key('Beach'))
        self.assertNotEqual(m.text_key('District 2'), m.text_key('District 3'))

    def test_foreign_country_name_collision_not_matched(self):
        d, c = fixture()
        c['local_hotels']['100']['country_id'] = 1
        out = m.enrich(d, c)
        self.assertEqual(out['summary']['no_exact_local_geography'], 1)

    def test_equal_numeric_town_and_local_id_not_proof(self):
        d, c = fixture()
        c['local_hotels']['100'].update(region_id=77, region_name='Другой город')
        self.assertEqual(m.enrich(d, c)['summary']['no_exact_local_geography'], 1)

    def test_ambiguous_same_country_scope_held(self):
        d, c = fixture()
        c['local_hotels']['101'] = dict(c['local_hotels']['100'], id=101, region_id=11)
        geo = m.enrich(d, c)['dossiers'][0]['geography_evidence']
        self.assertIn('ambiguous_local_geography', geo['holds'])

    def test_inconsistent_local_definition_held(self):
        d, c = fixture()
        c['local_hotels']['101'] = dict(c['local_hotels']['100'], id=101, region_name='Другая область')
        self.assertIn('inconsistent_local_geography_definition', m.enrich(d, c)['dossiers'][0]['geography_evidence']['holds'])

    def test_dictionary_conflict_propagates_across_ids(self):
        d, c = fixture(2)
        link(d, 1)['dictionary_fields']['name'] = 'Другой город'
        for row in m.enrich(d, c)['dossiers']:
            self.assertIn('dictionary_versions_conflict', row['geography_evidence']['holds'])

    def test_same_town_number_different_countries_is_not_dictionary_conflict(self):
        d, c = fixture(2)
        item = d['dossiers'][1]
        item['frontier'].update(country_id=1, country_name='Египет', state_key=3)
        item['retained_evidence'][0].update(country_id=1)
        item['retained_evidence'][0]['hotel_fields']['stateKey'] = '3'
        link(d, 1).update(country_id=1, dictionary_fields={'name': 'Хургада', 'stateName': 'Египет'})
        geo = m.enrich(d, c)['dossiers'][0]['geography_evidence']
        self.assertNotIn('dictionary_versions_conflict', geo['holds'])

    def test_context_and_flag_fail_closed(self):
        mutations = [lambda x: x.update(namespace='tourvisor_town'), lambda x: x.update(country_id=1),
                     lambda x: x.update(country_id=True), lambda x: x.update(external_id='88'),
                     lambda x: x.update(conflict=True), lambda x: x.update(conflict=None),
                     lambda x: x['dictionary_fields'].update(stateName='Египет')]
        for change in mutations:
            with self.subTest(change=change):
                d, c = fixture()
                change(link(d))
                self.assertIn('typed_dictionary_context_or_conflict', m.enrich(d, c)['dossiers'][0]['geography_evidence']['holds'])

    def test_inherited_primary_name_conflict_preserved(self):
        d, c = fixture()
        d['dossiers'][0]['evidence_flags'] = ['multiple_retained_search_primary_names']
        out = m.enrich(d, c)
        self.assertEqual(out['summary']['new_vs_earlier_missing_anchor'], 0)
        self.assertIn('multiple_retained_search_primary_names', out['dossiers'][0]['geography_evidence']['holds'])

    def test_changed_evidence_does_not_gain_support(self):
        d, c = fixture()
        d['dossiers'][0]['retained_source_digest_matches_frontier'] = False
        self.assertIn('source_evidence_changed', m.enrich(d, c)['dossiers'][0]['geography_evidence']['holds'])

    def test_conflicting_prior_anchor_not_overwritten(self):
        d, c = fixture()
        c['routes']['needs_extra_evidence'][0]['geo_anchors'] = [{'scope': 'region', 'scope_id': 99}]
        self.assertIn('prior_same_scope_disagreement', m.enrich(d, c)['dossiers'][0]['geography_evidence']['holds'])

    def test_prior_cross_scope_requires_parent_readback(self):
        d, c = fixture()
        c['routes']['needs_extra_evidence'][0]['geo_anchors'] = [{'scope': 'subregion', 'scope_id': 99}]
        self.assertIn('prior_cross_scope_requires_parent_readback', m.enrich(d, c)['dossiers'][0]['geography_evidence']['holds'])

    def test_unchanged_prior_anchor_not_new(self):
        d, c = fixture()
        c['routes']['needs_extra_evidence'][0]['geo_anchors'] = [{'scope': 'region', 'scope_id': 10}]
        self.assertEqual(m.enrich(d, c)['summary']['new_vs_earlier_missing_anchor'], 0)

    def test_prior_source_drift_not_counted_as_new(self):
        d, c = fixture()
        c['routes']['needs_extra_evidence'][0]['evidence_sha256'] = 'b' * 64
        self.assertEqual(m.enrich(d, c)['summary']['new_vs_earlier_missing_anchor'], 0)

    def test_unknown_and_zero_frequency_kept_distinct(self):
        d, c = fixture(2)
        d['dossiers'][1]['frontier']['frequency'] = 0
        out = m.enrich(d, c)
        self.assertEqual(out['summary']['unknown_frequency_preserved'], 1)
        self.assertEqual([x['frontier']['frequency'] for x in out['dossiers']], [None, 0])

    def test_invalid_frontier_rejected(self):
        for kind in ('duplicate', 'excluded', 'namespace', 'identity', 'acceptance', 'digest'):
            with self.subTest(kind=kind):
                d, c = fixture()
                if kind == 'duplicate':
                    d['dossiers'] *= 2
                    d['summary']['queue_rows'] = 2
                elif kind == 'excluded':
                    d['frontier_holds_preserved'] = [{'andromeda_hotel_id': '1'}]
                elif kind == 'namespace':
                    d['dossiers'][0]['supplier_namespace'] = 'operator_5'
                elif kind == 'identity':
                    d['dossiers'][0]['frontier']['andromeda_hotel_id'] = '2'
                elif kind == 'acceptance':
                    d['dossiers'][0]['safe_to_write_now'] = True
                else:
                    d['dossiers'][0]['frontier']['evidence_sha256'] = 'missing'
                with self.assertRaises(ValueError):
                    m.enrich(d, c)

    def test_duplicate_json_and_nonfinite_rejected(self):
        for raw in (b'{"a":1,"a":2}', b'{"a":NaN}', b'{"a":Infinity}', b'[]'):
            with self.assertRaises(ValueError):
                m.decode(raw)

    def test_empty_dictionary_not_assumed(self):
        d, c = fixture()
        d['dossiers'][0]['retained_evidence'] = []
        self.assertEqual(m.enrich(d, c)['summary']['no_retained_town_dictionary'], 1)

    def test_read_pinned_inputs_and_actual_complete_frontier(self):
        if not os.environ.get('MATCH_GEO_DOSSIER_ZIP'):
            self.skipTest('actual pinned archives supplied by CLI/CI')
        d = m.read_input(Path(os.environ['MATCH_GEO_DOSSIER_ZIP']), 'dossier')
        c = m.read_input(Path(os.environ['MATCH_GEO_CENSUS_ZIP']), 'census')
        out = m.enrich(d, c)
        self.assertEqual(out['summary'], {
            'held_geography_or_inherited_evidence': 148, 'new_vs_earlier_missing_anchor': 212,
            'no_exact_local_geography': 101, 'no_retained_town_dictionary': 794,
            'queue_rows': 1321, 'unique_dictionary_geography_support': 278,
            'unknown_frequency_preserved': 1321, 'with_typed_dictionary': 527})
        self.assertEqual([x['external_hotel_id'] for x in out['dossiers']], [x['external_hotel_id'] for x in d['dossiers']])
        conflict = next(x for x in out['dossiers'] if x['external_hotel_id'] == '13293')
        self.assertIn('multiple_retained_search_primary_names', conflict['geography_evidence']['holds'])
        self.assertTrue(all(x['safe_to_write_now'] is False for x in out['dossiers']))

    def test_cli_exclusive_output_and_tampered_archive(self):
        if not os.environ.get('MATCH_GEO_DOSSIER_ZIP'):
            self.skipTest('actual pinned archives supplied by CLI/CI')
        with tempfile.TemporaryDirectory() as tmp:
            out = Path(tmp) / 'result.json'
            cmd = [sys.executable, str(SOURCE), os.environ['MATCH_GEO_DOSSIER_ZIP'], os.environ['MATCH_GEO_CENSUS_ZIP'], str(out)]
            subprocess.run(cmd, check=True, capture_output=True)
            first = out.read_bytes()
            second = subprocess.run(cmd, capture_output=True)
            self.assertNotEqual(second.returncode, 0)
            self.assertEqual(out.read_bytes(), first)
            altered = Path(tmp) / 'bad.zip'
            altered.write_bytes(Path(os.environ['MATCH_GEO_DOSSIER_ZIP']).read_bytes() + b'x')
            with self.assertRaisesRegex(ValueError, 'archive_digest'):
                m.read_input(altered, 'dossier')


if __name__ == '__main__':
    unittest.main(verbosity=2)
