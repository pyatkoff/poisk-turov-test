import importlib.util
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location("gate",ROOT/"scripts/diagnostics/search3_next_live_source_counts_gate.py")
gate=importlib.util.module_from_spec(SPEC);SPEC.loader.exec_module(gate)
def event(body=gate.COMMAND,issue=3419,login="pyatkoff",uid=226193297):
 return {"issue":{"number":issue},"comment":{"body":body,"user":{"login":login,"id":uid}}}
def env(**kw):
 d={"GITHUB_EVENT_NAME":"issue_comment","GITHUB_RUN_ATTEMPT":"1","GITHUB_ACTOR":"pyatkoff","GITHUB_TRIGGERING_ACTOR":"pyatkoff"};d.update(kw);return d
class T(unittest.TestCase):
 def test_exact(self):
  self.assertEqual(gate.authorize(event(),env()),(True,"authorized"))
  for e,n in [(event(issue=2530),env()),(event(body=gate.COMMAND+" "),env()),(event(login="x"),env()),(event(uid=1),env()),(event(),env(GITHUB_RUN_ATTEMPT="2"))]:self.assertFalse(gate.authorize(e,n)[0])
if __name__=="__main__":unittest.main()
