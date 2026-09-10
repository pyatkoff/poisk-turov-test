#!/usr/bin/env python3
"""Check actual saved-file-only reader, not a new import operation."""
import ast,re,subprocess,unittest
from pathlib import Path
import yaml
W=yaml.load((Path(__file__).resolve().parents[1]/'.github/workflows/andromeda-identity-accept.yml').read_text(),Loader=yaml.BaseLoader)
S=W['jobs']['read']['steps'];RUN=S[1]['run'];CODE=RUN.split("python3 - <<'PYTHON'\n",1)[1].rsplit('\nPYTHON',1)[0]
TREE=ast.parse(CODE);PHP=next(n.value.value for n in TREE.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='PHP' for t in n.targets))
class ReaderTests(unittest.TestCase):
 def test_exact_ops_scope(self):
  for c in ("github.event_name == 'push'","github.actor == 'pyatkoff'","github.run_attempt == 1","refs/heads/feat/int-andromeda-live-20260909"):self.assertIn(c,W['jobs']['read']['if'])
  self.assertEqual(W['jobs']['read']['needs'],'check-operation');self.assertEqual(set(W['jobs']),{'check-operation','read'})
 def test_pinned_source(self):
  self.assertEqual(S[0]['with']['ref'],'d583c38fdbb08c711121b93108a40befeb608f7c');self.assertIn("CE_LIBRARY_ONLY',true",CODE)
 def test_reader_cannot_write_or_call_suppliers(self):
  for term in ('ce_run(', 'ce_import(', 'ce_save(', 'v2_data_db(', 'new PDO', 'AnyTourAndromedaClient', 'file_put_contents(', 'fopen(', 'unlink(', 'rename(', 'curl_', 'exec('):self.assertNotIn(term,PHP)
 def test_complete_hashes_and_secret_filter(self):
  for term in ('capture_sha256','plan_sha256','readback_verified','4000000','rawurlencode','CE_COUNTRIES'):self.assertIn(term,PHP)
  self.assertIn("'catalog','local','plan'",PHP)
 def test_actual_syntax(self):
  compile(CODE,'<workflow>','exec');p=subprocess.run(['php','-l'],input='<?php\n'+PHP,text=True,capture_output=True);self.assertEqual(p.returncode,0,p.stdout+p.stderr)
 def test_private_access_only_one_step(self):
  self.assertEqual(sum('secrets.' in str(s) for s in S),1);self.assertNotIn('secrets.',str(W['jobs']['check-operation']));self.assertEqual(S[-1]['if'],'always()')
if __name__=='__main__':unittest.main(verbosity=2)
