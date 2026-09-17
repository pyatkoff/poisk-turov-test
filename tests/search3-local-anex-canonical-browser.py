#!/usr/bin/env python3
"""Offline regression for LOCAL #2688.

A direct ANEX result adds a hotel after the first Search3 render. The new hotel must
remain withheld until its accepted legacy identity resolves to an AnyTour canonical
profile, then render only with first-party hotel presentation while retaining exact
ANEX offer facts. All data is fictional and all network transport is intercepted.
"""
from pathlib import Path
import json
import os
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
V2 = ROOT / 'v2'
ROOM = (V2 / 'search3-room-normalizer-v1.js').read_text()
CODE = (V2 / 'search3-canonical-profiles-v1.js').read_text() + '\n' + (V2 / 'results-renderer-v5.js').read_text()
ANEX = (V2 / 'anex-final-price-provider-v1.js').read_text()
OUT = Path(os.environ.get('SEARCH3_EVIDENCE_DIR', str(ROOT / 'canonical-card-evidence')))
OUT.mkdir(parents=True, exist_ok=True)

HTML = '''<!doctype html><html lang="ru"><head><meta charset="utf-8"></head><body class="search3-candidate">
<main class="v2-shell"><section id="status" class="status" hidden></section>
<section id="resultsTools"><span id="resultSummary"></span></section>
<section id="results" class="results"></section><section id="selectedTour" hidden></section></main></body></html>'''
SETUP = r'''window.__requests=[];
window.fetch=(url,options)=>new Promise((resolve,reject)=>window.__requests.push({url:String(url),options:options||{},resolve,reject}));
window.V2Runtime={fetch:(url,options)=>window.fetch(url,options)};
window.Search3LocalHotelFilter={project:items=>items};
window.__resolve=(index,data,status=200)=>window.__requests[index].resolve({ok:status>=200&&status<300,status,json:async()=>data});
'''


def profile(own):
    return {
        'id': own, 'catalog': 'anytour', 'revision': 1, 'name': f'Наш локальный отель {own}',
        'description': 'Описание только из собственного каталога AnyTour.', 'detailsAvailable': True,
        'category': 5, 'rating': 4.9, 'country': {'name': 'Тестовая страна'},
        'region': {'name': 'Наш курорт'}, 'images': [],
        'hotelInformation': {'services': ['Детский клуб'], 'meals': [], 'roomTypes': ''},
    }


def base_hotel(legacy):
    tour = {
        'id': f'tourvisor:offer-{legacy}', 'provider': 'tourvisor', 'price': 125000,
        'date': '05.10.2026', 'nights': 7, 'meal': {'name': 'HB'}, 'roomType': 'Standard',
        'placement': 'DBL', 'operator': {'name': 'Coral Travel'}, 'isCharter': True,
    }
    return {
        'id': str(legacy), 'provider': 'tourvisor', 'mappingStatus': 'resolved',
        'name': 'НЕЛЬЗЯ: supplier base name', 'country': {'name': 'НЕЛЬЗЯ'},
        'region': {'name': 'НЕЛЬЗЯ'}, 'picturelink': 'https://fixture.invalid/supplier.svg',
        'category': 2, 'rating': 1, 'tours': [tour], 'price': tour['price'],
    }


def canonical_payload(page, index, links):
    ids = page.evaluate('(i)=>new URL(__requests[i].url,"https://fixture.invalid/").searchParams.getAll("legacyHotelIds[]").map(Number)', index)
    return {
        'ok': True, 'source': 'anytour-canonical-catalog', 'catalog': 'anytour',
        'requestedLegacyIds': ids,
        'items': [profile(x) for x in dict.fromkeys(links.values())],
        'links': [{'legacyHotelId': int(k), 'anytourHotelId': v} for k, v in links.items()],
        'missingLegacyIds': [],
    }


def resolve(page, index, data, status=200, wait=100):
    page.evaluate('(args)=>__resolve(...args)', [index, data, status])
    page.wait_for_timeout(wait)


def check(value, label, checks):
    checks.append({'check': label, 'pass': bool(value)})
    if not value:
        raise AssertionError(label)


checks = []
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH') or None, headless=True, args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 1280, 'height': 900})
    context.route('**/*', lambda route: route.abort())
    page = context.new_page()
    page.set_default_timeout(3000)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.set_content(HTML)
    page.add_script_tag(content=SETUP)
    page.add_script_tag(content=ROOM)
    page.evaluate("""code=>{
      const location=new URL('https://fixture.invalid/_preview/search3-local-candidate/poisk-turov/');
      const fake=new Proxy(window,{get(t,k){if(k==='location')return location;const v=Reflect.get(t,k,t);return typeof v==='function'?v.bind(t):v;},set(t,k,v){t[k]=v;return true;}});
      new Function('window',code)(fake);
    }""", CODE)
    page.evaluate('window.V2SearchLifecycle={generation:1,snapshot:{from:1,country:4,dateFrom:"2026-10-05",dateTo:"2026-10-05",nightsFrom:7,nightsTo:7,adults:2,childs:0},dirty:false};window.V2_CONFIG={anexApi:"/_preview/search3-anex-candidate/api-anex-search3-preview.php"};')
    page.evaluate("""code=>{
      const location=new URL('https://fixture.invalid/_preview/search3-local-candidate/poisk-turov/');
      const fake=new Proxy(window,{get(t,k){if(k==='location')return location;const v=Reflect.get(t,k,t);return typeof v==='function'?v.bind(t):v;},set(t,k,v){t[k]=v;return true;}});
      new Function('window',code)(fake);
    }""", ANEX)

    # Request 0: ANEX search begins. Request 1: canonical profile for initial Tourvisor hotel.
    page.evaluate('dispatchEvent(new CustomEvent("v2:search-reset",{detail:{generation:1}}))')
    page.evaluate('(items)=>V2Results.render(items)', [base_hotel(102)])
    page.wait_for_timeout(50)
    check(page.evaluate('__requests.length') == 2, 'initial ANEX and canonical requests are isolated', checks)
    resolve(page, 1, canonical_payload(page, 1, {102: 1}))
    check(page.locator('.hotel-card').count() == 1, 'initial hotel renders from AnyTour catalog', checks)
    check(page.locator('.hotel-title').inner_text() == 'Наш локальный отель 1', 'initial supplier title is replaced', checks)

    search_ref = 'b' * 32
    offer_ref = 'anex_online:' + 'a' * 64
    anex_tour = {
        'kind': 'concrete', 'search_ref': search_ref, 'offer_ref': offer_ref,
        'price': {'amount': '110000', 'currency': 'RUB'}, 'checkin': '2026-10-05',
        'nights': 7, 'meal': 'AI', 'room': 'Standard Sea View', 'placement': 'DBL',
    }
    anex_hotel = {
        'local_id': 106, 'mapping_status': 'resolved', 'name': 'НЕЛЬЗЯ: ANEX hotel name',
        'country': 'НЕЛЬЗЯ', 'region': 'НЕЛЬЗЯ', 'category': 3, 'rating': 2,
        'catalog': {'image_url': 'https://fixture.invalid/anex-supplier.svg'}, 'tours': [anex_tour],
    }
    resolve(page, 0, {'ok': True, 'data': {'provider': 'anex', 'generation': 1, 'search_ref': search_ref, 'hotels': [anex_hotel]}})
    check(page.evaluate('__requests.length') == 3, 'ANEX requests final-price batch only after valid search', checks)
    batch_body = page.evaluate('()=>JSON.parse(__requests[2].options.body)')
    check(batch_body['action'] == 'additional_prices_batch' and batch_body['items'] == [{'offer_ref': offer_ref, 'local_hotel_id': 106}], 'final-price request retains accepted local identity', checks)

    batch = {
        'ok': True, 'data': {
            'provider': 'anex', 'generation': 1, 'search_ref': search_ref,
            'status': 'additional_prices_batch',
            'offers': [{
                'offer_ref': offer_ref, 'local_hotel_id': 106, 'status': 'additional_prices',
                'finalPriceReady': True, 'finalPrice': '119000', 'price': '119000',
                'additional_prices': {
                    'application_state': 'applied',
                    'search_plus_additional': {'amount': '119000', 'currency': 'RUB'},
                },
            }],
        },
    }
    resolve(page, 2, batch, wait=120)
    check(page.evaluate('__requests.length') == 4, 'new ANEX hotel triggers post-merge canonical hydration', checks)
    requested = page.evaluate('()=>new URL(__requests[3].url,"https://fixture.invalid/").searchParams.getAll("legacyHotelIds[]")')
    check(requested == ['106'], 'post-merge hydration asks only for newly admitted legacy hotel', checks)
    check(page.locator('.hotel-card').count() == 1, 'new supplier hotel is withheld before AnyTour profile resolves', checks)
    check('НЕЛЬЗЯ' not in page.locator('#results').inner_text(), 'no supplier hotel presentation leaks while hydrating', checks)

    resolve(page, 3, canonical_payload(page, 3, {106: 2}), wait=150)
    check(page.locator('.hotel-card').count() == 2, 'new ANEX hotel appears after AnyTour hydration', checks)
    titles = page.locator('.hotel-title').all_inner_texts()
    check(set(titles) == {'Наш локальный отель 1', 'Наш локальный отель 2'}, 'both cards use first-party hotel titles', checks)
    check('НЕЛЬЗЯ' not in page.locator('#results').inner_text(), 'supplier name/country/region never replace first-party profile', checks)
    check('anex-supplier.svg' not in page.locator('#results').inner_html(), 'supplier hotel image never becomes presentation fallback', checks)
    provider_state = page.evaluate('''()=>{const h=V2Results.state.items.find(x=>x.anytourHotelId===2);const t=h&&h.tours.find(x=>x.provider==='anex');return h&&t?{id:h.id,anytourHotelId:h.anytourHotelId,provider:t.provider,price:t.price,nights:t.nights,date:t.date,finalPriceReady:t.finalPriceReady,fuelIncluded:t.fuelIncluded,offerRef:t.offerRef}:null;}''')
    check(provider_state == {'id': 106, 'anytourHotelId': 2, 'provider': 'anex', 'price': 119000, 'nights': 7, 'date': '05.10.2026', 'finalPriceReady': True, 'fuelIncluded': True, 'offerRef': offer_ref}, 'canonical hotel keeps exact admitted ANEX offer facts and final price', checks)
    before = page.evaluate('__requests.length')
    page.evaluate('V2Results.rerender()')
    page.wait_for_timeout(80)
    check(page.evaluate('__requests.length') == before and page.locator('.hotel-card').count() == 2, 'rerender is idempotent and does not refetch known profiles', checks)
    check(not errors, 'no browser JavaScript errors', checks)

    context.close()
    browser.close()

result = {
    'status': 'offline_anex_post_merge_canonical_verified',
    'checks': len(checks), 'passed': sum(x['pass'] for x in checks), 'checks_detail': checks,
    'real_supplier_requests': 0, 'real_leads': 0, 'actual_sql': False,
    'target': '/_preview/search3-local-candidate/poisk-turov/',
    'tested_source_sha': os.environ.get('GITHUB_SHA', 'local-uncommitted'),
}
(OUT / 'anex-post-merge-canonical.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
print(json.dumps({k: v for k, v in result.items() if k != 'checks_detail'}, ensure_ascii=False, indent=2))
