#!/usr/bin/env python3
"""Mass read-only saved Tourvisor tour-handle inventory for MATCH #1971.

Consumes the completed CURRENT v8 unresolved queue and reads only existing DB
observations. It does not call Tourvisor or any supplier and never writes DB state.
"""
from __future__ import annotations
import hashlib,json,os,re,subprocess,sys
from collections import Counter,defaultdict
from pathlib import Path
from typing import Any

OP='hotel-match-saved-tv-tour-handles-1971-20260915-v1'
V8='hotel-match-manual-live-queue-1971-20260914-v8'
QSHA='f17ca8e8d9d3945f6aea3d232fca96708fc6f119c504461f95a4cc51af8ed3ed'
RSHA='52acba203042e86a1650ece5b0ae079dac6cfb49f25b5bc6747cdaefd316b09d'
NONHOTEL={'17097','817','28869','5173','815','29000','17194','17195','17196','17443','20963'}
ANEX=re.compile(r'\b(?:anex|анекс)\b',re.I)

def dump(x): return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def h(raw): return hashlib.sha256(raw).hexdigest()
def write(path,obj):
    raw=dump(obj); fd=os.open(path,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
    try:
        with os.fdopen(fd,'wb',closefd=False) as f: f.write(raw); f.flush(); os.fsync(f.fileno())
    finally: os.close(fd)
    if path.read_bytes()!=raw: raise RuntimeError('readback')
    return h(raw)

def targets(home:Path):
    p=home/'.anytoour-match'/'operations'/V8
    qr=(p/'queue.json').read_bytes(); rr=(p/'result.json').read_bytes(); receipt=json.loads((p/'receipt.json').read_bytes())
    if h(qr)!=QSHA or h(rr)!=RSHA or receipt.get('readback_verified') is not True or receipt.get('result_sha256')!=RSHA: raise RuntimeError('v8_pin')
    q=json.loads(qr); out=[]
    for r in q.get('rows',[]):
        if r.get('provider')!='anex': continue
        ext=str(r.get('external_hotel_id') or '')
        if ext in NONHOTEL: continue
        local=r.get('candidate_local_id')
        if not re.fullmatch(r'[1-9][0-9]{0,19}',ext) or not isinstance(local,int) or local<=0: raise RuntimeError('target')
        out.append(r)
    if len(out)!=97: raise RuntimeError('target_count')
    return out

def php(): return r'''<?php
declare(strict_types=1); error_reporting(0); ob_start();
function rows(PDO $db,string $table,array $ids,array $cols):array{
  $exists=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$exists->execute([$table]);if((int)$exists->fetchColumn()!==1)return [];
  $ph=implode(',',array_fill(0,count($ids),'?'));$sql='SELECT '.implode(',',$cols).' FROM '.$table.' WHERE hotel_id IN ('.$ph.') ORDER BY hotel_id DESC LIMIT 100000';$q=$db->prepare($sql);$q->execute($ids);$r=$q->fetchAll(PDO::FETCH_ASSOC);if(count($r)>=100000)throw new RuntimeException('cap');return $r;
}
try{$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$raw=(string)getenv('MATCH_LOCAL_IDS');if(!preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D',$raw))throw new RuntimeException('ids');$ids=array_values(array_unique(array_map('intval',explode(',',$raw))));if(!$ids||count($ids)>100)throw new RuntimeException('scope');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
$price=rows($db,'tour_price_observations',$ids,['observed_at','source','search_id','country_id','hotel_id','tour_id','operator_id','departure_date','nights']);
$hot=rows($db,'hot_tours_current',$ids,['fetched_at','country_id','hotel_id','tour_id','operator_id','operator_name','departure_date','nights']);
$dict=[];$tables=['tour_operator_identity_observations','hot_tours_current'];foreach($tables as $t){$ex=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$ex->execute([$t]);if((int)$ex->fetchColumn()!==1)continue;$q=$db->query('SELECT operator_id,operator_name,COUNT(*) n FROM '.$t.' WHERE operator_id IS NOT NULL AND operator_name IS NOT NULL AND operator_name<>\'\' GROUP BY operator_id,operator_name ORDER BY n DESC LIMIT 1000');foreach($q->fetchAll(PDO::FETCH_ASSOC) as $x)$dict[]=$x+['source_table'=>$t];}
$db->exec('ROLLBACK');ob_end_clean();echo json_encode(['price'=>$price,'hot'=>$hot,'operator_dictionary'=>$dict],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit(0);}catch(Throwable $e){try{if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable $x){}ob_end_clean();fwrite(STDERR,'read_failed\n');exit(2);}'''

def read(ids):
    env=os.environ.copy();env['MATCH_LOCAL_IDS']=','.join(map(str,ids));p=subprocess.run(['php','-d','display_errors=0','-r',php().replace('<?php','',1)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,env=env,timeout=120)
    if p.returncode: raise RuntimeError('db_read')
    d=json.loads(p.stdout)
    if not isinstance(d,dict): raise RuntimeError('shape')
    return d

def main():
    if '--self-test' in sys.argv:
        assert ANEX.search('ANEX Tour') and ANEX.search('Анекс') and not ANEX.search('Intourist'); assert 'INSERT ' not in php().upper() and 'UPDATE ' not in php().upper() and 'DELETE ' not in php().upper();print('saved-tv-tour-handles self-test: PASS');return 0
    sha=os.environ.get('MATCH_SOURCE_SHA','');
    if os.environ.get('OPERATION_ID')!=OP or not re.fullmatch(r'[0-9a-f]{40}',sha): raise RuntimeError('guard')
    home=Path.home().resolve();base=home/'.anytoour-match'/'operations';out=base/OP;out.mkdir(mode=0o700,exist_ok=False)
    write(out/'reservation.json',{'operation_id':OP,'source_sha':sha,'state':'reserved_before_db_access','read_only':True,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True})
    ts=targets(home); ids=sorted({int(x['candidate_local_id']) for x in ts}); d=read(ids)
    names=defaultdict(Counter)
    for x in d.get('operator_dictionary',[]):
        if x.get('operator_id') is not None and x.get('operator_name'): names[int(x['operator_id'])][str(x['operator_name'])]+=int(x.get('n') or 0)
    anex_ids=sorted(i for i,c in names.items() if any(ANEX.search(n) for n in c))
    by=defaultdict(list)
    for source in ('price','hot'):
        for x in d.get(source,[]):
            try: hid=int(x.get('hotel_id') or 0);opid=int(x.get('operator_id') or 0)
            except: continue
            if hid>0 and opid in anex_ids:
                y=dict(x);y['saved_source_table']='tour_price_observations' if source=='price' else 'hot_tours_current';by[hid].append(y)
    ev=[]
    for t in ts:
        hid=int(t['candidate_local_id']);seen=set();handles=[]
        for x in by.get(hid,[]):
            tour=str(x.get('tour_id') or '')
            if not tour or tour in seen: continue
            seen.add(tour);handles.append({'tour_id':tour,'operator_id':int(x['operator_id']),'operator_names':sorted(names[int(x['operator_id'])]),'country_id':int(x.get('country_id') or 0),'departure_date':x.get('departure_date'),'nights':x.get('nights'),'source_table':x['saved_source_table'],'observed_at':x.get('observed_at') or x.get('fetched_at')})
        ev.append({'external_anex_id':str(t['external_hotel_id']),'source_name':t.get('source_name'),'search_count':int(t.get('search_count') or 0),'country_id':int(t['country_id']),'candidate_local_id':hid,'candidate_name':t.get('candidate_name'),'current_hold_reason':t.get('auto_block_reason'),'saved_anex_tour_handles':handles[:20],'saved_anex_tour_handle_count':len(handles),'route':'saved_anex_tour_detail_candidate' if handles else 'no_saved_anex_tour_handle'})
    ev.sort(key=lambda x:(-x['search_count'],int(x['external_anex_id'])));routes=Counter(x['route'] for x in ev)
    result={'schema':'hotel-match-saved-tv-tour-handles/1','operation_id':OP,'source_sha':sha,'state':'read_only_complete','target_count':len(ts),'unique_candidate_local_ids':len(ids),'operator_dictionary':{str(i):dict(c) for i,c in names.items()},'identified_anex_operator_ids':anex_ids,'price_rows_read':len(d.get('price',[])),'hot_rows_read':len(d.get('hot',[])),'route_counts':dict(routes),'evidence':ev,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'booking_calls':0,'production_changed':False,'no_replay':True}
    eh=write(out/'evidence.json',{'schema':'hotel-match-saved-tv-tour-handles-rows/1','rows':ev});result['evidence_sha256']=eh;rh=write(out/'result.json',result);write(out/'receipt.json',{'operation_id':OP,'source_sha':sha,'state':'read_only_complete','result_sha256':rh,'evidence_sha256':eh,'readback_verified':h((out/'result.json').read_bytes())==rh and h((out/'evidence.json').read_bytes())==eh,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True});print(json.dumps({'target_count':len(ts),'identified_anex_operator_ids':anex_ids,'price_rows_read':len(d.get('price',[])),'hot_rows_read':len(d.get('hot',[])),'route_counts':dict(routes),'result_sha256':rh},sort_keys=True));return 0
if __name__=='__main__':
    try: raise SystemExit(main())
    except Exception as e: print(json.dumps({'operation_id':OP,'state':'failed_read_only','reason':type(e).__name__,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True}),file=sys.stderr);raise
