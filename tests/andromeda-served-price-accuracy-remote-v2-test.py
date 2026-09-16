#!/usr/bin/env python3
import importlib.util
import sys
from pathlib import Path

root=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(root/'scripts/diagnostics'))
path=root/'scripts/diagnostics/andromeda_served_price_accuracy_remote_v2.py'
spec=importlib.util.spec_from_file_location('accuracy_remote_v2',path); module=importlib.util.module_from_spec(spec); spec.loader.exec_module(module)

assert "api-andromeda-quote-preview.php" in module.PHP
assert "api-andromeda-selected-quote.php" not in module.PHP
assert "AnyTourAndromedaPriceObservation::compareServed" in module.PHP
assert "served_price_observation" in module.PHP
for forbidden in ('curl_','PDO','mysqli_','file_put_contents(','unlink(','rename(','mkdir('):
    assert forbidden not in module.PHP, forbidden
sample={
 'status':'ok','observed_at':1789551000,'runtime_supports_served_price_observation':True,
 'scan':{'matched_files':11,'completed_checkpoints':5,'observations':1,'no_observation':4,'not_completed':6,'invalid_files':0},
 'accuracy':{'comparable':1,'currency_mismatch':0,'exact':0,'exact_accuracy':0,
   'within_100_bps_accuracy':0,'within_300_bps_accuracy':0,'within_500_bps_accuracy':0,'within_1000_bps_accuracy':1,
   'mean_relative_delta_bps':771,'p50_relative_delta_bps':771,'p90_relative_delta_bps':771,'max_relative_delta_bps':771},
 'supplier_calls':0,'database_access':False,'remote_writes':0,
}
assert module.validate(sample)==sample
print('Andromeda deployed served-observation capability probe v2: PASS')
