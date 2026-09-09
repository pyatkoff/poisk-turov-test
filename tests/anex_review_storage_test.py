import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts/diagnostics'))
import anex_review_storage as storage

class StorageTests(unittest.TestCase):
    def fixture(self, directory):
        cp={'inherited':[{'external_id':i} for i in range(1,91)],
            'rows':[{'external_id':i} for i in range(91,293)],'completed_total':292,
            'in_flight':[],'batch_needs_finalization':False}
        triage={'schema_version':1,'scope':'preview','kind':'observed_review_dossiers','source_sha':'a'*40,
                'checkpoint_sha256':storage.gaps.digest(cp),'summary':{'count':0},'rows':[]}
        for name,value in [('anex-observed-hotel-checkpoint.json',cp),('anex-observed-hotel-triage.json',triage),
                           ('anex-checkpoint-source.json',{'artifact_id':10094445724,'run_id':34327629358})]:
            (directory/name).write_text(json.dumps(value))
        return cp,triage
    def test_inspect_binds_source(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d)
            reservation,envelope=storage.prepare(d,'b'*40)
            self.assertEqual(reservation['action'],'inspect');self.assertEqual(envelope['artifact_id'],10094445724)
            self.assertEqual(json.loads((d/'anex-review-storage-reservation.json').read_bytes()),reservation)
    def test_apply_requires_pinned_digest(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d)
            plan={'action':'apply','schema_sha256':storage.schema_files()[1]}
            with self.assertRaisesRegex(ValueError,'pinned'):storage.prepare(d,'b'*40,plan)
            plan.update(triage_sha256=storage.digest((d/'anex-observed-hotel-triage.json').read_bytes()),readiness_schema_sha256=plan['schema_sha256'])
            self.assertEqual(storage.prepare(d,'b'*40,plan)[0]['action'],'apply')
    def test_oversized_dossier_allows_only_schema_inspection(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d)
            path=d/'anex-observed-hotel-triage.json'
            raw=path.read_bytes()+b' ' * storage.packer().MAX_BYTES
            path.write_bytes(raw)
            reservation,envelope=storage.prepare(d,'b'*40)
            self.assertIsNone(envelope)
            self.assertIsNone(reservation['rows'])
            self.assertEqual(reservation['dossier_status'],'deferred_source_bound')
            self.assertEqual(reservation['triage_bytes'],len(raw))
            self.assertEqual(reservation['triage_sha256'],storage.digest(raw))
            plan={'action':'apply','schema_sha256':storage.schema_files()[1],
                  'readiness_schema_sha256':storage.schema_files()[1], 'triage_sha256':storage.digest(raw)}
            with self.assertRaisesRegex(ValueError,'source_digest_or_bound'):
                storage.prepare(d,'b'*40,plan)
    def test_inspect_fingerprint_is_bounded_and_detects_changed_bytes(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d)
            path=d/'anex-observed-hotel-triage.json'
            original=storage.read_triage(path,1)
            path.write_bytes(path.read_bytes()+b' ')
            changed=storage.read_triage(path,1)
            self.assertIsNone(changed[0])
            self.assertNotEqual(original[1],changed[1])
            self.assertEqual(changed[2],original[2]+1)
            with patch.object(storage,'MAX_INSPECT_SOURCE_BYTES',10):
                with self.assertRaisesRegex(ValueError,'inspection_source_bound'):
                    storage.read_triage(path,1)
    def test_inflight_and_checkpoint_mismatch(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);cp,_=self.fixture(d);cp['in_flight']=[1]
            (d/'anex-observed-hotel-checkpoint.json').write_text(json.dumps(cp))
            with self.assertRaisesRegex(ValueError,'completed'):storage.prepare(d,'b'*40)
            cp['in_flight']=[];cp['extra']=True
            (d/'anex-observed-hotel-checkpoint.json').write_text(json.dumps(cp))
            with self.assertRaisesRegex(ValueError,'mismatch'):storage.prepare(d,'b'*40)
    def test_schema_and_source_boundary(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d)
            with self.assertRaises(ValueError):storage.prepare(d,'not-sha')
            with self.assertRaises(ValueError):storage.prepare(d,'b'*40,{'action':'inspect','schema_sha256':'0'*64})
    def test_inherited_history_count_and_duplicates(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);cp,triage=self.fixture(d)
            self.assertEqual(storage.prepare(d,'b'*40)[0]['action'],'inspect')
            cp['rows'][0]['external_id']=1
            (d/'anex-observed-hotel-checkpoint.json').write_text(json.dumps(cp))
            with self.assertRaisesRegex(ValueError,'completed'):storage.prepare(d,'b'*40)
    def test_php_composition_and_no_supplier(self):
        source=storage.php_source()
        self.assertEqual(source.count('declare(strict_types=1);'),1)
        self.assertNotIn('<?php',source)
        self.assertIn('class AnexReviewSchemaManager',source)
        self.assertIn('class AnexReviewDossierStore',source)
        self.assertNotIn('AnyTourAnexClient',source)
    def test_saved_search_candidate_overlap_is_not_acceptance(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp)
            result={'status':'ok','offers':[{'hotel_id':123},{'hotel_id':'123'}]}
            cp={'cases':{'tv_day':{'state':'completed','result':result,'result_sha256':storage.gaps.digest(result)}}}
            raw=json.dumps(cp).encode();(d/'saved.json').write_bytes(raw)
            def row(i,candidates,hints):
                return {'id':i,'row_json':json.dumps({'status':'review','observation':{'hotel_name':'Example'},'evidence':{'candidates':candidates},'prior_fixed_queue_hints':hints})}
            envelope={'artifact_id':5,'source_digest':'a'*64,'rows':[row(1,[{'id':123}],[]),row(2,[],[{'id':123}])]}
            with patch.object(storage,'SAVED_SOURCES',{'saved.json':(storage.digest(raw),('tv_day',))}):
                audit=storage.saved_search_audit(d,envelope)
                self.assertEqual(audit['saved_tv_unique_hotels'],1)
                self.assertEqual(audit['with_candidate_seen_in_saved_tv'],1)
                self.assertEqual(audit['with_historical_hint_only_seen_in_saved_tv'],1)
                self.assertEqual(audit['new_bindings'],0)
                (d/'saved.json').write_bytes(raw+b' ')
                with self.assertRaisesRegex(ValueError,'changed'):storage.saved_search_audit(d,envelope)
    def test_review_mode_isolated(self):
        import yaml
        workflow=yaml.safe_load((ROOT/'.github/workflows/anex-access-probe.yml').read_text())
        for step in workflow['jobs']['verify-access']['steps']:
            run=step.get('run','')
            if any(line.startswith('python3 -B scripts/diagnostics/') and any(x in line for x in ['--accept','_mapping_probe.py','_preview_deploy.py','_observed_queue.py']) for line in run.splitlines()):
                self.assertIn('if',step);self.assertNotIn("== 'review'",step['if'])

if __name__=='__main__':unittest.main()
