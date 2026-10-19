#!/usr/bin/env python3
"""Offline contract, contention, projection and native MySQL acceptance."""
import ast
from concurrent.futures import ThreadPoolExecutor
import copy
import datetime as dt
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
SOURCE=Path(os.environ.get('MATCH_RETAINED_SOURCE_ROOT',ROOT))


def load(name,path):
    spec=importlib.util.spec_from_file_location(name,path)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    return module


m=load('retained_control',ROOT/'scripts/deploy/int_server_executor_match_retained.py')
q=load('retained_quota',SOURCE/'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py')
p=load('retained_projection',SOURCE/'scripts/diagnostics/hotel_match_retained_projection_v1.py')
SHA='a'*40
CONTROL='b'*40
OP='int-andromeda-match-retained-20261020-v1'
QUOTA_OP='int-andromeda-match-quota5000-20261020-v1'
IDENTITY_COLUMNS=['supplier_namespace','external_hotel_id','local_hotel_id','decision_status']


def core():
    return load('retained_core_test',ROOT/'scripts/deploy/int_server_executor.py')


class ContractTest(unittest.TestCase):
    def test_readback_preserves_failed_receipt_and_never_loads_unreviewed_helper(self):
        with tempfile.TemporaryDirectory() as td:
            private=Path(td)/'private';target=private/OP;target.mkdir(parents=True)
            project=Path(td)/'project';(project/'data').mkdir(parents=True)
            (project/'data/db-v1.php').write_text('unreviewed helper')
            reservation={'operation_id':OP,'source_sha':SHA}
            prior={**reservation,'status':'unknown_no_replay','supplier_calls':0,'database_writes':0,'reason':'retained_child_failed_no_replay'}
            (target/'reservation.json').write_text(json.dumps(reservation));(target/'result.json').write_text(json.dumps(prior))
            (target/'retained-raw.ndjson').write_text('{"kind":"observation","operator_link":"private raw"}\n{bad-partial-json')
            before={p.name:p.read_bytes() for p in target.iterdir()}
            def fail(reason):raise ValueError(reason)
            space={'private':private,'project':project,'payload':{'target_operation_id':OP},'re':__import__('re'),
                   'json':json,'hashlib':hashlib,'fail':fail,
                   'safe_json':lambda path,bound:json.loads(path.read_text()),
                   'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound}
            exec(m.REMOTE_HANDLER,space)
            result=space['retained_readback']()
            self.assertEqual('reconciled_read_only',result['state'])
            self.assertEqual({'observation':1,'invalid_json':1},result['raw_row_kinds'])
            self.assertNotIn('database_schema',result)
            self.assertNotIn('private raw',json.dumps(result))
            self.assertEqual(before,{p.name:p.read_bytes() for p in target.iterdir()})

    def test_closed_parser_keeps_old_modes_and_rejects_scope_expansion(self):
        c=core();original=c.parse_command(c.PREFIX+SHA+' local-readback int-andromeda-read-local-test-v1 1 4 2026-10-21 2026-10-22 7 2 AI 0')
        m.register_parser(c)
        self.assertEqual(original,c.parse_command(c.PREFIX+SHA+' local-readback int-andromeda-read-local-test-v1 1 4 2026-10-21 2026-10-22 7 2 AI 0'))
        for mode,cohort in m.MODES.items():
            op=OP if mode=='match-retained-intake' else 'int-andromeda-match-retained-readback-20261020-v1' if mode=='match-retained-readback' else QUOTA_OP
            command=c.PREFIX+SHA+' '+mode+' '+op+' '+cohort+(' '+OP if mode=='match-retained-readback' else '')
            parsed=c.parse_command(command)
            self.assertEqual(cohort,parsed['cohort'])
            for bad in (command+' extra',command.replace(cohort,'arbitrary-sql'),command.replace(op,op+'-replay')):
                with self.assertRaises(ValueError):c.parse_command(bad)
            with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}):
                with self.assertRaises(ValueError):c.parse_command(command)

    def test_exact_source_bundle_and_tamper_rejection(self):
        bundle,hashes=m.bundle_source(SOURCE)
        self.assertEqual(m.SOURCE_HASHES,hashes)
        with tarfile.open(fileobj=io.BytesIO(bundle)) as archive:
            self.assertEqual(set(m.SOURCE_FILES)|{'manifest.json'},set(archive.getnames()))
        with tempfile.TemporaryDirectory() as td:
            for rel in m.SOURCE_FILES:
                target=Path(td)/rel;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes((SOURCE/rel).read_bytes())
            target.write_bytes(target.read_bytes()+b'\n')
            with self.assertRaisesRegex(ValueError,'reviewed_source_changed'):m.bundle_source(td)

    def test_stock_remote_registration_cannot_dispatch_supplier_collector(self):
        for mode,cohort in m.MODES.items():
            c=core();m.register_parser(c)
            op=OP if mode=='match-retained-intake' else 'int-andromeda-match-retained-readback-20261020-v1' if mode=='match-retained-readback' else QUOTA_OP
            cmd=c.parse_command(c.PREFIX+SHA+' '+mode+' '+op+' '+cohort+(' '+OP if mode=='match-retained-readback' else ''))
            with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,cmd)
            ast.parse(c.REMOTE)
            self.assertEqual(2,c.REMOTE.count("if mode not in ('match-retained-intake','match-tourvisor-quota-policy','match-retained-readback','reconcile',"))
            self.assertIn("globals().get('retained_reserved') is True",c.REMOTE)
            self.assertNotIn("if 'op' in globals() and op.exists():",c.REMOTE)
            self.assertEqual(CONTROL,cmd['match_retained_control_sha'])
            self.assertIn("files!="+repr(m.SOURCE_HASHES),c.REMOTE)

    def test_completion_rejects_wrong_identity_writes_or_digest(self):
        cmd={'mode':'match-retained-intake','source_sha':SHA,'operation_id':OP,'cohort':'common4-retained'}
        data={**cmd,'control_source_sha':CONTROL,'state':'captured_and_projected_read_only',
              'supplier_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
        result={**cmd,'status':'complete','production_unchanged':True,
                'supplier_calls':0,'database_writes':0,'mapping_writes':0,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()}}
        self.assertTrue(m.completed_result(cmd,result,CONTROL))
        for change in ({'supplier_calls':1},{'database_writes':True},{'source_sha':'c'*40},{'production_unchanged':False}):
            other=copy.deepcopy(result);other.update(change);self.assertFalse(m.completed_result(cmd,other,CONTROL))
        other=copy.deepcopy(result);other['match_retained']['data']['safe_to_write_now']=True
        self.assertFalse(m.completed_result(cmd,other,CONTROL))


class QuotaTest(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
        self.now=dt.datetime(2026,10,20,12,tzinfo=dt.timezone.utc)
        self.quota=q.Quota(Path(self.tmp.name),lambda:self.now)
        self.quota.root.mkdir(parents=True)
        self.original={'provider':'tourvisor-anex','provider_day':self.quota.day,'owner_daily_limit':3000,
                       'physical_http_attempts':4980,'tariff_search_units':120,
                       'operations':{'old':{'status':'unknown_no_replay','physical_http_attempts':3}},
                       'baseline_status':'not_measured','custom_protected':{'retained':True}}
        self.quota.path.write_bytes(q.encoded(self.original))

    def test_policy_preserves_inode_all_usage_operations_and_unknown(self):
        inode=self.quota.path.stat().st_ino;receipt=self.quota.apply_policy();after=json.loads(self.quota.path.read_text())
        for k,v in self.original.items():
            if k!='owner_daily_limit':self.assertEqual(v,after[k])
        self.assertEqual(inode,self.quota.path.stat().st_ino)
        self.assertEqual(20,receipt['local_remaining_upper_bound'])
        self.assertEqual('not_measured',receipt['global_usage'])
        self.assertEqual(5000,after['owner_daily_limit'])
        self.assertEqual(5000,after['physical_http_daily_limit'])

    def test_concurrent_reservations_never_cross_daily_physical_cap(self):
        self.quota.apply_policy()
        def reserve(i):
            try:self.quota.reserve(OP,i,'search_results');return True
            except ValueError as e:
                self.assertEqual('quota_exhausted',str(e));return False
        with ThreadPoolExecutor(max_workers=6) as pool:outcomes=list(pool.map(reserve,range(1,41)))
        self.assertEqual(20,sum(outcomes))
        after=json.loads(self.quota.path.read_text())
        self.assertEqual(5000,after['physical_http_attempts']);self.assertEqual(120,after['tariff_search_units'])
        self.assertEqual(20,len(after['physical_http_reservations']))

    def test_reservation_is_charged_before_unknown_outcome_and_never_replayed(self):
        self.quota.apply_policy();self.quota.reserve(OP,1,'search_start')
        state=json.loads(self.quota.path.read_text())
        self.assertEqual(121,state['tariff_search_units'])
        self.assertEqual('reserved_unknown_no_replay',state['physical_http_reservations'][OP+':1']['state'])
        self.quota.complete(OP,1,'failed_unknown_no_replay')
        with self.assertRaisesRegex(ValueError,'request_no_replay'):self.quota.reserve(OP,1,'search_start')
        self.assertEqual(4981,json.loads(self.quota.path.read_text())['physical_http_attempts'])

    def test_empty_or_wrong_day_state_is_not_reset(self):
        for data in (b'',q.encoded({**self.original,'provider_day':'2026-10-19'})):
            self.quota.path.write_bytes(data)
            with self.assertRaises((ValueError,KeyError)):self.quota.apply_policy()
            self.assertEqual(data,self.quota.path.read_bytes())

    def test_provider_day_rollover_and_symlink_are_rejected(self):
        self.now+=dt.timedelta(days=1)
        with self.assertRaisesRegex(ValueError,'day_changed'):self.quota.apply_policy()
        self.now-=dt.timedelta(days=1);self.quota.path.unlink();other=Path(self.tmp.name)/'protected';other.write_text('protected');self.quota.path.symlink_to(other)
        with self.assertRaises(OSError):self.quota.apply_policy()
        self.assertEqual('protected',other.read_text())


class ProjectionTest(unittest.TestCase):
    def fixture(self,extras,registry=True):
        scope={'kind':'scope','cohort':'common4-retained','captured_at_utc':'2026-10-20T00:00:00Z',
               'database_snapshot':'repeatable-read-read-only','schema_columns':{'andromeda_hotel_identities':IDENTITY_COLUMNS if registry else []}}
        with tempfile.TemporaryDirectory() as td:
            path=Path(td)/'raw.ndjson';path.write_text(''.join(json.dumps(r,ensure_ascii=False)+'\n' for r in [scope,*extras]))
            return p.project(path)

    def observation(self,link='https://fun-sun.ru/hotel/126268',tv=1546):
        return {'kind':'observation','row':{'id':tv,'hotel_id':tv,'operator_id':25,'operator_name':'FUN&SUN',
                'operator_link':link,'first_seen_at':'2026-10-01','last_seen_at':'2026-10-19'}}

    def test_url_namespace_is_not_accepted_samo_namespace(self):
        result=self.fixture([self.observation()]);edge=result['candidate_edges'][0]
        self.assertEqual('operator_url:fun-sun.ru:hotel-path',edge['namespace'])
        self.assertEqual('needs_independent_namespace_bridge',edge['state'])
        self.assertIs(False,edge['safe_to_write_now']);self.assertEqual(0,result['new_accepted_mappings'])

    def test_conflicting_targets_and_secret_bearing_urls_are_held(self):
        result=self.fixture([self.observation(tv=1546),self.observation(tv=1584)])
        self.assertEqual({'hold_multiple_targets':2},result['candidate_states'])
        for url in ('https://fun-sun.ru/hotel/123?token=secret','https://evil.ru/hotel/123',
                    'https://u:p@fun-sun.ru/hotel/123','https://fun-sun.ru/hotel/123#secret'):
            self.assertEqual([],p.native_link(url))

    def test_missing_registry_is_unknown_and_cannot_be_reported_as_zero_coverage(self):
        result=self.fixture([self.observation()],registry=False)
        self.assertEqual({'needs_current_registry_read':1},result['candidate_states'])
        self.assertIsNone(result['registry_coverage'][0]['accepted_rows'])

    def test_raw_source_integrity_and_exact_namespace_ownership(self):
        raw='{"hotel":12}';source={'namespace':'operator_315','external_key':'12','source_json':raw,'source_sha256':hashlib.sha256(raw.encode()).hexdigest()}
        row={'kind':'legacy_source','old_local_id':736,'tv_hotel_id':1546,'snapshot_sha256':'a'*64,'source':source}
        accepted={'kind':'identity','row':{'supplier_namespace':'operator_315','external_hotel_id':'12','local_hotel_id':1546,'decision_status':'accepted'}}
        result=self.fixture([row,accepted]);self.assertEqual({'already_accepted':1},result['candidate_states'])
        row['source']['source_json']='tampered'
        with self.assertRaisesRegex(ValueError,'source_integrity'):self.fixture([row])


@unittest.skipUnless(os.environ.get('MATCH_RETAINED_NATIVE_CI')=='1','native MySQL contract runs in stock CI')
class NativeMySQLTest(unittest.TestCase):
    def test_actual_read_only_snapshot_and_dml_rejection(self):
        code=r'''define('MATCH_RETAINED_LIBRARY_ONLY',true);require $argv[1];
        $db=new PDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $name='match_retained_test_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
        try{
          $db->exec('CREATE TABLE tour_operator_identity_observations(id INT,hotel_id INT,operator_id INT,operator_name TEXT,operator_link TEXT,country_id INT,first_seen_at DATETIME,last_seen_at DATETIME) ENGINE=InnoDB');
          $db->exec('CREATE TABLE local_tv_legacy_links(old_local_id INT,tv_id INT,snapshot_json LONGTEXT,snapshot_sha256 CHAR(64)) ENGINE=InnoDB');
          $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(80),external_hotel_id VARCHAR(80),local_hotel_id INT,decision_status VARCHAR(40)) ENGINE=InnoDB');
          $db->exec('CREATE TABLE catalog_hotels(id INT,name TEXT,country_id INT,country_name TEXT,region_name TEXT,subregion_name TEXT,latitude DECIMAL(10,7),longitude DECIMAL(10,7),is_active INT) ENGINE=InnoDB');
          $db->exec("INSERT INTO tour_operator_identity_observations VALUES(1,1546,25,'FUN&SUN','https://fun-sun.ru/hotel/126268',4,'2026-10-01','2026-10-19')");
          $source=['namespace'=>'operator_315','external_key'=>'12','source_json'=>'{}','source_sha256'=>hash('sha256','{}')];
          $snapshot=json_encode(['sources'=>[$source]],JSON_THROW_ON_ERROR);$s=$db->prepare('INSERT INTO local_tv_legacy_links VALUES(?,?,?,?)');$s->execute([736,1546,$snapshot,hash('sha256',$snapshot)]);
          $db->exec("INSERT INTO andromeda_hotel_identities VALUES('operator_315','12',1546,'accepted')");
          $db->exec("INSERT INTO catalog_hotels VALUES(1546,'A11',4,'Turkey','Alanya','',36.5,32.0,1)");
          $db->exec("INSERT INTO catalog_hotels VALUES(999999,'Unrelated catalog hotel',4,'Turkey','Alanya','',36.5,32.0,1)");
          $rows=[];$result=MatchRetainedIntakeV1::capture($db,static function($row)use(&$rows){$rows[]=$row;});
          $denied=false;try{MatchRetainedIntakeV1::capture($db,static function($row)use($db){$db->exec('DELETE FROM catalog_hotels');});}catch(PDOException $e){$denied=true;}
          if(!$denied||(int)$db->query('SELECT COUNT(*) FROM catalog_hotels')->fetchColumn()!==2)throw new RuntimeException('read_only_not_enforced');
          echo json_encode(['result'=>$result,'rows'=>$rows,'dml_denied'=>$denied],JSON_THROW_ON_ERROR);
        }finally{if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}'''
        run=subprocess.run(['php','-r',code,str(SOURCE/'scripts/diagnostics/hotel_match_retained_intake_v1.php')],capture_output=True,text=True,timeout=60)
        self.assertEqual(0,run.returncode,run.stderr);data=json.loads(run.stdout)
        self.assertTrue(data['dml_denied']);self.assertEqual({'observation':1,'legacy_source':1,'identity':1,'target':1},data['result']['rows'])
        with tempfile.TemporaryDirectory() as td:
            path=Path(td)/'raw';path.write_text(''.join(json.dumps(r)+'\n' for r in data['rows']))
            result=p.project(path)
            self.assertEqual(1,result['candidate_states']['already_accepted'])
            self.assertEqual(1,result['candidate_states']['needs_independent_namespace_bridge'])


if __name__=='__main__':unittest.main()
