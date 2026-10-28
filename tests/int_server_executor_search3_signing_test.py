#!/usr/bin/env python3
"""Native synthetic custody and stock executor gates; no server/provider access."""
import base64
import ast
from email.message import Message
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SHA = '5c83c4134f8f1c3aeec24be1713736cd058fc6d3'


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


signing = module('signing', ROOT / 'scripts/deploy/int_server_executor_search3_signing.py')


def core():
    value = module('signing_test_core', ROOT / 'scripts/deploy/int_server_executor.py')
    signing.register_parser(value)
    return value


class Admission(unittest.TestCase):
    def test_three_old_remote_programs_remain_byte_identical(self):
        for mode in signing.MODES[:3]:
            c=core();prefix='int-anex-search3-producer-install-' if mode=='install-search3-producer' else 'int-anex-search3-signing-'
            signing.activate(c,c.parse_command(f'/run-int-server-v1 {SHA} {mode} {prefix}case-v1'))
            self.assertEqual('7586c11adb456016f0a975a0b1b88e340b94e538dc75c70e1f4f5c155d2f4b9e',
                             hashlib.sha256(c.REMOTE.encode()).hexdigest())
        c=core();command=c.parse_command(f'/run-int-server-v1 {signing.TV_SOURCE} {signing.TV_MODE} int-anex-tv-session-gateway-install-golden-v1')
        signing.activate(c,command)
        self.assertEqual('7f54b6544c3adab4b4f54cc4c02ac435ea6a81efe9c22b54b0aa4f1985b1abf7',
                         hashlib.sha256(c.REMOTE.encode()).hexdigest())
        c=core();command=c.parse_command(f'/run-int-server-v1 {signing.TV_SOURCE} {signing.TV_ENABLE_MODE} int-anex-tv-session-gateway-enable-golden-v1')
        signing.activate(c,command)
        self.assertEqual('06c91a5c9a94799f3f048ba41c6fda079a811e22730ce8678c2462ab58a6e85e',
                         hashlib.sha256(c.REMOTE.encode()).hexdigest())
        c=core();command=c.parse_command(f'/run-int-server-v1 {signing.TV_SOURCE} {signing.TV_RECOVERY_MODE} {signing.TV_RECOVERY_OPERATION}')
        signing.activate(c,command)
        self.assertEqual('245bcaa130aa14e9f440abbb665f9532ed298a7e448e977462ebd41ea99d42db',
                         hashlib.sha256(c.REMOTE.encode()).hexdigest())

    def test_old_diagnostic_remote_stays_byte_identical(self):
        c=core();command=c.parse_command(f'/run-int-server-v1 {signing.TV_DIAGNOSTIC_SOURCE} {signing.TV_DIAGNOSTIC_MODE} {signing.TV_DIAGNOSTIC_OPERATION}')
        signing.activate(c,command)
        self.assertEqual('c7168a39c0466160c61dc2cae37b3075e39bb45a05e9dd1d178a812824276476',
                         hashlib.sha256(c.REMOTE.encode()).hexdigest())
        c=core();command=c.parse_command(f'/run-int-server-v1 {signing.TV_CACHE_SOURCE} {signing.TV_CACHE_MODE} {signing.TV_CACHE_OPERATION}')
        signing.activate(c,command)
        self.assertEqual('c7ab1eb55f5e2446b02abe9ede7cb859c5527f6c155b9a38c0351e1f068b8e66',hashlib.sha256(c.REMOTE.encode()).hexdigest())
        for mode,operation,source in [(signing.TV_SESSION_FUEL_MODE,signing.TV_SESSION_FUEL_OPERATION,signing.TV_SESSION_FUEL_SOURCE),
                                      (signing.TV_NUMERIC_MODE,signing.TV_NUMERIC_OPERATION,signing.TV_NUMERIC_SOURCE),
                                      (signing.TV_PARTY_MODE,signing.TV_PARTY_OPERATION,signing.TV_PARTY_SOURCE),
                                      (signing.TV_CHECKPOINT_MODE,signing.TV_CHECKPOINT_OPERATION,signing.TV_CHECKPOINT_SOURCE),
                                      (signing.TV_BYTES_MODE,signing.TV_BYTES_OPERATION,signing.TV_BYTES_SOURCE),
                                      (signing.TV_CAP_MODE,signing.TV_CAP_OPERATION,signing.TV_CAP_SOURCE),
                                      (signing.TV_LIMIT_MODE,signing.TV_LIMIT_OPERATION,signing.TV_LIMIT_SOURCE),
                                      (signing.TV_DAY_MODE,signing.TV_DAY_OPERATION,signing.TV_DAY_SOURCE),
                                      (signing.TV_FIELDS_MODE,signing.TV_FIELDS_OPERATION,signing.TV_FIELDS_SOURCE),
                                      (signing.TV_CONTEXT_MODE,signing.TV_CONTEXT_OPERATION,signing.TV_CONTEXT_SOURCE),
                                      (signing.TV_CACHE_MODE,signing.TV_CACHE_OPERATION,signing.TV_CACHE_SOURCE),
                                      (signing.TV_DIAGNOSTIC_MODE,signing.TV_DIAGNOSTIC_OPERATION,signing.TV_DIAGNOSTIC_SOURCE)]:
            with self.assertRaises(ValueError):core().parse_command(f'/run-int-server-v1 {"0"*40} {mode} {operation}')

    def test_all_previous_fixed_profiles_stay_byte_identical(self):
        c=core();signing.activate(c,c.parse_command(f'/run-int-server-v1 {signing.TV_NUMERIC_SOURCE} {signing.TV_NUMERIC_MODE} {signing.TV_NUMERIC_OPERATION}'))
        self.assertEqual('19cd1ee2446545932435ed2be42c5d9a8ba6039860b5b43f3b803f86b403baf1',hashlib.sha256(c.REMOTE.encode()).hexdigest())
        c=core();signing.activate(c,c.parse_command(f'/run-int-server-v1 {signing.TV_PARTY_SOURCE} {signing.TV_PARTY_MODE} {signing.TV_PARTY_OPERATION}'))
        self.assertEqual('d9778b30c381c523939619e4de3c7ee912d6f296f99fb9afd940fe9703ef3ed2',hashlib.sha256(c.REMOTE.encode()).hexdigest())
        c=core();signing.activate(c,c.parse_command(f'/run-int-server-v1 {signing.TV_CHECKPOINT_SOURCE} {signing.TV_CHECKPOINT_MODE} {signing.TV_CHECKPOINT_OPERATION}'))
        self.assertEqual('a590c1f86deaefc02006bc5b89f019a4ac005880c37bcf0dd7f7d1cd52917949',hashlib.sha256(c.REMOTE.encode()).hexdigest())
        expected={'TV_BYTES': '881eb82c4ca77000ff8c9cba0c29ccb4952bbbc86bbef2d4544e93dc59330341', 'TV_CACHE': 'c7ab1eb55f5e2446b02abe9ede7cb859c5527f6c155b9a38c0351e1f068b8e66', 'TV_CAP': 'cb882cde61f6075620e46da322c74c1ab6464120c6100958444f70c4c39d92eb', 'TV_CONTEXT': '82f914f7ad68348e6ea11975a56cad6650368e32f969a9d1fcf2435bdf2d99a3', 'TV_DAY': 'c4b2d7a66ccfde5073c0697498c99362d86168a581cd8be033e6c30c3a01d20a', 'TV_DIAGNOSTIC': 'c7168a39c0466160c61dc2cae37b3075e39bb45a05e9dd1d178a812824276476', 'TV_FIELDS': '42bcc3854f4d96ef9c6437bdbe0c76f416473b99e66503a392f2f96ebffb81df', 'TV_LIMIT': 'acbfcbe309e1b1f18861ae1a9d3e7c261f1df67881f2bc15eefff36af9432ead', 'TV_MISMATCH': '33f56821b857630903354dcf8d7ac816ca6ec1645b2d6529804f2bb4e59814e8'}
        expected['TV_SEATS']='ce3f9718a675648985d8285ed5187b3b383468e1d5576d90a9ff2c049ebc8a77'
        for prefix,want in expected.items():
            c=core();source=getattr(signing,prefix+"_SOURCE");mode=getattr(signing,prefix+"_MODE");op=getattr(signing,prefix+"_OPERATION")
            signing.activate(c,c.parse_command(f"/run-int-server-v1 {source} {mode} {op}"))
            self.assertEqual(want,hashlib.sha256(c.REMOTE.encode()).hexdigest(),prefix)

    def test_exact_modes_and_no_arbitrary_arguments(self):
        c = core()
        for mode in signing.MODES:
            prefix = ('int-anex-apd-application-reason-install-' if mode == signing.ANEX_APD_REASON_MODE else
                      'int-anex-tv-seat-availability-install-' if mode == signing.TV_SEATS_MODE else
                      'int-anex-tv-session-fuel-context-install-' if mode == signing.TV_SESSION_FUEL_MODE else
                      'int-anex-tv-numeric-admission-install-' if mode == signing.TV_NUMERIC_MODE else
                      'int-anex-tv-party-retirement-install-' if mode == signing.TV_PARTY_MODE else
                      'int-anex-tv-scoped-checkpoint-install-' if mode == signing.TV_CHECKPOINT_MODE else
                      'int-anex-tv-context-cap-20mib-install-' if mode == signing.TV_BYTES_MODE else
                      'int-anex-tv-request-cap-640-install-' if mode == signing.TV_CAP_MODE else
                      'int-anex-tv-limit-diagnostics-install-' if mode == signing.TV_LIMIT_MODE else
                      'int-anex-tv-calendar-day-install-' if mode == signing.TV_DAY_MODE else
                      'int-anex-tv-mismatch-fields-install-' if mode == signing.TV_FIELDS_MODE else
                      'int-anex-tv-context-revocation-install-' if mode == signing.TV_CONTEXT_MODE else
                      'int-anex-tv-cache-revocation-install-' if mode == signing.TV_CACHE_MODE else
                      'int-anex-tv-client-diagnostics-install-' if mode == signing.TV_DIAGNOSTIC_MODE else
                      'int-anex-tv-session-gateway-recovery-' if mode == signing.TV_RECOVERY_MODE else
                      'int-anex-tv-session-gateway-enable-' if mode == signing.TV_ENABLE_MODE else
                      'int-anex-tv-session-gateway-install-' if mode == signing.TV_MODE else
                      'int-anex-search3-producer-install-' if mode == 'install-search3-producer' else 'int-anex-search3-signing-')
            operation = signing.TV_RECOVERY_OPERATION if mode == signing.TV_RECOVERY_MODE else prefix + 'case-v1'
            if mode==signing.TV_DIAGNOSTIC_MODE:operation=signing.TV_DIAGNOSTIC_OPERATION
            if mode==signing.TV_CACHE_MODE:operation=signing.TV_CACHE_OPERATION
            if mode==signing.TV_CONTEXT_MODE:operation=signing.TV_CONTEXT_OPERATION
            source=signing.TV_CONTEXT_SOURCE if mode==signing.TV_CONTEXT_MODE else (signing.TV_CACHE_SOURCE if mode==signing.TV_CACHE_MODE else (signing.TV_DIAGNOSTIC_SOURCE if mode==signing.TV_DIAGNOSTIC_MODE else SHA))
            if mode==signing.TV_FIELDS_MODE:source=signing.TV_FIELDS_SOURCE;operation=signing.TV_FIELDS_OPERATION
            if mode==signing.TV_DAY_MODE:source=signing.TV_DAY_SOURCE;operation=signing.TV_DAY_OPERATION
            if mode==signing.TV_LIMIT_MODE:source=signing.TV_LIMIT_SOURCE;operation=signing.TV_LIMIT_OPERATION
            if mode==signing.TV_CAP_MODE:source=signing.TV_CAP_SOURCE;operation=signing.TV_CAP_OPERATION
            if mode==signing.TV_CHECKPOINT_MODE:source=signing.TV_CHECKPOINT_SOURCE;operation=signing.TV_CHECKPOINT_OPERATION
            if mode==signing.TV_BYTES_MODE:source=signing.TV_BYTES_SOURCE;operation=signing.TV_BYTES_OPERATION
            if mode==signing.TV_PARTY_MODE:source=signing.TV_PARTY_SOURCE;operation=signing.TV_PARTY_OPERATION
            if mode==signing.TV_NUMERIC_MODE:source=signing.TV_NUMERIC_SOURCE;operation=signing.TV_NUMERIC_OPERATION
            if mode==signing.ANEX_APD_REASON_MODE:source=signing.ANEX_APD_REASON_SOURCE;operation=signing.ANEX_APD_REASON_OPERATION
            if mode==signing.TV_SEATS_MODE:source=signing.TV_SEATS_SOURCE;operation=signing.TV_SEATS_OPERATION
            if mode==signing.TV_SESSION_FUEL_MODE:source=signing.TV_SESSION_FUEL_SOURCE;operation=signing.TV_SESSION_FUEL_OPERATION
            body = f'/run-int-server-v1 {source} {mode} {operation}'
            parsed = c.parse_command(body)
            self.assertEqual({'source_sha': source, 'mode': mode, 'operation_id': operation}, parsed)
            for invalid in [body + ' /tmp/key', body.replace(source, 'x' * 40),
                            body.replace(prefix, 'int-andromeda-'),
                            body.replace(operation, operation.removesuffix('-v1')), body.replace('/run-int-server-v1', '/other')]:
                with self.subTest(mode=mode, invalid=invalid), self.assertRaises(ValueError):
                    c.parse_command(invalid)
            if mode in (signing.TV_RECOVERY_MODE,signing.TV_DIAGNOSTIC_MODE,signing.TV_CACHE_MODE,signing.TV_CONTEXT_MODE,signing.TV_FIELDS_MODE,signing.TV_DAY_MODE,signing.TV_LIMIT_MODE,signing.TV_CAP_MODE,signing.TV_BYTES_MODE,signing.TV_CHECKPOINT_MODE,signing.TV_PARTY_MODE,signing.TV_NUMERIC_MODE,signing.TV_SESSION_FUEL_MODE,signing.TV_SEATS_MODE,signing.ANEX_APD_REASON_MODE):
                for changed in (operation.replace('v1','v2'),prefix+'alias-v1'):
                    with self.assertRaises(ValueError):c.parse_command(body.replace(operation,changed))
        self.assertEqual('install-runtime', c.parse_command(f'/run-int-server-v1 {SHA} install-runtime int-anex-existing-install-v1')['mode'])

    def test_seats_fixed_source_and_latest_predecessor_profile(self):
        c=core();body=f'/run-int-server-v1 {signing.TV_SEATS_SOURCE} {signing.TV_SEATS_MODE} {signing.TV_SEATS_OPERATION}'
        command=c.parse_command(body);signing.activate(c,command);ast.parse(c.REMOTE)
        self.assertEqual(12,len(signing.tv_seats_previous()|signing.TV_DEPENDENCIES))
        for bad in (body.replace(signing.TV_SEATS_SOURCE,'0'*40),body.replace('v1','v2'),body+' extra'):
            with self.assertRaises(ValueError):core().parse_command(bad)
        self.assertIn("if changed!=['app/integrations/tourvisor-preview-gateway.php']",c.REMOTE)
        self.assertEqual(signing.TV_SESSION_FUEL_UPDATES['v2/api-anex-search3-preview.php'],
            signing.tv_seats_previous()['v2/api-anex-search3-preview.php'])

    def test_apd_reason_fixed_source_and_latest_predecessor_profile(self):
        c=core();body=f'/run-int-server-v1 {signing.ANEX_APD_REASON_SOURCE} {signing.ANEX_APD_REASON_MODE} {signing.ANEX_APD_REASON_OPERATION}'
        command=c.parse_command(body);signing.activate(c,command);ast.parse(c.REMOTE)
        self.assertEqual(12,len(signing.anex_apd_reason_previous()|signing.TV_DEPENDENCIES))
        for bad in (body.replace(signing.ANEX_APD_REASON_SOURCE,'0'*40),body.replace('v1','v2'),body+' extra'):
            with self.assertRaises(ValueError):core().parse_command(bad)
        self.assertIn("if changed!=['api-anex-search3-preview.php']",c.REMOTE)
        self.assertEqual(signing.TV_SESSION_FUEL_UPDATES['v2/api-anex-search3-preview.php'],
            signing.anex_apd_reason_previous()['v2/api-anex-search3-preview.php'])

    def test_registration_is_isolated_and_stock_guards_remain(self):
        c = core(); before = c.REMOTE
        signing.activate(c, {'mode': 'install-runtime'})
        self.assertEqual(before, c.REMOTE)
        command = c.parse_command(f'/run-int-server-v1 {SHA} search3-signing-readback int-anex-search3-signing-read-v1')
        signing.activate(c, command)
        compile(c.REMOTE, '<registered stock remote>', 'exec')
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')", c.REMOTE)
        self.assertIn("result['status']='reserved'", c.REMOTE)
        self.assertEqual(2, c.REMOTE.count("    if mode not in ('install-search3-producer','search3-signing-provision','search3-signing-readback','reconcile',"))
        self.assertEqual(1, c.REMOTE.count('def search3_signing_custody():'))
        self.assertLess(len(base64.b64encode(__import__('zlib').compress(c.REMOTE.encode(), 9))), 64000)
        with self.assertRaises(ValueError):
            signing.activate(core(), command | {'key_file': '/tmp/caller'})


class NativeStockCustody(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='search3-custody-native-')
        self.home = Path(self.temporary.name)
        self.project = self.home / 'www/anytoour.ru'
        self.runtime = self.project / '_preview/search3-anex-candidate'
        self.runtime.mkdir(parents=True)
        self.config = self.runtime / '.anex-private.php'
        self.original = "<?php\ndefine('ANEX_API_TOKEN','SYNTHETIC_UNRELATED_CREDENTIAL');\n"
        self.config.write_text(self.original); self.config.chmod(0o640)
        self.keyfile = self.home / '.anytoour-int-executor/search3-signing/anytour-v1.json'
        self.inventory = {f'app/integrations/fixture-{i}.php': b'<?php\n' for i in range(20)}
        self.inventory.update({p: b'<?php\n' for p in (
            'app/integrations/search3-messenger-handoff.php', 'app/integrations/anex-package-quote-handoff.php',
            'v2/api-anex-search3-preview.php')})
        self.archive = self.home / 'source.tar.gz'
        self.rebuild_archive()
        self.c = core(); self.counter = 0

    def rebuild_archive(self):
        hashes = {p: hashlib.sha256(raw).hexdigest() for p, raw in self.inventory.items()}
        self.manifest_hash = hashlib.sha256(json.dumps(hashes, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
        with tarfile.open(self.archive, 'w:gz') as tar:
            files = self.inventory | {'manifest.json': json.dumps({'schema_version': 1, 'files': hashes}).encode()}
            for name, raw in files.items():
                info = tarfile.TarInfo(name); info.size = len(raw); info.mode = 0o600
                tar.addfile(info, io.BytesIO(raw))

    def tearDown(self):
        self.temporary.cleanup()

    def execute(self, mode='search3-signing-provision', operation=None, source_sha=SHA):
        self.counter += 1
        operation = operation or f'int-anex-search3-signing-native-{self.counter}-v1'
        c = core()
        command = c.parse_command(f'/run-int-server-v1 {source_sha} {mode} {operation}')
        signing.activate(c, command)
        self.mutate_remote(c)
        script = self.home / 'stock-remote.py'; script.write_text(c.REMOTE)
        env = dict(os.environ, HOME=str(self.home))
        php_bin = os.environ.get('PHP_BIN')
        if php_bin:
            env['PATH'] = str(Path(php_bin).parent) + os.pathsep + env.get('PATH', '')
        payload = command | {'archive': str(self.archive), 'manifest_sha256': self.manifest_hash}
        run = subprocess.run([sys.executable, str(script)], input=json.dumps(payload), env=env,
                             text=True, capture_output=True, timeout=30)
        self.assertEqual(0, run.returncode, run.stderr)
        self.assertEqual('', run.stderr)
        result = json.loads(run.stdout)
        self.assertNotIn('SYNTHETIC_UNRELATED_CREDENTIAL', run.stdout)
        self.assertNotIn('secret_key_base64', run.stdout)
        self.assertEqual(operation, result['operation_id'])
        return result, run.stdout

    def mutate_remote(self, c):
        pass

    def test_conditional_or_noncanonical_pointer_rejected_before_writes(self):
        pointer = repr(str(self.keyfile))
        direct = f"define('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE', {pointer});"
        guard = f"if (!defined('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE')) {direct}"
        for statement in [f'if (false) {direct}', f'if (false) {{ {direct} }}',
                          f'function unused() {{ {direct} }}', f'if (false) {guard}', direct]:
            with self.subTest(statement=statement):
                self.config.write_text(self.original + statement + '\n')
                before = self.config.read_bytes()
                result, _ = self.execute()
                self.assertEqual('custody_pointer_conflict', result['reason'])
                self.assertFalse(self.keyfile.exists())
                self.assertEqual(before, self.config.read_bytes())

    def test_native_provision_readback_and_no_rotation(self):
        provision, stdout = self.execute()
        self.assertEqual('complete', provision['status'])
        self.assertEqual(0, provision['supplier_calls']); self.assertEqual(0, provision['database_writes'])
        self.assertFalse(provision['private_key_exported'])
        public = provision['signing']; self.assertTrue(public['created']); self.assertTrue(public['pointer_configured'])
        self.assertEqual(32, len(base64.b64decode(public['public_key'], validate=True)))
        self.assertEqual(90 * 86400 + 60, public['not_after'] - public['not_before'])
        self.assertEqual(0o600, self.keyfile.stat().st_mode & 0o777)
        self.assertEqual(0o700, self.keyfile.parent.stat().st_mode & 0o777)
        key_bytes = self.keyfile.read_bytes(); key = json.loads(key_bytes)
        self.assertNotIn(key['secret_key_base64'], stdout)
        self.assertEqual(64, len(base64.b64decode(key['secret_key_base64'], validate=True)))
        configured = self.config.read_bytes()
        self.assertTrue(configured.decode().startswith(self.original))
        self.assertEqual(0o640, self.config.stat().st_mode & 0o777)
        self.assertIn('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE', configured.decode())
        for mode in ['search3-signing-readback', 'search3-signing-provision']:
            again, output = self.execute(mode)
            self.assertEqual('complete', again['status'])
            self.assertFalse(again['signing']['created']); self.assertFalse(again['signing']['pointer_configured'])
            self.assertEqual(public['public_key'], again['signing']['public_key'])
            self.assertTrue(self.keyfile.read_bytes() == key_bytes)
            self.assertTrue(self.config.read_bytes() == configured)
            self.assertNotIn(key['secret_key_base64'], output)
        duplicate, _ = self.execute(operation='int-anex-search3-signing-native-1-v1')
        self.assertEqual('blocked', duplicate['status'])
        self.assertEqual('operation_exists_no_replay', duplicate['reason'])
        self.assertTrue(self.keyfile.read_bytes() == key_bytes)

    def test_readback_does_not_provision(self):
        result, _ = self.execute('search3-signing-readback')
        self.assertNotEqual('complete', result['status'])
        self.assertFalse(self.keyfile.exists())
        self.assertEqual(self.original, self.config.read_text())

    def test_conflicting_pointer_does_not_generate_key(self):
        self.config.write_text(self.original + "define('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE','/wrong/path');\n")
        before = self.config.read_bytes()
        result, _ = self.execute()
        self.assertEqual('custody_pointer_conflict', result['reason'])
        self.assertFalse(self.keyfile.exists())
        self.assertTrue(self.config.read_bytes() == before)

    def test_invalid_or_unreachable_config_is_not_executed(self):
        for suffix, error in [('return;\n', 'custody_config_shape'), ('define(];\n', 'custody_config_lint')]:
            with self.subTest(suffix=suffix):
                self.config.write_text(self.original + suffix)
                before = self.config.read_bytes()
                result, _ = self.execute()
                self.assertEqual(error, result['reason'])
                self.assertFalse(self.keyfile.exists())
                self.assertTrue(self.config.read_bytes() == before)

    def test_closed_php_config_is_extended_as_php(self):
        self.config.write_text(self.original + '?>\n')
        result, _ = self.execute()
        self.assertEqual('complete', result['status'])
        self.assertIn('?>\n\n<?php\nif (!defined(', self.config.read_text())

    def test_symlink_and_world_readable_custody_rejected(self):
        private = self.keyfile.parent.parent; private.mkdir(mode=0o700)
        other = self.home / 'outside'; other.mkdir(mode=0o700)
        self.keyfile.parent.symlink_to(other, target_is_directory=True)
        result, _ = self.execute()
        self.assertEqual('search3_signing_directory', result['reason'])
        self.assertEqual([], list(other.iterdir()))
        self.keyfile.parent.unlink(); self.keyfile.parent.mkdir(mode=0o755)
        result, _ = self.execute()
        self.assertEqual('search3_signing_directory', result['reason'])

    def test_corrupt_or_expired_key_is_never_replaced(self):
        result, _ = self.execute(); self.assertEqual('complete', result['status'])
        original = self.keyfile.read_bytes()
        key = json.loads(original); key['not_after'] = key['not_before'] - 1
        for bad in [b'not json', json.dumps(key).encode()]:
            self.keyfile.write_bytes(bad)
            result, _ = self.execute()
            self.assertNotEqual('complete', result['status'])
            self.assertTrue(self.keyfile.read_bytes() == bad)
        self.keyfile.write_bytes(original); self.keyfile.chmod(0o644)
        result, _ = self.execute()
        self.assertEqual('custody_key_mode', result['reason'])
        self.assertTrue(self.keyfile.read_bytes() == original)


class NativeStockProducerInstall(NativeStockCustody):
    # Inherit fixture helpers, not custody cases (loaded explicitly below).
    def setUp(self):
        super().setUp()
        self.inventory.update({p: ('<?php\n// reviewed synthetic ' + p + '\n').encode()
                               for p in signing.PRODUCER_PINS})
        self.rebuild_archive()
        self.fixture_pins = {p: hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.PRODUCER_PINS}
        integrations = self.runtime / 'app/integrations'; integrations.mkdir(parents=True)
        self.initial = integrations / 'anex-initial-week-gate.php'
        self.initial.write_bytes(self.inventory['app/integrations/anex-initial-week-gate.php'])
        self.old_quote = b'<?php\n// old quote\n'; self.old_api = b'<?php\n// old api\n'
        (integrations / 'anex-package-quote-runtime.php').write_bytes(self.old_quote)
        (self.runtime / 'api-anex-search3-preview.php').write_bytes(self.old_api)
        self.sentinel = integrations / 'unrelated.php'; self.sentinel.write_bytes(b'<?php // unchanged\n')
        self.inject_failure = False

    def mutate_remote(self, c):
        # Synthetic fixture adaptation only; production pins have no caller override.
        old = 'SEARCH3_PRODUCER_PINS=' + repr(signing.PRODUCER_PINS)
        self.assertEqual(1, c.REMOTE.count(old))
        c.REMOTE = c.REMOTE.replace(old, 'SEARCH3_PRODUCER_PINS=' + repr(self.fixture_pins), 1)
        if self.inject_failure:
            anchor = "        result['install']=install_anex_preview(stage,files,op)\n        result['production_after']=fingerprints()"
            self.assertEqual(1, c.REMOTE.count(anchor))
            c.REMOTE = c.REMOTE.replace(anchor, anchor.split('\n')[0] + "\n        fail('synthetic_postinstall_failure')\n        result['production_after']=fingerprints()", 1)

    def install(self):
        return self.execute('install-search3-producer', f'int-anex-search3-producer-install-native-{self.counter+1}-v1')[0]

    def test_install_narrow_dependency_first_and_endpoint_last(self):
        result = self.install()
        self.assertEqual('installed', result['status'])
        self.assertEqual(4, result['install']['changed_files']); self.assertEqual(2, result['install']['created_files'])
        self.assertFalse(result['install']['dependency']['changed'])
        for source, raw in self.inventory.items():
            if source in signing.PRODUCER_PINS:
                target = source if source.startswith('app/') else 'api-anex-search3-preview.php'
                self.assertEqual(raw, (self.runtime / target).read_bytes())
        operation = self.home / '.anytoour-int-executor/operations' / result['operation_id']
        # Locate the stock private operation plan without assuming a second store.
        plans = list(self.home.rglob('anex-preview-install-plan.json'))
        self.assertEqual(1, len(plans))
        plan = json.loads(plans[0].read_text())
        self.assertEqual('api-anex-search3-preview.php', plan['changed_files'][-1])
        self.assertEqual(5, len(plan['files']))
        self.assertEqual(b'<?php // unchanged\n', self.sentinel.read_bytes())
        self.assertEqual(self.original, self.config.read_text()); self.assertFalse(self.keyfile.exists())
        self.assertEqual(0, result['supplier_calls']); self.assertEqual(0, result['database_writes'])

    def test_install_source_pin_and_existing_dependency_drift(self):
        for kind in ('source', 'target'):
            with self.subTest(kind=kind):
                pins = dict(self.fixture_pins)
                if kind == 'source': self.fixture_pins['app/integrations/search3-messenger-handoff.php'] = '0' * 64
                else: self.initial.write_bytes(b'<?php // drift\n')
                result = self.install()
                self.assertEqual('search3_producer_source_pin' if kind == 'source' else 'search3_producer_dependency_drift', result['reason'])
                self.assertEqual(self.old_quote, (self.runtime / 'app/integrations/anex-package-quote-runtime.php').read_bytes())
                self.assertEqual(self.old_api, (self.runtime / 'api-anex-search3-preview.php').read_bytes())
                self.assertFalse((self.runtime / 'app/integrations/search3-messenger-handoff.php').exists())
                self.fixture_pins = pins

    def test_install_postwrite_failure_restores_and_removes(self):
        self.inject_failure = True
        result = self.install()
        self.assertEqual('rolled_back', result['status']); self.assertEqual('complete', result['rollback']['status'])
        self.assertFalse(result['runtime_changed'])
        self.assertEqual(self.old_quote, (self.runtime / 'app/integrations/anex-package-quote-runtime.php').read_bytes())
        self.assertEqual(self.old_api, (self.runtime / 'api-anex-search3-preview.php').read_bytes())
        for name in ['anex-package-quote-handoff.php', 'search3-messenger-handoff.php']:
            self.assertFalse((self.runtime / 'app/integrations' / name).exists())
        self.assertEqual(b'<?php // unchanged\n', self.sentinel.read_bytes())
        self.assertEqual(self.original, self.config.read_text()); self.assertFalse(self.keyfile.exists())


class NativeTVGatewayInstall(NativeStockCustody):
    def setUp(self):
        super().setUp()
        fixture=os.environ.get('TV_SESSION_GATEWAY_SOURCE_ROOT')
        for p,sha in {**signing.TV_PINS,**signing.TV_DEPENDENCIES}.items():
            raw=(Path(fixture)/p).read_bytes() if fixture else ('<?php\n// reviewed '+p+'\n').encode()
            if fixture:self.assertEqual(sha,hashlib.sha256(raw).hexdigest())
            self.inventory[p]=raw
        self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        self.deps={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_DEPENDENCIES}
        (self.runtime/'app/integrations').mkdir(parents=True)
        self.previous={}
        for p,old in signing.TV_PREVIOUS.items():
            if old is None:self.previous[p]=None
            else:
                raw=('<?php\n// prior '+p+'\n').encode();(self.runtime/p).write_bytes(raw)
                self.previous[p]=hashlib.sha256(raw).hexdigest()
        for p in signing.TV_DEPENDENCIES:(self.runtime/p).write_bytes(self.inventory[p])
        self.public={}
        for p,record in signing.TV_PUBLIC.items():
            if record['state']=='absent':self.public[p]={'state':'absent'}
            else:
                target=self.project/p;target.parent.mkdir(parents=True,exist_ok=True)
                target.write_bytes(('<?php\n// public '+p+'\n').encode())
                self.public[p]={'state':'regular','sha256':hashlib.sha256(target.read_bytes()).hexdigest()}
        self.old_api=(self.runtime/'api-anex-search3-preview.php').read_bytes()
        self.old_producer=(self.runtime/'app/integrations/search3-messenger-handoff.php').read_bytes()
        self.race=False;self.post_failure=False;self.rebuild_archive()

    def mutate_remote(self,c):
        for name,old,new in [('TV_PINS',signing.TV_PINS,self.pins),
                             ('TV_PREVIOUS',signing.TV_PREVIOUS,self.previous),
                             ('TV_DEPENDENCIES',signing.TV_DEPENDENCIES,self.deps),
                             ('TV_PUBLIC',signing.TV_PUBLIC,self.public)]:
            anchor=name+'='+repr(old);self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,name+'='+repr(new),1)
        if self.race:
            anchor="    if mode==TV_GATEWAY_MODE: tv_gateway_preflight()\n    install_started=True\n"
            self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,
                "    (runtime/'app/integrations/search3-messenger-handoff.php').write_bytes(b'<?php // raced\\n')\n"+anchor,1)
        if self.post_failure:
            anchor="        result['install']=install_anex_preview(stage,files,op)\n        result['tv_gateway_public_after']"
            self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,anchor.split('\n')[0]+"\n        fail('synthetic_tv_failure')\n        result['tv_gateway_public_after']",1)

    def install(self,operation=None):
        return self.execute(signing.TV_MODE,operation or f'int-anex-tv-session-gateway-install-native-{self.counter+1}-v1',
                            source_sha=signing.TV_SOURCE)[0]

    def assert_old(self):
        self.assertEqual(self.old_api,(self.runtime/'api-anex-search3-preview.php').read_bytes())
        self.assertEqual(self.old_producer,(self.runtime/'app/integrations/search3-messenger-handoff.php').read_bytes())
        self.assertEqual(self.original,self.config.read_text());self.assertFalse(self.keyfile.exists())

    def test_exact_install_endpoint_last_no_activation_and_completion(self):
        r=self.install();self.assertEqual('installed',r['status'])
        self.assertEqual((6,5,3),(r['install']['files'],r['install']['changed_files'],r['install']['created_files']))
        plan=json.loads(next(self.home.rglob('anex-preview-install-plan.json')).read_text())
        self.assertEqual('api-anex-search3-preview.php',plan['changed_files'][-1])
        changed=plan['changed_files']
        self.assertLess(changed.index('app/integrations/tourvisor-selected-tour-handoff.php'),
                        changed.index('app/integrations/search3-messenger-handoff.php'))
        self.assertLess(changed.index('app/integrations/tourvisor-interactive-client.php'),
                        changed.index('app/integrations/tourvisor-preview-gateway.php'))
        for p in signing.TV_PINS:
            target=p if p.startswith('app/') else 'api-anex-search3-preview.php'
            self.assertEqual(self.inventory[p],(self.runtime/target).read_bytes())
        self.assertEqual(self.original,self.config.read_text());self.assertFalse(self.keyfile.exists())
        self.assertEqual(self.public,r['tv_gateway_public_before']);self.assertEqual(self.public,r['tv_gateway_public_after'])
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.multiple(signing,TV_PINS=self.pins,TV_PUBLIC=self.public,TV_DEPENDENCIES=self.deps):
            self.assertTrue(signing.completed_tv(command,r))
            for k,v in [('production_before',{}),('tv_gateway_public_after',None),('supplier_calls',True),('schema_version',True)]:
                self.assertFalse(signing.completed_tv(command,r|{k:v}))
        self.assertEqual('operation_exists_no_replay',self.install(r['operation_id'])['reason'])

    def test_source_dependency_and_expected_absent_guards(self):
        for kind in ('source','dependency','staged_dependency','exists'):
            with self.subTest(kind=kind):
                if kind=='source':self.pins['app/integrations/tourvisor-preview-gateway.php']='0'*64
                elif kind=='staged_dependency':
                    p='app/integrations/anex-initial-week-gate.php'
                    original=self.inventory[p];self.inventory[p]=b'<?php // substituted staged dependency\n'
                    self.rebuild_archive()
                else:
                    p='app/integrations/'+('anex-initial-week-gate.php' if kind=='dependency' else 'tourvisor-preview-gateway.php')
                    (self.runtime/p).write_bytes(b'<?php // foreign\n')
                r=self.install();self.assertEqual({'source':'tv_gateway_source_pin','dependency':'tv_gateway_predecessor',
                                                  'staged_dependency':'tv_gateway_source_pin',
                                                  'exists':'tv_gateway_expected_absent'}[kind],r['reason']);self.assert_old()
                self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
                if kind=='dependency':(self.runtime/p).write_bytes(self.inventory[p])
                if kind=='staged_dependency':self.inventory[p]=original;self.rebuild_archive()
                if kind=='exists':(self.runtime/p).unlink()

    def test_symlink_public_is_not_absence(self):
        target=self.project/'v2/api-v2.php';target.parent.mkdir(parents=True)
        target.symlink_to(self.project/'api-v2.php')
        self.assertEqual('tv_gateway_public_type',self.install()['reason']);self.assert_old()

    def test_prewrite_rechecks_predecessor(self):
        self.race=True;r=self.install();self.assertEqual('tv_gateway_predecessor',r['reason'])
        self.assertEqual(self.old_api,(self.runtime/'api-anex-search3-preview.php').read_bytes())
        self.assertEqual(b'<?php // raced\n',(self.runtime/'app/integrations/search3-messenger-handoff.php').read_bytes())
        self.assertFalse((self.runtime/'app/integrations/tourvisor-preview-gateway.php').exists())

    def test_postwrite_failure_rolls_back_and_removes_created(self):
        self.post_failure=True;r=self.install();self.assertEqual('rolled_back',r['status']);self.assert_old()
        self.assertEqual('complete',r['rollback']['status'])
        for p,old in self.previous.items():
            if old is None:self.assertFalse((self.runtime/p).exists())


class NativeTVGatewayEnable(NativeTVGatewayInstall):
    def setUp(self):
        super().setUp()
        for p in signing.TV_PINS:
            (self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php')).write_bytes(self.inventory[p])
        pointer=f"if (!defined('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE')) define('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE', {str(self.keyfile)!r});\n"
        self.original="<?php\ndeclare(strict_types=1);\ndefine('ANEX_API_TOKEN','SYNTHETIC_UNRELATED_CREDENTIAL');\n"+pointer
        self.config.write_text(self.original)
        self.keyfile.parent.mkdir(parents=True,exist_ok=True)
        self.keyfile.write_text('SYNTHETIC_KEY_MUST_NOT_BE_OPENED');self.keyfile.chmod(0o000)
        self.parent=self.home/'.anytoour-int-executor'/signing.TV_PARENT_OPERATION/'result.json'
        self.parent.parent.mkdir();self.parent.write_text('{"status":"installed","fixture":true}')
        self.parent.chmod(0o600)
        self.parent_digest=hashlib.sha256(json.dumps(json.loads(self.parent.read_bytes()),sort_keys=True,separators=(',',':')).encode()).hexdigest()
        self.http_failure=False;self.config_race=False;self.parser_failure=None

    def mutate_remote(self,c):
        for name,old,new in [('TV_PINS',signing.TV_PINS,self.pins),('TV_DEPENDENCIES',signing.TV_DEPENDENCIES,self.deps),
                             ('TV_PUBLIC',signing.TV_PUBLIC,self.public),('TV_PARENT_CANONICAL',signing.TV_PARENT_CANONICAL,self.parent_digest)]:
            anchor=name+'='+repr(old);self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,name+'='+repr(new),1)
        # Only the fixed own-site HTTP call is replaced, never PHP/tokenization or filesystem operations.
        start=c.REMOTE.index('def tv_enable_http_ready():')
        end=c.REMOTE.index('\ndef tv_enable_config(op):',start)
        proof={'status':200,'provider':'tourvisor','csrf_exported':False,'private_cookie_scope':True,'supplier_calls':0,'requests':1}
        body="fail('synthetic_http_failure')" if self.http_failure else 'return '+repr(proof)
        c.REMOTE=c.REMOTE[:start]+'def tv_enable_http_ready():\n    '+body+'\n'+c.REMOTE[end:]
        c.REMOTE=c.REMOTE.replace('    time.sleep(3)\n','',1)
        if self.parser_failure:
            anchor="    try:check=subprocess.run(['php','-r',TV_FLAG_PHP,str(config)],capture_output=True,timeout=20)"
            self.assertEqual(1,c.REMOTE.count(anchor))
            if self.parser_failure=='timeout':value="(_ for _ in ()).throw(subprocess.TimeoutExpired('native-parser',20))"
            elif self.parser_failure=='unavailable':value="(_ for _ in ()).throw(OSError('SYNTHETIC_UNRELATED_CREDENTIAL'))"
            else:
                values={'json':b'not JSON SYNTHETIC_UNRELATED_CREDENTIAL','list':b'[]',
                        'foreign':b'{"ok":false,"code":"SYNTHETIC_UNRELATED_CREDENTIAL"}',
                        'missing':b'{"ok":false}', 'oversize':b'x'*1025}
                value="__import__('types').SimpleNamespace(returncode=1,stdout="+repr(values[self.parser_failure])+")"
            c.REMOTE=c.REMOTE.replace(anchor,'    try:check='+value,1)
        if self.config_race:
            anchor="    if tv_enable_config_identity(config)!=identity or config.read_bytes()!=old:fail('tv_enable_config_drift')"
            self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,"    config.write_bytes(old+b'// concurrent writer\\n')\n"+anchor,1)

    def enable(self,operation=None,source_sha=None):
        return self.execute(signing.TV_ENABLE_MODE,operation or f'int-anex-tv-session-gateway-enable-native-{self.counter+1}-v1',
                            source_sha=source_sha or signing.TV_SOURCE)[0]

    def assert_config_unchanged(self):
        self.assertEqual(self.original,self.config.read_text());self.assertEqual(0o640,self.config.stat().st_mode&0o777)
        self.assertEqual(0,self.keyfile.stat().st_mode&0o777)

    def test_enable_preserves_bytes_tail_key_and_strict_completion(self):
        for strict in (True,False):
            with self.subTest(strict=strict):
                if not strict:self.original=self.original.replace('declare(strict_types=1);','');self.config.write_text(self.original)
                r=self.enable();self.assertEqual('enabled',r['status'],r)
                expected=self.original.encode();offset=expected.index(b';')+1 if strict else len(b'<?php\n')
                self.assertEqual(expected[:offset]+signing.TV_FLAG_LINE+expected[offset:],self.config.read_bytes())
                self.assertEqual(0,self.keyfile.stat().st_mode&0o777)
                command={k:r[k] for k in ('source_sha','mode','operation_id')}
                with mock.patch.multiple(signing,TV_PUBLIC=self.public,TV_PARENT_CANONICAL=self.parent_digest):
                    self.assertTrue(signing.completed_enable(command,r))
                    for k,v in [('supplier_calls',True),('schema_version',True),('production_after',{}),('tv_gateway_public_after',{})]:
                        self.assertFalse(signing.completed_enable(command,r|{k:v}))
                    for k,v in [('changed_files',True),('after_bytes',0),('after_mode',-512),('signing_custody_changed',True),('http',{})]:
                        self.assertFalse(signing.completed_enable(command,r|{'tv_enable':r['tv_enable']|{k:v}}))
                    self.assertFalse(signing.completed_enable(command,r|{'tv_enable':r['tv_enable']|{
                        'before_bytes':-1000,'after_bytes':-1000+len(signing.TV_FLAG_LINE)}}))
                self.assertEqual('operation_exists_no_replay',self.enable(r['operation_id'])['reason'])
                self.config.write_text(self.original)

    def test_bad_source_parent_and_runtime_refuse_before_config(self):
        r=self.enable(source_sha=SHA);self.assertEqual('tv_enable_source',r['reason']);self.assert_config_unchanged()
        self.parent.write_text('{}');self.assertEqual('tv_enable_parent_receipt',self.enable()['reason']);self.assert_config_unchanged()
        self.parent.write_text('{"status":"installed","fixture":true}')
        p='app/integrations/tourvisor-preview-gateway.php';target=self.runtime/p;old=target.read_bytes()
        target.write_bytes(b'<?php // drift\n');self.assertEqual('tv_enable_runtime_pin',self.enable()['reason']);self.assert_config_unchanged()
        target.write_bytes(old);self.pins[p]='0'*64
        self.assertEqual('tv_enable_source_pin',self.enable()['reason']);self.assert_config_unchanged()

    def test_unknown_config_or_existing_flag_denied_without_execution(self):
        for extra,reason in [("define('ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED',false);",'tv_enable_flag_already_defined'),
                      ('return;','tv_enable_statement_shape'),("include '/no/config/includes';",'tv_enable_statement_shape'),
                      ("define('DYNAMIC',getenv('SECRET'));",'tv_enable_value_shape'),
                      ("file_put_contents(__DIR__.'/unsafe-executed','x');",'tv_enable_statement_shape'),
                      ('define(];','tv_enable_php_syntax')]:
            with self.subTest(extra=extra):
                self.config.write_text(self.original+extra)
                r=self.enable();self.assertEqual(reason,r['reason'])
                self.assertEqual(self.original+extra,self.config.read_text())
                self.assertFalse((self.runtime/'unsafe-executed').exists())

    def test_parser_environment_and_untrusted_output_only_fixed_codes(self):
        for case in ('timeout','unavailable','json','list','foreign','missing','oversize'):
            with self.subTest(case=case):
                self.parser_failure=case;r=self.enable()
                expected={'timeout':'tv_enable_parser_timeout','unavailable':'tv_enable_parser_unavailable'}.get(case,'tv_enable_parser_output')
                self.assertEqual(expected,r['reason']);self.assert_config_unchanged()
                self.assertNotIn('SYNTHETIC_UNRELATED_CREDENTIAL',json.dumps(r))
                self.assertEqual([],list(self.home.rglob('tv-enable-plan.json')))

    def test_native_missing_tokenizer_has_own_safe_classification(self):
        php_bin=os.environ.get('PHP_BIN','php')
        run=subprocess.run([php_bin,'-d','disable_functions=token_get_all','-r',signing.TV_FLAG_PHP,str(self.config)],
                           capture_output=True,timeout=20)
        self.assertEqual(1,run.returncode);self.assertEqual(b'',run.stderr)
        self.assertEqual({'ok':False,'code':'tv_flag_tokenizer_unavailable'},json.loads(run.stdout))
        self.assert_config_unchanged()

    def publisher_stub(self):
        fixture=os.environ.get('TV_SESSION_GATEWAY_SOURCE_ROOT')
        if not fixture:
            # Explicit synthetic counterpart for stock self-check without the CI checkout.
            return "<?php\nrequire_once dirname(__DIR__, 4) . '/.anytoour-anex/search3-preview.php';\n"
        path=Path(fixture)/'scripts/diagnostics/anex_search3_preview_deploy.py';raw=path.read_bytes()
        self.assertEqual('7504e7250f3cfacd070c30e2dda461f7f4f4d28e',
                         hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest())
        # Read the exact literal emitted by the real publisher; never run its deploy/build code.
        function=next(n for n in ast.parse(raw).body if isinstance(n,ast.FunctionDef) and n.name=='build_payload')
        calls=[n for n in ast.walk(function) if isinstance(n,ast.Call) and isinstance(n.func,ast.Attribute)
               and n.func.attr=='write_text' and isinstance(n.func.value,ast.BinOp)
               and isinstance(n.func.value.right,ast.Constant) and n.func.value.right.value=='.anex-private.php']
        self.assertEqual(1,len(calls));self.assertIsInstance(calls[0].args[0],ast.Constant)
        return calls[0].args[0].value

    def test_actual_publisher_stub_old_reject_fixed_preserves_bytes_without_following_include(self):
        stub=self.publisher_stub();self.assertTrue(stub.startswith('<?php\n'))
        pointer=self.original.splitlines(keepends=True)[-1]
        outside=self.home/'.anytoour-anex/search3-preview.php';outside.parent.mkdir()
        private=b"<?php file_put_contents(__DIR__.'/unwanted-execution','SYNTHETIC_UNRELATED_CREDENTIAL');\n"
        outside.write_bytes(private);outside.chmod(0o000)
        start=signing.TV_FLAG_PHP.index("        if($t['id']===T_REQUIRE_ONCE){")
        end=signing.TV_FLAG_PHP.index("        if($t['id']===T_IF){",start)
        old=(signing.TV_FLAG_PHP[:start]+signing.TV_FLAG_PHP[end:]).replace('$pointer=false;$declared=false;$bootstrap=false;',
                 '$pointer=false;').replace("$insert=$tokens[$i-1]['end'];$declared=true;","$insert=$tokens[$i-1]['end'];")
        self.assertEqual('087973187fd1f3ef4d3a149743eb6875e8f8d0900efd8b562d42f46ef102a5c5',hashlib.sha256(old.encode()).hexdigest())
        for strict in (False,True):
            with self.subTest(strict=strict):
                self.original=stub.replace('<?php\n','<?php\ndeclare(strict_types=1);\n',1) if strict else stub
                self.original+=pointer;self.config.write_text(self.original)
                native=subprocess.run([os.environ.get('PHP_BIN','php'),'-r',old,str(self.config)],capture_output=True,timeout=20)
                self.assertEqual(1,native.returncode);self.assertEqual(b'',native.stderr)
                self.assertEqual({'ok':False,'code':'tv_flag_statement_shape'},json.loads(native.stdout))
                result=self.enable();self.assertEqual('enabled',result['status'],result)
                before=self.original.encode();offset=before.index(b';')+1 if strict else len(b'<?php\n')
                self.assertEqual(before[:offset]+signing.TV_FLAG_LINE+before[offset:],self.config.read_bytes())
                self.assertTrue(self.config.read_text().endswith(pointer))
                self.assertEqual(0,outside.stat().st_mode&0o777);self.assertEqual(0,self.keyfile.stat().st_mode&0o777)
                self.assertFalse((outside.parent/'unwanted-execution').exists())
                self.assertNotIn('SYNTHETIC_UNRELATED_CREDENTIAL',json.dumps(result))

    def test_only_exact_first_publisher_stub_is_recognized(self):
        statement=self.publisher_stub().splitlines()[1]
        for bad in [statement.replace('4','3'),statement.replace('4','5'),statement.replace('__DIR__','__FILE__'),
                    statement.replace('search3-preview.php','other.php'),statement.replace('require_once','require'),
                    statement.replace('require_once','include_once'),statement.replace('dirname(__DIR__, 4)',"getenv('HOME')"),
                    'if (true) '+statement,statement+'\n'+statement,
                    "define('OTHER',true);\n"+statement]:
            with self.subTest(shape=bad):
                before='<?php\n'+bad+'\n'+self.original[len('<?php\n'):];self.config.write_text(before)
                result=self.enable();self.assertIn(result['reason'],['tv_enable_value_shape','tv_enable_statement_shape','tv_enable_syntax_shape'])
                self.assertEqual(before,self.config.read_text());self.assertEqual(0,self.keyfile.stat().st_mode&0o777)
                self.assertEqual([],list(self.home.rglob('tv-enable-plan.json')))

    def test_prewrite_config_race_not_overwritten(self):
        self.config_race=True;r=self.enable();self.assertEqual('tv_enable_config_drift',r['reason'])
        self.assertEqual(self.original+'// concurrent writer\n',self.config.read_text())

    def test_http_failure_rolls_back_exact_config(self):
        self.http_failure=True;r=self.enable();self.assertEqual('rolled_back',r['status'],r)
        self.assertEqual('synthetic_http_failure',r['install_failure_class']);self.assert_config_unchanged()
        self.assertEqual({'status':'complete','restored_files':1},r['rollback'])

    def test_config_candidate_budget_is_checked_before_backup(self):
        self.original+=' '*(65536-len(self.original.encode()))
        self.config.write_text(self.original)
        self.assertEqual('tv_enable_config_budget',self.enable()['reason']);self.assert_config_unchanged()
        self.assertEqual([],list(self.home.rglob('tv-enable-plan.json')))

    def test_own_http_response_validation_without_network(self):
        start=signing.TV_ENABLE_REMOTE.index('def tv_enable_http_ready():')
        end=signing.TV_ENABLE_REMOTE.index('\ndef tv_enable_config(op):',start)
        def fail(reason):raise RuntimeError(reason)
        namespace={'json':json,'re':__import__('re'),'fail':fail}
        exec(signing.TV_ENABLE_REMOTE[start:end],namespace)
        class Response:
            status=200
            def __init__(self,body,cookie,cache='private, no-store'):
                self.body=json.dumps(body).encode();self.headers=Message()
                self.headers.add_header('Set-Cookie',cookie);self.headers.add_header('Cache-Control',cache)
            def read(self,limit):return self.body[:limit]
            def __enter__(self):return self
            def __exit__(self,*args):pass
        body={'ok':True,'csrf':'a'*64,'provider':'tourvisor'}
        cookie='ANYTOUR_ANEX_SEARCH3=discarded; path=/_preview/search3-anex-candidate/; secure; HttpOnly; SameSite=Lax'
        opener=mock.Mock();opener.open.return_value=Response(body,cookie)
        with mock.patch('urllib.request.build_opener',return_value=opener):
            proof=namespace['tv_enable_http_ready']()
            self.assertEqual(1,proof['requests']);self.assertFalse(proof['csrf_exported'])
            request=opener.open.call_args.args[0]
            self.assertEqual('GET',request.get_method());self.assertEqual({'timeout':15},opener.open.call_args.kwargs)
            for bad in [Response(body|{'csrf':'short'},cookie),Response(body|{'provider':'anex'},cookie),
                        Response(body|{'extra':'unexpected'},cookie),Response(body,cookie.replace('search3-anex-candidate/','')),
                        Response(body,cookie.replace('; secure','')),Response(body,cookie,'public')]:
                opener.open.return_value=bad
                with self.assertRaisesRegex(RuntimeError,'tv_enable_http_unavailable'):namespace['tv_enable_http_ready']()

    def test_fixed_http_and_parent_contract(self):
        self.assertIn("https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php?action=tv_token",signing.TV_ENABLE_REMOTE)
        self.assertIn('class NoRedirect',signing.TV_ENABLE_REMOTE)
        self.assertNotIn('keyfile.read',signing.TV_ENABLE_REMOTE)
        self.assertEqual('d5be638399918ca5cabf2ba2e22e768c9e1e5a99c416d032272d65664a5e42b9',signing.TV_PARENT_CANONICAL)


class NativeTVGatewayRecovery(NativeTVGatewayEnable):
    def setUp(self):
        super().setUp()
        self.original=self.publisher_stub()+self.original.splitlines(keepends=True)[-1]
        self.config.write_text(self.original)
        self.failed=self.home/'.anytoour-int-executor'/signing.TV_FAILURE_OPERATION
        self.failed.mkdir(mode=0o700);(self.failed/'source').mkdir(mode=0o700)
        self.failed_result={'booking_calls':0,'database_writes':'unknown','lead_calls':0,'mode':'enable-tv-session-gateway',
            'operation_id':signing.TV_FAILURE_OPERATION,'production_before':{p:v.get('sha256') for p,v in signing.TV_PUBLIC.items() if p!='api-v2.php'},
            'reason':'tv_enable_config_shape','schema_version':1,'source_sha':signing.TV_SOURCE,'status':'unknown_no_replay',
            'supplier_calls':'unknown','tv_gateway_public_before':signing.TV_PUBLIC}
        self.assertEqual(signing.TV_FAILURE_CANONICAL,hashlib.sha256(json.dumps(self.failed_result,sort_keys=True,separators=(',',':')).encode()).hexdigest())
        (self.failed/'result.json').write_text(json.dumps(self.failed_result,sort_keys=True,separators=(',',':')))
        (self.failed/'reservation.json').write_text(json.dumps({'operation_id':signing.TV_FAILURE_OPERATION,
            'source_sha':signing.TV_SOURCE,'mode':'enable-tv-session-gateway','reserved_at':1792878873},sort_keys=True))
        (self.failed/'installed-source.json').write_text(json.dumps({'source_sha':signing.TV_SOURCE,
            'files':{p:hashlib.sha256(b).hexdigest() for p,b in self.inventory.items()}},sort_keys=True))
        for p in ('result.json','reservation.json','installed-source.json'):(self.failed/p).chmod(0o600)
        self.before={p:(self.failed/p).read_bytes() for p in ('result.json','reservation.json','installed-source.json')}
        self.original_drift=False

    def mutate_remote(self,c):
        super().mutate_remote(c)
        anchor='TV_FAILURE_MANIFEST='+repr(signing.TV_FAILURE_MANIFEST)
        self.assertEqual(1,c.REMOTE.count(anchor))
        c.REMOTE=c.REMOTE.replace(anchor,'TV_FAILURE_MANIFEST='+repr(self.manifest_hash),1)
        if self.original_drift:
            anchor="    tv_enable_preflight()\n    if tv_enable_config_identity(config)!=identity or config.read_bytes()!=old:fail('tv_enable_config_drift')"
            self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,
                "    p=private/TV_FAILURE_OPERATION/'result.json';p.write_bytes(p.read_bytes()+b' ')\n"+anchor,1)

    def recover(self,operation=None):
        # Isolated HOME cases use explicit fixture IDs; production parser permits ONLY the pinned one.
        operation=operation or f'int-anex-tv-session-gateway-recovery-native-{self.counter+1}-v1'
        with mock.patch.object(signing,'TV_RECOVERY_OPERATION',operation):
            return self.execute(signing.TV_RECOVERY_MODE,operation,source_sha=signing.TV_SOURCE)[0]

    def assert_original_immutable(self):
        for p,b in self.before.items():self.assertEqual(b,(self.failed/p).read_bytes())
        self.assertEqual({'reservation.json','result.json','source','installed-source.json'},{p.name for p in self.failed.iterdir()})

    def test_first_write_success_preserves_original_unknown_and_strict_receipt(self):
        r=self.recover();self.assertEqual('enabled',r['status'],r);self.assert_original_immutable()
        self.assertEqual('unknown_no_replay',r['tv_recovery']['status']);self.assertEqual('unknown',r['tv_recovery']['supplier_calls'])
        self.assertEqual(0,r['supplier_calls']);self.assertEqual(0,r['database_writes'])
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.multiple(signing,TV_PUBLIC=self.public,TV_PARENT_CANONICAL=self.parent_digest,
                                 TV_RECOVERY_OPERATION=r['operation_id'],TV_FAILURE_MANIFEST=self.manifest_hash):
            self.assertTrue(signing.completed_recovery(command,r))
            for k,v in [('original_prewrite_state_absent',False),('run_id',True),('status','complete'),('reservation_sha256','bad')]:
                self.assertFalse(signing.completed_recovery(command,r|{'tv_recovery':r['tv_recovery']|{k:v}}))
        self.assertEqual('operation_exists_no_replay',self.recover(r['operation_id'])['reason'])

    def test_any_original_plan_backup_or_temp_holds_without_config_write(self):
        for name in ('tv-enable-plan.json','backup'):
            p=self.failed/name;p.mkdir() if name=='backup' else p.write_text('{}')
            self.assertEqual('tv_recovery_original_write_state',self.recover()['reason']);self.assert_config_unchanged()
            p.rmdir() if name=='backup' else p.unlink()
        temp=self.runtime/('..anex-private.php.'+signing.TV_FAILURE_OPERATION+'.tmp');temp.write_text('uncertain original staged state')
        self.assertEqual('tv_recovery_original_write_state',self.recover()['reason']);self.assert_config_unchanged()
        self.assert_original_immutable()

    def test_original_receipt_or_reservation_mismatch_holds(self):
        p=self.failed/'result.json';p.write_text('{}')
        self.assertEqual('tv_recovery_original_receipt',self.recover()['reason']);self.assert_config_unchanged();p.write_bytes(self.before['result.json'])
        p=self.failed/'reservation.json';v=json.loads(p.read_bytes());v['reserved_at']=1792878800;p.write_text(json.dumps(v))
        self.assertEqual('tv_recovery_original_reservation',self.recover()['reason']);self.assert_config_unchanged()
        p.write_bytes(self.before['reservation.json']);self.assert_original_immutable()
        p=self.failed/'installed-source.json';p.write_text(json.dumps({'source_sha':SHA,'files':{}}))
        self.assertEqual('tv_recovery_original_source_manifest',self.recover()['reason']);self.assert_config_unchanged()
        p.write_bytes(self.before['installed-source.json']);self.assert_original_immutable()

    def test_current_outer_stub_and_flag_absence_required(self):
        before=self.original;self.config.write_text(before.replace(self.publisher_stub(),"<?php\n"))
        self.assertEqual('tv_recovery_current_outer_stub',self.recover()['reason']);self.assert_original_immutable()
        self.config.write_text(before+"define('ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED',true);\n")
        self.assertEqual('tv_enable_flag_already_defined',self.recover()['reason']);self.assert_original_immutable()
        self.assertEqual([],list(self.home.rglob('tv-enable-plan.json')))

    def test_original_identity_drift_before_write_holds(self):
        self.original_drift=True;r=self.recover();self.assertEqual('tv_recovery_original_drift',r['reason']);self.assert_config_unchanged()

    def test_readiness_failure_rolls_back_only_new_flag(self):
        self.http_failure=True;r=self.recover();self.assertEqual('rolled_back',r['status']);self.assert_config_unchanged()
        self.assert_original_immutable();self.assertEqual({'status':'complete','restored_files':1},r['rollback'])



class NativeTVDiagnosticInstall(NativeTVGatewayInstall):
    mode=signing.TV_DIAGNOSTIC_MODE
    operation_attr='TV_DIAGNOSTIC_OPERATION'
    source=signing.TV_DIAGNOSTIC_SOURCE
    path=signing.TV_DIAGNOSTIC_PATH
    sha_attr='TV_DIAGNOSTIC_SHA'
    fixture_env='TV_DIAGNOSTIC_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_diagnostics)

    def expected_previous(self):
        if self.mode==signing.TV_CHECKPOINT_MODE:return signing.TV_PINS|{signing.TV_DIAGNOSTIC_PATH:signing.TV_DIAGNOSTIC_SHA,signing.TV_BYTES_PATH:signing.TV_BYTES_SHA}
        if self.mode==signing.TV_BYTES_MODE:return signing.TV_PINS|{signing.TV_DIAGNOSTIC_PATH:signing.TV_DIAGNOSTIC_SHA,signing.TV_CAP_PATH:signing.TV_CAP_SHA}
        if self.mode==signing.TV_CAP_MODE:return signing.TV_PINS|{signing.TV_DIAGNOSTIC_PATH:signing.TV_DIAGNOSTIC_SHA,signing.TV_LIMIT_PATH:signing.TV_LIMIT_SHA}
        if self.mode==signing.TV_LIMIT_MODE:return signing.TV_PINS|{signing.TV_DIAGNOSTIC_PATH:signing.TV_DIAGNOSTIC_SHA,signing.TV_DAY_PATH:signing.TV_DAY_SHA}
        if self.mode==signing.TV_DIAGNOSTIC_MODE:return signing.TV_PINS
        current=signing.TV_PINS|{signing.TV_DIAGNOSTIC_PATH:signing.TV_DIAGNOSTIC_SHA}
        if self.mode in (signing.TV_CONTEXT_MODE,signing.TV_FIELDS_MODE):current|={signing.TV_CACHE_PATH:signing.TV_CACHE_SHA}
        if self.mode in (signing.TV_FIELDS_MODE,signing.TV_DAY_MODE):current|={signing.TV_CONTEXT_PATH:signing.TV_CONTEXT_SHA}
        return current|{signing.TV_FIELDS_PATH:signing.TV_FIELDS_SHA} if self.mode==signing.TV_DAY_MODE else current
    def setUp(self):
        super().setUp()
        # Use the current accepted diagnostic client before the later cache-guard update.
        if self.mode!=signing.TV_DIAGNOSTIC_MODE:
            fixture=os.environ.get('TV_DIAGNOSTIC_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_DIAGNOSTIC_PATH).read_bytes() if fixture else b'<?php // installed diagnostic client\n'
            if fixture:self.assertEqual(signing.TV_DIAGNOSTIC_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_DIAGNOSTIC_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        if self.mode in (signing.TV_CONTEXT_MODE,signing.TV_FIELDS_MODE):
            fixture=os.environ.get('TV_CACHE_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_CACHE_PATH).read_bytes() if fixture else b'<?php // installed cache guard\n'
            if fixture:self.assertEqual(signing.TV_CACHE_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_CACHE_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        if self.mode in (signing.TV_FIELDS_MODE,signing.TV_DAY_MODE):
            fixture=os.environ.get('TV_CONTEXT_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_CONTEXT_PATH).read_bytes() if fixture else b'<?php // installed context guard\n'
            if fixture:self.assertEqual(signing.TV_CONTEXT_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_CONTEXT_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        if self.mode==signing.TV_DAY_MODE:
            fixture=os.environ.get('TV_FIELDS_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_FIELDS_PATH).read_bytes() if fixture else b'<?php // installed field diagnostics\n'
            if fixture:self.assertEqual(signing.TV_FIELDS_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_FIELDS_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        if self.mode==signing.TV_LIMIT_MODE:
            fixture=os.environ.get('TV_DAY_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_DAY_PATH).read_bytes() if fixture else b'<?php // installed calendar-day helper\n'
            if fixture:self.assertEqual(signing.TV_DAY_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_DAY_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        if self.mode==signing.TV_CAP_MODE:
            fixture=os.environ.get('TV_LIMIT_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_LIMIT_PATH).read_bytes() if fixture else b'<?php // installed limit diagnostics\n'
            if fixture:self.assertEqual(signing.TV_LIMIT_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_LIMIT_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        if self.mode==signing.TV_BYTES_MODE:
            fixture=os.environ.get('TV_CAP_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_CAP_PATH).read_bytes() if fixture else b'<?php // installed640 request capacity\n'
            if fixture:self.assertEqual(signing.TV_CAP_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_CAP_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        if self.mode==signing.TV_CHECKPOINT_MODE:
            fixture=os.environ.get('TV_BYTES_SOURCE_ROOT')
            raw=(Path(fixture)/signing.TV_BYTES_PATH).read_bytes() if fixture else b'<?php // installed20MiB gateway\n'
            if fixture:self.assertEqual(signing.TV_BYTES_SHA,hashlib.sha256(raw).hexdigest())
            self.inventory[signing.TV_BYTES_PATH]=raw
            self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        # All current TV files are present; only the fixed maintenance target may change.
        for p in signing.TV_PINS:
            (self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php')).write_bytes(self.inventory[p])
        self.previous={p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in self.pins.items()}
        fixture=os.environ.get(self.fixture_env)
        raw=(Path(fixture)/self.path).read_bytes() if fixture else b'<?php // synthetic maintenance helper\n'
        if fixture:self.assertEqual(getattr(signing,self.sha_attr),hashlib.sha256(raw).hexdigest())
        self.inventory[self.path]=raw
        self.newpins=self.pins|{self.path:hashlib.sha256(raw).hexdigest()}
        self.config.chmod(0o644);self.config_hash=hashlib.sha256(self.config.read_bytes()).hexdigest()
        self.keyfile.parent.mkdir(parents=True);self.keyfile.write_text('KEY_MUST_NOT_BE_READ');self.keyfile.chmod(0)
        self.parent=self.home/'.anytoour-int-executor'/signing.TV_RECOVERY_OPERATION/'result.json'
        self.parent.parent.mkdir(mode=0o700)
        self.parent.write_text(json.dumps({'source_sha':signing.TV_SOURCE,'mode':'recover-tv-session-gateway',
            'operation_id':signing.TV_RECOVERY_OPERATION,'status':'enabled'}));self.parent.chmod(0o600)
        self.parent_hash=hashlib.sha256(json.dumps(json.loads(self.parent.read_bytes()),sort_keys=True,separators=(',',':')).encode()).hexdigest()
        self.parent_raw=self.parent.read_bytes();self.config_raw=self.config.read_bytes()
        self.rebuild_archive();self.fail_after_write=False;self.config_before_write=False

    def expected_target(self):
        return self.expected_previous()|{self.path:getattr(signing,self.sha_attr)}

    def mutate_remote(self,c):
        for name,old,new in [('TV_PINS',self.expected_target(),self.newpins),
            ('TV_PREVIOUS',{p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in self.expected_previous().items()},self.previous),
            ('TV_DEPENDENCIES',signing.TV_DEPENDENCIES,self.deps),('TV_PUBLIC',signing.TV_PUBLIC,self.public),
            ('TV_ENABLED_CANONICAL',signing.TV_ENABLED_CANONICAL,self.parent_hash),
            ('TV_ENABLED_CONFIG_SHA',signing.TV_ENABLED_CONFIG_SHA,self.config_hash)]:
            anchor=name+'='+repr(old);self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,name+'='+repr(new),1)
        if self.fail_after_write:
            anchor="        result['diagnostic_basis_after']=tv_diagnostic_boundary()"
            self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,"        fail('synthetic_postwrite_failure')\n"+anchor,1)
        if self.config_before_write:
            anchor="    if mode==TV_GATEWAY_MODE: tv_gateway_preflight()\n    install_started=True"
            self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,"    (runtime/'.anex-private.php').write_bytes(b'<?php // concurrent writer\\n')\n"+anchor,1)

    def install_maintenance(self,operation=None):
        prefix=('int-anex-apd-application-reason-install-' if self.mode==signing.ANEX_APD_REASON_MODE else
                'int-anex-tv-seat-availability-install-' if self.mode==signing.TV_SEATS_MODE else
                'int-anex-tv-session-fuel-context-install-' if self.mode==signing.TV_SESSION_FUEL_MODE else
                'int-anex-tv-numeric-admission-install-' if self.mode==signing.TV_NUMERIC_MODE else
                'int-anex-tv-party-retirement-install-' if self.mode==signing.TV_PARTY_MODE else
                'int-anex-tv-scoped-checkpoint-install-' if self.mode==signing.TV_CHECKPOINT_MODE else
                'int-anex-tv-context-cap-20mib-install-' if self.mode==signing.TV_BYTES_MODE else
                'int-anex-tv-request-cap-640-install-' if self.mode==signing.TV_CAP_MODE else
                'int-anex-tv-limit-diagnostics-install-' if self.mode==signing.TV_LIMIT_MODE else
                'int-anex-tv-calendar-day-install-' if self.mode==signing.TV_DAY_MODE else
                'int-anex-tv-mismatch-fields-install-' if self.mode==signing.TV_FIELDS_MODE else
                'int-anex-tv-context-revocation-install-' if self.mode==signing.TV_CONTEXT_MODE else
                'int-anex-tv-cache-revocation-install-' if self.mode==signing.TV_CACHE_MODE else 'int-anex-tv-client-diagnostics-install-')
        operation=operation or f'{prefix}native-{self.counter+1}-v1'
        with mock.patch.object(signing,self.operation_attr,operation):
            return self.execute(self.mode,operation,self.source)[0]

    def test_one_helper_update_preserves_every_other_boundary_and_no_replay(self):
        r=self.install_maintenance();self.assertEqual('installed',r['status'])
        self.assertEqual((6,1,0),(r['install']['files'],r['install']['changed_files'],r['install']['created_files']))
        plan=json.loads(next(self.home.rglob('anex-preview-install-plan.json')).read_bytes())
        self.assertEqual([self.path],plan['changed_files'])
        self.assertFalse(r['install']['endpoint']['changed']);self.assertFalse(r['install']['dependency']['changed'])
        for p in signing.TV_PINS:
            self.assertEqual(self.inventory[p],(self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php')).read_bytes())
        self.assertEqual(self.config_raw,self.config.read_bytes());self.assertEqual(0o644,self.config.stat().st_mode&0o777)
        self.assertEqual(self.parent_raw,self.parent.read_bytes());self.assertEqual(0,self.keyfile.stat().st_mode&0o777)
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.multiple(signing,**{self.operation_attr:r['operation_id'],'TV_PINS':self.pins,
            'TV_DIAGNOSTIC_SHA':self.newpins[signing.TV_DIAGNOSTIC_PATH],self.sha_attr:self.newpins[self.path],'TV_PUBLIC':self.public,'TV_DEPENDENCIES':self.deps,
            'TV_ENABLED_CANONICAL':self.parent_hash,'TV_ENABLED_CONFIG_SHA':self.config_hash}):
            self.assertTrue(self.completion(command,r))
            for k,v in [('supplier_calls',True),('diagnostic_basis_after',{}),('tv_gateway_public_after',{}),('runtime_changed',False)]:
                self.assertFalse(self.completion(command,r|{k:v}))
            for k,v in [('changed_files',2),('created_files',True),('files',True)]:
                self.assertFalse(self.completion(command,r|{'install':r['install']|{k:v}}))
        self.assertEqual('operation_exists_no_replay',self.install_maintenance(r['operation_id'])['reason'])

    def test_bad_parent_config_dependency_and_source_hold_before_write(self):
        target=self.runtime/self.path;old=target.read_bytes()
        for kind in ('parent','config','dependency','source','already_updated'):
            with self.subTest(kind=kind):
                if kind=='parent':p=self.parent;original=p.read_bytes();p.write_text('{}');reason='tv_diagnostic_parent_receipt'
                elif kind=='config':p=self.config;original=p.read_bytes();p.write_text('<?php // foreign config');reason='tv_diagnostic_config'
                elif kind=='dependency':p=self.runtime/'app/integrations/anex-initial-week-gate.php';original=p.read_bytes();p.write_text('<?php // foreign dependency');reason='tv_gateway_predecessor'
                elif kind=='source':
                    p=None;original=self.inventory[self.path];self.inventory[self.path]=b'<?php // wrong staged source';self.rebuild_archive();reason='tv_gateway_source_pin'
                else:p=target;original=old;p.write_bytes(self.inventory[self.path]);reason='tv_gateway_predecessor'
                self.assertEqual(reason,self.install_maintenance()['reason'])
                if kind!='already_updated':self.assertEqual(old,target.read_bytes())
                if p is not None:p.write_bytes(original)
                else:self.inventory[self.path]=original;self.rebuild_archive()
        self.assertEqual(self.config_raw,self.config.read_bytes())

    def test_config_prewrite_race_holds_and_postwrite_failure_rolls_back(self):
        old=(self.runtime/self.path).read_bytes();self.config_before_write=True
        self.assertEqual('tv_diagnostic_config',self.install_maintenance()['reason'])
        self.assertEqual(old,(self.runtime/self.path).read_bytes())
        self.config_before_write=False;self.config.write_bytes(self.config_raw);self.fail_after_write=True
        r=self.install_maintenance();self.assertEqual('rolled_back',r['status']);self.assertEqual('complete',r['rollback']['status'])
        self.assertEqual(old,(self.runtime/self.path).read_bytes());self.assertEqual(self.config_raw,self.config.read_bytes())

class NativeTVCacheInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_CACHE_MODE
    operation_attr='TV_CACHE_OPERATION'
    source=signing.TV_CACHE_SOURCE
    path=signing.TV_CACHE_PATH
    sha_attr='TV_CACHE_SHA'
    fixture_env='TV_CACHE_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_cache)


class NativeTVContextInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_CONTEXT_MODE
    operation_attr='TV_CONTEXT_OPERATION'
    source=signing.TV_CONTEXT_SOURCE
    path=signing.TV_CONTEXT_PATH
    sha_attr='TV_CONTEXT_SHA'
    fixture_env='TV_CONTEXT_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_context)


class NativeTVFieldsInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_FIELDS_MODE
    operation_attr='TV_FIELDS_OPERATION'
    source=signing.TV_FIELDS_SOURCE
    path=signing.TV_FIELDS_PATH
    sha_attr='TV_FIELDS_SHA'
    fixture_env='TV_FIELDS_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_fields)


class NativeTVDayInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_DAY_MODE
    operation_attr='TV_DAY_OPERATION'
    source=signing.TV_DAY_SOURCE
    path=signing.TV_DAY_PATH
    sha_attr='TV_DAY_SHA'
    fixture_env='TV_DAY_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_day)


class NativeTVLimitInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_LIMIT_MODE
    operation_attr='TV_LIMIT_OPERATION'
    source=signing.TV_LIMIT_SOURCE
    path=signing.TV_LIMIT_PATH
    sha_attr='TV_LIMIT_SHA'
    fixture_env='TV_LIMIT_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_limit)


class NativeTVCapInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_CAP_MODE
    operation_attr='TV_CAP_OPERATION'
    source=signing.TV_CAP_SOURCE
    path=signing.TV_CAP_PATH
    sha_attr='TV_CAP_SHA'
    fixture_env='TV_CAP_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_cap)


class NativeTVPartyInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_PARTY_MODE
    operation_attr='TV_PARTY_OPERATION'
    source=signing.TV_PARTY_SOURCE
    path=signing.TV_CHECKPOINT_PATH
    sha_attr='TV_CHECKPOINT_SHA'
    fixture_env='TV_CHECKPOINT_SOURCE_ROOT'

    def expected_previous(self):
        return signing.TV_PINS|{signing.TV_DIAGNOSTIC_PATH:signing.TV_DIAGNOSTIC_SHA,self.path:signing.TV_CHECKPOINT_SHA}

    def expected_target(self):
        return self.expected_previous()|signing.TV_PARTY_UPDATES

    def setUp(self):
        super().setUp()
        old=os.environ.get('TV_CHECKPOINT_SOURCE_ROOT');new=os.environ.get('TV_PARTY_SOURCE_ROOT')
        for p in signing.TV_PARTY_UPDATES:
            raw=(Path(old)/p).read_bytes() if old else b'<?php // previous party helper\n'
            if old:self.assertEqual(self.expected_previous()[p],hashlib.sha256(raw).hexdigest())
            (self.runtime/p).write_bytes(raw);self.pins[p]=hashlib.sha256(raw).hexdigest()
            raw=(Path(new)/p).read_bytes() if new else b'<?php // new party helper\n'
            if new:self.assertEqual(signing.TV_PARTY_UPDATES[p],hashlib.sha256(raw).hexdigest())
            self.inventory[p]=raw
        self.previous={p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in self.pins.items()}
        self.newpins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        self.rebuild_archive()

    def test_pair_install_strict_completion_and_no_replay(self):
        r=self.install_maintenance();self.assertEqual('installed',r['status'],r)
        self.assertEqual((6,2,0),tuple(r['install'][k] for k in ('files','changed_files','created_files')))
        for p in signing.TV_PINS:self.assertEqual(self.inventory[p],(self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php')).read_bytes())
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.multiple(signing,TV_PARTY_OPERATION=r['operation_id'],TV_PINS=self.pins,
            TV_PARTY_UPDATES={p:self.newpins[p] for p in signing.TV_PARTY_UPDATES},TV_PUBLIC=self.public,
            TV_ENABLED_CANONICAL=self.parent_hash,TV_ENABLED_CONFIG_SHA=self.config_hash,TV_DEPENDENCIES=self.deps):
            self.assertTrue(signing.completed_tv_party(command,r))
            for k,v in [('changed_files',1),('changed_files',3),('changed_files',True),('created_files',1)]:
                self.assertFalse(signing.completed_tv_party(command,r|{'install':r['install']|{k:v}}))
            for k,v in [('supplier_calls',True),('diagnostic_basis_after',{}),('tv_gateway_public_after',{})]:
                self.assertFalse(signing.completed_tv_party(command,r|{k:v}))
        self.assertEqual(self.config_raw,self.config.read_bytes());self.assertEqual(self.parent_raw,self.parent.read_bytes())
        self.assertEqual('operation_exists_no_replay',self.install_maintenance(r['operation_id'])['reason'])

    def test_either_predecessor_or_source_drift_holds(self):
        before={p:(self.runtime/p).read_bytes() for p in signing.TV_PARTY_UPDATES}
        for p in signing.TV_PARTY_UPDATES:
            target=self.runtime/p;target.write_text('<?php // predecessor drift')
            self.assertEqual('tv_gateway_predecessor',self.install_maintenance()['reason'])
            target.write_bytes(before[p]);raw=self.inventory[p];self.inventory[p]=b'<?php // source drift';self.rebuild_archive()
            self.assertEqual('tv_gateway_source_pin',self.install_maintenance()['reason'])
            self.inventory[p]=raw;self.rebuild_archive()
            for other,b in before.items():self.assertEqual(b,(self.runtime/other).read_bytes())

    def test_pair_prewrite_race_and_postwrite_rollback(self):
        before={p:(self.runtime/p).read_bytes() for p in signing.TV_PARTY_UPDATES}
        self.config_before_write=True
        self.assertEqual('tv_diagnostic_config',self.install_maintenance()['reason'])
        self.config_before_write=False;self.config.write_bytes(self.config_raw);self.fail_after_write=True
        r=self.install_maintenance();self.assertEqual('rolled_back',r['status']);self.assertEqual('complete',r['rollback']['status'])
        for p,b in before.items():self.assertEqual(b,(self.runtime/p).read_bytes())
        self.assertEqual(self.config_raw,self.config.read_bytes())


class NativeTVNumericInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_NUMERIC_MODE
    operation_attr='TV_NUMERIC_OPERATION'
    source=signing.TV_NUMERIC_SOURCE
    path=signing.TV_DIAGNOSTIC_PATH
    sha_attr='TV_NUMERIC_SHA'
    fixture_env='TV_NUMERIC_SOURCE_ROOT'

    def expected_previous(self):
        return signing.TV_PINS|signing.TV_PARTY_UPDATES

    def setUp(self):
        super().setUp()
        fixture=os.environ.get('TV_PARTY_SOURCE_ROOT')
        for p,sha in signing.TV_PARTY_UPDATES.items():
            raw=(Path(fixture)/p).read_bytes() if fixture else b'<?php // installed party helper\n'
            if fixture:self.assertEqual(sha,hashlib.sha256(raw).hexdigest())
            (self.runtime/p).write_bytes(raw);self.pins[p]=hashlib.sha256(raw).hexdigest()
            if p!=self.path:self.inventory[p]=raw
        self.previous={p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in self.pins.items()}
        self.newpins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
        self.rebuild_archive()

    def completion(self,command,result):
        with mock.patch.object(signing,'TV_PARTY_UPDATES',{p:self.newpins[p] for p in signing.TV_PARTY_UPDATES}):
            return signing.completed_tv_numeric(command,result)

    def test_unchanged_gateway_drift_holds_before_client_write(self):
        target=self.runtime/'app/integrations/tourvisor-preview-gateway.php'
        client=(self.runtime/self.path).read_bytes();previous=target.read_bytes()
        target.write_bytes(b'<?php // foreign gateway\n')
        self.assertEqual('tv_gateway_predecessor',self.install_maintenance()['reason'])
        self.assertEqual(client,(self.runtime/self.path).read_bytes())
        target.write_bytes(previous)


class NativeTVSessionFuelInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_SESSION_FUEL_MODE
    operation_attr='TV_SESSION_FUEL_OPERATION'
    source=signing.TV_SESSION_FUEL_SOURCE
    path=signing.TV_SESSION_FUEL_PATH
    sha_attr='TV_SESSION_FUEL_SHA'
    fixture_env='TV_SESSION_FUEL_SOURCE_ROOT'

    def expected_previous(self):
        return signing.tv_session_fuel_previous()

    def expected_target(self):
        return self.expected_previous()|signing.TV_SESSION_FUEL_UPDATES

    @staticmethod
    def target(path):
        return path if path.startswith('app/') else 'api-anex-search3-preview.php'

    def setUp(self):
        super().setUp()
        previous=os.environ.get('TV_NUMERIC_SOURCE_ROOT')
        candidate=os.environ.get(self.fixture_env)
        for p,sha in self.expected_previous().items():
            raw=(Path(previous)/p).read_bytes() if previous else b'<?php // exact installed session fuel dependency\n'
            if previous:self.assertEqual(sha,hashlib.sha256(raw).hexdigest(),p)
            (self.runtime/self.target(p)).write_bytes(raw);self.pins[p]=hashlib.sha256(raw).hexdigest()
            if p not in signing.TV_SESSION_FUEL_UPDATES:self.inventory[p]=raw
        for p,sha in signing.TV_SESSION_FUEL_UPDATES.items():
            raw=(Path(candidate)/p).read_bytes() if candidate else b'<?php // reviewed session fuel update '+p.encode()+b'\n'
            if candidate:self.assertEqual(sha,hashlib.sha256(raw).hexdigest(),p)
            self.inventory[p]=raw
        self.previous={self.target(p):sha for p,sha in self.pins.items()}
        self.newpins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in self.expected_target()}
        self.updates={p:self.newpins[p] for p in signing.TV_SESSION_FUEL_UPDATES}
        self.rebuild_archive()

    def test_pair_install_strict_completion_and_no_replay(self):
        r=self.install_maintenance();self.assertEqual('installed',r['status'])
        self.assertEqual((10,2,0),(r['install']['files'],r['install']['changed_files'],r['install']['created_files']))
        plan=json.loads(next(self.home.rglob('anex-preview-install-plan.json')).read_bytes())
        self.assertEqual([self.target(p) for p in self.updates],plan['changed_files'])
        self.assertTrue(r['install']['endpoint']['changed']);self.assertFalse(r['install']['dependency']['changed'])
        for p in self.newpins:self.assertEqual(self.inventory[p],(self.runtime/self.target(p)).read_bytes())
        self.assertEqual(self.config_raw,self.config.read_bytes());self.assertEqual(self.parent_raw,self.parent.read_bytes())
        self.assertEqual(0,self.keyfile.stat().st_mode&0o777)
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        overrides={self.operation_attr:r['operation_id'],'TV_PINS':self.pins,
            'TV_PARTY_UPDATES':{p:self.pins[p] for p in signing.TV_PARTY_UPDATES},
            'TV_NUMERIC_SHA':self.pins[signing.TV_DIAGNOSTIC_PATH],
            'TV_SESSION_FUEL_PREDECESSOR':self.pins[self.path],
            'TV_SESSION_FUEL_DEPENDENCIES':{p:self.pins[p] for p in signing.TV_SESSION_FUEL_DEPENDENCIES},
            'TV_SESSION_FUEL_UPDATES':self.updates,'TV_PUBLIC':self.public,'TV_DEPENDENCIES':self.deps,
            'TV_ENABLED_CANONICAL':self.parent_hash,'TV_ENABLED_CONFIG_SHA':self.config_hash}
        with mock.patch.multiple(signing,**overrides):
            self.assertTrue(signing.completed_tv_session_fuel(command,r))
            for k,v in [('supplier_calls',True),('diagnostic_basis_after',{}),('tv_gateway_public_after',{}),('runtime_changed',False)]:
                self.assertFalse(signing.completed_tv_session_fuel(command,r|{k:v}))
            for k,v in [('changed_files',1),('created_files',True),('files',6)]:
                self.assertFalse(signing.completed_tv_session_fuel(command,r|{'install':r['install']|{k:v}}))
            self.assertFalse(signing.completed_tv_session_fuel(command,r|{'tv_gateway':r['tv_gateway']|{'files':self.pins}}))
            self.assertFalse(signing.completed_tv_session_fuel(command,r|{'install':r['install']|{
                'endpoint':r['install']['endpoint']|{'changed':False}}}))
        self.assertEqual('operation_exists_no_replay',self.install_maintenance(r['operation_id'])['reason'])

    def test_either_target_or_passive_dependency_drift_holds(self):
        old={p:(self.runtime/self.target(p)).read_bytes() for p in self.updates}
        for p in self.updates|signing.TV_SESSION_FUEL_DEPENDENCIES:
            target=self.runtime/self.target(p);before=target.read_bytes();target.write_bytes(b'<?php // foreign predecessor\n')
            self.assertEqual('tv_gateway_predecessor',self.install_maintenance()['reason'])
            target.write_bytes(before)
            for q,raw in old.items():self.assertEqual(raw,(self.runtime/self.target(q)).read_bytes())
        for p in self.updates:
            raw=self.inventory[p];self.inventory[p]=b'<?php // foreign staged source\n';self.rebuild_archive()
            self.assertEqual('tv_gateway_source_pin',self.install_maintenance()['reason'])
            self.inventory[p]=raw;self.rebuild_archive()
            for q,before in old.items():self.assertEqual(before,(self.runtime/self.target(q)).read_bytes())

    def test_pair_prewrite_race_and_postwrite_rollback(self):
        old={p:(self.runtime/self.target(p)).read_bytes() for p in self.updates}
        self.config_before_write=True
        self.assertEqual('tv_diagnostic_config',self.install_maintenance()['reason'])
        for p,raw in old.items():self.assertEqual(raw,(self.runtime/self.target(p)).read_bytes())
        self.config_before_write=False;self.config.write_bytes(self.config_raw);self.fail_after_write=True
        r=self.install_maintenance();self.assertEqual('rolled_back',r['status']);self.assertEqual('complete',r['rollback']['status'])
        for p,raw in old.items():self.assertEqual(raw,(self.runtime/self.target(p)).read_bytes())
        self.assertEqual(self.config_raw,self.config.read_bytes());self.assertEqual(self.parent_raw,self.parent.read_bytes())


class NativeTVCheckpointInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_CHECKPOINT_MODE
    operation_attr='TV_CHECKPOINT_OPERATION'
    source=signing.TV_CHECKPOINT_SOURCE
    path=signing.TV_CHECKPOINT_PATH
    sha_attr='TV_CHECKPOINT_SHA'
    fixture_env='TV_CHECKPOINT_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_checkpoint)


class NativeTVBytesInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_BYTES_MODE
    operation_attr='TV_BYTES_OPERATION'
    source=signing.TV_BYTES_SOURCE
    path=signing.TV_BYTES_PATH
    sha_attr='TV_BYTES_SHA'
    fixture_env='TV_BYTES_SOURCE_ROOT'
    completion=staticmethod(signing.completed_tv_bytes)


class NativeTVMismatchReadback(NativeStockCustody):
    def setUp(self):
        super().setUp()
        self.store=self.home/'.anytoour-andromeda/searches';self.store.mkdir(parents=True)
        digest=hashlib.sha256(signing.TV_MISMATCH_ID.encode()).hexdigest()
        self.pending=self.store/('tourvisor-fuel-pending-v1-'+digest+'.json')
        self.receipt=self.store/('tourvisor-fuel-intake-receipt-v1-tour-'+digest+'.json')
        at=signing.TV_MISMATCH_WINDOW[0]+30
        self.tour={'id':signing.TV_MISMATCH_ID,'hotel':{'id':216},'departure':{'id':1},
            'adults':2,'childs':0,'date':'19.04.2027','nights':7,
            'price':181409,'operator':{'name':'PRIVATE SUPPLIER TEXT'},'token':'SYNTHETIC_RAW_SECRET'}
        self.p={'version':1,'tour_id':signing.TV_MISMATCH_ID,'tour':self.tour,'observed_at':at,
            'pending_expires_at':at+900,'tour_response_sha256':'a'*64}
        self.r={'schema_version':1,'source':'tourvisor-operator-fuel-passive-intake-v1','stage':'tour',
            'outcome':'retained','code':'tour_retained','observed_at':at,'expires_at':at+86400,'elapsed_ms':5,
            'offer_ref_digest':hashlib.sha256(('tourvisor:tour:'+signing.TV_MISMATCH_ID).encode()).hexdigest(),
            'normalized_response_sha256':'a'*64,'observation_count':None,'rule_confirmed':None}
        self.write(self.pending,self.p);self.write(self.receipt,self.r)
    def write(self,path,value):
        path.write_text(json.dumps(value));path.chmod(0o600)
    def inspect(self):
        operation=f'int-anex-tv-mismatch-retained-readback-native-{self.counter+1}-v1'
        with mock.patch.object(signing,'TV_MISMATCH_OPERATION',operation):
            r,out=self.execute(signing.TV_MISMATCH_MODE,operation,signing.TV_MISMATCH_SOURCE)
            command={'source_sha':signing.TV_MISMATCH_SOURCE,'mode':signing.TV_MISMATCH_MODE,'operation_id':operation}
            complete=signing.completed_tv_mismatch(command,r)
        self.assertNotIn('SYNTHETIC_RAW_SECRET',out);self.assertNotIn('PRIVATE SUPPLIER TEXT',out)
        self.assertNotIn(signing.TV_MISMATCH_ID,out);self.assertNotIn('181409',out)
        return r,complete,command
    def test_fixed_admission_historical_bytes_and_measured_read_only(self):
        body=f'/run-int-server-v1 {signing.TV_MISMATCH_SOURCE} {signing.TV_MISMATCH_MODE} {signing.TV_MISMATCH_OPERATION}'
        old=core();signing.activate(old,old.parse_command(body))
        self.assertEqual('33f56821b857630903354dcf8d7ac816ca6ec1645b2d6529804f2bb4e59814e8',hashlib.sha256(old.REMOTE.encode()).hexdigest())
        old=core();signing.activate(old,old.parse_command(f'/run-int-server-v1 {signing.TV_FIELDS_SOURCE} {signing.TV_FIELDS_MODE} {signing.TV_FIELDS_OPERATION}'))
        self.assertEqual('42bcc3854f4d96ef9c6437bdbe0c76f416473b99e66503a392f2f96ebffb81df',hashlib.sha256(old.REMOTE.encode()).hexdigest())
        old=core();signing.activate(old,old.parse_command(f'/run-int-server-v1 {signing.TV_DAY_SOURCE} {signing.TV_DAY_MODE} {signing.TV_DAY_OPERATION}'))
        self.assertEqual('c4b2d7a66ccfde5073c0697498c99362d86168a581cd8be033e6c30c3a01d20a',hashlib.sha256(old.REMOTE.encode()).hexdigest())
        for bad in (body+' /tmp/private',body.replace(signing.TV_MISMATCH_SOURCE,'0'*40),body.replace('-v1','-v2')):
            with self.assertRaises(ValueError):core().parse_command(bad)
        c=core();signing.activate(c,c.parse_command(f'/run-int-server-v1 {signing.TV_CONTEXT_SOURCE} {signing.TV_CONTEXT_MODE} {signing.TV_CONTEXT_OPERATION}'))
        self.assertEqual('82f914f7ad68348e6ea11975a56cad6650368e32f969a9d1fcf2435bdf2d99a3',hashlib.sha256(c.REMOTE.encode()).hexdigest())
        before={p:p.read_bytes() for p in (self.pending,self.receipt,self.config)}
        result,complete,cmd=self.inspect();self.assertTrue(complete)
        self.assertEqual(before,{p:p.read_bytes() for p in before})
        value=result['tv_mismatch'];self.assertEqual('measured',value['status'])
        self.assertEqual({'id':True,'hotel':True,'adults':True,'children':True,'departure':True,'date':False,'nights':True},value['matches'])
        self.assertEqual('dmy',value['date_format']);self.assertIs(True,value['calendar_match'])
        c=core()
        with mock.patch.object(signing,'TV_MISMATCH_OPERATION',cmd['operation_id']):signing.activate(c,cmd)
        script=self.home/'replay.py';script.write_text(c.REMOTE)
        replay=subprocess.run([sys.executable,str(script)],input=json.dumps(cmd|{'archive':str(self.archive),'manifest_sha256':self.manifest_hash}),
            text=True,capture_output=True,env=dict(os.environ,HOME=str(self.home)),timeout=30)
        self.assertEqual('operation_exists_no_replay',json.loads(replay.stdout)['reason'])
    def test_missing_retention_is_unavailable_never_invented(self):
        self.pending.unlink();self.r.update(outcome='skipped',code='offer_context_missing');self.write(self.receipt,self.r)
        r,ok,_=self.inspect();self.assertTrue(ok);self.assertEqual('pending_missing',r['tv_mismatch']['reason'])
        self.assertIsNone(r['tv_mismatch']['matches'])
        self.receipt.unlink();r,ok,_=self.inspect();self.assertTrue(ok);self.assertEqual('both_missing',r['tv_mismatch']['reason'])
        self.write(self.pending,self.p);r,ok,_=self.inspect();self.assertTrue(ok);self.assertEqual('receipt_missing',r['tv_mismatch']['reason'])
    def test_unsafe_files_identity_time_and_link_rejected(self):
        cases=[('symlink',None),('mode',None),('duplicate',None),('time',None),('id',None),('link',None)]
        original=self.pending.read_bytes()
        for name,_ in cases:
            with self.subTest(name=name):
                if self.pending.is_symlink():self.pending.unlink()
                self.pending.write_bytes(original);self.pending.chmod(0o600);self.write(self.receipt,self.r)
                if name=='symlink':self.pending.unlink();self.pending.symlink_to(self.config)
                if name=='mode':self.pending.chmod(0o644)
                if name=='duplicate':self.pending.write_text('{"version":1,"version":1}')
                if name=='time':self.write(self.pending,self.p|{'observed_at':signing.TV_MISMATCH_WINDOW[0]-1})
                if name=='id':self.write(self.pending,self.p|{'tour_id':'123456789'})
                if name=='link':self.write(self.receipt,self.r|{'normalized_response_sha256':'b'*64})
                result,complete,_=self.inspect();self.assertFalse(complete);self.assertNotEqual('complete',result['status'])
    def test_strict_types_and_completion_reject_forged_facts(self):
        self.p['tour']=self.tour|{'hotel':{'id':'216'},'adults':True,'childs':'0','nights':'7','date':'31.02.2027'}
        self.write(self.pending,self.p);result,ok,command=self.inspect();self.assertTrue(ok)
        value=result['tv_mismatch'];self.assertFalse(value['calendar_match']);self.assertEqual('other',value['date_format'])
        for k in ('hotel','adults','children','nights','date'):self.assertIs(False,value['matches'][k])
        with mock.patch.object(signing,'TV_MISMATCH_OPERATION',command['operation_id']):
            for change in ({'supplier_calls':False},{'runtime_writes':1},{'runtime_changed':True},{'raw':'secret'}):
                self.assertFalse(signing.completed_tv_mismatch(command,result|change))
            for change in ({'matches':dict(value['matches'],id=1)},{'response_digest_linked':False},{'code':'RAW ERROR SECRET'},{'extra':'secret'}):
                self.assertFalse(signing.completed_tv_mismatch(command,result|{'tv_mismatch':value|change}))
    def mutate_remote(self,c):
        if getattr(self,'race',False):
            anchor="    for path,expected_identity in identities.items():\n"
            self.assertEqual(1,c.REMOTE.count(anchor))
            c.REMOTE=c.REMOTE.replace(anchor,"    os.utime("+repr(str(self.pending))+",ns=(1,1))\n"+anchor,1)
    def test_snapshot_change_is_unknown_without_raw_output(self):
        self.race=True
        result,complete,_=self.inspect();self.assertFalse(complete)
        self.assertEqual('tv_mismatch_snapshot_changed',result['reason'])


class NativeTVSeatsInstall(NativeTVDiagnosticInstall):
    mode=signing.TV_SEATS_MODE
    operation_attr='TV_SEATS_OPERATION'
    source=signing.TV_SEATS_SOURCE
    path=signing.TV_SEATS_PATH
    sha_attr='TV_SEATS_SHA'
    fixture_env='TV_SEATS_SOURCE_ROOT'

    def expected_previous(self):return signing.tv_seats_previous()

    def setUp(self):
        super().setUp()
        previous=os.environ.get('TV_SESSION_FUEL_SOURCE_ROOT')
        for p,sha in self.expected_previous().items():
            raw=(Path(previous)/p).read_bytes() if previous else b'<?php // exact installed seat predecessor '+p.encode()+b'\n'
            if previous:self.assertEqual(sha,hashlib.sha256(raw).hexdigest(),p)
            target=p if p.startswith('app/') else 'api-anex-search3-preview.php'
            (self.runtime/target).write_bytes(raw);self.pins[p]=hashlib.sha256(raw).hexdigest()
            if p!=self.path:self.inventory[p]=raw
        self.previous={p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in self.pins.items()}
        self.newpins=self.pins|{self.path:hashlib.sha256(self.inventory[self.path]).hexdigest()}
        self.rebuild_archive()

    def test_seats_install_strict_completion_and_no_replay(self):
        r=self.install_maintenance();self.assertEqual('installed',r['status'])
        self.assertEqual((10,1,0),(r['install']['files'],r['install']['changed_files'],r['install']['created_files']))
        plan=json.loads(next(self.home.rglob('anex-preview-install-plan.json')).read_bytes())
        self.assertEqual([self.path],plan['changed_files'])
        self.assertFalse(r['install']['endpoint']['changed']);self.assertFalse(r['install']['dependency']['changed'])
        self.assertEqual(self.parent_raw,self.parent.read_bytes());self.assertEqual(self.config_raw,self.config.read_bytes())
        for p in self.newpins:
            target=p if p.startswith('app/') else 'api-anex-search3-preview.php'
            self.assertEqual(self.inventory[p],(self.runtime/target).read_bytes())
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.object(signing,'tv_seats_previous',return_value=self.pins),mock.patch.multiple(signing,
            TV_SEATS_OPERATION=r['operation_id'],TV_SEATS_SHA=self.newpins[self.path],TV_PUBLIC=self.public,
            TV_DEPENDENCIES=self.deps,TV_ENABLED_CANONICAL=self.parent_hash,TV_ENABLED_CONFIG_SHA=self.config_hash):
            self.assertTrue(signing.completed_tv_seats(command,r))
            for k,v in [('supplier_calls',True),('diagnostic_basis_after',{}),('tv_gateway_public_after',{}),('runtime_changed',False)]:
                self.assertFalse(signing.completed_tv_seats(command,r|{k:v}))
            for k,v in [('changed_files',2),('created_files',True),('files',6)]:
                self.assertFalse(signing.completed_tv_seats(command,r|{'install':r['install']|{k:v}}))
            self.assertFalse(signing.completed_tv_seats(command,r|{'install':r['install']|{'endpoint':r['install']['endpoint']|{'changed':True}}}))
        self.assertEqual('operation_exists_no_replay',self.install_maintenance(r['operation_id'])['reason'])

    def test_seats_any_predecessor_or_source_drift_holds(self):
        target=self.runtime/self.path;before=target.read_bytes()
        for p in self.expected_previous()|signing.TV_DEPENDENCIES:
            f=self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php');raw=f.read_bytes();f.write_bytes(b'<?php // foreign predecessor\n')
            self.assertEqual('tv_gateway_predecessor',self.install_maintenance()['reason'])
            f.write_bytes(raw);self.assertEqual(before,target.read_bytes())
        self.inventory[self.path]=b'<?php // wrong source\n';self.rebuild_archive()
        self.assertEqual('tv_gateway_source_pin',self.install_maintenance()['reason'])
        self.assertEqual(before,target.read_bytes())


class NativeAnexAPDReasonInstall(NativeTVDiagnosticInstall):
    mode=signing.ANEX_APD_REASON_MODE
    operation_attr='ANEX_APD_REASON_OPERATION'
    source=signing.ANEX_APD_REASON_SOURCE
    path=signing.ANEX_APD_REASON_PATH
    sha_attr='ANEX_APD_REASON_SHA'
    fixture_env='ANEX_APD_REASON_SOURCE_ROOT'

    def expected_previous(self):return signing.anex_apd_reason_previous()

    def setUp(self):
        super().setUp()
        previous=os.environ.get('TV_SEATS_SOURCE_ROOT')
        for p,sha in self.expected_previous().items():
            raw=(Path(previous)/p).read_bytes() if previous else b'<?php // exact installed seat predecessor '+p.encode()+b'\n'
            if previous:self.assertEqual(sha,hashlib.sha256(raw).hexdigest(),p)
            target=p if p.startswith('app/') else 'api-anex-search3-preview.php'
            (self.runtime/target).write_bytes(raw);self.pins[p]=hashlib.sha256(raw).hexdigest()
            if p!=self.path:self.inventory[p]=raw
        self.previous={p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in self.pins.items()}
        self.newpins=self.pins|{self.path:hashlib.sha256(self.inventory[self.path]).hexdigest()}
        self.rebuild_archive()

    def test_apd_reason_install_strict_completion_and_no_replay(self):
        r=self.install_maintenance();self.assertEqual('installed',r['status'])
        self.assertEqual((10,1,0),(r['install']['files'],r['install']['changed_files'],r['install']['created_files']))
        plan=json.loads(next(self.home.rglob('anex-preview-install-plan.json')).read_bytes())
        self.assertEqual(['api-anex-search3-preview.php'],plan['changed_files'])
        self.assertTrue(r['install']['endpoint']['changed']);self.assertFalse(r['install']['dependency']['changed'])
        self.assertEqual(self.parent_raw,self.parent.read_bytes());self.assertEqual(self.config_raw,self.config.read_bytes())
        for p in self.newpins:
            target=p if p.startswith('app/') else 'api-anex-search3-preview.php'
            self.assertEqual(self.inventory[p],(self.runtime/target).read_bytes())
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.object(signing,'anex_apd_reason_previous',return_value=self.pins),mock.patch.multiple(signing,
            ANEX_APD_REASON_OPERATION=r['operation_id'],ANEX_APD_REASON_SHA=self.newpins[self.path],TV_PUBLIC=self.public,
            TV_DEPENDENCIES=self.deps,TV_ENABLED_CANONICAL=self.parent_hash,TV_ENABLED_CONFIG_SHA=self.config_hash):
            self.assertTrue(signing.completed_anex_apd_reason(command,r))
            for k,v in [('supplier_calls',True),('diagnostic_basis_after',{}),('tv_gateway_public_after',{}),('runtime_changed',False)]:
                self.assertFalse(signing.completed_anex_apd_reason(command,r|{k:v}))
            for k,v in [('changed_files',2),('created_files',True),('files',6)]:
                self.assertFalse(signing.completed_anex_apd_reason(command,r|{'install':r['install']|{k:v}}))
            self.assertFalse(signing.completed_anex_apd_reason(command,r|{'install':r['install']|{'endpoint':r['install']['endpoint']|{'changed':False}}}))
        self.assertEqual('operation_exists_no_replay',self.install_maintenance(r['operation_id'])['reason'])

    def test_apd_reason_any_predecessor_or_source_drift_holds(self):
        target=self.runtime/'api-anex-search3-preview.php';before=target.read_bytes()
        for p in self.expected_previous()|signing.TV_DEPENDENCIES:
            f=self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php');raw=f.read_bytes();f.write_bytes(b'<?php // foreign predecessor\n')
            self.assertEqual('tv_gateway_predecessor',self.install_maintenance()['reason'])
            f.write_bytes(raw);self.assertEqual(before,target.read_bytes())
        self.inventory[self.path]=b'<?php // wrong source\n';self.rebuild_archive()
        self.assertEqual('tv_gateway_source_pin',self.install_maintenance()['reason'])
        self.assertEqual(before,target.read_bytes())

    def test_config_prewrite_race_holds_and_postwrite_failure_rolls_back(self):
        target=self.runtime/'api-anex-search3-preview.php';old=target.read_bytes()
        self.config_before_write=True
        self.assertEqual('tv_diagnostic_config',self.install_maintenance()['reason'])
        self.assertEqual(old,target.read_bytes())
        self.config_before_write=False;self.config.write_bytes(self.config_raw);self.fail_after_write=True
        r=self.install_maintenance();self.assertEqual('rolled_back',r['status']);self.assertEqual('complete',r['rollback']['status'])
        self.assertEqual(old,target.read_bytes());self.assertEqual(self.config_raw,self.config.read_bytes())



class OwnSessionAPDAdmission(unittest.TestCase):
    """Literal release pins are independent of the fictional native mechanism fixture."""
    def command(self):
        return {'source_sha':signing.ANEX_OWN_SESSION_APD_SOURCE,
                'mode':signing.ANEX_OWN_SESSION_APD_MODE,
                'operation_id':signing.ANEX_OWN_SESSION_APD_OPERATION}

    def test_literal_source_endpoint_and_all_twelve_runtime_pins(self):
        self.assertEqual('4e0b9707162db0c517a3e545eaf8933724f9d6a8',signing.ANEX_OWN_SESSION_APD_SOURCE)
        self.assertEqual('install-anex-own-session-apd-receipt',signing.ANEX_OWN_SESSION_APD_MODE)
        self.assertEqual('int-anex-own-session-apd-receipt-install-20261028-v1',signing.ANEX_OWN_SESSION_APD_OPERATION)
        self.assertEqual('v2/api-anex-search3-preview.php',signing.ANEX_OWN_SESSION_APD_PATH)
        self.assertEqual('c4cf2c782510048f8a40b5e0301ca0410bb60679d7779d0a72107f1f130d9436',signing.ANEX_OWN_SESSION_APD_SHA)
        expected={
            'app/integrations/operator-fuel-rule-evidence.php':'5205c275da1f71aa756aab6852664902a8ea0453a4a457673f0896737f11a6a4',
            'app/integrations/operator-fuel-rule-store.php':'a84468efc7953f50ee6fc25aaa77a40b97bd527ad9fbbe923d547dbe9db60fb9',
            'app/integrations/search3-messenger-handoff.php':'5e0fe33e6adf3ce30451b9ffe1742cc77f791114e241fb41d92236c1d70ea85c',
            'app/integrations/tourvisor-interactive-client.php':'72056e5c4642daa9515d2d5dccece0804b6e3dd9c935b6250e0e29ca2e39daf9',
            'app/integrations/tourvisor-operator-fuel-passive-intake.php':'09a40aa5c6c4b6d98d378e353707412a4a3b83631452585a3fff27c6392d7008',
            'app/integrations/tourvisor-operator-fuel-retained-intake.php':'6c233c9fc0fe64c1647941ccb22756e1cad4c17b9692998dbd1eb6b7eb04a3d0',
            'app/integrations/tourvisor-preview-gateway.php':'c3008d765b9279c7be347e9fa92861053b5dc661d7ea2dbf65450e83f488d8d1',
            'app/integrations/tourvisor-selected-tour-handoff.php':'9dafb88cab92e19376e120c80dd5dfecc975730cca7e7039edc419bf1ffa57f3',
            'v2/api-anex-search3-preview.php':'4b0f8bdeeec2170d7ff64d1ff668c717e9204fabd677927dd1e60c0f6240de08',
            'app/integrations/anex-package-quote-runtime.php':'bdc4b6737974935cf195f5db1477013ab95110108e2137aea684571bbd22af01',
            'app/integrations/anex-package-quote-handoff.php':'d3d134a43b6f19676e7d046de525fc7ac206d7781aaaaf9eee375334e237bb7d',
            'app/integrations/anex-initial-week-gate.php':'145eff807355b50bcd381c6b3d732eb5c8ac4350f2945fb77304c9fd50673ce6'}
        self.assertEqual(expected,signing.anex_own_session_apd_previous()|signing.TV_DEPENDENCIES)
        self.assertEqual(9,len(signing.anex_own_session_apd_previous()))

    def test_exact_command_and_direct_activation_reject_alias_or_caller_fields(self):
        cmd=self.command();body='/run-int-server-v1 '+' '.join(cmd.values())
        self.assertEqual(cmd,core().parse_command(body))
        for bad in (body.replace(cmd['source_sha'],'0'*40),body.replace('20261028-v1','20261028-v2'),
                    body.replace(cmd['mode'],cmd['mode']+'-alias'),body+' extra',
                    '/run-int-server-v1 '+cmd['source_sha']+' '+cmd['mode']):
            with self.subTest(body=bad),self.assertRaises(ValueError):core().parse_command(bad)
        for extra in ({'key_file':'/tmp/caller'},{'archive':'/tmp/caller'},{'operation_id':cmd['operation_id']+'x'}):
            with self.subTest(extra=extra),self.assertRaises(ValueError):signing.activate(core(),cmd|extra)

    def test_stock_remote_pins_exact_delta_and_all_maintenance_guards(self):
        c=core();signing.activate(c,self.command());tree=ast.parse(c.REMOTE)
        constants={}
        for node in tree.body:
            if isinstance(node,ast.Assign) and len(node.targets)==1 and isinstance(node.targets[0],ast.Name):
                try:constants[node.targets[0].id]=ast.literal_eval(node.value)
                except (ValueError,TypeError):pass
        previous=signing.anex_own_session_apd_previous()
        self.assertEqual(previous|{signing.ANEX_OWN_SESSION_APD_PATH:signing.ANEX_OWN_SESSION_APD_SHA},constants['TV_PINS'])
        self.assertEqual({p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in previous.items()},constants['TV_PREVIOUS'])
        self.assertEqual(signing.TV_DEPENDENCIES,constants['TV_DEPENDENCIES'])
        self.assertEqual(signing.ANEX_OWN_SESSION_APD_SOURCE,constants['TV_SOURCE'])
        self.assertEqual(signing.ANEX_OWN_SESSION_APD_MODE,constants['TV_GATEWAY_MODE'])
        self.assertEqual(10,len([p for p in constants['TV_PINS'] if p.startswith('app/integrations/')])+2)
        self.assertEqual(12,len(constants['TV_PINS']|constants['TV_DEPENDENCIES']))
        for guard in ("if changed!=['api-anex-search3-preview.php']:fail('tv_diagnostic_exact_delta')",
            "if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",
            "result['diagnostic_basis_before']=tv_diagnostic_boundary()",
            "result['diagnostic_basis_after']=tv_diagnostic_boundary()",
            "tv_gateway_preflight()", "anex_preview_install_source_lint",
            "anex_preview_install_backup_hash", "anex_preview_install_readback",
            "result['production_before']", "result['production_after']"):
            self.assertIn(guard,c.REMOTE)
        self.assertNotIn('sudo',c.REMOTE)
        self.assertNotIn('if changed!=[\'v2/api-anex-search3-preview.php\']',c.REMOTE)
        self.assertLess(len(base64.b64encode(__import__('zlib').compress(c.REMOTE.encode(),9))),64000)
        self.assertEqual(signing.ANEX_OWN_SESSION_APD_MODE,c.parse_command('/run-int-server-v1 '+' '.join(self.command().values()))['mode'])

    def receipt(self):
        cmd=self.command();source=cmd['source_sha'];dep='app/integrations/anex-initial-week-gate.php'
        proof={'source_sha':source,'files':signing.anex_own_session_apd_previous()|{signing.ANEX_OWN_SESSION_APD_PATH:signing.ANEX_OWN_SESSION_APD_SHA},
            'activation_performed':False,'configuration_changed':False,'signing_custody_changed':False}
        basis={'parent_operation':signing.TV_RECOVERY_OPERATION,'parent_canonical_sha256':signing.TV_ENABLED_CANONICAL,
            'configuration_sha256':signing.TV_ENABLED_CONFIG_SHA,'configuration_mode':0o644,'receipt_and_configuration_unchanged':True}
        public={p:v.get('sha256') for p,v in signing.TV_PUBLIC.items() if p!='api-v2.php'}
        return cmd|{'schema_version':1,'status':'installed',
            'install':{'status':'installed','source_sha':source,'manifest_sha256':'a'*64,'files':10,'changed_files':1,'created_files':0,
                'endpoint':{'source':signing.ANEX_OWN_SESSION_APD_PATH,'target':'api-anex-search3-preview.php','sha256':signing.ANEX_OWN_SESSION_APD_SHA,'changed':True},
                'dependency':{'source':dep,'target':dep,'sha256':signing.TV_DEPENDENCIES[dep],'changed':False}},
            'tv_gateway':proof,'diagnostic_basis_before':basis,'diagnostic_basis_after':basis,
            'tv_gateway_public_before':signing.TV_PUBLIC,'tv_gateway_public_after':signing.TV_PUBLIC,
            'production_before':public,'production_after':public,'supplier_calls':0,'database_writes':0,
            'booking_calls':0,'lead_calls':0,'public_ui_entrypoints_unchanged':True,'runtime_changed':True}

    def test_completion_strict_types_delta_proofs_zero_calls_and_dispatch(self):
        cmd=self.command();r=self.receipt();c=core();signing.activate(c,cmd)
        self.assertTrue(signing.completed_anex_own_session_apd(cmd,r));self.assertTrue(c.completed_result(cmd,r))
        for k,v in [('schema_version',True),('supplier_calls',False),('database_writes',1),('booking_calls',True),
                    ('lead_calls',1),('runtime_changed',False),('public_ui_entrypoints_unchanged',1),
                    ('diagnostic_basis_after',{}),('tv_gateway_public_after',{}),('production_after',{})]:
            with self.subTest(key=k):self.assertFalse(c.completed_result(cmd,r|{k:v}))
        for k,v in [('files',True),('files',9),('changed_files',2),('changed_files',True),
                    ('created_files',False),('created_files',1),('manifest_sha256','f'*63)]:
            with self.subTest(install=k):self.assertFalse(c.completed_result(cmd,r|{'install':r['install']|{k:v}}))
        for key,field,values in [('endpoint','changed',(False,1)),('endpoint','sha256',('0'*64,)),
                                 ('dependency','changed',(True,0))]:
            for value in values:
                with self.subTest(key=key,value=value):
                    bad=r|{'install':r['install']|{key:r['install'][key]|{field:value}}}
                    self.assertFalse(c.completed_result(cmd,bad))
        for key,field,value in [('tv_gateway','activation_performed',0),
                ('diagnostic_basis_after','receipt_and_configuration_unchanged',1)]:
            self.assertFalse(c.completed_result(cmd,r|{key:r[key]|{field:value}}))
        for key in ('tv_gateway','diagnostic_basis_before','diagnostic_basis_after'):
            for value in (None,[], 'malformed'):
                with self.subTest(key=key,shape=type(value).__name__):
                    self.assertFalse(c.completed_result(cmd,r|{key:value}))
        self.assertFalse(c.completed_result(cmd|{'operation_id':cmd['operation_id']+'x'},r))

    def test_historical_apd_reason_render_stays_byte_identical(self):
        c=core();cmd=c.parse_command(f'/run-int-server-v1 {signing.ANEX_APD_REASON_SOURCE} {signing.ANEX_APD_REASON_MODE} {signing.ANEX_APD_REASON_OPERATION}')
        signing.activate(c,cmd)
        self.assertEqual('f367c3084c5c46c28898cc9b9909125b2a3ef33d4cfb15b315faaee9cd5913ee',hashlib.sha256(c.REMOTE.encode()).hexdigest())

    def test_new_native_cases_are_registered_in_existing_harness(self):
        suite=load_tests(unittest.TestLoader(),unittest.TestSuite(),None)
        def flatten(s):
            for item in s:
                if isinstance(item,unittest.TestSuite):yield from flatten(item)
                else:yield item
        cases=[c for c in flatten(suite) if type(c) is NativeOwnSessionAPDInstall]
        self.assertEqual(3,len(cases))


class NativeOwnSessionAPDInstall(NativeTVDiagnosticInstall):
    mode=signing.ANEX_OWN_SESSION_APD_MODE
    operation_attr='ANEX_OWN_SESSION_APD_OPERATION'
    source=signing.ANEX_OWN_SESSION_APD_SOURCE
    path=signing.ANEX_OWN_SESSION_APD_PATH
    sha_attr='ANEX_OWN_SESSION_APD_SHA'
    fixture_env='ANEX_APD_REASON_SOURCE_ROOT'

    def expected_previous(self):return signing.anex_own_session_apd_previous()

    def setUp(self):
        # Explicitly fictional mechanism fixture. The existing CI env points at the
        # old API source; it must not be treated as the new reviewed source.
        # This scope restores the environment before any existing class runs.
        with mock.patch.dict(os.environ):
            os.environ.pop(self.fixture_env,None)
            NativeTVDiagnosticInstall.setUp(self)
        for p in self.expected_previous():
            raw=b'<?php // fictional own-session predecessor '+p.encode()+b'\n'
            target=p if p.startswith('app/') else 'api-anex-search3-preview.php'
            (self.runtime/target).write_bytes(raw);self.pins[p]=hashlib.sha256(raw).hexdigest()
            if p!=self.path:self.inventory[p]=raw
        self.previous={p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in self.pins.items()}
        self.newpins=self.pins|{self.path:hashlib.sha256(self.inventory[self.path]).hexdigest()}
        self.rebuild_archive()

    def install_maintenance(self,operation=None):
        operation=operation or f'int-anex-own-session-apd-receipt-install-native-{self.counter+1}-v1'
        with mock.patch.object(signing,self.operation_attr,operation):
            return self.execute(self.mode,operation,self.source)[0]

    def test_own_session_apd_install_strict_completion_and_no_replay(self):
        r=self.install_maintenance();self.assertEqual('installed',r['status'])
        self.assertEqual((10,1,0),(r['install']['files'],r['install']['changed_files'],r['install']['created_files']))
        plan=json.loads(next(self.home.rglob('anex-preview-install-plan.json')).read_bytes())
        self.assertEqual(['api-anex-search3-preview.php'],plan['changed_files'])
        self.assertTrue(r['install']['endpoint']['changed']);self.assertFalse(r['install']['dependency']['changed'])
        self.assertEqual(self.parent_raw,self.parent.read_bytes());self.assertEqual(self.config_raw,self.config.read_bytes())
        for p in self.newpins:
            target=p if p.startswith('app/') else 'api-anex-search3-preview.php'
            self.assertEqual(self.inventory[p],(self.runtime/target).read_bytes())
        command={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.object(signing,'anex_own_session_apd_previous',return_value=self.pins),mock.patch.multiple(signing,
            ANEX_OWN_SESSION_APD_OPERATION=r['operation_id'],ANEX_OWN_SESSION_APD_SHA=self.newpins[self.path],TV_PUBLIC=self.public,
            TV_DEPENDENCIES=self.deps,TV_ENABLED_CANONICAL=self.parent_hash,TV_ENABLED_CONFIG_SHA=self.config_hash):
            self.assertTrue(signing.completed_anex_own_session_apd(command,r))
            for k,v in [('supplier_calls',True),('diagnostic_basis_after',{}),('tv_gateway_public_after',{}),('runtime_changed',False)]:
                self.assertFalse(signing.completed_anex_own_session_apd(command,r|{k:v}))
            for k,v in [('changed_files',2),('created_files',True),('files',6)]:
                self.assertFalse(signing.completed_anex_own_session_apd(command,r|{'install':r['install']|{k:v}}))
            self.assertFalse(signing.completed_anex_own_session_apd(command,r|{'install':r['install']|{'endpoint':r['install']['endpoint']|{'changed':False}}}))
        self.assertEqual('operation_exists_no_replay',self.install_maintenance(r['operation_id'])['reason'])

    def test_own_session_apd_any_predecessor_or_source_drift_holds(self):
        target=self.runtime/'api-anex-search3-preview.php';before=target.read_bytes()
        for p in self.expected_previous()|signing.TV_DEPENDENCIES:
            f=self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php');raw=f.read_bytes();f.write_bytes(b'<?php // foreign predecessor\n')
            self.assertEqual('tv_gateway_predecessor',self.install_maintenance()['reason'])
            f.write_bytes(raw);self.assertEqual(before,target.read_bytes())
        self.inventory[self.path]=b'<?php // wrong source\n';self.rebuild_archive()
        self.assertEqual('tv_gateway_source_pin',self.install_maintenance()['reason'])
        self.assertEqual(before,target.read_bytes())

    def test_config_prewrite_race_holds_and_postwrite_failure_rolls_back(self):
        target=self.runtime/'api-anex-search3-preview.php';old=target.read_bytes()
        self.config_before_write=True
        self.assertEqual('tv_diagnostic_config',self.install_maintenance()['reason'])
        self.assertEqual(old,target.read_bytes())
        self.config_before_write=False;self.config.write_bytes(self.config_raw);self.fail_after_write=True
        r=self.install_maintenance();self.assertEqual('rolled_back',r['status']);self.assertEqual('complete',r['rollback']['status'])
        self.assertEqual(old,target.read_bytes());self.assertEqual(self.config_raw,self.config.read_bytes())



class APDReadFactsAdmission(unittest.TestCase):
    def command(self):
        return {'source_sha':signing.ANEX_APD_READ_FACTS_SOURCE,'mode':signing.ANEX_APD_READ_FACTS_MODE,
                'operation_id':signing.ANEX_APD_READ_FACTS_OPERATION}

    def test_fixed_admission_and_exact_remote(self):
        cmd=self.command();body='/run-int-server-v1 '+' '.join(cmd.values())
        self.assertEqual(cmd,core().parse_command(body))
        for bad in (body+' extra',body.replace(cmd['source_sha'],'0'*40),body.replace('20261029-v1','20261029-v2'),
                    body.replace(cmd['mode'],cmd['mode']+'-alias')):
            with self.subTest(body=bad),self.assertRaises(ValueError):core().parse_command(bad)
        with self.assertRaises(ValueError):signing.activate(core(),cmd|{'path':'/tmp/caller'})
        c=core();signing.activate(c,cmd);ast.parse(c.REMOTE)
        self.assertIn("if changed!=['app/integrations/anex-additional-prices-client.php', 'api-anex-search3-preview.php']:fail('tv_diagnostic_exact_delta')",c.REMOTE)
        self.assertIn("complete['apd_client']",c.REMOTE)
        self.assertIn("operation_exists_no_replay",c.REMOTE)
        self.assertNotIn('sudo',c.REMOTE)
        self.assertLess(len(base64.b64encode(__import__('zlib').compress(c.REMOTE.encode(),9))),64000)
        self.assertEqual(10,len(signing.anex_apd_read_facts_previous()))
        self.assertEqual('b13657f206961c2a3e88ef5515ba2bb5739230483d35102496b479656bb4db8e',
                         signing.anex_apd_read_facts_previous()[signing.ANEX_APD_READ_FACTS_CLIENT_PATH])

    def receipt(self):
        r=OwnSessionAPDAdmission().receipt();cmd=self.command();r.update(cmd)
        r['install'].update(source_sha=cmd['source_sha'],files=11,changed_files=2)
        r['install']['endpoint']['sha256']=signing.ANEX_APD_READ_FACTS_API_SHA
        client=signing.ANEX_APD_READ_FACTS_CLIENT_PATH
        r['install']['apd_client']={'source':client,'target':client,'sha256':signing.ANEX_APD_READ_FACTS_UPDATES[client],'changed':True}
        r['tv_gateway']=r['tv_gateway']|{'source_sha':cmd['source_sha'],
            'files':signing.anex_apd_read_facts_previous()|signing.ANEX_APD_READ_FACTS_UPDATES}
        return r

    def test_completion_exact_pair_and_strict_boundary_facts(self):
        cmd=self.command();r=self.receipt();c=core();signing.activate(c,cmd)
        self.assertTrue(c.completed_result(cmd,r))
        for k,v in [('files',10),('changed_files',1),('changed_files',True),('created_files',False),('created_files',1)]:
            self.assertFalse(c.completed_result(cmd,r|{'install':r['install']|{k:v}}))
        for k,v in [('source','v2/client.php'),('target','api-anex-search3-preview.php'),('sha256','0'*64),('changed',False),('changed',1)]:
            bad=r|{'install':r['install']|{'apd_client':r['install']['apd_client']|{k:v}}}
            self.assertFalse(c.completed_result(cmd,bad))
        for k,v in [('supplier_calls',True),('database_writes',1),('booking_calls',True),('lead_calls',1),
                    ('diagnostic_basis_after',{}),('tv_gateway_public_after',{}),('production_after',{}),('runtime_changed',False)]:
            self.assertFalse(c.completed_result(cmd,r|{k:v}))
        self.assertFalse(c.completed_result(cmd,r|{'install':r['install']|{'endpoint':r['install']['endpoint']|{'changed':1}}}))
        self.assertFalse(c.completed_result(cmd,r|{'install':r['install']|{'dependency':r['install']['dependency']|{'changed':0}}}))

    def test_only_three_new_native_cases_are_registered(self):
        def flatten(suite):
            for case in suite:
                if isinstance(case,unittest.TestSuite):yield from flatten(case)
                else:yield case
        suite=load_tests(unittest.TestLoader(),unittest.TestSuite(),None)
        self.assertEqual(3,len([case for case in flatten(suite) if type(case) is NativeAPDReadFactsInstall]))

    def test_historical_own_session_program_unchanged(self):
        c=core();signing.activate(c,OwnSessionAPDAdmission().command())
        self.assertEqual('8f9680fc236eb03dffd2370e8648a70c7942ce83c20baa75ccc64da4cfab763f',hashlib.sha256(c.REMOTE.encode()).hexdigest())


class NativeAPDReadFactsInstall(NativeOwnSessionAPDInstall):
    mode=signing.ANEX_APD_READ_FACTS_MODE
    operation_attr='ANEX_APD_READ_FACTS_OPERATION'
    source=signing.ANEX_APD_READ_FACTS_SOURCE
    sha_attr='ANEX_APD_READ_FACTS_API_SHA'

    def expected_previous(self):return signing.anex_apd_read_facts_previous()
    def expected_target(self):return self.expected_previous()|signing.ANEX_APD_READ_FACTS_UPDATES

    def setUp(self):
        super().setUp()
        client=signing.ANEX_APD_READ_FACTS_CLIENT_PATH
        self.inventory[client]=b'<?php // fictional new APD read diagnostic client\n'
        self.newpins=self.pins|{self.path:hashlib.sha256(self.inventory[self.path]).hexdigest(),
            client:hashlib.sha256(self.inventory[client]).hexdigest()}
        self.rebuild_archive()

    def install_maintenance(self,operation=None):
        operation=operation or f'int-anex-apd-read-facts-install-native-{self.counter+1}-v1'
        with mock.patch.object(signing,self.operation_attr,operation):
            return self.execute(self.mode,operation,self.source)[0]

    def test_pair_install_strict_receipt_and_no_replay(self):
        r=self.install_maintenance();self.assertEqual('installed',r['status'])
        self.assertEqual((11,2,0),(r['install']['files'],r['install']['changed_files'],r['install']['created_files']))
        plan=json.loads(next(self.home.rglob('anex-preview-install-plan.json')).read_bytes())
        client=signing.ANEX_APD_READ_FACTS_CLIENT_PATH
        self.assertEqual([client,'api-anex-search3-preview.php'],plan['changed_files'])
        self.assertEqual(self.inventory[client],(self.runtime/client).read_bytes())
        self.assertEqual(self.inventory[self.path],(self.runtime/'api-anex-search3-preview.php').read_bytes())
        self.assertEqual(self.config_raw,self.config.read_bytes());self.assertEqual(self.parent_raw,self.parent.read_bytes())
        cmd={k:r[k] for k in ('source_sha','mode','operation_id')}
        with mock.patch.object(signing,'anex_apd_read_facts_previous',return_value=self.pins),mock.patch.multiple(signing,
            ANEX_APD_READ_FACTS_OPERATION=r['operation_id'],
            ANEX_APD_READ_FACTS_UPDATES={client:self.newpins[client],self.path:self.newpins[self.path]},
            TV_PUBLIC=self.public,TV_DEPENDENCIES=self.deps,TV_ENABLED_CANONICAL=self.parent_hash,TV_ENABLED_CONFIG_SHA=self.config_hash):
            self.assertTrue(signing.completed_anex_apd_read_facts(cmd,r))
        self.assertEqual('operation_exists_no_replay',self.install_maintenance(r['operation_id'])['reason'])

    def test_any_predecessor_dependency_or_source_drift_holds(self):
        oldapi=(self.runtime/'api-anex-search3-preview.php').read_bytes()
        oldclient=(self.runtime/signing.ANEX_APD_READ_FACTS_CLIENT_PATH).read_bytes()
        for p in self.expected_previous()|signing.TV_DEPENDENCIES:
            f=self.runtime/(p if p.startswith('app/') else 'api-anex-search3-preview.php');raw=f.read_bytes();f.write_bytes(b'<?php // drift\n')
            self.assertEqual('tv_gateway_predecessor',self.install_maintenance()['reason']);f.write_bytes(raw)
        for p in signing.ANEX_APD_READ_FACTS_UPDATES:
            raw=self.inventory[p];self.inventory[p]=b'<?php // wrong source\n';self.rebuild_archive()
            self.assertEqual('tv_gateway_source_pin',self.install_maintenance()['reason']);self.inventory[p]=raw
        self.assertEqual(oldapi,(self.runtime/'api-anex-search3-preview.php').read_bytes())
        self.assertEqual(oldclient,(self.runtime/signing.ANEX_APD_READ_FACTS_CLIENT_PATH).read_bytes())

    def test_prewrite_race_and_partial_or_full_postwrite_rollback(self):
        targets=[self.runtime/signing.ANEX_APD_READ_FACTS_CLIENT_PATH,self.runtime/'api-anex-search3-preview.php']
        before=[p.read_bytes() for p in targets]
        self.config_before_write=True
        self.assertEqual('tv_diagnostic_config',self.install_maintenance()['reason'])
        self.assertEqual(before,[p.read_bytes() for p in targets])
        self.config_before_write=False;self.config.write_bytes(self.config_raw)
        self.fail_after_write=True
        r=self.install_maintenance();self.assertEqual('rolled_back',r['status']);self.assertEqual('complete',r['rollback']['status'])
        self.assertEqual(before,[p.read_bytes() for p in targets])
        self.fail_after_write=False
        original=self.mutate_remote
        def partial(c):
            original(c)
            anchor="        install_applied.append(target_relative)\n"
            begin=c.REMOTE.index('def install_anex_preview(stage,files,op):\n')
            end=c.REMOTE.index('def install_andromeda_preview(stage,files,op):\n',begin)
            block=c.REMOTE[begin:end];self.assertEqual(1,block.count(anchor))
            c.REMOTE=c.REMOTE[:begin]+block.replace(anchor,anchor+"        fail('synthetic_partial_pair_failure')\n",1)+c.REMOTE[end:]
        self.mutate_remote=partial
        r=self.install_maintenance();self.assertEqual('rolled_back',r['status']);self.assertEqual('complete',r['rollback']['status'])
        self.assertEqual(before,[p.read_bytes() for p in targets])


def load_tests(loader, tests, pattern):
    suite = unittest.TestSuite()
    for case in (Admission, NativeStockCustody): suite.addTests(loader.loadTestsFromTestCase(case))
    for name in ('test_fixed_admission_historical_bytes_and_measured_read_only','test_missing_retention_is_unavailable_never_invented',
                 'test_unsafe_files_identity_time_and_link_rejected','test_strict_types_and_completion_reject_forged_facts',
                 'test_snapshot_change_is_unknown_without_raw_output'):
        suite.addTest(NativeTVMismatchReadback(name))
    for name in ('test_install_narrow_dependency_first_and_endpoint_last',
                 'test_install_source_pin_and_existing_dependency_drift',
                 'test_install_postwrite_failure_restores_and_removes'):
        suite.addTest(NativeStockProducerInstall(name))
    for name in ('test_exact_install_endpoint_last_no_activation_and_completion',
                 'test_source_dependency_and_expected_absent_guards','test_symlink_public_is_not_absence',
                 'test_prewrite_rechecks_predecessor','test_postwrite_failure_rolls_back_and_removes_created'):
        suite.addTest(NativeTVGatewayInstall(name))
    for name in ('test_enable_preserves_bytes_tail_key_and_strict_completion','test_bad_source_parent_and_runtime_refuse_before_config',
                 'test_unknown_config_or_existing_flag_denied_without_execution','test_prewrite_config_race_not_overwritten',
                 'test_http_failure_rolls_back_exact_config','test_fixed_http_and_parent_contract',
                 'test_config_candidate_budget_is_checked_before_backup','test_own_http_response_validation_without_network',
                 'test_parser_environment_and_untrusted_output_only_fixed_codes','test_native_missing_tokenizer_has_own_safe_classification',
                 'test_actual_publisher_stub_old_reject_fixed_preserves_bytes_without_following_include',
                 'test_only_exact_first_publisher_stub_is_recognized'):
        suite.addTest(NativeTVGatewayEnable(name))
    for name in ('test_first_write_success_preserves_original_unknown_and_strict_receipt',
                 'test_any_original_plan_backup_or_temp_holds_without_config_write',
                 'test_original_receipt_or_reservation_mismatch_holds','test_current_outer_stub_and_flag_absence_required',
                 'test_original_identity_drift_before_write_holds','test_readiness_failure_rolls_back_only_new_flag'):
        suite.addTest(NativeTVGatewayRecovery(name))
    for name in ('test_one_helper_update_preserves_every_other_boundary_and_no_replay',
                 'test_bad_parent_config_dependency_and_source_hold_before_write',
                 'test_config_prewrite_race_holds_and_postwrite_failure_rolls_back'):
        suite.addTest(NativeTVDiagnosticInstall(name))
        suite.addTest(NativeTVCacheInstall(name))
        suite.addTest(NativeTVContextInstall(name))
        suite.addTest(NativeTVFieldsInstall(name))
        suite.addTest(NativeTVDayInstall(name))
        suite.addTest(NativeTVLimitInstall(name))
        suite.addTest(NativeTVCapInstall(name))
        suite.addTest(NativeTVBytesInstall(name))
        suite.addTest(NativeTVCheckpointInstall(name))
        suite.addTest(NativeTVNumericInstall(name))
    suite.addTest(NativeTVNumericInstall('test_unchanged_gateway_drift_holds_before_client_write'))
    for name in ('test_pair_install_strict_completion_and_no_replay','test_either_target_or_passive_dependency_drift_holds',
                 'test_pair_prewrite_race_and_postwrite_rollback'):
        suite.addTest(NativeTVSessionFuelInstall(name))
    for name in ('test_pair_install_strict_completion_and_no_replay','test_either_predecessor_or_source_drift_holds',
                 'test_pair_prewrite_race_and_postwrite_rollback'):
        suite.addTest(NativeTVPartyInstall(name))
    for name in ('test_seats_install_strict_completion_and_no_replay','test_seats_any_predecessor_or_source_drift_holds',
                 'test_config_prewrite_race_holds_and_postwrite_failure_rolls_back'):
        suite.addTest(NativeTVSeatsInstall(name))
    for name in ('test_apd_reason_install_strict_completion_and_no_replay','test_apd_reason_any_predecessor_or_source_drift_holds',
                 'test_config_prewrite_race_holds_and_postwrite_failure_rolls_back'):
        suite.addTest(NativeAnexAPDReasonInstall(name))
    suite.addTests(loader.loadTestsFromTestCase(APDReadFactsAdmission))
    for name in ('test_pair_install_strict_receipt_and_no_replay',
                 'test_any_predecessor_dependency_or_source_drift_holds',
                 'test_prewrite_race_and_partial_or_full_postwrite_rollback'):
        suite.addTest(NativeAPDReadFactsInstall(name))
    suite.addTests(loader.loadTestsFromTestCase(OwnSessionAPDAdmission))
    for name in ('test_own_session_apd_install_strict_completion_and_no_replay',
                 'test_own_session_apd_any_predecessor_or_source_drift_holds',
                 'test_config_prewrite_race_holds_and_postwrite_failure_rolls_back'):
        suite.addTest(NativeOwnSessionAPDInstall(name))
    return suite


if __name__ == '__main__':
    unittest.main()
