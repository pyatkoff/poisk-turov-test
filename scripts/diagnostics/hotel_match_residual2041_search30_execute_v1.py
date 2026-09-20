#!/usr/bin/env python3
import collections,datetime as dt,json,os,pathlib,re,sys,time,urllib.parse
from zoneinfo import ZoneInfo
from hotel_match_residual2041_search30_provider_v1 import OP,OPS,Provider,ident,link_state,num,ready,rows,save,unwrap
MAX_CONTINUE=2
def readj(p):x=json.loads(pathlib.Path(p).read_text());return x if isinstance(x,dict) else (_ for _ in ()).throw(RuntimeError('json_shape'))
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
def choose(h,missing,captured):
 out={}
 for t in h.get('tours') or []:
  if not isinstance(t,dict):continue
  op=ident(t,'operator');tid=str(t.get('id') or t.get('tourId') or '')
  if op in missing and op not in captured and re.fullmatch(r'[1-9][0-9]{0,31}',tid):out.setdefault(op,tid)
 return out
def execute(root,opdir,planp):
 plan=readj(planp);res=readj(opdir/'reservation.json')
 if plan.get('operation')!=OP or plan.get('state')!='current_plan_complete' or res.get('operation')!=OP:raise RuntimeError('input_guard')
 groups=plan.get('groups') or []
 for g in groups:
  if not 1<=len(g.get('hotel_ids',[]))<=30 or len(set(g['hotel_ids']))!=len(g['hotel_ids']):raise RuntimeError('batch_guard')
 p=None;batches=[];edges=[];state='failed_before_provider_access';reason=None
 try:
  p=Provider(root,opdir)
  now=dt.datetime.now(ZoneInfo('Europe/Moscow'));date_from=(now+dt.timedelta(days=1)).date().isoformat();date_to=(now+dt.timedelta(days=21)).date().isoformat()
  for g in groups:
   wanted=set(map(int,g['hotel_ids']));missing={int(t['tv_hotel_id']):set(map(int,t['missing_operator_ids'])) for t in g['targets']};captured={h:set() for h in wanted}
   ctx={'departureId':1,'countryId':int(g['country_id']),'dateFrom':date_from,'dateTo':date_to,'nightsFrom':7,'nightsTo':10,'adults':2,'currency':'RUB','onlyCharter':False,'onlyDirect':False,'hotelIds':sorted(wanted)}
   _,st=p.call('search_start','/tours/search',ctx);search=sid(st)
   if not search:raise RuntimeError('search_id_missing')
   rounds=[];cont=0
   while True:
    poll(p,search);_,rx=p.call('search_results',f'/tours/search/{search}',{'limit':30});rm=result_map(rx,wanted);new=0
    for hid,h in rm.items():
     for op,tid in choose(h,missing[hid],captured[hid]).items():
      code,d=p.call('tour_detail','/tours/'+urllib.parse.quote(tid,safe=''),{'currency':'RUB'});d=unwrap(d);e={'tv_hotel_id':hid,'operator_id':op,'operator':OPS[op],'tour_id':tid,'search_id':search,'batch':g['batch'],'round':cont,'safe_to_write_now':False}
      if code==404:e['state']='detail_404'
      elif not isinstance(d,dict) or ident(d,'hotel')!=hid or ident(d,'operator')!=op or str(d.get('id') or d.get('tourId') or '')!=tid:e['state']='detail_identity_mismatch'
      else:e['state']='detail_identity_verified';e.update(link_state(op,d));captured[hid].add(op);new+=1
      save(opdir/f'edge-{len(edges)+1:05d}.json',e);edges.append(e)
    unresolved=sum(len(missing[h]-captured[h]) for h in wanted);rounds.append({'round':cont,'returned_hotels':len(rm),'new_edges':new,'unresolved_edges':unresolved})
    if unresolved==0 or cont>=MAX_CONTINUE:break
    p.call('search_continue',f'/tours/search/{search}/continue',{});cont+=1
   b={'batch':g['batch'],'country_id':g['country_id'],'hotel_ids':sorted(wanted),'search_id':search,'continues':cont,'rounds':rounds,'captured_edges':sum(len(v) for v in captured.values()),'unresolved_edges':sum(len(missing[h]-captured[h]) for h in wanted)}
   save(opdir/f'batch-{int(g["batch"]):04d}.json',b);batches.append(b)
  state='completed_read_only'
 except Exception as e:
  reason=type(e).__name__+':'+str(e) if isinstance(e,RuntimeError) else type(e).__name__;state='terminal_failed_no_replay' if p and p.physical else 'failed_before_provider_access'
 counts=collections.Counter(e.get('link_state','none') for e in edges);ops=collections.Counter(str(e['operator_id']) for e in edges)
 out={'operation':OP,'state':state,'reason':reason,'planned_groups':len(groups),'completed_batches':len(batches),'target_hotels':plan['stats']['search_targets'],'physical_http_attempts':p.physical if p else 0,'tariff_search_units_after':p.tariff if p else None,'action_counts':dict(p.actions) if p else {},'operator_detail_counts':dict(ops),'link_state_counts':dict(counts),'batches':batches,'edges':edges,'database_writes':0,'mapping_writes':0,'samo_calls':0,'safe_to_write_now':False,'no_replay':state!='failed_before_provider_access'}
 dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'physical_http_attempts':out['physical_http_attempts'],'tariff_search_units_after':out['tariff_search_units_after'],'database_writes':0,'mapping_writes':0,'no_replay':out['no_replay']})
 print(json.dumps({k:out[k] for k in ['state','reason','planned_groups','completed_batches','target_hotels','physical_http_attempts','tariff_search_units_after','action_counts','operator_detail_counts','link_state_counts']},ensure_ascii=False))
 return 0 if state=='completed_read_only' else 2
def selftest():
 assert len(list(range(30)))==30
 print('SEARCH30_EXEC_SELFTEST_OK')
if __name__=='__main__':
 if '--self-test' in sys.argv:selftest()
 elif '--execute' in sys.argv:sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
 else:raise SystemExit('disabled')
