#!/usr/bin/env python3
"""One bounded BG dictionary GET after immutable supplier-queue reconciliation."""
from __future__ import annotations
import base64, collections, datetime, hashlib, io, json, os, pathlib, re, signal, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, zipfile, zlib
OP='hotel-match-bg-dictionary-1971-20260922-v5'
URL='https://export.bgoperator.ru/yandex?action=hotelsJson'
REPO='pyatkoff/poisk-turov-test'
CAP=4*1024*1024
P=pathlib.Path
FIELDS=('key','name','stars','countryKey','cityKey','inPr')
PINS={
 'v9':(10634643226,'0a4337d6a00f668b643b8babb14867e3cae5189bbb881c724c24e5b74e7baaf0','reconciliation.json','0995ccbd0c14639a335a644748d6dc5af6b359345328698ea3c0fc1300d88814'),
 'dossier':(10680794954,'2d7efa9e652a561928f72cb940e3c0d70c6eac6b18b132d93b90cf22e61d2c42','result.json','84cc7d2097c191a2bfc64983a57ccfb63fe38775e6ecd0c8c9db1255f1efb6ce'),
 'queue':(10684698560,'49bc3353dd24ab2d8eb5f9bdd2799653cc5fccea0db430b402672b7cae6d280b','queue.json','10e04c21287a6ac7dab7c98133f2c23c7ebe4528fc1179bee066ffdc1e04257b')}
def sha(b):return hashlib.sha256(b).hexdigest()
def now():return datetime.datetime.now(datetime.timezone.utc).isoformat()
def check(x,msg):
 if not x:raise ValueError(msg)
def save(path,value):
 b=(json.dumps(value,ensure_ascii=False,indent=2,sort_keys=True)+'\n').encode()
 with open(path,'xb') as f:f.write(b);f.flush();os.fsync(f.fileno())
 return sha(b)
def api(path):
 check(not path.startswith('/') and '..' not in path,'api_path')
 return json.loads(subprocess.check_output(['gh','api','repos/'+REPO+'/'+path],timeout=40))
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
def artifact(name):
 aid,zsha,member,msha=PINS[name]
 raw=subprocess.check_output(['gh','api',f'repos/{REPO}/actions/artifacts/{aid}/zip'],timeout=60)
 check(len(raw)<8*CAP and sha(raw)==zsha,'artifact_'+name)
 z=zipfile.ZipFile(io.BytesIO(raw));b=z.read(member);check(sha(b)==msha,'member_'+name)
 if name=='queue':
  b2=z.read('immutable-sources.json');check(sha(b2)=='31ba0aee0a1b8692c3d9662425ad4aa37892c376e9d4c7048c8f3a96400b3791','proof_sources')
  save('input/proof-sources.json',json.loads(b2))
 save('input/'+name+'.json',json.loads(b));return json.loads(b)
def f4(edge):
 link=edge['operator_link'];check(sha(link.encode())==edge['operator_link_sha256'],'operator_link_hash')
 u=urllib.parse.urlsplit(link);check(u.scheme in ('http','https') and u.hostname in ('bgoperator.ru','www.bgoperator.ru'),'operator_link_host')
 ids=[v for k,v in urllib.parse.parse_qsl(u.query,keep_blank_values=True) if k.casefold()=='f4']
 check(ids and all(re.fullmatch(r'[1-9][0-9]{5,17}',v) for v in ids),'raw_f4')
 return ids
def norm(s):return ' '.join(re.findall(r'[^\W_]+',str(s).casefold(),re.UNICODE))
def inflate(b,encoding):
 check(len(b)<=CAP,'wire_cap')
 if encoding=='gzip':
  d=zlib.decompressobj(16+zlib.MAX_WBITS);out=d.decompress(b,CAP+1)
  check(len(out)<=CAP and d.eof and not d.unconsumed_tail and not d.unused_data,'decompression_cap_or_framing')
  return out
 check(encoding in ('','identity'),'unknown_content_encoding');return b
def select(data,ids):
 check(isinstance(data,list),'dictionary_not_array')
 chosen={};dupes=set()
 for item in data:
  check(isinstance(item,dict),'dictionary_row_not_object')
  key=str(item.get('key',''))
  if key not in ids:continue
  row={k:item[k] for k in FIELDS if k in item}
  check(all(v is None or isinstance(v,(str,int,float,bool)) for v in row.values()),'non_scalar_dictionary_field')
  row['key']=key
  if key in chosen:dupes.add(key)
  else:chosen[key]=row
 return chosen,sorted(dupes)
def join(dossier,chosen,dupes):
 rows=[];counts=collections.Counter()
 for old in dossier['rows']:
  ids=old['f4_candidates'];found=[chosen[x] for x in ids if x in chosen];reason=[]
  if not found:reason.append('not_in_active_bulk_dictionary')
  if len(ids)!=1:reason.append('multiple_raw_f4')
  if any(x in dupes for x in ids):reason.append('duplicate_dictionary_key')
  if len(old['accepted_catalog_ids'])!=1:reason.append(old['anchor_class'])
  if old['manual']:reason.append('manual_decision')
  if any(x['supplier_namespace']=='operator_115' and x['decision_status']=='accepted' for x in old['identities']):reason.append('operator115_already_accepted')
  name_exact=bool(len(found)==1 and norm(found[0].get('name',''))==norm(old['catalog_hotel']['name']))
  if found:reason.append('bg_geography_namespace_requires_validation')
  if found and not name_exact:reason.append('name_or_qualifier_review')
  r={k:old[k] for k in ('tv_hotel_id','f4_candidates','operator_link','operator_link_sha256','search_id','tour_id','batch','catalog_hotel','anchor_class','accepted_catalog_ids','manual')}
  r.update(dictionary_rows=found,local_name_exact=name_exact,reasons=reason,safe_to_write_now=False)
  rows.append(r);counts['matched_hotels' if found else 'unmatched_hotels']+=1
  if found and len(ids)==1 and len(old['accepted_catalog_ids'])==1 and not old['manual']:counts['matched_single_f4_single_anchor_no_manual']+=1
 return rows,dict(counts)
def preflight():
 P('input').mkdir();P('reservation').mkdir();P('terminal').mkdir()
 claim=api('issues/comments/5773743101')
 check(claim['user']['id']==226193297 and claim['author_association']=='OWNER' and claim['issue_url'].endswith('/issues/2530'),'claim_owner')
 for s in (OP,'ровно1 unauthenticated HTTPS GET','838 raw F4','mapping/DBwrites0'):check(s in claim['body'],'claim_scope')
 v=artifact('v9');d=artifact('dossier');q=artifact('queue')
 check(d['source_sha']=='8b15323f18189db09c1c3591182c167ba8c5243a' and len(d['rows'])==816,'dossier')
 edges=[e for e in v['source_result']['edges'] if e.get('operator_id')==18];check(len(edges)==816,'edge_count')
 bytv={};seen={};sizes=collections.Counter()
 for e in edges:
  tv=int(e['tv_hotel_id']);ids=f4(e);check(tv not in bytv,'duplicate_tv');bytv[tv]=ids;sizes[len(ids)]+=1
  for x in ids:check(x not in seen or seen[x]==tv,'f4_collision');seen[x]=tv
 check(len(seen)==838 and sizes=={1:794,2:22},'membership')
 check({r['tv_hotel_id']:r['f4_candidates'] for r in d['rows']}==bytv,'current_membership')
 save('input/frontier.json',{'ids':sorted(seen),'tv':bytv})
 save('reservation/reservation.json',{'operation':OP,'state':'reserved_before_supplier','head_sha':os.environ['HEAD'],'at_utc':now(),'max_supplier_http_requests':1,'frontier_f4':838,'frontier_hotels':816,'input_pins':PINS,'claim_sha256':sha(claim['body'].encode()),'no_replay':True})
def security():
 for _ in range(60):
  a=[r for r in api('actions/runs?head_sha='+os.environ['HEAD']+'&per_page=100')['workflow_runs'] if r['name']=='Security guard' and r['head_sha']==os.environ['HEAD']]
  if a and a[0]['status']=='completed':check(a[0]['conclusion']=='success','security_failed');return
  time.sleep(3)
 raise ValueError('security_not_green')
def capability():
 baseline={r['id']:r for r in json.load(open('input/queue.json'))};sources=json.load(open('input/proof-sources.json'))
 result=[];main=api('git/ref/heads/main')['object']['sha']
 for status in ('queued','in_progress'):
  a=api('actions/runs?status='+status+'&per_page=100');check(a['total_count']<=100,'queue_pagination')
  for r in a['workflow_runs']:
   if r['id']==int(os.environ['GITHUB_RUN_ID']) or r['name']=='Security guard':continue
   txt=' '.join(str(r.get(k,'')) for k in ('name','path','head_branch')).lower()
   if not any(x in txt for x in ('andromeda','anex','tourvisor','hotel-match','match-biblio')):continue
   old=baseline.get(r['id']);check(old is not None,'new_or_unknown_supplier_run_'+str(r['id']))
   for k in ('head_sha','path','run_attempt','event'):check(r.get(k)==old.get(k),'changed_run_'+str(r['id']))
   check(r['status']=='queued' and api('actions/runs/'+str(r['id'])+'/jobs?per_page=100')['total_count']==0,'started_supplier_run_'+str(r['id']))
   raw=base64.b64decode(api('contents/'+r['path']+'?ref='+r['head_sha'])['content'])
   check(sha(raw)==old['workflow_sha256'],'changed_workflow')
   path=r['path'];why=''
   if path.endswith(('andromeda-detail-publish.yml','andromeda-package-checkpoint-read.yml')):
    check(main!=r['head_sha'],'main_gate_may_pass')
    check(b'if (main.object.sha !== context.sha) throw new Error' in raw,'missing_main_gate');why='mandatory_current_main_mismatch_before_every_remote_job'
   elif path.endswith('andromeda-fresh-package-live.yml'):
    a=api('actions/artifacts/10266054879');check(a['expired'] is True,'required_artifact_available')
    check(b'curl --fail' in raw and raw.index(b'actions/artifacts/10266054879/zip')<raw.index(b'--execute'),'artifact_not_before_execute')
    req=urllib.request.Request('https://api.github.com/repos/'+REPO+'/actions/artifacts/10266054879/zip',headers={'Authorization':'Bearer '+os.environ['GH_TOKEN']})
    try:
     with urllib.request.build_opener(NoRedirect()).open(req,timeout=20) as f:code=f.status
    except urllib.error.HTTPError as e:code=e.code;e.close()
    check(code==410,'required_artifact_not_410');why='mandatory_fail_closed_artifact_410_before_live_execute'
   elif path.endswith('andromeda-quote-preview-publish.yml'):
    check(b'4d8f53763806932cbf24160437cb93e33d04f419' in raw and b'95fa11f5a1cb7e18c905c9760a1eae7f2c0ee7aa' in raw,'publisher_pin')
    why='reviewed_pinned_file_only_publisher_and_mocked_smoke_no_supplier_invocation'
   elif path.endswith('hotel-match-current-tv-anex-observations-check.yml'):
    check(r['head_sha']=='ae70cde8c71d5bc0430f954b8836fb69a19aefaa','offline_source')
    why='reviewed_pure_classifier_tests_library_only_no_supplier_execution'
   else:raise ValueError('unproven_supplier_capability')
   result.append({'id':r['id'],'head_sha':r['head_sha'],'workflow_sha256':sha(raw),'status':'queued','jobs_count':0,'non_supplier_reason':why})
 return {'at_utc':now(),'current_main':main,'reconciled':result,'unknown_or_capable':0,'policy':'no_concurrent_supplier_access','source_evidence_sha256':'31ba0aee0a1b8692c3d9662425ad4aa37892c376e9d4c7048c8f3a96400b3791'}
def execute():
 security(); proof=capability();save('terminal/capability.json',proof)
 d=json.load(open('input/dossier.json'));ids=set(json.load(open('input/frontier.json'))['ids'])
 save('terminal/http-reservation.json',{'operation':OP,'head_sha':os.environ['HEAD'],'state':'reserved_one_get_no_replay','at_utc':now(),'supplier_http_requests_reserved':1,'url':URL,'maximum_bytes':CAP,'timeout_seconds':30})
 def timeout(sig,frame):raise TimeoutError('supplier_deadline')
 signal.signal(signal.SIGALRM,timeout);signal.alarm(30)
 count=0;chosen={};duplicates=[];result={'operation':OP,'source_sha':os.environ['HEAD'],'supplier_http_requests':1,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True,'at_utc':now()}
 try:
  req=urllib.request.Request(URL,headers={'Accept-Encoding':'gzip','Accept':'application/json','User-Agent':'AnyTour-MATCH-dictionary/1'})
  with urllib.request.build_opener(NoRedirect()).open(req,timeout=30) as f:
   result['http_status']=f.status;check(f.status==200,'supplier_http_status')
   b=f.read(CAP+1);result['wire_bytes']=len(b);result['wire_sha256']=sha(b)
   body=inflate(b,f.headers.get('Content-Encoding','').strip().lower());result['decoded_bytes']=len(body);result['response_sha256']=sha(body)
  data=json.loads(body.decode('utf-8-sig'));chosen,duplicates=select(data,ids);count=len(data)
  signal.alarm(0)
  rows,counts=join(d,chosen,duplicates)
  result.update(state='completed_dictionary_read',dictionary_total_rows=count,matched_f4=len(chosen),missing_f4=sorted(ids-set(chosen)),duplicate_matching_keys=duplicates,counts=counts,rows=rows,retained_dictionary_rows=list(chosen.values()),full_raw_body_retained=False)
 except Exception as e:
  signal.alarm(0);result.update(state='failed_no_replay',error_type=type(e).__name__,error=str(e)[:200])
 finally:
  digest=save('terminal/result.json',result);save('terminal/receipt.json',{'operation':OP,'source_sha':os.environ['HEAD'],'state':result['state'],'result_sha256':digest,'supplier_http_requests':1,'database_writes':0,'mapping_writes':0,'no_replay':True})
 print(json.dumps({k:v for k,v in result.items() if k not in ('rows','retained_dictionary_rows','missing_f4')},ensure_ascii=False));check(result['state']=='completed_dictionary_read','supplier_operation_failed')
def finish():
 P('terminal').mkdir(exist_ok=True)
 if not P('terminal/receipt.json').exists():
  reserved=P('terminal/http-reservation.json').exists()
  result={'operation':OP,'source_sha':os.environ['HEAD'],'state':'access_unknown' if reserved else 'blocked_before_supplier','supplier_http_requests':None if reserved else 0,'database_writes':0,'mapping_writes':0,'no_replay':True}
  h=save('terminal/result.json',result);save('terminal/receipt.json',dict(result,result_sha256=h))
def test():
 import gzip
 ids={'102123456789'}
 check(select([{'key':'102123456789','name':'A','private':'excluded'}],ids)==({'102123456789':{'key':'102123456789','name':'A'}},[]),'projection_test')
 check(select([{'key':'102123456789'},{'key':'102123456789'}],ids)[1]==list(ids),'duplicate_test')
 check(inflate(gzip.compress(b'[]'),'gzip')==b'[]','gzip_test')
 for b,enc in ((b'x'*(CAP+1),'identity'),(gzip.compress(b'x'*(CAP+1)),'gzip')):
  try:inflate(b,enc)
  except ValueError:pass
  else:raise AssertionError('cap_test')
 e={'operator_link':'https://www.bgoperator.ru/price.shtml?F4=102123456789&F4=102999999999'};e['operator_link_sha256']=sha(e['operator_link'].encode());check(len(f4(e))==2,'dual_f4_test')
 print('BG bounded parser tests PASS')
if __name__=='__main__':
 {'preflight':preflight,'execute':execute,'finish':finish,'test':test}[sys.argv[1]]()
