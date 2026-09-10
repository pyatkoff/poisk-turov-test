#!/usr/bin/env python3
"""Test actual ANEX strong25 operation boundaries without private access."""
import ast
from pathlib import Path
import unittest
import yaml
PATH=Path(__file__).resolve().parents[1]/'.github/workflows/andromeda-identity-accept.yml'
WORKFLOW=yaml.load(PATH.read_text(),Loader=yaml.BaseLoader)
STEPS=WORKFLOW['jobs']['accept']['steps']
PREPARE=next(s['run'] for s in STEPS if s.get('name','').startswith('Verify checked source'))
CODE=PREPARE.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0]
NODE=next(n for n in ast.parse(CODE).body if isinstance(n,ast.FunctionDef) and n.name=='require_new_operation')
NAMESPACE={}
exec(compile(ast.Module(body=[NODE],type_ignores=[]),'<actual-workflow-guard>','exec'),NAMESPACE)
GUARD=NAMESPACE['require_new_operation']
TITLE='ANEX 1759 saved strong25 acceptance v1'

class OperationTests(unittest.TestCase):
    def test_only_existing_ops_branch_owner_first_push(self):
        job=WORKFLOW['jobs']['accept'];self.assertEqual(job['needs'],'check-operation')
        for c in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):
            self.assertIn(c,job['if'])
        self.assertEqual(set(WORKFLOW['on']),{'push','pull_request'})
        self.assertEqual(WORKFLOW['on']['push']['paths'],['.github/workflows/andromeda-identity-accept.yml'])
        self.assertEqual(WORKFLOW['concurrency']['cancel-in-progress'],'false')

    def test_checked_source_all_three_gates_and_exact_report(self):
        self.assertEqual(STEPS[0]['with']['ref'],'de5b7a08be4c6314622719becf67189ec0e60b81')
        self.assertEqual(STEPS[0]['with']['persist-credentials'],'false')
        self.assertEqual(WORKFLOW['run-name'],TITLE)
        self.assertEqual(WORKFLOW['env']['OPERATION_ID'],'anex-1759-saved-strong25-20260910-v1')
        for identifier in (34517850536,34517850459,34517850464,10097668551,10113498830,10118184869,10165733820):self.assertIn(str(identifier),PREPARE)
        self.assertIn("value.get('head_sha')!=os.environ['SOURCE_SHA']",CODE)
        self.assertIn("value.get('conclusion')!='success'",CODE)
        self.assertIn('5a04ff6b3ed11f0477ab8c06f4779d901f335af06cca652e8fd87864f29737f7',CODE)
        self.assertIn('ca69d167a542023d008a9864c925cf9990d86b0f3f2c36bd7c7ed7379428773f',CODE)

    def test_reservation_before_single_private_step(self):
        reserved=next(i for i,s in enumerate(STEPS) if s.get('id')=='reserved')
        private=[i for i,s in enumerate(STEPS) if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{})]
        self.assertEqual(len(private),1);self.assertLess(reserved,private[0])
        command=STEPS[private[0]]['run']
        for flag in ('anex_saved_strong_batch.py','--report','--apply','--receipt'):self.assertIn(flag,command)
        for old in ('andromeda_country_pending.py','andromeda_saved_bridge.py','anex_access_probe.py'):self.assertNotIn(old,command)
        self.assertIn('request.json',STEPS[reserved]['with']['path'])
        self.assertEqual(STEPS[-1]['if'],"always() && steps.reserved.outcome == 'success'")
        self.assertEqual(sum('secrets.' in str(s) for s in STEPS),1)
        self.assertNotIn('secrets.',str(WORKFLOW['jobs']['check-operation']))

    def test_first_new_operation_preserves_old_history(self):
        GUARD([{'workflow_runs':[{'id':10},{'id':1,'display_title':'Andromeda 1759 country pending403 acceptance v1'}]}],{'total_count':0,'artifacts':[]},10)

    def test_prior_operation_blocks_all_outcomes(self):
        for outcome in ('success','failure','cancelled',None):
            with self.subTest(outcome=outcome),self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10},{'id':9,'display_title':TITLE,'conclusion':outcome}]}],{'total_count':0,'artifacts':[]},10)

    def test_expired_reservation_still_blocks(self):
        for expired in (True,False):
            with self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10}]}],{'total_count':1,'artifacts':[{'expired':expired}]},10)

    def test_missing_history_or_current_run_fails_closed(self):
        for history in ([],{},[{}],[{'workflow_runs':[]}],[{'workflow_runs':[{'id':9}]}]):
            with self.assertRaises(ValueError):GUARD(history,{'total_count':0,'artifacts':[]},10)

    def test_missing_artifact_response_fails_closed(self):
        with self.assertRaises(ValueError):GUARD([{'workflow_runs':[{'id':10}]}],{},10)

    def test_embedded_python_compiles_and_finalized_is_required(self):
        for step in STEPS:
            text=step.get('run','')
            if "python3 - <<'PYTHON'\n" in text:
                compile(text.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0],'<workflow-python>','exec')
        self.assertTrue(any("value.get('state')!='finalized'" in s.get('run','') for s in STEPS))

if __name__=='__main__':unittest.main(verbosity=2)
