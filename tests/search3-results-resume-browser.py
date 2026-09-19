#!/usr/bin/env python3
"""Offline same-tab reload/re-entry acceptance. All offers and transports fictional."""
from pathlib import Path
from urllib.parse import urlencode
import json, os, re, subprocess
from playwright.sync_api import sync_playwright, expect

ROOT=Path(__file__).resolve().parents[1]
V2=ROOT/'v2'
OUT=Path(os.environ.get('SEARCH3_EVIDENCE_DIR', str(ROOT/'canonical-card-evidence')))
OUT.mkdir(parents=True,exist_ok=True)
KEY='anytour:search3:completed:v1'
BASE='https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/'
QUERY=urlencode([('from','1'),('country','4'),('dateFrom','2030-10-05'),('dateTo','2030-10-07'),('daysFrom','7'),('daysTill','9'),('count_people','2'),('child_count','2'),('child_age[]','0'),('child_age[]','17')])
CSS_FILES=json.loads(subprocess.check_output(['php','-r','require '+json.dumps(str(V2/'bundle-manifest-v1.php'))+'; echo json_encode(v2_bundle_files("css","search3"));'],text=True))
PAGE_STYLE=re.search(r'<style>(.*?)</style>',(V2/'index.php').read_text(),re.S).group(1)
CSS=PAGE_STYLE+'\n'+'\n'.join((V2/f).read_text() for f in CSS_FILES+['search3-entry-v1.css','search3-results-cards-v2.css','search3-results-filters-v1.css','search3-selected-flow-v2.css'])
SOURCE_FILES=['search3-canonical-profiles-v1.js','results-renderer-v5.js','current-price-calendar-v1.js','search-lifecycle-v6.js','search3-results-continuity-v1.js','search3-hotel-details-presentation-v1.js']
CODE='\n'.join((V2/f).read_text() for f in SOURCE_FILES)
FORM=(V2/'search3-results-filters-v1.js').read_text()
# Only local development may substitute the readable shell pending its pinned build.
# CI defaults to the checked-in generated asset, not this source substitution.
if os.environ.get('SEARCH3_READABLE_FORM')=='1':
    marker='}();!function'
    assert marker in FORM
    FORM=(ROOT/'src/search3/behavior/search-form.js').read_text()+'\n!function'+FORM.split(marker,1)[1]
CODE+='\n'+FORM+'\n'+(V2/'tour-controller-v4.js').read_text()
HOTELS=[]
for i in range(1,5):
    tours=[]
    for j,provider in enumerate(['tourvisor','anex','andromeda']*3):
        tours.append({'id':str(700+i*10+j),'provider':provider,'price':120000+i*1000+j*100,'date':'2030-10-05','nights':7,'adults':2,'childs':2,'meal':{'name':'AI'},'roomType':'STANDARD ROOM','operator':{'name':'Coral Travel'},'placement':'2AD+2CHD','selectionEnabled':provider=='tourvisor','offerRef':'PRIVATE-OFFER-REF-'+str(j) if provider!='tourvisor' else None,'context':{'secret':'PRIVATE-CONTEXT'},'quoteToken':'PRIVATE-QUOTE'})
    HOTELS.append({'id':100+i,'provider':'tourvisor','mappingStatus':'resolved','tours':tours})
PROFILES=[{'id':9000+i,'catalog':'anytour','revision':1,'detailsAvailable':True,'name':'Тестовый отель '+str(i),'description':'Вымышленное подробное описание отеля. '*12,'category':5,'rating':4.5,'country':{'name':'Тестовая страна'},'region':{'name':'Тестовый регион'},'hotelInformation':{'services':['Бассейн']},'images':[],'phone':'PRIVATE-PHONE'} for i in range(1,5)]
BOOT=r'''window.__calls=[];window.__events=[];
['v2:search-reset','v2:search-started','v2:search-complete','v2:search-resumed'].forEach(name=>addEventListener(name,()=>__events.push(name)));
window.__hotels=HOTELS;window.__profiles=PROFILES;
window.V2Runtime={setSearchId(id){this.searchId=id},api:async(action,params)=>{
 __calls.push({action,params});
 if(action==='search_start')return window.__holdStart?new Promise(resolve=>window.__releaseStart=resolve):{searchId:731};
 if(action==='search_status')return{status:'complete',progress:100};
 if(action==='search_results')return __hotels;
 if(action==='tour')throw new Error('Fixture exact-tour revalidation reached');
 throw new Error('Unexpected API '+action);
}};
window.V2Catalogs={init:async()=>{},handleChange:async()=>{},updateServiceCount(){},renderChildAges(){}};
window.fetch=async(url)=>{
 if(!String(url).includes('hotel-details-read-v1.php'))throw new Error('Unexpected fetch');
 __calls.push({action:'profiles'});
 const ids=new URL(url,location.href).searchParams.getAll('legacyHotelIds[]');
 return{ok:true,json:async()=>({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,missingLegacyIds:[],items:ids.map(id=>__profiles[Number(id)-101]),links:ids.map(id=>({legacyHotelId:Number(id),anytourHotelId:9000+Number(id)-100}))})};
};
'''.replace('HOTELS',json.dumps(HOTELS,ensure_ascii=False)).replace('PROFILES',json.dumps(PROFILES,ensure_ascii=False))
FIELDS='''<label class="field">Вылет<select name="from"><option value="1">Москва</option><option value="2">Другой город</option></select></label><label class="field">Страна<select name="country"><option value="4">Тестовая страна</option><option value="9">Другая страна</option></select></label>'''
for name,value in [('dateFrom','2030-10-05'),('dateTo','2030-10-07'),('daysFrom','7'),('daysTill','9'),('count_people','2'),('child_count','2'),('child_age[]','0'),('child_age[]','17')]:
    FIELDS+=f'<input name="{name}" value="{value}" aria-label="{name}">'
HTML='''<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'''+CSS+'''</style></head><body class="search3-candidate"><main class="v2-shell"><p>Компонентная проверка · вымышленные данные</p><form id="tourSearch">'''+FIELDS+'''<button class="primary" type="submit">Найти туры</button><button type="button" class="search-editor-collapse">К результатам</button></form><section id="resultsTripContext" hidden><strong data-search3-trip-route></strong><span data-search3-trip-details></span></section><button id="resultsSearchEdit" type="button">Изменить поиск</button><section id="status" hidden></section><section id="resultsTools" class="results-tools"><span id="resultSummary"></span><div class="results-tools__actions"><select id="sortResults"><option value="price">По цене</option><option value="name">По названию</option></select></div></section><div class="results-layout"><aside class="results-filter-rail"></aside><section id="results" class="results"></section></div><section id="selectedTour" hidden></section><div style="height:500px"></div></main><script>'''+BOOT+'\n'+CODE.replace('</script',r'<\/script')+'''</script></body></html>'''
REPORT=[]
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_PATH') or None)
    for width in [375,768,1440]:
        ctx=browser.new_context(viewport={'width':width,'height':900})
        ctx.route('**/*',lambda route: route.fulfill(status=200,content_type='text/html',body=HTML) if route.request.resource_type=='document' else route.abort())
        offline=os.environ.get('SEARCH3_OFFLINE_DOCUMENTS')=='1'
        memory={};pages=[];errors=[]
        def open_page(url):
            if offline:
                if pages:
                    old=pages.pop();old.evaluate("dispatchEvent(new Event('pagehide'))")
                    memory.update(old.evaluate('window.__memoryStore'));old.close()
                current=ctx.new_page();pages.append(current);current.set_default_timeout(6000)
                current.on('pageerror',lambda error: errors.append(str(error)))
                current.set_content(HTML.split('<script>')[0]+'</body></html>')
                current.evaluate(r"""({code,url,memory})=>{
                  window.__memoryStore=memory;const storage={getItem:k=>memory[k]??null,setItem:(k,v)=>memory[k]=String(v),removeItem:k=>delete memory[k]};
                  Object.defineProperty(window,'sessionStorage',{get:()=>storage,configurable:true});
                  const loc=new URL(url);window.__fakeLocation=loc;
                  const hist={state:null,replaceState(state,unused,url){this.state=state;if(url)loc.href=url},pushState(state,unused,url){this.state=state;if(url)loc.href=url}};
                  const view=new Proxy(window,{get(t,k){if(k==='location')return loc;if(k==='history')return hist;const v=Reflect.get(t,k,t);return typeof v==='function'?v.bind(t):v},set(t,k,v){t[k]=v;return true}});
                  new Function('window','location','sessionStorage',code)(view,loc,storage);
                }""",{'code':BOOT+'\n'+CODE,'url':url,'memory':memory.copy()})
                return current
            if not pages:
                current=ctx.new_page();current.set_default_timeout(6000);current.on('pageerror',lambda error: errors.append(str(error)));pages.append(current)
            current=pages[0];current.goto(url);return current
        page=open_page(BASE+'?'+QUERY+'&yclid=PRIVATE-ATTRIBUTION&utm_source=PRIVATE-UTM')
        expect(page.locator('.hotel-card')).to_have_count(4)
        page.wait_for_function('sessionStorage.getItem('+json.dumps(KEY)+')!==null')
        snapshot=page.evaluate('JSON.parse(sessionStorage.getItem('+json.dumps(KEY)+'))')
        encoded=json.dumps(snapshot,ensure_ascii=False)
        assert 'PRIVATE-' not in encoded, 'No lead, attribution or provider secrets may be persisted'
        # Desktop presents this field through its separate sort controls.
        page.locator('#sortResults').evaluate("n=>{n.value='name';n.dispatchEvent(new Event('change',{bubbles:true}))}")
        page.locator('.hotel-details > summary').nth(1).click()
        expanded=page.locator('.hotel-card').nth(1)
        expanded.locator('.tour-more-toggle').click()
        expanded.locator('.tour-list-more').click()
        expect(expanded.locator('.tour-row')).to_have_count(6)
        page.evaluate('window.scrollTo(0,400)')
        page.wait_for_timeout(150)
        page.evaluate('Search3ResultsContinuityV1.captureSnapshot()')
        snapshot=page.evaluate('JSON.parse(sessionStorage.getItem('+json.dumps(KEY)+'))')
        assert snapshot['view']['renderer']['sort']=='name'
        assert ['102',6] in snapshot['view']['renderer']['limits'], 'remember all displayed offers, including providers without selection buttons'
        initial_created=snapshot['createdAt'];original_query=snapshot['query']
        # Existing LOCAL controls keep ownership of predicates; resume sends the
        # same input event as a user edit instead of implementing another filter.
        page.locator('.search3-hotel-filter input').evaluate("n=>{n.value='отель 2';n.dispatchEvent(new Event('input',{bubbles:true}))}")
        page.wait_for_timeout(150)
        page.evaluate('Search3ResultsContinuityV1.captureSnapshot()')
        if offline:
            page=open_page(page.evaluate('__fakeLocation.href'))
        else:
            page.reload()
        expect(page.locator('.hotel-card')).to_have_count(4)
        assert page.evaluate('__calls')==[], 'reload must not start/status/results/profile-fetch a new search'
        assert page.evaluate('__events')==['v2:search-resumed'], 'no fake provider lifecycle events'
        assert page.evaluate('V2SearchLifecycle.searchId')==731
        assert page.evaluate('V2SearchLifecycle.restoredAt')==initial_created
        assert page.locator('#sortResults').input_value()=='name'
        expect(page.locator('.hotel-details').nth(1)).to_have_attribute('open','')
        expect(page.locator('.hotel-card').nth(1).locator('.tour-row')).to_have_count(6)
        assert 'сохранённая выдача' in page.locator('#resultSummary').inner_text()
        assert 'цены из текущего поиска' not in page.locator('#resultSummary').inner_text()
        assert set(page.locator('.hotel-card').nth(1).locator('.tour-action > small').all_text_contents())=={'Цена из поиска'}, 'restored rows cannot claim a current total'
        assert page.locator('.provider-detail-toggle').count()==0, 'no saved provider context becomes selection authority'
        page.wait_for_function('(key)=>{const v=JSON.parse(sessionStorage.getItem(key)).view;if(!v.anchor)return Math.abs(scrollY-v.scrollY)<5;const card=[...document.querySelectorAll(".hotel-card")].find(c=>c.dataset.hotelId===v.anchor.hotel);return card&&Math.abs(card.getBoundingClientRect().top-v.anchor.top)<5}',arg=KEY)
        assert 'Выдача восстановлена' in page.locator('#status').inner_text()
        expect(page.locator('#resultsTripContext')).to_be_visible()
        assert page.locator('.search3-hotel-filter input').input_value()=='отель 2'
        expect(page.locator('.hotel-card:not([hidden])')).to_have_count(1)
        expect(page.locator('.hotel-card:visible')).to_have_count(1)
        assert page.evaluate('V2SearchLifecycle.snapshot.childs')==[0,17]
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'no overflow'
        page.screenshot(path=str(OUT/f'resume-{width}.png'),full_page=True)
        # A second resumed visit preserves changed view state without refreshing prices.
        original_hotels=page.evaluate('JSON.parse(sessionStorage.getItem('+json.dumps(KEY)+')).hotels')
        page.locator('.search3-hotel-filter input').evaluate("n=>{n.value='отель 3';n.dispatchEvent(new Event('input',{bubbles:true}))}")
        assert page.evaluate('Search3ResultsContinuityV1.captureSnapshot()')
        page=open_page(BASE+'?'+QUERY)
        expect(page.locator('.hotel-card:not([hidden])')).to_have_count(1)
        expect(page.locator('.hotel-card:visible')).to_have_count(1)
        assert page.locator('.search3-hotel-filter input').input_value()=='отель 3'
        assert page.evaluate('__calls')==[]
        unchanged=page.evaluate('JSON.parse(sessionStorage.getItem('+json.dumps(KEY)+'))')
        assert unchanged['createdAt']==initial_created and unchanged['hotels']==original_hotels
        # Select a saved TV locator: the unchanged controller must fetch it anew.
        # These mixed-provider cards use in-page alternatives, not a hotel-tab link.
        page.locator('.search3-hotel-filter input').evaluate("n=>{n.value='';n.dispatchEvent(new Event('input',{bubbles:true}))}")
        page.locator('.tour-more-toggle').first.click()
        page.locator('.direct-tour').first.click()
        page.wait_for_function('__calls.some(call=>call.action==="tour")')
        assert [c for c in page.evaluate('__calls') if c['action']=='tour'][0]['params']['tourId']=='710'
        assert page.evaluate('V2TourController.currentTour') is None, 'a stored price never becomes the selected quote'
        # Going away and returning to the same explicit query preserves the session snapshot.
        page=open_page(BASE+'?'+QUERY)
        expect(page.locator('.hotel-card')).to_have_count(4)
        assert page.evaluate('__calls')==[]
        assert page.evaluate('V2SearchLifecycle.restoredAt')==initial_created, 'restore must not extend the TTL'
        if not offline:
            page.goto(BASE.replace('/poisk-turov/','/resume-return-fixture/'))
            page.go_back()
            expect(page.locator('.hotel-card')).to_have_count(4)
            assert page.evaluate('__calls')==[], 'native browser Back cannot restart supplier search'
            assert page.evaluate('V2SearchLifecycle.restoredAt')==initial_created
        # Bare re-entry hydrates the saved form instead of starting a search.
        page=open_page(BASE)
        expect(page.locator('.hotel-card')).to_have_count(4)
        assert page.evaluate('__calls')==[]
        assert page.evaluate('V2SearchLifecycle.restoreQuery')==original_query
        assert page.evaluate('V2SearchLifecycle.snapshot.childs')==[0,17]
        valid=page.evaluate('sessionStorage.getItem('+json.dumps(KEY)+')')
        mutations=[
          "d.version=99", "d.createdAt=Date.now()-1800001", "d.createdAt=Date.now()+60000",
          "d.route='/poisk-turov/'", "d.query+='&yclid=forbidden'", "d.searchId='bad'",
          "d.hotels[0].hotel.id=0", "d.hotels[0].tours[0].date='2030-02-31'",
          "d.hotels[0].tours[0].provider='foreign'", "d.hotels[0].tours[0].price=-10",
        ]
        for mutation in mutations:
            page.evaluate("({key,valid})=>{const d=JSON.parse(valid);"+mutation+";sessionStorage.setItem(key,JSON.stringify(d))}",{'key':KEY,'valid':valid})
            assert page.evaluate('Search3ResultsContinuityV1.readSnapshot()') is None, mutation
        page.evaluate('(key)=>sessionStorage.setItem(key,"broken JSON")',KEY)
        assert page.evaluate('Search3ResultsContinuityV1.readSnapshot()') is None
        page.evaluate('({key,valid})=>sessionStorage.setItem(key,valid)',{'key':KEY,'valid':valid})
        # A tampered authority field is discarded rather than promoted to a quote.
        page.evaluate('(key)=>{const d=JSON.parse(sessionStorage.getItem(key));d.hotels[0].tours[1].offerRef="PRIVATE-INJECTED";d.hotels[0].tours[1].booking_enabled=true;sessionStorage.setItem(key,JSON.stringify(d))}',KEY)
        checked=page.evaluate('Search3ResultsContinuityV1.readSnapshot()')
        assert 'PRIVATE-INJECTED' not in json.dumps(checked)
        assert 'booking_enabled' not in json.dumps(checked)
        page.evaluate('({key,valid})=>sessionStorage.setItem(key,valid)',{'key':KEY,'valid':valid})
        # Storage failure must stay an optional optimization, not break the page.
        storage_checks=page.evaluate('''()=>{
          const descriptor=Object.getOwnPropertyDescriptor(window,'sessionStorage');
          const storage=window.sessionStorage;
          try{
            Object.defineProperty(window,'sessionStorage',{configurable:true,get(){throw new Error('storage denied')}});
            if(Search3ResultsContinuityV1.readSnapshot()!==null||Search3ResultsContinuityV1.captureSnapshot()!==false)return false;
            Object.defineProperty(window,'sessionStorage',{configurable:true,value:{getItem:key=>storage.getItem(key),setItem(){throw new Error('quota exceeded')}}});
            if(Search3ResultsContinuityV1.captureSnapshot()!==false)return false;
            return true;
          }finally{if(descriptor)Object.defineProperty(window,'sessionStorage',descriptor);else delete window.sessionStorage;}
        }''')
        assert storage_checks,'storage denial/quota cannot escape into the user journey'
        # Deliberate refresh starts exactly one normal new search.
        page.locator('.search3-resume-refresh').click()
        page.wait_for_function('__calls.some(call=>call.action==="search_start")')
        assert len([c for c in page.evaluate('__calls') if c['action']=='search_start'])==1
        assert page.evaluate('V2SearchLifecycle.restoredAt')==0
        expect(page.locator('.hotel-card')).to_have_count(4)
        page.wait_for_timeout(150)
        page=open_page(BASE+'?'+QUERY)
        expect(page.locator('.hotel-card')).to_have_count(4)
        assert page.evaluate('V2SearchLifecycle.restoredAt')>0
        page=open_page(BASE+'?'+QUERY.replace('country=4','country=9'))
        page.wait_for_function('__calls.some(c=>c.action==="search_start")')
        starts=[c for c in page.evaluate('__calls') if c['action']=='search_start']
        assert len(starts)==1 and starts[0]['params']['countryId']=='9','other query cannot reuse saved results'
        assert page.evaluate('V2SearchLifecycle.restoredAt')==0
        # Expired bare re-entry stays in the editable form and never invents a
        # current result or automatically buys another supplier search.
        page.evaluate('({key,valid})=>{const d=JSON.parse(valid);d.createdAt=Date.now()-1800001;sessionStorage.setItem(key,JSON.stringify(d))}',{'key':KEY,'valid':valid})
        page=open_page(BASE)
        page.wait_for_function('!!window.V2SearchLifecycle')
        expect(page.locator('.hotel-card')).to_have_count(0)
        assert page.evaluate('__calls')==[]
        assert page.evaluate('V2SearchLifecycle.restoredAt')==0
        expect(page.locator('#tourSearch')).to_be_visible()
        # LOCAL data can arrive before the same generation receives its remote
        # search id. Its visible in-page offers must stay usable in that interval.
        page.evaluate('()=>{window.__holdStart=true;void V2SearchLifecycle.submit()}')
        page.wait_for_function('typeof window.__releaseStart==="function"')
        transient=page.evaluate('''async()=>{
          const owner=Search3CanonicalProfilesV1.current();
          owner.upsertHotel(__profiles[0]);
          __hotels[0].tours.slice(0,2).forEach((t,i)=>owner.upsertOffer(9001,{...t,id:'cached:'+i,cachedListing:true,selectionEnabled:false},{source:'local-db',legacyHotelId:'101'}));
          owner.refresh();
          const before=V2Results.state.items.length;
          __releaseStart({searchId:732});await Promise.resolve();await Promise.resolve();
          document.querySelector('button.tour-more-toggle').click();
          return {before,after:V2Results.state.items.length,rows:document.querySelectorAll('.tour-row').length};
        }''')
        assert transient=={'before':1,'after':1,'rows':2}, transient
        assert not errors,errors
        REPORT.append({'width':width,'document_mode':'in-memory document reconstruction' if offline else 'native reload and Back','reload_no_search':True,'native_back':not offline,'same_tab_reentry':True,'query_and_ages':True,'sort_and_details':True,'expanded_mixed_offers':6,'saved_price_wording':True,'expired_bare_form':True,'fresh_tv_selection':True,'privacy':True,'non_sliding_ttl':True,'updated_view':True,'storage_failure':True,'overflow':False})
        ctx.close()
    browser.close()
(OUT/'resume-report.json').write_text(json.dumps(REPORT,ensure_ascii=False,indent=2)+'\n')
print('SEARCH3_RESULTS_RESUME_BROWSER_OK',json.dumps(REPORT))
