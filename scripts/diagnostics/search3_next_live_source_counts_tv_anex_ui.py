"""One initial search and bounded TV/ANEX selections; no SAMO quote or lead.

Use the same pinned NEXT runner and canonical owners. Identities and bodies stay
in memory. Evidence contains only budgets, price states, UI checks and fixed codes.
"""
import copy
from decimal import Decimal, InvalidOperation
import importlib.util
from pathlib import Path
import re
from urllib.parse import parse_qs, urlparse

spec = importlib.util.spec_from_file_location('samo_ui', Path(__file__).with_name('search3_next_live_source_counts_samo_ui.py'))
shared = importlib.util.module_from_spec(spec)
spec.loader.exec_module(shared)
base = shared.base
ANEX_PATH = base.ANEX + 'api-anex-search3-preview.php'

OBSERVER = r"""(() => {
 const candidate=(h,o)=>({hotelId:h.id,name:h.name,key:o.key,day:o.day,nights:o.nights,room:o.room,meal:o.meal,
  tourId:String(o.raw.id),localId:o.raw.anexLocalHotelId,generation:o.raw.anexGeneration,
  searchRef:o.raw.searchRef,offerRef:o.raw.offerRef});
 window.__nextSelectedCandidates={};window.__nextAnexExpanded=[];window.__nextTvPayload=null;
 let current;
 Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>current,set(api){
  const wrapped=Object.create(api);
  const descriptors=Object.getOwnPropertyDescriptors(api);
  delete descriptors.expandAnexGroup;delete descriptors.leadSession;
  Object.defineProperties(wrapped,descriptors);
  Object.defineProperties(wrapped,{
   expandAnexGroup:{enumerable:true,value:async o=>{const value=await api.expandAnexGroup(o);
    window.__nextAnexExpanded=value.offers.map(row=>candidate({id:row.hotelId,name:''},row));return value;}},
   leadSession:{enumerable:true,value:o=>{const session=api.leadSession(o);return Object.freeze({...session,payload(fd){const value=session.payload(fd);
    window.__nextTvPayload={identityMatches:String(value.tourId)===String(o.raw.id),price:api.amount(value.flightPrice),quotePrice:api.amount(value.price),
     selectedPrice:o.total,hasFlight:!!o.variants?.[Number(o.flightChoiceId)]};return value;}});}}
  });current=Object.freeze(wrapped);
 }});
 const descriptor=Object.getOwnPropertyDescriptor(window,'AnyTourPrototypeSearchLifecycleV1');
 Object.defineProperty(window,'AnyTourPrototypeSearchLifecycleV1',{configurable:true,get:descriptor.get,set(api){
  descriptor.set({...api,create(options){const after=options.afterEvent;
   return api.create({...options,afterEvent(e,r){
    if(e.type==='results'&&Array.isArray(e.hotels)){
     const found={};
     for(const h of e.hotels)for(const o of [...h.offers].sort((a,b)=>a.total-b.total)){
      if(o.cached||found[o.provider])continue;
      if(o.provider==='tourvisor'&&o.raw.selectionEnabled!==false)found.tourvisor=candidate(h,o);
      if(o.provider==='anex'&&o.raw.anexKind==='group_minimum'){
       const c=candidate(h,o),exact={...o.search,from:o.day,to:o.day,minNights:o.nights,maxNights:o.nights,adults:o.adults,ages:[...o.ages]};
       c.params=window.AnyTourPrototypeData.params(exact,[String(c.localId)],o.meal&&!/уточняется/i.test(o.meal)?{meals:[o.meal]}:{});found.anex=c;
      }
     }window.__nextSelectedCandidates=found;
    }return after?.call(this,e,r);
   }});
  }});
 }});
})();"""


def amount(value):
    if isinstance(value, dict):
        value = value.get('value', value.get('amount'))
    if isinstance(value, bool) or value is None:
        return None
    try:
        number = Decimal(str(value))
        return number if number.is_finite() and 0 < number < Decimal('1000000000000') else None
    except InvalidOperation:
        return None


class SelectedGuard(shared.JourneyGuard):
    response_paths = {'/api-v2.php', ANEX_PATH}

    def __init__(self):
        super().__init__()
        self.tv = self.anex = self.concrete = None
        self.selected_calls = dict(tour=0, flights=0, search=0, expand=0, offer=0, additional_prices=0)
        self.tv_current = False
        self.tv_price = None
        self.flight_prices = []
        self.anex_ref = None
        self.concrete_refs = set()
        self.anex_current = False
        self.estimate = None
        self.journeys = {}
        self.responses = []

    def select_tv(self, value):
        if (not self.armed or self.tv is not None or not isinstance(value, dict)
                or not isinstance(value.get('tourId'), str) or not 1 <= len(value['tourId']) <= 300
                or not base.TRIP['from'] <= str(value.get('day', '')) <= base.TRIP['to'] or value.get('nights') != 7):
            raise RuntimeError('selection_scope_invalid')
        self.tv = copy.deepcopy(value)

    def select_anex(self, value):
        if not self.armed or self.anex is not None or not isinstance(value, dict):
            raise RuntimeError('selection_scope_invalid')
        p = value.get('params', {})
        expected = dict(p, dateFrom=base.TRIP['from'], dateTo=base.TRIP['to'])
        if (not base.Guard.scope(expected) or p.get('dateFrom') != p.get('dateTo') or p.get('dateFrom') != value.get('day')
                or not base.TRIP['from'] <= str(value.get('day', '')) <= base.TRIP['to']
                or type(value.get('localId')) is not int or value['localId'] < 1
                or p.get('hotelIds') != [str(value['localId'])] or value.get('nights') != 7
                or type(value.get('generation')) is not int or value['generation'] < 1
                or not re.fullmatch('anex_online:[a-f0-9]{64}', str(value.get('offerRef', '')))):
            raise RuntimeError('selection_scope_invalid')
        self.anex = copy.deepcopy(value)

    def select_concrete(self, value):
        if (self.concrete is not None or not self.anex or not isinstance(value, dict)
                or value.get('offerRef') not in self.concrete_refs or value.get('searchRef') != self.anex_ref
                or value.get('generation') != self.anex['generation'] + 1 or value.get('localId') != self.anex['localId']):
            raise RuntimeError('selection_identity_invalid')
        self.concrete = copy.deepcopy(value)

    def identity(self, action, concrete=False):
        c = self.concrete if concrete else self.anex
        return dict(action=action, generation=self.anex['generation']+1, search_ref=self.anex_ref,
                    offer_ref=c['offerRef'], local_hotel_id=self.anex['localId'])

    def allow(self, url, method, raw_body=None):
        u = urlparse(url)
        if u.scheme+'://'+u.netloc != base.ORIGIN:
            return False
        q = parse_qs(u.query)
        action = q.get('action', [''])[0]
        if u.path == '/api-v2.php' and action in ('tour', 'flights'):
            expected = {'action': [action], 'tourId': [self.tv['tourId']], 'currency': ['RUB']} if self.tv else None
            if (not self.armed or method != 'GET' or q != expected or self.selected_calls[action]
                    or action == 'flights' and not self.tv_current):
                return self.deny('tv_selected_identity_or_budget')
            self.selected_calls[action] = 1
            return True
        body = self.body(raw_body)
        action = body.get('action')
        if u.path == ANEX_PATH and self.anex is not None:
            if not self.armed or method != 'POST' or u.query or action not in ('search', 'expand', 'offer', 'additional_prices') or self.selected_calls[action]:
                return self.deny('anex_selected_budget')
            if action == 'search':
                expected = dict(action=action, generation=self.anex['generation']+1, params=self.anex['params'])
            elif action == 'expand' and self.anex_ref:
                expected = self.identity(action)
            elif action == 'offer' and self.concrete:
                expected = self.identity(action, True)
            elif action == 'additional_prices' and self.concrete and self.anex_current:
                expected = self.identity(action, True)
            else:
                return self.deny('anex_unobserved_transition')
            if body != expected:
                return self.deny('anex_selected_identity_or_scope')
            self.selected_calls[action] = 1
            return True
        return super().allow(url, method, raw_body)

    def observe_response(self, url, status, payload, raw_body=None):
        u = urlparse(url)
        action = parse_qs(u.query).get('action', [self.body(raw_body).get('action', '')])[0]
        if action not in self.selected_calls:
            return
        # Fixed classifications only; never retain provider text or identities.
        error = payload.get('error') if isinstance(payload, dict) else None
        category = error.get('category') if isinstance(error, dict) else error
        allowed = {'supplier_rejected', 'supplier_auth', 'rate_limited', 'search_expired', 'invalid_request', 'supplier_unavailable'}
        self.responses.append({'action': action, 'httpStatus': status, 'errorCategory': category if isinstance(category, str) and category in allowed else 'other_error' if error else None})
        if status != 200:
            return
        value = payload.get('data') if isinstance(payload, dict) and payload.get('ok') is True else payload
        if u.path == '/api-v2.php' and self.tv:
            if action == 'tour' and isinstance(value, dict) and str(value.get('id')) == self.tv['tourId'] and amount(value.get('price')):
                self.tv_current = True
                self.tv_price = amount(value['price'])
            elif action == 'flights' and self.tv_current:
                rows = value.get('flights') if isinstance(value, dict) else value
                if isinstance(rows, list) and len(rows) <= 1000:
                    self.flight_prices = [amount(row.get('price')) if isinstance(row, dict) else None for row in rows]
            return
        if not self.anex or not isinstance(payload, dict) or payload.get('ok') is not True or not isinstance(value, dict):
            return
        if value.get('provider') != 'anex' or value.get('generation') != self.anex['generation']+1:
            return
        if action == 'search':
            ref = value.get('search_ref')
            hotels = value.get('hotels', [])
            if (re.fullmatch('[a-f0-9]{32}', str(ref)) and value.get('date_range') == {'from': self.anex['day'], 'to': self.anex['day']}
                    and any(h.get('local_id') == self.anex['localId'] and any(t.get('offer_ref') == self.anex['offerRef'] and t.get('kind') == 'group_minimum' and t.get('search_ref') == ref for t in h.get('tours', [])) for h in hotels)):
                self.anex_ref = ref
        elif action == 'expand' and value.get('search_ref') == self.anex_ref and value.get('offer_ref') == self.anex['offerRef'] and value.get('status') == 'expanded':
            for h in value.get('hotels', []):
                if h.get('local_id') == self.anex['localId']:
                    self.concrete_refs.update(t['offer_ref'] for t in h.get('tours', []) if t.get('kind') == 'concrete' and t.get('search_ref') == self.anex_ref and re.fullmatch('anex_online:[a-f0-9]{64}', str(t.get('offer_ref', ''))))
        elif self.concrete and value.get('search_ref') == self.anex_ref and value.get('offer_ref') == self.concrete['offerRef'] and value.get('selection_state') == 'disabled':
            if action == 'offer' and value.get('status') == 'current' and value.get('offer', {}).get('context', {}).get('current_context_verified') is True and value.get('offer', {}).get('final_price_verified') is False:
                self.anex_current = True
            elif action == 'additional_prices' and self.anex_current:
                p = value.get('additional_prices', {})
                search, extra, total = [amount(p.get(k)) for k in ('search_price', 'party_surcharge', 'search_plus_additional')]
                if (p.get('application_state') == 'applied' and p.get('arithmetic_applied') is True
                        and p.get('final_price_verified') is False and p.get('included_in_search_price') is False
                        and all(x is not None for x in (search, extra, total)) and search+extra == total):
                    self.estimate = total

    def receipt(self):
        return dict(super().receipt(), selectedCalls=dict(self.selected_calls), journeys=self.journeys, selectedResponses=self.responses[:20])


def click_offer(page, candidate, from_card=True):
    key = candidate['key']
    if from_card:
        page.locator('#hotel-query').fill(candidate['name'])
        card = page.locator('.hotel-card[data-hotel-id="'+str(candidate['hotelId'])+'"]')
        direct = card.locator('[data-action="offer"][data-key="'+key+'"]')
        if direct.count():
            direct.click()
            return
        card.locator('[data-action="all-offers"]').click()
    # These are local refinements only, not supplier requests.
    for field, value in [('departure', candidate['day']), ('room', candidate['room']), ('meal', candidate['meal'])]:
        control = page.locator('#offer-'+field)
        if control.is_visible():
            control.select_option(value)
    offer = page.locator('#modal-body [data-action="offer"][data-key="'+key+'"]')
    if not offer.is_visible():
        offer.locator('xpath=ancestor::section[contains(@class,"offer-group")]').locator('[data-action="offer-group"]').click()
    offer.click()


def application(page, guard, out, provider):
    before = dict(guard.selected_calls)
    page.locator('[name="phone"]').fill('+7 999 123-45-67')
    page.locator('[name="consent"]').check()
    page.locator('[type="submit"][form="prototype-lead-form"]').click()
    page.wait_for_function("document.querySelector('#prototype-lead-form')?.dataset.checked==='1'", timeout=10000)
    message = page.locator('.lead-message').inner_text()
    if 'не отправлена' not in message.lower() or guard.lead_attempts or before != guard.selected_calls:
        raise RuntimeError('application_boundary_failed')
    widths = []
    for width in (1280, 390):
        page.set_viewport_size({'width': width, 'height': 900})
        page.wait_for_timeout(150)
        overflow = page.evaluate("document.documentElement.scrollWidth>innerWidth+2 || document.querySelector('#modal').scrollWidth>document.querySelector('#modal').clientWidth+2")
        widths.append({'width': width, 'overflow': overflow})
        page.screenshot(path=str(out / (provider+'-application-'+str(width)+'.png')))
    if any(row['overflow'] for row in widths):
        raise RuntimeError('application_boundary_failed')
    return message, widths


def exercise_selected(page, guard, out):
    if not page.evaluate("String(window.V2_CONFIG?.leadApi||'').endsWith('/preview-lead-disabled.php')"):
        raise RuntimeError('preview_lead_boundary_missing')
    candidates = page.evaluate('window.__nextSelectedCandidates')
    for provider in ('tourvisor', 'anex'):
        try:
            guard.journey_stage = provider+'_select'
            if provider == 'tourvisor':
                guard.select_tv(candidates.get(provider))
                click_offer(page, guard.tv)
                page.locator('[data-action="choose-flight"]').click(timeout=55000)
                choice = page.locator('[name="flight-pair"]:not(:disabled)').first
                index = int(choice.get_attribute('value'))
                choice.check()
                if not 0 <= index < len(guard.flight_prices) or guard.flight_prices[index] is None:
                    raise RuntimeError('verified_quote_missing')
                expected = guard.flight_prices[index]
                page.locator('[data-action="apply-flight"]').click()
                page.locator('[data-action="confirm-tour"]').click()
                guard.journey_stage = 'tourvisor_application'
                _, widths = application(page, guard, out, provider)
                payload = page.evaluate('window.__nextTvPayload')
                if (not isinstance(payload, dict) or payload.get('identityMatches') is not True or payload.get('hasFlight') is not True
                        or amount(payload.get('price')) != expected or amount(payload.get('selectedPrice')) != expected
                        or amount(payload.get('quotePrice')) != guard.tv_price):
                    raise RuntimeError('application_price_mismatch')
                guard.journeys[provider] = {'status': 'passed', 'application_checked': True, 'price': str(expected), 'flight_variants': len(guard.flight_prices), 'payload_matches_selected_offer_and_price': True, 'widths': widths}
            else:
                guard.select_anex(candidates.get(provider))
                click_offer(page, guard.anex)
                page.locator('[data-action="refresh-hotel"]').click()
                page.wait_for_function('window.__nextAnexExpanded.length>0', timeout=55000)
                expanded = page.evaluate('window.__nextAnexExpanded')
                guard.select_concrete(expanded[0])
                click_offer(page, guard.concrete, False)
                guard.journey_stage = 'anex_current'
                page.locator('[data-action="refresh-hotel"]').click()
                page.locator('[data-action="anex-additional-prices"]').click(timeout=35000)
                page.locator('[data-action="anex-application-preview"]').click(timeout=35000)
                guard.journey_stage = 'anex_application'
                message, widths = application(page, guard, out, provider)
                match = re.search(r'Расчётная сумма:\s*([0-9\s,.]+)\s*₽', message)
                shown = amount(re.sub(r'\s', '', match[1]).replace(',', '.')) if match else None
                if (guard.estimate is None or shown != guard.estimate
                        or 'Расчётная сумма' not in message or 'требует подтверждения' not in message or 'Подтверждённая стоимость' in message):
                    raise RuntimeError('application_price_mismatch')
                guard.journeys[provider] = {'status': 'passed', 'application_checked': True, 'price': str(guard.estimate), 'price_kind': 'estimate', 'final_price_verified': False, 'flights_verified': False, 'widths': widths}
        except Exception as error:
            known = {'selection_scope_invalid', 'selection_identity_invalid', 'verified_quote_missing', 'application_price_mismatch', 'application_boundary_failed'}
            guard.journeys[provider] = {'status': 'incomplete', 'stage': guard.journey_stage, 'reason': str(error) if str(error) in known else 'ui_step_unavailable'}
            page.screenshot(path=str(out / (provider+'-incomplete.png')))
        finally:
            if page.locator('#modal[open]').count():
                page.locator('[data-action="close-modal"]').first.click()
            page.set_viewport_size({'width': 1280, 'height': 900})
    if any(x['status'] != 'passed' for x in guard.journeys.values()):
        raise RuntimeError('selected_journeys_incomplete')
    guard.journey_stage = 'complete'
    return guard.journeys


if __name__ == '__main__':
    raise SystemExit(shared.main(SelectedGuard(), OBSERVER, exercise_selected, 'v20'))
