#!/usr/bin/env python3
"""No-network regressions for #1759 saved-link review; uses existing policy."""
import copy
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import anex_tourvisor_link_review as review


class LinkReviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.policy = review.policy_namespace()

    def fixture(self, api_name='Aperion Beach Hotel', local_name='APERION BEACH (EX. SEA PARADISE)'):
        api = dict(id=8121, name=api_name, country='Турция', latitude=36.713018,
                   longitude=31.563078, town_id=None)
        evidence = {'external_id': 8121, 'api': api,
                    'xml': dict(id=8121, name=api_name, alternate_name=api_name, town_id=None),
                    'api_xml_relation': 'same_record',
                    'candidates': [dict(id=6319, name=local_name, country='Турция',
                                        latitude='36.7130180', longitude='31.5630780',
                                        region='Сиде', town='Кызылот')]}
        dossier = dict(anex_hotel_id=8121, evidence=evidence,
                       evidence_row_sha256=review.digest(evidence),
                       observation={'country_name': 'Турция'})
        entry = dict(anex_hotel_id=8121, local_hotel_id=6319,
                     anex_name=api_name, local_name=local_name,
                     dossier_digest=review.digest(evidence),
                     operator_link=dict(origin='https://agent.anextour.ru', path='/search/tour',
                                        field='HOTELLIST', value='8121',
                                        safe_url='https://agent.anextour.ru/search/tour?HOTELLIST=8121'))
        return entry, dossier

    def evaluate(self, entry, dossier, accepted=None, manual=None, excluded=None):
        return review.review_pair(entry, dossier, accepted or {}, manual or set(),
                                  excluded or set(), self.policy)

    @staticmethod
    def reseal(entry, dossier):
        entry['dossier_digest'] = dossier['evidence_row_sha256'] = review.digest(dossier['evidence'])

    def test_explicit_former_name_only(self):
        for text in ('APERION BEACH (EX. SEA PARADISE)', 'APERION BEACH (ЕХ. SEA PARADISE)',
                     'APERION BEACH (ex SEA PARADISE)'):
            self.assertEqual(review.current_name(text), 'APERION BEACH')
        for text in ('SUN GARDEN', 'SUN (ANNEX)', 'SUN ADULTS ONLY 16+', 'SUN EXAMPLE HOTEL'):
            self.assertEqual(review.current_name(text), text)
        with self.assertRaises(ValueError):
            review.current_name('(EX. OLD HOTEL)')

    def test_same_coordinates_not_string_conflict(self):
        entry, dossier = self.fixture()
        result = self.evaluate(entry, dossier)
        self.assertEqual(result['status'], 'eligible_for_guarded_import')
        self.assertEqual(result['distance_m'], 0.0)
        self.assertEqual(result['name_similarity'], 1.0)
        self.assertEqual(result['previous_status'], 'review')

    def test_old_garden_not_current_section(self):
        entry, dossier = self.fixture('Phoenix Sun Hotel', 'PHOENIX SUN (EX. PALM GARDEN HOTEL)')
        self.assertEqual(self.evaluate(entry, dossier)['status'], 'eligible_for_guarded_import')

    def test_real_current_qualifier_preserved(self):
        # A formerly matching name cannot erase a current HOTEL ANNEX section.
        entry, dossier = self.fixture('SUN BEACH', 'SUN BEACH ANNEX (EX. SUN BEACH)')
        result = self.evaluate(entry, dossier)
        self.assertEqual(result['status'], 'review')
        self.assertEqual(result['reason'], 'hotel_section_difference')

    def test_coordinate_conflict(self):
        entry, dossier = self.fixture()
        dossier['evidence']['candidates'][0]['latitude'] = 38
        self.reseal(entry, dossier)
        self.assertEqual(self.evaluate(entry, dossier)['reason'], 'coordinate_conflict')

    def test_country_conflict(self):
        entry, dossier = self.fixture()
        dossier['evidence']['candidates'][0]['country'] = 'Египет'
        self.reseal(entry, dossier)
        self.assertEqual(self.evaluate(entry, dossier)['reason'], 'country_conflict')

    def test_competition_not_overridden_by_operator_link(self):
        entry, dossier = self.fixture()
        second = dict(dossier['evidence']['candidates'][0], id=6320)
        dossier['evidence']['candidates'].append(second)
        self.reseal(entry, dossier)
        self.assertEqual(self.evaluate(entry, dossier)['reason'], 'competing_candidates')

    def test_all_candidates_reranked(self):
        entry, dossier = self.fixture('SUN HOTEL', 'SUN HOTEL CITY')
        dossier['evidence']['candidates'].append(dict(id=6320, name='SUN HOTEL (EX. OLD HOTEL)',
            country='Турция', latitude=36.713018, longitude=31.563078))
        self.reseal(entry, dossier)
        self.assertEqual(self.evaluate(entry, dossier)['reason'], 'operator_target_not_best_candidate')

    def test_truncated_set_never_accepted(self):
        entry, dossier = self.fixture()
        base = dossier['evidence']['candidates'][0]
        dossier['evidence']['candidates'] += [dict(base, id=7000+i) for i in range(255)]
        self.reseal(entry, dossier)
        self.assertEqual(self.evaluate(entry, dossier)['reason'], 'candidate_limit_reached')

    def test_dossier_tampering_rejected(self):
        entry, dossier = self.fixture()
        dossier['evidence']['api']['name'] = 'Another Hotel'
        with self.assertRaises(ValueError):
            self.evaluate(entry, dossier)

    def test_namespace_and_duplicate_candidate_guards(self):
        entry, dossier = self.fixture()
        dossier['evidence']['xml']['id'] = 8122
        self.reseal(entry, dossier)
        with self.assertRaises(ValueError):
            self.evaluate(entry, dossier)
        entry, dossier = self.fixture()
        dossier['evidence']['candidates'] *= 2
        self.reseal(entry, dossier)
        with self.assertRaises(ValueError):
            self.evaluate(entry, dossier)

    def test_invalid_operator_links_rejected(self):
        bad = ('https://agent.anextour.ru.evil.test/search/tour?HOTELLIST=8121',
               'https://user@agent.anextour.ru/search/tour?HOTELLIST=8121',
               'http://agent.anextour.ru/search/tour?HOTELLIST=8121',
               'https://agent.anextour.ru/search/tour?HOTELLIST=8122',
               'https://agent.anextour.ru/search/tour?HOTELLIST=8121&HOTELLIST=8122',
               'https://agent.anextour.ru/search/tour?HOTELLIST=8121,8122',
               'https://agent.anextour.ru/search/tour?HOTELLIST=8121#8122')
        for url in bad:
            with self.subTest(url=url):
                entry, dossier = self.fixture()
                entry['operator_link']['safe_url'] = url
                with self.assertRaises(ValueError):
                    self.evaluate(entry, dossier)

    def test_manual_existing_and_pair_exclusion_preserved(self):
        entry, dossier = self.fixture()
        for arguments, reason in (
            ({'manual': {8121}}, 'manual_or_pair_exclusion'),
            ({'excluded': {(8121, 6319)}}, 'manual_or_pair_exclusion'),
            ({'accepted': {8121: 6319}}, 'already_accepted'),
            ({'accepted': {8121: 9999}}, 'existing_mapping_conflict'),
        ):
            with self.subTest(reason=reason):
                result = self.evaluate(entry, dossier, **arguments)
                self.assertEqual(result['status'], 'protected')
                self.assertEqual(result['reason'], reason)

    def test_missing_coordinates_not_evidence(self):
        entry, dossier = self.fixture()
        dossier['evidence']['candidates'][0]['latitude'] = None
        self.reseal(entry, dossier)
        self.assertEqual(self.evaluate(entry, dossier)['status'], 'review')

    def test_does_not_mutate_sources(self):
        entry, dossier = self.fixture()
        before = copy.deepcopy((entry, dossier))
        self.evaluate(entry, dossier)
        self.assertEqual((entry, dossier), before)


if __name__ == '__main__':
    unittest.main()
