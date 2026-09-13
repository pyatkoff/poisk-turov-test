from pathlib import Path
import json
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
DIAG = ROOT / 'scripts' / 'diagnostics'
sys.path.insert(0, str(DIAG))

import anex_freight_ref_binding as binding


SEALED_UNKNOWN = {
    'additional_prices_requests': 0,
    'additional_prices_tour_binding_verified': False,
    'andromeda_requests': 0,
    'anex_requests': 4,
    'automatic_retry': False,
    'booking_calls': 0,
    'broninit_calls': 0,
    'elapsed_ms': 3970,
    'experiment_id': binding.EXPERIMENT,
    'mapping_writes': 0,
    'production_price_arithmetic_applied': False,
    'reason': 'FREIGHT_REF_BINDING_SEARCH_EMPTY',
    'ref_binding': None,
    'schema_version': 1,
    'selected_concrete': None,
    'selected_transport_verified': False,
    'status': 'unknown',
    'supplier_effect': 'unknown',
    'supplier_replay_allowed': False,
    'tourvisor_requests': 0,
}


def completed_result():
    return {
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


class FreightRefBindingTest(unittest.TestCase):
    def test_completed_exact_key_overlap(self):
        value = completed_result()
        self.assertIs(binding.validate(value), value)
        report = binding.summarize(value)
        self.assertTrue(report['ref_binding']['freight_key_namespace_match_verified'])
        self.assertFalse(report['additional_prices_tour_binding_verified'])
        self.assertFalse(report['selected_transport_verified'])
        self.assertFalse(report['production_price_arithmetic_applied'])

    def test_completed_non_overlap_is_valid_falsification(self):
        value = completed_result()
        value['ref_binding'].update({
            'searchtour_refs': {'outbound': '166896', 'return': '168151'},
            'outbound_route_indexes': [],
            'return_route_indexes': [],
            'both_refs_exist_in_freightmonitor': False,
            'ordered_route_key_match': False,
            'reversed_route_key_match': False,
            'freight_key_namespace_match_verified': False,
        })
        self.assertIs(binding.validate(value), value)

    def test_sealed_unknown_artifact_is_readable_but_not_replayable(self):
        self.assertIs(binding.validate(SEALED_UNKNOWN), SEALED_UNKNOWN)
        report = binding.summarize(SEALED_UNKNOWN)
        self.assertEqual(report['status'], 'unknown')
        self.assertEqual(report['reason'], 'FREIGHT_REF_BINDING_SEARCH_EMPTY')
        self.assertFalse(report['supplier_replay_allowed'])
        self.assertEqual(report['sealed_evidence']['artifact_id'], 10312404322)
        self.assertEqual(report['sealed_evidence']['disposition'], 'sealed_prohibited_replay_not_new_p0_evidence')

    def test_reader_cli_has_no_transport_or_output_checkpoint(self):
        with tempfile.TemporaryDirectory() as td:
            source = Path(td) / 'result.json'
            source.write_text(json.dumps(SEALED_UNKNOWN), encoding='utf-8')
            proc = subprocess.run(
                [sys.executable, '-B', str(DIAG / 'anex_freight_ref_binding.py'), str(source)],
                check=False, capture_output=True, text=True,
            )
            self.assertEqual(proc.returncode, 0, proc.stderr)
            report = json.loads(proc.stdout)
            self.assertEqual(report['status'], 'unknown')
            self.assertEqual(sorted(Path(td).iterdir()), [source])

    def test_money_booking_and_network_boundaries_are_absent(self):
        text = (DIAG / 'anex_freight_ref_binding.py').read_text(encoding='utf-8')
        self.assertNotIn('anex_search3_three_source_price', text)
        self.assertNotIn('ssh_php_no_mux', text)
        self.assertNotIn('php_source', text)
        self.assertNotIn('AnyTourAnexClient', text)
        self.assertNotIn('FreightMonitor_FREIGHTSBYPACKET', text)
        self.assertNotIn('AdditionalPricesDaily', text)
        self.assertNotIn('bron_ticket', text)
        self.assertNotIn('broninit(', text)
        self.assertNotIn('->calc(', text)
        self.assertNotIn('requests.', text)
        self.assertIn("'supplier_replay_allowed': False", text)

    def test_invalid_ref_is_rejected(self):
        value = completed_result()
        value['ref_binding']['searchtour_refs'] = {'outbound': '01', 'return': '2'}
        with self.assertRaisesRegex(ValueError, 'freight_ref_binding_refs'):
            binding.validate(value)

    def test_partial_unknown_is_rejected(self):
        value = dict(SEALED_UNKNOWN)
        value['ref_binding'] = {'searchtour_refs': {'outbound': '1', 'return': '2'}}
        with self.assertRaisesRegex(ValueError, 'freight_ref_binding_unknown_partial'):
            binding.validate(value)

    def test_sensitive_saved_payload_is_rejected(self):
        value = dict(SEALED_UNKNOWN)
        value['reason'] = 'Bearer secret'
        with self.assertRaisesRegex(ValueError, 'freight_ref_binding_sensitive'):
            binding.validate(value)


if __name__ == '__main__':
    unittest.main()
