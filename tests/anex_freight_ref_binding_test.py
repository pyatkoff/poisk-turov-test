from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
DIAG = ROOT / 'scripts' / 'diagnostics'
sys.path.insert(0, str(DIAG))

import anex_freight_ref_binding as binding


class FreightRefBindingTest(unittest.TestCase):
    def test_completed_exact_key_overlap(self):
        value = {
            'schema_version': 1,
            'experiment_id': binding.EXPERIMENT,
            'status': 'completed',
            'automatic_retry': False,
            'supplier_replay_allowed': False,
            'anex_requests': 6,
            'additional_prices_requests': 0,
            'tourvisor_requests': 0,
            'andromeda_requests': 0,
            'booking_calls': 0,
            'broninit_calls': 0,
            'mapping_writes': 0,
            'production_price_arithmetic_applied': False,
            'additional_prices_tour_binding_verified': False,
            'selected_transport_verified': False,
            'supplier_effect': 'read_only_search_expand_freight_ref_binding_completed',
            'selected_concrete': {
                'kind': 'concrete', 'price': '120000', 'currency': 'RUB',
                'supplier_tour_program_id': '2637', 'final_price_verified': False,
            },
            'ref_binding': {
                'searchtour_refs': {'outbound': '15578', 'return': '15577'},
                'freightmonitor_route_keys': [['15578'], ['15577']],
                'outbound_route_indexes': [0],
                'return_route_indexes': [1],
                'both_refs_exist_in_freightmonitor': True,
                'ordered_route_key_match': True,
                'reversed_route_key_match': False,
                'freight_key_namespace_match_verified': True,
            },
        }
        self.assertIs(binding.validate(value), value)
        report = binding.summarize(value)
        self.assertTrue(report['ref_binding']['freight_key_namespace_match_verified'])
        self.assertFalse(report['additional_prices_tour_binding_verified'])
        self.assertFalse(report['selected_transport_verified'])
        self.assertFalse(report['production_price_arithmetic_applied'])

    def test_completed_non_overlap_is_valid_falsification(self):
        value = {
            'schema_version': 1,
            'experiment_id': binding.EXPERIMENT,
            'status': 'completed',
            'automatic_retry': False,
            'supplier_replay_allowed': False,
            'anex_requests': 6,
            'additional_prices_requests': 0,
            'tourvisor_requests': 0,
            'andromeda_requests': 0,
            'booking_calls': 0,
            'broninit_calls': 0,
            'mapping_writes': 0,
            'production_price_arithmetic_applied': False,
            'additional_prices_tour_binding_verified': False,
            'selected_transport_verified': False,
            'supplier_effect': 'read_only_search_expand_freight_ref_binding_completed',
            'selected_concrete': {'kind': 'concrete', 'price': '120000'},
            'ref_binding': {
                'searchtour_refs': {'outbound': '166896', 'return': '168151'},
                'freightmonitor_route_keys': [['15578'], ['15577']],
                'outbound_route_indexes': [],
                'return_route_indexes': [],
                'both_refs_exist_in_freightmonitor': False,
                'ordered_route_key_match': False,
                'reversed_route_key_match': False,
                'freight_key_namespace_match_verified': False,
            },
        }
        self.assertIs(binding.validate(value), value)

    def test_money_and_booking_boundaries_are_absent(self):
        text = (DIAG / 'anex_freight_ref_binding.py').read_text()
        self.assertIn("'additional_prices_tour_binding_verified' => false", text)
        self.assertIn("'selected_transport_verified' => false", text)
        self.assertIn("'production_price_arithmetic_applied' => false", text)
        self.assertNotIn('AdditionalPricesDaily', text)
        self.assertNotIn('bron_ticket', text)
        self.assertNotIn('->bron(', text)
        self.assertNotIn('broninit(', text)
        self.assertNotIn('->calc(', text)
        self.assertIn("$client->request('FreightMonitor_FREIGHTSBYPACKET'", text)
        self.assertIn("'supplier_replay_allowed' => false", text)

    def test_invalid_ref_is_rejected(self):
        value = {
            'schema_version': 1,
            'experiment_id': binding.EXPERIMENT,
            'status': 'completed',
            'automatic_retry': False,
            'supplier_replay_allowed': False,
            'anex_requests': 6,
            'additional_prices_requests': 0,
            'tourvisor_requests': 0,
            'andromeda_requests': 0,
            'booking_calls': 0,
            'broninit_calls': 0,
            'mapping_writes': 0,
            'production_price_arithmetic_applied': False,
            'additional_prices_tour_binding_verified': False,
            'selected_transport_verified': False,
            'supplier_effect': 'read_only_search_expand_freight_ref_binding_completed',
            'selected_concrete': {'kind': 'concrete'},
            'ref_binding': {
                'searchtour_refs': {'outbound': '01', 'return': '2'},
                'freightmonitor_route_keys': [['1'], ['2']],
                'outbound_route_indexes': [], 'return_route_indexes': [1],
            },
        }
        with self.assertRaisesRegex(ValueError, 'freight_ref_binding_refs'):
            binding.validate(value)


if __name__ == '__main__':
    unittest.main()
