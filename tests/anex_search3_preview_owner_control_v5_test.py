import importlib.util
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location('v5gate',ROOT/'scripts/diagnostics/anex_search3_preview_owner_gate_v5.py')
gate=importlib.util.module_from_spec(SPEC); SPEC.loader.exec_module(gate)
WORKFLOW=ROOT/'.github/workflows/publish-anex-search3-preview-v5.yml'
SOURCE='8ccf1174c1277e6128258de33d403b52faddf7e2'
FIX='df839684e66ed0a00734d1d6c555e4d6941b4c3a'
CHECK_IDS={'105341515847','105341515773','105341515707','105341515008','105341514098','105341513675'}

def event(body=gate.COMMAND,issue=2530,login='pyatkoff',user_id=226193297,action='created',pr=False):
    i={'number':issue}
    if pr:i['pull_request']={}
    return {'action':action,'issue':i,'comment':{'body':body,'user':{'login':login,'id':user_id}}}

def env(**kw):
    v={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_REPOSITORY':gate.REPOSITORY,'GITHUB_REF':gate.MAIN_REF,'GITHUB_RUN_ATTEMPT':'1','GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff'};v.update(kw);return v

class V5Control(unittest.TestCase):
    def test_gate_exact_only(self):
        self.assertEqual(gate.authorize(event(),env()),(True,'authorized'))
        bad=[(event(body=gate.COMMAND+' '),env()),(event(issue=996),env()),(event(login='x'),env()),(event(user_id=1),env()),(event(action='edited'),env()),(event(pr=True),env()),(event(),env(GITHUB_RUN_ATTEMPT='2')),(event(),env(GITHUB_REF='refs/heads/x'))]
        for e,n in bad:self.assertFalse(gate.authorize(e,n)[0])

    def test_new_no_replay_identity_and_exact_fix_checks(self):
        t=WORKFLOW.read_text()
        self.assertIn(gate.COMMAND,t);self.assertIn(SOURCE,t);self.assertIn(FIX,t)
        for i in CHECK_IDS:self.assertIn(i,t)
        self.assertNotIn('/publish-anex-search3-preview-2530-v4',t)
        self.assertNotIn('35262444268',t)
        self.assertIn('publish-anex-search3-preview-2530-v5-page2-checkpoint',t)
        self.assertIn('andromeda-page2-canary-2530-v5-country4-2026-10-20-2a-7n',t)
        self.assertIn('duplicate_or_stale_owner_command',t)
        self.assertNotIn('workflow_dispatch:',t)

    def test_live_canary_is_two_pages_and_no_money_or_booking_calls(self):
        t=WORKFLOW.read_text(); live=t.split('<?php',1)[1].split('PHP\n',1)[0]
        self.assertEqual(live.count('anytour_andromeda_search3_run('),2)
        self.assertIn("$request['page']=2",live);self.assertNotIn("$request['page']=3",live)
        self.assertIn('START TRANSACTION READ ONLY',live)
        self.assertIn('auth_checkpoint_created_at_mismatch',live);self.assertIn('page2_checkpoint_missing',live)
        for forbidden in ('getFlights(','->calc(','quote_resolve','bron_ticket'):self.assertNotIn(forbidden,live)

    def test_artifact_can_never_include_ssh_key(self):
        t=WORKFLOW.read_text()
        self.assertIn('$RUNNER_TEMP/v5-canary-ssh/key',t)
        self.assertIn('$RUNNER_TEMP/v5-canary-artifact/result.json',t)
        upload=t.split('Upload sanitized canary result only',1)[1]
        self.assertIn('v5-canary-artifact/result.json',upload)
        self.assertNotIn('v5-canary-ssh',upload)
        self.assertIn("! grep -R -E 'PRIVATE KEY|known_hosts|ssh-'",t)

if __name__=='__main__':unittest.main()
