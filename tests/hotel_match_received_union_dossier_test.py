import copy
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

spec = importlib.util.spec_from_file_location('receiver', Path(__file__).resolve().parents[1] /
        'scripts/diagnostics/hotel_match_received_union_dossier.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


def fact(op='115', native='800', hotel='700'):
    return dict(operator_key=op, native_hotel_id=native, andromeda_hotel_id=hotel,
                country_id=1, action='price', is_operator_hotel_key=False,
                hotel_name='Example Beach', original_name='Example Beach',
                request_sha256='a'*64, response_sha256='b'*64)


def current():
    return dict(state='completed_read_only', supplier_calls=0, operation_id='new-read',
                source_sha='c'*40, target_count=1, existing_operator_rows=[],
                targets=[dict(andromeda_hotel_id='700', country_id=1,
                              decision_status='accepted', local_hotel_id=10,
                              frequency=12, local=dict(id=10, name='Example Beach'))])


class ReceiverGuards(unittest.TestCase):
    def test_accepted_anchor_is_never_safe_or_apply_authority(self):
        r = m.reconcile([fact()], current(), [])
        self.assertFalse(r['auto_accept'])
        self.assertFalse(r['apply_manifest'])
        self.assertEqual(r['safe_mappings'], 0)
        self.assertFalse(r['candidates'][0]['auto_accept'])

    def test_newly_committed_row_leaves_the_candidate_pool(self):
        c = current()
        c['existing_operator_rows'] = [dict(supplier_namespace='operator_115',
             external_hotel_id='800', decision_status='accepted', local_hotel_id=10)]
        r = m.reconcile([fact()], c, [])
        self.assertEqual(r['candidate_count_not_safe'], 0)
        self.assertEqual(r['relation_counts'], {'already_linked_same_target': 1})

    def test_same_number_in_another_namespace_does_not_deduplicate(self):
        c = current()
        c['existing_operator_rows'] = [dict(supplier_namespace='operator_5',
             external_hotel_id='800', decision_status='accepted', local_hotel_id=10)]
        self.assertEqual(m.reconcile([fact()], c, [])['candidate_count_not_safe'], 1)

    def test_prior_anex_hold_propagates_to_biblio_same_anchor(self):
        hold = dict(operator_key='5', native_hotel_id='10', andromeda_hotel_id='700',
                    snapshot_local_id=10, reason_for_evidence_hold='Wrong primary geography')
        candidate = m.reconcile([fact()], current(), [hold])['candidates'][0]
        self.assertIn('prior_primary_identity_hold_on_same_anchor', candidate['dependency_reasons'])
        self.assertEqual(candidate['inherited_identity_holds'], [hold])

    def test_hold_is_not_applied_to_a_different_current_target(self):
        hold = dict(andromeda_hotel_id='700', snapshot_local_id=99)
        c = m.reconcile([fact()], current(), [hold])['candidates'][0]
        self.assertEqual(c['inherited_identity_holds'], [])
        self.assertFalse(c['auto_accept'])

    def test_existing_conflict_is_not_a_candidate(self):
        c = current()
        c['existing_operator_rows'] = [dict(supplier_namespace='operator_115',
             external_hotel_id='800', decision_status='conflict', local_hotel_id=None)]
        r = m.reconcile([fact()], c, [])
        self.assertEqual(r['relation_counts'], {'protected_nonaccepted_native': 1})
        self.assertEqual(r['candidates'], [])

    def test_accepted_target_conflict_is_never_rewritten(self):
        c = current()
        c['existing_operator_rows'] = [dict(supplier_namespace='operator_115',
             external_hotel_id='800', decision_status='accepted', local_hotel_id=11)]
        r = m.reconcile([fact()], c, [])
        self.assertEqual(r['relation_counts'], {'accepted_target_conflict': 1})
        self.assertEqual(r['candidates'], [])

    def test_current_pending_stays_pending_and_country_conflict_holds(self):
        c = current()
        c['targets'][0].update(decision_status='pending', local_hotel_id=None, local=None)
        self.assertEqual(len(m.reconcile([fact()], c, [])['unresolved_anchors']), 1)
        c['targets'][0]['country_id'] = 4
        self.assertEqual(m.reconcile([fact()], c, [])['relation_counts'], {'country_conflict': 1})

    def test_duplicate_evidence_and_current_keys_fail_closed(self):
        with self.assertRaisesRegex(ValueError, 'duplicate_evidence_pair'):
            m.reconcile([fact(), fact()], current(), [])
        c = current()
        c['targets'].append(copy.deepcopy(c['targets'][0]))
        c['target_count'] = 2
        with self.assertRaisesRegex(ValueError, 'duplicate_current_target'):
            m.reconcile([fact()], c, [])

    def test_namespaced_source_contract_and_digest_required(self):
        for change in [dict(is_operator_hotel_key=True), dict(response_sha256='missing'),
                       dict(country_id=99), dict(operator_key='999'), dict(hotel_name='')]:
            f = fact(); f.update(change)
            with self.assertRaises(ValueError):
                m.reconcile([f], current(), [])

    def test_former_name_does_not_remove_current_posh_or_building(self):
        c = current()
        c['targets'][0]['local']['name'] = 'POSH Example Beach (EX. Example Beach)'
        row = m.reconcile([fact()], c, [])['candidates'][0]
        self.assertIn('primary_qualifiers_require_independent_evidence', row['dependency_reasons'])
        f = fact(); f['hotel_name'] = f['original_name'] = 'Example Mountain View'
        row = m.reconcile([f], current(), [])['candidates'][0]
        self.assertTrue(row['review_signals'])

    def test_two_thousand_rows_preserved_in_frequency_order(self):
        c = current(); c['targets'] = []
        facts = []
        for i in range(1, 2001):
            t = copy.deepcopy(current()['targets'][0])
            t.update(andromeda_hotel_id=str(i), frequency=i)
            c['targets'].append(t)
            facts.append(fact(native=str(i), hotel=str(i)))
        c['target_count'] = 2000
        r = m.reconcile(facts, c, [])
        self.assertEqual(r['pair_count'], 2000)
        self.assertEqual(len(r['candidates']), 2000)
        self.assertEqual(r['candidates'][0]['frequency'], 2000)
        self.assertEqual(r['candidates'][-1]['frequency'], 1)

    def test_tampered_receipt_and_unsafe_archive_rejected(self):
        result = dict(state='completed_read_only', operation_id='op', source_sha='a'*40,
                      no_replay=True, database_writes=0)
        raw = json.dumps(result).encode()
        receipt = dict(result, readback_verified=True, result_sha256='0'*64)
        with self.assertRaisesRegex(ValueError, 'receipt_digest'):
            m.verify({'result.json': raw, 'receipt.json': json.dumps(receipt).encode()})
        with tempfile.TemporaryDirectory() as d:
            p = Path(d)/'bad.zip'
            with zipfile.ZipFile(p, 'w') as z: z.writestr('../result.json', '{}')
            with self.assertRaisesRegex(ValueError, 'unsafe_zip_path'):
                m.read_archive(p, m.digest(p.read_bytes()))

    def test_duplicate_json_keys_rejected(self):
        with self.assertRaisesRegex(ValueError, 'duplicate_json_key'):
            m.parse('{"state":"pending","state":"accepted"}')


if __name__ == '__main__':
    unittest.main()
