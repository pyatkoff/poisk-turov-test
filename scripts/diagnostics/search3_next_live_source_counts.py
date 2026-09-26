"""One explicit NEXT UI search. Never select, quote, submit a lead, or replay.

The observer copies only aggregate canonical lifecycle evidence, not offer identities
or supplier bodies. It does not use the unrelated legacy V2Results renderer.
"""
from __future__ import annotations
import hashlib
import json
import os
from pathlib import Path
from urllib.parse import parse_qs, urlencode, urlparse

ORIGIN = 'https://anytoour.ru'
BASE = '/_preview/search3-next-candidate/'
ANEX = '/_preview/search3-anex-candidate/'
PROVIDERS = ('tourvisor', 'anex', 'andromeda')
TRIP = dict(origin='Москва', country='4', **{'from': '2026-10-10', 'to': '2026-10-16'}, minNights='7', maxNights='7', adults='2', ages='')
EXPECTED = {
    'visual-search/app.js': 'ff2adf47bf9f03e808b339d2c7d9ecf9f2b447e42fbf083328fa6858310b8359',
    'prototype-search/data.js': '73fa30a1a3b064ff5adb88ca564fb859ae062fdfda29dd259db6437663cf9228',
    'prototype-search/search-lifecycle-v1.js': 'ee5851243288cd3d1de348721ec2a4606241779a06e7ea7c6d7f73be520489ab',
    'visual-search/live-bridge.js': '07c649b13c83a6d370f2a3dada8d99e91c7820b5211e9939bba0302f3c2a7509',
}
OBSERVER = r"""(() => {
 const names=['tourvisor','anex','andromeda'];
 const s=window.__nextAcceptance={installed:false,submits:0,complete:false,phase:'boot',providers:{},counts:null,sources:{}};
 const integer=n=>Number.isSafeInteger(n)&&n>=0&&n<=1000000;
 const counts=rows=>{const offers={tourvisor:0,anex:0,andromeda:0},hotels={tourvisor:0,anex:0,andromeda:0};let total=0,other=0;
  for(const h of rows){const present=new Set();for(const o of h.offers||[]){total++;if(names.includes(o.provider)){offers[o.provider]++;present.add(o.provider);}else other++;}for(const p of present)hotels[p]++;}
  return {hotels:rows.length,offers:total,hotelsByProvider:hotels,offersByProvider:offers,otherOffers:other};};
 const record=(e,r)=>{if(e.type==='results'&&Array.isArray(e.hotels))s.counts=counts(e.hotels);
  if(e.type==='provider'&&names.includes(e.provider)&&['loading','complete','partial','error','skipped','cancelled'].includes(e.status))s.providers[e.provider]=e.status;
  if(['loading','complete','error','cancelled','coverage_gap'].includes(r?.phase))s.phase=r.phase;
  if(e.type==='complete'){s.complete=true;for(const p of names){const source=e.sources?.[p]||{};s.sources[p]={};for(const k of ['hotels','offers','receivedHotels','receivedOffers','unmappedHotels','unmappedOffers','pagesRead','pagesTotal'])if(integer(source[k]))s.sources[p][k]=source[k];}}
 };
 let current;
 Object.defineProperty(window,'AnyTourPrototypeSearchLifecycleV1',{configurable:true,get:()=>current,set:api=>{
  current=Object.freeze({...api,create(options){const after=options.afterEvent;const result=api.create({...options,afterEvent(e,r){record(e,r);return after?.call(this,e,r);}});s.installed=true;return result;}});
 }});
 document.addEventListener('submit',e=>{if(e.target?.id==='search-form')s.submits++;},true);
})();"""

class Guard:
    def __init__(self):
        self.armed = False
        self.calls = {'tourvisor': 0, 'anex': 0, 'andromeda': 0}
        self.tv_reads = 0
        self.pages = set()
        self.denied = []

    def deny(self, code):
        self.denied.append(code)
        return False

    @staticmethod
    def body(value):
        try:
            result = json.loads(value or '{}')
            return result if isinstance(result, dict) else {}
        except (ValueError, TypeError):
            return {}

    @staticmethod
    def scope(params, query=False):
        def value(key):
            v = params.get(key)
            return v[0] if query and isinstance(v, list) and v else v
        expected = dict(departureId='1', countryId='4', dateFrom=TRIP['from'], dateTo=TRIP['to'], nightsFrom='7', nightsTo='7', adults='2')
        return all(str(value(k)) == v for k, v in expected.items()) and not params.get('childs[]' if query else 'childs')

    def allow(self, url, method, raw_body=None):
        u = urlparse(url)
        if u.scheme + '://' + u.netloc != ORIGIN:
            return False  # no analytics, external transports or external assets
        path = u.path
        query = parse_qs(u.query)
        body = self.body(raw_body)
        action = query.get('action', [''])[0]
        if path == '/api-v2.php':
            if action in ('departures', 'meals', 'countries', 'regions'):
                return method == 'GET'
            if action == 'search_start':
                if not self.armed or self.calls['tourvisor'] or not self.scope(query, True):
                    return self.deny('tv_start_or_scope_guard')
                self.calls['tourvisor'] += 1
                return True
            if action in ('search_status', 'search_results'):
                if not self.armed or self.calls['tourvisor'] != 1 or self.tv_reads >= 60:
                    return self.deny('tv_read_budget')
                self.tv_reads += 1
                return True
            return self.deny('tv_action_blocked')
        if path == ANEX + 'api-anex-search3-preview.php':
            if not self.armed or method != 'POST' or body.get('action') != 'search' or self.calls['anex'] or not self.scope(body.get('params', {})):
                return self.deny('anex_start_or_scope_guard')
            self.calls['anex'] += 1
            return True
        if path == ANEX + 'api-andromeda-search3-preview.php':
            page = body.get('page')
            if not self.armed or method != 'POST' or body.get('action', 'search') != 'search' or type(page) is not int or not 1 <= page <= 40 or page in self.pages or page != len(self.pages) + 1 or not self.scope(body.get('params', {})):
                return self.deny('andromeda_page_or_scope_guard')
            self.pages.add(page)
            self.calls['andromeda'] += 1
            return True
        if any(x in path.lower() for x in ('lead', 'quote', 'booking', 'payment')) and path.endswith('.php'):
            return self.deny('transaction_endpoint_blocked')
        if '/data/' in path and (path.startswith('/data/') or path.startswith(BASE) or path.startswith('/_preview/search3-local-candidate/')):
            return method in ('GET', 'POST') and path.endswith(('-read-v1.php', '-v1.php'))
        if path.startswith(BASE):
            return method == 'GET' and (path == BASE + 'visual-search/' or path.endswith(('.js', '.css', '.svg', '.png', '.jpg', '.webp', '.woff2', '.json', '.ico')))
        return False


def safe_response(url, status, raw_body, payload):
    u = urlparse(url)
    if u.path not in ('/api-v2.php', ANEX + 'api-anex-search3-preview.php', ANEX + 'api-andromeda-search3-preview.php') and '/data/' not in u.path:
        return None
    b = Guard.body(raw_body)
    action = parse_qs(u.query).get('action', [b.get('action', 'search' if u.path.startswith(ANEX) else '')])[0]
    actions = {'search', 'search_start', 'search_status', 'search_results', 'departures', 'meals', 'countries', 'regions', 'meal_catalog'}
    row = {'path': u.path, 'httpStatus': status, 'action': action if action in actions else 'other_read'}
    if not isinstance(payload, dict):
        row['jsonType'] = 'array' if isinstance(payload, list) else 'unavailable'
        return row
    data = payload.get('data') if isinstance(payload.get('data'), dict) else payload
    row['ok'] = payload.get('ok') if type(payload.get('ok')) is bool else None
    if data.get('provider') in PROVIDERS: row['provider'] = data['provider']
    if isinstance(data.get('hotels'), list): row['receivedHotels'] = len(data['hotels'])
    for key in ('received_offers', 'mapped_offers', 'pages_count', 'page', 'pages_read'):
        if type(data.get(key)) is int and 0 <= data[key] <= 1000000: row[key] = data[key]
    error = payload.get('error')
    categories = {'supplier_rejected', 'supplier_auth', 'request_invalid', 'invalid_request', 'search_unavailable', 'search_expired', 'rate_limited', 'budget_exceeded', 'configuration_unavailable', 'unsupported_country'}
    code = error.get('category') if isinstance(error, dict) else error
    if error: row['errorCategory'] = code if isinstance(code, str) and code in categories else 'other_error'
    return row


def exercise(page, guard, timeout=120000):
    """The same UI sequence is exercised by the fully offline browser test."""
    page.goto(ORIGIN + BASE + 'visual-search/?' + urlencode(TRIP), wait_until='domcontentloaded', timeout=45000)
    page.wait_for_function("window.__nextAcceptance?.installed && window.AnyTourPrototypeData?.live === true && document.querySelector('.search-submit')?.disabled === false", timeout=60000)
    if any(guard.calls.values()): raise RuntimeError('unexpected_search_before_click')
    guard.armed = True
    page.locator('#search-form .search-submit').click(timeout=10000)
    try:
        page.wait_for_function("window.__nextAcceptance.complete || (window.__nextAcceptance.phase === 'error' && !Object.values(window.__nextAcceptance.providers).includes('loading'))", timeout=timeout)
    except Exception:
        pass  # preserve partial evidence; never retry a provider or click
    state = page.evaluate('window.__nextAcceptance')
    state['hotelCards'] = page.locator('.hotel-card').count()
    state['calls'] = dict(guard.calls)
    state['tvReadCalls'] = guard.tv_reads
    state['blocked'] = guard.denied[:20]
    return state


def main():
    from playwright.sync_api import sync_playwright
    out = Path('search3-next-live-source-counts'); out.mkdir(exist_ok=True)
    result = {'source': 'eb0f66d9fc455da053ba64a5cf8d881795f95286', 'route': BASE, 'trip': TRIP, 'status': 'not_started', 'network': [], 'sourceHashes': {}}
    guard = Guard()
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1280, 'height': 900}, service_workers='block')
        try:
            if os.environ.get('SEARCH3_NEXT_LIVE_ALLOWED') != 'v12' or os.environ.get('GITHUB_RUN_ATTEMPT') != '1': raise RuntimeError('live_authorization_missing')
            for name, expected in EXPECTED.items():
                response = context.request.get(ORIGIN + BASE + name, timeout=30000, max_redirects=0)
                digest = hashlib.sha256(response.body()).hexdigest()
                result['sourceHashes'][name] = digest
                if response.status != 200 or digest != expected: raise RuntimeError('served_source_mismatch')
            context.add_init_script(OBSERVER)
            page = context.new_page()
            def page_error(_):
                result['pageErrorCount'] = result.get('pageErrorCount', 0) + 1
            page.on('pageerror', page_error)
            def route(r):
                q = r.request
                if guard.allow(q.url, q.method, q.post_data): r.continue_()
                else: r.abort()
            context.route('**/*', route)
            def response(r):
                try: value = r.json()
                except Exception: value = None
                row = safe_response(r.url, r.status, r.request.post_data, value)
                if row is not None and len(result['network']) < 150: result['network'].append(row)
            page.on('response', response)
            result['status'] = 'running'
            state = exercise(page, guard)
            result['state'] = state
            page.evaluate('window.AnyTourPrototypeData?.stop()')
            result['widths'] = []
            for width in (1280, 390):
                page.set_viewport_size({'width': width, 'height': 900})
                page.wait_for_timeout(100)
                page.screenshot(path=str(out / f'results-{width}.png'))
                result['widths'].append({'width': width, 'overflow': page.evaluate('document.documentElement.scrollWidth > innerWidth + 2')})
            counts = (state.get('counts') or {}).get('offersByProvider', {})
            result['status'] = 'passed' if state['submits'] == 1 and state['complete'] and state['hotelCards'] > 0 and not guard.denied and not result.get('pageErrorCount', 0) and all(counts.get(p, 0) > 0 for p in PROVIDERS) else 'incomplete'
        except Exception as e:
            allowed = {'live_authorization_missing', 'served_source_mismatch', 'unexpected_search_before_click'}
            result['status'] = 'blocked'
            result['reason'] = str(e) if str(e) in allowed else 'browser_or_bootstrap_failure'
        finally:
            result['calls'] = guard.calls
            result['blocked'] = guard.denied[:20]
            browser.close()
            (out / 'result.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result['status'] == 'passed' else 1

if __name__ == '__main__':
    raise SystemExit(main())
