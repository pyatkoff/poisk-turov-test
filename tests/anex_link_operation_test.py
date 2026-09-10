#!/usr/bin/env python3
"""Actual403 operation guards, no private access in this check."""
import ast
from pathlib import Path
import unittest
import yaml
W=yaml.load((Path(__file__).resolve().parents[1]/'.github/workflows/andromeda-identity-accept.yml').read_text(),Loader=yaml.BaseLoader)
STEPS=W['jobs']['accept']['steps']
PREPARE=next(s['run'] for s in STEPS if s.get('name','').startswith('Verify checked source'))
CODE=PREPARE.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0]
NODE=next(n for n in ast.parse(CODE).body if isinstance(n,ast.FunctionDef) and n.name=='require_new_operation')
N={};exec(compile(ast.Module(body=[NODE],type_ignores=[]),'<actual-workflow>','exec'),N);GUARD=N['require_new_operation']
TITLE='Andromeda 1759 country pending403 acceptance v1'
class OperationTests(unittest.TestCase):
 def test_only_explicit_ops_push_can_write(self):
  job=W['jobs']['accept'];self.assertEqual(job['needs'],'check-operation')
  for c in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):self.assertIn(c,job['if'])
  self.assertEqual(set(W['on']),{'push','pull_request'});self.assertEqual(W['on']['push']['paths'],['.github/workflows/andromeda-identity-accept.yml'])
 def test_exact_checked_source_and_input(self):
  self.assertEqual(STEPS[0]['with']['ref'],'3fdd40cf1170272dde35f8802a2fe6551a3df77c');self.assertEqual(STEPS[0]['with']['persist-credentials'],'false')
  self.assertEqual(W['run-name'],TITLE)
  for v in ('34513027308','34513027295','10165733820','9e89e6585b01205ac9a0b1bc47f91c99585eb8cc8863fc89e3b463c3b3d9d687',"payload['count']!=403","value.get('head_sha')!=os.environ['SOURCE_SHA']"):
   self.assertIn(v,PREPARE)
 def test_reserve_before_one_ssh_step_and_retain_outcome(self):
  reserved=next(i for i,s in enumerate(STEPS) if s.get('id')=='reserved');private=[i for i,s in enumerate(STEPS) if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{})]
  self.assertEqual(len(private),1);self.assertLess(reserved,private[0]);self.assertIn('--receipt',STEPS[private[0]]['run']);self.assertIn('andromeda_country_pending.py',STEPS[private[0]]['run'])
  self.assertNotIn('andromeda_country_expansion.py',STEPS[private[0]]['run']);self.assertNotIn('andromeda_saved_bridge.py',STEPS[private[0]]['run'])
  self.assertEqual(STEPS[-1]['if'],"always() && steps.reserved.outcome == 'success'");self.assertNotIn('secrets.',str(W['jobs']['check-operation']))
 def test_first_new_operation_allows_old_completed_names(self):
  GUARD([{'workflow_runs':[{'id':10},{'id':1,'display_title':'Andromeda 1759 Maldives bridge61 acceptance v1'}]}],{'total_count':0,'artifacts':[]},10)
 def test_prior_named_operation_blocks_all_outcomes(self):
  for outcome in ('success','failure','cancelled',None):
   with self.subTest(outcome=outcome),self.assertRaisesRegex(ValueError,'do_not_replay'):
    GUARD([{'workflow_runs':[{'id':10},{'id':9,'display_title':TITLE,'conclusion':outcome}]}],{'total_count':0,'artifacts':[]},10)
 def test_prior_reservation_even_expired_blocks(self):
  for expired in (True,False):
   with self.assertRaisesRegex(ValueError,'do_not_replay'):GUARD([{'workflow_runs':[{'id':10}]}],{'total_count':1,'artifacts':[{'expired':expired}]},10)
 def test_missing_history_or_current_run_fails_closed(self):
  for history in ([],{},[{}],[{'workflow_runs':[]}],[{'workflow_runs':[{'id':9}]}]):
   with self.assertRaises(ValueError):GUARD(history,{'total_count':0,'artifacts':[]},10)
 def test_missing_reservation_response_fails_closed(self):
  with self.assertRaises(ValueError):GUARD([{'workflow_runs':[{'id':10}]}],{},10)
 def test_all_actual_embedded_python_compiles(self):
  for step in STEPS:
   text=step.get('run','')
   if "python3 - <<'PYTHON'\n" in text:compile(text.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0],'<workflow-python>','exec')
if __name__=='__main__':unittest.main(verbosity=2)
