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
    def test_exact_modes_and_no_arbitrary_arguments(self):
        c = core()
        for mode in signing.MODES:
            body = f'/run-int-server-v1 {SHA} {mode} int-anex-search3-signing-case-v1'
            parsed = c.parse_command(body)
            self.assertEqual({'source_sha': SHA, 'mode': mode, 'operation_id': 'int-anex-search3-signing-case-v1'}, parsed)
            for invalid in [body + ' /tmp/key', body.replace(SHA, 'x' * 40),
                            body.replace('int-anex-search3-signing-', 'int-andromeda-'),
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
        self.assertEqual(2, c.REMOTE.count("    if mode not in ('search3-signing-provision','search3-signing-readback','reconcile',"))
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
        hashes = {p: hashlib.sha256(raw).hexdigest() for p, raw in self.inventory.items()}
        self.manifest_hash = hashlib.sha256(json.dumps(hashes, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
        self.archive = self.home / 'source.tar.gz'
        with tarfile.open(self.archive, 'w:gz') as tar:
            files = self.inventory | {'manifest.json': json.dumps({'schema_version': 1, 'files': hashes}).encode()}
            for name, raw in files.items():
                info = tarfile.TarInfo(name); info.size = len(raw); info.mode = 0o600
                tar.addfile(info, io.BytesIO(raw))
        self.c = core(); self.counter = 0

    def tearDown(self):
        self.temporary.cleanup()

    def execute(self, mode='search3-signing-provision', operation=None):
        self.counter += 1
        operation = operation or f'int-anex-search3-signing-native-{self.counter}-v1'
        c = core()
        command = c.parse_command(f'/run-int-server-v1 {SHA} {mode} {operation}')
        signing.activate(c, command)
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


if __name__ == '__main__':
    unittest.main()
