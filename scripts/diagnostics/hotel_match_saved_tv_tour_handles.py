#!/usr/bin/env python3
"""Mass read-only saved Tourvisor ANEX tour-handle inventory for MATCH #1971 v2.

ANEX's numeric Tourvisor operator id is established only from exact historical
ANEX tour handles that were already detail-verified in a completed no-replay
operation. No provider calls and no DB writes.
"""
from __future__ import annotations
import hashlib,json,os,re,subprocess,sys
from collections import Counter,defaultdict
from pathlib import Path

OP='hotel-match-saved-tv-tour-handles-1971-20260915-v2'
V8='hotel-match-manual-live-queue-1971-20260914-v8'
QSHA='f17ca8e8d9d3945f6aea3d232fca96708fc6f119c504461f95a4cc51af8ed3ed'
RSHA='52acba203042e86a1650ece5b0ae079dac6cfb49f25b5bc6747cdaefd316b09d'
NONHOTEL={'17097','817','28869','5173','815','29000','17194','17195','17196','17443','20963'}
ANEX_CONTROLS={
  '13278670760276':{'hotel_id':37412,'expected_native_anex_id':'4158','detail_name':'BARCELO TIRAN SHARM'},
  '13279232318107':{'hotel_id':132075,'expected_native_anex_id':'37719','detail_name':'POSH CLUB SUNRISE DIAMOND BEACH RESORT'},
  '13272998781716':{'hotel_id':182,'expected_native_anex_id':'1767','detail_name':'DOMINA CORAL BAY AQUAMARINE BEACH'},
  '13261030029798':{'hotel_id':183,'expected_native_anex_id':'1768','detail_name':'DOMINA CORAL BAY AQUAMARINE POOL'},
  '13278723374403':{'hotel_id':131347,'expected_native_anex_id':'37723','detail_name':'POSH CLUB BY SUNRISE TUCANA RESORT'},
}
INTOURIST_CONTROL={'tour_id':'43277415737179','hotel_id':2904,'expected_operator_id':43}

def dump(x): return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def h(raw): return hashlib.sha256(raw).hexdigest()
def write(path,obj):
    raw=dump(obj);fd=os.open(path,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
    try:
        with os.fdopen(fd,'wb',closefd=False) as f:f.write(raw);f.flush();os.fsync(f.fileno())
    finally: os.close(fd)
    if path.read_bytes()!=raw: raise RuntimeError('readback')
    return h(raw)

def targets(home):
    p=home/'.anytoour-match'/'operations'/V8;qr=(p/'queue.json').read_bytes();rr=(p/'result.json').read_bytes();rc=json.loads((p/'receipt.json').read_bytes())
    if h(qr)!=QSHA or h(rr)!=RSHA or rc.get('readback_verified') is not True or rc.get('result_sha256')!=RSHA:raise RuntimeError('v8_pin')
    out=[]
    for r in json.loads(qr).get('rows',[]):
        if r.get('provider')!='anex':continue
        ext=str(r.get('external_hotel_id') or '')
        if ext in NONHOTEL:continue
        local=r.get('candidate_local_id')
        if not re.fullmatch(r'[1-9][0-9]{0,19}',ext) or not isinstance(local,int) or local<=0:raise RuntimeError('target')
        out.append(r)
    if len(out)!=97:raise RuntimeError('target_count')
    return out

def php():return r'''<?php
declare(strict_types=1);error_reporting(0);ob_start();
try{$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$raw=(string)getenv('MATCH_LOCAL_IDS');if(!preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D',$raw))throw new RuntimeException('ids');$ids=array_values(array_unique(array_map('intval',explode(',',$raw))));if(!$ids||count($ids)>110)throw new RuntimeException('scope');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');$ex=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_price_observations'")->fetchColumn();if((int)$ex!==1)throw new RuntimeException('table');$ph=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare("SELECT observed_at,source,search_id,country_id,hotel_id,tour_id,operator_id,departure_date,nights FROM tour_price_observations WHERE hotel_id IN ($ph) ORDER BY observed_at DESC,id DESC LIMIT 100000");$q->execute($ids);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)>=100000)throw new RuntimeException('cap');$db->exec('ROLLBACK');ob_end_clean();echo json_encode(['price'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit(0);}catch(Throwable $e){try{if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable $x){}ob_end_clean();fwrite(STDERR,'read_failed\n');exit(2);}'''

def read(ids):
    env=os.environ.copy();env['MATCH_LOCAL_IDS']=','.join(map(str,ids));p=subprocess.run(['php','-d','display_errors=0','-r',php().replace('<?php','',1)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,env=env,timeout=120)
    if p.returncode:raise RuntimeError('db_read')
    d=json.loads(p.stdout);rows=d.get('price')
    if not isinstance(rows,list):raise RuntimeError('shape')
    return rows

def anchor(rows):
    by_tour=defaultdict(list)
    for x in rows:
        tour=str(x.get('tour_id') or '')
        if tour:by_tour[tour].append(x)
    matches=[];op_controls=defaultdict(set)
    for tour,c in ANEX_CONTROLS.items():
        for x in by_tour.get(tour,[]):
            if int(x.get('hotel_id') or 0)!=c['hotel_id'] or not x.get('operator_id'):continue
            op=int(x['operator_id']);op_controls[op].add(tour);matches.append({'tour_id':tour,'hotel_id':c['hotel_id'],'expected_native_anex_id':c['expected_native_anex_id'],'operator_id':op,'observed_at':x.get('observed_at')})
    qualified=sorted(op for op,tours in op_controls.items() if len(tours)>=2)
    if len(qualified)>1:raise RuntimeError('anex_operator_anchor_conflict')
    int_matches=[]
    for x in by_tour.get(INTOURIST_CONTROL['tour_id'],[]):
        if int(x.get('hotel_id') or 0)==INTOURIST_CONTROL['hotel_id'] and x.get('operator_id'):
            int_matches.append({'tour_id':INTOURIST_CONTROL['tour_id'],'hotel_id':INTOURIST_CONTROL['hotel_id'],'operator_id':int(x['operator_id']),'observed_at':x.get('observed_at')})
    if int_matches and any(x['operator_id']!=INTOURIST_CONTROL['expected_operator_id'] for x in int_matches):raise RuntimeError('intourist_control_conflict')
    return qualified,matches,int_matches

def main():
    if '--self-test' in sys.argv:
        assert len(ANEX_CONTROLS)==5 and INTOURIST_CONTROL['expected_operator_id']==43;assert 'INSERT ' not in php().upper() and 'UPDATE ' not in php().upper() and 'DELETE ' not in php().upper();print('saved-tv-tour-handles-v2 self-test: PASS');return 0
    source_sha=os.environ.get('MATCH_SOURCE_SHA','')
    if os.environ.get('OPERATION_ID')!=OP or not re.fullmatch(r'[0-9a-f]{40}',source_sha):raise RuntimeError('guard')
    home=Path.home().resolve();out=home/'.anytoour-match'/'operations'/OP;out.mkdir(mode=0o700,exist_ok=False)
    write(out/'reservation.json',{'operation_id':OP,'source_sha':source_sha,'state':'reserved_before_db_access','read_only':True,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True})
    ts=targets(home);candidate_ids=sorted({int(x['candidate_local_id']) for x in ts});control_ids=sorted({x['hotel_id'] for x in ANEX_CONTROLS.values()}|{INTOURIST_CONTROL['hotel_id']});rows=read(sorted(set(candidate_ids)|set(control_ids)))
    anex_ids,anchor_matches,int_matches=anchor(rows);by=defaultdict(list)
    if anex_ids:
        allowed=set(anex_ids)
        for x in rows:
            if x.get('operator_id') is None:continue
            try:hid=int(x.get('hotel_id') or 0);op=int(x['operator_id'])
            except:continue
            if hid in candidate_ids and op in allowed:by[hid].append(x)
    ev=[]
    for t in ts:
        hid=int(t['candidate_local_id']);seen=set();handles=[]
        for x in by.get(hid,[]):
            tour=str(x.get('tour_id') or '')
            if not tour or tour in seen:continue
            seen.add(tour);handles.append({'tour_id':tour,'operator_id':int(x['operator_id']),'country_id':int(x.get('country_id') or 0),'departure_date':x.get('departure_date'),'nights':x.get('nights'),'search_id':x.get('search_id'),'observed_at':x.get('observed_at')})
        handles.sort(key=lambda x:str(x.get('observed_at') or ''),reverse=True)
        ev.append({'external_anex_id':str(t['external_hotel_id']),'source_name':t.get('source_name'),'search_count':int(t.get('search_count') or 0),'country_id':int(t['country_id']),'candidate_local_id':hid,'candidate_name':t.get('candidate_name'),'current_hold_reason':t.get('auto_block_reason'),'saved_anex_tour_handles':handles[:20],'saved_anex_tour_handle_count':len(handles),'route':'saved_anex_tour_detail_candidate' if handles else ('operator_id_anchor_insufficient' if not anex_ids else 'no_saved_anex_tour_handle')})
    ev.sort(key=lambda x:(-x['search_count'],int(x['external_anex_id'])));routes=Counter(x['route'] for x in ev);result={'schema':'hotel-match-saved-tv-tour-handles/2','operation_id':OP,'source_sha':source_sha,'state':'read_only_complete','target_count':len(ts),'unique_candidate_local_ids':len(candidate_ids),'control_local_ids':control_ids,'price_rows_read':len(rows),'anex_operator_ids':anex_ids,'anex_anchor_matches':anchor_matches,'intourist_control_matches':int_matches,'route_counts':dict(routes),'evidence':ev,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'booking_calls':0,'production_changed':False,'no_replay':True};eh=write(out/'evidence.json',{'schema':'hotel-match-saved-tv-tour-handles-rows/2','rows':ev});result['evidence_sha256']=eh;rh=write(out/'result.json',result);write(out/'receipt.json',{'operation_id':OP,'source_sha':source_sha,'state':'read_only_complete','result_sha256':rh,'evidence_sha256':eh,'readback_verified':h((out/'result.json').read_bytes())==rh and h((out/'evidence.json').read_bytes())==eh,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True});print(json.dumps({'target_count':len(ts),'price_rows_read':len(rows),'anex_operator_ids':anex_ids,'anex_anchor_matches':len(anchor_matches),'intourist_control_matches':len(int_matches),'route_counts':dict(routes),'result_sha256':rh},sort_keys=True));return 0
if __name__=='__main__':
    try:raise SystemExit(main())
    except Exception as e:print(json.dumps({'operation_id':OP,'state':'failed_read_only','reason':type(e).__name__,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True}),file=sys.stderr);raise
