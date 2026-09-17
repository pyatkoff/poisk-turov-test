#!/usr/bin/env python3
from __future__ import annotations
import http.cookiejar, json, os, re, urllib.error, urllib.request
from pathlib import Path

ENDPOINT='https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php'
PAGE='https://anytoour.ru/_preview/search3-anex-candidate/poisk-turov/'
OUT=Path(os.environ['RUNNER_TEMP'])/'anex-turkey-live.json'

def post(opener,payload):
    req=urllib.request.Request(ENDPOINT,data=json.dumps(payload,separators=(',',':')).encode(),method='POST',headers={
        'Content-Type':'application/json','Accept':'application/json','Origin':'https://anytoour.ru','Referer':PAGE,
        'Sec-Fetch-Site':'same-origin','User-Agent':'AnyTour-INT-ANEX-Turkey-APD/1'})
    try:
        with opener.open(req,timeout=90) as r: code=r.status; raw=r.read(2*1024*1024)
    except urllib.error.HTTPError as e: code=e.code; raw=e.read(2*1024*1024)
    data=json.loads(raw.decode())
    if not isinstance(data,dict): raise RuntimeError('response_not_object')
    return code,data

def projected(hotels):
    out=[]
    if not isinstance(hotels,list): return out
    for h in hotels:
        if not isinstance(h,dict) or not isinstance(h.get('local_id'),int) or not isinstance(h.get('tours'),list): continue
        for t in h['tours']:
            if isinstance(t,dict): out.append((h['local_id'],t))
    return out

def save(v): OUT.write_text(json.dumps(v,sort_keys=True,ensure_ascii=False)+'\n')

def main():
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    generation=1789649800
    params={'departureId':'1','countryId':'1','dateFrom':'2026-09-21','dateTo':'2026-09-21','nightsFrom':7,'nightsTo':7,
        'adults':2,'childs':[],'meal':'7','hotelCategory':'','hotelRating':'','hotelTypes':[],'hotelIds':['6319'],'hotelServices':[],
        'arrivalId':'','regionIds':[],'subregionIds':[],'operatorIds':[],'priceFrom':'','priceTo':'','currency':'RUB','onlyCharter':False,'onlyDirect':False}
    summary={'status':'starting','supplier_searches':0,'expansion_calls':0,'apd_contexts':0,'lead_calls':0,'booking_calls':0,'metrika_writes':0,'mapping_writes':0}
    save(summary)
    code,s=post(opener,{'generation':generation,'params':params,'labels':{'from':'Москва','country':'Турция'}})
    summary.update({'supplier_searches':1,'search_http':code})
    d=s.get('data') if isinstance(s,dict) else None; hotels=d.get('hotels') if isinstance(d,dict) else None; rows=projected(hotels)
    summary.update({'mapped_hotels':len(hotels) if isinstance(hotels,list) else 0,'projected_tours':len(rows),'grouped_tours':sum(1 for _,t in rows if t.get('kind')=='group_minimum'),'concrete_before_expand':sum(1 for _,t in rows if t.get('kind')=='concrete'),'status':'search_received'})
    save(summary)
    if code!=200 or s.get('ok') is not True or not isinstance(d,dict): raise RuntimeError('search_failed')
    ref=d.get('search_ref')
    if not isinstance(ref,str) or not re.fullmatch(r'[a-f0-9]{32}',ref): raise RuntimeError('search_ref')
    if not rows: raise RuntimeError('no_tours')
    concrete=next(((lid,t) for lid,t in rows if t.get('kind')=='concrete' and isinstance(t.get('offer_ref'),str)),None)
    if concrete is None:
        group=next(((lid,t) for lid,t in rows if t.get('kind')=='group_minimum' and isinstance(t.get('offer_ref'),str)),None)
        if group is None: raise RuntimeError('no_expandable_group')
        lid,t=group
        code,e=post(opener,{'action':'expand','generation':generation,'search_ref':ref,'offer_ref':t['offer_ref'],'local_hotel_id':lid})
        summary.update({'expansion_calls':1,'expand_http':code})
        ed=e.get('data') if isinstance(e,dict) else None; erows=projected(ed.get('hotels') if isinstance(ed,dict) else None)
        summary.update({'expanded_tours':len(erows),'concrete_after_expand':sum(1 for _,x in erows if x.get('kind')=='concrete'),'status':'expanded'})
        save(summary)
        if code!=200 or e.get('ok') is not True: raise RuntimeError('expand_failed')
        concrete=next(((x,y) for x,y in erows if y.get('kind')=='concrete' and isinstance(y.get('offer_ref'),str) and re.fullmatch(r'anex_online:[a-f0-9]{64}',y['offer_ref'])),None)
    if concrete is None: raise RuntimeError('no_concrete')
    lid,t=concrete
    code,b=post(opener,{'action':'additional_prices_batch','generation':generation,'search_ref':ref,'items':[{'offer_ref':t['offer_ref'],'local_hotel_id':lid}]})
    summary.update({'apd_contexts':1,'batch_http':code})
    bd=b.get('data') if isinstance(b,dict) else None; offers=bd.get('offers') if isinstance(bd,dict) else None; row=offers[0] if isinstance(offers,list) and offers and isinstance(offers[0],dict) else None
    app=row.get('additional_prices') if isinstance(row,dict) and isinstance(row.get('additional_prices'),dict) else {}
    summary.update({'batch_status':bd.get('status') if isinstance(bd,dict) else None,'final_price_ready':1 if isinstance(row,dict) and row.get('finalPriceReady') is True else 0,'application_state':app.get('application_state'),'total_count':app.get('total_count'),'row_count':len(app.get('rows')) if isinstance(app.get('rows'),list) else None,'truncated':app.get('truncated'),'arithmetic_applied':app.get('arithmetic_applied'),'retryable':row.get('retryable') if isinstance(row,dict) else None,'retry_reason':row.get('retry_reason') if isinstance(row,dict) else None,'status':'batch_received'})
    save(summary)
    if code!=200 or b.get('ok') is not True: raise RuntimeError('batch_failed')
    if not isinstance(row,dict) or row.get('finalPriceReady') is not True: raise RuntimeError('price_not_ready')
    price=row.get('finalPrice')
    if not isinstance(price,str) or not re.fullmatch(r'(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?',price) or not re.search(r'[1-9]',price): raise RuntimeError('bad_final_price')
    summary.update({'status':'http_acceptance_complete','final_price_currency':'RUB'})
    save(summary); print(json.dumps(summary,sort_keys=True,ensure_ascii=False)); return 0

if __name__=='__main__': raise SystemExit(main())
