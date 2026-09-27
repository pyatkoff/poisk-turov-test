import copy
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('selected_ui', ROOT/'scripts/diagnostics/search3_next_live_source_counts_tv_anex_ui.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
DAY = m.base.TRIP['from']
GROUP = 'anex_online:'+'a'*64
CONCRETE = 'anex_online:'+'b'*64
REF = 'c'*32
TV = dict(hotelId=501, name='TV fixture', key='tourvisor%3Atest', tourId='test', day=DAY, nights=7, room='ROOM', meal='AI')
PARAMS = dict(departureId='1', countryId='4', dateFrom=DAY, dateTo=DAY, nightsFrom=7, nightsTo=7, adults=2, childs=[], hotelIds=['101'])
ANEX = dict(hotelId=502, name='ANEX fixture', key='anex%3Agroup', day=DAY, nights=7, room='ROOM', meal='AI', params=PARAMS, localId=101, generation=1, offerRef=GROUP)
CHILD = dict(hotelId=502, name='', key='anex%3Aconcrete', day=DAY, nights=7, room='CONCRETE', meal='AI', localId=101, generation=2, offerRef=CONCRETE, searchRef=REF)
SEARCH = dict(ok=True, data=dict(provider='anex', generation=2, date_range=dict(**{'from':DAY,'to':DAY}), search_ref=REF, hotels=[dict(local_id=101, tours=[dict(offer_ref=GROUP,kind='group_minimum',search_ref=REF)])]))
EXPAND = dict(ok=True, data=dict(provider='anex', generation=2, search_ref=REF, offer_ref=GROUP, status='expanded', hotels=[dict(local_id=101,tours=[dict(offer_ref=CONCRETE,kind='concrete',search_ref=REF)])]))
CURRENT = dict(ok=True, data=dict(provider='anex', generation=2, search_ref=REF, offer_ref=CONCRETE, selection_state='disabled', status='current', offer=dict(final_price_verified=False), context=dict(status='current', current_context_verified=True, selection_state='disabled')))
ADDITIONAL = dict(ok=True, data=dict(provider='anex', generation=2, search_ref=REF, offer_ref=CONCRETE, selection_state='disabled', additional_prices=dict(application_state='applied', arithmetic_applied=True, final_price_verified=False, included_in_search_price=False, search_price=dict(amount='121000'), party_surcharge=dict(amount='2000.50'), search_plus_additional=dict(amount='123000.50'))))
ANEX_URL = m.base.ORIGIN+m.ANEX_PATH
def tv_url(action, identity='test'):
    return m.base.ORIGIN+'/api-v2.php?'+urlencode(dict(action=action,tourId=identity,currency='RUB'))
def ready():
    g=m.SelectedGuard();g.armed=True;g.calls=dict.fromkeys(m.base.PROVIDERS,1)
    return g
def searched(g):
    g.select_anex(ANEX)
    body=dict(action='search',generation=2,params=PARAMS)
    assert g.allow(ANEX_URL,'POST',json.dumps(body))
    g.observe_response(ANEX_URL,200,SEARCH,json.dumps(body))
def concrete(g):
    searched(g)
    body=g.identity('expand');assert g.allow(ANEX_URL,'POST',json.dumps(body))
    g.observe_response(ANEX_URL,200,EXPAND,json.dumps(body));g.select_concrete(CHILD)


class Guards(unittest.TestCase):
    def test_tv_exact_quote_then_one_flight_read(self):
        g=ready();g.select_tv(TV)
        self.assertFalse(g.allow(tv_url('flights'),'GET'))
        self.assertFalse(g.allow(tv_url('tour','foreign'),'GET'))
        self.assertTrue(g.allow(tv_url('tour'),'GET'))
        g.observe_response(tv_url('tour'),200,dict(ok=True,data=dict(id='test',price=120000)))
        self.assertTrue(g.allow(tv_url('flights'),'GET'))
        self.assertFalse(g.allow(tv_url('flights'),'GET'))
        self.assertFalse(g.allow(tv_url('tour'),'GET'))
        self.assertEqual(g.selected_calls['tour'],1)

    def test_anex_exact_chain_and_no_replay(self):
        g=ready();concrete(g)
        for action,payload in [('offer',CURRENT),('additional_prices',ADDITIONAL)]:
            body=g.identity(action,True)
            self.assertTrue(g.allow(ANEX_URL,'POST',json.dumps(body)))
            g.observe_response(ANEX_URL,200,payload,json.dumps(body))
            self.assertFalse(g.allow(ANEX_URL,'POST',json.dumps(body)))
        self.assertEqual(g.estimate,m.Decimal('123000.50'))
        self.assertEqual(g.selected_calls,dict(tour=0,flights=0,search=1,expand=1,offer=1,additional_prices=1))

    def test_only_verified_current_server_envelope_grants_additional_prices(self):
        for change in [lambda d:d.pop('context'),
                       lambda d:d['offer'].update(context=d.pop('context')),
                       lambda d:d['context'].update(current_context_verified=False),
                       lambda d:d['context'].update(status='expired'),
                       lambda d:d['context'].update(selection_state='enabled')]:
            g=ready();concrete(g)
            p=copy.deepcopy(CURRENT);change(p['data'])
            g.observe_response(ANEX_URL,200,p,json.dumps(g.identity('offer',True)))
            self.assertFalse(g.anex_current)
            self.assertFalse(g.allow(ANEX_URL,'POST',json.dumps(g.identity('additional_prices',True))))
            self.assertEqual(g.selected_calls['additional_prices'],0)

    def test_foreign_scopes_and_unobserved_transitions(self):
        for change in [lambda c:c['params'].update(hotelIds=['999']),lambda c:c['params'].update(adults=3),lambda c:c.update(day='2026-12-01'),lambda c:c.update(localId=0)]:
            c=copy.deepcopy(ANEX);change(c)
            with self.assertRaises(RuntimeError):ready().select_anex(c)
        g=ready();g.select_anex(ANEX)
        self.assertFalse(g.allow(ANEX_URL,'POST',json.dumps(g.identity('expand'))))
        g=ready();concrete(g)
        self.assertFalse(g.allow(ANEX_URL,'POST',json.dumps(g.identity('additional_prices',True))))
        bad=g.identity('offer',True);bad['offer_ref']=GROUP
        self.assertFalse(g.allow(ANEX_URL,'POST',json.dumps(bad)))

    def test_failed_quote_or_invalid_total_does_not_grant_evidence(self):
        g=ready();g.select_tv(TV)
        g.observe_response(tv_url('tour'),502,dict(ok=True,data=dict(id='test',price=120000)))
        self.assertFalse(g.tv_current)
        g=ready();concrete(g)
        g.observe_response(ANEX_URL,200,CURRENT,json.dumps(g.identity('offer',True)))
        for change in [lambda x:x.update(final_price_verified=True),lambda x:x['search_plus_additional'].update(amount='123001'),lambda x:x.update(included_in_search_price=True)]:
            p=copy.deepcopy(ADDITIONAL);change(p['data']['additional_prices'])
            g.observe_response(ANEX_URL,200,p,json.dumps(g.identity('additional_prices',True)))
            self.assertIsNone(g.estimate)

    def test_no_samo_quote_continue_or_transactions_and_redacted_receipt(self):
        g=ready();concrete(g)
        for path in [m.shared.QUOTE_PATH,m.base.BASE+'preview-lead-disabled.php','/api-v2.php?action=search_continue']:
            self.assertFalse(g.allow(m.base.ORIGIN+path,'POST','{}'))
        g.observe_response(ANEX_URL,502,dict(error='private supplier identity'),json.dumps(g.identity('offer',True)))
        report=json.dumps(g.receipt())
        for private in [REF,GROUP,CONCRETE,'private supplier identity','offer_ref','tourId']:
            self.assertNotIn(private,report)


class Browser(unittest.TestCase):
    def test_response_forwarding_cannot_follow_unapproved_redirect(self):
        from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
        from threading import Thread
        from playwright.sync_api import sync_playwright
        requests=[]
        class Handler(BaseHTTPRequestHandler):
            def log_message(self,*args):pass
            def do_GET(self):
                requests.append(self.path)
                if self.path.startswith('/api-v2.php'):
                    self.send_response(302);self.send_header('Location','/unapproved');self.send_header('Content-Length','0');self.end_headers();return
                body=b'''<script>fetch('/api-v2.php?action=tour&tourId=test&currency=RUB').catch(()=>{}).finally(()=>window.done=true)</script>'''
                self.send_response(200);self.send_header('Content-Type','text/html');self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
        server=ThreadingHTTPServer(('127.0.0.1',0),Handler);thread=Thread(target=server.serve_forever,daemon=True);thread.start()
        original=m.base.ORIGIN;m.base.ORIGIN='http://127.0.0.1:'+str(server.server_port)
        try:
            with sync_playwright() as p:
                browser=p.chromium.launch(headless=True);page=browser.new_page();g=ready();g.select_tv(TV)
                page.route('**/*',lambda r:m.shared.route_request(r,g))
                try:
                    page.goto(m.base.ORIGIN+m.base.BASE+'visual-search/')
                    page.wait_for_function('window.done',timeout=5000)
                    self.assertEqual(len([x for x in requests if x.startswith('/api-v2.php')]),1)
                    self.assertNotIn('/unapproved',requests)
                    self.assertEqual(g.denied,['response_redirect_blocked'])
                    self.assertFalse(g.tv_current)
                    self.assertEqual(g.selected_calls['flights'],0)
                finally:browser.close()
        finally:m.base.ORIGIN=original;server.shutdown();server.server_close();thread.join()

    def test_immediate_client_followup_waits_for_response_authority(self):
        from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
        from threading import Thread
        from playwright.sync_api import sync_playwright
        class Handler(BaseHTTPRequestHandler):
            def log_message(self,*args):pass
            def do_GET(self):
                if self.path.startswith('/api-v2.php'):
                    data=dict(id='test',price=120000) if 'action=tour&' in self.path else [dict(price=dict(value=133500.5))]
                    body=json.dumps(data).encode();kind='application/json'
                else:
                    body=b'''<script>window.done=false;(async()=>{try{await(await fetch('/api-v2.php?action=tour&tourId=test&currency=RUB')).json();await(await fetch('/api-v2.php?action=flights&tourId=test&currency=RUB')).json();}catch(e){}finally{window.done=true;}})();</script>''';kind='text/html'
                self.send_response(200);self.send_header('Content-Type',kind);self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
        server=ThreadingHTTPServer(('127.0.0.1',0),Handler)
        thread=Thread(target=server.serve_forever,daemon=True);thread.start()
        original=m.base.ORIGIN;m.base.ORIGIN='http://127.0.0.1:'+str(server.server_port)
        try:
            with sync_playwright() as p:
                browser=p.chromium.launch(headless=True);page=browser.new_page()
                g=ready();g.select_tv(TV)
                page.route('**/*',lambda r:m.shared.route_request(r,g))
                def delayed_observer(response):
                    if 'action=tour&' not in response.url:return
                    payload=response.json()
                    # A post-delivery observer can finish after the client chains flights.
                    page.wait_for_timeout(75)
                    g.observe_response(response.url,response.status,payload)
                page.on('response',delayed_observer)
                try:
                    page.goto(m.base.ORIGIN+m.base.BASE+'visual-search/')
                    page.wait_for_function('window.done',timeout=5000)
                    page.wait_for_timeout(120)
                    self.assertEqual(g.selected_calls['tour'],1)
                    self.assertEqual(g.selected_calls['flights'],1)
                    self.assertEqual(g.denied,[])
                finally:browser.close()
        finally:
            m.base.ORIGIN=original;server.shutdown();server.server_close();thread.join()

    def test_observer_preserves_frozen_live_bridge_and_payload_contract(self):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            browser=p.chromium.launch(headless=True)
            page=browser.new_page()
            try:
                page.add_init_script(m.base.OBSERVER+'\n'+m.OBSERVER)
                page.route('**/*',lambda r:r.fulfill(content_type='text/html',body='<meta charset="utf-8">'))
                page.goto(m.base.ORIGIN+m.base.BASE+'visual-search/')
                result=page.evaluate('''() => {
                 const api=Object.freeze({init:()=>true,amount:x=>Number(x),expandAnexGroup:async()=>({offers:[]}),leadSession:()=>Object.freeze({payload:()=>({tourId:'test',price:120000,flightPrice:133500.5})})});
                 window.AnyTourPrototypeData=api;
                 window.AnyTourPrototypeData=Object.freeze({...window.AnyTourPrototypeData});
                 const bridge=Object.create(window.AnyTourPrototypeData);Object.defineProperty(bridge,'live',{value:true});window.AnyTourPrototypeData=Object.freeze(bridge);
                 window.AnyTourPrototypeData.leadSession({raw:{id:'test'},total:133500.5,flightChoiceId:'0',variants:[{}]}).payload();
                 return {live:window.AnyTourPrototypeData.live,init:window.AnyTourPrototypeData.init(),payload:window.__nextTvPayload};
                }''')
                self.assertTrue(result['live'])
                self.assertTrue(result['init'])
                self.assertEqual(result['payload'],dict(identityMatches=True,price=133500.5,quotePrice=120000,selectedPrice=133500.5,hasFlight=True))
            finally:browser.close()

    def test_both_actual_click_paths_and_unverified_anex_price(self):
        from playwright.sync_api import sync_playwright
        with tempfile.TemporaryDirectory() as tmp, sync_playwright() as p:
            browser=p.chromium.launch(headless=True)
            page=browser.new_page(viewport=dict(width=1280,height=900))
            page.set_default_timeout(3000)
            g=ready()
            html='''<!doctype html><meta charset="utf-8"><input id="hotel-query">
            <article class="hotel-card" data-hotel-id="501"><button data-action="offer" data-key="tourvisor%3Atest" onclick="tv()">TV</button></article>
            <article class="hotel-card" data-hotel-id="502"><button data-action="offer" data-key="anex%3Agroup" onclick="anex()">ANEX</button></article>
            <dialog id="modal" style="max-width:100%;box-sizing:border-box"><button data-action="close-modal" onclick="document.querySelector('dialog').close()">Close</button><div id="modal-body"></div></dialog><script>
            window.V2_CONFIG={leadApi:'/preview-lead-disabled.php'};window.__nextSelectedCandidates=CANDIDATES;window.__nextAnexExpanded=[];
            const box=document.querySelector('#modal-body'),modal=document.querySelector('dialog');
            const request=async body=>{const r=await fetch(ANEX_URL,{method:'POST',body:JSON.stringify(body)});return r.json();};
            const form=kind=>{box.innerHTML='<form id="prototype-lead-form"><input name="phone"><input type="checkbox" name="consent"><p class="lead-message"></p></form><button type="submit" form="prototype-lead-form">Check</button>';document.querySelector('form').onsubmit=e=>{e.preventDefault();e.target.dataset.checked='1';document.querySelector('.lead-message').textContent=kind==='anex'?'Расчётная сумма: 123 000,50 ₽. Итоговая стоимость требует подтверждения. Заявка не отправлена.':'Данные проверены. Заявка не отправлена.';};};
            async function tv(){modal.showModal();await fetch(TV_QUOTE);await fetch(TV_FLIGHTS);box.innerHTML='<button data-action="choose-flight">Flights</button>';box.querySelector('button').onclick=()=>{box.innerHTML='<input type="radio" name="flight-pair" value="0"><button data-action="apply-flight">Apply</button>';box.querySelector('button').onclick=()=>{box.innerHTML='<button data-action="confirm-tour">Application</button>';box.querySelector('button').onclick=()=>{window.__nextTvPayload={identityMatches:true,price:133500.5,quotePrice:120000,selectedPrice:133500.5,hasFlight:true};form('tv');};};};}
            function anex(){modal.showModal();box.innerHTML='<button data-action="refresh-hotel">Expand</button>';box.querySelector('button').onclick=async()=>{await request(SEARCH_BODY);await request(EXPAND_BODY);window.__nextAnexExpanded=[CHILD];box.innerHTML='<select id="offer-departure" hidden><option>'+DAY+'</option></select><select id="offer-room"><option>CONCRETE</option></select><select id="offer-meal" hidden><option>AI</option></select><button data-action="offer" data-key="anex%3Aconcrete">Concrete</button>';box.querySelector('button').onclick=()=>{box.innerHTML='<button data-action="refresh-hotel">Current</button>';box.querySelector('button').onclick=async()=>{await request(OFFER_BODY);box.innerHTML='<button data-action="anex-additional-prices">Additional</button>';box.querySelector('button').onclick=async()=>{await request(ADDITIONAL_BODY);box.innerHTML='<button data-action="anex-application-preview">Application</button>';box.querySelector('button').onclick=()=>form('anex');};};};};}
            </script>'''
            facts={'CANDIDATES':dict(tourvisor=TV,anex=ANEX),'ANEX_URL':ANEX_URL,'TV_QUOTE':tv_url('tour'),'TV_FLIGHTS':tv_url('flights'),'SEARCH_BODY':dict(action='search',generation=2,params=PARAMS),'EXPAND_BODY':dict(action='expand',generation=2,search_ref=REF,offer_ref=GROUP,local_hotel_id=101),'OFFER_BODY':dict(action='offer',generation=2,search_ref=REF,offer_ref=CONCRETE,local_hotel_id=101),'ADDITIONAL_BODY':dict(action='additional_prices',generation=2,search_ref=REF,offer_ref=CONCRETE,local_hotel_id=101),'CHILD':CHILD,'DAY':DAY}
            import re
            html=re.sub(r'\b('+'|'.join(facts)+r')\b',lambda match:json.dumps(facts[match[0]]),html)
            def route(r):
                q=r.request
                if not g.allow(q.url,q.method,q.post_data):r.abort();return
                if q.is_navigation_request():r.fulfill(content_type='text/html',body=html);return
                if q.url==tv_url('tour'):value=dict(ok=True,data=dict(id='test',price=120000))
                elif q.url==tv_url('flights'):value=dict(ok=True,data=[dict(price=dict(value=133500.5))])
                else:value={'search':SEARCH,'expand':EXPAND,'offer':CURRENT,'additional_prices':ADDITIONAL}[json.loads(q.post_data)['action']]
                g.observe_response(q.url,200,value,q.post_data)
                r.fulfill(content_type='application/json',body=json.dumps(value))
            page.route('**/*',route)
            try:
                page.goto(m.base.ORIGIN+m.base.BASE+'visual-search/')
                result=m.exercise_selected(page,g,Path(tmp))
                self.assertEqual([x['status'] for x in result.values()],['passed','passed'])
                self.assertEqual(result['tourvisor']['price'],'133500.5')
                self.assertEqual(result['anex']['price'],'123000.50')
                self.assertFalse(result['anex']['final_price_verified'])
                self.assertFalse(result['anex']['flights_verified'])
                self.assertEqual(g.selected_calls,dict(tour=1,flights=1,search=1,expand=1,offer=1,additional_prices=1))
                self.assertEqual(g.lead_attempts,0);self.assertEqual(g.denied,[])
                g=ready()
                page.goto(m.base.ORIGIN+m.base.BASE+'visual-search/')
                result=m.exercise_selected(page,g,Path(tmp),('anex',))
                self.assertEqual(list(result),['anex'])
                self.assertEqual(result['anex']['status'],'passed')
                self.assertEqual(g.selected_calls,dict(tour=0,flights=0,search=1,expand=1,offer=1,additional_prices=1))
                self.assertEqual(g.lead_attempts,0);self.assertEqual(g.denied,[])
            finally:browser.close()


if __name__=='__main__':unittest.main()
