#!/usr/bin/env python3
from __future__ import annotations
import argparse,json,re,time,urllib.parse,urllib.request,urllib.error
from pathlib import Path

HOSTS={"files.anextour.ru","files.anextour.com","cdn.anextour.ru"}
PATH_RE=re.compile(r"/hotel/([^/]+)/hotel/([^/]+)/o(\d+)(?:/|$)",re.I)
UA="AnyTour-MATCH-media-dual-id/1.0"

def extract(url:str):
    p=urllib.parse.urlparse(url)
    if (p.hostname or '').lower() not in HOSTS:return None
    m=PATH_RE.search(p.path);q=urllib.parse.parse_qs(p.query)
    hc=(q.get('hotelCode') or q.get('hotelcode') or [None])[0]
    if not m or hc is None or not str(hc).isdigit():return None
    return {'source_url':url,'country_slug':m.group(1).lower(),'hotel_slug':m.group(2),'samo_object_id':int(m.group(3)),'anex_hotel_code':int(hc),'evidence':'direct_files_url_dual_id'}

def walk(v,out:set[str]):
    if isinstance(v,dict):
        for z in v.values():walk(z,out)
    elif isinstance(v,list):
        for z in v:walk(z,out)
    elif isinstance(v,str) and 'anextour.' in v and '/hotel/' in v:
        for u in re.findall(r'https://(?:files\.anextour\.(?:ru|com)|cdn\.anextour\.ru)/[^\s"\'<>]+',v,re.I):out.add(u.rstrip('.,);]'))

def get_json(path:str):
    url='https://api.anextour.ru/b2c/hotel?hotel='+urllib.parse.quote(path,safe='')
    req=urllib.request.Request(url,headers={'User-Agent':UA,'Accept':'application/json'})
    with urllib.request.urlopen(req,timeout=25) as r:return json.loads(r.read(6*1024*1024)),url

def main():
    ap=argparse.ArgumentParser();ap.add_argument('--evidence',required=True);ap.add_argument('--out',required=True);ap.add_argument('--delay',type=float,default=.08);ns=ap.parse_args()
    ev=json.loads(Path(ns.evidence).read_text())
    seeds=[]
    for row in ev.get('matched') or []:
        aid=int(row.get('anex_hotel_id') or 0);cid=int(row.get('country_id') or 0)
        for h in row.get('hits') or []:
            d=(h or {}).get('detail') or {}
            if int(d.get('inc') or 0)!=aid:continue
            path=d.get('b2cLink') or d.get('slug') or (h or {}).get('path')
            if isinstance(path,str) and path.strip():
                if not path.startswith('/'):
                    country={1:'egypt',2:'thailand',4:'turkey',8:'uae',9:'vietnam',10:'sri-lanka',12:'maldives',16:'cuba'}.get(cid)
                    path=f'/hotels/{country}/{path.strip("/")}' if country else path
                seeds.append((aid,cid,path));break
    uniq=[];seen=set()
    for x in seeds:
        if x[0] not in seen:seen.add(x[0]);uniq.append(x)
    rows=[];errors=[];urls_total=0
    for aid,cid,path in uniq:
        try:data,api_url=get_json(path)
        except Exception as e:errors.append({'anex_hotel_code':aid,'path':path,'error':type(e).__name__+':'+str(e)[:200]});continue
        urls=set();walk(data,urls);urls_total+=len(urls)
        pairs=[]
        for u in sorted(urls):
            x=extract(u)
            if x:pairs.append(x)
        rows.append({'anex_hotel_code':aid,'country_id':cid,'api_path':path,'api_url':api_url,'media_url_count':len(urls),'direct_pairs':pairs,'pair_count':len(pairs)})
        time.sleep(ns.delay)
    flat=[];pair_keys=set();conflicts={}
    by_anex={};by_samo={}
    for r in rows:
        for p in r['direct_pairs']:
            k=(p['samo_object_id'],p['anex_hotel_code'])
            if k in pair_keys:continue
            pair_keys.add(k);flat.append(p)
            by_anex.setdefault(p['anex_hotel_code'],set()).add(p['samo_object_id'])
            by_samo.setdefault(p['samo_object_id'],set()).add(p['anex_hotel_code'])
    conflicts={'anex_to_multiple_samo':{str(k):sorted(v) for k,v in by_anex.items() if len(v)>1},'samo_to_multiple_anex':{str(k):sorted(v) for k,v in by_samo.items() if len(v)>1}}
    out={'status':'completed','source_seed_count':len(uniq),'api_rows':len(rows),'api_errors':len(errors),'media_url_count':urls_total,'direct_pair_count':len(flat),'unique_anex_with_pair':len(by_anex),'unique_samo_with_pair':len(by_samo),'conflicts':conflicts,'pairs':flat,'rows':rows,'errors':errors,'database_writes':0,'mapping_writes':0}
    Path(ns.out).write_text(json.dumps(out,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps({k:out[k] for k in ('source_seed_count','api_rows','api_errors','media_url_count','direct_pair_count','unique_anex_with_pair','unique_samo_with_pair')},ensure_ascii=False,sort_keys=True))
if __name__=='__main__':main()
