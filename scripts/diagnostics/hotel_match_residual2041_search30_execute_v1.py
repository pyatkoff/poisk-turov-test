#!/usr/bin/env python3
"""Bounded identity-only acquisition: one start per disjoint <=30 hotel batch."""
import collections,datetime as dt,json,os,pathlib,re,sys,time,urllib.parse
from zoneinfo import ZoneInfo
from hotel_match_residual2041_search30_provider_v1 import OP,OPS,Provider,ident,link_state,num,ready,rows,save,unwrap
MAX_CONTINUE=0

def readj(p):
 x=json.loads(pathlib.Path(p).read_text())
 if not isinstance(x,dict):raise RuntimeError('json_shape')
 return x

def sid(x):
 x=unwrap(x)
 if isinstance(x,dict):
  for k in ('searchId','id'):
   v=num(x.get(k))
   if v:return str(v)
 return None

def poll(p,s):
 for _ in range(10):
  time.sleep(3);_,x=p.call('search_status',f'/tours/search/{s}/status',{'operatorStatus':False})
  if ready(x):return
 raise RuntimeError('search_timeout')

def result_map(x,wanted):
 out={}
 for h in rows(x):
  if isinstance(h,dict) and num(h.get('id')) in wanted:out[num(h['id'])]=h
 return out

def choose(h,missing,attempted,hid,seen):
 out={}
 for t in h.get('tours') or []:
  if not isinstance(t,dict):continue
  op=ident(t,'operator');tid=str(t.get('id') or t.get('tourId') or '')
  if op in missing and op not in attempted and re.fullmatch(r'[1-9][0-9]{0,31}',tid) and f'{hid}|{op}|{tid}' not in seen:out.setdefault(op,tid)
 return out

def context(g,date_from,date_to):
 ids=g.get('hotel_ids',[])
 if not 1<=len(ids)<=30 or any(type(i) is not int or i<=0 for i in ids) or len(set(ids))!=len(ids):raise RuntimeError('batch_guard')
 if type(g.get('country_id')) is not int or g['country_id']<=0:raise RuntimeError('country_guard')
 return {'departureId':1,'countryId':g['country_id'],'dateFrom':date_from,'dateTo':date_to,'nightsFrom':7,'nightsTo':10,'adults':2,'currency':'RUB','onlyCharter':False,'onlyDirect':False,'hotelIds':sorted(ids)}

def execute(root,opdir,planp):
 plan=readj(planp);res=readj(opdir/'reservation.json')
 if plan.get('operation')!=OP or plan.get('state')!='current_plan_complete' or res.get('operation')!=OP:raise RuntimeError('input_guard')
 groups=plan.get('groups') or [];all_ids=set()
 for g in groups:
  context(g,'2026-09-22','2026-10-12')
  if all_ids.intersection(g['hotel_ids']):raise RuntimeError('duplicate_batch_hotel')
  all_ids.update(g['hotel_ids'])
  if {t['tv_hotel_id'] for t in g['targets']}!=set(g['hotel_ids']):raise RuntimeError('target_binding')
  if any(not t['missing_operator_ids'] or not set(t['missing_operator_ids'])<=set(OPS) for t in g['targets']):raise RuntimeError('operator_binding')
 p=None;batches=[];attempted_batches=[];edges=[];found=set();reason=None;state='failed_before_provider_access'
 seen=set(plan.get('prior_detail_attempts',[]))
 try:
  p=Provider(root,opdir)
  now=dt.datetime.now(ZoneInfo('Europe/Moscow'));date_from=(now+dt.timedelta(days=1)).date().isoformat();date_to=(now+dt.timedelta(days=21)).date().isoformat()
  for g in groups:
   wanted=set(g['hotel_ids']);missing={t['tv_hotel_id']:set(t['missing_operator_ids']) for t in g['targets']};captured={h:set() for h in wanted};attempted={h:set() for h in wanted}
   ctx=context(g,date_from,date_to)
   # Retain the whole sent set before start, including interrupted batches.
   attempt={'batch':g['batch'],'country_id':g['country_id'],'hotel_ids':sorted(wanted),'context':ctx}
   save(opdir/f'batch-{int(g["batch"]):04d}-reserved.json',attempt);attempted_batches.append(attempt)
   _,st=p.call('search_start','/tours/search',ctx);search=sid(st)
   if not search:raise RuntimeError('search_id_missing')
   poll(p,search);_,rx=p.call('search_results',f'/tours/search/{search}',{'limit':30});rm=result_map(rx,wanted);found.update(rm)
   for hid,h in rm.items():
    for op,tid in choose(h,missing[hid],attempted[hid],hid,seen).items():
     attempted[hid].add(op);seen.add(f'{hid}|{op}|{tid}')
     code,d=p.call('tour_detail','/tours/'+urllib.parse.quote(tid,safe=''),{'currency':'RUB'});d=unwrap(d)
     e={'tv_hotel_id':hid,'operator_id':op,'operator':OPS[op],'tour_id':tid,'search_id':search,'batch':g['batch'],'safe_to_write_now':False}
     if code==404:e['state']='detail_404'
     elif not isinstance(d,dict) or ident(d,'hotel')!=hid or ident(d,'operator')!=op or str(d.get('id') or d.get('tourId') or '')!=tid:e['state']='detail_identity_mismatch'
     else:
      e['state']='detail_identity_verified';e.update(link_state(op,d))
      if e.get('link_state','').startswith('captured'):captured[hid].add(op)
     save(opdir/f'edge-{len(edges)+1:05d}.json',e);edges.append(e)
   b={'batch':g['batch'],'country_id':g['country_id'],'hotel_ids':sorted(wanted),'search_id':search,'continues':0,'returned_hotels':len(rm),'captured_edges':sum(len(v) for v in captured.values()),'unresolved_edges':sum(len(missing[h]-captured[h]) for h in wanted)}
   save(opdir/f'batch-{int(g["batch"]):04d}.json',b);batches.append(b)
  state='completed_read_only'
 except Exception as e:
  reason=type(e).__name__+':'+str(e) if isinstance(e,RuntimeError) else type(e).__name__;state='terminal_failed_no_replay' if p and p.physical else 'failed_before_provider_access'
 counts=collections.Counter(e.get('link_state','none') for e in edges);ops=collections.Counter(str(e['operator_id']) for e in edges)
 out={'operation':OP,'state':state,'reason':reason,'planned_groups':len(groups),'completed_batches':len(batches),'target_hotels':plan['stats']['search_targets'],'found_unique_hotels':len(found),'attempted_batches':attempted_batches,'physical_http_attempts':p.physical if p else 0,'tariff_search_units_after':p.tariff if p else None,'action_counts':dict(p.actions) if p else {},'operator_detail_counts':dict(ops),'link_state_counts':dict(counts),'batches':batches,'edges':edges,'database_writes':0,'mapping_writes':0,'samo_calls':0,'safe_to_write_now':False,'no_replay':state!='failed_before_provider_access'}
 dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'physical_http_attempts':out['physical_http_attempts'],'tariff_search_units_after':out['tariff_search_units_after'],'database_writes':0,'mapping_writes':0,'no_replay':out['no_replay']})
 print(json.dumps({k:out[k] for k in ['state','reason','planned_groups','completed_batches','target_hotels','found_unique_hotels','physical_http_attempts','tariff_search_units_after','action_counts','operator_detail_counts','link_state_counts']},ensure_ascii=False))
 return 0 if state=='completed_read_only' else 2

def selftest():
 g={'hotel_ids':list(range(1,31)),'country_id':4};ctx=context(g,'2026-09-22','2026-10-12')
 assert len(ctx['hotelIds'])==30 and 'operatorIds' not in ctx and ctx['onlyCharter'] is False
 for bad in ([1,1],list(range(1,32)),[],[0]):
  try:context({'hotel_ids':bad,'country_id':4},'a','b')
  except RuntimeError:pass
  else:raise AssertionError('bad_batch_accepted')
 h={'tours':[{'id':'100','operator':{'id':25}},{'id':'101','operator':{'id':25}},{'id':'102','operator':{'id':18}}]}
 assert choose(h,{25,18},set(),1,{'1|25|100'})=={25:'101',18:'102'}
 assert choose(h,{25},{25},1,set())=={}
 assert result_map({'data':{'hotels':[{'id':1},{'id':2}]}},{1})=={1:{'id':1}}
 assert not link_state(25,{}).get('link_state','').startswith('captured')
 assert MAX_CONTINUE==0
 print('SEARCH30_EXEC_SELFTEST_OK')
if __name__=='__main__':
 if '--self-test' in sys.argv:selftest()
 elif '--execute' in sys.argv:sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
 else:raise SystemExit('disabled')
