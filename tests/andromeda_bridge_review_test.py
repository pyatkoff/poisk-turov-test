#!/usr/bin/env python3
"""Focused offline regressions for country, short names, geography and ambiguity."""
import copy
import json
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import andromeda_bridge_review as audit


def fixture():
    source = {'id': 46462, 'name': 'Arsi', 'lName': 'Arsi', 'stateKey': 5,
              'state': 'Турция', 'townKey': 7, 'town': 'Аланья', 'star': '4'}
    evidence = {'source': source, 'candidate_ids': []}
    identity = {'external_hotel_id': '46462', 'local_hotel_id': None,
                'decision_status': 'pending', 'evidence_json': json.dumps(evidence, ensure_ascii=False)}
    local = {'complete': True, 'country_id': 4, 'aliases': [],
             'hotels': [{'id': 3406, 'name': 'ARSI HOTEL', 'country_id': 4,
                         'region_name': 'Аланья', 'subregion_name': 'Алания-центр', 'category': 4}]}
    towns = [{'id': 7, 'name': 'Аланья', 'state': 5, 'Region': 485, 'RegionName': 'Анталья'},
             {'id': 99, 'name': 'Фатих', 'state': 5, 'Region': 32, 'RegionName': 'Стамбул'}]
    catalogue = {'params': {'STATEINC': 5}, 'payload': {'HOTELS': [source], 'TOWNTO': towns}}
    bridges = [{'anex_id': 8230, 'local_id': 3406}]
    return identity, 3406, local, catalogue, bridges


class ReviewTests(unittest.TestCase):
    def test_short_names_require_full_unique_catalogue_and_geography(self):
        result = audit.review_one(*fixture())
        self.assertEqual(result['status'], 'validated_proposal_not_accepted')
        self.assertFalse(result['live_guards_checked'])
        self.assertFalse(result['coordinates_verified'])

    def test_current_distinguishing_words_are_kept(self):
        for word in ['ANNEX', 'ADULTS', 'GARDEN', 'RESORT', 'FAMILY', 'BEACH', 'NORTH']:
            self.assertNotEqual(audit.name_key('SUN HOTEL'), audit.name_key('SUN ' + word))
        self.assertEqual(audit.name_key('SUN (EX. OLD HOTEL)'), audit.name_key('SUN'))
        self.assertNotEqual(audit.name_key('SUN SUN'), audit.name_key('SUN'))

    def test_whole_word_order_but_not_joining(self):
        self.assertEqual(audit.name_key('Saint Sophia Boutique'), audit.name_key('BOUTIQUE SAINT SOPHIA'))
        self.assertNotEqual(audit.name_key('Pasabey'), audit.name_key('PASA BEY'))
        self.assertEqual(audit.name_key('HOTEL'), '')

    def test_exact_competitor_keeps_ambiguity(self):
        args = list(fixture())
        args[2]['hotels'].append(dict(args[2]['hotels'][0], id=9999))
        self.assertEqual(audit.review_one(*args)['status'], 'review_name_or_ambiguity')

    def test_alias_competitor_cannot_be_ignored(self):
        args = list(fixture())
        args[2]['hotels'].append(dict(args[2]['hotels'][0], id=9999, name='OTHER HOTEL'))
        args[2]['aliases'].append({'hotel_id': 9999, 'alias': 'ARSI'})
        self.assertEqual(audit.review_one(*args)['status'], 'review_name_or_ambiguity')

    def test_original_candidates_are_preserved(self):
        args = list(fixture())
        e = json.loads(args[0]['evidence_json']); e['candidate_ids'] = [9999]
        args[0]['evidence_json'] = json.dumps(e)
        self.assertEqual(audit.review_one(*args)['status'], 'review_original_candidate_conflict')

    def test_known_wrong_city_is_blocked_even_with_matching_subregion(self):
        args = list(fixture())
        e = json.loads(args[0]['evidence_json']); e['source'].update(townKey=99, town='Фатих')
        args[0]['evidence_json'] = json.dumps(e)
        args[3]['payload']['HOTELS'] = [e['source']]
        args[2]['hotels'][0]['subregion_name'] = 'Фатих'
        self.assertEqual(audit.review_one(*args)['status'], 'blocked_geography_conflict')

    def test_supplier_parent_resolves_district(self):
        args = list(fixture())
        e = json.loads(args[0]['evidence_json']); e['source'].update(townKey=99, town='Фатих')
        args[0]['evidence_json'] = json.dumps(e)
        args[3]['payload']['HOTELS'] = [e['source']]
        args[2]['hotels'][0].update(region_name='Стамбул', subregion_name='Султанахмет')
        self.assertEqual(audit.review_one(*args)['status'], 'validated_proposal_not_accepted')

    def test_unknown_geography_is_not_accepted(self):
        args = list(fixture()); args[3]['payload']['TOWNTO'] = []
        self.assertEqual(audit.review_one(*args)['status'], 'review_geography_unknown')

    def test_incomplete_duplicate_or_orphan_local_catalogue_is_rejected(self):
        for kind in ['incomplete', 'duplicate', 'orphan']:
            args = list(fixture())
            if kind == 'incomplete': args[2]['complete'] = False
            elif kind == 'duplicate': args[2]['hotels'] *= 2
            else: args[2]['aliases'].append({'hotel_id': 9999, 'alias': 'ARSI'})
            with self.subTest(kind=kind), self.assertRaises(ValueError): audit.review_one(*args)

    def test_country_and_source_tampering_are_rejected(self):
        for kind in ['country', 'source', 'identity']:
            args = list(fixture())
            if kind == 'country': args[2]['country_id'] = 1
            elif kind == 'source': args[3]['payload']['HOTELS'][0]['name'] = 'OTHER HOTEL'
            else: args[0]['external_hotel_id'] = '1'
            with self.subTest(kind=kind), self.assertRaises(ValueError): audit.review_one(*args)

    def test_protected_identity_and_wrong_bridge_are_rejected(self):
        for kind in ['accepted', 'conflict', 'bridge']:
            args = list(fixture())
            if kind == 'bridge': args[4][0]['local_id'] = 9999
            else: args[0]['decision_status'] = kind
            with self.subTest(kind=kind), self.assertRaises(ValueError): audit.review_one(*args)

    def test_category_difference_is_visible_not_hidden(self):
        args = list(fixture()); args[2]['hotels'][0]['category'] = 3
        result = audit.review_one(*args)
        self.assertTrue(result['category_difference'])

    def test_inputs_unchanged_and_no_apply_authority(self):
        args = fixture(); before = copy.deepcopy(args)
        result = audit.review_one(*args)
        self.assertEqual(args, before)
        self.assertNotIn('operation', result)
        self.assertNotIn('accepted', result)



class ScaleTests(unittest.TestCase):
    def inputs(self, history=None):
        identity, target, local, catalogue, _ = fixture()
        identity['catalog_sha256'] = 'a' * 64
        source = json.loads(identity['evidence_json'])['source']
        prior = {'rows': [], 'sources': {}, 'catalogue_coverage': {}, 'limitations': []}
        anex = [{'external_id': 8230, 'name': 'Arsi', 'alternate_name': '',
                 'country': 'Турция', 'town': 'Аланья'}]
        def read(key, member):
            rows = (history or []) if member == 'anex-hotel-geo-enrichment.json' else []
            return {'rows': rows}
        return ({'46462': identity}, anex, {}, set(), {4: local}, {4: catalogue}, prior, read)

    def test_full_inventory_does_not_require_a_name_bridge(self):
        report = audit.scale_report(*self.inputs())
        self.assertEqual(report['totals']['all_unresolved_examined'], 2)
        self.assertEqual(report['totals']['additional_andromeda_proposals'], 1)
        self.assertEqual(report['totals']['anex_unique_name_candidates'], 1)
        row = next(r for r in report['rows'] if r['provider'] == 'andromeda')
        self.assertEqual(row['detail']['anex_bridges'], [])
        self.assertEqual(report['effect_if_all_validated_proposals_are_accepted']['triple_after'], 0)
        self.assertEqual(report['new_accepted_mappings'], 0)
        self.assertFalse(report['batches']['andromeda_validated'][0]['apply_allowed'])

    def test_prior_proposals_are_not_new_progress(self):
        args = list(self.inputs())
        args[6]['rows'] = [{'andromeda_id': '46462', 'local_hotel_id': 3406,
                           'status': 'validated_proposal_not_accepted'}]
        report = audit.scale_report(*args)
        self.assertEqual(report['totals']['additional_andromeda_proposals'], 0)
        args[4][4]['hotels'].append(dict(args[4][4]['hotels'][0], id=9999))
        with self.assertRaises(ValueError): audit.scale_report(*args)

    def test_accepted_and_conflicting_records_are_not_reopened(self):
        args = list(self.inputs())
        for external, status in [('111', 'accepted'), ('222', 'conflict')]:
            args[0][external] = dict(args[0]['46462'], external_hotel_id=external,
                                    decision_status=status, local_hotel_id=3406 if status == 'accepted' else None)
        args[2][8230] = 3406
        report = audit.scale_report(*args)
        self.assertEqual(report['totals']['all_unresolved_examined'], 1)
        self.assertEqual(report['effect_if_all_validated_proposals_are_accepted']['new_unique_local_hotels'], 0)

    def test_saved_truncated_candidates_are_not_coordinate_approval(self):
        history = [{'external_id': 8230, 'api': {'id': 8230, 'country': 'Турция'},
                    'api_xml_relation': 'same_record', 'reason': 'candidate_limit_reached',
                    'candidates': [{'id': 3406, 'distance_m': 0.0}]}]
        report = audit.scale_report(*self.inputs(history))
        row = next(r for r in report['rows'] if r['provider'] == 'anex')
        self.assertEqual(row['status'], 'review_candidate_set_truncated')
        self.assertEqual(report['database_writes'], 0)

    def test_coordinate_conflicts_never_enter_batch_queue(self):
        history = [{'external_id': 8230, 'api': {'id': 8230, 'country': 'Турция'},
                    'api_xml_relation': 'same_record', 'reason': 'coordinate_conflict',
                    'candidates': [{'id': 3406, 'distance_m': 6000.0}]}]
        report = audit.scale_report(*self.inputs(history))
        self.assertEqual(report['counts']['anex']['blocked_saved_coordinate_conflict'], 1)
        self.assertEqual(report['batches']['anex_candidates_requiring_review'], [])

    def test_batches_stable_bounded_and_namespace_qualified(self):
        rows = [{'provider': 'anex', 'external_id': str(i), 'local_hotel_id': i}
                for i in range(1, 252)]
        first = audit.planned_batches(rows)
        self.assertEqual([b['count'] for b in first], [100, 100, 51])
        self.assertEqual(first, audit.planned_batches(list(reversed(rows))))
        with self.assertRaises(ValueError): audit.planned_batches(rows + rows[:1])
        for size in (0, 501, True):
            with self.assertRaises(ValueError): audit.planned_batches(rows, size)
        rows.append({'provider': 'andromeda', 'external_id': '1', 'local_hotel_id': 1})
        self.assertEqual(sum(b['count'] for b in audit.planned_batches(rows)), 252)

    def test_no_empty_name_wildcard_and_no_fuzzy_matching(self):
        index = {'': {1}, 'arsi': {2}, 'pasa bey': {3}}
        self.assertEqual(audit.exact_candidates(index, ['HOTEL']), set())
        self.assertEqual(audit.exact_candidates(index, ['Pasabey']), set())
        self.assertEqual(audit.exact_candidates(index, ['ARSI HOTEL']), {2})

    def test_scale_does_not_mutate_input_evidence(self):
        args = self.inputs(); before = copy.deepcopy(args[:-1])
        audit.scale_report(*args)
        self.assertEqual(args[:-1], before)


if __name__ == '__main__':
    unittest.main(verbosity=2)
