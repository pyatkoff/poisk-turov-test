import copy
import importlib.util
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location('gate',ROOT/'scripts/diagnostics/search3_next_live_source_counts_gate.py')
gate=importlib.util.module_from_spec(SPEC);SPEC.loader.exec_module(gate)
def event():
    return {'action':'created','issue':{'number':3419},'comment':{'id':1,'body':gate.COMMAND,'author_association':'OWNER','user':{'login':'pyatkoff','id':226193297}}}
def env():
    return {'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ATTEMPT':'1','GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_REPOSITORY':gate.REPOSITORY,'GITHUB_REF':'refs/heads/main'}
class Gates(unittest.TestCase):
    def test_exact(self):self.assertEqual(gate.authorize(event(),env()),(True,'authorized'))
    def test_reject_other_event_actor_ref_and_rerun(self):
        for key,value in [('GITHUB_EVENT_NAME','pull_request'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_ACTOR','x'),('GITHUB_TRIGGERING_ACTOR','x'),('GITHUB_REPOSITORY','other/repo'),('GITHUB_REF','refs/heads/release/search3-production-ready-v1')]:
            e=env();e[key]=value;self.assertFalse(gate.authorize(event(),e)[0])
    def test_reject_old_commands_issue_and_edits(self):
        for command in ['/search3-next-live-source-counts-v7',gate.COMMAND+' ',gate.COMMAND+'\n']:
            e=event();e['comment']['body']=command;self.assertFalse(gate.authorize(e,env())[0])
        for issue in [2530,996]:
            e=event();e['issue']['number']=issue;self.assertFalse(gate.authorize(e,env())[0])
        e=event();e['action']='edited';self.assertFalse(gate.authorize(e,env())[0])
        e=event();e['issue']['pull_request']={};self.assertFalse(gate.authorize(e,env())[0])
    def test_reject_owner_mismatch(self):
        for field,value in [('id',1),('login','x')]:
            e=event();e['comment']['user'][field]=value;self.assertFalse(gate.authorize(e,env())[0])
        e=event();e['comment']['author_association']='CONTRIBUTOR';self.assertFalse(gate.authorize(e,env())[0])
if __name__=='__main__':unittest.main()
