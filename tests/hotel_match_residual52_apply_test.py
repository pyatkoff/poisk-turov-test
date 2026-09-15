#!/usr/bin/env python3
"""Verify immutable plan gate and actual generated PHP guard fixtures."""
import importlib.util,json,pathlib,subprocess,sys,tempfile,unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts/diagnostics'))
import hotel_match_residual52_apply_bundle as module

class ImmutableApplyTest(unittest.TestCase):
 def test_generated_guards(self):
  with tempfile.TemporaryDirectory() as td:
   p=pathlib.Path(td)/'apply.php';p.write_text(module.build())
   subprocess.run(['php','-l',str(p)],check=True)
   subprocess.run(['php','-d','allow_url_fopen=0',str(p),'--self-test'],check=True)
 def test_plan_tampering_rejected_before_database(self):
  original=module.ROOT
  raw=(original/module.PLAN).read_text()
  try:
   with tempfile.TemporaryDirectory() as td:
    module.ROOT=pathlib.Path(td);p=module.ROOT/module.PLAN;p.parent.mkdir(parents=True)
    for case in ['target','duplicate','evidence','count']:
     d=json.loads(raw)
     if case=='target':d['rows'][0]['local_id']+=1
     if case=='duplicate':d['rows'][0]=d['rows'][1]
     if case=='evidence':d['rows'][0]['evidence_sha256']='0'*64
     if case=='count':d['rows'].pop()
     p.write_text(json.dumps(d))
     with self.assertRaisesRegex(AssertionError,'immutable_plan_changed'):module.build()
  finally:module.ROOT=original

if __name__=='__main__':unittest.main()
