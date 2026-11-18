"""Offline transport tests. No SSH, SCP, PHP or admission code is executed."""
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import types
import unittest
from unittest.mock import patch

SOURCE = Path(__file__).with_name('run.py')
APP = 'a' * 64
CANDIDATE = b'<?php /* offline candidate fixture, never executed */'
CANDIDATE_SHA = hashlib.sha256(CANDIDATE).hexdigest()
PATHS = {
    'hook': '/var/www/anytoour/data/www/anytoour.ru/data/monitor-price-hook.php',
    'cohort': '/var/www/anytoour/data/www/anytoour.ru/data/published-hotels-monitor-v1.php',
    'collector': '/var/www/anytoour/data/www/anytoour.ru/data/top500-daily-collector-v1.php',
    'journal': '/var/www/anytoour/data/channel-post-operations/monitor-price-after-collection-v1.jsonl',
}

class RunnerTests(unittest.TestCase):
    def exercise(self, mutation=None, error=None):
        calls = []
        admissions = []
        mock_admit = types.ModuleType('admit')
        mock_admit.APP = APP
        mock_admit.main = lambda: admissions.append(True)
        with patch.dict(sys.modules, {'admit': mock_admit}):
            spec = importlib.util.spec_from_file_location('readiness_test_subject', SOURCE)
            runner = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(runner)
        with tempfile.TemporaryDirectory(prefix='readiness-offline-') as root:
            root = Path(root)
            runner.ROOT = root
            journal = {'sha256': 'b' * 64, 'bytes': 100}
            config = {'native_before_shas': {k: str(i) * 64 for i, k in enumerate(PATHS, 1)},
                      'feed_shas': {'hotel-posts-all.xml': 'f' * 64},
                      'expected_ids': list(range(1, 878)), 'journal_pin': journal,
                      'candidate_hook': CANDIDATE_SHA}
            (root / 'monitor-price-hook.php').write_bytes(CANDIDATE)
            feeds = {'ok': True, 'files': {'hotel-posts-all.xml': {'sha256': 'f' * 64}}}
            result = {'ok': True, 'mode': 'read_only_hook_inspection', 'installed_sha': APP,
                      'roster_pairs': 877, 'scanned_pairs': 877, 'verified_ids': list(range(1, 878)),
                      'new_posts': 0, 'supplier_calls': 0, 'site_writes': 0,
                      'updated_pairs': 0, 'held_pairs': 0, 'error_counts': {},
                      'journal_read': journal, 'eligible_pairs': 877, 'unchanged_pairs': 877}
            state = {'pins': 0, 'feeds': 0, 'status': 0, 'result': result}
            if mutation:
                mutation(state, config)
            (root / 'config.json').write_text(json.dumps(config))
            def fake_run(argv, **kwargs):
                calls.append(argv)
                self.assertIn(argv[0], ('ssh', 'scp'))
                self.assertEqual(kwargs, {'capture_output': True})
                if argv[0] == 'scp':
                    self.assertIn(Path(argv[-2]).name, ('feeds.php', 'monitor-price-hook.php'))
                    self.assertEqual(argv[-1], 'tester@example.invalid:/tmp/price877-readiness-123/')
                    raw = b''
                else:
                    command = argv[-1].split('export GIT_OPTIONAL_LOCKS=0; ', 1)[1]
                    if command == 'sha256sum ' + ' '.join(PATHS.values()):
                        state['pins'] += 1
                        values = config['native_before_shas'].copy()
                        if state.get('changed_pin_at') == state['pins']:
                            values['hook'] = '0' * 64
                        raw = ''.join(f'{values[k]}  {p}\n' for k, p in PATHS.items()).encode()
                    elif command == 'umask 077; mkdir /tmp/price877-readiness-123':
                        raw = b''
                    elif command == 'php /tmp/price877-readiness-123/feeds.php':
                        state['feeds'] += 1
                        f = copy.deepcopy(feeds)
                        if state.get('changed_feed_at') == state['feeds']:
                            f['files']['hotel-posts-all.xml']['sha256'] = 'e' * 64
                        raw = json.dumps(f).encode()
                    elif command == 'php /tmp/price877-readiness-123/monitor-price-hook.php --inspect':
                        raw = b'MONITOR_POST_FEED_RESULT=' + json.dumps(result).encode() + b'\n'
                        return subprocess.CompletedProcess(argv, state['status'], raw, b'')
                    else:
                        self.fail('Disallowed command (default hook/install included): ' + command)
                return subprocess.CompletedProcess(argv, 0, raw, b'')
            with patch.dict(os.environ, {'RUNNER_TEMP': str(root), 'DEPLOY_HOST': 'example.invalid',
                                         'DEPLOY_USER': 'tester', 'GITHUB_RUN_ID': '123'}), \
                 patch.object(runner.subprocess, 'run', side_effect=fake_run):
                if error:
                    with self.assertRaisesRegex(RuntimeError, error):
                        runner.main()
                    self.assertFalse((root / 'price-result/summary.json').exists())
                else:
                    runner.main()
                    summary = json.loads((root / 'price-result/summary.json').read_text())
                    self.assertTrue(summary['ok'])
                    self.assertTrue(summary['old_code_and_journal_unchanged'])
                    self.assertEqual(len(summary['verified_ids']), 877)
                    self.assertEqual(len(admissions), 2)
                    self.assertEqual(state['pins'], 2)
                    self.assertEqual(state['feeds'], 2)
                if error == 'candidate_bundle_mismatch':
                    self.assertEqual(calls, [])
                else:
                    self.assertTrue(calls)

    def test_success(self): self.exercise()
    def test_native_before_changed(self): self.exercise(lambda s,c: s.update(changed_pin_at=1), 'native_before_changed')
    def test_native_after_changed(self): self.exercise(lambda s,c: s.update(changed_pin_at=2), 'native_before_changed')
    def test_feed_preimage_changed(self): self.exercise(lambda s,c: s.update(changed_feed_at=1), 'feed_preimage_changed')
    def test_feed_changed_during_inspection(self): self.exercise(lambda s,c: s.update(changed_feed_at=2), 'feeds_changed_during_inspection')
    def test_missing_id(self): self.exercise(lambda s,c: s['result']['verified_ids'].pop(), 'full877_context_not_verified')
    def test_wrong_id(self): self.exercise(lambda s,c: s['result']['verified_ids'].__setitem__(0, 9999), 'full877_context_not_verified')
    def test_candidate_sha_mismatch(self): self.exercise(lambda s,c: c.update(candidate_hook='0'*64), 'candidate_bundle_mismatch')
    def test_wrong_runtime(self): self.exercise(lambda s,c: s['result'].update(installed_sha='d'*64), 'inspection_failed')
    def test_write_counters(self):
        for key in ['new_posts','supplier_calls','site_writes','updated_pairs','held_pairs']:
            with self.subTest(counter=key):
                self.exercise(lambda s,c: s['result'].update({key:1}), 'read_only_scope_changed')
    def test_error(self): self.exercise(lambda s,c: s['result'].update(error='failed'), 'read_only_journal_or_errors_changed')
    def test_error_counts(self): self.exercise(lambda s,c: s['result'].update(error_counts={'failed':1}), 'read_only_journal_or_errors_changed')
    def test_journal_changed(self): self.exercise(lambda s,c: s['result'].update(journal_read={}), 'read_only_journal_or_errors_changed')
    def test_nonzero_exit(self): self.exercise(lambda s,c: s.update(status=2), 'inspect_native_nonzero')

if __name__ == '__main__':
    unittest.main(verbosity=2)
