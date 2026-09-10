#!/usr/bin/env python3
import argparse
import importlib.util
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('probe', ROOT / 'scripts/diagnostics/andromeda_retained_contexts.py')
probe = importlib.util.module_from_spec(spec)
spec.loader.exec_module(probe)
parser = argparse.ArgumentParser()
parser.add_argument('--runtime', type=Path)
args, _ = parser.parse_known_args()


class ResultContractTest(unittest.TestCase):
    def good(self):
        now = int(time.time())
        return {'status':'ok','observed_at':now,'ttl_seconds':900,'active_searches':1,
            'candidates':[{'created_at':now-2,'expires_at':now+898,
                'capture_request':{'version':1,'operation':'capture-selected-retained-offer-1717',
                    'runtime_source':probe.RUNTIME_SOURCE,'local_country_id':4,
                    'selection':{'provider':'andromeda','search_ref':'a'*64,'generation':7,'page':1,
                        'offer_ref':'offer_'+'b'*64,'hotel_scope':'3414','operator_ref':'5','local_id':900}},
                'display':{'hotel':'Fixture hotel','operator':'ANEX','check_in':'22.09.2026','nights':7,
                    'room':'Standard','meal':'RO','price_amount':'83080','price_currency':'RUB'}}],
            'supplier_calls':0,'database_writes':0,'capture_invoked':False}

    def test_contract_and_private_output_file(self):
        value = self.good()
        with tempfile.TemporaryDirectory() as d:
            out = Path(d) / 'evidence'
            result = probe.inspect(lambda source, request: value, out)
            self.assertEqual(value, result)
            self.assertEqual(0o700, out.stat().st_mode & 0o777)
            self.assertEqual(0o600, (out/'result.json').stat().st_mode & 0o777)
            self.assertEqual(value, json.loads((out/'result.json').read_text()))

    def test_unknown_is_fixed_and_never_contains_exception(self):
        with tempfile.TemporaryDirectory() as d:
            result = probe.inspect(lambda *_: (_ for _ in ()).throw(RuntimeError('PRIVATE ssh host secret')), Path(d)/'out')
        self.assertEqual({'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,
            'database_writes':0,'capture_invoked':False}, result)

    def test_extra_private_or_wrong_identity_is_rejected(self):
        for mutate in ('extra','provider','runtime','expired'):
            value = self.good()
            if mutate == 'extra': value['candidates'][0]['private_supplier_id'] = 'PRIVATE'
            if mutate == 'provider': value['candidates'][0]['capture_request']['selection']['provider'] = 'other'
            if mutate == 'runtime': value['candidates'][0]['capture_request']['runtime_source'] = '0'*40
            if mutate == 'expired': value['candidates'][0]['expires_at'] = value['observed_at']
            with self.assertRaises(ValueError): probe.validate_result(value)

    def test_remote_source_has_no_supplier_operation(self):
        source = probe.remote_source()
        self.assertNotIn("action']??null)!=='broninit", source)
        self.assertNotIn('AnyTourAndromedaTransport(', source)
        self.assertNotIn('->start(', source)
        self.assertNotIn('->package(', source)
        self.assertNotIn('anytour_andromeda_search3_budget(', source)
        self.assertIn('AnyTourAndromedaSelectedOffer::publicSelection', source)
        self.assertIn("decision_status='accepted'", source)


@unittest.skipUnless(args.runtime is not None, 'hosted runtime fixture only')
class ActualRemoteReadTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        home = Path(self.temp.name) / 'account'
        self.root = home / 'www/anytoour.ru'
        self.target = self.root / '_preview/search3-anex-candidate'
        self.target.mkdir(parents=True)
        runtime = args.runtime.resolve()
        shutil.copytree(runtime/'app/integrations', self.target/'app/integrations')
        shutil.copy2(runtime/'v2/api-andromeda-search3-preview.php', self.target/'api-andromeda-search3-preview.php')
        shutil.copy2(runtime/'v2/api-anex-search3-preview.php', self.target/'api-anex-search3-preview.php')
        shutil.copytree(runtime/'v2/data', self.target/'data')
        self.private = home / '.anytoour-andromeda'
        self.searches = self.private / 'searches'
        self.searches.mkdir(parents=True)
        (self.private/'catalog.json').write_text('{}')
        catalog = str(self.private/'catalog.json').replace('\\','\\\\').replace("'","\\'")
        (self.target/'.andromeda-private.php').write_text("<?php return ['enabled'=>true,'catalog_path'=>'" + catalog + "'];\n")
        self.db = self.private/'mapping.sqlite'
        con = sqlite3.connect(self.db)
        con.executescript("""
            CREATE TABLE catalog_hotels (id INTEGER, country_id INTEGER, is_active INTEGER);
            CREATE TABLE andromeda_hotel_identities (supplier_namespace TEXT, external_hotel_id TEXT, local_hotel_id INTEGER, decision_status TEXT);
            INSERT INTO catalog_hotels VALUES (900,4,1),(901,4,1);
            INSERT INTO andromeda_hotel_identities VALUES ('andromeda_catalog','3414',900,'accepted');
        """)
        con.commit(); con.close()
        (self.root/'data').mkdir()
        db_literal = str(self.db).replace('\\','\\\\').replace("'","\\'")
        (self.root/'data/db-v1.php').write_text("<?php function v2_data_db(): PDO { static $p=null; if($p===null)$p=new PDO('sqlite:" + db_literal + "'); return $p; }\n")
        self.ref = 'a'*64
        self.offer_ref = 'offer_' + 'b'*64
        self.raw_supplier = 'PRIVATE-SUPPLIER-CLAIM-ID-DO-NOT-OUTPUT'
        self.write_state()

    def tearDown(self):
        self.temp.cleanup()

    def write_state(self, *, expired=False):
        now = int(time.time()); created = now - (910 if expired else 10)
        store = {'version':1,'search_ref':self.ref,'generation':7,'created_at':created,'expires_at':created+900,
            'snapshot':{'page':1,'pages_count':1,'offers':[{
                'offer_ref':self.offer_ref,'supplier_namespace':'andromeda_catalog','external_hotel_id':'3414',
                'local_hotel_id':900,'operator_ref':'5','hotel':'Fixture Hotel','operator':'ANEX',
                'check_in':'22.09.2026','nights':7,'adults':2,'children':0,'room':'Standard','placement':'DBL',
                'meal':{'key':1,'label':'RO'},'price':{'amount':'83080','currency':'RUB','currency_key':643}}]},
            'criteria':{'PAGE':1,'HOTELS':'3414'},'raw_ids':{self.offer_ref:self.raw_supplier}}
        (self.searches/(self.ref+'-1.json')).write_text(json.dumps({'status':'complete','generation':7,'store':store}))
        os.utime(self.searches/(self.ref+'-1.json'), (now, now))

    def php(self, source):
        return subprocess.run(['php','-d','allow_url_fopen=0',
            '-d','disable_functions=curl_init,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client',
            '-r',source], cwd=self.root, input=b'{}', capture_output=True, timeout=8)

    def run_php(self):
        run = self.php(probe.remote_source())
        self.assertEqual(0, run.returncode, run.stderr.decode(errors='replace'))
        self.assertEqual(b'', run.stderr)
        self.assertNotIn(self.raw_supplier.encode(), run.stdout)
        self.assertNotIn(b'PRIVATE', run.stdout)
        value = probe.validate_result(json.loads(run.stdout))
        if value.get('status') == 'blocked' and value.get('reason') == 'inspection_unconfirmed':
            # Synthetic fixture only: expose exact PHP exception locally. This source is
            # never passed to ssh_php and cannot contain production config/data.
            debug = probe.remote_source().replace(":'inspection_unconfirmed';", ":('fixture_'.get_class($e).'_'.$e->getMessage());")
            diag = self.php(debug)
            self.fail('synthetic remote reason: ' + diag.stdout.decode(errors='replace'))
        return value

    def test_current_public_selection_and_country_are_read_only(self):
        before = (self.searches/(self.ref+'-1.json')).read_bytes()
        value = self.run_php()
        self.assertEqual('ok', value['status'], value)
        self.assertEqual(1, value['active_searches'])
        self.assertEqual(1, len(value['candidates']))
        row = value['candidates'][0]
        self.assertEqual(4, row['capture_request']['local_country_id'])
        self.assertEqual({'provider':'andromeda','search_ref':self.ref,'generation':7,'page':1,
            'offer_ref':self.offer_ref,'hotel_scope':'3414','operator_ref':'5','local_id':900}, row['capture_request']['selection'])
        self.assertEqual('ANEX', row['display']['operator'])
        self.assertEqual('Fixture Hotel', row['display']['hotel'])
        self.assertEqual(before, (self.searches/(self.ref+'-1.json')).read_bytes())
        self.assertFalse((self.private/'monthly-requests.json').exists())

    def test_expired_and_revoked_mapping_never_become_candidates(self):
        self.write_state(expired=True)
        value = self.run_php()
        self.assertEqual('ok', value['status'], value)
        self.assertEqual(0, value['active_searches']); self.assertEqual([], value['candidates'])
        self.write_state(expired=False)
        con = sqlite3.connect(self.db); con.execute("UPDATE andromeda_hotel_identities SET decision_status='pending'"); con.commit(); con.close()
        value = self.run_php(); self.assertEqual('ok', value['status'], value); self.assertEqual(1, value['active_searches']); self.assertEqual([], value['candidates'])

    def test_ambiguous_current_identity_fails_closed(self):
        con = sqlite3.connect(self.db)
        con.execute("INSERT INTO andromeda_hotel_identities VALUES ('andromeda_catalog','3414',900,'accepted')")
        con.commit(); con.close()
        value = self.run_php()
        self.assertEqual('ok', value['status'], value)
        self.assertEqual([], value['candidates'])


if __name__ == '__main__':
    unittest.main(argv=['retained-contexts-test'], verbosity=2)
