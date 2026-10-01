#!/usr/bin/env python3
from __future__ import annotations

import ast
import hashlib
import importlib.util
import io
import json
import os
import re
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import types
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]


def load(name, relative):
    spec = importlib.util.spec_from_file_location(name, ROOT / relative)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


local = load('local_profile_plan', 'scripts/deploy/int_server_executor_local_profile_plan.py')
SHA = 'a' * 40
CONTROL = 'b' * 40
COMMAND = f'/run-int-server-v1 {SHA} {local.MODE} {local.OPERATION} {local.BATCH}'


class LocalContractTest(unittest.TestCase):
    def core(self):
        core = load('local_test_core', 'scripts/deploy/int_server_executor.py')
        local.register_parser(core)
        return core

    def test_exact_scope_and_zero_authority(self):
        value = self.core().parse_command(COMMAND)
        self.assertEqual(local.BATCH, value['batch'])
        self.assertEqual(0, value['maximum_writes'])
        self.assertEqual(0, value['provider_http_calls'])
        self.assertEqual(local.OPERATION, value['operation_id'])

    def test_rejects_arbitrary_scope_fields_apply_and_replay_names(self):
        bad = [
            COMMAND + ' --apply', COMMAND + ' 5227', COMMAND.replace(local.BATCH, 'all'),
            COMMAND.replace(local.OPERATION, local.OPERATION.replace('-v2', '-v1')),
            COMMAND.replace(local.OPERATION, local.OPERATION.replace('-v2', '-v3')),
            COMMAND.replace(SHA, 'g' * 40),
            COMMAND.replace(local.MODE, 'local-profile-apply-4191'),
            COMMAND.replace(local.MODE, 'local-profile-plan'),
        ]
        for body in bad:
            with self.subTest(body=body), self.assertRaises(ValueError):
                self.core().parse_command(body)

    def test_old_mode_parser_delegates_without_change(self):
        original = load('old_test_core', 'scripts/deploy/int_server_executor.py')
        new = self.core()
        for body in [
            f'/run-int-server-v1 {SHA} install-runtime int-andromeda-runtime-install-20260922-v1',
            f'/run-int-server-v1 {SHA} local-readback int-andromeda-test-readback-20261001-v1 1 4 2026-10-12 2026-10-18 7 7 2 0',
        ]:
            with self.subTest(body=body):
                try:
                    expected = original.parse_command(body)
                except ValueError as error:
                    with self.assertRaisesRegex(ValueError, str(error)):
                        new.parse_command(body)
                else:
                    self.assertEqual(expected, new.parse_command(body))

    def event(self, body=COMMAND):
        return {'issue': {'number': 4217}, 'comment': {
            'body': body, 'id': 1, 'user': {'id': 226193297}, 'author_association': 'OWNER'}}

    def test_fresh_release_required_with_unchanged_main_and_owner_guards(self):
        core = self.core()
        paths = []

        def api(path, token):
            paths.append(path)
            if path == '/issues/comments/1':
                return self.event()['comment']
            return {'object': {'sha': CONTROL if path.endswith('/main') else SHA}}

        with patch.object(core, 'api_get', side_effect=api):
            self.assertEqual(local.MODE, core.checked_event('test', self.event(), CONTROL)['mode'])
        self.assertIn('/git/ref/heads/release/search3-production-ready-v1', paths)
        self.assertNotIn('/git/ref/heads/' + core.FEATURE, paths)

    def test_rejects_changed_release_and_main(self):
        for target in ('main', 'release'):
            core = self.core()

            def api(path, token):
                if path == '/issues/comments/1':
                    return self.event()['comment']
                is_main = path.endswith('/main')
                value = CONTROL if is_main else SHA
                if (target == 'main') == is_main:
                    value = 'c' * 40
                return {'object': {'sha': value}}

            with self.subTest(target=target), patch.object(core, 'api_get', side_effect=api):
                with self.assertRaises(ValueError):
                    core.checked_event('test', self.event(), CONTROL)

    def test_rejects_foreign_actor_journal_or_edited_comment(self):
        for mutate in (
            lambda e: e['comment']['user'].update(id=1),
            lambda e: e['issue'].update(number=2690),
            lambda e: e['comment'].update(author_association='MEMBER'),
            lambda e: e['issue'].update(pull_request={}),
        ):
            event = self.event()
            mutate(event)
            core = self.core()
            with patch.object(core, 'api_get', return_value={'body': 'changed', 'user': {'id': 226193297}}):
                with self.assertRaises(ValueError):
                    core.checked_event('test', event, CONTROL)
        # An actual pull-request marker is excluded, regardless of owner.
        event = self.event()
        event['issue']['pull_request'] = {'url': 'https://example.invalid'}
        with self.assertRaises(ValueError):
            self.core().checked_event('test', event, CONTROL)

    def test_bundle_only_saved_content_owners_and_control_runner(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for relative in local.SOURCE_FILES:
                file = root / relative
                file.parent.mkdir(parents=True, exist_ok=True)
                file.write_text('<?php // saved content only\n')
            (root / 'config.php').write_text('private-do-not-bundle')
            value, hashes = local.bundle_source(root)
            self.assertEqual(set(local.BUNDLE_FILES), set(hashes))
            with tarfile.open(fileobj=io.BytesIO(value), mode='r:gz') as archive:
                self.assertEqual(set(local.BUNDLE_FILES) | {'manifest.json'}, set(archive.getnames()))
                for name, digest in hashes.items():
                    self.assertEqual(digest, hashlib.sha256(archive.extractfile(name).read()).hexdigest())
            self.assertNotIn(b'private-do-not-bundle', value)

    def test_missing_or_symlink_source_fails_before_transport(self):
        with tempfile.TemporaryDirectory() as tmp:
            with self.assertRaisesRegex(ValueError, 'source_path'):
                local.bundle_source(Path(tmp))
            root = Path(tmp)
            for relative in local.SOURCE_FILES:
                file = root / relative
                file.parent.mkdir(parents=True, exist_ok=True)
                file.write_text('<?php')
            first = root / local.SOURCE_FILES[0]
            first.unlink()
            first.symlink_to(root / local.SOURCE_FILES[1])
            with self.assertRaisesRegex(ValueError, 'source_path'):
                local.bundle_source(root)

    def test_remote_preserves_other_mode_floor_and_excludes_collectors(self):
        core = self.core()
        remote = local.remote_with_plan(core)
        ast.parse(remote)
        self.assertIn("elif not isinstance(files,dict) or len(files)<20: fail('manifest')", remote)
        self.assertEqual(2, remote.count("if mode not in ('local-profile-plan-4191','reconcile',"))
        self.assertIn("set(files)!=", remote)
        self.assertNotIn('--execute', local.REMOTE_HANDLER)
        self.assertNotIn('ANEX_API_TOKEN', local.REMOTE_HANDLER)
        self.assertIn("'-d','allow_url_fopen=0'", local.REMOTE_HANDLER)
        self.assertIn('socket_connect', local.REMOTE_HANDLER)
        self.assertIn("result['production_after']!=before", local.REMOTE_DISPATCH)

    def test_activation_cannot_change_other_modes_or_bypass_command_shape(self):
        core = self.core()
        remote, bundle = core.REMOTE, core.bundle_source
        local.activate(core, {'mode': 'match-primary-candidate'})
        self.assertEqual(remote, core.REMOTE)
        self.assertIs(bundle, core.bundle_source)
        bad = core.parse_command(COMMAND)
        bad['maximum_writes'] = 1
        with self.assertRaises(ValueError):
            local.activate(core, bad)
        local.activate(core, core.parse_command(COMMAND))
        self.assertIs(local.bundle_source, core.bundle_source)
        self.assertNotEqual(remote, core.REMOTE)

    def test_registration_fails_on_control_source_drift(self):
        core = self.core()
        core.REMOTE = core.REMOTE.replace("    if mode=='match-tv942-write':", '    if False:')
        with self.assertRaisesRegex(ValueError, 'source_drift'):
            local.remote_with_plan(core)

    def test_workflow_only_extends_existing_contract_checks(self):
        flow = (ROOT / '.github/workflows/int-server-executor.yml').read_text()
        self.assertEqual(2, flow.count('python3 tests/int_server_executor_local_profile_plan_test.py -v'))
        self.assertEqual(2, flow.count('php -l scripts/diagnostics/local_profile_plan_4191.php'))
        self.assertIn("group: anytoour-int-server-executor", flow)
        self.assertIn('github.event.issue.number == 4217', flow)
        self.assertIn('github.event.comment.user.id == 226193297', flow)
        self.assertNotIn('local-profile-plan-4191', flow)  # Same entrypoint, no auto command/job.


class TerminalReceiptTest(unittest.TestCase):
    def namespace(self, tmp):
        root = Path(tmp)
        stage = root / 'source'
        runner = stage / local.RUNNER
        runner.parent.mkdir(parents=True)
        runner.write_text('<?php')
        plan = root / 'local-plan.json'
        plan.write_text('{"private": "not a public receipt"}')
        data = {
            'schema_version': 1, 'state': 'completed_read_only',
            'operation_id': local.OPERATION, 'source_sha': SHA, 'control_source_sha': CONTROL, 'batch': local.BATCH,
            'requested_profiles': 366, 'profiles_read': 366, 'aliases_validated': 366,
            'source_plans_prepared': 0, 'classification_counts': {'D1_MANIFEST_UNKNOWN_HELD': 366},
            'd1_exclusion_state': 'unknown_held',
            'private_plan_sha256': hashlib.sha256(plan.read_bytes()).hexdigest(),
            'provider_http_calls': 0, 'database_writes': 0, 'profile_writes': 0,
            'mapping_writes': 0, 'schema_writes': 0, 'safe_to_apply': False,
        }
        receipt = root / 'local-plan-receipt.json'
        receipt.write_text(json.dumps(data))
        run = types.SimpleNamespace(returncode=0, stdout='', stderr='')
        ns = {
            'payload': {'batch': local.BATCH, 'maximum_writes': 0, 'provider_http_calls': 0,
                        'local_profile_control_sha': CONTROL},
            'operation': local.OPERATION, 'source': SHA, 'project': root, 'op': root,
            'safe_file': lambda p, maximum: p.is_file() and p.stat().st_size <= maximum,
            'safe_json': lambda p, maximum: json.loads(p.read_text()),
            'fail': lambda reason: (_ for _ in ()).throw(RuntimeError(reason)),
            'os': os, 're': re, 'hashlib': hashlib, 'subprocess': types.SimpleNamespace(run=lambda *a, **k: run),
        }
        exec(local.REMOTE_HANDLER, ns)
        return ns, stage, receipt, data, run

    def test_actual_handler_accepts_only_sanitized_zero_write_receipt(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns, stage, receipt, data, run = self.namespace(tmp)
            value = ns['run_local_profile_plan_4191'](stage)
            self.assertEqual(data, value)
            self.assertNotIn('private', value)

    def test_actual_handler_checks_private_digest_shape_counts_and_zero_authority(self):
        mutations = [
            lambda d: d.update(profile_writes=1),
            lambda d: d.update(database_writes=True),
            lambda d: d.update(safe_to_apply=True),
            lambda d: d.update(requested_profiles=365),
            lambda d: d.update(private_plan_sha256='0' * 64),
            lambda d: d.update(classification_counts={'D1_MANIFEST_UNKNOWN_HELD': 365}),
            lambda d: d.update(classification_counts={'accepted_mapping': 366}),
            lambda d: d.update(source_plans_prepared=1),
            lambda d: d.update(raw_profile='must not be public'),
            lambda d: d.update(source_sha='c' * 40),
            lambda d: d.update(control_source_sha='c' * 40),
        ]
        for mutate in mutations:
            with self.subTest(mutation=mutate), tempfile.TemporaryDirectory() as tmp:
                ns, stage, receipt, data, run = self.namespace(tmp)
                mutate(data)
                receipt.write_text(json.dumps(data))
                with self.assertRaises(RuntimeError):
                    ns['run_local_profile_plan_4191'](stage)

    def test_missing_terminal_does_not_retry_php(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns, stage, receipt, data, run = self.namespace(tmp)
            calls = []
            ns['subprocess'].run = lambda *a, **k: calls.append((a, k)) or run
            receipt.unlink()
            with self.assertRaisesRegex(RuntimeError, 'no_replay'):
                ns['run_local_profile_plan_4191'](stage)
            self.assertEqual(1, len(calls))

    def test_process_nonzero_or_stderr_cannot_be_complete(self):
        for field, value in [('returncode', 2), ('stderr', 'failure')]:
            with self.subTest(field=field), tempfile.TemporaryDirectory() as tmp:
                ns, stage, receipt, data, run = self.namespace(tmp)
                setattr(run, field, value)
                with self.assertRaisesRegex(RuntimeError, 'no_replay'):
                    ns['run_local_profile_plan_4191'](stage)

    def test_php_mode_has_no_supply_apply_or_mutating_sql_path(self):
        source = (ROOT / local.RUNNER).read_text()
        self.assertIn('SET SESSION TRANSACTION READ ONLY', source)
        self.assertIn('$owner->plan(count($scope), $through, $scope, true)', source)
        self.assertIn('array_chunk($eligible, LPP_OWNER_MAX_BATCH, true)', source)
        self.assertIn("AnyTourProfileEnrichmentV1::MAX_BATCH === LPP_OWNER_MAX_BATCH", source)
        for forbidden in ('->apply(', 'curl_', 'INSERT ', 'UPDATE ', 'DELETE ', 'ALTER ', 'CREATE TABLE'):
            self.assertNotIn(forbidden, source)
        self.assertIn("basename($dir) === LPP_OPERATION", source)


@unittest.skipUnless(shutil.which('php'), 'PHP contract runs on the repository CI runner')
class PhpPlannerTest(unittest.TestCase):
    def php(self, body):
        start = "require $argv[1];\nfunction ok($c){if(!$c)throw new RuntimeException('assertion');}\n"
        result = subprocess.run(['php', '-r', start + body, str(ROOT / local.RUNNER)],
                                text=True, capture_output=True, timeout=15)
        self.assertEqual(0, result.returncode, result.stderr + result.stdout)

    def fixtures(self):
        return r"""
function currentRow($own,$local){
    $profile='{"description":null,"images":[]}';
    $proof=['schema_version'=>1,'accepted_local_hotel_id'=>$local,'canonical_hotel_id'=>$own,
        'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('a',64)];
    $alias=lpp_json($proof);
    return ['id'=>$own,'is_active'=>1,'revision'=>1,'profile_json'=>$profile,
        'profile_sha256'=>hash('sha256',$profile),'local_id'=>$local,
        'alias_acquired_via'=>'canonical_local_alias_v1','alias_source_json'=>$alias,
        'alias_source_sha256'=>hash('sha256',$alias)];
}
function prepared($scope,$rows){
    $selected=[];
    foreach($scope as $s){
        $row=$rows[$s['anytourHotelId']][0];
        $selected[]=['anytourHotelId'=>$s['anytourHotelId'],'localHotelId'=>$s['localHotelId'],
            'expectedRevision'=>1,'expectedProfileSha256'=>$row['profile_sha256'],
            'expectedAliasSha256'=>$row['alias_source_sha256']];
    }
    return ['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0,'limit'=>count($scope),
        'activeProfiles'=>count($scope),'scannedProfiles'=>count($scope),'contentPolicy'=>'sync_imported_retained_tv_v1',
        'selected'=>$selected,'held'=>[]];
}
"""

    def test_fixed_queue_and_zero_callback_for_unknown_d1(self):
        self.php(self.fixtures() + r"""
$rows=[];foreach(lpp_ids() as $id)$rows[$id]=[currentRow($id,$id+100000)];
$calls=0;$result=lpp_classify($rows,['state'=>'unknown_held'],
    function($scope)use(&$calls){++$calls;throw new RuntimeException('must not call');});
ok(count(lpp_ids())===366 && count($result['rows'])===366 && $calls===0);
ok($result['classification_counts']===['D1_MANIFEST_UNKNOWN_HELD'=>366]);
""")

    def test_mass_scope_keeps_d1_overlap_and_drift_held(self):
        self.php(self.fixtures() + r"""
$ids=lpp_ids();$rows=[];foreach($ids as $id)$rows[$id]=[currentRow($id,$id+100000)];
$d1=['state'=>'verified_terminal_manifest','ownIds'=>[$ids[0]],'legacyIds'=>[$ids[1]+100000]];
$calls=[];
$result=lpp_classify($rows,$d1,function($scope)use(&$calls,$rows,$ids){
    $calls[]=count($scope);foreach($scope as $s)ok($s['fields']===LPP_FIELDS);
    $plan=prepared($scope,$rows);
    foreach($plan['selected'] as &$item)if($item['anytourHotelId']===$ids[2])$item['expectedRevision']=2;unset($item);
    return $plan;
});
ok($calls===[250,114] && $result['source_plans_prepared']===364 && $result['classification_counts']['D1_OVERLAP_HELD']===2);
ok($result['classification_counts']['CURRENT_DRIFT_HELD']===1);
ok($result['classification_counts']['RETAINED_DELTA_PREPARED']===363);
foreach($result['rows'] as $row)ok($row['safeToApply']===false);
""")

    def test_bad_alias_profile_and_missing_profile_never_reach_owner(self):
        self.php(self.fixtures() + r"""
$ids=lpp_ids();$a=currentRow($ids[0],100001);$a['alias_source_sha256']=str_repeat('0',64);
$b=currentRow($ids[1],100002);$b['profile_sha256']=str_repeat('0',64);
$c=currentRow($ids[2],100003);
$rows=[$ids[0]=>[$a],$ids[1]=>[$b],$ids[2]=>[$c,$c]];
$result=lpp_classify($rows,['state'=>'verified_terminal_manifest','ownIds'=>[],'legacyIds'=>[]],
    function($s){throw new RuntimeException('must not call');});
ok($result['source_plans_prepared']===0 && $result['classification_counts']['ALIAS_HELD']===2);
ok($result['classification_counts']['PROFILE_INTEGRITY_HELD']===1);
ok($result['classification_counts']['PROFILE_UNAVAILABLE']===363);
""")

    def test_source_missing_and_manual_protection_preserve_separate_rows(self):
        self.php(self.fixtures() + r"""
$ids=lpp_ids();$rows=[];foreach(array_slice($ids,0,3) as $id)$rows[$id]=[currentRow($id,$id+100000)];
$result=lpp_classify($rows,['state'=>'verified_terminal_manifest','ownIds'=>[],'legacyIds'=>[]],
    function($scope)use($rows,$ids){
        $plan=prepared($scope,$rows);$plan['selected']=[];
        foreach($scope as $s){
            $id=$s['anytourHotelId'];
            if($id===$ids[0])$plan['held'][$id]=['description'=>'source_missing_preserved'];
            elseif($id===$ids[1])$plan['held'][$id]=['profile'=>'SYNC_UNPROVEN_OR_MANUAL_PROFILE'];
        }
        return $plan;
    });
ok($result['classification_counts']['SOURCE_MISSING']===1);
ok($result['classification_counts']['SOURCE_PROVENANCE_HELD']===1);
ok($result['classification_counts']['RETAINED_NO_DELTA']===1);
""")

    def test_d1_seal_requires_exact_terminal_digest_and_all_80_identities(self):
        self.php(r"""
$core=['schemaVersion'=>1,'retryBefore'=>'1970-01-01 00:00:00','demandThrough'=>'2026-09-28 22:00:00',
    'limit'=>80,'eligibleIdentityOnly'=>80,'genericPreSkipped'=>0,'selected'=>[],'supplierCalls'=>0,'writes'=>0];
for($i=1;$i<=80;$i++)$core['selected'][]=['anytourHotelId'=>$i,'tourvisorHotelId'=>$i+1000,
    'previousDetailStatus'=>null,'previousDetailFetchedAt'=>null];
$digest=hash('sha256',lpp_json($core));$core['planSha256']=$digest;$plan=['status'=>'prepared_read_only']+$core;
$result=['status'=>'completed','planSha256'=>$digest,'selected'=>80,'success'=>80,'notFound'=>0,'failed'=>0,
    'httpAttempts'=>80,'canonicalProfileWrites'=>0,'mappingWrites'=>0];
$reserved="owner_claim=5880018336\nsource=".LPP_D1_SOURCE."\n";
$started="plan=$digest\nsource=".LPP_D1_SOURCE."\n";
$proof=lpp_d1_values($plan,$result,$reserved,$started,'0');
ok(count($proof['ownIds'])===80 && count($proof['legacyIds'])===80);
foreach(['hash','result','marker','identity'] as $mutation){
    $p=$plan;$r=$result;$s=$started;
    if($mutation==='hash')$p['eligibleIdentityOnly']=81;
    if($mutation==='result')$r['canonicalProfileWrites']=1;
    if($mutation==='marker')$s='wrong';
    if($mutation==='identity')$p['selected'][0]['tourvisorHotelId']=null;
    $threw=false;try{lpp_d1_values($p,$r,$reserved,$s,'0');}catch(Throwable){$threw=true;}ok($threw);
}
""")

    def test_missing_private_d1_manifest_is_explicit_unknown_hold(self):
        self.php("ok(lpp_d1('/path/that/does/not/exist')['state']==='unknown_held');")

    def test_php_lint(self):
        result = subprocess.run(['php', '-l', str(ROOT / local.RUNNER)], capture_output=True, text=True)
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)


if __name__ == '__main__':
    unittest.main()
