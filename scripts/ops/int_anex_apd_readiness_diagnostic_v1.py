#!/usr/bin/env python3
from __future__ import annotations
import http.cookiejar, json, os, re, urllib.error, urllib.request
from pathlib import Path

ENDPOINT='https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php'
PAGE='https://anytoour.ru/_preview/search3-anex-candidate/poisk-turov/'
OUT=Path(os.environ['RUNNER_TEMP'])/'anex-apd-readiness.json'

def post(opener,payload):
    req=urllib.request.Request(ENDPOINT,data=json.dumps(payload,separators=(',',':')).encode(),method='POST',headers={
        'Content-Type':'application/json','Accept':'application/json','Origin':'https://anytoour.ru','Referer':PAGE,
        'Sec-Fetch-Site':'same-origin','User-Agent':'AnyTour-INT-ANEX-APD-diagnostic/1'})
    try:
        with opener.open(req,timeout=90) as r: code=r.status; raw=r.read(2*1024*1024)
    except urllib.error.HTTPError as e: code=e.code; raw=e.read(2*1024*1024)
    data=json.loads(raw.decode());
    if not isinstance(data,dict): raise RuntimeError('response_not_object')
    return code,data

def rows(hotels):
    out=[]
    if not isinstance(hotels,list): return out
    for h in hotels:
        if not isinstance(h,dict) or not isinstance(h.get('local_id'),int) or not isinstance(h.get('tours'),list): continue
        for t in h['tours']:
            if isinstance(t,dict): out.append((h['local_id'],t))
    return out

def write(v): OUT.write_text(json.dumps(v,sort_keys=True,ensure_ascii=False)+'\n')

def main():
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    generation=1789649000
    params={'departureId':'1','countryId':'4','dateFrom':'2026-10-12','dateTo':'2026-10-12','nightsFrom':7,'nightsTo':7,
        'adults':2,'childs':[],'meal':'','hotelCategory':'','hotelRating':'','hotelTypes':[],'hotelIds':[],'hotelServices':[],
        'arrivalId':'','regionIds':[],'subregionIds':[],'operatorIds':[],'priceFrom':'','priceTo':'','currency':'RUB','onlyCharter':False,'onlyDirect':False}
    code,s=post(opener,{'generation':generation,'params':params,'labels':{'from':'Москва','country':'Египет'}})
    if code!=200 or s.get('ok') is not True: raise RuntimeError('search_failed')
    data=s.get('data'); hotels=data.get('hotels') if isinstance(data,dict) else None; initial=rows(hotels)
    search_ref=data.get('search_ref') if isinstance(data,dict) else None
    if not isinstance(search_ref,str) or not re.fullmatch(r'[a-f0-9]{32}',search_ref): raise RuntimeError('search_ref')
    grouped=next(((lid,t) for lid,t in initial if t.get('kind')=='group_minimum' and isinstance(t.get('offer_ref'),str)),None)
    if grouped is None: raise RuntimeError('no_group')
    lid,group=grouped
    code,e=post(opener,{'action':'expand','generation':generation,'search_ref':search_ref,'offer_ref':group['offer_ref'],'local_hotel_id':lid})
    if code!=200 or e.get('ok') is not True: raise RuntimeError('expand_failed')
    edata=e.get('data'); expanded=rows(edata.get('hotels') if isinstance(edata,dict) else None)
    concrete=[(x,t) for x,t in expanded if t.get('kind')=='concrete' and isinstance(t.get('offer_ref'),str) and re.fullmatch(r'anex_online:[a-f0-9]{64}',t['offer_ref'])][:6]
    if not concrete: raise RuntimeError('no_concrete')
    items=[{'offer_ref':t['offer_ref'],'local_hotel_id':x} for x,t in concrete]
    code,b=post(opener,{'action':'additional_prices_batch','generation':generation,'search_ref':search_ref,'items':items})
    if code!=200 or b.get('ok') is not True: raise RuntimeError('batch_failed:'+str(code)+':'+str(b.get('error')))
    bd=b.get('data'); offers=bd.get('offers') if isinstance(bd,dict) else None
    if not isinstance(offers,list) or len(offers)!=len(items): raise RuntimeError('batch_shape')
    summaries=[]
    for row in offers:
        app=row.get('additional_prices') if isinstance(row,dict) else None
        summaries.append({
            'status':row.get('status') if isinstance(row,dict) else None,
            'ready':row.get('finalPriceReady') is True if isinstance(row,dict) else False,
            'retryable':row.get('retryable') is True if isinstance(row,dict) else False,
            'retry_reason':row.get('retry_reason') if isinstance(row,dict) else None,
            'application_state':app.get('application_state') if isinstance(app,dict) else None,
            'total_count':app.get('total_count') if isinstance(app,dict) else None,
            'row_count':len(app.get('rows')) if isinstance(app,dict) and isinstance(app.get('rows'),list) else None,
            'truncated':app.get('truncated') if isinstance(app,dict) else None,
            'arithmetic_applied':app.get('arithmetic_applied') if isinstance(app,dict) else None,
            'has_search_plus_additional':isinstance(app,dict) and isinstance(app.get('search_plus_additional'),dict),
            'has_adult_rate':isinstance(app,dict) and isinstance(app.get('rates'),dict) and isinstance(app['rates'].get('adult'),dict),
        })
    out={'status':'complete','search_http':200,'mapped_hotels':len(hotels) if isinstance(hotels,list) else 0,
        'grouped_tours':sum(1 for _,t in initial if t.get('kind')=='group_minimum'),'expand_http':200,'expanded_tours':len(expanded),
        'concrete_considered':len(concrete),'batch_http':200,'batch_status':bd.get('status') if isinstance(bd,dict) else None,
        'ready_count':sum(1 for x in summaries if x['ready']),'applications':summaries,
        'supplier_searches':1,'expansion_calls':1,'apd_contexts_max':len(concrete),'lead_calls':0,'booking_calls':0,'metrika_writes':0,'mapping_writes':0}
    write(out); print(json.dumps(out,sort_keys=True,ensure_ascii=False)); return 0

if __name__=='__main__': raise SystemExit(main())
