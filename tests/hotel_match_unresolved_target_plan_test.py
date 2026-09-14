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
                 country_id=country, search_count=10, source_name='Example Beach Hotel',
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


if __name__ == '__main__':
    unittest.main()
