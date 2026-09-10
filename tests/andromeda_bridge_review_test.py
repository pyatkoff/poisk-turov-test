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


if __name__ == '__main__':
    unittest.main(verbosity=2)
