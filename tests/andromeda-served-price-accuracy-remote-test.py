#!/usr/bin/env python3
import importlib.util
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
print('Andromeda remote served-price accuracy: sanitized/read-only contract PASS')
