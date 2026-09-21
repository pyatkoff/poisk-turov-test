#!/usr/bin/env python3
"""Mass read-only saved ANEX tour-handle inventory for CURRENT MATCH queue."""
from __future__ import annotations
import hashlib,json,os,re,subprocess,sys
from collections import Counter,defaultdict
from pathlib import Path

OP='hotel-match-saved-tv-tour-handles-1971-20260915-v3'
V8='hotel-match-manual-live-queue-1971-20260914-v8'
V8_QSHA='f17ca8e8d9d3945f6aea3d232fca96708fc6f119c504461f95a4cc51af8ed3ed'
V8_RSHA='52acba203042e86a1650ece5b0ae079dac6cfb49f25b5bc6747cdaefd316b09d'
DICT_OP='hotel-match-tv-operator-dictionary-1971-20260915-v1'
DICT_RSHA='19bb9de6380de4d77f17319af04c8744043b227c890deb6015f2fc77b9d487b8'
ANEX_OPERATOR_ID=13
NONHOTEL={'17097','817','28869','5173','815','29000','17194','17195','17196','17443','20963'}

def raw(obj):return (json.dumps(obj,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def digest(b):return hashlib.sha256(b).hexdigest()
def write(path,obj):
    b=raw(obj);fd=os.open(path,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
    try:
        with os.fdopen(fd,'wb',closefd=False) as f:f.write(b);f.flush();os.fsync(f.fileno())
    finally:os.close(fd)
    if path.read_bytes()!=b:raise RuntimeError('readback')
    return digest(b)

def pinned_inputs(home:Path):
    v=home/'.anytoour-match'/'operations'/V8;qb=(v/'queue.json').read_bytes();rb=(v/'result.json').read_bytes();rc=json.loads((v/'receipt.json').read_bytes())
    if digest(qb)!=V8_QSHA or digest(rb)!=V8_RSHA or rc.get('result_sha256')!=V8_RSHA or rc.get('readback_verified') is not True:raise RuntimeError('v8_pin')
    d=home/'.anytoour-match'/'operations'/DICT_OP;db=(d/'result.json').read_bytes();dc=json.loads((d/'receipt.json').read_bytes());dd=json.loads(db)
    if digest(db)!=DICT_RSHA or dc.get('result_sha256')!=DICT_RSHA or dc.get('readback_verified') is not True:raise RuntimeError('dict_pin')
    op=dd.get('anex_operator') or {}
    if int(op.get('id') or 0)!=ANEX_OPERATOR_ID or not re.search(r'anex|анекс',' '.join(str(op.get(k,'')) for k in ('name','russianName','fullName')),re.I):raise RuntimeError('dict_identity')
    targets=[]
    for r in json.loads(qb).get('rows',[]):
        if r.get('provider')!='anex':continue
        ext=str(r.get('external_hotel_id') or '')
        if ext in NONHOTEL:continue
        local=r.get('candidate_local_id')
        if not re.fullmatch(r'[1-9][0-9]{0,19}',ext) or not isinstance(local,int) or local<=0:raise RuntimeError('target')
        targets.append(r)
    if len(targets)!=97:raise RuntimeError('target_count')
    return targets,{'v8_queue_sha256':V8_QSHA,'v8_result_sha256':V8_RSHA,'dictionary_result_sha256':DICT_RSHA,'anex_operator':op}

def php():return r'''<?php
declare(strict_types=1);error_reporting(0);ob_start();try{$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$raw=(string)getenv('MATCH_LOCAL_IDS');if(!preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D',$raw))throw new RuntimeException('ids');$ids=array_values(array_unique(array_map('intval',explode(',',$raw))));if(!$ids||count($ids)>100)throw new RuntimeException('scope');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');$ph=implode(',',array_fill(0,count($ids),'?'));$args=array_merge($ids,[13]);$q=$db->prepare("SELECT observed_at,source,search_id,country_id,departure_id,hotel_id,tour_id,operator_id,departure_date,nights FROM tour_price_observations WHERE hotel_id IN ($ph) AND operator_id=? AND tour_id IS NOT NULL ORDER BY observed_at DESC,id DESC LIMIT 100000");$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)>=100000)throw new RuntimeException('cap');$db->exec('ROLLBACK');ob_end_clean();echo json_encode(['rows'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit(0);}catch(Throwable $e){try{if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable $x){}ob_end_clean();fwrite(STDERR,'read_failed\n');exit(2);}'''

def db_read(ids):
    env=os.environ.copy();env['MATCH_LOCAL_IDS']=','.join(map(str,ids));p=subprocess.run(['php','-d','display_errors=0','-r',php().replace('<?php','',1)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,env=env,timeout=120)
    if p.returncode:raise RuntimeError('db_read')
    rows=json.loads(p.stdout).get('rows');
    if not isinstance(rows,list):raise RuntimeError('shape')
    return rows

def main():
    if '--self-test' in sys.argv:
        assert ANEX_OPERATOR_ID==13 and len(NONHOTEL)==11 and 'INSERT ' not in php().upper() and 'UPDATE ' not in php().upper() and 'DELETE ' not in php().upper();print('saved-tv-tour-handles-v3 self-test: PASS');return 0
    sha=os.environ.get('MATCH_SOURCE_SHA','')
    if os.environ.get('OPERATION_ID')!=OP or not re.fullmatch(r'[0-9a-f]{40}',sha):raise RuntimeError('guard')
    home=Path.home().resolve();out=home/'.anytoour-match'/'operations'/OP;out.mkdir(mode=0o700,exist_ok=False)
    write(out/'reservation.json',{'operation_id':OP,'source_sha':sha,'state':'reserved_before_db_access','read_only':True,'operator_id':ANEX_OPERATOR_ID,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True})
    targets,pins=pinned_inputs(home);local_ids=sorted({int(x['candidate_local_id']) for x in targets});rows=db_read(local_ids);by=defaultdict(list)
    for x in rows:
        try:hid=int(x.get('hotel_id') or 0);op=int(x.get('operator_id') or 0)
        except:continue
        if op==ANEX_OPERATOR_ID:by[hid].append(x)
    ev=[]
    for t in targets:
        hid=int(t['candidate_local_id']);country=int(t['country_id']);seen=set();handles=[]
        for x in by.get(hid,[]):
            if int(x.get('country_id') or 0)!=country:continue
            tour=str(x.get('tour_id') or '');sid=str(x.get('search_id') or '')
            if not tour or tour in seen:continue
            seen.add(tour);handles.append({'tour_id':tour,'search_id':sid or None,'operator_id':ANEX_OPERATOR_ID,'observed_at':x.get('observed_at'),'departure_id':x.get('departure_id'),'departure_date':x.get('departure_date'),'nights':x.get('nights'),'source':x.get('source')})
        handles.sort(key=lambda x:str(x.get('observed_at') or ''),reverse=True)
        ev.append({'external_anex_id':str(t['external_hotel_id']),'source_name':t.get('source_name'),'search_count':int(t.get('search_count') or 0),'country_id':country,'candidate_local_id':hid,'candidate_name':t.get('candidate_name'),'current_hold_reason':t.get('auto_block_reason'),'saved_anex_tour_handle_count':len(handles),'saved_anex_tour_handles':handles[:20],'route':'saved_anex_tour_detail_candidate' if handles else 'no_saved_anex_tour_handle'})
    ev.sort(key=lambda x:(-x['search_count'],int(x['external_anex_id'])));routes=Counter(x['route'] for x in ev);with_handles=[x for x in ev if x['saved_anex_tour_handle_count']>0]
    result={'schema':'hotel-match-saved-tv-tour-handles/3','operation_id':OP,'source_sha':sha,'state':'read_only_complete','input':pins,'target_count':len(targets),'unique_candidate_local_ids':len(local_ids),'saved_operator13_rows_read':len(rows),'targets_with_saved_anex_handles':len(with_handles),'live_frequency_with_saved_anex_handles':sum(x['search_count'] for x in with_handles),'route_counts':dict(routes),'evidence':ev,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'booking_calls':0,'production_changed':False,'no_replay':True}
    eh=write(out/'evidence.json',{'schema':'hotel-match-saved-tv-tour-handles-rows/3','rows':ev});result['evidence_sha256']=eh;rh=write(out/'result.json',result);write(out/'receipt.json',{'operation_id':OP,'source_sha':sha,'state':'read_only_complete','result_sha256':rh,'evidence_sha256':eh,'readback_verified':digest((out/'result.json').read_bytes())==rh and digest((out/'evidence.json').read_bytes())==eh,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True});print(json.dumps({'target_count':len(targets),'saved_operator13_rows_read':len(rows),'targets_with_saved_anex_handles':len(with_handles),'live_frequency_with_saved_anex_handles':sum(x['search_count'] for x in with_handles),'route_counts':dict(routes),'result_sha256':rh},sort_keys=True));return 0
if __name__=='__main__':
    try:raise SystemExit(main())
    except Exception as e:print(json.dumps({'operation_id':OP,'state':'failed_read_only','reason':type(e).__name__,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True}),file=sys.stderr);raise
