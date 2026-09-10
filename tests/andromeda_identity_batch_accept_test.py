#!/usr/bin/env python3
"""Pinned input and durable receipt regressions; no application access."""
import json, os, tempfile, unittest
from pathlib import Path
from unittest.mock import patch
from zipfile import ZipFile
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import andromeda_identity_batch_accept as batch
import anex_search3_owner_decisions as owner

REPORT = Path(os.environ['ANDROMEDA_SCALE_FULL_REPORT'])


def result_fixture(payload):
    return {'status':'accepted','operation_id':batch.OPERATION_ID,'request_sha256':batch.digest(payload),
            'input_count':92,'updated':92,'readback_verified':True,'other_identities_unchanged':True,
            'supplier_calls':0,'rows':[{'external_hotel_id':r['external_hotel_id'],
            'local_hotel_id':r['local_hotel_id'],'decision_status':'accepted'} for r in payload['rows']]}


class BatchAcceptTests(unittest.TestCase):
    def test_exact_92_only(self):
        rows = batch.load_proposals(REPORT)
        self.assertEqual(len(rows), 92)
        self.assertEqual(len({r['external_hotel_id'] for r in rows}), 92)
        self.assertEqual(len({r['local_hotel_id'] for r in rows}), 92)
        self.assertEqual({r['country_id'] for r in rows}, {1, 4})
        self.assertTrue(all(r['geography']['status'] == 'supported' for r in rows))
        self.assertEqual(batch.digest(batch.request(REPORT)), '2a83b140e1ba36ad2d41168cd67abb1b3f05329e29793af41f5f5d23b7905024')

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

    def test_receipt_precedes_transport_and_no_repeat(self):
        with tempfile.TemporaryDirectory() as td:
            receipt=Path(td)/'receipt.json';payload=batch.request(REPORT)
            def remote(source, req, **kwargs):
                self.assertEqual(json.loads(receipt.read_bytes())['state'],'reserved')
                self.assertEqual(req,payload)
                self.assertIn('class AnyTourAnexSearchMappingRegistry',source)
                return result_fixture(payload)
            with patch.object(owner,'ssh_php',side_effect=remote) as transport:
                self.assertEqual(batch.apply(REPORT,receipt)['updated'],92)
                self.assertEqual(batch.apply(REPORT,receipt)['status'],'already_finalized')
                self.assertEqual(transport.call_count,1)

    def test_unknown_never_replayed(self):
        with tempfile.TemporaryDirectory() as td:
            receipt=Path(td)/'receipt.json'
            with patch.object(owner,'ssh_php',side_effect=TimeoutError) as transport:
                with self.assertRaises(TimeoutError):batch.apply(REPORT,receipt)
                with self.assertRaisesRegex(ValueError,'do_not_replay'):batch.apply(REPORT,receipt)
                self.assertEqual(transport.call_count,1)

    def test_bad_readback_is_retained_not_finalized(self):
        with tempfile.TemporaryDirectory() as td:
            receipt=Path(td)/'receipt.json';bad=result_fixture(batch.request(REPORT))
            bad['rows'][0]['local_hotel_id']=1
            with patch.object(owner,'ssh_php',return_value=bad):
                with self.assertRaises(ValueError):batch.apply(REPORT,receipt)
            self.assertEqual(json.loads(receipt.read_bytes())['state'],'reserved')
            self.assertTrue(receipt.with_name('receipt.json.outcome.json').exists())


def write_mysql_fixture():
    base=REPORT.parent
    with ZipFile(base/'egypt.zip') as archive:
        egypt=json.loads(archive.read('import-request.json'))
        egypt_local=json.loads(archive.read('local-catalog.json'))
    with ZipFile(base/'turkey.zip') as archive:
        turkey=json.loads(archive.read('import-request.json'))
        turkey_local=json.loads(archive.read('capture.json'))['local']
    identities={}
    for document in (egypt,turkey):
        for row in document['rows']:
            identities.setdefault(row['external_hotel_id'],dict(row,catalog_sha256=document['catalog_sha256']))
    payload=batch.request(REPORT)
    fixture={'request':payload,'identities':[identities[r['external_hotel_id']] for r in payload['rows']],
             'hotels':egypt_local['hotels']+turkey_local['hotels'],
             'aliases':egypt_local['aliases']+turkey_local['aliases']}
    for row in fixture['identities']:
        assert row['decision_status']=='pending'
    (base/'andromeda-92-fixture.json').write_bytes(batch.canonical(fixture)+b'\n')


if __name__ == '__main__':
    result=unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(BatchAcceptTests))
    if result.wasSuccessful():write_mysql_fixture()
    sys.exit(0 if result.wasSuccessful() else 1)
