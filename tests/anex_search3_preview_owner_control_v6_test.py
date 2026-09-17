import importlib.util
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location('v6gate',ROOT/'scripts/diagnostics/anex_search3_preview_owner_gate_v6.py')
gate=importlib.util.module_from_spec(SPEC); SPEC.loader.exec_module(gate)
WORKFLOW=ROOT/'.github/workflows/publish-anex-search3-preview-v6.yml'
SOURCE='e4a6dfa2cefed04747fbe3e91127b165e88a62c9'
FIX='ca051254ca6c79c4692d9a36bbfe22d19cfd17a7'
TREE='f5fad654455605f70a331dad5c99cda47a83889c'
BLOB='d840b13db9dcc496cf18183c216730fc7b9a86bf'
RUN_IDS={'35273070654','35273070649','35273070655','35273070679','35273070678','35273070636'}

def event(body=gate.COMMAND,issue=2530,login='pyatkoff',user_id=226193297,action='created',pr=False):
    i={'number':issue}
    if pr:i['pull_request']={}
    return {'action':action,'issue':i,'comment':{'body':body,'user':{'login':login,'id':user_id}}}

def env(**kw):
    v={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_REPOSITORY':gate.REPOSITORY,'GITHUB_REF':gate.MAIN_REF,'GITHUB_RUN_ATTEMPT':'1','GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff'};v.update(kw);return v

class V6Control(unittest.TestCase):
    def test_gate_exact_only(self):
        self.assertEqual(gate.authorize(event(),env()),(True,'authorized'))
        bad=[(event(body=gate.COMMAND+' '),env()),(event(issue=996),env()),(event(login='x'),env()),(event(user_id=1),env()),(event(action='edited'),env()),(event(pr=True),env()),(event(),env(GITHUB_RUN_ATTEMPT='2')),(event(),env(GITHUB_REF='refs/heads/x'))]
        for e,n in bad:self.assertFalse(gate.authorize(e,n)[0])

    def test_new_no_replay_identity_and_exact_source_provenance(self):
        t=WORKFLOW.read_text()
        for value in (gate.COMMAND,SOURCE,FIX,TREE,BLOB):self.assertIn(value,t)
        for run in RUN_IDS:self.assertIn(run,t)
        self.assertNotIn('/publish-anex-search3-preview-2530-v5',t)
        self.assertIn('publish-anex-search3-preview-2530-v6-full-pagination',t)
        self.assertIn('andromeda-full-pagination-canary-2530-v6-country4-2026-10-20-2a-7n',t)
        self.assertIn('duplicate_or_stale_owner_command',t)
        self.assertNotIn('workflow_dispatch:',t)

    def test_live_canary_uses_full_orchestrator_with_read_only_budget(self):
        t=WORKFLOW.read_text(); live=t.split('<?php',1)[1].split('PHP\n',1)[0]
        self.assertIn('anytour_andromeda_search3_run_pages(',live)
        self.assertIn('$page1=anytour_andromeda_search3_run(',live)
        self.assertIn('if($page===1)return $page1',live)
        self.assertIn('$maxPages=40',live)
        self.assertIn('START TRANSACTION READ ONLY',live)
        self.assertIn('full_page_cohort_drained',live)
        self.assertIn('final_page_checkpoint_present',live)
        self.assertNotIn('anytour_andromeda_search3_record_response(',live)
        for forbidden in ('getFlights(','->calc(','quote_resolve','bron_ticket'):self.assertNotIn(forbidden,live)

    def test_artifact_can_never_include_ssh_key(self):
        t=WORKFLOW.read_text()
        self.assertIn('$RUNNER_TEMP/v6-canary-ssh/key',t)
        self.assertIn('$RUNNER_TEMP/v6-canary-artifact/result.json',t)
        upload=t.split('Upload sanitized full-pagination canary result only',1)[1]
        self.assertIn('v6-canary-artifact/result.json',upload)
        self.assertNotIn('v6-canary-ssh',upload)
        self.assertIn("! grep -R -E 'PRIVATE KEY|known_hosts|ssh-'",t)

if __name__=='__main__':unittest.main()