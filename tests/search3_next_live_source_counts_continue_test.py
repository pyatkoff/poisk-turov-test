import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location('continue_runner', ROOT / 'scripts/diagnostics/search3_next_live_source_counts_continue.py')
m = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(m)
ANEX = m.base.ORIGIN + m.base.ANEX + 'api-anex-search3-preview.php'
SAMO = m.base.ORIGIN + m.base.ANEX + 'api-andromeda-search3-preview.php'
PARAMS = dict(departureId='1', countryId='4', dateFrom='2026-10-10', dateTo='2026-10-16', nightsFrom=7, nightsTo=7, adults=2, childs=[])
REF = 'a' * 32

def ready():
    guard = m.ContinueGuard()
    guard.armed = True
    request = json.dumps(dict(action='search', generation=1, params=PARAMS))
    assert guard.allow(ANEX, 'POST', request)
    guard.observe(ANEX, 200, request, {'ok': True, 'data': {'provider': 'anex', 'generation': 1, 'search_ref': REF, 'pages_read': 1, 'first_page_only': True, 'continuation': {'state': 'available', 'pages_read': 1, 'next_page': 2}}})
    return guard

class ContinuationGuards(unittest.TestCase):
    def test_anex_requires_click_identity_and_one_attempt(self):
        body = json.dumps(dict(action='continue', generation=1, search_ref=REF, page=2))
        guard = ready()
        self.assertFalse(guard.allow(ANEX, 'POST', body))
        guard.continuing = True
        self.assertFalse(guard.allow(ANEX, 'POST', body.replace(REF, 'b' * 32)))
        self.assertTrue(guard.allow(ANEX, 'POST', body))
        self.assertFalse(guard.allow(ANEX, 'POST', body))
        self.assertFalse(guard.allow(ANEX, 'POST', json.dumps(dict(action='search', generation=1, params=PARAMS))))

    def test_samo_first_page_then_only_second_explicitly(self):
        guard = m.ContinueGuard()
        guard.armed = True
        for page, expected in [(1, True), (2, False)]:
            self.assertEqual(guard.allow(SAMO, 'POST', json.dumps(dict(action='search', params=PARAMS, page=page))), expected)
        guard.continuing = True
        self.assertTrue(guard.allow(SAMO, 'POST', json.dumps(dict(action='search', params=PARAMS, page=2))))
        self.assertFalse(guard.allow(SAMO, 'POST', json.dumps(dict(action='search', params=PARAMS, page=2))))
        self.assertFalse(guard.allow(SAMO, 'POST', json.dumps(dict(action='search', params=PARAMS, page=3))))

    def test_no_ticket_no_quote_no_lead(self):
        guard = m.ContinueGuard()
        guard.armed = guard.continuing = True
        self.assertFalse(guard.allow(ANEX, 'POST', json.dumps(dict(action='continue', generation=1, search_ref=REF, page=2))))
        for action in ('quote', 'offer', 'additional_prices'):
            self.assertFalse(guard.allow(ANEX, 'POST', json.dumps({'action': action})))
        self.assertFalse(guard.allow(m.base.ORIGIN + '/lead.php', 'POST', '{}'))

    def test_tv_continue_once_after_initial(self):
        guard = m.ContinueGuard()
        guard.armed = guard.continuing = True
        url = m.base.ORIGIN + '/api-v2.php?action=search_continue&searchId=test'
        self.assertFalse(guard.allow(url, 'GET'))
        guard.calls['tourvisor'] = 1
        self.assertTrue(guard.allow(url, 'GET'))
        self.assertFalse(guard.allow(url, 'GET'))

    def test_retention_uses_canonical_key_total_not_supplier_id_price(self):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            page = browser.new_page()
            page.evaluate(m.base.OBSERVER)
            page.evaluate(m.RETENTION_OBSERVER)
            page.evaluate('''() => {
                window.AnyTourPrototypeSearchLifecycleV1={create(options){window.send=e=>options.afterEvent(e,{});return {};}};
                window.AnyTourPrototypeSearchLifecycleV1.create({});
                window.rows=[{id:'private-hotel',offers:[
                  {key:'anex%3Aprivate-a',provider:'anex',total:100},
                  {key:'anex%3Aprivate-b',provider:'anex',total:150}
                ]}];
                window.send({type:'results',hotels:window.rows});
            }''')
            self.assertEqual(page.evaluate('window.__nextContinue.capture()'), {'offers': 2, 'unique': 2})
            page.evaluate("window.rows[0].offers.push({key:'anex%3Aprivate-new',provider:'anex',total:200});window.send({type:'results',hotels:window.rows});")
            result = page.evaluate('window.__nextContinue.compare()')
            self.assertEqual(result['added']['anex'], 1)
            self.assertEqual(result['missing'], 0)
            self.assertEqual(result['changed'], 0)
            self.assertTrue(result['identityUnique'])
            self.assertNotIn('private-', json.dumps(result))
            page.evaluate('window.rows[0].offers[0].total=101')
            self.assertEqual(page.evaluate('window.__nextContinue.compare()')['changed'], 1)
            page.evaluate('window.rows[0].offers.pop();delete window.rows[0].offers[0].key')
            with self.assertRaisesRegex(Exception, 'canonical_offer_shape'):
                page.evaluate('window.__nextContinue.compare()')
            browser.close()

if __name__ == '__main__':
    unittest.main()
