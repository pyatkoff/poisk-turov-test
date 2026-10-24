#!/usr/bin/env python3
"""Native synthetic custody and stock executor gates; no server/provider access."""
import base64
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
    def test_exact_modes_and_no_arbitrary_arguments(self):
        c = core()
        for mode in signing.MODES:
            prefix = ('int-anex-tv-session-gateway-install-' if mode == signing.TV_MODE else
                      'int-anex-search3-producer-install-' if mode == 'install-search3-producer' else 'int-anex-search3-signing-')
            operation = prefix + 'case-v1'
            body = f'/run-int-server-v1 {SHA} {mode} {operation}'
            parsed = c.parse_command(body)
            self.assertEqual({'source_sha': SHA, 'mode': mode, 'operation_id': operation}, parsed)
            for invalid in [body + ' /tmp/key', body.replace(SHA, 'x' * 40),
                            body.replace(prefix, 'int-andromeda-'),
                            body.replace('case-v1', 'case'), body.replace('/run-int-server-v1', '/other')]:
                with self.subTest(mode=mode, invalid=invalid), self.assertRaises(ValueError):
                    c.parse_command(invalid)
        self.assertEqual('install-runtime', c.parse_command(f'/run-int-server-v1 {SHA} install-runtime int-anex-existing-install-v1')['mode'])

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
        for kind in ('source','dependency','exists'):
            with self.subTest(kind=kind):
                if kind=='source':self.pins['app/integrations/tourvisor-preview-gateway.php']='0'*64
                else:
                    p='app/integrations/'+('anex-initial-week-gate.php' if kind=='dependency' else 'tourvisor-preview-gateway.php')
                    (self.runtime/p).write_bytes(b'<?php // foreign\n')
                r=self.install();self.assertEqual({'source':'tv_gateway_source_pin','dependency':'tv_gateway_predecessor',
                                                  'exists':'tv_gateway_expected_absent'}[kind],r['reason']);self.assert_old()
                self.pins={p:hashlib.sha256(self.inventory[p]).hexdigest() for p in signing.TV_PINS}
                if kind=='dependency':(self.runtime/p).write_bytes(self.inventory[p])
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


def load_tests(loader, tests, pattern):
    suite = unittest.TestSuite()
    for case in (Admission, NativeStockCustody): suite.addTests(loader.loadTestsFromTestCase(case))
    for name in ('test_install_narrow_dependency_first_and_endpoint_last',
                 'test_install_source_pin_and_existing_dependency_drift',
                 'test_install_postwrite_failure_restores_and_removes'):
        suite.addTest(NativeStockProducerInstall(name))
    for name in ('test_exact_install_endpoint_last_no_activation_and_completion',
                 'test_source_dependency_and_expected_absent_guards','test_symlink_public_is_not_absence',
                 'test_prewrite_rechecks_predecessor','test_postwrite_failure_rolls_back_and_removes_created'):
        suite.addTest(NativeTVGatewayInstall(name))
    return suite


if __name__ == '__main__':
    unittest.main()
