#!/usr/bin/env python3
"""Offline regression for LOCAL-2 #2690.

A resolved Andromeda hotel may be introduced after the initial Search3 render without
carrying a Tourvisor presentation catalog. The accepted local identity must be enough
to enter first-party AnyTour canonical hydration. Supplier hotel presentation remains
withheld until that profile resolves; exact admitted Andromeda offer facts are kept.
All data is fictional and every network request is intercepted in-memory.
"""
from pathlib import Path
import json
import os
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
V2 = ROOT / 'v2'
ROOM = (V2 / 'search3-room-normalizer-v1.js').read_text()
CODE = (V2 / 'search3-canonical-profiles-v1.js').read_text() + '\n' + (V2 / 'results-renderer-v5.js').read_text()
ANDROMEDA = (V2 / 'andromeda-provider-v1.js').read_text()
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
        'region': {'name': 'Наш курорт'}, 'primaryImage': '', 'images': [],
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


def request_rows(page):
    return page.evaluate(r'''()=>__requests.map((request,index)=>{
      const url=new URL(request.url,'https://fixture.invalid/');
      let body=null;
      try{body=request.options&&request.options.body?JSON.parse(request.options.body):null;}catch(_){body=null;}
      return {
        index,
        pathname:url.pathname,
        method:String(request.options&&request.options.method||'GET').toUpperCase(),
        body,
        legacyIds:url.searchParams.getAll('legacyHotelIds[]')
      };
    })''')


def check(value, label, checks):
    checks.append({'check': label, 'pass': bool(value)})
    if not value:
        raise AssertionError(label)


def one_request(page, predicate, label, checks):
    matches = [row for row in request_rows(page) if predicate(row)]
    check(len(matches) == 1, label, checks)
    return matches[0]


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
    page.evaluate('window.V2SearchLifecycle={generation:1,snapshot:{from:1,country:4,dateFrom:"2026-10-05",dateTo:"2026-10-05",nightsFrom:7,nightsTo:7,adults:2,childs:0},dirty:false};window.V2_CONFIG={andromedaApi:"/_preview/search3-anex-candidate/api-andromeda-search3-preview.php"};')
    page.evaluate("""code=>{
      const location=new URL('https://fixture.invalid/_preview/search3-local-candidate/poisk-turov/');
      const fake=new Proxy(window,{get(t,k){if(k==='location')return location;const v=Reflect.get(t,k,t);return typeof v==='function'?v.bind(t):v;},set(t,k,v){t[k]=v;return true;}});
      new Function('window',code)(fake);
    }""", ANDROMEDA)

    page.evaluate('dispatchEvent(new CustomEvent("v2:search-reset",{detail:{generation:1}}))')
    page.evaluate('(items)=>V2Results.render(items)', [base_hotel(102)])
    page.wait_for_timeout(50)
    provider_request = one_request(
        page,
        lambda row: row['pathname'].endswith('/api-andromeda-search3-preview.php') and row['method'] == 'POST',
        'one initial Andromeda page request is present', checks,
    )
    check(provider_request['body']['generation'] == 1 and provider_request['body']['page'] == 1, 'Andromeda request keeps current generation/page', checks)
    first_canonical = one_request(
        page,
        lambda row: row['pathname'].endswith('/data/hotel-details-read-v1.php') and row['legacyIds'] == ['102'],
        'initial Tourvisor hotel requests its AnyTour profile', checks,
    )
    resolve(page, first_canonical['index'], canonical_payload(page, first_canonical['index'], {102: 1}))
    check(page.locator('.hotel-card').count() == 1, 'initial hotel renders from AnyTour catalog', checks)
    check(page.locator('.hotel-title').inner_text() == 'Наш локальный отель 1', 'initial supplier title is replaced', checks)

    ref = 'offer_' + 'a' * 64
    offer_context = {'provider': 'andromeda', 'search_ref': 'b' * 64, 'generation': 1, 'page': 1, 'offer_ref': ref}
    base_price = {'amount': '100000', 'currency': 'RUB'}
    final_price = {'amount': '110000', 'currency': 'RUB', 'source': 'derived_search_estimate'}
    tour = {
        'provider': 'andromeda', 'offer_ref': ref, 'offer_context': offer_context,
        'price': final_price, 'base_search_price': base_price, 'checkin': '2026-10-05',
        'nights': 7, 'meal': 'AI', 'room': 'Standard Sea View', 'placement': 'DBL', 'operator': 'ANEX',
        'search_surcharge': {
            'schema_version': 1, 'provider': 'andromeda', 'state': 'estimated',
            'arithmetic_applied': True, 'final_price_verified': False, 'surcharge_scope': 'party',
            'search_price': base_price, 'search_price_with_surcharge': final_price,
            'party_surcharge': {'amount': '10000', 'currency': 'RUB', 'source': 'andromeda_get_flights_transport'},
        },
    }
    provider_hotel = {
        'local_id': 108, 'mapping_status': 'resolved', 'name': 'НЕЛЬЗЯ: Andromeda supplier hotel',
        'country': 'НЕЛЬЗЯ', 'region': 'НЕЛЬЗЯ', 'category': 3, 'rating': 2,
        'andromeda_content': {'image_url': 'https://images.example.test/supplier.jpg'},
        'tours': [tour],
    }
    # Deliberately no hotel.catalog / Tourvisor presentation payload: accepted local identity
    # must be sufficient for AnyTour canonical hydration in search3-local-candidate.
    resolve(page, provider_request['index'], {'ok': True, 'data': {'provider': 'andromeda', 'generation': 1, 'page': 1, 'pages_count': 1, 'hotels': [provider_hotel]}}, wait=120)
    post_canonical = one_request(
        page,
        lambda row: row['pathname'].endswith('/data/hotel-details-read-v1.php') and row['legacyIds'] == ['108'],
        'new resolved Andromeda hotel enters AnyTour hydration without Tourvisor catalog dependency', checks,
    )
    check(page.locator('.hotel-card').count() == 1, 'new supplier hotel is withheld before AnyTour profile resolves', checks)
    check('НЕЛЬЗЯ' not in page.locator('#results').inner_text(), 'supplier hotel presentation never leaks while hydrating', checks)
    check('supplier.jpg' not in page.locator('#results').inner_html(), 'supplier image is not used as canonical presentation', checks)

    resolve(page, post_canonical['index'], canonical_payload(page, post_canonical['index'], {108: 2}), wait=150)
    check(page.locator('.hotel-card').count() == 2, 'new Andromeda hotel appears after AnyTour hydration', checks)
    check(set(page.locator('.hotel-title').all_inner_texts()) == {'Наш локальный отель 1', 'Наш локальный отель 2'}, 'both hotel cards use first-party AnyTour titles', checks)
    check('НЕЛЬЗЯ' not in page.locator('#results').inner_text(), 'supplier hotel text never replaces first-party profile', checks)
    state = page.evaluate('''ref=>{const h=V2Results.state.items.find(x=>x.anytourHotelId===2);const t=h&&h.tours.find(x=>x.offerRef===ref);return h&&t?{id:h.id,anytourHotelId:h.anytourHotelId,provider:t.provider,price:t.price,nights:t.nights,date:t.date,fuelIncluded:t.fuelIncluded,offerRef:t.offerRef,offerContext:t.offerContext}:null;}''', ref)
    check(state == {'id': '108', 'anytourHotelId': 2, 'provider': 'andromeda', 'price': 110000, 'nights': 7, 'date': '05.10.2026', 'fuelIncluded': True, 'offerRef': ref, 'offerContext': offer_context}, 'canonical hotel keeps exact admitted Andromeda identity/context/fuel-inclusive amount', checks)
    before = page.evaluate('__requests.length')
    page.evaluate('V2Results.rerender()')
    page.wait_for_timeout(80)
    check(page.evaluate('__requests.length') == before and page.locator('.hotel-card').count() == 2, 'rerender is idempotent and does not refetch known profiles', checks)
    check(not errors, 'no browser JavaScript errors', checks)

    context.close()
    browser.close()

result = {
    'status': 'offline_andromeda_post_merge_canonical_verified',
    'checks': len(checks), 'passed': sum(x['pass'] for x in checks), 'checks_detail': checks,
    'real_supplier_requests': 0, 'real_leads': 0, 'actual_sql': False,
    'tourvisor_catalog_required': False,
    'target': '/_preview/search3-local-candidate/poisk-turov/',
    'tested_source_sha': os.environ.get('GITHUB_SHA', 'local-uncommitted'),
}
(OUT / 'andromeda-post-merge-canonical.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
print(json.dumps({k: v for k, v in result.items() if k != 'checks_detail'}, ensure_ascii=False, indent=2))
