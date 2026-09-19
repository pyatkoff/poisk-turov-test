#!/usr/bin/env python3
import argparse,hashlib,io,json,re,tarfile,zipfile
from pathlib import Path
OP='hotel-match-refresh46-common4-retained-1971-20260919-v1'
FAILED_SHA='a06ad2c06bfc212c3047b5ed88e6d425717969ce628fb67281b8884c9447b35c'
RECON_SHA='83932d0269975b382ceeddb7329c7890b4278d1da6a27502976459e279769c6e'
RECON_RESULT='329321e8f90efc8a9e0d8d50868818888567be61d9dda57b0748a69daf14f3b1'
COMMON={13:'Anex',25:'Fun&Sun (RU)',18:'Biblioglobus',43:'Интурист'}
def sha(b):return hashlib.sha256(b).hexdigest()
def need(x,m):
 if not x:raise RuntimeError(m)
def zmap(path,want):
 b=Path(path).read_bytes();need(sha(b)==want,'zip_sha');z=zipfile.ZipFile(io.BytesIO(b));o={}
 for i in z.infolist():
  need(not i.is_dir() and not i.filename.startswith('/') and '..' not in Path(i.filename).parts and i.filename not in o,'zip_member');o[i.filename]=z.read(i)
 return o
def tmap(b):
 t=tarfile.open(fileobj=io.BytesIO(b),mode='r:gz');o={}
 for m in t.getmembers():need(m.isfile() and m.name not in o,'tar');o[m.name]=t.extractfile(m).read()
 return o
def oid(t):
 o=t.get('operator') or {};return int(o.get('id') or 0) if isinstance(o,dict) else int(t.get('operatorId') or 0)
def build(failed,recon):
 rz=zmap(recon,RECON_SHA);rb=rz['result.json'];need(sha(rb)==RECON_RESULT,'recon_result');r=json.loads(rb);ids={int(x['tv_hotel_id']) for x in r['completed_contexts']};need(len(ids)==46,'ids46')
 fm=zmap(failed,FAILED_SHA);tm=tmap(fm['server.tgz']);pairs=[];covered=set();counts={str(k):0 for k in COMMON};batch_hash={}
 for n,b in tm.items():
  if not re.fullmatch(r'raw-tv-batch-[1-8]\.json',n):continue
  batch_hash[n]=sha(b);d=json.loads(b)
  for h in d['rows']:
   hid=int(h['id'])
   if hid not in ids:continue
   by={}
   for t in h.get('tours') or []:
    if not isinstance(t,dict):continue
    op=oid(t)
    if op not in COMMON:continue
    tid=str(t.get('id') or t.get('tourId') or '').strip();dt=str(t.get('date') or '').strip();nn=int(t.get('nights') or 0)
    if not tid or not re.fullmatch(r'20\d\d-\d\d-\d\d',dt) or nn<=0:continue
    key=(dt,abs(nn-7),tid)
    if op not in by or key<by[op][0]:by[op]=(key,t)
   for op,(key,t) in by.items():
    o=t.get('operator') or {};need(int(o.get('id'))==op and str(o.get('name'))==COMMON[op],'operator_binding')
    pairs.append({'tv_hotel_id':hid,'hotel_name':h.get('name'),'country_id':int(d['country_id']),'tv_operator_id':op,'tv_operator_name':COMMON[op],'tour_id':str(t.get('id') or t.get('tourId')),'departure_date':t['date'],'nights':int(t['nights']),'search_id':int(d['search_id']),'raw_batch_file':n,'raw_batch_sha256':batch_hash[n],'state':'retained_detail_candidate','safe_to_write_now':False})
    covered.add(hid);counts[str(op)]+=1
 pairs.sort(key=lambda x:(x['tv_hotel_id'],x['tv_operator_id']))
 need(len(pairs)==82 and counts=={'13':37,'25':15,'18':5,'43':25},'pair_counts')
 zero=sorted(ids-covered);need(len(covered)==44 and len(zero)==2,'coverage44')
 return {'operation':OP,'state':'completed_offline_retained_common4','source_failed_artifact':10588765891,'source_reconciliation_artifact':10590198805,'provider_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'completed_hotel_count':46,'hotels_with_common4':44,'hotels_without_common4':2,'hotels_without_common4_ids':zero,'pair_count':82,'operator_pair_counts':counts,'tv_operator_bindings':{str(k):v for k,v in COMMON.items()},'pairs':pairs}
def main():
 a=argparse.ArgumentParser();a.add_argument('--failed');a.add_argument('--recon');a.add_argument('--output');a.add_argument('--receipt');a.add_argument('--self-test',action='store_true');x=a.parse_args()
 if x.self_test: need(COMMON[43]=='Интурист' and len(COMMON)==4,'common4');print('REFRESH46_COMMON4_SELFTEST_OK');return
 r=build(x.failed,x.recon);raw=(json.dumps(r,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode();Path(x.output).write_bytes(raw);dig=sha(raw);Path(x.receipt).write_text(json.dumps({'operation':OP,'state':r['state'],'result_sha256':dig,'provider_calls':0,'database_writes':0,'mapping_writes':0},sort_keys=True)+'\n');print(json.dumps({'pairs':82,'covered':44,'counts':r['operator_pair_counts'],'result_sha256':dig},ensure_ascii=False))
if __name__=='__main__':main()
