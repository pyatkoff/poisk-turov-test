import hashlib
import importlib.util
import json
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
MODULE_PATH = ROOT / 'scripts/diagnostics/andromeda_package_checkpoint_read.py'
spec = importlib.util.spec_from_file_location('checkpoint_read', MODULE_PATH)
mod = importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)

REF = '7' * 64
OFFER = 'offer_' + '8' * 64
SELECTION = {'provider':'andromeda','search_ref':REF,'generation':1,'page':1,'offer_ref':OFFER,
             'hotel_scope':None,'operator_ref':'342','local_id':46673}
REQUEST = {'version':1,'operation':'capture-selected-retained-offer-1717','runtime_source':mod.RUNTIME_SOURCE,
           'local_country_id':4,'selection':SELECTION}


def tree_bytes(root):
    return {str(p.relative_to(root)): p.read_bytes() for p in root.rglob('*') if p.is_file() and not p.is_symlink()}


def php_json(root, request):
    run = subprocess.run(['php','-d','allow_url_fopen=0','-d','disable_functions=curl_init,curl_exec,fsockopen,pfsockopen,stream_socket_client',
                          '-r',mod.remote_source()], cwd=root, input=json.dumps(request,separators=(',',':')),
                         text=True,capture_output=True,timeout=5)
    assert run.returncode == 0 and run.stderr == ''
    return json.loads(run.stdout)


def package_path(searches, created=123456):
    return searches / f'{REF}-{created}-1-{OFFER}-package.json'


def write_record(path, status, *, source=None, context=None, private=None, flags=False):
    record = {'version':1,'status':status,'context':dict(SELECTION) if context is None else context,
              'created_at':123457,'criteria_sha256':'a'*64,'supplier_offer_sha256':'b'*64}
    if private is not None:
        record['private_package'] = private
        wire = json.dumps(private,separators=(',',':'),ensure_ascii=False)
        record['package_sha256'] = hashlib.sha256(wire.encode()).hexdigest()
    if flags:
        record.update(identity_verified=False,quote_verified=False,selection_enabled=False)
    path.write_text(json.dumps({'source':source or mod.RUNTIME_SOURCE,'record':record},separators=(',',':')))


def test_fixture_states_and_no_mutation():
    assert 'gateway.samo.ru' not in mod.remote_source()
    assert 'curl_' not in mod.remote_source()
    assert 'broninit' not in mod.remote_source()
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp) / 'account/www/anytoour.ru'; target = root / '_preview/search3-anex-candidate'
        private = Path(tmp) / 'private'; searches = private / 'searches'
        target.mkdir(parents=True); searches.mkdir(parents=True)
        (target / '.andromeda-private.php').write_text("<?php return ['enabled'=>true,'catalog_path'=>" + repr(str(private/'catalog.json')) + "];\n")
        first = {'status':'complete','store':{'version':1,'search_ref':REF,'generation':1,'created_at':123456}}
        (searches / f'{REF}-1.json').write_text(json.dumps(first,separators=(',',':')))
        before = tree_bytes(Path(tmp))
        r = php_json(root, REQUEST)
        assert mod.validate_result(r)['checkpoint_status'] == 'absent'
        assert tree_bytes(Path(tmp)) == before

        path = package_path(searches)
        for status in ('reserved','unknown'):
            write_record(path,status)
            before = tree_bytes(Path(tmp)); r = mod.validate_result(php_json(root,REQUEST)); after = tree_bytes(Path(tmp))
            assert r['checkpoint_status'] == status and r['package_present'] is False and r['package_hash_valid'] is None
            assert r['supplier_effect'] == ('unknown' if status == 'unknown' else 'not_determined')
            assert r['automatic_retry'] is False and r['supplier_calls'] == r['database_writes'] == 0 and r['capture_invoked'] is False
            assert before == after; path.unlink()

        private_claim = {'claimDocument':[{'catalogKey':'PRIVATE-CATALOG-ID'}],'buyer':{'email':'PRIVATE@EXAMPLE.TEST'}}
        for status in ('captured','stale'):
            write_record(path,status,private=private_claim,flags=True)
            before = tree_bytes(Path(tmp)); r = mod.validate_result(php_json(root,REQUEST)); after = tree_bytes(Path(tmp))
            assert r['checkpoint_status'] == status and r['package_present'] is True and r['package_hash_valid'] is True
            assert r['verification_flags'] == 'all_false' and r['supplier_effect'] == 'response_retained'
            assert 'PRIVATE' not in json.dumps(r) and before == after; path.unlink()


def test_invalid_or_mismatched_checkpoint_fails_closed():
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp) / 'account/www/anytoour.ru'; target = root / '_preview/search3-anex-candidate'
        private = Path(tmp) / 'private'; searches = private / 'searches'
        target.mkdir(parents=True); searches.mkdir(parents=True)
        (target / '.andromeda-private.php').write_text("<?php return ['enabled'=>true,'catalog_path'=>" + repr(str(private/'catalog.json')) + "];\n")
        (searches / f'{REF}-1.json').write_text(json.dumps({'status':'complete','store':{'version':1,'search_ref':REF,'generation':1,'created_at':123456}},separators=(',',':')))
        path = package_path(searches)
        bad_context = dict(SELECTION, operator_ref='999')
        write_record(path,'unknown',context=bad_context)
        assert php_json(root,REQUEST)['reason'] == 'checkpoint_context_mismatch'; path.unlink()
        write_record(path,'unknown',source='0'*40)
        assert php_json(root,REQUEST)['reason'] == 'checkpoint_invalid'; path.unlink()
        write_record(path,'captured',private={'claimDocument':[]},flags=True)
        envelope=json.loads(path.read_text()); envelope['record']['package_sha256']='0'*64; path.write_text(json.dumps(envelope,separators=(',',':')))
        assert php_json(root,REQUEST)['reason'] == 'checkpoint_invalid'; path.unlink()
        write_record(path,'unknown',private={'PRIVATE':'should-never-exist'})
        assert php_json(root,REQUEST)['reason'] == 'checkpoint_invalid'; path.unlink()
        write_record(path,'captured',private={'claimDocument':[]},flags=True)
        envelope=json.loads(path.read_text()); envelope['record']['quote_verified']=True; path.write_text(json.dumps(envelope,separators=(',',':')))
        assert php_json(root,REQUEST)['reason'] == 'checkpoint_invalid'; path.unlink()
        outside = Path(tmp) / 'outside.json'; write_record(outside,'unknown'); path.symlink_to(outside)
        assert php_json(root,REQUEST)['reason'] == 'checkpoint_invalid'


def test_request_validation_and_result_allowlist():
    assert mod.validate_request(json.loads(json.dumps(REQUEST))) == REQUEST
    for mutate in (
        lambda x: x.update(runtime_source='0'*40),
        lambda x: x.update(local_country_id='4'),
        lambda x: x['selection'].update(provider='anex'),
        lambda x: x['selection'].update(search_ref='bad'),
        lambda x: x['selection'].update(offer_ref='bad'),
        lambda x: x['selection'].update(page=0),
        lambda x: x['selection'].update(operator_ref='bad\nvalue'),
        lambda x: x['selection'].update(extra='PRIVATE'),
    ):
        value=json.loads(json.dumps(REQUEST)); mutate(value)
        try: mod.validate_request(value)
        except ValueError: pass
        else: raise AssertionError('invalid request accepted')
    safe={'status':'ok','checkpoint_status':'unknown','context_match':True,'source_match':True,'package_present':False,
          'package_hash_valid':None,'verification_flags':'absent','supplier_effect':'unknown','supplier_calls':0,'database_writes':0,
          'capture_invoked':False,'automatic_retry':False}
    assert mod.validate_result(safe)==safe
    for key,value in [('supplier_calls',1),('database_writes',1),('capture_invoked',True),('automatic_retry',True),('supplier_effect','captured')]:
        bad=dict(safe); bad[key]=value
        try: mod.validate_result(bad)
        except ValueError: pass
        else: raise AssertionError('unsafe result accepted')


if __name__ == '__main__':
    test_fixture_states_and_no_mutation(); test_invalid_or_mismatched_checkpoint_fails_closed(); test_request_validation_and_result_allowlist()
    print('Andromeda package checkpoint read: PASS; supplier/network/DB writes/capture=0.')
