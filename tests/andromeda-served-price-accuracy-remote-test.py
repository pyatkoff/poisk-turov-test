#!/usr/bin/env python3
import importlib.util
import json
import subprocess
import tempfile
from pathlib import Path

path=Path(__file__).resolve().parents[1]/'scripts/diagnostics/andromeda_served_price_accuracy_remote.py'
spec=importlib.util.spec_from_file_location('accuracy_remote',path); module=importlib.util.module_from_spec(spec); spec.loader.exec_module(module)

sample={
 'status':'ok','observed_at':1789551000,'runtime_supports_served_price_observation':True,
 'scan':{'matched_files':12,'completed_checkpoints':10,'observations':8,'no_observation':2,'not_completed':1,'invalid_files':1},
 'accuracy':{'comparable':7,'currency_mismatch':1,'exact':5,'exact_accuracy':0.7143,
   'within_100_bps_accuracy':0.7143,'within_300_bps_accuracy':0.8571,'within_500_bps_accuracy':1.0,'within_1000_bps_accuracy':1.0,
   'mean_relative_delta_bps':143,'p50_relative_delta_bps':0,'p90_relative_delta_bps':500,'max_relative_delta_bps':500},
 'supplier_calls':0,'database_access':False,'remote_writes':0,
}
assert module.validate(sample)==sample
blocked={'status':'blocked','reason':'search_store_missing','supplier_calls':0,'database_access':False,'remote_writes':0}
assert module.validate(blocked)==blocked
bad=dict(sample); bad['search_ref']='private';
try: module.validate(bad); raise AssertionError('private key accepted')
except ValueError: pass
for forbidden in ('curl_','PDO','mysqli_','file_put_contents(','fopen(','unlink(','rename(','mkdir('):
    assert forbidden not in module.PHP, forbidden
for required in ("/_preview/search3-anex-candidate",'.andromeda-private.php','/searches','served_price_observation','supplier_calls','remote_writes'):
    assert required in module.PHP, required
# Execute the actual embedded PHP locally; never import the SSH helper or call main().
with tempfile.TemporaryDirectory(prefix='anytour-accuracy-probe-') as temporary:
    base=Path(temporary).resolve()
    root=base/'anytoour.ru'
    target=root/'_preview/search3-anex-candidate'
    target.mkdir(parents=True)
    (base/'private/searches').mkdir(parents=True)
    (target/'.andromeda-private.php').write_text(
        "<?php return ['catalog_path' => dirname(__DIR__, 3) . '/private/catalog.json'];\n")

    def inspect_runtime():
        result=subprocess.run(
            ['php','-d','allow_url_fopen=0','-r',module.PHP],
            cwd=root,check=True,capture_output=True,text=True,timeout=10)
        value=module.validate(json.loads(result.stdout))
        assert value['status']=='ok'
        assert value['scan']['matched_files']==0 and value['accuracy']['comparable']==0
        return value['runtime_supports_served_price_observation']

    assert inspect_runtime() is False, 'missing source must not report support'
    endpoint=target/'api-andromeda-quote-preview.php'
    endpoint.write_text("<?php throw new RuntimeException('must not execute'); /* served_price_observation */\n")
    assert inspect_runtime() is True, 'real quote-preview endpoint was not inspected'
    endpoint.write_text("<?php /* older runtime without the observation */\n")
    assert inspect_runtime() is False, 'endpoint presence alone is not support'
    (target/'api-andromeda-selected-quote.php').write_text("<?php /* served_price_observation */\n")
    assert inspect_runtime() is False, 'unused endpoint name must not establish runtime support'

print('Andromeda remote served-price accuracy: sanitized/read-only contract and actual endpoint probe PASS')
