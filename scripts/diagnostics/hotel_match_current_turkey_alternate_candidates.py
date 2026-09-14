#!/usr/bin/env python3
from __future__ import annotations
import hashlib,json,os,subprocess,sys
from pathlib import Path

TRIED={16330:28426,23894:1600,34680:37404,45225:153743,28882:129511,32783:54633,32880:17443,44573:59085,35275:81810,43984:99257,16605:115500,44939:77557,32692:70291,44413:21838,28869:82811,32745:85422,32572:68169,37885:111046,32724:17586,32686:17390,29000:104021,30424:43550,32567:82420,33110:104168,45226:163543,15072:77400,32742:132803,34804:81578,39389:1557,43077:1558,32743:129511,29446:17586,32355:21838,28933:17390,44138:53531,44139:67042,8550:1283,10115:2160,11241:1255,11756:996,12143:1004,12901:1474,14812:5535,15046:1217,20470:101227,21696:1070,27777:17561,29036:132803,29332:60388,29430:71266,31515:1346,32822:82758,32937:76112,34858:60766,34961:27553,35511:28581,35898:1221,37344:3409,37538:1461,37985:77759,35319:1346,39875:1071,8319:1124,8355:1432,8366:17595,8460:1385,8537:1643,8538:1207,8666:1253,9126:1570,9384:77574}

def dump(x):return json.dumps(x,ensure_ascii=False,sort_keys=True,separators=(',',':'))
def write_new(p:Path,x)->str:
    raw=(json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode(); fd=os.open(p,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
    try:
        with os.fdopen(fd,'wb') as f:f.write(raw);f.flush();os.fsync(f.fileno())
    except: raise
    if p.read_bytes()!=raw:raise RuntimeError('readback_failed')
    return hashlib.sha256(raw).hexdigest()

def reader_php(ids:list[int])->str:
    csv=','.join(map(str,ids))
    return f'''<?php
    declare(strict_types=1); error_reporting(0); ob_start();
    function q(PDO $d,string $s):array{{if(!str_starts_with(ltrim($s),'SELECT ')||str_contains($s,';'))throw new RuntimeException('select_only');return $d->query($s)->fetchAll(PDO::FETCH_ASSOC);}}
    try{{$r=realpath(getcwd());if(!$r||basename($r)!=='anytoour.ru')throw new RuntimeException('root');require_once $r.(is_file($r.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$d=v2_data_db();$d->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$d->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$d->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');$ids='{csv}';$o=[];$o['map']=q($d,"SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ids)");$o['manual']=q($d,"SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ids)");$o['excl']=q($d,"SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ids)");$o['cand']=q($d,"SELECT * FROM anex_hotel_candidates WHERE anex_hotel_id IN ($ids) AND candidate_rank<=5 ORDER BY anex_hotel_id,candidate_rank");$o['obs']=q($d,"SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($ids)");$o['stage']=q($d,"SELECT * FROM anex_hotels WHERE anex_hotel_id IN ($ids)");$cids=[];foreach($o['cand'] as $x)if(isset($x['catalog_hotel_id']))$cids[(int)$x['catalog_hotel_id']]=1;$list=$cids?implode(',',array_keys($cids)):'0';$o['catalog']=q($d,"SELECT id,country_id,country_name,name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($list)");$d->exec('ROLLBACK');ob_end_clean();echo json_encode($o,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}}catch(Throwable $e){{try{{if(isset($d)&&$d->inTransaction())$d->exec('ROLLBACK');}}catch(Throwable $x){{}}ob_end_clean();fwrite(STDERR,'read_failed\n');exit(2);}}'''

def main():
    if '--self-test' in sys.argv:
        assert len(TRIED)==71 and len(set(TRIED.values()))<71 and all(k>0 and v>0 for k,v in TRIED.items());print('alternate-candidates self-test: PASS');return 0
    op=os.environ.get('OPERATION_ID',''); sha=os.environ.get('MATCH_SOURCE_SHA','')
    if op!='hotel-match-current-turkey-alternate-candidates-1971-20260914-v1' or len(sha)!=40:raise RuntimeError('identity_guard')
    root=Path.cwd().resolve(); base=Path.home()/'.anytoour-match'/'operations'; out=base/op
    if root.name!='anytoour.ru' or not base.is_dir():raise RuntimeError('root_guard')
    out.mkdir(mode=0o700,exist_ok=False); write_new(out/'reservation.json',{'operation_id':op,'source_sha':sha,'state':'reserved_before_current_db','read_only':True,'input_identities':71,'supplier_calls':0,'tourvisor_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True})
    p=subprocess.run(['php','-d','display_errors=0','-r',reader_php(sorted(TRIED)) .replace('<?php','',1)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=120)
    if p.returncode:raise RuntimeError('current_db_read_failed')
    doc=json.loads(p.stdout); mapped={int(x['anex_hotel_id']) for x in doc['map']}; manual={int(x['anex_hotel_id']) for x in doc['manual']}; excl={(int(x['anex_hotel_id']),int(x['catalog_hotel_id'])) for x in doc['excl']}; cat={int(x['id']):x for x in doc['catalog'] if int(x.get('is_active') or 0)==1 and int(x.get('country_id') or 0)==4}; obs={int(x['anex_hotel_id']):x for x in doc['obs']}; stage={int(x['anex_hotel_id']):x for x in doc['stage']}
    by={a:[] for a in TRIED};
    for x in doc['cand']:
        a=int(x.get('anex_hotel_id') or 0); c=int(x.get('catalog_hotel_id') or 0); rank=int(x.get('candidate_rank') or 0)
        if a not in by or c not in cat or (a,c) in excl:continue
        by[a].append((rank,c,x))
    rows=[]; unique=set(); with_alt=0
    for a,tried in TRIED.items():
        if a in mapped or a in manual:continue
        src=obs.get(a) or stage.get(a) or {}; alts=[]
        for rank,c,x in sorted(by[a],key=lambda z:(z[0],z[1])):
            if c==tried:continue
            h=cat[c]; alts.append({'candidate_rank':rank,'tourvisor_hotel_id':c,'name':h.get('name'),'region':h.get('region_name'),'subregion':h.get('subregion_name'),'category':h.get('category')});unique.add(c)
        if alts:with_alt+=1
        rows.append({'anex_hotel_id':a,'source_name':src.get('hotel_name') or src.get('api_name') or src.get('xml_name'),'live_search_count':int((obs.get(a) or {}).get('search_count') or 0),'tried_top_hotel_id':tried,'alternate_candidates':alts})
    ids=sorted(unique); batches=[ids[i:i+30] for i in range(0,len(ids),30)]
    result={'schema':'hotel-match-current-turkey-alternate-candidates/1','operation_id':op,'source_sha':sha,'status':'read_only_complete','input_identities':71,'current_unresolved_identities':len(rows),'identities_with_alternates':with_alt,'unique_alternate_hotel_ids':len(ids),'alternate_batches':[{'batch':i+1,'count':len(v),'hotelIds':v} for i,v in enumerate(batches)],'rows':sorted(rows,key=lambda r:(-r['live_search_count'],r['anex_hotel_id'])),'database_writes':0,'mapping_writes':0,'supplier_calls':0,'tourvisor_calls':0,'no_replay':True}
    rh=write_new(out/'result.json',result);write_new(out/'receipt.json',{'operation_id':op,'source_sha':sha,'state':'read_only_complete','result_sha256':rh,'readback_verified':hashlib.sha256((out/'result.json').read_bytes()).hexdigest()==rh,'database_writes':0,'mapping_writes':0,'supplier_calls':0,'tourvisor_calls':0,'no_replay':True});print('MATCH_ALT_RESULT:'+dump({k:result[k] for k in ['current_unresolved_identities','identities_with_alternates','unique_alternate_hotel_ids','alternate_batches']}));return 0
if __name__=='__main__':raise SystemExit(main())
