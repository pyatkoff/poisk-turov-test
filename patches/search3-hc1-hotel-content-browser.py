"""Actual Search3 app; fictional fixture transport only, no live data or supplier.

HC-1: canonical content, full galleries and exact offers. All transport is intercepted.
"""
import copy
import importlib.util
import json
import os
from pathlib import Path
import threading
from urllib.parse import parse_qs, urlencode, urlparse
from http.server import ThreadingHTTPServer
from playwright.sync_api import sync_playwright

spec = importlib.util.spec_from_file_location('contact_fixture', Path(__file__).with_name('search3-prototype-contact-recovery.py'))
fx = importlib.util.module_from_spec(spec)
spec.loader.exec_module(fx)
OUT = Path(os.environ.get('HC1_OUTPUT', 'local-db-price-evidence/hotel-content'))
OUT.mkdir(parents=True, exist_ok=True)
profiles = {}
for old, count in [(101,240),(102,1),(103,0),(104,2)]:
    p = copy.deepcopy(fx.PROFILE)
    p.update(id=old-100,name=f'Вымышленный отель {old}',description=f'Описание фиктивного отеля {old}.',
             primaryImage=f'http://127.0.0.1/photo-{old}-0.svg' if count else None,
             images=[f'http://127.0.0.1/photo-{old}-{i}.svg' for i in range(count)])
    if count: p['images'].append(p['images'][0])
    profiles[old] = p

profiles[101].update(address='Тестовая улица, 1 &amp; корпус Б', place='<p>Рядом с набережной.</p>',
                     build='Построен в 2018 году.', repair='Реновация в 2025 году.', square='Площадь территории 42 000 м².')
profiles[101]['hotelInformation'] = {
    'services': {
        'available': ['<p>Прачечная</p>', {'name': 'Камера хранения'}],
        'inRoom': {'description': '<p>Кондиционер</p>', 'list': ['<p>Сейф</p>']},
        'child': '<p>Мини-клуб</p>', 'animation': '<p>Вечерняя программа</p>',
        'free': '<p>Wi-Fi</p>', 'servicesPay': '<p>Спа-центр</p>'},
    'infrastructure': {'beach': '<ul><li>Песчаный пляж</li><li>Шезлонги</li></ul>',
                       'territory': '<p>Сад &amp; бассейн</p>'},
    'meals': {'description': 'Главный ресторан', 'list': ['BB', {'name': 'HB'}]},
    'roomTypes': [{'name': 'Standard 30 м²'}, {'description': 'Family 45 м²'}]}
profiles[103].update(description='', rating=None, category=None)
INITIAL_PROFILES = copy.deepcopy(profiles)


def check(browser, origin, width):
    profiles.clear(); profiles.update(copy.deepcopy(INITIAL_PROFILES))
    ctx = browser.new_context(viewport={'width':width,'height':900},service_workers='block')
    page = ctx.new_page()
    page.set_default_timeout(10000)
    api_calls, forbidden, errors = [], [], []
    page.on('pageerror', lambda e: errors.append(str(e)))
    def intercept(route):
        req = route.request
        u = urlparse(req.url)
        q = parse_qs(u.query)
        def reply(value, status=200):
            route.fulfill(status=status,content_type='application/json',body=json.dumps(value,ensure_ascii=False))
        if u.hostname in ('127.0.0.1','localhost') and u.path.startswith('/photo-'):
            route.fulfill(content_type='image/svg+xml',body=fx.PHOTO)
        elif u.path == '/data/departures-v1.php':
            reply({'ok':True,'items':[{'id':1,'name':'Москва'}]})
        elif u.path.endswith('/search3-destination-read-v1.php'):
            country = q.get('action') == ['countries']
            rows = [{'id':4,'kind':'country','parentId':None,'name':'Турция','russianName':'Турция','slug':'turkey','revision':1,'tourvisorIds':['4']}] if country else [{'id':20,'kind':'region','parentId':4,'name':'Анталья','revision':1,'tourvisorIds':['20']}]
            reply({'ok':True,'source':'anytour-destination-identities-v1','provider':'tourvisor','items':rows})
        elif u.path.endswith('/hotel-details-read-v1.php'):
            if 'anytourHotelId' in q:
                own=int(q['anytourHotelId'][0])
                reply({'ok':True,'catalog':'anytour','source':'anytour-canonical-catalog','item':profiles[own+100]})
                return
            ids = list(map(int,q['legacyHotelIds[]']))
            assert all(i in profiles for i in ids)
            reply({'ok':True,'catalog':'anytour','source':'anytour-canonical-catalog',
                   'requestedLegacyIds':ids,'missingLegacyIds':[],
                   'items':[profiles[i] for i in ids],
                   'links':[{'legacyHotelId':i,'anytourHotelId':i-100} for i in ids]})
        elif u.path.endswith('/search3-local-results-read-v1.php'):
            body = req.post_data_json
            if body.get('action')=='meal_catalog':
                reply({'ok':True,'data':{'source':'anytour-search-meal-v1','provider':'tourvisor','scopeKey':'global','available':True,'revision':'a'*64,'plans':[{'id':7,'code':'all-inclusive','nameRu':'Всё включено','nativeIds':['7']}]}})
            elif 'params' in body:
                reply({'ok':True,'data':{'source':'anytour-db-first-results-v1','scopeVersion':1,'scope':{'scopeVersion':1,**body['params']},'scopeDigest':'c'*64,'selectionAuthority':False,'hotels':[],
                       'storedOfferCount':0,'withheldOfferCount':0,'categoryFilteredOfferCount':0,'eligibleHotelCount':0,'omittedHotelCount':0,'omittedOfferCount':0,'offerCount':0,'providerOfferCounts':{}}})
            else:
                reply({'ok':False},503)
        elif u.path.endswith('/api-anex-search3-preview.php'):
            b=req.post_data_json
            reply({'ok':True,'data':{'provider':'anex','generation':b['generation'],'date_range':{'from':b['params']['dateFrom'],'to':b['params']['dateTo']},'search_ref':'d'*32,'external_search_pending':False,'pages_read':1,'first_page_only':True,'hotels':[]}})
        elif u.path.endswith('/api-andromeda-search3-preview.php'):
            b=req.post_data_json
            reply({'ok':True,'data':{'provider':'andromeda','generation':b['generation'],'hotels':[]}})
        elif u.path=='/api-v2.php':
            a=q.get('action',[''])[0]
            api_calls.append(a)
            if a=='meals': reply([{'id':7,'name':'AI'}])
            elif a=='search_start': reply({'searchId':123})
            elif a=='search_status': reply({'progress':100,'status':'complete'})
            elif a=='search_results':
                reply([{'id':i,'provider':'tourvisor','tours':[{**fx.TOUR,'id':f'gallery-{i}','price':100000+i,'selectionEnabled':False}]} for i in profiles])
            else:
                forbidden.append(a); route.abort()
        elif req.method=='GET' and req.url.startswith(origin+fx.BASE):
            route.continue_()
        else:
            forbidden.append(u.path); route.abort()
    ctx.route('**/*',intercept)
    checks={}
    geometry=[]
    def visible_gallery():
        metrics=page.evaluate("""() => {
            const image=document.querySelector('#gallery-image').getBoundingClientRect();
            const body=document.querySelector('#modal-body').getBoundingClientRect();
            const strip=document.querySelector('.gallery-thumbs').getBoundingClientRect();
            const active=document.querySelector('.gallery-thumbs .active').getBoundingClientRect();
            return {
                imageVisible:image.left>=body.left-1 && image.right<=body.right+1 && image.top>=body.top-1 && image.bottom<=body.bottom+1,
                activeVisible:active.left>=strip.left-1 && active.right<=strip.right+1,
                thumbWidth:active.width,thumbHeight:active.height,
                pageOverflow:document.documentElement.scrollWidth-innerWidth
            };
        }""")
        assert metrics['imageVisible'] and metrics['activeVisible'], metrics
        assert metrics['thumbWidth']>=44 and metrics['thumbHeight']>=44 and metrics['pageOverflow']<=1, metrics
        geometry.append(metrics)
    try:
        query=urlencode({'origin':'Москва','country':4,'from':fx.DATE,'to':fx.DATE,'minNights':7,'maxNights':7,'adults':2,'ages':''})
        page.goto(origin+fx.BASE+'prototype-search/?'+query)
        page.locator('.search-submit:not([disabled])').click()
        page.wait_for_function("document.querySelectorAll('.hotel-card').length===4")
        card=page.locator('#hotel-1')
        card.locator('[data-action="gallery"]').click()
        count=page.locator('.gallery-thumbs button').count()
        assert count==240, f'FULL_GALLERY_TRUNCATED expected=240 actual={count}'
        checks['all240PhotosAvailable']=True
        assert card.locator('.card-thumb').count()==12, 'Card preview grew with gallery'
        checks['cardPreviewStill12']=True
        assert page.locator('.gallery-thumbs img[loading="lazy"]').count()==240
        page.locator('[data-action="gallery-index"][data-value="239"]').click()
        assert page.locator('#gallery-image').get_attribute('src').endswith('/photo-101-239.svg')
        page.wait_for_function("document.querySelector('#gallery-image').complete && document.querySelector('#gallery-image').naturalWidth>0")
        visible_gallery()
        page.screenshot(path=str(OUT/f'full-gallery-{width}.png'))
        page.locator('[data-action="gallery-next"]').click()
        assert page.locator('#gallery-image').get_attribute('src').endswith('/photo-101-0.svg')
        page.locator('[data-action="gallery-prev"]').click()
        assert page.locator('#gallery-image').get_attribute('src').endswith('/photo-101-239.svg')
        checks['lastPhotoAndWrap']=True
        page.locator('#modal [data-action="close-modal"]').click()
        for _ in range(15): card.locator('[data-action="card-photo"][data-dir="1"]').click()
        current=card.locator('.hotel-image').get_attribute('src')
        assert current.endswith('/photo-101-15.svg')
        card.locator('[data-action="gallery"]').click()
        assert page.locator('#gallery-image').get_attribute('src')==current
        visible_gallery()
        checks['opensCurrentFrameBeyond12']=True
        checks['longGalleryHasUsableVisibleThumbs']=True
        page.locator('#modal [data-action="close-modal"]').click()
        card.locator('[data-action="hotel-details"]').click()
        assert page.locator('[data-hotel-description="full"]').inner_text()==profiles[101]['description']
        assert 'Песчаный пляж' in page.locator('[data-hotel-field="infrastructure.beach"]').inner_text()
        assert 'Шезлонги' in page.locator('[data-hotel-field="infrastructure.beach"]').inner_text()
        assert 'Мини-клуб' in page.locator('[data-hotel-field="services.child"]').inner_text()
        page.locator('[data-hotel-reference] > summary').click()
        expected = {
            'address':'Тестовая улица, 1 & корпус Б', 'place':'Рядом с набережной.',
            'infrastructure.territory':'Сад & бассейн', 'services.available':'Прачечная',
            'services.inRoom':'Кондиционер', 'services.animation':'Вечерняя программа',
            'services.free':'Wi-Fi', 'services.servicesPay':'Спа-центр',
            'meals':'Главный ресторан', 'roomTypes':'Standard 30 м²',
            'build':'Построен в 2018 году.', 'repair':'Реновация в 2025 году.',
            'square':'Площадь территории 42 000 м².'}
        for key, value in expected.items():
            assert value in page.locator(f'[data-hotel-field="{key}"]').inner_text(), key
        for key, values in {'meals':['BB','HB'], 'roomTypes':['Family 45 м²'],
                            'services.inRoom':['Сейф'], 'services.available':['Камера хранения']}.items():
            for value in values: assert value in page.locator(f'[data-hotel-field="{key}"]').inner_text(), (key,value)
        assert 'FAMILY SEA VIEW' in page.locator('.room-options').inner_text()
        assert 'Standard 30 м²' not in page.locator('.room-options').inner_text()
        assert page.locator('.room-options').evaluate("node => Boolean(node.compareDocumentPosition(document.querySelector('[data-hotel-reference]')) & Node.DOCUMENT_POSITION_FOLLOWING)")
        checks['canonicalHotelFactsVisible']=True
        checks['nestedFactsKeepNamesAndExactOfferRoom']=True
        page.screenshot(path=str(OUT/f'hotel-content-{width}.png'))
        page.locator('#modal [data-action="close-modal"]').click()
        card.locator('[data-action="toggle-offers"]').click()
        offer=card.locator('.offer').first
        before=offer.inner_text()
        before_calls=len(api_calls)
        offer.locator('[data-action="offer"]').click()
        assert page.locator('.tour-hero h3').inner_text()==profiles[101]['name']
        page.locator('#modal [data-action="close-modal"]').click()
        assert offer.inner_text()==before and card.locator('.hotel-image').get_attribute('src')==current
        page.locator('#sort').select_option('price')
        assert card.locator('.hotel-image').get_attribute('src')==current
        assert len(api_calls)==before_calls and not forbidden and not errors
        checks['descriptionOfferReturnSortNoNewApi']=True
        page.locator('#hotel-2 [data-action="gallery"]').click()
        assert page.locator('.gallery-thumbs button').count()==1
        page.locator('[data-action="gallery-next"]').click()
        assert page.locator('#gallery-image').get_attribute('src').endswith('/photo-102-0.svg')
        page.locator('#modal [data-action="close-modal"]').click()
        assert page.locator('#hotel-3').is_visible()
        page.locator('#hotel-3 [data-action="gallery"]').click()
        assert not page.locator('#modal').is_visible()
        checks['oneAndZeroPhotoHotelsRemainUsable']=True
        page.locator('#hotel-3 [data-action="hotel-details"]').click()
        assert page.locator('[data-hotel-description], [data-hotel-reference], .detail-rating').count()==0
        assert 'Описание пока не заполнено' not in page.locator('#modal-body').inner_text()
        assert page.locator('.room-options').count()==1
        checks['emptyHotelFactsDescriptionRatingOmitted']=True
        page.locator('#modal [data-action="close-modal"]').click()
        for own in (2,4):
            page.locator(f'#hotel-{own} [data-action="hotel-details"]').click()
            metrics=page.locator('.hotel-detail-photos').evaluate("""node=>({width:node.clientWidth,
                children:[...node.children].map(c=>({width:c.getBoundingClientRect().width,height:c.getBoundingClientRect().height}))})""")
            assert sum(p['width'] for p in metrics['children'])>=metrics['width']-10, metrics
            assert min(p['height'] for p in metrics['children'])>=180, metrics
            assert page.locator('[data-hotel-reference]').count()==0
            page.screenshot(path=str(OUT/f'hotel-{own}-{width}.png'))
            page.locator('#modal [data-action="close-modal"]').click()
        checks['oneAndTwoPhotosFillDetails']=True
        # Refresh only the canonical profile, preserving the current exact offer.
        profiles[101]['revision'] += 1
        profiles[101]['description'] = ('<p>Новое описание &amp; факты.</p>' +
            '<p>Подробное описание отеля. </p>'*30 + '<p>Последний абзац описания.</p>' +
            '<script>window.HC1_INJECTED=true</script><style>body{display:none}</style>' +
            '<img src="https://invalid.example/forbidden" onerror="window.HC1_INJECTED=true">')
        profiles[101]['hotelInformation']['services']['available'].append({'text':'Новая услуга'})
        before_refresh=len(api_calls)
        page.evaluate("""async () => {const owner=Search3CanonicalProfilesV1.current(); await owner.readProfile(1); owner.refresh();}""")
        card.locator('[data-action="hotel-details"]').click()
        assert 'Новое описание & факты.' in page.locator('[data-hotel-description="summary"]').inner_text()
        page.locator('.hotel-description-more > summary').click()
        assert 'Последний абзац описания.' in page.locator('[data-hotel-description="full"]').inner_text()
        assert 'HC1_INJECTED' not in page.locator('[data-hotel-description="full"]').inner_text()
        assert page.evaluate('window.HC1_INJECTED === undefined')
        assert page.locator('#modal script,#modal style,#modal iframe,#modal img[onerror]').count()==0
        page.locator('[data-hotel-reference] > summary').click()
        assert 'Новая услуга' in page.locator('[data-hotel-field="services.available"]').inner_text()
        page.screenshot(path=str(OUT/f'hotel-full-description-{width}.png'))
        page.locator('#modal [data-action="close-modal"]').click()
        page.locator('#sort').select_option('rating')
        assert offer.inner_text()==before
        card.locator('[data-action="gallery"]').click()
        assert page.locator('.gallery-thumbs button').count()==240
        assert page.locator('#gallery-image').get_attribute('src')==current
        page.locator('#modal [data-action="close-modal"]').click()
        assert len(api_calls)==before_refresh and not errors and not forbidden
        checks['newProfileKeepsOfferGalleryAndSort']=True
        checks['completeSafeDescription']=True
        page.screenshot(path=str(OUT/f'cards-return-{width}.png'))
        return {'width':width,'status':'passed','checks':checks,'geometry':geometry,'externalCalls':0,'fictionalApiCalls':api_calls}
    finally:
        ctx.close()


server=ThreadingHTTPServer(('127.0.0.1',0),fx.StaticFiles)
threading.Thread(target=server.serve_forever,daemon=True).start()
try:
    with sync_playwright() as pw:
        browser=pw.chromium.launch(**({'executable_path':os.environ['CHROMIUM_PATH']} if os.environ.get('CHROMIUM_PATH') else {}))
        results=[check(browser,f'http://127.0.0.1:{server.server_port}',w) for w in (390,768,1440)]
        browser.close()
    (OUT/'result.json').write_text(json.dumps({'basis':'actual_app_fictional_transport','results':results,'liveAccepted':False},ensure_ascii=False,indent=2))
    print('HC1_FULL_GALLERY_BROWSER_OK widths=390,768,1440 fixtures=240,1,0,2 supplier0 lead0')
finally:
    server.shutdown()
