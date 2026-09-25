"""HC-1 D2: actual installed UI with real saved offers, never supplier searches.

Run once from the authorized operations carrier. Empty inventory remains empty.
The operation does not create profiles, offers, quotes, flights or leads.
"""
from __future__ import annotations
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import sys
from datetime import datetime, timedelta, timezone
from urllib.parse import urlencode, urlsplit
from urllib.request import Request, build_opener, HTTPRedirectHandler

ORIGIN = 'https://anytoour.ru'
BASE = '/_preview/search3-local-candidate/'
PAGE = BASE + 'prototype-search/'
IDS = [559,562,563,570,571,572,573,583,584,586,587,606,608,609,615,621,628,632]
PAIRS = dict(zip(IDS, range(4349,4367)))
OUT = Path(os.environ.get('HC1_OUTPUT', 'hc1-d2-evidence'))


def need(condition, reason):
    if not condition:
        raise ValueError(reason)


def same_scope(request, scope):
    if not isinstance(scope, dict) or scope.get('scopeVersion') != 1 or set(scope) != set(request) | {'scopeVersion'}:
        return False
    for k, v in request.items():
        got = scope[k]
        if isinstance(v, list):
            if not isinstance(got, list) or sorted(map(str, v)) != sorted(map(str, got)):
                return False
        elif type(v) is bool:
            if type(got) is not bool or got != v:
                return False
        elif got is None or str(v) != str(got):
            return False
    return True


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, *args):
        raise ValueError('redirect_refused')


def main():
    from playwright.sync_api import sync_playwright
    spec = importlib.util.spec_from_file_location('hc1_reader', Path(__file__).with_name('search3_hotel_content_readback.py'))
    reader = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(reader)
    OUT.mkdir(parents=True, exist_ok=True)
    report = {'operation': 'hc1-actual-ui-d2', 'observedAt': datetime.now(timezone.utc).isoformat(),
              'supplierRequests': 0, 'databaseWrites': 0, 'realLeads': 0, 'inventoryQueries': [],
              'journeys': [], 'sourceReads': [], 'scope': PAIRS,
              'quoteAndFlights': 'not_requested', 'physicalSafari': 'not_checked', 'status': 'started'}
    fetched = 0

    def read(path, payload=None):
        nonlocal fetched
        need(fetched < 8, 'source_read_budget')
        fetched += 1
        headers = {'Accept': 'application/json', 'X-Requested-With': 'AnyTourSearch3'}
        raw = None
        if payload is not None:
            raw = json.dumps(payload).encode()
            headers['Content-Type'] = 'application/json'
        req = Request(ORIGIN+path, data=raw, headers=headers)
        with build_opener(NoRedirect()).open(req, timeout=20) as response:
            need(response.status == 200, 'source_http')
            body = response.read(4*1024*1024+1)
            need(len(body) <= 4*1024*1024, 'source_size')
        data = json.loads(body)
        need(data.get('ok') is True, 'source_not_ok')
        report['sourceReads'].append({'path': path.split('?')[0], 'sha256': hashlib.sha256(body).hexdigest()})
        return data

    try:
        canonical = read(BASE+'data/hotel-details-read-v1.php?'+urlencode([('catalog','anytour')]+[('legacyHotelIds[]',i) for i in IDS]))
        profiles = reader.validate(canonical, IDS, True)
        need(all(profiles[i]['id']==PAIRS[i] for i in IDS), 'cohort_alias_changed')
        saved = read(BASE+'data/hotel-details-read-v1.php?'+urlencode([('hotelIds[]',i) for i in IDS]))
        saved = reader.validate(saved, IDS, False)
        countries = {str(saved[i]['country']['id']) for i in IDS}
        need(len(countries)==1, 'cohort_country_mixed')
        native_country = next(iter(countries))
        departures = read('/data/departures-v1.php')['items']
        moscow = [r for r in departures if (r.get('name') or r.get('russianName'))=='Москва']
        need(len(moscow)==1, 'departure_not_unique')
        departure = str(moscow[0]['id'])
        countries = read(BASE+'data/search3-destination-read-v1.php?'+urlencode({'action':'countries','departureId':departure}))['items']
        choices = [r for r in countries if list(map(str,r.get('tourvisorIds',[])))==[native_country]]
        need(len(choices)==1, 'country_link_not_unique')
        own_country = str(choices[0]['id'])
        today = datetime.now(timezone.utc).date()
        start, end = str(today+timedelta(days=1)), str(today+timedelta(days=21))
        candidates = []
        for n0,n1 in [(1,10),(11,20),(21,28)]:
            params = dict(departureId=departure,countryId=native_country,dateFrom=start,dateTo=end,
                          nightsFrom=n0,nightsTo=n1,adults=2,childs=[],meal='',hotelCategory='',hotelRating='',
                          hotelTypes=[],hotelIds=list(map(str,IDS)),hotelServices=[],arrivalId='',regionIds=[],
                          subregionIds=[],operatorIds=[],priceFrom='',priceTo='',currency='RUB',onlyCharter=False,onlyDirect=False)
            result = read(BASE+'data/search3-local-results-read-v1.php', {'params':params})['data']
            need(same_scope(params,result.get('scope')), 'inventory_scope_mismatch')
            rows = result.get('hotels')
            need(isinstance(rows,list), 'inventory_shape')
            need(all(row.get('anytourHotelId') in PAIRS.values() for row in rows), 'inventory_outside_cohort')
            report['inventoryQueries'].append({'from':start,'to':end,'nightsFrom':n0,'nightsTo':n1,
                                               'hotels':len(rows),'offers':result.get('offerCount'),
                                               'storedOffers':result.get('storedOfferCount')})
            for row in rows:
                for offer in row.get('offers',[]):
                    tour=offer.get('listing',{}).get('tour',{})
                    party=tour.get('party',{})
                    if isinstance(tour.get('checkin'),str) and type(tour.get('nights')) is int and party.get('adults')==2 and party.get('children')==0:
                        candidates.append({'own':row['anytourHotelId'],'old':int(offer['legacyHotelId']),
                                           'day':tour['checkin'],'nights':tour['nights']})
                        break
            if len(candidates)>=3:
                break
        unique = {c['own']:c for c in candidates}
        if not unique:
            report['status']='no_saved_inventory_in_bounded_scope'
            report['actualUiJourney']='not_checked_no_eligible_saved_offer'
            return 0
        selected = sorted(unique.values(),key=lambda c:(not bool(profiles[c['old']].get('description')),c['own']))[:3]
        report['selected'] = selected
        images = {v for row in profiles.values() for v in [row.get('primaryImage'),*row.get('images',[])] if isinstance(v,str) and v.startswith('https://')}
        assets = {}
        with sync_playwright() as pw:
            browser = pw.chromium.launch()
            for target in selected:
                for width in (390,1440):
                    item={'hotelId':target['own'],'legacyHotelId':target['old'],'width':width,'checks':{},
                          'blockedRequests':{},'localReads':0,'imageRequests':0,'scriptHashes':{},'pageErrors':0}
                    report['journeys'].append(item)
                    context=browser.new_context(viewport={'width':width,'height':900},service_workers='block')
                    page=context.new_page()
                    page.set_default_timeout(15000)
                    page.on('pageerror',lambda error: item.__setitem__('pageErrors',item['pageErrors']+1))
                    local_budget=0
                    media_budget=0
                    seen_data=[]
                    def intercept(route):
                        nonlocal local_budget,media_budget
                        request=route.request
                        url=urlsplit(request.url)
                        path=url.path
                        key=None
                        try:
                            allowed=False
                            if request.method=='GET' and request.url in images:
                                media_budget+=1
                                item['imageRequests']=media_budget
                                allowed=media_budget<=100
                            elif url.netloc=='anytoour.ru' and request.method=='GET':
                                is_static=path.startswith(BASE) and (path==PAGE or re.search(r'\.(?:js|css|svg|png|jpg|jpeg|webp|woff2?|ico)$',path))
                                allowed=bool(is_static)
                                if is_static and path!=PAGE:
                                    key=request.url
                                if path in ('/data/departures-v1.php','/data/hotel-search-v1.php',BASE+'data/search3-destination-read-v1.php',BASE+'data/hotel-details-read-v1.php'):
                                    local_budget+=1
                                    allowed=local_budget<=35
                            elif url.netloc=='anytoour.ru' and request.method=='POST' and path==BASE+'data/search3-local-results-read-v1.php':
                                body=request.post_data_json
                                if isinstance(body,dict) and body.get('action')=='meal_catalog':
                                    allowed=body=={'action':'meal_catalog','provider':'tourvisor','scopeKey':'global'}
                                elif isinstance(body,dict) and set(body)=={'params'}:
                                    p=body['params']
                                    allowed=(str(p.get('countryId'))==native_country and str(p.get('departureId'))==departure
                                             and p.get('adults')==2 and p.get('childs')==[]
                                             and p.get('dateFrom')>=start and p.get('dateTo')<=end
                                             and p.get('hotelIds') and set(map(str,p['hotelIds'])) <= {str(target['old'])})
                                local_budget+=1
                                allowed=bool(allowed and local_budget<=35)
                            if not allowed:
                                category='supplier_or_unapproved_transport'
                                if path==BASE+'data/search3-local-results-read-v1.php': category='unapproved_local_scope'
                                item['blockedRequests'][category]=item['blockedRequests'].get(category,0)+1
                                route.fulfill(status=503,content_type='application/json',body='{"ok":false,"error":"Transport disabled for read-only content verification"}')
                                return
                            if key and key in assets:
                                status,headers,body=assets[key]
                            else:
                                response=route.fetch(max_redirects=0,timeout=20000)
                                status,headers,body=response.status,response.headers,response.body()
                                if 300<=status<400:
                                    raise ValueError('redirect_refused')
                                if key and status==200:
                                    assets[key]=(status,headers,body)
                            if path.endswith(('/app.js','/data.js','/search3-canonical-profiles-v1.js')):
                                item['scriptHashes'][path]=hashlib.sha256(body).hexdigest()
                            if request.method=='POST' and path==BASE+'data/search3-local-results-read-v1.php' and request.post_data_json.get('params') and status==200:
                                snapshot=json.loads(body)
                                if snapshot.get('ok') is True:
                                    need(same_scope(request.post_data_json['params'],snapshot['data']['scope']), 'browser_scope_mismatch')
                                    seen_data.append(snapshot['data'])
                            item['localReads']=local_budget
                            headers={k:v for k,v in headers.items() if k.lower() not in ('content-length','content-encoding','transfer-encoding','connection','location')}
                            route.fulfill(status=status,headers=headers,body=body)
                        except Exception:
                            item['transportErrors']=item.get('transportErrors',0)+1
                            route.fulfill(status=503,content_type='application/json',body='{"ok":false,"error":"Read-only verification could not read this response"}')
                    context.route('**/*',intercept)
                    try:
                        query=urlencode({'origin':'Москва','country':own_country,'from':target['day'],'to':target['day'],
                                         'minNights':target['nights'],'maxNights':target['nights'],'adults':2,'ages':'',
                                         'hotel':target['own'],'searched':'1'})
                        page.goto(ORIGIN+PAGE+'?'+query,wait_until='domcontentloaded')
                        card=page.locator(f'#hotel-{target["own"]}')
                        card.wait_for(state='visible',timeout=30000)
                        need(bool(seen_data),'no_actual_local_response')
                        need(page.locator('#cards .hotel-card').count()==1,'wrong_hotel_card')
                        image=card.locator('.hotel-image')
                        image.scroll_into_view_if_needed()
                        page.wait_for_function('(id)=>{const x=document.querySelector(`#hotel-${id} .hotel-image`);return x&&x.complete&&x.naturalWidth>0;}',arg=target['own'])
                        original=card.locator('[data-action="hotel-details"]').inner_text()
                        item['checks']['realCardAndPrimaryPhoto']=True
                        card.locator('[data-action="card-photo"][data-dir="1"]').click()
                        current=image.get_attribute('src')
                        card.locator('[data-action="gallery"]').click()
                        page.locator('#gallery-image').wait_for(state='visible')
                        item['checks']['galleryOpensCurrentFrame']=page.locator('#gallery-image').get_attribute('src')==current
                        count=page.locator('.gallery-thumbs button').count()
                        expected=len(set(v for v in [profiles[target['old']].get('primaryImage'),*profiles[target['old']].get('images',[])] if v))
                        item['galleryAvailable']=count
                        item['galleryInProfile']=expected
                        item['checks']['fullGalleryAvailable']=count==expected
                        page.screenshot(path=str(OUT/f'hotel-{target["own"]}-{width}-gallery.png'))
                        page.locator('#modal [data-action="close-modal"]').click()
                        card.locator('[data-action="hotel-details"]').click()
                        copy=page.locator('.hotel-detail-copy')
                        expected_text=re.sub('<[^>]*>',' ',profiles[target['old']].get('description') or '')
                        actual_text=copy.inner_text()
                        item['checks']['description']=(' '.join(actual_text.split())==' '.join(expected_text.split())) if expected_text.strip() else ('Описание пока не заполнено' in actual_text)
                        page.screenshot(path=str(OUT/f'hotel-{target["own"]}-{width}-description.png'))
                        page.locator('#modal [data-action="close-modal"]').click()
                        card.locator('[data-action="toggle-offers"]').click()
                        offer=card.locator('.offer').first
                        key=offer.get_attribute('data-offer-key')
                        text=offer.inner_text()
                        offer.locator('[data-action="offer"]').click()
                        page.locator('.tour-hero h3').wait_for(state='visible')
                        item['checks']['selectedSameHotel']=page.locator('.tour-hero h3').inner_text() in original
                        item['checks']['selectedPrimaryPhoto']=page.locator('.tour-hero img').get_attribute('src') in images
                        item['selectedOfferKeySha256']=hashlib.sha256(key.encode()).hexdigest()
                        item['checks']['savedPriceClearlyUnconfirmed']=page.locator('.saved-tour-notice').count()==1
                        page.screenshot(path=str(OUT/f'hotel-{target["own"]}-{width}-offer.png'))
                        page.locator('#modal [data-action="close-modal"]').click()
                        item['checks']['returnPreservesOffer']=card.locator(f'.offer[data-offer-key="{key}"]').inner_text()==text
                        item['checks']['returnPreservesPhoto']=image.get_attribute('src')==current
                        page.locator('#sort').select_option('price')
                        item['checks']['sortPreservesPhoto']=card.locator('.hotel-image').get_attribute('src')==current
                        item['checks']['sortPreservesHotel']=page.locator('#cards .hotel-card').count()==1
                        page.screenshot(path=str(OUT/f'hotel-{target["own"]}-{width}-return.png'))
                        item['status']='passed' if all(item['checks'].values()) and item['pageErrors']==0 else 'confirmed_functional_gap'
                    except Exception as exc:
                        item['status']='incomplete'
                        item['errorType']=type(exc).__name__
                        # Do not export exception bodies that could contain URLs or opaque offer tokens.
                        try: page.screenshot(path=str(OUT/f'hotel-{target["own"]}-{width}-incomplete.png'))
                        except Exception: pass
                    finally:
                        context.close()
            browser.close()
        report['status']='actual_ui_checked' if all(x['status']=='passed' for x in report['journeys']) else 'actual_ui_gaps_or_incomplete'
        return 0
    except Exception as exc:
        report['status']='readback_or_execution_incomplete'
        report['errorType']=type(exc).__name__
        return 2
    finally:
        (OUT/'result.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
        print(json.dumps({k:report[k] for k in ('operation','status','inventoryQueries','supplierRequests','databaseWrites')},ensure_ascii=False))


if __name__=='__main__':
    if '--self-test' in sys.argv:
        assert same_scope({'a':[1,2],'b':False},{'a':['2','1'],'b':False,'scopeVersion':1})
        assert not same_scope({'a':[1,2]},{'a':[1],'scopeVersion':1})
        assert not same_scope({'b':False},{'b':0,'scopeVersion':1})
        assert len(PAIRS)==18 and len(set(PAIRS.values()))==18
        print('HC1_D2_SELF_TEST_OK')
    else:
        sys.exit(main())
