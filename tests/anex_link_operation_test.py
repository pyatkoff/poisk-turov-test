#!/usr/bin/env python3
"""Boundaries for the actual complete187 read-only operation."""
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
NS={};exec(compile(ast.Module(body=[NODE],type_ignores=[]),'<workflow-guard>','exec'),NS)
GUARD=NS['require_new_operation'];TITLE='ANEX 1759 complete187 read v1'
class OperationTests(unittest.TestCase):
    def test_owner_first_push_existing_ops_branch_only(self):
        job=WORKFLOW['jobs']['accept']
        for c in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):self.assertIn(c,job['if'])
        self.assertEqual(WORKFLOW['on']['push']['paths'],['.github/workflows/andromeda-identity-accept.yml'])
        self.assertEqual(WORKFLOW['concurrency']['cancel-in-progress'],'false')
    def test_exact_checked_source_seed_and_archives(self):
        self.assertEqual(STEPS[0]['with']['ref'],'02d1b5de487623c500954da3c761323b6f1cc0ab')
        self.assertEqual(WORKFLOW['run-name'],TITLE);self.assertEqual(WORKFLOW['env']['OPERATION_ID'],'anex-1759-complete187-review-20260910-v1')
        for x in (34524075314,34524075324,10097668551,10113498830,10118184869,10165733820):self.assertIn(str(x),PREPARE)
        self.assertIn('ba9c9e068fb9240cef81a87129e4a31ee91f9ecd79d259e5d54f2e7c016a7cd8',CODE)
    def test_reservation_before_only_private_read_and_no_apply(self):
        reserved=next(i for i,s in enumerate(STEPS) if s.get('id')=='reserved')
        private=[i for i,s in enumerate(STEPS) if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{})]
        self.assertEqual(len(private),1);self.assertLess(reserved,private[0])
        cmd=STEPS[private[0]]['run'];self.assertIn('anex_saved_complete_review.py',cmd);self.assertIn('--directory',cmd)
        for bad in ('--apply','anex_search_mapping_import','anex_saved_strong_batch.py','andromeda_country_pending.py'):self.assertNotIn(bad,cmd)
        self.assertEqual(sum('secrets.' in str(s) for s in STEPS),1)
    def test_zero_writes_and_suppliers_are_asserted(self):
        private=next(s for s in STEPS if 'ANYTOOUR_DEPLOY_SSH_KEY' in s.get('env',{}))
        self.assertIn("value.get('supplier_calls')!=0",private['run']);self.assertIn("value.get('database_writes')!=0",private['run'])
    def test_old_operations_do_not_block_but_same_name_does(self):
        GUARD([{'workflow_runs':[{'id':10},{'id':1,'display_title':'ANEX 1759 saved strong25 acceptance v1'}]}],{'total_count':0,'artifacts':[]},10)
        with self.assertRaisesRegex(ValueError,'do_not_replay'):GUARD([{'workflow_runs':[{'id':10},{'id':9,'display_title':TITLE,'conclusion':'success'}]}],{'total_count':0,'artifacts':[]},10)
    def test_expired_reservation_blocks(self):
        with self.assertRaisesRegex(ValueError,'do_not_replay'):GUARD([{'workflow_runs':[{'id':10}]}],{'total_count':1,'artifacts':[{'expired':True}]},10)
    def test_missing_history_fails_closed(self):
        for h in ([],{},[{}],[{'workflow_runs':[]}],[{'workflow_runs':[{'id':9}]}]):
            with self.assertRaises(ValueError):GUARD(h,{'total_count':0,'artifacts':[]},10)
    def test_embedded_python_compiles(self):
        for s in STEPS:
            text=s.get('run','')
            if "python3 - <<'PYTHON'\n" in text:compile(text.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0],'<workflow-python>','exec')
if __name__=='__main__':unittest.main(verbosity=2)
