import copy
import json
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import andromeda_hotel_shortlist as subject


class ShortlistTest(unittest.TestCase):
    def setUp(self):
        self.decisions = json.loads((ROOT / 'reports/andromeda-hotel-shortlist-20260909.json').read_bytes())
        self.review = {'provider': 'andromeda', 'accepted_mappings': 0, 'selection_enabled': False,
                       'rows': [{'source': {'supplier_namespace': d['supplier_namespace'],
                                           'external_hotel_id': d['external_hotel_id'],
                                           'local_hotel_id': None, 'offer_count': 1},
                                 'candidates': [{'id': d['proposed_catalog_hotel_id'] or 501}]}
                                for d in self.decisions['rows']]}

    def test_proposals_never_become_resolver_authority(self):
        result = subject.build(self.review, self.decisions)
        self.assertEqual(result['counts']['proposed'], 13)
        self.assertEqual(result['counts']['quarantined'], 1)
        self.assertEqual(result['resolver_rows'], [])
        self.assertTrue(all(r['local_hotel_id'] is None for r in result['rows']))

    def test_swapped_or_missing_target_rejected(self):
        self.decisions['rows'][0]['proposed_catalog_hotel_id'] = 999999
        with self.assertRaises(ValueError): subject.build(self.review, self.decisions)

    def test_empire_conflict_cannot_be_promoted(self):
        row = next(r for r in self.decisions['rows'] if r['external_hotel_id'] == '2000073714')
        row.update(decision_status='proposed', proposed_catalog_hotel_id=501)
        with self.assertRaisesRegex(ValueError, 'Empire'): subject.build(self.review, self.decisions)

    def test_acceptance_and_duplicate_identity_rejected(self):
        changed = copy.deepcopy(self.decisions)
        changed['rows'][0]['decision_status'] = 'accepted'
        with self.assertRaises(ValueError): subject.build(self.review, changed)
        self.decisions['rows'][1] = copy.deepcopy(self.decisions['rows'][0])
        with self.assertRaises(ValueError): subject.build(self.review, self.decisions)


if __name__ == '__main__':
    unittest.main()
