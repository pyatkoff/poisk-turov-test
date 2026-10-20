"""Offline regression tests: no provider calls, credentials, or database."""
import copy
import importlib.util
import tempfile
import unittest
from pathlib import Path

PATH = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/hotel_match_unresolved_target_plan.py'
spec = importlib.util.spec_from_file_location('planner', PATH)
planner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(planner)


def row(ext='123', local=50, provider='anex', country=1, **changes):
    value = dict(provider=provider, external_hotel_id=ext, candidate_local_id=local,
                 country_id=country, search_count=10, source_name='Example Beach Hotel', candidate_name='EXAMPLE BEACH HOTEL',
                 source_aliases=[], auto_block_reason='insufficient_independent_evidence',
                 existing_provider_bridges=[], qualifier_conflict=False, numeric_conflict=False,
                 pair_excluded=False, distance_km=None)
    value.update(changes)
    return value


def inputs(rows):
    queue = dict(rows=copy.deepcopy(rows), queue_sha256='fixture',
                 result={'generated_at_utc': '2026-09-14T08:57:30Z'})
    photo = dict(rows=copy.deepcopy(rows), result={'manual_source_sha256': 'fixture'})
    return queue, photo


def offers(local=50, country=1):
    return [dict(country_id=country, date='2026-10-31', artifact_id=1, result_sha256='fixture',
                 rows=[dict(tourvisor_hotel_id=local, refs={'id': '13261030029798'})])]


class PlannerTests(unittest.TestCase):
    def build(self, rows, saved=None):
        return planner.build_packet(*inputs(rows), saved or [])

    def test_saved_targeted_tour_avoids_new_discovery(self):
        packet = self.build([row()], offers())
        self.assertEqual(packet['targets'][0]['route'], 'saved_targeted_tour_detail')
        self.assertEqual(packet['anex_discovery_batches'], [])
        self.assertFalse(packet['current_db_verified'])
        self.assertFalse(packet['acceptance_authorized'])

    def test_andromeda_missing_side_does_not_request_known_tv(self):
        packet = self.build([row(provider='andromeda', existing_provider_bridges=['anex:321'])], offers())
        self.assertEqual(packet['targets'][0]['route'], 'missing_andromeda_identity')
        self.assertNotIn('saved_tours', packet['targets'][0])
        self.assertEqual(packet['anex_discovery_batches'], [])

    def test_duplicate_local_preserves_both_source_variants(self):
        packet = self.build([row('123'), row('124')])
        self.assertEqual(packet['anex_discovery_batches'], [dict(country_id=1, hotelIds=[50], target_keys=['anex:123', 'anex:124'])])
        self.assertEqual(len(packet['targets']), 2)

    def test_country_scoped_saved_offer_cannot_cross_match(self):
        packet = self.build([row(country=4)], offers())
        self.assertEqual(packet['targets'][0]['route'], 'hotel_filtered_anex_discovery')
        self.assertEqual(packet['anex_discovery_batches'][0]['country_id'], 4)

    def test_synthetic_2000_source_rows_no_truncation(self):
        packet = self.build([row(str(i+1), local=i//2+1) for i in range(2000)])
        batches = packet['anex_discovery_batches']
        self.assertEqual(len(packet['targets']), 2000)
        self.assertEqual(sum(len(b['hotelIds']) for b in batches), 1000)
        self.assertEqual(sum(len(b['target_keys']) for b in batches), 2000)
        self.assertTrue(all(0 < len(b['hotelIds']) <= 30 for b in batches))

    def test_roulette_not_named_fortuna_hotel(self):
        for name in ['Roulette 3* Sharm', 'Fortuna 3 Ai Marmaris']:
            self.assertEqual(self.build([row(source_name=name)])['targets'], [])
        for name in ['Fortuna Hotel Phu Quoc', 'Roulette Hotel']:
            self.assertEqual(len(self.build([row(source_name=name)])['targets']), 1)

    def test_noncore_not_automatically_imported(self):
        packet = self.build([row(country=99)])
        self.assertEqual(packet['targets'], [])
        self.assertEqual(packet['excluded'][0]['reason'], 'outside_core8')

    def test_no_candidate_never_broadens(self):
        packet = self.build([row(local=None)])
        self.assertEqual(packet['targets'][0]['route'], 'needs_local_candidate')
        self.assertEqual(packet['anex_discovery_batches'], [])

    def test_empty_queue_no_empty_search_batch(self):
        packet = self.build([])
        self.assertEqual(packet['targets'], [])
        self.assertEqual(packet['anex_discovery_batches'], [])

    def test_protections_remain_holds(self):
        for change in [dict(pair_excluded=True), dict(distance_km=5.001)]:
            packet = self.build([row(**change)], offers())
            self.assertEqual(packet['targets'][0]['route'], 'protected_hold')
            self.assertEqual(packet['anex_discovery_batches'], [])

    def test_qualifier_not_erased_by_native_id(self):
        queue, photo = inputs([row(qualifier_conflict=True, source_aliases=['Example Hotel'])])
        photo['rows'][0]['native_anex_hotelcode_confirmed'] = True
        packet = planner.build_packet(queue, photo, [])
        self.assertIn('meaningful_qualifier_conflict', packet['targets'][0]['acceptance_holds'])
        self.assertFalse(packet['acceptance_authorized'])

    def test_own_mapping_in_snapshot_rejected(self):
        with self.assertRaisesRegex(ValueError, 'already_mapped'):
            self.build([row(existing_provider_bridges=['anex:123'])])

    def test_distinct_existing_provider_id_is_not_overwritten(self):
        packet = self.build([row(existing_provider_bridges=['anex:999'])])
        self.assertEqual(packet['targets'][0]['existing_provider_bridges'], ['anex:999'])
        self.assertEqual(packet['targets'][0]['key'], 'anex:123')
        self.assertEqual(packet['mapping_writes'], 0)

    def test_enriched_cannot_mutate_or_drop_original(self):
        for field, value in [('source_name', 'Other'), ('distance_km', 0), ('pair_excluded', True)]:
            queue, photo = inputs([row()])
            photo['rows'][0][field] = value
            with self.assertRaisesRegex(ValueError, 'changed_original'):
                planner.build_packet(queue, photo, [])
        queue, photo = inputs([row()])
        photo['rows'] = []
        with self.assertRaisesRegex(ValueError, 'identity_set'):
            planner.build_packet(queue, photo, [])

    def test_duplicate_identity_rejected(self):
        with self.assertRaisesRegex(ValueError, 'duplicate_source'):
            self.build([row(), row()])

    def test_unknown_provider_rejected(self):
        with self.assertRaisesRegex(ValueError, 'invalid_provider'):
            self.build([row(provider='fake')])

    def test_archive_change_rejected_before_json(self):
        with tempfile.TemporaryDirectory() as root:
            path = Path(root) / 'bad.zip'
            path.write_bytes(b'not-the-sealed-artifact')
            with self.assertRaisesRegex(ValueError, 'archive_pin'):
                planner.load_archive(path, 'queue')

    def test_output_create_only_and_deterministic(self):
        first = self.build([row('124'), row('123')])
        second = self.build([row('123'), row('124')])
        self.assertEqual(planner.canonical(first), planner.canonical(second))
        with tempfile.TemporaryDirectory() as root:
            path = Path(root) / 'packet.json'
            expected = planner.write_new(path, first)
            with self.assertRaises(FileExistsError):
                planner.write_new(path, second)
            self.assertEqual(planner.digest(path.read_bytes()), expected)


class RequestQualityTests(unittest.TestCase):
    def quality(self, a, b, **kw):
        return planner.request_quality(row(source_name=a, candidate_name=b, **kw), [])

    def test_false_brand_shared_place_not_a_request_target(self):
        for a, b in [('Mira Beach Resort Bodrum', 'JASMIN BEACH HOTEL BODRUM'),
                     ('Milano Hotel & Spa Sultanahmet', 'SULTANAHMET'),
                     ('Amon Hotels Only Gentleman', 'SAMOY HOTEL SAMOY ONLY 16+'),
                     ('The Meretto Hotel Istanbul', 'THE ISTANBUL HOTEL'),
                     ('Igman Hotel Istanbul', 'IN ISTANBUL HOTEL')]:
            r = row(source_name=a, candidate_name=b)
            p = planner.build_packet(*inputs([r]), offers())
            self.assertEqual(p['targets'][0]['route'], 'needs_candidate_evidence_before_search')
            self.assertEqual(p['anex_discovery_batches'], [])
            self.assertFalse(p['acceptance_authorized'])

    def test_generic_words_never_identify_hotel(self):
        self.assertIn('candidate_name_anchor_missing', self.quality('Hotel Resort Spa', 'Hotel Resort Spa')['reasons'])

    def test_saved_alias_cannot_hide_unrelated_current_brand(self):
        r = row(source_name='Mira Beach Bodrum', candidate_name='Jasmin Beach Bodrum', source_aliases=['Jasmin Beach Bodrum'])
        self.assertTrue(planner.request_quality(r, [])['reasons'])

    def test_exact_compound_and_possessive_remain_discovery(self):
        for a, b in [('Darkhill Hotel', 'DARK HILL HOTEL LALELI'),
                     ("Cook's Club", 'COOKS CLUB'), ('Grand Palace Adult Only', 'THE GRAND PALACE ADULTS ONLY')]:
            self.assertEqual(self.quality(a, b)['reasons'], [])

    def test_strong_typo_is_discovery_not_acceptance(self):
        q = self.quality('Swissotel Resort El Quseir', 'SWISSTEL RESORT EL QUSEIR', name_score=.760135, margin=.180135)
        self.assertEqual(q['reasons'], [])
        self.assertFalse(q['is_acceptance_evidence'])
        for score, margin in [(.74, .18), (.76, .14)]:
            self.assertTrue(self.quality('Swissotel', 'Swisstel', name_score=score, margin=margin)['reasons'])
        self.assertTrue(self.quality('Greenmax Hotel', 'Green Mar Hotel', name_score=.7, margin=.03)['reasons'])

    def test_different_city_context_requires_new_candidate_evidence(self):
        r = row(source_name='Malkoc Hotel', candidate_name='BURSA MALKOC', candidate_region='Бурса')
        ctx = [dict(kind='search_index_only', geography='Turkey/Istanbul', url='https://example.invalid/saved')]
        q = planner.request_quality(r, ctx)
        self.assertIn('candidate_disagrees_with_saved_geography', q['reasons'])
        self.assertEqual(q['saved_context_disagreements'], ctx)
        self.assertFalse(q['is_acceptance_evidence'])

    def test_parent_geography_not_false_conflict(self):
        r = row(source_name='Example Hotel', candidate_name='Example Hotel', candidate_region='Анталья')
        self.assertEqual(planner.request_quality(r, [dict(kind='parsed_catalog', geography='Turkey/Belek', url='saved')])['reasons'], [])

    def test_explicit_excursion_is_not_a_physical_hotel(self):
        for name in ['Тур " Золотое Кольцо Турции" 7н/8д', 'Тур «Стамбул Город Мечты» 3*']:
            p = planner.build_packet(*inputs([row(source_name=name)]), [])
            self.assertEqual(p['targets'], [])
            self.assertEqual(p['excluded'][0]['reason'], 'nonphysical_excursion_product')
        self.assertFalse(planner.nonphysical('Tur Hotel Istanbul'))
        self.assertFalse(planner.nonphysical('Fortuna Hotel Phu Quoc'))

    def test_latest_snapshot_replaces_not_unions_retired_identities(self):
        q, photo = inputs([row('1'), row('2')])
        latest = dict(rows=[row('2'), row('3')], result={'generated_at_utc':'2026-09-14T18:46:36Z'})
        p = planner.build_packet(q, photo, [], latest=latest)
        self.assertEqual({r['key'] for r in p['targets']}, {'anex:2', 'anex:3'})
        self.assertEqual(p['retired_source_keys'], ['anex:1'])
        self.assertEqual(p['new_source_keys'], ['anex:3'])
        self.assertEqual(p['snapshot_at_utc'], latest['result']['generated_at_utc'])
        self.assertFalse(p['current_db_verified'])
        latest['rows'].append(row('2'))
        with self.assertRaisesRegex(ValueError, 'duplicate_current'):
            planner.build_packet(q, photo, [], latest=latest)

    def test_latest_newer_and_native_name_binding(self):
        q, photo = inputs([row()]); photo['rows'][0]['native_anex_hotelcode_confirmed'] = True
        latest = dict(rows=[row(source_name='Changed')], result={'generated_at_utc':'2026-09-14T18:46:36Z'})
        p = planner.build_packet(q, photo, [], latest=latest)
        self.assertFalse(p['targets'][0]['native_anex_hotelcode'])
        latest['result']['generated_at_utc'] = '2026-09-13T18:00:00Z'
        with self.assertRaisesRegex(ValueError, 'not_newer'):
            planner.build_packet(q, photo, [], latest=latest)

    def test_captured_outcomes_do_not_repeat_search_or_override_holds(self):
        r = row()
        observed = dict(expected_anex_hotel_id=123, expected_tourvisor_hotel_id=50,
                        detail_tourvisor_hotel_id=50, operator_identity={'anex_hotel_id':123},
                        tier='DIRECT', reason='direct_identity_confirmed', source_name=r['source_name'],
                        semantic={'state':'corroborated'}, qualifier_conflict=False, numeric_conflict=False, distance_km=None)
        def build(source, item):
            return planner.build_packet(*inputs([source]), offers(), detail=dict(rows=[item], artifact_id=1, result_sha256='fixture'))
        p = build(r, observed)
        self.assertEqual(p['targets'][0]['route'], 'captured_direct_identity_pending_current_acceptance')
        self.assertEqual(p['anex_discovery_batches'], [])
        self.assertFalse(p['acceptance_authorized'])
        contradicted = copy.deepcopy(observed); contradicted['operator_identity']['anex_hotel_id'] = 999
        self.assertEqual(build(r, contradicted)['targets'][0]['route'], 'captured_identity_contradiction')
        for field in ['qualifier_conflict', 'numeric_conflict']:
            held = copy.deepcopy(observed); held[field] = True
            self.assertEqual(build(r, held)['targets'][0]['route'], 'captured_evidence_hold')
            source = copy.deepcopy(r); source[field] = True
            self.assertEqual(build(source, observed)['targets'][0]['route'], 'captured_evidence_hold')
        changed = copy.deepcopy(r); changed['candidate_local_id'] = 99
        self.assertEqual(build(changed, observed)['targets'][0]['route'], 'captured_evidence_hold')

    def test_quality_cannot_erase_coordinate_or_pair_protection(self):
        for kw in [dict(pair_excluded=True), dict(distance_km=6)]:
            p = planner.build_packet(*inputs([row(source_name='Mira', candidate_name='Jasmin', **kw)]), [])
            self.assertEqual(p['targets'][0]['route'], 'protected_hold')

    def test_andromeda_identity_query_independent_of_bad_local_proposal(self):
        r = row(provider='andromeda', source_name='Mira', candidate_name='Jasmin')
        p = planner.build_packet(*inputs([r]), offers())
        self.assertEqual(p['targets'][0]['route'], 'missing_andromeda_identity')
        self.assertTrue(p['targets'][0]['candidate_quality']['reasons'])
        self.assertEqual(p['anex_discovery_batches'], [])

    def test_compact_packet_preserves_quality_and_capture_provenance(self):
        q, photo = inputs([row(source_name='Mira', candidate_name='Jasmin')])
        p = planner.build_packet(q, photo, [])
        c = planner.compact_packet(p)
        self.assertIn('anex:123', c['candidate_quality_holds'])
        self.assertEqual(c['full_packet_sha256'], planner.digest(planner.canonical(p)))

    def test_pinned_new_inputs_and_context_reject_tampering(self):
        with tempfile.TemporaryDirectory() as root:
            path = Path(root)/'bad'; path.write_bytes(b'changed')
            for kind in ['latest', 'detail']:
                with self.assertRaisesRegex(ValueError, 'archive_pin'):
                    planner.load_archive(path, kind)
            with self.assertRaisesRegex(ValueError, 'context_hash'):
                planner.load_context(path)


if __name__ == '__main__':
    unittest.main()
