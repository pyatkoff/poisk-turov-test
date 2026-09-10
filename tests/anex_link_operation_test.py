#!/usr/bin/env python3
"""Actual six-country operation guards: no private access during this check."""
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
TITLE='Andromeda 1759 six-country expansion v1'

class OperationTests(unittest.TestCase):
    def test_only_explicit_existing_ops_branch_can_write(self):
        job=WORKFLOW['jobs']['accept'];self.assertEqual(job['needs'],'check-operation')
        for c in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):
            self.assertIn(c,job['if'])
        self.assertEqual(set(WORKFLOW['on']),{'push','pull_request'})
        self.assertEqual(WORKFLOW['on']['push']['paths'],['.github/workflows/andromeda-identity-accept.yml'])

    def test_only_reviewed_source_and_six_countries(self):
        self.assertEqual(STEPS[0]['with']['ref'],'6a62df9ba2e82651c9a136c044d576f6e59f7205')
        self.assertEqual(STEPS[0]['with']['persist-credentials'],'false')
        self.assertEqual(WORKFLOW['run-name'],TITLE)
        self.assertIn('34506089235',PREPARE);self.assertIn('34506089363',PREPARE)
        self.assertIn("check.get('head_sha') != os.environ['SOURCE_SHA']",CODE)
        self.assertIn("('uae','thailand','vietnam','sri-lanka','maldives','cuba')",CODE)
        self.assertIn("'max_supplier_calls': 18",CODE)

    def test_two_reservations_precede_capture_and_database_write(self):
        first=next(i for i,s in enumerate(STEPS) if s.get('id')=='reserved')
        captured=next(i for i,s in enumerate(STEPS) if s.get('id')=='captured')
        private=[i for i,s in enumerate(STEPS) if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{})]
        self.assertEqual(len(private),2)
        self.assertLess(first,private[0]);self.assertLess(private[0],captured);self.assertLess(captured,private[1])
        self.assertEqual(STEPS[private[1]]['if'],"steps.captured.outcome == 'success'")
        for i,phase in zip(private,('capture','apply')):
            self.assertIn('andromeda_country_expansion.py '+phase,STEPS[i]['run'])
            self.assertIn('--execute',STEPS[i]['run']);self.assertIn('--directory',STEPS[i]['run'])
            self.assertNotIn('andromeda_identity_batch_accept',STEPS[i]['run'])
            self.assertNotIn('anex_tourvisor_link_import',STEPS[i]['run'])
        self.assertEqual(STEPS[-1]['if'],"always() && steps.reserved.outcome == 'success'")
        self.assertEqual(sum('secrets.' in str(s) for s in STEPS),2)
        self.assertNotIn('secrets.',str(WORKFLOW['jobs']['check-operation']))

    def test_first_new_operation_allows_historical_completed_imports(self):
        GUARD([{'workflow_runs':[{'id':10},{'id':1,'display_title':'Andromeda 1759 92-identity acceptance v1'}]}],{'total_count':0,'artifacts':[]},10)

    def test_prior_operation_blocks_every_outcome(self):
        for outcome in ('success','failure','cancelled',None):
            with self.subTest(outcome=outcome),self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10},{'id':9,'display_title':TITLE,'conclusion':outcome}]}],{'total_count':0,'artifacts':[]},10)

    def test_prior_expired_reservation_still_blocks(self):
        for expired in (True,False):
            with self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10}]}],{'total_count':1,'artifacts':[{'expired':expired}]},10)

    def test_missing_history_or_current_run_fails_closed(self):
        for history in ([],{},[{}],[{'workflow_runs':[]}],[{'workflow_runs':[{'id':9}]}]):
            with self.assertRaises(ValueError):GUARD(history,{'total_count':0,'artifacts':[]},10)

    def test_missing_artifact_response_fails_closed(self):
        with self.assertRaises(ValueError):GUARD([{'workflow_runs':[{'id':10}]}],{},10)

    def test_embedded_python_compiles_and_partial_is_not_success(self):
        for step in STEPS:
            text=step.get('run','')
            if "python3 - <<'PYTHON'\n" in text:
                compile(text.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0],'<workflow-python>','exec')
        self.assertTrue(any("result.get('status') != 'completed'" in s.get('run','') for s in STEPS))

if __name__=='__main__':unittest.main(verbosity=2)
