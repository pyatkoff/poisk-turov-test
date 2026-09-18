#!/usr/bin/env python3
"""Offline mobile/desktop component acceptance of LOCAL cached price states.
Uses actual canonical owner, renderer, provider and compiled CSS; all data fictional.
No supplier, lead or external requests. This is CI emulation, not physical Safari.
"""
from pathlib import Path
import json, os, subprocess
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
V2=ROOT/'v2'
OUT=Path(os.environ.get('SEARCH3_EVIDENCE_DIR','local-db-price-evidence'));OUT.mkdir(parents=True,exist_ok=True)
css_files=json.loads(subprocess.check_output(['php','-r','require '+json.dumps(str(V2/'bundle-manifest-v1.php'))+'; echo json_encode(v2_bundle_files("css","search3"));'],text=True))
css='\n'.join((V2/f).read_text() for f in css_files+['search3-results-filters-v1.css','search3-entry-v1.css','search3-results-cards-v2.css','search3-selected-flow-v2.css'])
code='\n'.join((V2/f).read_text() for f in ['search3-canonical-profiles-v1.js','results-renderer-v5.js','search3-local-db-provider-v1.js'])
html='<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style><body class="search3-candidate"><main class="v2-shell"><p>Компонентная проверка · вымышленные предложения</p><section id="status" hidden></section><section id="resultsTools" class="results-tools"><span id="resultSummary"></span><label>Сортировка<select id="sortResults"><option value="price">По цене</option></select></label></section><section id="results" class="results"></section><section id="selectedTour" hidden></section></main></body></html>'
states=['final_ready_estimate','final_verified','search_price_confirmation_required']
def row(state,i):
    verified=state=='final_verified';confirmation=state=='search_price_confirmation_required'
    provider=['tourvisor','andromeda','anex'][i]
    return {'provider':provider,'legacyHotelId':102,'price':125000+i*1000,'currency':'RUB','listing':{
        'schema_version':1,'provider':provider,'currency':'RUB','listingPrice':{'amount':str(125000+i*1000),'currency':'RUB'},
        'listingPriceState':state,'listingPriceReady':not confirmation,'priceConfirmationRequired':confirmation,
        'quoteState':'verified' if verified else 'unknown','finalPriceVerified':verified,'quoteEvidenceDigest':'f'*64 if verified else None,
        'selection_state':'refresh_required','booking_enabled':False,'operator':{'raw':'ANEX'},
        'identity':{'search_ref_digest':'a'*64,'offer_ref_digest':['b','c','d'][i]*64,'provider_hotel_ref_digest':'e'*64},
        'tour':{'checkin':'2026-10-13','nights':7,'party':{'adults':2,'children':0},'room':{'raw':['STANDARD','DELUXE','FAMILY ROOM SEA VIEW'][i]},'meal':{'raw':'AI'},'placement':{'raw':'DBL'}}}}
payload={'source':'anytour-db-first-results-v1','scopeVersion':1,'scopeDigest':'f'*64,'selectionAuthority':False,'hotels':[{'anytourHotelId':77,'hotel':{'id':77,'catalog':'anytour','revision':1,'name':'Тестовый отель с длинным названием у моря','category':5,'country':{'name':'Тестовая страна'},'region':{'name':'Тестовый курорт'},'description':'Только вымышленные данные для проверки отображения.'},'offers':[row(s,i) for i,s in enumerate(states)]}]}
evidence=[]
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True)
    for width,height in [(375,812),(390,500),(430,932),(1440,980)]:
        context=browser.new_context(viewport={'width':width,'height':height})
        context.route('**/*',lambda route:route.abort())
        page=context.new_page();page.set_default_timeout(5000);errors=[]
        page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(html)
        page.evaluate("""code=>{const loc=new URL('https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/');
          const view=new Proxy(window,{get(t,k){if(k==='location')return loc;const v=Reflect.get(t,k,t);return typeof v==='function'?v.bind(t):v;},set(t,k,v){t[k]=v;return true;}});
          new Function('window',code)(view);
        }""",code)
        result=page.evaluate('(data)=>AnyTourLocalDbProviderV1.apply(Search3CanonicalProfilesV1.current(),data)',payload)
        assert result and result['offerCount']==3,'all three states must render atomically'
        assert page.locator('.hotel-card').count()==1,'one canonical hotel for all providers'
        toggle=page.get_by_role('button',name='Показать варианты · 3',exact=True)
        assert toggle.bounding_box()['height']>=44,'touch target'
        toggle.click()
        rows=page.locator('.tour-row');assert rows.count()==3,'no lost offers'
        confirmation=rows.filter(has_text='FAMILY ROOM SEA VIEW')
        assert confirmation.count()==1 and 'Цена из поиска' in confirmation.inner_text()
        assert 'Итого за тур' not in confirmation.inner_text()
        assert '127 000' in confirmation.inner_text().replace('\xa0',' ')
        assert page.locator('.direct-tour').count()==0,'cached states never gain selection authority'
        assert page.locator('.tour-selection-note').count()==3
        assert page.locator('.tour-flight-badge').count()==0,'price state never invents flight facts'
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'no horizontal overflow'
        confirmation.screenshot(path=str(OUT/f'confirmation-{width}.png'))
        page.screenshot(path=str(OUT/f'offers-{width}.png'),full_page=True)
        page.get_by_role('button',name='Скрыть варианты',exact=False).click()
        assert page.locator('.tour-row').count()==0
        assert not errors,errors
        evidence.append({'viewport':[width,height],'offers':3,'confirmationPriceLabel':True,'cachedSelectionEnabled':False,'overflow':False})
        context.close()
    browser.close()
(OUT/'receipt.json').write_text(json.dumps({'kind':'CI Chromium component fixtures','cases':evidence},ensure_ascii=False,indent=2))
print('SEARCH3_LOCAL_DB_PRICE_STATES_BROWSER_OK',json.dumps(evidence))
