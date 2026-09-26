import importlib.util
import json
from pathlib import Path
import unittest
from urllib.parse import urlencode
import os

ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location('runner',ROOT/'scripts/diagnostics/search3_next_live_source_counts.py')
m=importlib.util.module_from_spec(SPEC);SPEC.loader.exec_module(m)
PARAMS=dict(departureId='1',countryId='4',dateFrom='2026-10-10',dateTo='2026-10-16',nightsFrom=7,nightsTo=7,adults=2,childs=[])

class TransportTests(unittest.TestCase):
    def test_no_boot_search_and_one_explicit_start(self):
        url=m.ORIGIN+'/api-v2.php?'+urlencode(dict(action='search_start',**{k:v for k,v in PARAMS.items() if k!='childs'}))
        g=m.Guard();self.assertFalse(g.allow(url,'GET'));self.assertEqual(g.calls['tourvisor'],0)
        g=m.Guard();g.armed=True
        self.assertTrue(g.allow(url,'GET'));self.assertFalse(g.allow(url,'GET'));self.assertEqual(g.calls['tourvisor'],1)
    def test_scope_and_money_guards(self):
        g=m.Guard();g.armed=True
        for action in ['quote','expand','additional_prices_batch']:
            self.assertFalse(g.allow(m.ORIGIN+m.ANEX+'api-anex-search3-preview.php','POST',json.dumps(dict(action=action,params=PARAMS))))
        self.assertFalse(g.allow(m.ORIGIN+m.ANEX+'api-andromeda-quote-preview.php','POST','{}'))
        self.assertFalse(g.allow(m.ORIGIN+'/lead-receiver-v1.php','POST','{}'))
        self.assertFalse(g.allow(m.ORIGIN+m.ANEX+'api-anex-search3-preview.php','POST',json.dumps(dict(action='search',params={**PARAMS,'dateTo':'2026-10-17'}))))
        self.assertFalse(g.allow('https://mc.yandex.ru/watch/1','GET'))
        self.assertEqual(sum(g.calls.values()),0)
    def test_sequential_bounded_samo_pages(self):
        g=m.Guard();g.armed=True;u=m.ORIGIN+m.ANEX+'api-andromeda-search3-preview.php'
        self.assertFalse(g.allow(u,'POST',json.dumps(dict(page=2,params=PARAMS))))
        for page in range(1,41):self.assertTrue(g.allow(u,'POST',json.dumps(dict(page=page,params=PARAMS))))
        self.assertFalse(g.allow(u,'POST',json.dumps(dict(page=41,params=PARAMS))))
        self.assertFalse(g.allow(u,'POST',json.dumps(dict(page=1,params=PARAMS))))
        self.assertEqual(g.calls['andromeda'],40)
    def test_no_raw_error_or_identity_export(self):
        row=m.safe_response(m.ORIGIN+m.ANEX+'api-anex-search3-preview.php',502,'{}',dict(ok=False,error='password=DO_NOT_LOG',data=dict(provider='anex',hotels=[dict(secret='DO_NOT_LOG')],received_offers=3,mapped_offers=True)))
        self.assertNotIn('DO_NOT_LOG',json.dumps(row));self.assertEqual(row['errorCategory'],'other_error');self.assertEqual(row['receivedHotels'],1);self.assertNotIn('mapped_offers',row)

class BrowserTests(unittest.TestCase):
    def test_actual_browser_click_and_canonical_not_legacy_counts(self):
        from playwright.sync_api import sync_playwright
        original_origin=m.ORIGIN;m.ORIGIN='http://127.0.0.1:18001'
        self.addCleanup(setattr,m,'ORIGIN',original_origin)
        html='''<!doctype html><html><body><form id="search-form"><button class="search-submit" disabled>Найти туры</button></form><div id="cards"></div><script>
        window.V2Results={state:{items:[{tours:[{provider:'wrong_legacy'}]}]}};
        window.AnyTourPrototypeData={live:true,stop(){}};
        window.AnyTourPrototypeSearchLifecycleV1=Object.freeze({create(o){const r={phase:'loading'};document.querySelector('form').addEventListener('submit',async e=>{e.preventDefault();const params=PARAMS;
          const send=(x)=>o.afterEvent(x,r);
          for(const provider of ['tourvisor','anex','andromeda'])send({type:'provider',provider,status:'loading'});
          await fetch('/api-v2.php?'+new URLSearchParams({action:'search_start',...Object.fromEntries(Object.entries(params).filter(([k])=>k!=='childs'))}));
          await fetch('/_preview/search3-anex-candidate/api-anex-search3-preview.php',{method:'POST',body:JSON.stringify({action:'search',params})});
          await fetch('/_preview/search3-anex-candidate/api-andromeda-search3-preview.php',{method:'POST',body:JSON.stringify({page:1,params})});
          send({type:'results',hotels:[{id:1,offers:[{provider:'tourvisor',operator:'ANEX',private:'DO_NOT_LOG'},{provider:'anex',operator:'ANEX'}]},{id:2,offers:[{provider:'andromeda'}]}]});
          document.querySelector('#cards').innerHTML='<article class="hotel-card"></article><article class="hotel-card"></article>';
          for(const provider of ['tourvisor','anex','andromeda'])send({type:'provider',provider,status:'complete'});
          r.phase='complete';send({type:'complete',sources:{}});
        });return {bound:true};}});
        const lifecycle=AnyTourPrototypeSearchLifecycleV1.create({afterEvent(){window.forwarded=(window.forwarded||0)+1;}});
        if(!lifecycle.bound)throw new Error('return contract lost');document.querySelector('button').disabled=false;
        </script></body></html>'''.replace('PARAMS',json.dumps(PARAMS))
        with sync_playwright() as p:
            opts={'headless':True}
            if os.environ.get('TEST_CHROMIUM'):opts['executable_path']=os.environ['TEST_CHROMIUM']
            b=p.chromium.launch(**opts);context=b.new_context();context.add_init_script(m.OBSERVER);page=context.new_page();guard=m.Guard();forwarded=[]
            def route(r):
                if not guard.allow(r.request.url,r.request.method,r.request.post_data):r.abort();return
                forwarded.append(r.request.url)
                if r.request.is_navigation_request():r.fulfill(content_type='text/html',body=html)
                else:r.fulfill(content_type='application/json',body='{"ok":true}')
            context.route('**/*',route)
            try:
                result=m.exercise(page,guard,timeout=5000)
                self.assertEqual(result['submits'],1);self.assertEqual(result['hotelCards'],2)
                self.assertEqual(result['calls'],dict(tourvisor=1,anex=1,andromeda=1))
                self.assertEqual(result['counts']['offersByProvider'],dict(tourvisor=1,anex=1,andromeda=1))
                self.assertEqual(result['counts']['hotels'],2);self.assertTrue(result['complete']);self.assertEqual(result['blocked'],[])
                self.assertGreater(page.evaluate('window.forwarded'),1);self.assertNotIn('DO_NOT_LOG',json.dumps(result));self.assertNotIn('wrong_legacy',json.dumps(result))
            finally:b.close()

if __name__=='__main__':unittest.main()
