#!/usr/bin/env python3
import importlib.util
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SCRIPT=Path(__file__).resolve().parents[1]/'scripts/deploy/int_server_executor.py'
spec=importlib.util.spec_from_file_location('int_server_executor',SCRIPT)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
SHA='cde183f7d33cef6bd1df4d0ff16dad0904d53570'

class ParseTest(unittest.TestCase):
    def test_install_runtime(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} install-runtime int-andromeda-runtime-install-20260922-v1')
        self.assertEqual('install-runtime',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_program_fuel_readback(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} program-fuel-readback int-andromeda-program-fuel-readback-20260923-v1')
        self.assertEqual('program-fuel-readback',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} program-fuel-readback int-anex-program-fuel-readback-20260923-v1')
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} program-fuel-readback int-andromeda-program-fuel-readback-20260923-v1 extra')
    def test_anex(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260921-v1 3')
        self.assertEqual(3,v['limit']);self.assertEqual('anex-demand',v['mode'])
    def test_andromeda(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v1 1 4 2026-10-10 2026-10-12 7 2 - 0 0')
        self.assertEqual('',v['meal']);self.assertEqual(0,v['max_captures'])
        bounded=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v2 1 4 2026-10-13 2026-10-14 7 2 - 0 10')
        self.assertEqual(10,bounded['max_captures'])
        scaled=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v4 1 4 2026-10-15 2026-10-16 7 2 - 0 30')
        self.assertEqual(30,scaled['max_captures'])
    def test_andromeda_operator_scope(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-operator-scope int-andromeda-intourist-scope-20260922-v1 1 4 2026-10-11 2026-10-11 7 2 - 0 43')
        self.assertEqual('andromeda-operator-scope',v['mode'])
        self.assertEqual(43,v['operator_id'])
        self.assertEqual(0,v['max_captures'])
        self.assertEqual('',v['meal'])

    def test_andromeda_operator_preflight(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-operator-preflight int-andromeda-intourist-preflight-20260923-v1 1 4 2026-10-18 2026-10-18 7 2 - 0 43')
        self.assertEqual('andromeda-operator-preflight',v['mode'])
        self.assertEqual(43,v['operator_id'])
        self.assertEqual('',v['meal'])
        self.assertNotIn('max_captures',v)

    def test_andromeda_external_group(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-external-group int-andromeda-external-group-turkey-20260922-v1 1 4 2026-10-20 2026-10-20 7 2 - 0')
        self.assertEqual('andromeda-external-group',v['mode'])
        self.assertEqual(1,v['max_captures'])
        self.assertEqual(0,v['region'])

    def test_match_live942_modes(self):
        tv=m.parse_command(f'/run-int-server-v1 {SHA} match-tv942 int-anex-match-tv942-20260923-v1 0 350')
        self.assertEqual('match-tv942',tv['mode']);self.assertEqual(0,tv['offset']);self.assertEqual(350,tv['limit'])
        samo=m.parse_command(f'/run-int-server-v1 {SHA} match-samo942 int-andromeda-match-samo942-20260923-v1 700 242')
        self.assertEqual('match-samo942',samo['mode']);self.assertEqual(700,samo['offset']);self.assertEqual(242,samo['limit'])

    def test_match_readback_mode(self):
        tv=m.parse_command(f'/run-int-server-v1 {SHA} match-readback int-anex-match-tv942-readback-20260923-v1 tv 0 350')
        self.assertEqual('match-readback',tv['mode']);self.assertEqual('tv',tv['lane'])
        self.assertEqual(0,tv['offset']);self.assertEqual(350,tv['limit'])
        samo=m.parse_command(f'/run-int-server-v1 {SHA} match-readback int-andromeda-match-samo942-readback-20260923-v1 samo 700 242')
        self.assertEqual('samo',samo['lane']);self.assertEqual(700,samo['offset']);self.assertEqual(242,samo['limit'])

    def test_local_readback(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} local-readback int-andromeda-local-readback-20260921-v1 1 4 2026-09-24 2026-09-25 7 2 - 0')
        self.assertEqual('local-readback',v['mode'])
        self.assertEqual(4,v['country'])
    def test_reconcile(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} reconcile int-andromeda-reconcile-turkey-20260921-v1 int-andromeda-current-turkey-20260921-v1')
        self.assertEqual('reconcile',v['mode'])
        self.assertEqual('int-andromeda-current-turkey-20260921-v1',v['target_operation_id'])
    def test_rejects_unsafe_or_unbounded(self):
        bad=[
          f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260921-v1 0',
          f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260921-v1 21',
          f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v1 1 4 2026-99-10 2026-10-12 7 2 - 0 0',
          f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v3 1 4 2026-10-13 2026-10-14 7 2 - 0 31',
          f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v1 1 4 2026-10-10 2026-10-12 7 2 ";rm" 0 0',
          f'/run-int-server-v1 {SHA[:-1]} anex-demand int-anex-current-demand-20260921-v1 3',
          f'/run-int-server-v1 {SHA} anex-demand ../../bad 3',
          f'/run-int-server-v1 {SHA} reconcile int-andromeda-reconcile-turkey-20260921-v1 ../../bad',
          f'/run-int-server-v1 {SHA} reconcile int-andromeda-current-turkey-20260921-v1 int-andromeda-current-turkey-20260921-v1',
          f'/run-int-server-v1 {SHA} install-runtime int-andromeda-runtime-install-20260922-v1 extra',
          f'/run-int-server-v1 {SHA} andromeda-external-group int-andromeda-external-group-turkey-20260922-v1 1 4 2026-10-20 2026-10-20 7 2 - 0 2',
          f'/run-int-server-v1 {SHA} andromeda-operator-scope int-andromeda-intourist-scope-20260922-v1 1 4 2026-10-11 2026-10-11 7 2 - 0 0',
          f'/run-int-server-v1 {SHA} andromeda-operator-scope int-andromeda-intourist-scope-20260922-v1 1 4 2026-10-11 2026-10-11 7 2 - 0 43 extra',
          f'/run-int-server-v1 {SHA} andromeda-operator-preflight int-andromeda-intourist-preflight-20260923-v1 1 4 2026-10-18 2026-10-18 7 2 - 0 0',
          f'/run-int-server-v1 {SHA} andromeda-operator-preflight int-andromeda-intourist-preflight-20260923-v1 1 4 2026-10-18 2026-10-18 7 2 - 0 43 extra',
          f'/run-int-server-v1 {SHA} match-tv942 int-andromeda-match-tv942-20260923-v1 0 100',
          f'/run-int-server-v1 {SHA} match-samo942 int-anex-match-samo942-20260923-v1 0 100',
          f'/run-int-server-v1 {SHA} match-tv942 int-anex-match-tv942-20260923-v1 900 43',
          f'/run-int-server-v1 {SHA} match-samo942 int-andromeda-match-samo942-20260923-v1 0 351',
          f'/run-int-server-v1 {SHA} match-readback int-anex-match-tv942-readback-20260923-v1 nope 0 100',
          f'/run-int-server-v1 {SHA} match-readback int-andromeda-match-tv942-readback-20260923-v1 tv 0 100',
          f'/run-int-server-v1 {SHA} match-readback int-anex-match-tv942-readback-20260923-v1 tv 900 43',
        ]
        for value in bad:
            with self.subTest(value=value),self.assertRaises(ValueError):m.parse_command(value)

class CoordinatorTest(unittest.TestCase):
    def test_only_current_journal_can_authorize_a_server_command(self):
        body=f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260922-v1 3'
        comment={'id':123,'body':body,'user':{'id':226193297},'author_association':'OWNER'}
        replies={
            '/issues/comments/123':comment,
            '/git/ref/heads/main':{'object':{'sha':'a'*40}},
            '/git/ref/heads/'+m.FEATURE:{'object':{'sha':SHA}},
        }
        with patch.object(m,'api_get',side_effect=lambda path,token:replies[path]) as api:
            event={'issue':{'number':3419},'comment':comment}
            self.assertEqual(m.checked_event('fixture',event,'a'*40)['source_sha'],SHA)
            for number in (2530,996,1646):
                api.reset_mock()
                event={'issue':{'number':number},'comment':comment}
                with self.subTest(issue=number),self.assertRaisesRegex(ValueError,'issue'):
                    m.checked_event('fixture',event,'a'*40)
                api.assert_not_called()

    def test_workflow_uses_the_same_current_journal(self):
        text=(SCRIPT.parents[2]/'.github/workflows/int-server-executor.yml').read_text()
        self.assertIn('github.event.issue.number == 3419',text)
        self.assertNotIn('github.event.issue.number == 2530',text)


class BundleTest(unittest.TestCase):
    def test_private_inventory_only(self):
        with tempfile.TemporaryDirectory() as td:
            root=Path(td);app=root/'app/integrations';app.mkdir(parents=True)
            for i in range(21):(app/f'x{i}.php').write_text('<?php\n')
            for rel in m.FIXED:
                p=root/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('<?php\n')
            blob,manifest=m.bundle_source(root)
            self.assertGreater(len(blob),100)
            self.assertGreaterEqual(len(manifest),28)
            self.assertTrue(all(x.startswith('app/integrations/') or x in m.FIXED for x in manifest))
            self.assertFalse(any(x.startswith('.github/') for x in manifest))
            p=root/m.FIXED[0];p.unlink();p.symlink_to(app/'x0.php')
            with self.assertRaises(ValueError):m.bundle_source(root)

class InstallRuntimeTest(unittest.TestCase):
    def fixture(self, root: Path, operation: str, fail_target_lint: bool = False):
        source=root/'source';app=source/'app/integrations';app.mkdir(parents=True)
        for i in range(21):(app/f'x{i}.php').write_text('<?php\n')
        # The functional fuel consumer must be in the exact installed inventory.
        (app/'three-provider-fuel-evidence.php').write_text('<?php\n')
        for rel in m.FIXED:
            p=source/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('<?php\n')
        bundle,manifest=m.bundle_source(source)
        archive=root/'source.tar.gz';archive.write_bytes(bundle)
        home=root/'home';project=home/'www/anytoour.ru'
        runtime=project/'_preview/search3-anex-candidate'
        (runtime/'app/integrations').mkdir(parents=True)
        (runtime/'app/integrations/x0.php').write_text('<?php /* old */\n')
        bindir=root/'bin';bindir.mkdir()
        php=bindir/'php'
        php.write_text(
            '#!/bin/sh\n'
            'if [ "${FAKE_PHP_FAIL_TARGET:-0}" = 1 ] && echo "$2" | grep -q "/www/anytoour.ru/"; then exit 1; fi\n'
            'exit 0\n'
        )
        php.chmod(0o755)
        payload={'source_sha':SHA,'mode':'install-runtime','operation_id':operation,
                 'archive':str(archive),'manifest_sha256':hashlib.sha256(
                     json.dumps(manifest,sort_keys=True,separators=(',',':')).encode()
                 ).hexdigest()}
        env=dict(os.environ,HOME=str(home),PATH=str(bindir)+os.pathsep+os.environ.get('PATH',''))
        if fail_target_lint:env['FAKE_PHP_FAIL_TARGET']='1'
        run=subprocess.run(['python3','-c',m.REMOTE],input=json.dumps(payload),
                           text=True,capture_output=True,env=env,timeout=30)
        self.assertEqual('',run.stderr)
        self.assertEqual(0,run.returncode)
        return json.loads(run.stdout),home,project

    def test_exact_install_backup_and_readback(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(Path(td),'int-andromeda-runtime-install-20260922-v1')
            self.assertEqual('installed',result['status'])
            self.assertEqual(22,result['install']['files'])
            self.assertEqual('<?php\n',(project/'_preview/search3-anex-candidate/app/integrations/x0.php').read_text())
            backup=home/'.anytoour-int-executor/int-andromeda-runtime-install-20260922-v1/backup/app/integrations/x0.php'
            self.assertEqual('<?php /* old */\n',backup.read_text())
            self.assertTrue(result['public_ui_entrypoints_unchanged'])
            self.assertEqual(0,result['supplier_calls'])
            self.assertEqual(0,result['database_writes'])
            self.assertFalse((project/'app').exists())

    def test_post_install_failure_rolls_back_every_file(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(
                Path(td),'int-andromeda-runtime-install-20260922-v2',True)
            self.assertEqual('rolled_back',result['status'])
            self.assertEqual('complete',result['rollback']['status'])
            self.assertEqual('<?php /* old */\n',(project/'_preview/search3-anex-candidate/app/integrations/x0.php').read_text())
            self.assertFalse((project/'_preview/search3-anex-candidate/app/integrations/x1.php').exists())
            self.assertFalse((project/'_preview/search3-anex-candidate/app/integrations/three-provider-fuel-evidence.php').exists())
            self.assertFalse(result['runtime_changed'])

class ContractTest(unittest.TestCase):
    def test_control_boundaries(self):
        text=SCRIPT.read_text()
        for x in ["ISSUE = 3419","OWNER_ID = 226193297","FEATURE = 'feature/anex-search-adapter-20260907'",
                  "operation_exists_no_replay","StrictHostKeyChecking=yes","production_unchanged",
                  "install-runtime","install-plan.json","install-state.json","rollback_install",
                  "manifest_digest","public_ui_entrypoints_unchanged","three-provider-fuel-evidence.php",
                  "anex_local_offer_demand_fill.php","andromeda_local_offer_collect.php",
                  "search3-local-results-read-v1.php","--max-captures=","non_external_only",
                  "andromeda-external-group","external_group_only",
                  "andromeda-operator-scope","--operator-id=","operatorId",
                  "andromeda-operator-preflight","operator_preflight","operator_not_loaded",
                  "operator_dictionary_missing","andromeda_operators","operator_preflight_db_drift",
                  "operator_name","dictionary_operator_id",
                  "reconcile_target","collector_stderr_sha256","skipped_after_collector_nonzero",
                  "php=\"$c=require $argv[1];","allowed_keys={'status'","name_sha256","top_level_keys",
                  "local-readback","local_readback_exit","stderr_sha256",
                  "program-fuel-readback","program_fuel_readback","program_fuel_readback_php_b64",
                  "program_fuel_readback_acceptance","program_fuel_readback_db_drift",
                  "LOCAL_READER_MISSING","LOCAL_DB_CONNECTION","LOCAL_DB_NOT_CONFIGURED",
                  "require_once $config","errorSha256","attempt_state","package_record",
                  "diagnostic_code","actualization","failure_class","actions_used",
                  "supplier_error_facts","reason_category","code_field","error_sha256",
                  "rejection_summary","ownership_class","missing_field","classified",
                  "match-tv942","match-samo942","supplier_slot_busy",
                  "hotel_match_live942_frontier_plan_v1.php",
                  "hotel_match_live942_tv_anex_refresh_v1.py",
                  "hotel_match_live942_samo_anex_refresh_v1.php",
                  ".anytoour-match/operations","match_terminal_hash",
                  "match-readback","match942_child_name","read_match942",
                  "install-runtime','match-readback','match-tv942','match-samo942",
                  "provider_attempted_without_terminal","pre_provider_reservation_only",
                  "match_readback_hash"]:
            self.assertIn(x,text)
        for x in ['shell=True',"booking(","bron_ticket","workflow_dispatch("]:
            self.assertNotIn(x,text)

if __name__=='__main__':unittest.main(verbosity=2)
