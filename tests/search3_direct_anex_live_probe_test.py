import importlib.util, unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location('gate',ROOT/'scripts/diagnostics/search3_direct_anex_live_probe_gate.py');g=importlib.util.module_from_spec(SPEC);SPEC.loader.exec_module(g)
class T(unittest.TestCase):
 def test_exact(self):
  e={'action':'created','issue':{'number':3419},'comment':{'id':1,'body':g.COMMAND,'author_association':'OWNER','user':{'login':'pyatkoff','id':226193297}}}
  env={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ATTEMPT':'1','GITHUB_REPOSITORY':g.REPOSITORY,'GITHUB_REF':'refs/heads/main','GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff'}
  self.assertEqual(g.authorize(e,env),(True,'authorized'))
if __name__=='__main__':unittest.main()
