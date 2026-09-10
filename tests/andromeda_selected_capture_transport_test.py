"""Selected-capture relay contracts; no SSH/supplier or production data.

The optional installed-runtime case runs the unchanged #1917 assertions with
only its subprocess entry replaced by the exact generated PHP -r wire source.
"""
import argparse
import ast
import base64
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('caller', type=Path)
parser.add_argument('--runtime', type=Path)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('publisher', ROOT / 'scripts/diagnostics/andromeda_detail_publish.py')
publisher = importlib.util.module_from_spec(spec); spec.loader.exec_module(publisher)
REQUEST = {'version': 1, 'operation': 'capture-selected-retained-offer-1717',
    'runtime_source': publisher.SOURCE_SHA, 'local_country_id': 4,
    'selection': {'provider': 'andromeda', 'search_ref': 'a' * 64, 'generation': 1,
        'page': 1, 'offer_ref': 'offer_' + 'b' * 64, 'hotel_scope': None,
        'operator_ref': 'operator_5', 'local_id': 900}}
BASE_REPLY = {'status': 'captured', 'reason': None, 'automatic_retry': False,
    'identity_verified': False, 'quote_verified': False, 'selection_enabled': False,
    'runtime_source': publisher.SOURCE_SHA, 'reused': False, 'package_sha256': 'c' * 64}


class CaptureTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.operation = self.root / 'capture'
        self.calls = 0

    def tearDown(self):
        self.temp.cleanup()

    def execute(self, source, request):
        self.calls += 1
        self.assertEqual(REQUEST, request)
        self.assertIn('anytour_retained_package_run', source)
        reservation = json.loads((self.operation / 'reservation.json').read_text())
        self.assertEqual('reserved', reservation['status'])
        self.assertEqual(1, reservation['max_broninit'])
        self.assertEqual(publisher.CALLER_SHA256, reservation['caller_sha256'])
        self.assertNotIn('search_ref', json.dumps(reservation))
        self.assertEqual(0o600, (self.operation / 'reservation.json').stat().st_mode & 0o777)
        return dict(BASE_REPLY)

    def call(self, request=None, execute=None):
        return publisher.capture_selected(args.caller, json.dumps(request or REQUEST).encode(),
            self.operation, execute or self.execute)

    def test_success_reservation_readback_privacy_and_no_replay(self):
        request = copy.deepcopy(REQUEST)
        request['selection']['tour'] = {'hotel': 'PRIVATE_PERSON', 'price': '987654.32'}
        result = self.call(request)
        self.assertEqual(BASE_REPLY, result['receipt'])
        self.assertEqual(result, json.loads((self.operation / 'result.json').read_text()))
        self.assertEqual(0o600, (self.operation / 'result.json').stat().st_mode & 0o777)
        self.assertEqual(0o700, self.operation.stat().st_mode & 0o777)
        before = {p.name: p.read_bytes() for p in self.operation.iterdir()}
        with self.assertRaises(FileExistsError): self.call()
        self.assertEqual(1, self.calls)
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.operation.iterdir()})
        public = b''.join(before.values()).decode()
        for text in ('PRIVATE_PERSON', '987654.32', REQUEST['selection']['offer_ref']): self.assertNotIn(text, public)

    def test_invalid_input_has_no_ledger_or_ssh(self):
        for bad in ({}, {'version': 1}, dict(REQUEST, sid='PRIVATE'), dict(REQUEST, local_country_id='4')):
            with self.subTest(bad=list(bad)):
                with self.assertRaises(ValueError):
                    publisher.capture_selected(args.caller, json.dumps(bad).encode(), self.operation, self.execute)
                self.assertFalse(self.operation.exists())
        raw = json.dumps(REQUEST).replace('"version": 1', '"version": 1,"version": 1')
        with self.assertRaises(ValueError): publisher.capture_selected(args.caller, raw.encode(), self.operation, self.execute)
        self.assertEqual(0, self.calls)

    def test_altered_caller_refuses_before_ssh(self):
        path = self.root / publisher.CALLER_PATH; path.parent.mkdir(parents=True)
        path.write_bytes((args.caller / publisher.CALLER_PATH).read_bytes() + b'\n')
        with self.assertRaisesRegex(ValueError, 'caller_hash_mismatch'):
            publisher.capture_selected(self.root, json.dumps(REQUEST).encode(), self.operation, self.execute)
        self.assertFalse(self.operation.exists()); self.assertEqual(0, self.calls)

    def test_unknown_ssh_has_only_fixed_receipt_and_never_retries(self):
        def fail(source, request):
            self.execute(source, request)
            raise RuntimeError('PRIVATE_PASSWORD_AND_RAW_CLAIM')
        result = self.call(execute=fail)
        self.assertEqual('remote_outcome_unknown', result['receipt']['reason'])
        self.assertFalse(result['receipt']['automatic_retry'])
        self.assertNotIn('PRIVATE', json.dumps(result))
        with self.assertRaises(FileExistsError): self.call(execute=fail)
        self.assertEqual(1, self.calls)

    def test_unexpected_remote_fields_are_not_written(self):
        def bad(source, request): return dict(self.execute(source, request), raw_claim={'name':'PRIVATE'})
        result = self.call(execute=bad)
        self.assertEqual('unconfirmed', result['receipt']['status'])
        self.assertNotIn('PRIVATE', (self.operation / 'result.json').read_text())

    def test_receipt_identity_verification_cannot_be_enabled(self):
        for field, value in [('quote_verified',True),('identity_verified',True),('selection_enabled',True),
                             ('automatic_retry',True),('runtime_source','0'*40),('package_sha256','PRIVATE'),('reused',1)]:
            with self.subTest(field=field):
                with self.assertRaises(ValueError): publisher.selected_receipt(dict(BASE_REPLY, **{field:value}))
        for status in ('blocked','unconfirmed'):
            r = {k:v for k,v in BASE_REPLY.items() if k not in ('runtime_source','reused','package_sha256')}
            r.update(status=status,reason='ANDROMEDA_PACKAGE_NOT_CAPTURED')
            self.assertEqual(r, publisher.selected_receipt(r))
        self.assertTrue(publisher.selected_receipt(dict(BASE_REPLY,reused=True))['reused'])

    def test_existing_unknown_runner_directory_is_untouched(self):
        self.operation.mkdir(); (self.operation/'reservation.json').write_text('{"status":"unknown"}')
        with self.assertRaises(FileExistsError): self.call()
        self.assertEqual('{"status":"unknown"}', (self.operation/'reservation.json').read_text())
        self.assertEqual(0,self.calls)

    def test_actual_php_wire_refuses_before_loading_configuration(self):
        caller=publisher.checked_caller(args.caller)
        result=subprocess.run(['php','-d','allow_url_fopen=0','-r',publisher.selected_remote_source(caller)],
            cwd=self.root,input=json.dumps(REQUEST).encode(),capture_output=True,timeout=5)
        self.assertEqual(0,result.returncode); self.assertFalse(result.stderr)
        self.assertEqual('project_invalid',json.loads(result.stdout)['reason'])
        self.assertEqual([],list(self.root.iterdir()))

    @unittest.skipUnless(args.runtime, 'full installed-runtime fixture runs in hosted CI')
    def test_real_remote_entry_with_unchanged_selected_cli_assertions(self):
        fixture=(args.caller/'tests/andromeda-retained-cli-smoke.php').read_bytes()
        blob=hashlib.sha1(b'blob '+str(len(fixture)).encode()+b'\0'+fixture).hexdigest()
        self.assertEqual('d13a6e7bf01050d4ad6b3ce5d9f751e6ef32b7c1',blob)
        wire=publisher.selected_remote_source(publisher.checked_caller(args.caller))
        text=fixture.decode()
        entry="__DIR__ . '/../scripts/diagnostics/andromeda-package-probe.php','--capture-retained-package']"
        self.assertEqual(1,text.count(entry))
        replacement="'-r',base64_decode('"+base64.b64encode(wire.encode()).decode()+"')]"
        text=text.replace(entry,replacement)
        script=self.root/'tests/andromeda-retained-cli-smoke.php';script.parent.mkdir()
        script.write_text(text)
        caller_path=self.root/publisher.CALLER_PATH;caller_path.parent.mkdir(parents=True)
        shutil.copyfile(args.caller/publisher.CALLER_PATH,caller_path)
        result=subprocess.run(['php','-d','allow_url_fopen=0',str(script),str(args.runtime.resolve()),'--installed-runtime'],
            capture_output=True,text=True,timeout=20)
        self.assertEqual(0,result.returncode,result.stderr+result.stdout)
        self.assertIn('113 checks passed',result.stdout)
        self.assertIn('network functions disabled',result.stdout)
        self.assertFalse(result.stderr)


unittest.main(argv=['selected-capture-test'], verbosity=2)
