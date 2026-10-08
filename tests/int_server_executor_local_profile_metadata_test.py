from __future__ import annotations

import ast
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import types
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'scripts/deploy/int_server_executor_local_profile_metadata.py'
spec = importlib.util.spec_from_file_location('local_metadata_contract', SCRIPT)
assert spec and spec.loader
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
PHP = ROOT / m.RUNNER
SHA = 'a' * 40


def core():
    def original(body):
        if body == 'old-command':
            return {'old': True}
        raise ValueError('old_denied')
    out = types.SimpleNamespace(PREFIX='/run-int-server-v1 ', SHA_RE=re.compile(r'[a-f0-9]{40}'),
                                parse_command=original, REMOTE='original')
    m.register_parser(out)
    return out


class MetadataParserTest(unittest.TestCase):
    def test_only_fixed_bounded_command_is_admitted(self):
        c = core()
        out = c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        self.assertEqual(out, {'source_sha': SHA, 'mode': m.MODE, 'operation_id': m.OPERATION,
                              'batch': m.BATCH, 'maximum_writes': 0, 'provider_http_calls': 0,
                              'maximum_metadata_profiles': 250, 'metadata_only': True})
        for tail in (m.BATCH + ' --apply', m.BATCH + ' 1', 'other', m.BATCH + '-v2'):
            with self.subTest(tail=tail), self.assertRaises(ValueError):
                c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.OPERATION} {tail}')
        for operation in (m.OPERATION + '-v2', m.OPERATION.replace('metadata', 'recovery')):
            with self.assertRaises(ValueError):
                c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {operation} {m.BATCH}')

    def test_old_parser_and_stop_are_not_overridden(self):
        c = core()
        self.assertEqual(c.parse_command('old-command'), {'old': True})
        with self.assertRaisesRegex(ValueError, 'old_denied'):
            c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} int-andromeda-local-profile-mass-recovery-4191-20261008-v1 local4191-mass-recovery-20261008')

    def test_activation_rejects_extra_authority_and_boolean_numbers(self):
        c = core()
        command = c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        plan = types.SimpleNamespace(remote_with_plan=mock.Mock(side_effect=AssertionError('must not generate')))
        for key, value in (('maximum_writes', 1), ('maximum_writes', False), ('provider_http_calls', True),
                           ('maximum_metadata_profiles', 251), ('metadata_only', 1), ('apply', True)):
            with self.subTest(key=key, value=value), self.assertRaises(ValueError):
                m.activate(c, command | {key: value}, plan)
        plan.remote_with_plan.assert_not_called()
        self.assertEqual(c.REMOTE, 'original')

    def test_stock_registration_shape_compiles_without_old_runner_invocation(self):
        c = core()
        command = c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        old_files = ('old.php',)
        old_literal = '{' + ', '.join(repr(v) for v in sorted(old_files)) + '}'
        source = ("def run_local_profile_plan_4191(stage):\n    return None\n"
                  "if not isinstance(files,dict) or set(files)!=" + old_literal + ": fail('local_profile_manifest')\n")
        plan = types.SimpleNamespace(BUNDLE_FILES=old_files, remote_with_plan=lambda _: source)
        m.activate(c, command, plan)
        ast.parse(c.REMOTE)
        self.assertIn("return run_local_profile_metadata_4191(stage)", c.REMOTE)
        self.assertIn("'--metadata-only'", c.REMOTE)
        self.assertNotIn("'--plan-only'", c.REMOTE)
        self.assertNotIn('local_profile_mass_recovery_plan_4191.php', m.BUNDLE_FILES)
        self.assertIs(c.bundle_source, m.bundle_source)

    def test_php_has_no_supplier_source_or_writer_and_private_roster_precedes_connection(self):
        text = PHP.read_text()
        for token in ('catalog_hotel_details', '->plan(', '->apply(', 'lpp_main(', 'lpm_snapshot(',
                      'local-mass3-plan.json', 'curl_exec(', 'INSERT INTO', 'UPDATE anytour', 'DELETE FROM'):
            self.assertNotIn(token, text)
        observe = text.split('function lmd_observe(', 1)[1]
        self.assertLess(observe.index('local-metadata-input.json'), observe.index('$connect()'))
        self.assertIn('SET TRANSACTION READ ONLY', text)
        self.assertIn("'safe_to_plan'=>false", text)
        self.assertIn("'phase3_state'=>'UNKNOWN_NO_REPLAY'", text)


PHP_FIXTURE = r'''
function ok(bool $v, string $label): void { if (!$v) throw new RuntimeException('fixture:'.$label); }
function fails(callable $fn, string $label): void {
    try { $fn(); } catch (Throwable) { return; } throw new RuntimeException('expected:'.$label);
}
function currentRow(int $own,int $local,array $profile=[]): array {
    $raw = $profile === [] ? '{}' : lpp_json($profile);
    $proof = lpp_json(['schema_version'=>1,'accepted_local_hotel_id'=>$local,'canonical_hotel_id'=>$own,
        'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('d',64)]);
    return ['id'=>$own,'is_active'=>1,'revision'=>1,'profile_json'=>$raw,'profile_sha256'=>hash('sha256',$raw),
        'local_id'=>(string)$local,'alias_acquired_via'=>'canonical_local_alias_v1',
        'alias_source_json'=>$proof,'alias_source_sha256'=>hash('sha256',$proof),'prior_content_operations'=>0];
}
function indexFixture(): array {
    $counts=['D1_OVERLAP_HELD'=>70,'HISTORICAL_366_HELD'=>366,'PLAN_BOUND_DEFERRED'=>9142,
        'PREDECESSOR_2000_HELD'=>2000,'PRIOR_OR_EDITORIAL_HELD'=>714,'RETAINED_DELTA_PREPARED'=>130,
        'SCREENED_FIELDS_PRESENT'=>1707,'SOURCE_MISSING'=>1868,'SOURCE_PROVENANCE_HELD'=>2];
    ksort($counts); $rows=[];$id=100000;
    foreach($counts as $state=>$count) for($i=0;$i<$count;$i++) {
        $own=++$id;$local=$own+200000;$current=currentRow($own,$local);
        $rows[]=['anytourHotelId'=>$own,'localHotelId'=>$local,'state'=>$state,'expectedRevision'=>1,
            'expectedProfileSha256'=>$current['profile_sha256'],'expectedAliasSha256'=>$current['alias_source_sha256'],
            'userSearches'=>$i%3,'observations'=>$i%5,'lastSeenAt'=>'2026-10-01 12:00:00'];
    }
    return ['schema_version'=>1,'operation_id'=>LMD_PARENT,'batch'=>'local4191-mass-retained2-20261002',
        'source_sha'=>'a54255507643501abdeca150aeae19b84cb586f6',
        'control_source_sha'=>'00cc9b3ba28319c85282a993c6ca0d57558b604e',
        'active_profiles'=>15999,'source_plans_prepared'=>2000,'safe_to_apply'=>false,
        'classification_counts'=>$counts,'rows'=>$rows];
}
function d1Fixture(): array { return ['state'=>'verified_terminal_manifest','ownIds'=>range(1,80),'legacyIds'=>range(800001,800080)]; }
class ReadProbe extends PDO {
    public array $trace=[]; private bool $active=false;
    public function __construct(public array $rows) {}
    public function getAttribute(int $attribute): mixed { return 'mysql'; }
    public function inTransaction(): bool { return $this->active; }
    public function exec(string $statement): int|false {
        ok(in_array($statement,['SET TRANSACTION ISOLATION LEVEL REPEATABLE READ','SET TRANSACTION READ ONLY'],true),'only_read_settings');
        $this->trace[]=$statement;return 0;
    }
    public function beginTransaction(): bool { $this->active=true;$this->trace[]='BEGIN';return true; }
    public function commit(): bool { $this->active=false;$this->trace[]='COMMIT';return true; }
    public function rollBack(): bool { $this->active=false;$this->trace[]='ROLLBACK';return true; }
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        ok(str_starts_with($query,'SELECT h.id') && !str_contains($query,'catalog_hotel_details'),'metadata_sql');
        $this->trace[]=$query;return new RowProbe($this);
    }
}
class RowProbe extends PDOStatement {
    private int $i=0;
    public function __construct(private ReadProbe $db) {}
    public function execute(?array $params=null): bool {
        ok(count($params??[])===250 && count(array_unique($params))===250,'exact_sql_scope');$this->i=0;return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {
        return $this->db->rows[$this->i++]??false;
    }
    public function closeCursor(): bool { return true; }
}
$index=indexFixture();$d1=d1Fixture();$scope=lmd_input($index,$d1);
'''


class MetadataNativePhpTest(unittest.TestCase):
    def php(self, code):
        executable = shutil.which('php')
        self.assertIsNotNone(executable, 'Native PHP is required for this stock contract check')
        program = '<?php\nrequire ' + json.dumps(str(PHP)) + ';\n' + PHP_FIXTURE + '\n' + code + '\necho "PASS\\n";'
        run = subprocess.run([executable], input=program, text=True, capture_output=True, timeout=40)
        self.assertEqual(run.returncode, 0, run.stdout + run.stderr)
        self.assertEqual(run.stdout.strip(), 'PASS')
        self.assertFalse(run.stderr)

    def test_bounded_historical_priority_and_exclusions(self):
        self.php(r'''
ok(count($scope)===250,'cap');$byId=array_column($index['rows'],null,'anytourHotelId');
foreach($scope as $s) ok($byId[$s['anytourHotelId']]['state']==='PLAN_BOUND_DEFERRED','not_consumed_or_source_missing');
$excluded=$scope[0]['anytourHotelId'];$d1['ownIds'][0]=$excluded;
$again=lmd_input($index,$d1);ok(!in_array($excluded,array_column($again,'anytourHotelId'),true),'d1_before_read');
ok($byId[$scope[0]['anytourHotelId']]['userSearches']===2,'historical_priority');
''')

    def test_unknown_d1_corrupt_parent_and_duplicate_identity_fail_closed(self):
        self.php(r'''
$bad=$d1;$bad['state']='unknown_held';fails(fn()=>lmd_input($index,$bad),'d1');
$bad=$index;$bad['rows'][1]=$bad['rows'][0];fails(fn()=>lmd_input($bad,$d1),'duplicate');
$bad=$index;array_pop($bad['rows']);fails(fn()=>lmd_input($bad,$d1),'incomplete');
$bad=$index;$bad['classification_counts']['SOURCE_MISSING']++;fails(fn()=>lmd_input($bad,$d1),'count');
$bad=$index;$bad['source_sha']=str_repeat('a',40);fails(fn()=>lmd_input($bad,$d1),'producer');
''')

    def test_current_metadata_is_real_read_only_but_never_source_admission(self):
        self.php(r'''
$rows=[];foreach($scope as $s)$rows[]=currentRow($s['anytourHotelId'],$s['localHotelId']);
$db=new ReadProbe($rows);$current=lmd_read($db,$scope);$audit=lmd_classify($scope,$current,$d1);
ok($audit['profiles_read']===250 && $audit['aliases_validated']===250,'read_counts');
ok($audit['classification_counts']===['PHASE3_INDEPENDENCE_UNPROVEN'=>250],'unknown_not_zero');
foreach(LPP_FIELDS as $field)ok($audit['missing_field_counts'][$field]===250,'actual_missing_mask');
foreach($audit['rows'] as $row){ok(!$row['safeToPlan']&&!$row['safeToApply']&&!$row['sourceMaterialEvaluated'],'no_authority');
 ok(!isset($row['profile_json'])&&!isset($row['description'])&&!isset($row['images']),'no_raw_content');}
ok($db->trace[1]==='SET TRANSACTION READ ONLY' && end($db->trace)==='COMMIT','readonly_transaction');
''')

    def test_editorial_identity_integrity_and_unversioned_drift_are_separate(self):
        self.php(r'''
$rows=[];foreach($scope as $s)$rows[$s['anytourHotelId']]=[currentRow($s['anytourHotelId'],$s['localHotelId'])];
$ids=array_column($scope,'anytourHotelId');
$rows[$ids[0]][0]['revision']=2;
$rows[$ids[1]][0]['alias_source_sha256']=str_repeat('0',64);
$rows[$ids[2]][0]['profile_sha256']=str_repeat('0',64);
$rows[$ids[3]]=[currentRow($ids[3],999999)];
$rows[$ids[4]]=[currentRow($ids[4],$scope[4]['localHotelId'],['description'=>'fixture editorial'])];
$rows[$ids[5]]=[currentRow($ids[5],$d1['legacyIds'][0])];unset($rows[$ids[6]]);
$a=lmd_classify($scope,$rows,$d1);$c=$a['classification_counts'];
foreach(['PRIOR_OR_EDITORIAL_HELD','ALIAS_HELD','PROFILE_INTEGRITY_HELD','CURRENT_LINK_DRIFT_HELD',
 'CURRENT_METADATA_DRIFT_HELD','D1_OVERLAP_HELD','PROFILE_UNAVAILABLE'] as $key)ok($c[$key]===1,$key);
ok($c['PHASE3_INDEPENDENCE_UNPROVEN']===243,'remaining');
''')

    def test_durable_input_is_written_and_read_back_before_connection_no_replay(self):
        self.php(r'''
$dir=sys_get_temp_dir().'/local-meta-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$identity=['schema_version'=>1,'operation_id'=>LMD_OPERATION,'batch'=>LMD_BATCH,'source_sha'=>str_repeat('a',40),
 'control_source_sha'=>str_repeat('b',40),'parent_sha256'=>LMD_PARENT_HASH];
$calls=0;$db=new ReadProbe([]);
$connect=function()use($dir,$scope,&$calls,$db){++$calls;$p=$dir.'/local-metadata-input.json';
 ok(is_file($p),'roster_before_connection');$input=json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);
 ok($input['scope']===$scope,'exact_input');return $db;};
try{$receipt=lmd_observe($dir,$identity,$scope,$d1,$connect);
 ok($calls===1 && $receipt['profiles_read']===0 && !$receipt['safe_to_apply'],'receipt');
 ok(hash_file('sha256',$dir.'/local-metadata-input.json')===$receipt['input_sha256'],'input_digest');
 ok(hash_file('sha256',$dir.'/local-metadata.json')===$receipt['metadata_sha256'],'private_digest');
 fails(fn()=>lmd_observe($dir,$identity,$scope,$d1,$connect),'no_replay');ok($calls===1,'no_second_connection');
}finally{foreach(glob($dir.'/*') as $p)unlink($p);rmdir($dir);}
''')

    def test_connection_failure_retains_input_and_prevents_blind_retry(self):
        self.php(r'''
$dir=sys_get_temp_dir().'/local-meta-'.bin2hex(random_bytes(8));mkdir($dir,0700);$calls=0;
$connect=function()use(&$calls){++$calls;throw new RuntimeException('synthetic connection failure');};
try{fails(fn()=>lmd_observe($dir,[],$scope,$d1,$connect),'first_failure');
 ok(is_file($dir.'/local-metadata-input.json')&&!is_file($dir.'/local-metadata-receipt.json'),'input_survives');
 fails(fn()=>lmd_observe($dir,[],$scope,$d1,$connect),'second_failure');ok($calls===1,'not_retried');
}finally{foreach(glob($dir.'/*') as $p)unlink($p);rmdir($dir);}
''')

    def test_present_core_and_optional_fields_do_not_imply_independence(self):
        self.php(r"""
$profile=['description'=>'fixture','primaryImage'=>'fixture','images'=>['fixture'],'address'=>'fixture',
 'place'=>'fixture','build'=>'fixture','repair'=>'fixture','square'=>'fixture',
 'hotelInformation'=>['infrastructure'=>['fixture'],'services'=>['fixture'],'meals'=>['fixture'],'roomTypes'=>'fixture']];
$rows=[];foreach($scope as &$s){$r=currentRow($s['anytourHotelId'],$s['localHotelId'],$profile);
 $s['expectedProfileSha256']=$r['profile_sha256'];$rows[$s['anytourHotelId']]=[$r];}unset($s);
$a=lmd_classify($scope,$rows,$d1);ok($a['classification_counts']===['PHASE3_INDEPENDENCE_UNPROVEN'=>250],'full_not_independent');
ok(json_encode($a['missing_field_counts'])==='{}','empty_map_contract');
""")

    def test_out_of_scope_sql_result_rolls_back_read_transaction(self):
        self.php(r"""
$db=new ReadProbe([currentRow(999999,888888)]);fails(fn()=>lmd_read($db,$scope),'foreign_row');
ok(end($db->trace)==='ROLLBACK' && !$db->inTransaction(),'read_rolled_back');
""")


class MetadataReceiptTest(unittest.TestCase):
    def run_handler(self, mutate=None, process=None):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory)
            runner=root/m.RUNNER;runner.parent.mkdir(parents=True);runner.write_text('<?php // synthetic fixture')
            identity={'schema_version':1,'operation_id':m.OPERATION,'batch':m.BATCH,'source_sha':SHA,
                      'control_source_sha':'b'*40,'parent_sha256':'aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1'}
            scope=[{'anytourHotelId':i,'localHotelId':i+1000,'expectedRevision':1,
                    'expectedProfileSha256':'c'*64,'expectedAliasSha256':'d'*64} for i in range(1,251)]
            rows=[{'anytourHotelId':i,'requestedLocalHotelId':i+1000,'state':'PROFILE_UNAVAILABLE',
                   'missingFields':[],'safeToPlan':False,'safeToApply':False,'sourceMaterialEvaluated':False}
                  for i in range(1,251)]
            input_data=identity|{'scope':scope,'priority_basis':'historical_mass2_demand','safe_to_plan':False,'safe_to_apply':False}
            private=identity|{'rows':rows,'read_at':'2026-10-08 00:00:00','phase3_state':'UNKNOWN_NO_REPLAY',
                              'safe_to_plan':False,'safe_to_apply':False}
            data=identity|{'state':'completed_read_only','requested_profiles':250,'profiles_read':0,
                           'metadata_screened':0,'aliases_validated':0,'classification_counts':{'PROFILE_UNAVAILABLE':250},
                           'missing_field_counts':{},'read_at':private['read_at'],'phase3_state':'UNKNOWN_NO_REPLAY',
                           'safe_to_plan':False,'safe_to_apply':False,'source_plans_prepared':0,'source_cards_read':0,
                           'provider_http_calls':0,'database_writes':0,'profile_writes':0,'mapping_writes':0,'schema_writes':0}
            if mutate:mutate(data,private,input_data)
            def store(name,value):
                raw=json.dumps(value).encode();(root/name).write_bytes(raw);return hashlib.sha256(raw).hexdigest()
            data['input_sha256']=store('local-metadata-input.json',input_data)
            private['input_sha256']=data['input_sha256']
            data['metadata_sha256']=store('local-metadata.json',private)
            store('local-metadata-receipt.json',data)
            outcome=types.SimpleNamespace(returncode=0,stderr='',stdout=json.dumps(data))
            if process:process(outcome,root)
            command=core().parse_command(f'/run-int-server-v1 {SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
            def fail(reason):raise ValueError(reason)
            ns={'payload':command|{'local_profile_control_sha':'b'*40},'operation':m.OPERATION,
                'source':SHA,'project':root,'op':root,'re':re,'os':__import__('os'),
                'json':json,'hashlib':hashlib,'fail':fail,
                'safe_file':lambda path,limit:path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=limit,
                'safe_json':lambda path,limit:json.loads(path.read_text()),
                'subprocess':types.SimpleNamespace(run=mock.Mock(return_value=outcome))}
            exec(compile(m.REMOTE_HANDLER,'<metadata-handler-test>','exec'),ns)
            result=ns['run_local_profile_metadata_4191'](root)
            args=ns['subprocess'].run.call_args
            self.assertEqual(args.args[0][-1],'--metadata-only')
            self.assertIn('allow_url_fopen=0',args.args[0])
            self.assertFalse(result['safe_to_plan'])
            return result

    def test_empty_current_result_has_valid_typed_zero_receipt(self):
        result=self.run_handler()
        self.assertEqual(result['missing_field_counts'],{})
        self.assertEqual(result['profiles_read'],0)

    def test_forged_authority_counts_and_private_identity_are_rejected(self):
        mutations=[lambda d,p,i:d.update(safe_to_apply=True),
                   lambda d,p,i:d.update(source_cards_read=1),
                   lambda d,p,i:d.update(profiles_read=1),
                   lambda d,p,i:d.update(database_writes=False),
                   lambda d,p,i:p['rows'][0].update(secret='never publish'),
                   lambda d,p,i:i['scope'][0].update(localHotelId=999),
                   lambda d,p,i:p['rows'].reverse()]
        for mutate in mutations:
            with self.subTest(mutation=mutate),self.assertRaises(ValueError):self.run_handler(mutate=mutate)

    def test_missing_corrupt_or_failed_terminal_is_not_a_success(self):
        cases=[lambda o,r:(r/'local-metadata.json').write_text('{}'),
               lambda o,r:(r/'local-metadata-receipt.json').unlink(),
               lambda o,r:setattr(o,'returncode',2),
               lambda o,r:setattr(o,'stdout','{}'),
               lambda o,r:setattr(o,'stderr','synthetic failure')]
        for process in cases:
            with self.subTest(process=process),self.assertRaises(ValueError):self.run_handler(process=process)


class MetadataStockWrapperTest(unittest.TestCase):
    def test_real_wrapper_preserves_stop_and_builds_fixed_metadata_remote(self):
        path = ROOT / 'scripts/deploy/int_server_executor_anex_secret_transport.py'
        spec = importlib.util.spec_from_file_location('metadata_actual_wrapper', path)
        self.assertIsNotNone(spec)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        c = module.core
        stopped = module.local_profile_recovery
        with self.assertRaisesRegex(ValueError, 'blocked_safety_stop'):
            c.parse_command(f'{c.PREFIX}{SHA} {stopped.MODE} {stopped.OPERATION} {stopped.BATCH}')
        command = c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        module.activate_local_plan(command)
        compile(c.REMOTE, '<metadata-real-stock-remote>', 'exec')
        self.assertIn('run_local_profile_metadata_4191', c.REMOTE)
        self.assertNotIn(m.MODE, module.SUPPLIER_SLOT_MODES)


if __name__ == '__main__':
    unittest.main()
