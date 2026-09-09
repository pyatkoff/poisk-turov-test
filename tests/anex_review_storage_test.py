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
    def setUp(self):
        env=patch.dict(storage.os.environ,{'GITHUB_RUN_ID':'123','GITHUB_RUN_ATTEMPT':'1'})
        env.start();self.addCleanup(env.stop)

    def fixture(self, directory):
        # Fixtures never inherit an explicit live apply manifest.
        plan_path=directory/'inspect-plan.json'
        plan_path.write_text(json.dumps({'action':'inspect','schema_sha256':storage.schema_files()[1]}))
        plan_patch=patch.object(storage,'PLAN',plan_path);plan_patch.start();self.addCleanup(plan_patch.stop)
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
    def test_formatted_source_keeps_original_hash_and_compact_bound(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d)
            path=d/'anex-observed-hotel-triage.json';raw=path.read_bytes()+b' '*8_000_001
            path.write_bytes(raw)
            reservation,envelope=storage.prepare(d,'b'*40)
            self.assertEqual(envelope['source_digest'],storage.digest(raw))
            self.assertGreater(reservation['formatted_bytes'],8_000_000)
            data=json.loads(raw);data['padding']='x'*8_000_001
            raw=json.dumps(data).encode()
            with self.assertRaisesRegex(ValueError,'compact_bound'):storage.packer().pack(raw,storage.digest(raw),5)
    def test_apply_requires_pinned_digest(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d)
            plan={'action':'apply','schema_sha256':storage.schema_files()[1]}
            with self.assertRaisesRegex(ValueError,'pinned'):storage.prepare(d,'b'*40,plan)
            plan.update(triage_sha256=storage.digest((d/'anex-observed-hotel-triage.json').read_bytes()),readiness_schema_sha256=plan['schema_sha256'],bootstrap_artifact_id=10094445724,expected_rows=0)
            self.assertEqual(storage.prepare(d,'b'*40,plan)[0]['action'],'apply')

    def apply_plan(self,d):
        return {'action':'apply','schema_sha256':storage.schema_files()[1],
                'readiness_schema_sha256':storage.schema_files()[1],
                'triage_sha256':storage.digest((d/'anex-observed-hotel-triage.json').read_bytes()),
                'bootstrap_artifact_id':10094445724,'expected_rows':0}

    def result(self,reservation):
        preserved={k:{'count':0,'sha256':'a'*64} for k in ['catalog_hotels','anex_hotels',
          'anex_hotel_auto_matches','anex_hotel_candidates','anex_hotel_search_mappings','anex_hotel_decisions',
          'anex_review_state','anex_review_pair_exclusions','anex_review_audit']}
        return {'status':'ok','supplier_requests':0,'source_sha':'b'*40,'panel_published':False,
          'schema':{'schema_sha256':reservation['schema_sha256'],'preserved':True,'read_only':False,
                    'status':'ready','after_schema':{'ready':True,'missing':[]}},
          'import':{'status':'stored','artifact_id':10094445724,'rows':0,'verified_ids':0,'inserted':0,
                    'before':preserved,'after':json.loads(json.dumps(preserved))}}

    def test_apply_finalization_and_no_sql_on_later_restore(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d);plan=self.apply_plan(d)
            reservation,_=storage.prepare(d,'b'*40,plan)
            self.assertEqual(json.loads((d/storage.CHECKPOINT).read_bytes())['state'],'reserved')
            with patch.object(storage,'execute',return_value=self.result(reservation)) as execute:
                storage.run_operation(d,'b'*40,plan);self.assertEqual(execute.call_count,1)
                self.assertEqual(json.loads((d/storage.CHECKPOINT).read_bytes())['state'],'completed')
                # New live checkpoint, source and artifact do not repackage completed dossiers.
                (d/'anex-observed-hotel-checkpoint.json').unlink()
                (d/'anex-observed-hotel-triage.json').unlink()
                (d/'anex-review-storage-report.json').unlink()
                (d/'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id':99999999999}))
                with patch.dict(storage.os.environ,{'GITHUB_RUN_ATTEMPT':'2'}):
                    storage.prepare(d,'c'*40,plan)
                    self.assertEqual(storage.run_operation(d,'c'*40,plan)['status'],'already_finalized')
                self.assertEqual(execute.call_count,1)
                self.assertEqual(json.loads((d/'anex-review-storage-report.json').read_bytes())['import']['verified_ids'],0)

    def test_reserved_previous_attempt_is_unknown(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d);plan=self.apply_plan(d);storage.prepare(d,'b'*40,plan)
            with patch.dict(storage.os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),patch.object(storage,'execute') as execute:
                with self.assertRaisesRegex(ValueError,'outcome unknown'):storage.run_operation(d,'b'*40,plan)
                execute.assert_not_called()

    def test_interrupted_operation_cannot_repeat_even_in_same_job(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d);plan=self.apply_plan(d);storage.prepare(d,'b'*40,plan)
            with patch.object(storage,'execute',side_effect=TimeoutError('unknown')) as execute:
                with self.assertRaises(TimeoutError):storage.run_operation(d,'b'*40,plan)
                self.assertEqual(json.loads((d/storage.CHECKPOINT).read_bytes())['state'],'executing')
                with self.assertRaisesRegex(ValueError,'outcome unknown'):storage.run_operation(d,'b'*40,plan)
                self.assertEqual(execute.call_count,1)

    def test_missing_checkpoint_after_bootstrap_and_partial_write_fail_closed(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d);plan=self.apply_plan(d)
            (d/'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id':99999999999}))
            with self.assertRaisesRegex(ValueError,'lineage'):storage.prepare(d,'b'*40,plan)
            (d/storage.CHECKPOINT).with_suffix('.pending').write_bytes(b'{')
            with self.assertRaisesRegex(ValueError,'outcome unknown'):storage.prepare(d,'b'*40,plan)

    def test_completion_requires_readback_and_preservation(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d);plan=self.apply_plan(d);reservation,_=storage.prepare(d,'b'*40,plan)
            report=self.result(reservation);report['import']['after']['anex_hotel_decisions']['count']=1
            with patch.object(storage,'execute',return_value=report):
                with self.assertRaisesRegex(ValueError,'preservation'):storage.run_operation(d,'b'*40,plan)
            self.assertEqual(json.loads((d/storage.CHECKPOINT).read_bytes())['state'],'executing')

    def test_completed_report_corruption_and_plan_change_fail_closed(self):
        with tempfile.TemporaryDirectory() as temp:
            d=Path(temp);self.fixture(d);plan=self.apply_plan(d);reservation,_=storage.prepare(d,'b'*40,plan)
            with patch.object(storage,'execute',return_value=self.result(reservation)):storage.run_operation(d,'b'*40,plan)
            changed=dict(plan,expected_rows=1)
            with self.assertRaisesRegex(ValueError,'checkpoint mismatch'):storage.prepare(d,'b'*40,changed)
            cp=json.loads((d/storage.CHECKPOINT).read_bytes());cp['report']['import']['verified_ids']=1
            (d/storage.CHECKPOINT).write_text(json.dumps(cp))
            with self.assertRaisesRegex(ValueError,'completion report mismatch'):storage.prepare(d,'b'*40,plan)
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
