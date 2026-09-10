#!/usr/bin/env python3
"""Offline checks for the explicit existing hotel-identity workflow; no network/DB."""
import ast
import copy
from pathlib import Path
import unittest
import yaml

PATH = Path(__file__).resolve().parents[1]/'.github/workflows/andromeda-identity-accept.yml'
WORKFLOW = yaml.load(PATH.read_text(), Loader=yaml.BaseLoader)
STEPS = WORKFLOW['jobs']['accept']['steps']
PREPARE = next(s['run'] for s in STEPS if s.get('name','').startswith('Verify checked source'))
CODE = PREPARE.split("python3 - <<'PYTHON'\n", 1)[1].rsplit('\nPYTHON', 1)[0]
TREE = ast.parse(CODE)
NODE = next(n for n in TREE.body if isinstance(n, ast.FunctionDef) and n.name == 'require_new_operation')
NAMESPACE = {}
exec(compile(ast.Module(body=[NODE], type_ignores=[]), '<actual-workflow-guard>', 'exec'), NAMESPACE)
GUARD = NAMESPACE['require_new_operation']

class OperationTests(unittest.TestCase):
    def test_only_explicit_existing_ops_branch_can_write(self):
        job = WORKFLOW['jobs']['accept']
        self.assertEqual(job['needs'], 'check-operation')
        for condition in ("github.event_name == 'push'", "github.actor == 'pyatkoff'",
                          "github.run_attempt == 1", "refs/heads/feat/int-andromeda-live-20260909"):
            self.assertIn(condition, job['if'])
        self.assertEqual(set(WORKFLOW['on']), {'push','pull_request'})
        self.assertEqual(WORKFLOW['on']['push']['paths'], ['.github/workflows/andromeda-identity-accept.yml'])

    def test_only_reviewed_source_executed(self):
        self.assertEqual(STEPS[0]['with']['ref'], '3d099dbd64c9b00234ac9bc77bd09ee9c3b5355c')
        self.assertEqual(STEPS[0]['with']['persist-credentials'], 'false')
        self.assertIn('34495764385', PREPARE)
        self.assertIn('34495764441', PREPARE)
        self.assertIn("check.get('head_sha') != os.environ['SOURCE_SHA']", CODE)
        self.assertIn('links.approved_delta', CODE)

    def test_reservation_before_ssh_and_failure_outcome_retained(self):
        reservation = next(i for i,s in enumerate(STEPS) if s.get('id') == 'reserved')
        apply = next(i for i,s in enumerate(STEPS) if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env', {}))
        self.assertLess(reservation, apply)
        self.assertEqual(STEPS[-1]['if'], "always() && steps.reserved.outcome == 'success'")
        self.assertIn('--receipt', STEPS[apply]['run'])
        self.assertIn('--apply', STEPS[apply]['run'])
        self.assertNotIn('andromeda_identity_accept.py', STEPS[apply]['run'])
        self.assertEqual(sum('secrets.' in str(s) for s in STEPS), 1)
        self.assertNotIn('secrets.', str(WORKFLOW['jobs']['check-operation']))

    def test_first_named_operation_allowed(self):
        GUARD([{'workflow_runs':[{'id':10}, {'id':1,'display_title':'Historical Kaftans'}]}],
              {'total_count':0,'artifacts':[]},10)

    def test_prior_operation_blocks_any_outcome(self):
        for outcome in ('success','failure','cancelled',None):
            with self.subTest(outcome=outcome), self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10},{'id':9,'display_title':'ANEX 1759 four-pair acceptance v1',
                                                  'conclusion':outcome}]}], {'total_count':0,'artifacts':[]},10)

    def test_prior_reservation_blocks_even_when_expired(self):
        for expired in (False,True):
            with self.subTest(expired=expired), self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10}]}], {'total_count':1,'artifacts':[{'expired':expired}]},10)

    def test_missing_history_or_current_run_fails_closed(self):
        for history in ([],{},[{}],[{'workflow_runs':[]}],[{'workflow_runs':[{'id':9}]}]):
            with self.subTest(history=history), self.assertRaises(ValueError):
                GUARD(history,{'total_count':0,'artifacts':[]},10)

    def test_missing_artifact_response_fails_closed(self):
        with self.assertRaises(ValueError): GUARD([{'workflow_runs':[{'id':10}]}],{},10)

    def test_all_embedded_python_compiles(self):
        for step in STEPS:
            run = step.get('run','')
            if "python3 - <<'PYTHON'\n" in run:
                code = run.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0]
                compile(code,'<workflow-python>','exec')

if __name__ == '__main__': unittest.main(verbosity=2)
