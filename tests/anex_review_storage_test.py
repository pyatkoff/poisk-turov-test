import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest

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
    def test_review_mode_isolated(self):
        import yaml
        workflow=yaml.safe_load((ROOT/'.github/workflows/anex-access-probe.yml').read_text())
        for step in workflow['jobs']['verify-access']['steps']:
            run=step.get('run','')
            if any(line.startswith('python3 -B scripts/diagnostics/') and any(x in line for x in ['--accept','_mapping_probe.py','_preview_deploy.py','_observed_queue.py']) for line in run.splitlines()):
                self.assertIn('if',step);self.assertNotIn("== 'review'",step['if'])

if __name__=='__main__':unittest.main()
