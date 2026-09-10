#!/usr/bin/env python3
"""Current exact bridge operation, with no private access during this check."""
import ast
from pathlib import Path
import unittest
import yaml
W=yaml.load((Path(__file__).resolve().parents[1]/'.github/workflows/andromeda-identity-accept.yml').read_text(),Loader=yaml.BaseLoader)
J=W['jobs']['accept'];S=J['steps'];P=next(s['run'] for s in S if s.get('name','').startswith('Verify checked source'))
C=P.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0]
N=next(n for n in ast.parse(C).body if isinstance(n,ast.FunctionDef) and n.name=='require_new_operation');NS={}
exec(compile(ast.Module(body=[N],type_ignores=[]),'actual-guard','exec'),NS);GUARD=NS['require_new_operation']
TITLE='Andromeda 1759 Maldives bridge61 acceptance v1'
class OperationTests(unittest.TestCase):
    def test_owner_first_push_exact_ref(self):
        self.assertEqual(J['needs'],'check-operation')
        for text in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):
            self.assertIn(text,J['if'])
        self.assertEqual(set(W['on']),{'push','pull_request'})
        self.assertEqual(W['on']['push']['paths'],['.github/workflows/andromeda-identity-accept.yml'])
    def test_checked_source_and_request_only(self):
        self.assertEqual(S[0]['with']['ref'],'543d0f0bb5be20fa0810bdf775e7e8c219980b69')
        self.assertEqual(S[0]['with']['persist-credentials'],'false')
        for value in ('34509355903','34509355947','bridge.request','941ad3dccbdd4d4c374bcf572016cc1234a535bfe56a5bcacbf1df99e8eac2b4'):
            self.assertIn(value,P)
    def test_reservation_before_single_private_step(self):
        reserved=next(i for i,s in enumerate(S) if s.get('id')=='reserved')
        private=[i for i,s in enumerate(S) if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{})]
        self.assertEqual(len(private),1);self.assertLess(reserved,private[0])
        command=S[private[0]]['run'];self.assertIn('andromeda_saved_bridge.py',command)
        self.assertIn('--receipt',command);self.assertIn('--apply',command)
        for old in ('andromeda_country_expansion.py','andromeda_identity_batch_accept.py','anex_tourvisor_link_import.py'):
            self.assertNotIn(old,command)
        self.assertEqual(S[-1]['if'],"always() && steps.reserved.outcome == 'success'")
        self.assertNotIn('secrets.',str(W['jobs']['check-operation']))
    def test_new_operation_accepts_only_historical_other_names(self):
        GUARD([{'workflow_runs':[{'id':10},{'id':1,'display_title':'Andromeda 1759 six-country expansion v1'}]}],{'total_count':0,'artifacts':[]},10)
    def test_all_prior_outcomes_rejected(self):
        for status in ('success','failure','cancelled',None):
            with self.assertRaisesRegex(ValueError,'do_not_replay'):
                GUARD([{'workflow_runs':[{'id':10},{'id':9,'display_title':TITLE,'conclusion':status}]}],{'total_count':0,'artifacts':[]},10)
    def test_prior_reservation_even_expired_rejected(self):
        for expired in (True,False):
            with self.assertRaises(ValueError):GUARD([{'workflow_runs':[{'id':10}]}],{'total_count':1,'artifacts':[{'expired':expired}]},10)
    def test_missing_history_fails_closed(self):
        for pages in ([],{},[{}],[{'workflow_runs':[]}],[{'workflow_runs':[{'id':9}]}]):
            with self.assertRaises(ValueError):GUARD(pages,{'total_count':0,'artifacts':[]},10)
    def test_missing_artifacts_fail_closed(self):
        with self.assertRaises(ValueError):GUARD([{'workflow_runs':[{'id':10}]}],{},10)
    def test_embedded_python_compiles(self):compile(C,'workflow','exec')
if __name__=='__main__':unittest.main(verbosity=2)
