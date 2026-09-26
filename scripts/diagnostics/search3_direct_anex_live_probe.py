"""One direct-ANEX Search3 POST; exports only safe aggregate outcome."""
import json, urllib.request, urllib.error
from pathlib import Path
URL='https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php'
PARAMS={'departureId':'1','countryId':'4','dateFrom':'2026-10-10','dateTo':'2026-10-16','nightsFrom':7,'nightsTo':7,'adults':2,'childs':[],'meal':'','hotelCategory':'','hotelRating':'','hotelTypes':[],'hotelIds':[],'hotelServices':[],'arrivalId':'','regionIds':[],'subregionIds':[],'operatorIds':[],'priceFrom':'','priceTo':'','currency':'RUB','onlyCharter':False,'onlyDirect':False}
SAFE={'supplier_timeout','supplier_unavailable','rate_limited','temporarily_unavailable','search_not_supported','invalid_request','not_found'}
def summarize(status,payload):
    result={'status':'complete','httpStatus':status,'ok':payload.get('ok') if isinstance(payload,dict) and isinstance(payload.get('ok'),bool) else None}
    if not isinstance(payload,dict): return result
    err=payload.get('error')
    if err: result['error']=err if isinstance(err,str) and err in SAFE else 'other_error'
    data=payload.get('data')
    if not isinstance(data,dict): return result
    if data.get('provider')=='anex': result['provider']='anex'
    hotels=data.get('hotels')
    if isinstance(hotels,list) and len(hotels)<=4800:
        result['hotels']=len(hotels)
        result['offers']=sum(len(h['tours']) for h in hotels if isinstance(h,dict) and isinstance(h.get('tours'),list))
    for key in ('received_offers','mapped_offers','pages_read'):
        if type(data.get(key)) is int and 0<=data[key]<=4800: result[key]=data[key]
    if type(data.get('first_page_only')) is bool: result['first_page_only']=data['first_page_only']
    return result

def main():
    out=Path('search3-direct-anex-live-probe');out.mkdir(exist_ok=True)
    body=json.dumps({'action':'search','generation':1,'params':PARAMS},separators=(',',':')).encode()
    req=urllib.request.Request(URL,data=body,method='POST',headers={'Content-Type':'application/json','Accept':'application/json','Sec-Fetch-Site':'same-origin','X-Requested-With':'AnyTourSearch3','User-Agent':'AnyTour-Search3-Acceptance/1'})
    status=0;payload=None
    try:
        with urllib.request.urlopen(req,timeout=120) as r:
            status=r.status; payload=json.load(r)
    except urllib.error.HTTPError as e:
        status=e.code
        try: payload=json.load(e)
        except Exception: payload=None
    result=summarize(status,payload)
    (out/'result.json').write_text(json.dumps(result,ensure_ascii=False,indent=2))
    print(json.dumps(result,ensure_ascii=False))
    return 0 if status in (200,422,429,502,503) and (result.get('provider')=='anex' or result.get('error') in SAFE or result.get('error')=='other_error') else 1
if __name__=='__main__': raise SystemExit(main())
