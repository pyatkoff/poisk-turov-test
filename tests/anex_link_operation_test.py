#!/usr/bin/env python3
"""Boundaries for the one-shot complete106 ANEX v2 live acceptance."""
import ast
from pathlib import Path
import unittest
import yaml

PATH=Path(__file__).resolve().parents[1]/'.github/workflows/andromeda-identity-accept.yml'
WORKFLOW=yaml.load(PATH.read_text(),Loader=yaml.BaseLoader)
STEPS=WORKFLOW['jobs']['accept']['steps']
PREPARE=next(s['run'] for s in STEPS if s.get('name','').startswith('Verify checked'))
CODE=PREPARE.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0]
NODE=next(n for n in ast.parse(CODE).body if isinstance(n,ast.FunctionDef) and n.name=='require_new_operation')
NS={}
exec(compile(ast.Module(body=[NODE],type_ignores=[]),'<workflow-guard>','exec'),NS)
GUARD=NS['require_new_operation']
TITLE='ANEX 1759 complete106 acceptance v2'

class OperationTests(unittest.TestCase):
    def test_owner_first_push_existing_ops_branch_only(self):
        job=WORKFLOW['jobs']['accept']
        self.assertEqual(job['needs'],'check-operation')
        for token in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):
            self.assertIn(token,job['if'])
        self.assertEqual(WORKFLOW['on']['push']['paths'],['.github/workflows/andromeda-identity-accept.yml'])
        self.assertEqual(WORKFLOW['concurrency']['cancel-in-progress'],'false')

    def test_exact_v2_source_artifact_and_request(self):
        self.assertEqual(STEPS[0]['with']['ref'],'ed5cdfb8c93d82cccfb46bb0f957fd14ed092f5e')
        self.assertEqual(WORKFLOW['run-name'],TITLE)
        self.assertEqual(WORKFLOW['env']['OPERATION_ID'],'anex-1759-complete106-20260911-v2')
        for token in (34532728599,34532728666,10171167462,34532232902):
            self.assertIn(str(token),PREPARE)
        for digest in ('1c4ad8fd32f5dfef1b52526a7050fb72c781d76fc1d2e455c72e6d09eff788bd','cec6672929ccd3333c757be1dcc9ba1a0b0df3283d8cd529b1093a31d01e4433'):
            self.assertIn(digest,PREPARE)
        self.assertIn("payload.get('count')!=106",CODE)
        self.assertIn("len({r['catalog_hotel_id'] for r in payload['rows']})!=91",CODE)
        self.assertIn("value.get('head_sha')!=os.environ['SOURCE_SHA']",CODE)
        self.assertIn("value.get('conclusion')!='success'",CODE)

    def test_reservation_precedes_single_private_apply(self):
        reserved=next(i for i,s in enumerate(STEPS) if s.get('id')=='reserved')
        private=[i for i,s in enumerate(STEPS) if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{})]
        self.assertEqual(len(private),1)
        self.assertLess(reserved,private[0])
        command=STEPS[private[0]]['run']
        for token in ('anex_complete_strong_accept.py','--archive','--apply','--receipt','--output'):
            self.assertIn(token,command)
        for old in ('anex_saved_complete_review.py','anex_saved_strong_batch.py','andromeda_country_pending.py','andromeda_saved_bridge.py'):
            self.assertNotIn(old,command)
        self.assertEqual(sum('secrets.' in str(step) for step in STEPS),1)
        self.assertNotIn('secrets.',str(WORKFLOW['jobs']['check-operation']))

    def test_result_requires_full_v2_commit_and_preservation(self):
        private=next(s for s in STEPS if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{}))
        for token in ("receipt.get('state')!='finalized'","result.get('inserted')!=106","result.get('readback_verified') is not True","result.get('previous_rows_unchanged') is not True","result.get('operation_id')!=os.environ['OPERATION_ID']"):
            self.assertIn(token,private['run'])
        self.assertIn("'supplier_calls':0",PREPARE)
        self.assertEqual(STEPS[-1]['if'],"always() && steps.reserved.outcome == 'success'")

    def test_failed_v1_and_completed_old_operations_do_not_block_v2(self):
        old_titles=('ANEX 1759 complete106 acceptance v1','ANEX 1759 complete187 read v1','ANEX 1759 saved strong25 acceptance v1','Andromeda 1759 country pending403 acceptance v1')
        GUARD([{'workflow_runs':[{'id':10}]+[{'id':i+1,'display_title':title,'conclusion':'failure' if i==0 else 'success'} for i,title in enumerate(old_titles)]}],{'total_count':0,'artifacts':[]},10)

    def test_same_v2_operation_any_prior_outcome_blocks(self):
        for outcome in ('success','failure','cancelled',None):
            with self.subTest(outcome=outcome), self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10},{'id':9,'display_title':TITLE,'conclusion':outcome}]}],{'total_count':0,'artifacts':[]},10)

    def test_expired_or_current_v2_reservation_blocks(self):
        for expired in (True,False):
            with self.subTest(expired=expired), self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10}]}],{'total_count':1,'artifacts':[{'expired':expired}]},10)

    def test_missing_history_or_current_run_fails_closed(self):
        for history in ([],{},[{}],[{'workflow_runs':[]}],[{'workflow_runs':[{'id':9}]}]):
            with self.subTest(history=history), self.assertRaises(ValueError):
                GUARD(history,{'total_count':0,'artifacts':[]},10)

    def test_missing_reservation_response_fails_closed(self):
        with self.assertRaises(ValueError):
            GUARD([{'workflow_runs':[{'id':10}]}],{},10)

    def test_embedded_python_compiles(self):
        for step in STEPS:
            text=step.get('run','')
            if "python3 - <<'PYTHON'\n" in text:
                compile(text.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0],'<workflow-python>','exec')

if __name__=='__main__':
    unittest.main(verbosity=2)
