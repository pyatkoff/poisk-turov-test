"""One initial NEXT search, one SAMO quote, optional flight choice, preview form.

Reuse the established first-page observer and request scope. Supplier identities,
opaque refs and request bodies stay in memory; retained evidence is aggregate only.
No search Continue, quote replay, real lead, booking or payment is permitted.
"""
from __future__ import annotations
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
from urllib.parse import urlparse

_spec = importlib.util.spec_from_file_location('next_initial_base', Path(__file__).with_name('search3_next_live_source_counts.py'))
base = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(base)
SOURCE = '7cd20c1b6faf3e0be00a0539a54fd625a304f018'
EXPECTED = dict(base.EXPECTED, **{'prototype-search/data.js': 'd29d892d52e12b23dd7610c82357b030dc2a3697b39f15e2b5e562032663d83e'})
QUOTE_PATH = base.ANEX + 'api-andromeda-quote-preview.php'
FLIGHT_REF = re.compile(r'flight_[a-f0-9]{32}')
MONEY = re.compile(r'(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?')

CANDIDATE_OBSERVER = r"""(() => {
 const descriptor=Object.getOwnPropertyDescriptor(window,'AnyTourPrototypeSearchLifecycleV1');
 window.__nextSamoCandidate=null;
 Object.defineProperty(window,'AnyTourPrototypeSearchLifecycleV1',{configurable:true,get:descriptor.get,set(api){
  descriptor.set({...api,create(options){const after=options.afterEvent;
   return api.create({...options,afterEvent(e,r){
    if(e.type==='results'&&Array.isArray(e.hotels)){
     window.__nextSamoCandidate=null;
     for(const h of e.hotels){
      const o=(h.offers||[]).find(o=>o.provider==='andromeda'&&!o.cached&&o.raw?.quoteRequired===true
       &&o.raw?.offer_context?.page===1&&!o.raw?.offer_context?.hotel_scope&&/^listing_[a-f0-9]{64}$/.test(o.raw?.listing_price_ref||''));
      if(o){const raw=o.raw,ctx=raw.offer_context;window.__nextSamoCandidate={hotelId:h.id,name:h.name,key:o.key,localId:raw.andromedaLocalHotelId,
       request:{action:'quote',generation:ctx.generation,page:ctx.page,params:raw.andromedaSearchParams,offer_context:ctx,listing_price_ref:raw.listing_price_ref}};break;}
     }
    }
    return after?.call(this,e,r);
   }});
  }});
 }});
})();"""


def money(value):
    if not isinstance(value, dict) or value.get('currency') != 'RUB':
        return None
    amount = value.get('amount')
    if not isinstance(amount, str) or not MONEY.fullmatch(amount) or not re.search('[1-9]', amount):
        return None
    return {'amount': amount, 'currency': 'RUB'}


class JourneyGuard(base.Guard):
    def __init__(self):
        super().__init__()
        self.selected = None
        self.quote_calls = {'quote': 0, 'quote_select_flights': 0}
        self.flight_refs = {'0': set(), '1': set()}
        self.quote_state = None
        self.final_price = None
        self.reply_counts = []
        self.lead_attempts = 0
        self.journey_stage = 'not_started'

    def select(self, candidate):
        if not self.armed or self.selected is not None or not isinstance(candidate, dict):
            raise RuntimeError('selection_not_available')
        request = candidate.get('request')
        if (not isinstance(request, dict) or set(request) != {'action', 'generation', 'page', 'params', 'offer_context', 'listing_price_ref'}
                or request.get('action') != 'quote' or type(request.get('generation')) is not int or request['generation'] < 1
                or type(request.get('page')) is not int or request['page'] != 1 or not self.scope(request.get('params', {}))
                or type(candidate.get('localId')) is not int or candidate['localId'] < 1):
            raise RuntimeError('selection_scope_invalid')
        context = request.get('offer_context')
        if (not isinstance(context, dict) or set(context) != {'provider', 'search_ref', 'generation', 'page', 'offer_ref'}
                or context.get('provider') != 'andromeda' or context.get('generation') != request['generation'] or context.get('page') != 1
                or not isinstance(context.get('search_ref'), str) or not re.fullmatch('[a-f0-9]{64}', context['search_ref'])
                or not isinstance(context.get('offer_ref'), str) or not re.fullmatch('offer_[a-f0-9]{64}', context['offer_ref'])
                or not isinstance(request.get('listing_price_ref'), str) or not re.fullmatch('listing_[a-f0-9]{64}', request['listing_price_ref'])):
            raise RuntimeError('selection_identity_invalid')
        self.selected = copy.deepcopy(candidate)

    def allow(self, url, method, raw_body=None):
        parsed = urlparse(url)
        if parsed.scheme + '://' + parsed.netloc != base.ORIGIN:
            return False
        if re.search(r'(?:lead|booking|payment).*\.php$', parsed.path, re.I):
            self.lead_attempts += 1
            return self.deny('transaction_endpoint_blocked')
        if parsed.path != QUOTE_PATH:
            return super().allow(url, method, raw_body)
        body = self.body(raw_body)
        action = body.get('action')
        if (not self.armed or method != 'POST' or parsed.query or self.selected is None
                or action not in self.quote_calls or self.quote_calls[action]):
            return self.deny('quote_attempt_budget')
        expected = copy.deepcopy(self.selected['request'])
        if action == 'quote_select_flights':
            choice = body.get('flight_selection')
            if (self.quote_calls['quote'] != 1 or self.quote_state != 'flight_selection_required'
                    or not isinstance(choice, dict) or set(choice) != {'provider', 'outbound_ref', 'return_ref'}
                    or choice.get('provider') != 'andromeda'
                    or choice.get('outbound_ref') not in self.flight_refs['0']
                    or choice.get('return_ref') not in self.flight_refs['1']):
                return self.deny('flight_selection_scope')
            expected['action'] = action
            expected['flight_selection'] = choice
        if body != expected:
            return self.deny('quote_identity_or_scope')
        self.quote_calls[action] = 1
        return True

    def observe(self, status, payload):
        if status != 200 or not isinstance(payload, dict) or payload.get('ok') is not True or self.selected is None:
            return
        value = payload.get('data')
        if (not isinstance(value, dict) or value.get('schema_version') != 1 or value.get('provider') != 'andromeda'
                or value.get('local_id') != self.selected['localId'] or value.get('booking_enabled') is not False
                or value.get('selection_enabled') is not True):
            return
        flights = value.get('flights')
        if not isinstance(flights, list) or len(flights) > 1000:
            return
        if value.get('state') == 'flight_selection_required' and value.get('final_price_verified') is False and value.get('final_price') is None:
            refs = {'0': set(), '1': set()}
            for row in flights:
                if not isinstance(row, dict) or row.get('direction') not in refs:
                    return
                ref = row.get('flight_ref')
                if not isinstance(ref, str) or not FLIGHT_REF.fullmatch(ref) or ref in refs['0'] or ref in refs['1']:
                    return
                refs[row['direction']].add(ref)
            if all(refs.values()):
                self.flight_refs = refs
                self.quote_state = 'flight_selection_required'
                self.reply_counts.append({key: len(value) for key, value in refs.items()})
        elif (value.get('state') == 'quote_verified' and value.get('final_price_verified') is True
                and value.get('flight_selection_required') is False and money(value.get('final_price')) is not None):
            self.final_price = money(value['final_price'])
            self.quote_state = 'quote_verified'


def exercise_selection(page, guard, out):
    guard.journey_stage = 'select_candidate'
    candidate = page.evaluate('window.__nextSamoCandidate')
    guard.select(candidate)
    if not page.evaluate("String(window.V2_CONFIG?.leadApi||'').endsWith('/preview-lead-disabled.php')"):
        raise RuntimeError('preview_lead_boundary_missing')
    guard.journey_stage = 'open_hotel_offers'
    page.locator('#hotel-query').fill(candidate['name'])
    page.locator('[data-action="all-offers"][data-id="'+str(candidate['hotelId'])+'"]').first.click()
    guard.journey_stage = 'select_offer'
    # The canonical adapter already encoded this exact DOM identity.
    key = candidate['key']
    offer = page.locator('#modal-body [data-action="offer"][data-key="'+key+'"]')
    if not offer.is_visible():
        offer.locator('xpath=ancestor::section[contains(@class,"offer-group")]').locator('[data-action="offer-group"]').click()
    offer.click()
    guard.journey_stage = 'quote'
    page.locator('[data-action="refresh-hotel"]').click()
    page.wait_for_function("document.querySelector('[data-action=apply-andromeda-flights]') || document.querySelector('[data-action=andromeda-application-preview]') || document.querySelector('#modal-body .error-text')", timeout=55000)
    if page.locator('[data-action="apply-andromeda-flights"]').count():
        guard.journey_stage = 'select_flights'
        outbound = page.locator('[name="andromeda-outbound"]')
        inbound = page.locator('[name="andromeda-return"]')
        counts = {'0': outbound.count(), '1': inbound.count()}
        if counts != {key: len(value) for key, value in guard.flight_refs.items()} or not all(counts.values()):
            raise RuntimeError('ui_flight_choices_mismatch')
        # One explicit test selection, not a cheapest-flight assertion or supplier retry.
        outbound.first.check()
        inbound.first.check()
        page.screenshot(path=str(out / 'samo-flight-choice-1280.png'))
        page.locator('[data-action="apply-andromeda-flights"]').click()
    guard.journey_stage = 'open_application'
    page.locator('[data-action="andromeda-application-preview"]').click(timeout=55000)
    if guard.quote_state != 'quote_verified' or guard.final_price is None:
        raise RuntimeError('verified_quote_missing')
    before = dict(guard.quote_calls)
    guard.journey_stage = 'check_application'
    page.locator('[name="phone"]').fill('+7 999 123-45-67')
    page.locator('[name="consent"]').check()
    page.locator('[type="submit"][form="prototype-lead-form"]').click()
    page.wait_for_function("document.querySelector('#prototype-lead-form')?.dataset.checked==='1'", timeout=10000)
    message = page.locator('.lead-message').inner_text()
    if 'не отправлена' not in message.lower() or 'Подтверждённая стоимость' not in message:
        raise RuntimeError('application_preview_not_verified')
    shown = re.sub(r'\s', '', message)
    expected = format(float(guard.final_price['amount']), '.0f')
    if expected not in shown:
        raise RuntimeError('application_price_mismatch')
    widths = []
    guard.journey_stage = 'responsive_application'
    for width in (1280, 390):
        page.set_viewport_size({'width': width, 'height': 900})
        page.wait_for_timeout(150)
        overflow = page.evaluate("document.documentElement.scrollWidth>innerWidth+2 || document.querySelector('#modal').scrollWidth>document.querySelector('#modal').clientWidth+2")
        widths.append({'width': width, 'overflow': overflow})
        page.screenshot(path=str(out / ('samo-application-'+str(width)+'.png')))
    if before != guard.quote_calls or guard.lead_attempts or any(row['overflow'] for row in widths):
        raise RuntimeError('application_boundary_failed')
    guard.journey_stage = 'complete'
    return {'application_checked': True, 'real_lead_sent': False, 'final_price': guard.final_price,
            'flight_choices': guard.reply_counts, 'widths': widths}


def main():
    from playwright.sync_api import sync_playwright
    out = Path('search3-next-live-source-counts')
    out.mkdir(exist_ok=True)
    guard = JourneyGuard()
    result = {'source': SOURCE, 'route': base.BASE, 'trip': base.TRIP, 'status': 'not_started', 'sourceHashes': {}, 'pageErrorCount': 0}
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1280, 'height': 900}, service_workers='block')
        page = None
        try:
            if os.environ.get('SEARCH3_NEXT_LIVE_ALLOWED') != 'v19' or os.environ.get('GITHUB_RUN_ATTEMPT') != '1':
                raise RuntimeError('live_authorization_missing')
            for name, expected in EXPECTED.items():
                response = context.request.get(base.ORIGIN + base.BASE + name, timeout=30000, max_redirects=0)
                digest = hashlib.sha256(response.body()).hexdigest()
                result['sourceHashes'][name] = digest
                if response.status != 200 or digest != expected:
                    raise RuntimeError('served_source_mismatch')
            context.add_init_script(base.OBSERVER + '\n' + CANDIDATE_OBSERVER)
            page = context.new_page()
            def page_error(_):
                result['pageErrorCount'] += 1
            page.on('pageerror', page_error)
            def route(r):
                q = r.request
                if guard.allow(q.url, q.method, q.post_data):
                    r.continue_()
                else:
                    r.abort()
            context.route('**/*', route)
            def observe(r):
                if urlparse(r.url).path != QUOTE_PATH:
                    return
                try:
                    guard.observe(r.status, r.json())
                except Exception:
                    pass  # Invalid/failed response never grants continuation authority.
            page.on('response', observe)
            result['initial'] = base.exercise(page, guard)
            if not result['initial'].get('complete') or guard.denied or guard.calls != dict.fromkeys(base.PROVIDERS, 1):
                raise RuntimeError('initial_batch_not_complete')
            result['journey'] = exercise_selection(page, guard, out)
            if guard.denied or result['pageErrorCount']:
                raise RuntimeError('browser_or_budget_failure')
            result['status'] = 'passed'
        except Exception as error:
            allowed = {'live_authorization_missing', 'served_source_mismatch', 'selection_not_available', 'selection_scope_invalid',
                       'selection_identity_invalid', 'preview_lead_boundary_missing', 'ui_flight_choices_mismatch',
                       'verified_quote_missing', 'application_preview_not_verified', 'application_price_mismatch',
                       'application_boundary_failed', 'initial_batch_not_complete', 'browser_or_budget_failure'}
            result['status'] = 'incomplete'
            result['reason'] = str(error) if str(error) in allowed else 'ui_journey_not_completed'
            if page is not None:
                try:
                    page.screenshot(path=str(out / 'samo-incomplete.png'), timeout=5000)
                except Exception:
                    pass
        finally:
            guard.armed = False
            result['initialCalls'] = dict(guard.calls)
            result['quoteCalls'] = dict(guard.quote_calls)
            result['quoteState'] = guard.quote_state
            result['journeyStage'] = guard.journey_stage
            result['leadAttempts'] = guard.lead_attempts
            result['blocked'] = guard.denied[:20]
            browser.close()
            (out / 'result.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result['status'] == 'passed' else 1


if __name__ == '__main__':
    raise SystemExit(main())
