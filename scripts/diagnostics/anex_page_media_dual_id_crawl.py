#!/usr/bin/env python3
from __future__ import annotations
import argparse,concurrent.futures,html,json,re,urllib.parse,urllib.request
from pathlib import Path

UA='AnyTour-MATCH-page-media-dual-id/2.0'
HOSTS={'files.anextour.ru','files.anextour.com','cdn.anextour.ru'}
PATH_RE=re.compile(r'/hotel/([^/]+)/hotel/([^/]+)/o(\d+)(?:/|$)',re.I)
URL_RE=re.compile(r'(?:https?:\\?/\\?/|//)(?:files\.anextour\.(?:ru|com)|cdn\.anextour\.ru)/[^\s"\'<>]+',re.I)
COUNTRY={1:'egypt',2:'thailand',4:'turkey',8:'uae',9:'vietnam',10:'sri-lanka',12:'maldives',16:'cuba'}

def norm_url(raw:str)->str:
    u=html.unescape(raw).replace('\\/','/')
    if u.startswith('//'):u='https:'+u
    elif not u.startswith('http'):u='https://'+u.lstrip('/')
    return u.rstrip('.,);]\\')

def extract(url:str):
    p=urllib.parse.urlparse(url)
    if (p.hostname or '').lower() not in HOSTS:return None
    m=PATH_RE.search(p.path); q=urllib.parse.parse_qs(p.query)
    hc=(q.get('hotelCode') or q.get('hotelcode') or [None])[0]
    if not m or hc is None or not str(hc).isdigit():return None
    return {'source_url':url,'country_slug':m.group(1).lower(),'hotel_slug':m.group(2),'samo_object_id':int(m.group(3)),'anex_hotel_code':int(hc),'evidence':'direct_hotel_page_media_url_dual_id'}

def get(url:str,timeout:float):
    req=urllib.request.Request(url,headers={'User-Agent':UA,'Accept':'text/html,application/xhtml+xml,*/*'})
    with urllib.request.urlopen(req,timeout=timeout) as r:
        return r.geturl(),r.read(8*1024*1024),int(r.status),r.headers.get('content-type')

def page_candidates(row:dict):
    aid=int(row.get('anex_hotel_id') or 0); cid=int(row.get('country_id') or 0)
    if not aid or cid not in COUNTRY:return None
    for h in row.get('hits') or []:
        d=(h or {}).get('detail') or {}
        if int(d.get('inc') or 0)!=aid:continue
        vals=[]
        for v in (d.get('b2cLink'),d.get('slug'),(h or {}).get('path')):
            if not isinstance(v,str) or not v.strip():continue
            v=v.strip()
            if v.startswith('http'): vals.append(v)
            elif v.startswith('/'): vals.append('https://anextour.ru'+v)
            else: vals.append(f'https://anextour.ru/hotels/{COUNTRY[cid]}/{v.strip("/")}')
        if vals:return {'anex_hotel_code':aid,'country_id':cid,'page_candidates':list(dict.fromkeys(vals))}
    return None

def crawl_one(s:dict,timeout:float):
    aid=s['anex_hotel_code']; urls=set(); meta=[]
    for page in s['page_candidates']:
        try:final,body,status,ctype=get(page,timeout)
        except Exception as e:
            meta.append({'page':page,'error':type(e).__name__+':'+str(e)[:160]});continue
        text=body.decode(errors='replace'); found=[]
        for raw in URL_RE.findall(text):
            u=norm_url(raw)
            if 'hotelCode=' in u or 'hotelcode=' in u:found.append(u);urls.add(u)
        meta.append({'page':page,'final_url':final,'http':status,'content_type':ctype,'bytes':len(body),'media_candidates':len(found)})
        if found:break
    pairs=[]
    for u in sorted(urls):
        p=extract(u)
        if p and int(p['anex_hotel_code'])==aid:pairs.append(p)
    return {'anex_hotel_code':aid,'country_id':s['country_id'],'pages':meta,'media_url_count':len(urls),'direct_pairs':pairs,'pair_count':len(pairs)}

def main():
    ap=argparse.ArgumentParser();ap.add_argument('--evidence',required=True);ap.add_argument('--out',required=True);ap.add_argument('--workers',type=int,default=24);ap.add_argument('--timeout',type=float,default=7.0);ns=ap.parse_args()
    ev=json.loads(Path(ns.evidence).read_text())
    uniq={}
    for row in ev.get('matched') or []:
        s=page_candidates(row)
        if s:uniq.setdefault(s['anex_hotel_code'],s)
    rows=[]
    with concurrent.futures.ThreadPoolExecutor(max_workers=max(1,ns.workers)) as ex:
        futs=[ex.submit(crawl_one,s,ns.timeout) for s in uniq.values()]
        for f in concurrent.futures.as_completed(futs):rows.append(f.result())
    rows.sort(key=lambda r:r['anex_hotel_code'])
    flat=[]; seen=set(); by_anex={}; by_samo={}
    for r in rows:
        for p in r['direct_pairs']:
            k=(p['samo_object_id'],p['anex_hotel_code'])
            if k not in seen:seen.add(k);flat.append(p)
            by_anex.setdefault(p['anex_hotel_code'],set()).add(p['samo_object_id'])
            by_samo.setdefault(p['samo_object_id'],set()).add(p['anex_hotel_code'])
    conflicts={'anex_to_multiple_samo':{str(k):sorted(v) for k,v in by_anex.items() if len(v)>1},'samo_to_multiple_anex':{str(k):sorted(v) for k,v in by_samo.items() if len(v)>1}}
    out={'status':'completed','source_seed_count':len(uniq),'page_rows':len(rows),'media_url_count':sum(r['media_url_count'] for r in rows),'direct_pair_count':len(flat),'unique_anex_with_pair':len(by_anex),'unique_samo_with_pair':len(by_samo),'conflicts':conflicts,'pairs':flat,'rows':rows,'database_writes':0,'mapping_writes':0}
    Path(ns.out).write_text(json.dumps(out,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps({k:out[k] for k in ('source_seed_count','page_rows','media_url_count','direct_pair_count','unique_anex_with_pair','unique_samo_with_pair')},ensure_ascii=False,sort_keys=True))
if __name__=='__main__':main()
