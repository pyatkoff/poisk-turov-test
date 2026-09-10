#!/usr/bin/env python3
import json, os, tempfile, unittest
from pathlib import Path
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import andromeda_identity_batch_accept as batch

REPORT = Path(os.environ['ANDROMEDA_SCALE_FULL_REPORT'])

class BatchAcceptTests(unittest.TestCase):
    def test_exact_92_only(self):
        rows = batch.load_proposals(REPORT)
        self.assertEqual(len(rows), 92)
        self.assertEqual(len({r['external_hotel_id'] for r in rows}), 92)
        self.assertEqual(len({r['local_hotel_id'] for r in rows}), 92)
        self.assertEqual({r['country_id'] for r in rows}, {1, 4})
        self.assertTrue(all(r['geography']['status'] == 'supported' for r in rows))

    def test_request_is_non_supplier_append_only_decision(self):
        req = batch.request(REPORT)
        self.assertEqual(req['operation_id'], batch.OPERATION_ID)
        self.assertTrue(req['append_only_decisions'])
        self.assertEqual(req['supplier_calls'], 0)
        self.assertNotIn('sql', req)

    def test_report_tamper_rejected(self):
        value = json.loads(REPORT.read_bytes()); value['database_writes'] = 1
        with tempfile.TemporaryDirectory() as td:
            p = Path(td)/'bad.json'; p.write_text(json.dumps(value), encoding='utf-8')
            with self.assertRaises(ValueError): batch.load_proposals(p)

    def test_no_apply_authority_in_rows(self):
        for row in batch.load_proposals(REPORT):
            self.assertNotIn('operation', row)
            self.assertNotIn('decision_status', row)
            self.assertEqual(row['geography']['status'], 'supported')

if __name__ == '__main__': unittest.main(verbosity=2)
