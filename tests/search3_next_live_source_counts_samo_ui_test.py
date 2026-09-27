import copy
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('samo_ui', ROOT / 'scripts/diagnostics/search3_next_live_source_counts_samo_ui.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
PARAMS = dict(departureId='1', countryId='4', dateFrom=m.base.TRIP['from'], dateTo=m.base.TRIP['to'], nightsFrom=7, nightsTo=7, adults=2, childs=[])
CTX = dict(provider='andromeda', search_ref='a'*64, generation=1, page=1, offer_ref='offer_'+'b'*64)
REQUEST = dict(action='quote', generation=1, page=1, params=PARAMS, offer_context=CTX, listing_price_ref='listing_'+'c'*64)
# data.js supplies the already encoded DOM offer key to the lifecycle observer.
CANDIDATE = dict(hotelId=501, name='Fictional hotel', key='andromeda%3Afixture', localId=101, request=REQUEST)
URL = m.base.ORIGIN + m.QUOTE_PATH
REFS = ['flight_'+'1'*32, 'flight_'+'2'*32]
PENDING = dict(ok=True, data=dict(schema_version=1, provider='andromeda', local_id=101, booking_enabled=False, selection_enabled=True,
    state='flight_selection_required', final_price_verified=False, final_price=None, flights=[dict(direction=str(i), flight_ref=ref) for i, ref in enumerate(REFS)]))
FINAL = dict(ok=True, data=dict(schema_version=1, provider='andromeda', local_id=101, booking_enabled=False, selection_enabled=True,
    state='quote_verified', final_price_verified=True, flight_selection_required=False, final_price=dict(amount='125500', currency='RUB'), flights=[]))


def selected():
    g = m.JourneyGuard()
    g.armed = True
    g.select(CANDIDATE)
    return g


def continuation():
    return dict(REQUEST, action='quote_select_flights', flight_selection=dict(provider='andromeda', outbound_ref=REFS[0], return_ref=REFS[1]))


class GuardTests(unittest.TestCase):
    def test_quote_failure_receipt_keeps_fixed_classification_only(self):
        g=selected()
        g.observe(502,dict(ok=False,failure_category='supplier_transport',error='private supplier token'))
        self.assertEqual(g.quote_responses[0]['failureCategory'],'supplier_transport')
        self.assertEqual(g.quote_responses[0]['httpStatus'],502)
        self.assertIsNone(g.quote_state)
        self.assertNotIn('private',json.dumps(g.receipt()))
        value=m.safe_quote_failure(dict(action='quote',code='quote_unconfirmed',httpStatus=0,failureCategory='timeout',raw='secret'))
        self.assertEqual(value,dict(action='quote',code='quote_unconfirmed',httpStatus=0,failureCategory='timeout'))
        value=m.safe_quote_failure(dict(action='private',code=['secret'],httpStatus=True,failureCategory='token'))
        self.assertEqual(value,dict(action='other',code='other',httpStatus=0,failureCategory='other'))

    def test_one_quote_and_one_observed_choice_only(self):
        g = selected()
        self.assertTrue(g.allow(URL, 'POST', json.dumps(REQUEST)))
        self.assertFalse(g.allow(URL, 'POST', json.dumps(REQUEST)))
        self.assertFalse(g.allow(URL, 'POST', json.dumps(continuation())))
        g.observe(200, PENDING)
        self.assertTrue(g.allow(URL, 'POST', json.dumps(continuation())))
        self.assertFalse(g.allow(URL, 'POST', json.dumps(continuation())))
        self.assertEqual(g.quote_calls, dict(quote=1, quote_select_flights=1))

    def test_selection_scope_and_foreign_bodies(self):
        for change in [lambda x: x['request']['params'].update(dateTo='2026-11-01'), lambda x: x['request']['offer_context'].update(hotel_scope={}), lambda x: x.update(localId=0)]:
            c = copy.deepcopy(CANDIDATE)
            change(c)
            g = m.JourneyGuard()
            g.armed = True
            with self.assertRaises(RuntimeError):
                g.select(c)
        for change in [lambda x: x.update(generation=2), lambda x: x['params'].update(adults=3), lambda x: x.update(supplier_offer_id='private'), lambda x: x.update(action='quote_select_flights')]:
            g = selected()
            body = copy.deepcopy(REQUEST)
            change(body)
            self.assertFalse(g.allow(URL, 'POST', json.dumps(body)))
            self.assertEqual(sum(g.quote_calls.values()), 0)

    def test_failed_or_foreign_reply_never_grants_choice(self):
        for status, payload in [(502, PENDING), (200, dict(ok=False)), (200, dict(ok=True, data={**PENDING['data'], 'local_id': 999})), (200, dict(ok=True, data={**PENDING['data'], 'booking_enabled': True}))]:
            g = selected()
            self.assertTrue(g.allow(URL, 'POST', json.dumps(REQUEST)))
            g.observe(status, payload)
            self.assertFalse(g.allow(URL, 'POST', json.dumps(continuation())))
            self.assertIsNone(g.quote_state)

    def test_quote_requires_selected_owner_and_post(self):
        g = m.JourneyGuard()
        self.assertFalse(g.allow(URL, 'POST', json.dumps(REQUEST)))
        g = selected()
        for method in ['GET', 'DELETE']:
            self.assertFalse(g.allow(URL, method, json.dumps(REQUEST)))
        self.assertFalse(g.allow(URL+'?bypass=1', 'POST', json.dumps(REQUEST)))
        self.assertFalse(g.allow('https://other.example'+m.QUOTE_PATH, 'POST', json.dumps(REQUEST)))
        self.assertEqual(sum(g.quote_calls.values()), 0)

    def test_initial_page_and_lead_boundaries_are_preserved(self):
        g = selected()
        samo = m.base.ORIGIN + m.base.ANEX + 'api-andromeda-search3-preview.php'
        self.assertTrue(g.allow(samo, 'POST', json.dumps(dict(page=1, params=PARAMS))))
        self.assertFalse(g.allow(samo, 'POST', json.dumps(dict(page=2, params=PARAMS))))
        self.assertFalse(g.allow(m.base.ORIGIN+'/api-v2.php?action=search_continue', 'POST', '{}'))
        self.assertFalse(g.allow(m.base.ORIGIN+m.base.BASE+'preview-lead-disabled.php', 'POST', '{}'))
        self.assertEqual(g.lead_attempts, 1)
        self.assertEqual(g.calls['andromeda'], 1)


class BrowserTests(unittest.TestCase):
    def test_browser_failure_events_are_bounded_and_redacted(self):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            browser=p.chromium.launch(headless=True)
            page=browser.new_page()
            try:
                page.add_init_script(m.QUOTE_FAILURE_OBSERVER)
                page.goto('data:text/html,<meta charset="utf-8">')
                result=page.evaluate('''()=>{
                 for(let i=0;i<4;i++)window.dispatchEvent(new CustomEvent('anytour:quote-failure',{detail:{provider:'andromeda',action:'quote',code:'quote_unconfirmed',httpStatus:502,failureCategory:i?'secret supplier response':'supplier_transport',raw:'private token'}}));
                 return window.__nextQuoteFailures;
                }''')
                self.assertEqual(len(result),2)
                self.assertEqual(result[0]['failureCategory'],'supplier_transport')
                self.assertEqual(result[1]['failureCategory'],'other')
                self.assertNotIn('secret',json.dumps(result));self.assertNotIn('private',json.dumps(result))
            finally:browser.close()

    def test_actual_click_sequence_uses_guard_and_local_form_only(self):
        from playwright.sync_api import sync_playwright
        with tempfile.TemporaryDirectory() as tmp, sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            context = browser.new_context(viewport=dict(width=1280, height=900))
            page = context.new_page()
            page.set_default_timeout(2000)
            guard = m.JourneyGuard()
            guard.armed = True
            guard.calls = dict.fromkeys(m.base.PROVIDERS, 1)
            html = '''<!doctype html><html><head><meta charset="utf-8"></head><body><input id="hotel-query"><button data-action="all-offers" data-id="501">Offers</button><div id="modal" style="max-width:100%;box-sizing:border-box"><div id="modal-body"><button data-action="offer" data-key="andromeda%3Afixture">Select</button><button data-action="refresh-hotel">Quote</button><div id="stage"></div></div></div><script>
            window.__nextSamoCandidate=CANDIDATE;window.V2_CONFIG={leadApi:'/preview-lead-disabled.php'};
            document.querySelector('[data-action=refresh-hotel]').onclick=async()=>{await fetch(ENDPOINT,{method:'POST',body:JSON.stringify(REQUEST)});document.querySelector('#stage').innerHTML='<input type="radio" name="andromeda-outbound"><input type="radio" name="andromeda-return"><button data-action="apply-andromeda-flights">Apply</button>';document.querySelector('[data-action=apply-andromeda-flights]').onclick=async()=>{await fetch(ENDPOINT,{method:'POST',body:JSON.stringify(CONTINUATION)});document.querySelector('#stage').innerHTML='<button data-action="andromeda-application-preview">Application</button>';document.querySelector('[data-action=andromeda-application-preview]').onclick=()=>{document.querySelector('#stage').innerHTML='<form id="prototype-lead-form"><input name="phone"><input type="checkbox" name="consent"><div class="lead-message"></div></form><button type="submit" form="prototype-lead-form">Check</button>';document.querySelector('form').onsubmit=e=>{e.preventDefault();e.target.dataset.checked='1';document.querySelector('.lead-message').textContent='Подтверждённая стоимость: 125 500 ₽. Заявка не отправлена.';};};};};
            </script></body></html>'''
            for name, value in [('CANDIDATE', CANDIDATE), ('REQUEST', REQUEST), ('CONTINUATION', continuation()), ('ENDPOINT', URL)]:
                html = html.replace(name, json.dumps(value))
            def route(r):
                q = r.request
                if not guard.allow(q.url, q.method, q.post_data):
                    r.abort()
                elif q.is_navigation_request():
                    r.fulfill(content_type='text/html', body=html)
                else:
                    body = json.loads(q.post_data)
                    value = PENDING if body['action'] == 'quote' else FINAL
                    guard.observe(200, value)
                    r.fulfill(content_type='application/json', body=json.dumps(value))
            context.route('**/*', route)
            try:
                page.goto(m.base.ORIGIN+m.base.BASE+'visual-search/')
                self.assertEqual(page.evaluate('document.characterSet'), 'UTF-8')
                result = m.exercise_selection(page, guard, Path(tmp))
                self.assertTrue(result['application_checked'])
                self.assertEqual(guard.journey_stage, 'complete')
                self.assertEqual(result['final_price'], dict(amount='125500', currency='RUB'))
                self.assertEqual(guard.quote_calls, dict(quote=1, quote_select_flights=1))
                self.assertEqual(guard.lead_attempts, 0)
                self.assertEqual(guard.denied, [])
                self.assertFalse(any(x['overflow'] for x in result['widths']))
            finally:
                browser.close()


if __name__ == '__main__':
    unittest.main()
