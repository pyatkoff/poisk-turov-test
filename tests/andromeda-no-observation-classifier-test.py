#!/usr/bin/env python3
import importlib.util,sys
from pathlib import Path
root=Path(__file__).resolve().parents[1];sys.path.insert(0,str(root/'scripts/diagnostics'))
p=root/'scripts/diagnostics/andromeda_no_observation_classifier.py';s=importlib.util.spec_from_file_location('classifier',p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
for required in ('api-andromeda-quote-preview.php','served_price_observation','price_observation','final_price_verified','quote-flight-v1','relative_to_current_producer'):
 assert required in m.PHP,required
for forbidden in ('curl_','PDO','mysqli_','file_put_contents(','fopen(','unlink(','rename(','mkdir('):assert forbidden not in m.PHP,forbidden
sample={'status':'ok','summary':{'completed':5,'with_observation':1,'without_observation':4,'kind':{'quote':3,'quote_flight':1},'observation_key':{'absent':4,'null':0},'final_price_verified':{'true':1,'false':3,'other':0},'price_observation':{'present':0,'absent':4},'result_state':{'quote_verified':1,'flight_selection_required':3,'other':0},'relative_to_current_producer':{'before':4,'same_or_after':0}},'supplier_calls':0,'database_access':False,'remote_writes':0}
assert m.validate(sample)==sample
print('Andromeda no-observation classifier: aggregate/read-only contract PASS')
