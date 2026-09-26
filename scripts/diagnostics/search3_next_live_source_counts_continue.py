"""One installed-NEXT search and one Continue click; no quote, lead, or replay.

Reuse the existing browser observer, first-search scope, and response redaction.
Continuation authority remains in memory and never enters the retained report.
"""
from __future__ import annotations
import importlib.util
import hashlib
import json
import os
from pathlib import Path
import re
from urllib.parse import parse_qs, urlparse

_spec = importlib.util.spec_from_file_location('next_counts_base', Path(__file__).with_name('search3_next_live_source_counts.py'))
base = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(base)
SOURCE = 'adc92d77d8906d66996e299dd9b1e346da122480'
EXPECTED = dict(base.EXPECTED, **{'prototype-search/data.js': '61eb8a8e34c48b9e3f621b3b3872b6fbd595c0e881644bf967b0ad7423879a58'})

RETENTION_OBSERVER = r"""(() => {
 const descriptor=Object.getOwnPropertyDescriptor(window,'AnyTourPrototypeSearchLifecycleV1');
 let rows=[],before=null;
 const snapshot=()=>{
  const offers=new Map();let total=0;
  for(const h of rows)for(const o of h.offers||[]){
   // data.js project()/offer() publishes key and total, not raw supplier id/price.
   if(typeof o.key!=='string'||!o.key||!Number.isFinite(o.total)||o.total<=0)throw new Error('canonical_offer_shape');
   total++;const key=JSON.stringify([h.id,o.provider,o.key]);
   offers.set(key,{price:JSON.stringify(o.total),provider:o.provider});
  }
  return {offers,total};
 };
 Object.defineProperty(window,'AnyTourPrototypeSearchLifecycleV1',{configurable:true,get:descriptor.get,set(api){
  descriptor.set({...api,create(options){const after=options.afterEvent;
   return api.create({...options,afterEvent(e,r){if(e.type==='results'&&Array.isArray(e.hotels))rows=e.hotels;return after?.call(this,e,r);}});
  }});
 }});
 window.__nextContinue={capture(){before=snapshot();window.__nextAcceptance.complete=false;return {offers:before.total,unique:before.offers.size};},compare(){
  const now=snapshot();let missing=0,changed=0;const added={tourvisor:0,anex:0,andromeda:0};
  const sampleRetained={};
  for(const [key,value]of before.offers){const current=now.offers.get(key);if(!current)missing++;else if(current.price!==value.price)changed++;
   if(!(value.provider in sampleRetained))sampleRetained[value.provider]=!!current&&current.price===value.price;}
  for(const [key,value]of now.offers)if(!before.offers.has(key)&&value.provider in added)added[value.provider]++;
  return {before:before.total,after:now.total,identityUnique:before.offers.size===before.total&&now.offers.size===now.total,missing,changed,added,sampleRetained};
 }};
})();"""

class ContinueGuard(base.Guard):
    def __init__(self):
        super().__init__()
        self.continuing = False
        self.continues = dict.fromkeys(base.PROVIDERS, 0)
        self.ticket = None
        self.anex_accepted = False

    def allow(self, url, method, raw_body=None):
        parsed = urlparse(url)
        if parsed.scheme + '://' + parsed.netloc != base.ORIGIN:
            return False
        body = self.body(raw_body)
        action = parse_qs(parsed.query).get('action', [body.get('action', '')])[0]
        if parsed.path == '/api-v2.php' and action == 'search_continue':
            if not self.armed or not self.continuing or self.calls['tourvisor'] != 1 or self.continues['tourvisor'] or method not in ('GET', 'POST'):
                return self.deny('tv_continue_budget')
            self.continues['tourvisor'] = 1
            return True
        if parsed.path == base.ANEX + 'api-anex-search3-preview.php' and action == 'continue':
            expected = (body.get('generation'), body.get('search_ref'), body.get('page'))
            if not self.armed or not self.continuing or method != 'POST' or self.continues['anex'] or self.ticket is None or expected != self.ticket or type(body.get('generation')) is not int or type(body.get('page')) is not int or set(body) != {'action', 'generation', 'search_ref', 'page'}:
                return self.deny('anex_continue_budget_or_identity')
            self.continues['anex'] = 1
            return True
        if parsed.path == base.ANEX + 'api-andromeda-search3-preview.php':
            page = body.get('page')
            if page != (2 if self.continuing else 1):
                return self.deny('andromeda_explicit_page_budget')
            allowed = super().allow(url, method, raw_body)
            if allowed and self.continuing:
                self.continues['andromeda'] += 1
            return allowed
        return super().allow(url, method, raw_body)

    def observe(self, url, status, raw_body, payload):
        if urlparse(url).path != base.ANEX + 'api-anex-search3-preview.php' or status != 200 or not isinstance(payload, dict) or payload.get('ok') is not True:
            return
        request = self.body(raw_body)
        data = payload.get('data')
        if not isinstance(data, dict) or data.get('provider') != 'anex':
            return
        ref, generation = data.get('search_ref'), data.get('generation')
        if type(generation) is not int or generation != request.get('generation') or not isinstance(ref, str) or not re.fullmatch('[a-f0-9]{32}', ref):
            return
        if request.get('action') == 'search' and self.calls['anex'] == 1 and not self.continuing:
            if data.get('continuation') == {'state': 'available', 'pages_read': 1, 'next_page': 2} and data.get('pages_read') == 1 and data.get('first_page_only') is True:
                self.ticket = (generation, ref, 2)
        elif request.get('action') == 'continue' and self.continues['anex'] == 1:
            self.anex_accepted = self.ticket == (generation, ref, data.get('page')) and data.get('pages_read') == 1 and data.get('first_page_only') is False


def continue_once(page, guard):
    button = page.locator('[data-action="continue-search"]')
    if guard.ticket is None or button.count() != 1 or not button.is_visible():
        return {'clicked': False, 'reason': 'anex_continuation_not_available'}
    captured = page.evaluate('window.__nextContinue.capture()')
    if not captured['offers'] or captured['offers'] != captured['unique']:
        return {'clicked': False, 'reason': 'initial_offer_identity_unavailable', 'captured': captured}
    guard.continuing = True
    button.click(timeout=10000)
    try:
        page.wait_for_function('window.__nextAcceptance.complete', timeout=80000)
    except Exception:
        pass  # Preserve a single attempt; never replay an uncertain request.
    return {'clicked': True, 'retention': page.evaluate('window.__nextContinue.compare()'), 'state': page.evaluate('window.__nextAcceptance')}


def main():
    from playwright.sync_api import sync_playwright
    out = Path('search3-next-live-source-counts')
    out.mkdir(exist_ok=True)
    result = {'source': SOURCE, 'route': base.BASE, 'trip': base.TRIP, 'status': 'not_started', 'network': [], 'sourceHashes': {}, 'leadCalls': 0, 'quoteCalls': 0}
    guard = ContinueGuard()
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1280, 'height': 900}, service_workers='block')
        try:
            if os.environ.get('SEARCH3_NEXT_LIVE_ALLOWED') != 'v14' or os.environ.get('GITHUB_RUN_ATTEMPT') != '1':
                raise RuntimeError('live_authorization_missing')
            for name, expected in EXPECTED.items():
                response = context.request.get(base.ORIGIN + base.BASE + name, timeout=30000, max_redirects=0)
                digest = hashlib.sha256(response.body()).hexdigest()
                result['sourceHashes'][name] = digest
                if response.status != 200 or digest != expected:
                    raise RuntimeError('served_source_mismatch')
            context.add_init_script(base.OBSERVER + '\n' + RETENTION_OBSERVER)
            page = context.new_page()
            def page_error(_):
                result['pageErrorCount'] = result.get('pageErrorCount', 0) + 1
            page.on('pageerror', page_error)
            def route(r):
                q = r.request
                if guard.allow(q.url, q.method, q.post_data):
                    r.continue_()
                else:
                    r.abort()
            context.route('**/*', route)
            def observe(r):
                try:
                    value = r.json()
                except Exception:
                    value = None
                guard.observe(r.url, r.status, r.request.post_data, value)
                row = base.safe_response(r.url, r.status, r.request.post_data, value)
                if row is not None and len(result['network']) < 150:
                    result['network'].append(row)
            page.on('response', observe)
            result['status'] = 'running'
            initial = base.exercise(page, guard)
            result['initial'] = initial
            result['initialCalls'] = dict(guard.calls)
            continuation = continue_once(page, guard) if initial.get('complete') else {'clicked': False, 'reason': 'initial_not_complete'}
            result['continuation'] = continuation
            page.evaluate('window.AnyTourPrototypeData?.stop()')
            result['widths'] = []
            for width in (1280, 390):
                page.set_viewport_size({'width': width, 'height': 900})
                page.wait_for_timeout(100)
                page.screenshot(path=str(out / f'results-{width}.png'))
                result['widths'].append({'width': width, 'overflow': page.evaluate('document.documentElement.scrollWidth > innerWidth + 2')})
            counts = (initial.get('counts') or {}).get('offersByProvider', {})
            retained = continuation.get('retention', {})
            state = continuation.get('state', {})
            good = initial.get('submits') == 1 and all(counts.get(provider, 0) > 0 for provider in base.PROVIDERS)
            good = good and continuation.get('clicked') and state.get('complete') and state.get('submits') == 1 and guard.anex_accepted
            good = good and retained.get('identityUnique') and retained.get('missing') == 0 and retained.get('changed') == 0 and retained.get('added', {}).get('anex', 0) > 0
            good = good and not guard.denied and not result.get('pageErrorCount', 0) and not any(row['overflow'] for row in result['widths'])
            result['status'] = 'passed' if good else 'incomplete'
        except Exception as error:
            allowed = {'live_authorization_missing', 'served_source_mismatch', 'unexpected_search_before_click'}
            result['status'] = 'blocked'
            result['reason'] = str(error) if str(error) in allowed else 'browser_or_bootstrap_failure'
        finally:
            result['calls'] = dict(guard.calls)
            result['continueCalls'] = dict(guard.continues)
            result['anexPageAccepted'] = guard.anex_accepted
            result['blocked'] = guard.denied[:20]
            browser.close()
            (out / 'result.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result['status'] == 'passed' else 1

if __name__ == '__main__':
    raise SystemExit(main())
