from __future__ import annotations

import ast
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import types
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('local_tv_schema_contract', ROOT/'scripts/deploy/int_server_executor_local_tv_schema.py')
assert spec and spec.loader
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
SOURCE_ROOT = Path(os.environ.get('LOCAL_TV_SCHEMA_SOURCE_ROOT', str(ROOT/'local-tv-source')))
SHA = 'a'*40


def core():
    def previous(body):
        if body == 'old-command': return {'old': True}
        raise ValueError('previous_denied')
    c = types.SimpleNamespace(PREFIX='/run-int-server-v1 ', SHA_RE=re.compile(r'[a-f0-9]{40}'), parse_command=previous)
    m.register_parser(c)
    return c


class SchemaControlContract(unittest.TestCase):
    def test_exact_operations_do_not_accept_arbitrary_scope_or_write_flags(self):
        c = core()
        for operation, action in m.OPERATIONS.items():
            command = c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {operation}')
            self.assertEqual(command['action'], action)
            self.assertEqual(command['provider_http_calls'], 0)
            self.assertEqual(command['maximum_content_writes'], 0)
            self.assertEqual(command['maximum_schema_tables'], 2 if action=='bootstrap' else 0)
            for tail in (' --apply', ' 20', ' ../config.php', '-v2'):
                with self.subTest(operation=operation, tail=tail), self.assertRaises(ValueError):
                    c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {operation}{tail}')

    def test_previous_parser_and_stopped_recovery_are_unchanged(self):
        c = core()
        self.assertEqual(c.parse_command('old-command'), {'old': True})
        with self.assertRaisesRegex(ValueError, 'previous_denied'):
            c.parse_command(f'{c.PREFIX}{SHA} local-profile-plan-4191 int-andromeda-local-profile-mass-recovery-4191-20261008-v1 old')

    def test_source_bundle_is_exact_and_checks_reviewed_sql_and_db_helper(self):
        data, hashes = m.bundle_source(SOURCE_ROOT)
        self.assertTrue(data)
        self.assertEqual(set(hashes), set(m.BUNDLE_FILES))
        self.assertEqual(hashes[m.RUNNER], hashlib.sha256((ROOT/m.RUNNER).read_bytes()).hexdigest())
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for path in m.SOURCE_HASHES:
                destination=root/path; destination.parent.mkdir(parents=True,exist_ok=True)
                shutil.copyfile(SOURCE_ROOT/path,destination)
            sql=root/'v2/data/migrations/20261010-local-tv-catalog.sql'
            sql.write_text(sql.read_text()+'\nUPDATE anytour_hotels SET profile_json=NULL;')
            with self.assertRaisesRegex(ValueError,'reviewed_source_changed'): m.bundle_source(root)

    def test_activation_keeps_stock_reservation_no_replay_and_owner_envelope(self):
        wrapper_spec=importlib.util.spec_from_file_location('schema_stock_wrapper',ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py')
        assert wrapper_spec and wrapper_spec.loader
        wrapper=importlib.util.module_from_spec(wrapper_spec); wrapper_spec.loader.exec_module(wrapper)
        c=wrapper.core
        command=c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.INSPECT_OPERATION}')
        previous=c.REMOTE
        m.activate(c,command)
        ast.parse(c.REMOTE)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",c.REMOTE)
        self.assertIn("result['production_after']=fingerprints()",c.REMOTE)
        self.assertIn("run_local_tv_schema(stage)",c.REMOTE)
        self.assertNotIn("mode not in ('reconcile',",c.REMOTE)
        self.assertIn("mode not in ('local-tv-schema-v1','reconcile',",c.REMOTE)
        self.assertNotIn('local_profile_mass_plan3_4191.php', str(m.BUNDLE_FILES))
        c.REMOTE=previous
        for key,value in (('maximum_schema_tables',True),('provider_http_calls',1),('maximum_content_writes',1),('extra',True)):
            with self.subTest(key=key), self.assertRaises(ValueError): m.activate(c,command|{key:value})

    def test_checked_event_routes_only_new_mode_to_current_release(self):
        wrapper_spec=importlib.util.spec_from_file_location('schema_current_wrapper',ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py')
        assert wrapper_spec and wrapper_spec.loader
        wrapper=importlib.util.module_from_spec(wrapper_spec); wrapper_spec.loader.exec_module(wrapper)
        c=wrapper.core
        body=f'{c.PREFIX}{SHA} {m.MODE} {m.INSPECT_OPERATION}'
        event={'issue':{'number':4217},'comment':{'id':99,'user':{'id':226193297},'author_association':'OWNER','body':body}}
        def api(path, token):
            if path=='/issues/comments/99': return {'body':body,'user':{'id':226193297}}
            if path=='/git/ref/heads/main': return {'object':{'sha':'b'*40}}
            if path=='/git/ref/heads/release/search3-production-ready-v1': return {'object':{'sha':SHA}}
            raise AssertionError(path)
        with mock.patch.object(c,'api_get',side_effect=api):
            self.assertEqual(c.checked_event('fixture',event,'b'*40)['action'],'inspect')
        event['comment']['user']['id']=1
        with self.assertRaisesRegex(ValueError,'owner'): c.checked_event('fixture',event,'b'*40)


@unittest.skipUnless(os.environ.get('LOCAL_TV_SCHEMA_NATIVE_CI')=='1','disposable native MySQL CI required')
class NativeSchemaAcceptance(unittest.TestCase):
    def native(self, scenario):
        code=r'''
declare(strict_types=1);
define('LOCAL_TV_SCHEMA_LIBRARY_ONLY',true);
require $argv[1];
$scenario=$argv[2]; $sql=file_get_contents($argv[3]); $directory=$argv[4];
$dsn=(string)getenv('LOCAL_TV_SCHEMA_TEST_DSN');
if ($dsn!=='mysql:host=127.0.0.1;port=3306;charset=utf8mb4') throw new RuntimeException('fixture_dsn');
if (getenv('LOCAL_TV_SCHEMA_TEST_PASSWORD')!=='local_tv_schema_test_only') throw new RuntimeException('fixture_password');
$server=new PDO($dsn,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='local_tv_schema_ci_'.bin2hex(random_bytes(6));
$server->exec('CREATE DATABASE `'.$name.'`');
class LostDdlAck extends PDO {
    public int $writes=0;
    public function exec(string $statement): int|false {
        $result=parent::exec($statement);
        if (str_starts_with(trim($statement),'CREATE TABLE IF NOT EXISTS') && ++$this->writes===1) throw new RuntimeException('lost_ddl_ack');
        return $result;
    }
}
try {
    $class=$scenario==='lost_ack' ? LostDdlAck::class : PDO::class;
    $db=new $class($dsn.';dbname='.$name,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec("CREATE TABLE anytour_hotels (id INT PRIMARY KEY,profile_json LONGTEXT)");
    $db->exec("INSERT INTO anytour_hotels VALUES (501,'manual-sentinel')");
    $target=hash('sha256',$name);
    $before=LocalTvSchemaV1::inspect($db);
    $result=null; $error=null;
    if ($scenario==='partial') {
        $first=explode(';',preg_replace('/^--.*$/m','',$sql))[0]; $db->exec($first);
    } elseif ($scenario==='drift') {
        foreach(array_filter(array_map('trim',explode(';',preg_replace('/^--.*$/m','',$sql)))) as $statement) $db->exec($statement);
        $db->exec('ALTER TABLE local_tv_hotels ADD wrong_column INT');
    }
    $other=null;
    if ($scenario==='lock') {
        $other=new PDO($dsn.';dbname='.$name,'root','local_tv_schema_test_only');
        $other->query("SELECT GET_LOCK('anytour-local-tv-daily',0)");
    }
    try {
        if ($scenario!=='inspect') $result=LocalTvSchemaV1::bootstrap($db,$scenario==='sql_drift'?$sql.' ':$sql,$directory,$scenario==='wrong_target'?str_repeat('0',64):$target);
    } catch (Throwable $e) { $error=$e->getMessage(); }
    $after=LocalTvSchemaV1::inspect($db);
    $unchanged=$db->query('SELECT profile_json FROM anytour_hotels WHERE id=501')->fetchColumn()==='manual-sentinel';
    echo json_encode(['before'=>$before,'after'=>$after,'result'=>$result,'error'=>$error,'old_unchanged'=>$unchanged,'lost_ack_writes'=>$db instanceof LostDdlAck?$db->writes:null],JSON_THROW_ON_ERROR);
} finally { $server->exec('DROP DATABASE `'.$name.'`'); }
'''
        with tempfile.TemporaryDirectory(prefix='local-tv-native-') as directory:
            call=subprocess.run(['php','-r',code,str(ROOT/m.RUNNER),scenario,str(SOURCE_ROOT/'v2/data/migrations/20261010-local-tv-catalog.sql'),directory],capture_output=True,text=True,timeout=30)
            self.assertEqual(call.returncode,0,call.stderr)
            result=json.loads(call.stdout)
            self.assertTrue(result['old_unchanged'])
            return result

    def test_inspection_does_not_create_schema_or_modify_old_profile(self):
        value=self.native('inspect')
        self.assertEqual(value['before'],value['after'])
        self.assertTrue(all(t=={'present':False} for t in value['after']['tables'].values()))

    def test_two_actual_innodb_tables_are_created_empty_and_verified(self):
        value=self.native('bootstrap')
        self.assertIsNone(value['error'])
        self.assertEqual(value['result']['state'],'installed_empty')
        self.assertEqual(value['result']['schema_tables_created'],2)
        self.assertTrue(all(t['present'] and t['valid'] and t['rows']==0 for t in value['after']['tables'].values()))

    def test_partial_existing_schema_is_held_without_completing_it(self):
        value=self.native('partial')
        self.assertEqual(value['error'],'existing_or_partial_schema_review_required')
        self.assertFalse(value['after']['tables']['local_tv_legacy_links']['present'])

    def test_schema_drift_is_detected_and_never_altered_by_bootstrap(self):
        value=self.native('drift')
        self.assertEqual(value['error'],'existing_or_partial_schema_review_required')
        self.assertIn('columns',value['after']['tables']['local_tv_hotels']['issues'])

    def test_wrong_database_or_changed_sql_produces_no_schema_write(self):
        for scenario, error in (('wrong_target','target_database_changed'),('sql_drift','schema_source_hash')):
            with self.subTest(scenario=scenario):
                value=self.native(scenario)
                self.assertEqual(value['error'],error)
                self.assertEqual(value['before'],value['after'])

    def test_lost_ack_after_real_ddl_is_unknown_and_no_second_ddl_is_attempted(self):
        value=self.native('lost_ack')
        self.assertEqual(value['error'],'schema_unknown_no_replay')
        self.assertEqual(value['lost_ack_writes'],1)
        self.assertTrue(value['after']['tables']['local_tv_hotels']['present'])
        self.assertFalse(value['after']['tables']['local_tv_legacy_links']['present'])

    def test_active_collector_lock_prevents_schema_change(self):
        value=self.native('lock')
        self.assertEqual(value['error'],'local_collector_active')
        self.assertEqual(value['before'],value['after'])


if __name__=='__main__':
    unittest.main(verbosity=2)
