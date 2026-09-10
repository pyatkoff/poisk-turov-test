#!/usr/bin/env python3
"""Checks for a saved-file-only continuation, not a repeated country import."""
import ast
from pathlib import Path
import subprocess
import unittest
import yaml

W=yaml.load((Path(__file__).resolve().parents[1]/'.github/workflows/andromeda-identity-accept.yml').read_text(),Loader=yaml.BaseLoader)
J=W['jobs']['inspect'];S=J['steps'];RUN=next(s['run'] for s in S if s.get('name','').startswith('Read only saved'))
CODE=RUN.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0]
TREE=ast.parse(CODE)
READER=next(n.value.value for n in TREE.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='reader' for t in n.targets))

class SavedReadTests(unittest.TestCase):
    def test_only_owner_first_push_on_existing_ops_branch(self):
        self.assertEqual(J['needs'],'check-operation')
        for text in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):
            self.assertIn(text,J['if'])
        self.assertEqual(set(W['on']),{'push','pull_request'})
        self.assertEqual(W['on']['push']['paths'],['.github/workflows/andromeda-identity-accept.yml'])
    def test_exact_completed_source_not_historical_runtime(self):
        self.assertEqual(S[0]['with']['ref'],'6a62df9ba2e82651c9a136c044d576f6e59f7205')
        self.assertEqual(S[0]['with']['persist-credentials'],'false')
        self.assertIn('34506730438',S[1]['run'])
        self.assertIn("define('CE_LIBRARY_ONLY',true)",CODE)
    def test_reader_has_no_write_db_or_supplier_calls(self):
        for forbidden in ('ce_run(', 'ce_import(', 'ce_save(', 'new PDO', 'v2_data_db', 'file_put_contents', 'fopen(', 'mkdir(', 'unlink(', 'rename(', 'new AnyTour', '->catalog(', '->login('):
            self.assertNotIn(forbidden,READER)
        self.assertIn("$r['decision_status']!=='pending'",READER)
        self.assertIn('isset(CE_COUNTRIES[$country])',READER)
    def test_capture_plan_and_completed_receipt_are_bound(self):
        for text in ("ce_hash($data)!==$cap['capture_sha256']","ce_hash($plan)!==$cap['plan_sha256']","$receipt['readback_verified']","$plan['catalog_sha256']!==ce_hash($data['catalog'])"):
            self.assertIn(text,READER)
    def test_no_credentials_in_artifact_and_one_private_step(self):
        self.assertEqual(sum('secrets.' in str(s) for s in S),1)
        self.assertNotIn('secrets.',str(W['jobs']['check-operation']))
        self.assertIn('strpos($out,$secret)',READER)
        self.assertNotIn("'config'=>",READER)
    def test_reader_and_embedded_python_syntax(self):
        compile(CODE,'workflow','exec')
        result=subprocess.run(['php','-l'],input='<?php\n'+READER,text=True,capture_output=True)
        self.assertEqual(result.returncode,0,result.stderr)

if __name__=='__main__':unittest.main(verbosity=2)
