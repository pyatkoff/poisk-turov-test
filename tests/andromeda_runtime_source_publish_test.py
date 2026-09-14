#!/usr/bin/env python3
import base64
import fcntl
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publisher', root / 'scripts/diagnostics/andromeda_runtime_source_publish.py')
publisher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(publisher)
handoff = Path(os.environ['ANDROMEDA_RUNTIME_HANDOFF'])
source = os.environ['RUNTIME_SOURCE_SHA']
wire, hashes = publisher.load_handoff(handoff, source)
files = {path: base64.b64decode(value) for path, value in wire.items()}


class RuntimePublisherTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.home = Path(self.temp.name)
        self.project = self.home / 'www/anytoour.ru'
        self.target = self.project / '_preview/search3-anex-candidate'
        (self.target / 'app/integrations').mkdir(parents=True)
        self.private = self.home / '.anytoour-andromeda'
        self.private.mkdir()
        self.release = self.private / ('runtime-surcharge-' + source)
        self.previous = {}
        for path in publisher.TARGET_PATHS:
            target = self.target / path
            target.parent.mkdir(parents=True, exist_ok=True)
            data = ('<?php /* previous ' + path + ' */').encode()
            target.write_bytes(data); target.chmod(0o644); self.previous[path] = data
        for path in publisher.SUPPORT_PATHS:
            target = self.target / path
            if not target.exists():
                target.write_bytes(('<?php /* support ' + path + ' */').encode()); target.chmod(0o644)
        self.sentinel = self.project / 'index.php'
        self.sentinel.write_bytes(b'PROTECTED')

    def tearDown(self):
        self.assertEqual(b'PROTECTED', self.sentinel.read_bytes())
        self.temp.cleanup()

    def run_remote(self, payload=None):
        payload = payload or {'source': source, 'files': wire, 'sha256': hashes}
        run = subprocess.run(['php','-d','allow_url_fopen=0','-r',publisher.remote_source()], cwd=self.project,
            input=json.dumps(payload).encode(), stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
        self.assertEqual(0, run.returncode, run.stderr.decode())
        self.assertEqual(b'', run.stderr)
        return json.loads(run.stdout)

    def test_publish_backup_api_last_and_noop(self):
        result = self.run_remote()
        self.assertEqual('published', result['status'])
        self.assertEqual(publisher.TARGET_PATHS, result['changed_paths'])
        self.assertEqual('api-andromeda-search3-preview.php', result['changed_paths'][-1])
        self.assertTrue(result['search_surcharge_source_installed'])
        self.assertFalse(result['live_surcharge_capture_enabled'])
        self.assertEqual(0, result['supplier_calls'] + result['database_writes'] + result['booking_calls'])
        for path, data in files.items():
            self.assertEqual(data, (self.target / path).read_bytes())
        for index, path in enumerate(publisher.TARGET_PATHS):
            self.assertEqual(self.previous[path], (self.release / 'backup' / f'{index}.php').read_bytes())
        inodes = {p:(self.target/p).stat().st_ino for p in publisher.TARGET_PATHS}
        self.assertEqual('already_published', self.run_remote()['status'])
        self.assertEqual(inodes, {p:(self.target/p).stat().st_ino for p in publisher.TARGET_PATHS})

    def test_bad_candidate_refused_before_install(self):
        bad_wire = dict(wire); bad_wire[publisher.TARGET_PATHS[0]] = base64.b64encode(b'bad').decode()
        result = self.run_remote({'source':source,'files':bad_wire,'sha256':hashes})
        self.assertEqual('candidate_hash', result['reason'])
        self.assertFalse(self.release.exists())
        for path, data in self.previous.items(): self.assertEqual(data, (self.target/path).read_bytes())

    def test_support_missing_refused(self):
        (self.target / publisher.SUPPORT_PATHS[0]).unlink()
        result = self.run_remote()
        self.assertEqual('support_changed', result['reason'])
        self.assertFalse(self.release.exists())

    def test_lock_not_stolen(self):
        with (self.private / 'grouped-search-update.lock').open('w') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            result = self.run_remote()
        self.assertEqual('lock_busy', result['reason'])
        self.assertFalse(self.release.exists())

    def test_unknown_reservation_is_no_replay(self):
        self.release.mkdir(mode=0o700)
        (self.release / 'reservation.json').write_text('{"status":"unknown"}')
        result = self.run_remote()
        self.assertEqual('previous_outcome_unknown', result['reason'])
        for path, data in self.previous.items(): self.assertEqual(data, (self.target/path).read_bytes())

    def test_handoff_inventory_and_receipt_are_pinned(self):
        self.assertEqual((wire, hashes), publisher.load_handoff(handoff, source))
        with tempfile.TemporaryDirectory() as tmp:
            clone = Path(tmp) / 'handoff'
            import shutil; shutil.copytree(handoff, clone)
            (clone / 'unexpected').write_text('x')
            with self.assertRaisesRegex(ValueError, 'handoff_inventory'):
                publisher.load_handoff(clone, source)

    def test_publisher_limit_is_accepted_by_pinned_helper(self):
        helper_path = Path(os.environ['ANDROMEDA_PUBLISH_HELPER']).resolve()
        sys.path.insert(0, str(helper_path.parent))
        try:
            helper_spec = importlib.util.spec_from_file_location('pinned_publish_helper', helper_path)
            helper = importlib.util.module_from_spec(helper_spec)
            helper_spec.loader.exec_module(helper)
            names = ('ANYTOOUR_DEPLOY_SSH_KEY', 'ANYTOOUR_DEPLOY_HOST', 'ANYTOOUR_DEPLOY_USER')
            saved = {name: os.environ.pop(name, None) for name in names}
            try:
                with self.assertRaisesRegex(ValueError, 'missing SSH configuration'):
                    helper.ssh_php('', {}, maximum_bytes=publisher.SSH_RESPONSE_LIMIT)
                with self.assertRaisesRegex(ValueError, 'unsupported diagnostic response limit'):
                    helper.ssh_php('', {}, maximum_bytes=131072)
            finally:
                for name, value in saved.items():
                    if value is not None:
                        os.environ[name] = value
        finally:
            sys.path.pop(0)


if __name__ == '__main__':
    unittest.main(verbosity=2)
