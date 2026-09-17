#!/usr/bin/env python3
"""Offline Chromium component tests. All hotels/offers/images are explicitly fictional fixtures.
Requires: playwright, installed Chromium, project v2 source and an explicit baseline renderer path.
No production requests, supplier requests, SQL, or lead submission are made.
"""
from pathlib import Path
from urllib.parse import urlparse
import os, json, sys, hashlib, base64, subprocess, mimetypes
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
PAYLOAD=ROOT/'v2'
CODE='\n'.join((PAYLOAD/name).read_text() for name in ['search3-canonical-profiles-v1.js','results-renderer-v5.js','search3-hotel-details-presentation-v1.js'])
ORIGINAL=Path(os.environ['SEARCH3_BASE_RENDERER']).read_text()
SCOPED_CSS=json.loads(subprocess.check_output(['php','-r', 'require '+json.dumps(str(PAYLOAD/'bundle-manifest-v1.php'))+'; echo json_encode(v2_bundle_files("css","search3"));'],text=True))
CSS_FILES=SCOPED_CSS+['search3-results-filters-v1.css','search3-entry-v1.css','search3-results-cards-v2.css','search3-selected-flow-v2.css']
CSS='\n'.join((PAYLOAD/f).read_text() for f in CSS_FILES)
LOGOS={p.name:'data:'+(mimetypes.guess_type(p.name)[0] or 'application/octet-stream')+';base64,'+base64.b64encode(p.read_bytes()).decode() for p in (PAYLOAD/'assets/operator-logos').iterdir() if p.suffix in ['.png','.svg']}

RESULTS=[]
def test_image(label):
    svg=f'<svg xmlns="http://www.w3.org/2000/svg" width="800" height="480"><rect width="800" height="480" fill="#e5e7eb"/><text x="30" y="230" font-size="24">Тестовое фото каталога {label}</text></svg>'
    return 'data:image/svg+xml;base64,'+base64.b64encode(svg.encode()).decode()

def check(ok,label):
    RESULTS.append({'check':label,'pass':bool(ok)})
    if not ok: raise AssertionError(label)

def profile(own,name=None):
    return {'id':own,'catalog':'anytour','revision':1,'name':name or f'Наш тестовый отель {own}',
            'description':'Описание из нашего каталога. Тестовые данные, не реальное предложение.',
            'detailsAvailable':True,'category':5,'rating':4.8,'country':{'name':'Тестовая страна'},
            'region':{'name':'Наш курорт'},'primaryImage':test_image(str(own)+' A'),
            'images':[test_image(str(own)+' A'),test_image(str(own)+' B')],
            'hotelInformation':{'services':['Детский клуб'], 'meals':['Питание в ресторане'], 'roomTypes':'Описание номеров отеля'}}

def hotel(legacy,provider='tourvisor',suffix=''):
    t={'id':f'{provider}:offer-{legacy}{suffix}','provider':provider,'price':125000+legacy,'date':'2026-10-05','nights':7,
       'meal':{'name':'HB+'},'roomType':'Standard Sea View','placement':'DBL',
       'operator':{'name':'ANEX' if provider=='anex' else 'Coral Travel'},'isCharter':True}
    if provider=='andromeda':
        t.update(offerRef='offer_'+str(legacy).zfill(64),offerContext={'provider':'andromeda','marker':'unchanged'},
                 fuelIncluded=True,quoteRequired=True,selectionEnabled=False)
    return {'id':str(legacy),'provider':provider,'mappingStatus':'resolved','name':'НЕЛЬЗЯ: название поставщика',
            'country':{'name':'НЕЛЬЗЯ: страна поставщика'},'region':{'name':'НЕЛЬЗЯ: курорт поставщика'},
            'picturelink':'https://fixture.example/supplier.svg','category':2,'rating':1,'seaDistance':999,'tours':[t],'price':t['price']}

SETUP=r'''window.__requests=[];window.__emitted=[];window.__projected=[];
window.fetch=(url,options)=>new Promise((resolve,reject)=>{window.__requests.push({url:String(url),options,resolve,reject});});
window.V2Runtime={fetch:(url,options)=>window.fetch(url,options)};
window.addEventListener('v2:results-rendered',e=>window.__emitted.push(e.detail.items));
window.addEventListener('v2:hotel-offers-toggle',e=>window.__emitted.push({expansion:e.detail}));
window.Search3LocalHotelFilter={project:items=>{window.__projected.push(items);return items;}};
window.__resolve=(index,data,status=200)=>window.__requests[index].resolve({ok:status>=200&&status<300,status,json:async()=>data});
'''
FILTER=(PAYLOAD/'search3-results-filters-v1.js').read_text()
CONTROLLER=(PAYLOAD/'tour-controller-v4.js').read_text()
LIFECYCLE=(PAYLOAD/'search-lifecycle-v6.js').read_text()
PROVIDER=(PAYLOAD/'andromeda-provider-v1.js').read_text()
OUT=Path(os.environ.get('SEARCH3_EVIDENCE_DIR',str(ROOT/'canonical-card-evidence')));OUT.mkdir(parents=True,exist_ok=True)
HTML='''<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'''+CSS+'''</style></head><body class="search3-candidate"><main class="v2-shell"><p>Компонентный тест · вымышленные отели и предложения</p><form id="tourSearch" hidden></form><section id="status" class="status" hidden></section><section id="resultsTools" class="results-tools results-tools--ds2"><div><strong>Предложения</strong><span id="resultSummary">Актуальные варианты</span></div><div class="results-tools__actions"><label>Сортировка <select id="sortResults"><option value="price">Сначала дешевле</option><option value="rating">По рейтингу</option></select></label></div></section><div class="results-layout"><aside class="results-filter-rail" aria-label="Фильтры результатов"></aside><section id="results" class="results" aria-busy="false"></section></div><section id="selectedTour" class="selected-tour" hidden tabindex="-1"></section></main></body></html>'''

def boot(browser,path='/_preview/search3-local-candidate/poisk-turov/',width=1440,original=False):
    context=browser.new_context(viewport={'width':width,'height':980},device_scale_factor=1)
    context.route('**/*',lambda route:route.abort())
    page=context.new_page();page.set_default_timeout(3000);errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
    # No navigation: Chromium policy denies every network destination in this runtime.
    # Render local HTML in about:blank; only the location dependency is an explicit fixture.
    page.set_content(HTML)
    page.add_script_tag(content=SETUP)
    page.evaluate("""({code,path})=>{
      const fixtureLocation=new URL(path,'https://fixture.invalid/');
      const fixtureWindow=new Proxy(window,{
        get(target,key){if(key==='location')return fixtureLocation;const value=Reflect.get(target,key,target);return typeof value==='function'?value.bind(target):value;},
        set(target,key,value){target[key]=value;return true;}
      });
      new Function('window',code)(fixtureWindow);
    }""",{'code':ORIGINAL if original else CODE,'path':path})
    return context,page,errors

def render(page,items):
    page.evaluate('(items)=>{window.__source=items;window.__before=JSON.stringify(items);V2Results.render(items);}',items)
    page.wait_for_timeout(30)

def resolve(page,index,links,missing=(),profiles=None,status=200,override=None):
    ids=page.evaluate('(i)=>new URL(__requests[i].url,"https://fixture.invalid/").searchParams.getAll("legacyHotelIds[]").map(Number)',index)
    owned=profiles or [profile(i) for i in dict.fromkeys(links.values())]
    data={'ok':True,'source':'anytour-canonical-catalog','catalog':'anytour','requestedLegacyIds':ids,'items':owned,
          'links':[{'legacyHotelId':int(k),'anytourHotelId':v} for k,v in links.items()], 'missingLegacyIds':list(missing)}
    if override:data.update(override)
    page.evaluate('(args)=>__resolve(...args)',[index,data,status]);page.wait_for_timeout(80)

with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH') or None,headless=True,args=['--no-sandbox'])
    for width in [375,768,1440]:
        c,page,errors=boot(browser,width=width)
        items=[hotel(102),hotel(106,'anex'),hotel(108,'andromeda')];render(page,items)
        check(page.locator('.hotel-card').count()==0,f'{width}: no supplier first paint')
        check('НЕЛЬЗЯ' not in page.locator('#results').inner_text(),f'{width}: no supplier text while loading')
        check(page.evaluate('__requests.length')==1,f'{width}: one batch for three legacy IDs')
        resolve(page,0,{102:1,106:1,108:1})
        check(page.locator('.hotel-card').count()==1,f'{width}: one AnyTour card from three sources')
        check(page.locator('.hotel-card').get_attribute('data-anytour-hotel-id')=='1',f'{width}: explicit own card identity')
        check(page.locator('.hotel-title').inner_text()=='Наш тестовый отель 1',f'{width}: own hotel title')
        check('НЕЛЬЗЯ' not in page.locator('#results').inner_text(),f'{width}: no supplier metadata after hydration')
        check(page.evaluate('JSON.stringify(__source)===__before'),f'{width}: input offers are byte-equivalent')
        check(page.evaluate('V2Results.state.items[0].tours.every((t,i)=>t===__source[i].tours[0])'),f'{width}: exact offer objects retained')
        check(page.evaluate('V2Results.state.items[0].seaDistance===undefined'),f'{width}: no unconfirmed supplier sea distance')
        check(page.evaluate('__projected.at(-1)[0].name')=='Наш тестовый отель 1',f'{width}: filter contract receives own metadata')
        if width<=760:
            geometry=page.locator('.hotel-offers-summary').evaluate('''node=>{
                const button=node.querySelector('.tour-more-toggle'),price=node.querySelector('.hotel-price'),b=button.getBoundingClientRect(),p=price.getBoundingClientRect(),card=node.closest('.hotel-card').getBoundingClientRect();
                return {buttonWidth:b.width,buttonHeight:b.height,cardWidth:card.width,buttonTop:b.top,priceBottom:p.bottom,priceColor:getComputedStyle(price).color,buttonColor:getComputedStyle(button).backgroundColor};
            }''')
            check(geometry['buttonWidth']>=geometry['cardWidth']-40 and geometry['buttonHeight']>=50,f'{width}: mobile hotel disclosure is a full-width touch target')
            check(geometry['buttonTop']>=geometry['priceBottom'] and geometry['priceColor']=='rgb(39, 67, 203)',f'{width}: blue group minimum precedes mobile action')
            check(geometry['buttonColor']=='rgb(255, 81, 12)',f'{width}: mobile disclosure follows owner-approved orange reference')
        page.screenshot(path=str(OUT/f'canonical-collapsed-{width}.png'),full_page=True)
        page.locator('.tour-more-toggle').click()
        check(page.locator('.tour-row').count()==3,f'{width}: all three exact offers expand')
        check(page.evaluate('Array.from(document.querySelectorAll(".direct-tour")).map(x=>x.dataset.tid)')==[items[0]['tours'][0]['id'],items[1]['tours'][0]['id']],f'{width}: native selection IDs unchanged; Andromeda not enabled by guess')
        page.locator('.hotel-details summary').click()
        check(page.locator('.hotel-description-summary').is_visible(),f'{width}: unique canonical description stays visible when hotel details open')
        check('Описание из нашего каталога' in page.locator('.hotel-description-summary').inner_text(),f'{width}: own description remains readable')
        check(page.locator('.hotel-details-content .hotel-description').count()==0,f'{width}: open hotel details never duplicate the canonical description')
        old=page.locator('.hotel-gallery-main').get_attribute('src');page.locator('.hotel-gallery-thumb').first.click()
        check(page.locator('.hotel-gallery-main').get_attribute('src')!=old,f'{width}: thumbnail click changes main photo')
        check('supplier.svg' not in page.locator('#results').inner_html(),f'{width}: provider image never used')
        check(not page.evaluate('document.documentElement.scrollWidth>innerWidth+1'),f'{width}: no document horizontal overflow')
        # Supply exact packaged logos offline, without redrawing them or making requests.
        page.evaluate("""logos=>document.querySelectorAll('img.hotel-operator-logo').forEach(img=>{const name=img.getAttribute('src').split('/').pop();if(logos[name])img.src=logos[name];})""",LOGOS)
        page.screenshot(path=str(OUT/f'canonical-card-{width}.png'),full_page=True)
        check(not errors,f'{width}: no browser JavaScript errors')
        # Sorting never feeds the already grouped view back into the source identity resolver.
        page.select_option('#sortResults','rating');page.wait_for_timeout(30)
        check(page.evaluate('V2Results.state.items[0].tours.length')==3,f'{width}: sort retains source offers')
        check(page.evaluate('__requests.length')==1,f'{width}: sort/expand/gallery do not refetch or call supplier')
        c.close()

    for width in [375,768,1440]:
        c,page,errors=boot(browser,width=width)
        page.add_script_tag(content=FILTER)
        page.evaluate("delete window.__projected;")
        items=[hotel(102),hotel(106,'anex'),hotel(108)]
        for h,meal,price in zip(items,['BB','AI','AI'],[100000,200000,300000]):
            h['tours'][0]['meal']={'name':meal};h['tours'][0]['price']=price;h['price']=price
        render(page,items);resolve(page,0,{102:1,106:1,108:2})
        check(page.locator('.hotel-card').count()==2,f'{width}: real filters receive two own groups')
        if width<1025: page.locator('.search3-mobile-filter-panel > summary').click()
        meal=page.locator('.search3-meal-filter select')
        # Selectors follow the actual exact-label controls, not a test-only filtering function.
        if meal.count()==0: meal=page.locator('select').filter(has=page.locator('option[value="meal:label:ai"]'))
        meal.select_option('meal:label:ai');page.wait_for_timeout(50)
        budget=page.locator('.search3-budget-filter input[type="number"]')
        if budget.count()==0: budget=page.locator('input[type="number"]')
        budget.last.fill('150000');budget.last.dispatch_event('change');page.wait_for_timeout(80)
        check(page.locator('.hotel-card:not([hidden])').count()==0,f'{width}: meal and budget must match SAME offer')
        budget.last.fill('250000');budget.last.dispatch_event('change');page.wait_for_timeout(80)
        check(page.locator('.hotel-card:not([hidden])').count()==1,f'{width}: matching priced offer restores one own card')
        visible=page.locator('.hotel-card:not([hidden])')
        check(visible.locator('.direct-tour').get_attribute('data-tid')==items[1]['tours'][0]['id'],f'{width}: filtered offer keeps source identity')
        check('200' in visible.locator('.hotel-price').inner_text(),f'{width}: price belongs to matching offer, not cheaper BB')
        check(page.evaluate('__requests.length')==1,f'{width}: real local filters do not call supplier or rehydrate')
        page.evaluate('Search3LocalHotelFilter.clear();V2Results.rerender();');page.wait_for_timeout(50)
        if width<1025: page.locator('.search3-mobile-filter-panel > summary').click()
        page.locator('.search3-hotel-filter input').fill('НЕЛЬЗЯ');page.locator('.search3-hotel-filter input').dispatch_event('input');page.wait_for_timeout(50)
        check(page.locator('.hotel-card:not([hidden])').count()==0,f'{width}: name filtering does not use supplier name')
        page.evaluate('Search3LocalHotelFilter.clear();V2Results.rerender();');page.wait_for_timeout(50)
        page.evaluate("""()=>{window.__apiCalls=[];V2Runtime.api=async(action,params)=>{__apiCalls.push({action,params});if(action==='tour'){const tour=__source.flatMap(h=>h.tours).find(t=>t.id===params.tourId);return window.__tourResponse={...tour,hotel:{id:1,name:'НЕЛЬЗЯ: название из supplier details',country:{name:'НЕЛЬЗЯ: страна из details'},region:{name:'НЕЛЬЗЯ: курорт из details'}},hotelDescription:'НЕЛЬЗЯ: описание из supplier details',picture:'https://fixture.example/supplier-detail.svg',adults:2,childs:0,currency:'RUB',departure:{name:'Тестовый город'}};}if(action==='flights')return [];throw new Error('Unexpected action '+action);};}""")
        page.evaluate('window.V2Catalogs={renderChildAges(){},init:async()=>{},handleChange(){},updateServiceCount(){}}')
        page.add_script_tag(content=LIFECYCLE)
        page.add_script_tag(content=CONTROLLER)
        page.locator('.tour-more-toggle').first.click()
        target=page.locator('.direct-tour').filter(has_text='Выбрать тур').first
        tid=target.get_attribute('data-tid');target.click();page.wait_for_timeout(100)
        check(page.evaluate('V2TourController.currentTour.id')==tid,f'{width}: actual controller selects original tour ID')
        selected=page.locator('#selectedTour')
        check(selected.locator('h2').inner_text()=='Наш тестовый отель 1',f'{width}: selected title stays canonical despite coincident supplier hotel ID')
        check('Тестовая страна · Наш курорт' in selected.inner_text(),f'{width}: selected geography stays canonical')
        check(selected.locator('.selected-picture img').get_attribute('src')==profile(1)['primaryImage'],f'{width}: selected photo stays canonical')
        check(selected.locator('.selected-picture img').get_attribute('alt')=='Фото отеля Наш тестовый отель 1',f'{width}: selected canonical photo has useful alternative text')
        check(selected.locator('.hotel-desc').inner_text()==profile(1)['description'],f'{width}: selected description stays canonical')
        check('НЕЛЬЗЯ' not in selected.inner_text(),f'{width}: supplier hotel presentation never replaces AnyTour profile')
        check(page.evaluate('V2TourController.currentTour===__tourResponse && __tourResponse.hotel.id===1 && __tourResponse.hotelDescription.startsWith("НЕЛЬЗЯ")'),f'{width}: presentation does not mutate supplier tour or lead source')
        check('05.10.2026' in selected.inner_text() and 'Standard Sea View' in selected.inner_text(),f'{width}: exact offer date and raw room survive canonical presentation')
        check(not page.evaluate('document.documentElement.scrollWidth>innerWidth+1'),f'{width}: selected canonical photo has no horizontal overflow')
        page.screenshot(path=str(OUT/f'canonical-selected-{width}.png'),full_page=True)

        check(page.evaluate('__apiCalls[0].params.tourId')==tid,f'{width}: native tour request identity unchanged')
        check(set(page.evaluate('__apiCalls.map(x=>x.action)'))<={'tour','flights'},f'{width}: no lead or extra search in controller path')
        page.locator('#selectedTour .back-results').first.click();page.wait_for_timeout(150)
        check(page.locator('#selectedTour').is_hidden(),f'{width}: actual controller returns to results')
        check(page.locator('.hotel-card:not([hidden])').count()==2,f'{width}: own cards survive selected return')
        check(page.evaluate('JSON.stringify(__source)===__before'),f'{width}: filter and selection inputs remain unchanged')
        check(not errors,f'{width}: actual filter/controller have no JS error')
        if width>=1025:
            check(page.evaluate('document.querySelector(".results-filter-rail").getBoundingClientRect().right<=document.getElementById("results").getBoundingClientRect().left+1'),f'{width}: real filter rail does not cover cards')
        check(not page.evaluate('document.documentElement.scrollWidth>innerWidth+1'),f'{width}: integrated page has no horizontal overflow')
        page.evaluate("logos=>document.querySelectorAll('img.hotel-operator-logo').forEach(img=>{const name=img.getAttribute('src').split('/').pop();if(logos[name])img.src=logos[name];})",LOGOS)
        page.screenshot(path=str(OUT/f'canonical-integrated-{width}.png'),full_page=True)
        c.close()

    # Exercise the real Andromeda wrapper with recorded-shape, fictional server data.
    for legacy in [102,106]:
        c,page,errors=boot(browser)
        page.evaluate('window.V2SearchLifecycle={generation:1,snapshot:{from:1,country:4},dirty:false};window.V2_CONFIG={andromedaApi:"/_preview/search3-anex-candidate/api-andromeda-search3-preview.php"};')
        page.evaluate("code=>{const fake=new Proxy(window,{get(t,k){if(k==='location')return new URL('https://fixture.invalid/_preview/search3-local-candidate/poisk-turov/');const v=Reflect.get(t,k,t);return typeof v==='function'?v.bind(t):v;},set(t,k,v){t[k]=v;return true;}});new Function('window',code)(fake);}",PROVIDER)
        page.evaluate('dispatchEvent(new CustomEvent("v2:search-reset",{detail:{generation:1}}))')
        items=[hotel(102),hotel(106,'anex')];render(page,items);resolve(page,1,{102:1,106:1})
        ref='offer_'+'a'*64;context={'provider':'andromeda','search_ref':'b'*64,'generation':1,'page':1,'offer_ref':ref}
        base={'amount':'100000','currency':'RUB'};price={'amount':'110000','currency':'RUB','source':'derived_search_estimate'}
        tour={'provider':'andromeda','offer_ref':ref,'offer_context':context,'price':price,'base_search_price':base,'checkin':'2026-10-05','nights':7,'meal':'AI','room':'Standard','operator':'ANEX','search_surcharge':{'schema_version':1,'provider':'andromeda','state':'estimated','arithmetic_applied':True,'final_price_verified':False,'surcharge_scope':'party','search_price':base,'search_price_with_surcharge':price,'party_surcharge':{'amount':'10000','currency':'RUB','source':'andromeda_get_flights_transport'}}}
        raw={'local_id':legacy,'mapping_status':'resolved','name':'НЕЛЬЗЯ provider','tours':[tour]}
        page.evaluate('(data)=>__resolve(0,data)',{'ok':True,'data':{'provider':'andromeda','generation':1,'page':1,'pages_count':1,'hotels':[raw]}});page.wait_for_timeout(100)
        check(page.locator('.hotel-card').count()==1,f'provider {legacy}: wrapper and own hydration converge')
        check(page.evaluate('V2Results.state.items[0].tours.length')==3,f'provider {legacy}: all admitted offers retained')
        selection=page.evaluate('ref=>AnyTourAndromedaProvider.prepareQuote(ref)',ref)
        check(selection is not None and selection['localId']==legacy,f'provider {legacy}: quote preparation keeps ORIGINAL local hotel ID')
        check(selection['hotel']['anytourHotelId']==1 and selection['hotel']['id']==legacy,f'provider {legacy}: own ID remains separate')
        check(selection['tour']['offerContext']==context and selection['tour']['price']==110000,f'provider {legacy}: context and fuel-inclusive amount unchanged')
        rejected=dict(tour);rejected.pop('search_surcharge')
        check(page.evaluate('x=>AnyTourAndromedaProvider.normalizeTour(x[0],x[1])',[rejected,raw]) is None,f'provider {legacy}: no fuel receipt means no admission')
        page.evaluate('ref=>{window.__detail=AnyTourAndromedaProvider.openDetail(ref)}',ref);page.wait_for_timeout(50)
        detail_index=page.evaluate('__requests.length-1')
        body=page.evaluate('i=>JSON.parse(__requests[i].options.body)',detail_index)
        check(body['action']=='offer_detail' and body['offer_context']==context,f'provider {legacy}: actual detail request retains exact context')
        page.evaluate('(args)=>__resolve(...args)',[detail_index,{'ok':True,'data':{'provider':'andromeda','local_id':legacy,'offer_context':context,'hotel':'НЕЛЬЗЯ provider detail','operator':'ANEX','room':'Standard','meal':'AI','checkin':'2026-10-05','nights':7,'adults':2,'children':0,'price':price}}]);page.wait_for_timeout(80)
        page.evaluate('V2Results.revealOfferAlternatives("andromeda:"+"offer_"+"a".repeat(64))');page.wait_for_timeout(50)
        check('НЕЛЬЗЯ' not in page.locator('#results').inner_text(),f'provider {legacy}: expanded detail does not replace local hotel title')
        page.evaluate('V2Results.state.items[0].canonicalOfferLinks=[]')
        check(page.evaluate('ref=>AnyTourAndromedaProvider.prepareQuote(ref)',ref) is None,f'provider {legacy}: missing origin fails closed, no anchor guess')
        check(not errors,f'provider {legacy}: no JavaScript errors')
        page.evaluate('dispatchEvent(new CustomEvent("v2:search-reset",{detail:{dirty:true}}))')
        c.close()

    c,page,errors=boot(browser);render(page,[hotel(1000+i) for i in range(205)])
    check(page.evaluate('__requests.length')==2,'batching: maximum two concurrent requests')
    check(page.evaluate('__requests.map(x=>new URL(x.url,"https://fixture.invalid/").searchParams.getAll("legacyHotelIds[]").length)')==[100,100],'batching: max 100 IDs per request')
    resolve(page,0,{1000+i:1000+i for i in range(100)})
    check(page.evaluate('__requests.length')==3,'batching: next chunk scheduled on completion')
    resolve(page,1,{1100+i:1100+i for i in range(100)})
    resolve(page,2,{1200+i:1200+i for i in range(5)})
    check(page.locator('.hotel-card').count()==205,'batching: 205 profiles rendered without per-card requests')
    check(page.evaluate('__requests.length')==3,'batching: exactly three requests, not 205');c.close()

    c,page,errors=boot(browser);render(page,[hotel(102)])
    page.evaluate('dispatchEvent(new CustomEvent("v2:search-reset",{detail:{dirty:true}}));document.getElementById("results").innerHTML="";')
    render(page,[hotel(106)])
    resolve(page,0,{102:1})
    check(page.locator('.hotel-card').count()==0,'stale: previous search reply cannot paint')
    resolve(page,1,{106:2});check(page.locator('.hotel-title').inner_text()=='Наш тестовый отель 2','stale: only current search profile appears')
    check(page.evaluate('__requests[0].options.signal.aborted'),'stale: previous request was aborted')
    c.close()

    c,page,errors=boot(browser);render(page,[hotel(102),hotel(106)])
    resolve(page,0,{102:1},missing=[106]);check(page.locator('.hotel-card').count()==1,'missing: uncovered own profile withheld')
    check('Часть предложений' in page.locator('#results').inner_text(),'missing: truthful coverage notice')
    page.evaluate('V2Results.rerender()');page.wait_for_timeout(30)
    check(page.evaluate('__requests.length')==1,'missing: no repeated request within same search')
    page.evaluate('dispatchEvent(new CustomEvent("v2:search-started"));V2Results.render(__source);');page.wait_for_timeout(30)
    check(page.evaluate('__requests.length')==2,'missing: new search rechecks mappings/coverage')
    c.close()

    invalids=[({'links':[{'legacyHotelId':999,'anytourHotelId':1}]},'unrequested legacy link'),
              ({'requestedLegacyIds':[106,102]},'wrong request identity order'),
              ({'missingLegacyIds':[102]},'mapped/missing overlap'),
              ({'items':[profile(1),profile(1)]},'duplicate own profiles'),
              ({'items':[profile(1),profile(2)]},'unlinked own profile'),
              ({'catalog':'tourvisor'},'wrong catalogue'),
              ({'items':[dict(profile(1),revision=0)]},'invalid revision')]
    for change,label in invalids:
        c,page,errors=boot(browser);render(page,[hotel(102),hotel(106)])
        resolve(page,0,{102:1,106:1},override=change)
        check(page.locator('.hotel-card').count()==0,'malformed: '+label+' fails entire batch')
        check(page.locator('.canonical-profile-retry').count()==1,'malformed: '+label+' exposes retry')
        c.close()

    c,page,errors=boot(browser);render(page,[hotel(102)]);resolve(page,0,{},status=503)
    check(page.locator('.hotel-card').count()==0,'failure: no supplier fallback on 503')
    page.locator('.canonical-profile-retry').click();page.wait_for_timeout(30)
    check(page.evaluate('__requests.length')==2,'failure: retry fetches profiles only')
    resolve(page,1,{102:1});check(page.locator('.hotel-card').count()==1,'failure: successful retry recovers card');c.close()

    c,page,errors=boot(browser);items=[hotel(102,'andromeda'),hotel(103,'anex'),hotel(104)]
    items[0]['mappingStatus']='unresolved';items[1].pop('mappingStatus');items[2]['id']='0104';render(page,items)
    check(page.evaluate('__requests.length')==0,'identity: unresolved supplier and nonexact IDs never queried')
    check(page.locator('.hotel-card').count()==0,'identity: no numeric provider-ID guess');c.close()

    for path in ['/poisk-turov/','/_preview/search3-site-candidate/poisk-turov/','/poisk-turov/?catalog=anytour','/_preview/search3-local-candidate-evil/poisk-turov/']:
        out=[]
        for original in [True,False]:
            c,page,errors=boot(browser,path=path,original=original);render(page,[hotel(102)])
            check(page.locator('.hotel-card').count()==1,f'isolation: legacy render unchanged {path} original={original}')
            check(page.evaluate('__requests.every(x=>!x.url.includes("catalog=anytour"))'),f'isolation: no canonical requests {path} original={original}')
            out.append(page.locator('#results').inner_html());c.close()
        check(out[0]==out[1],f'isolation: byte-identical legacy DOM {path}')
    browser.close()
result={'status':'offline_component_verified','checks':len(RESULTS),'passed':sum(x['pass'] for x in RESULTS),'checks_detail':RESULTS,
        'baseline_source_sha':'7482c62f2e6b6cb3d7f202441281868d500652d9','tested_source_sha':os.environ.get('GITHUB_SHA','local-uncommitted'),'browser':'Chromium about:blank; location dependency is a fixture','widths':[375,768,1440],'css_files':CSS_FILES,'css_scope':'actual search3 bundle scope and presentation order','filter_integration':'component tests use spy; integrated cases use actual filter/controller/lifecycle with fixture transport' ,'operator_logos':'unaltered artifact files, embedded offline',
        'real_supplier_requests':0,'real_leads':0,'live_site_acceptance':False,'actual_SQL':False,'hosted_CI':os.environ.get('GITHUB_ACTIONS')=='true',
        'renderer_sha256':hashlib.sha256((PAYLOAD/'results-renderer-v5.js').read_bytes()).hexdigest(),'profile_module_sha256':hashlib.sha256((PAYLOAD/'search3-canonical-profiles-v1.js').read_bytes()).hexdigest()}
(OUT/'browser-results.json').write_text(json.dumps(result,ensure_ascii=False,indent=2))
print(json.dumps({k:v for k,v in result.items() if k!='checks_detail'},ensure_ascii=False,indent=2))
