import copy,hashlib
from postpass_verify import verify,APP
rows=[{'tourvisor_hotel_id':i,'logical_id':'h'+str(i),'posts':{'max':'max'+str(i),'telegram':'tg'+str(i)},'feed_binding':{'offer_ids':{'max':'max_'+str(i),'telegram':'tg_'+str(i)}}} for i in range(1,1001)]
c={'expected_ids':list(range(1,1001)),'journal_pin':{'bytes':100,'lines':1},'previous_operations':['prior'],'previous_updated_hotel_ids':[2],'previous_scanned_ids':[2]}
j={'ok':True,'prefix_verified':True,'pending':None,'cycle_state':'preflight_blocked','cycle_collector_run_id':'44236272559','writes':0,'pin':{'bytes':110,'lines':2}}
f={'ok':True,'files':{}}
for name,prefixes in [('hotel-posts-max.xml',['max']),('hotel-posts-telegram.xml',['tg']),('hotel-posts-all.xml',['max','tg'])]:f['files'][name]={'envelope':'same','rows':{prefix+'_'+str(i):{'stable':'stable','full':'old'} for prefix in prefixes for i in range(1,1001)}}
rid='a'*64;op='commercial-monitor-price-h1-'+rid
posts=[]
for platform,url in [('max','max1'),('telegram','tg1')]:posts.append({'platform':platform,'state':'sent','body_photo_button_verified':True,'existing_message_reused':True,'content_updated':True,'price_link_verified':True,'post_url':url,'logical_id':'h1','publication_id':1,'version_id':3,'previous_version_id':2,'message_id':'1','price_link_url':'url','button_url':'url','text':'text','text_sha256':hashlib.sha256(b'text').hexdigest(),'monitor_price':{'record_id':rid,'tourvisor_hotel_id':1,'price':100}})
r={'ok':True,'installed_sha':APP,'roster_pairs':1000,'budget_seconds':480,'updated_pairs':1,'new_posts':0,'supplier_calls':0,'site_writes':0,'updates':[{'tourvisor_hotel_id':1,'record_id':rid,'operation':op,'posts':posts,'price':100}],'scanned_pairs':1}
a=copy.deepcopy(f)
for file in a['files'].values():
 for k,v in file['rows'].items():
  if k in ['max_1','tg_1']:v['full']='new'
assert verify(r,f,a,j,c,rows)['confirmed_messages']==2;checks=1
for case in ['repeat','receipt','posturl','count','partial','feedmember','unselected','stable','journal','cycle','budget']:
 rr,aa,jj,cc=map(copy.deepcopy,[r,a,j,c])
 if case=='repeat':cc['previous_operations'].append(op)
 elif case=='receipt':rr['updates'][0]['posts'][0]['existing_message_reused']=False
 elif case=='posturl':rr['updates'][0]['posts'][0]['post_url']='other'
 elif case=='count':rr['updated_pairs']=2
 elif case=='partial':rr['updates'][0]['posts'].pop()
 elif case=='feedmember':aa['files']['hotel-posts-all.xml']['rows'].pop('tg_2')
 elif case=='unselected':aa['files']['hotel-posts-all.xml']['rows']['tg_2']['full']='changed'
 elif case=='stable':aa['files']['hotel-posts-all.xml']['rows']['tg_1']['stable']='changed'
 elif case=='journal':jj['pending']={}
 elif case=='cycle':jj['cycle_collector_run_id']='other'
 elif case=='budget':rr['budget_seconds']=1800
 try:verify(rr,f,aa,jj,cc,rows);raise AssertionError(case+' accepted')
 except RuntimeError:checks+=1
print({'ok':True,'checks':checks,'remote_calls':0})
