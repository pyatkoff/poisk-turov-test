import copy,json,pathlib
from expiry_classification import classify_expired
checks=0
def valid(d,mode,admitted):
 global checks
 before=copy.deepcopy(d);out=classify_expired(d,mode=mode,admitted_verified_ids=admitted);assert d==before;checks+=1;return out
def rejects(d,mode,admitted,change):
 global checks
 x=copy.deepcopy(d);change(x)
 try:classify_expired(x,mode=mode,admitted_verified_ids=admitted)
 except (ValueError,TypeError):checks+=1;return
 raise AssertionError('mutation accepted')
d={'ok':True,'mode':'read_only_hook_inspection','new_posts':0,'supplier_calls':0,'site_writes':0,'held_pairs':0,'held_ids':[], 'verified_ids':[1,2,3], 'eligible_ids':[1],'unchanged_ids':[2],'updates':[], 'updated_pairs':0,'eligible_pairs':1,'unchanged_pairs':1,'scanned_pairs':3,'roster_pairs':3,'deferred':[{'tourvisor_hotel_id':3,'reason':'monitor_price_expired'}],'deferred_counts':{'monitor_price_expired':1},'error_counts':{'monitor_price_expired':1}}
valid(d,'inspect',[1,2,3])
p=copy.deepcopy(d);p.pop('mode');p.update(verified_ids=[1,2],updates=[{'tourvisor_hotel_id':1}],updated_pairs=1,budget_seconds=480)
valid(p,'default',[1,2,3])
for sample,mode in [(d,'inspect'),(p,'default')]:
 for change in [lambda x:x.update(error_counts={}),lambda x:x.update(error_counts={'monitor_price_expired':2}),lambda x:x['deferred'].append(x['deferred'][0].copy()),lambda x:x.update(eligible_ids=[1,3]),lambda x:x.update(updates=[{'tourvisor_hotel_id':3}]),lambda x:x.update(held_ids=[3]),lambda x:x.update(error_counts={'other_error':1}),lambda x:x.update(error='other_error'),lambda x:x.update(pending={'phase':'unknown'}),lambda x:x.update(scanned_pairs=2),lambda x:x.update(deferred_counts={}),lambda x:x['deferred'][0].update(reason='canonical_original_receipt_mismatch'),lambda x:x.update(error_counts={'monitor_price_expired':True})]:rejects(sample,mode,[1,2,3],change)
rejects(d,'inspect',[1,2,3],lambda x:x.update(global_journal_writes=1))
rejects(p,'default',[1,2,3],lambda x:x.update(verified_ids=[1,2,3]))
rejects(p,'default',[1,2,3],lambda x:x.update(budget_seconds=1800))
a=pathlib.Path(__file__).with_name('expiry-inspect-fixture.json')
assert a.is_file()
if a.exists():
 actual=json.loads(a.read_text());out=valid(actual,'inspect',actual['verified_ids']);assert out['expired_deferred_count']==9;checks+=1
# No hardcoded expiry waiver: zero and different counts are supported.
z=copy.deepcopy(d);z['deferred'][0]['reason']='no_fresh_observation';z['deferred_counts']={'no_fresh_observation':1};z['error_counts']=[];valid(z,'inspect',[1,2,3])
print({'ok':True,'checks':checks,'remote_calls':0,'writes':0})
