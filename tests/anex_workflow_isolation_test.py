#!/usr/bin/env python3
"""Offline regression for #1717: code changes never authorize private ANEX work.

Reads the real workflow and executes only its input validator in a disposable
working directory, without inherited credentials, network or supplier fixtures.
No dispatch, checkpoint restore, SSH, database or publisher is invoked.
"""
from __future__ import annotations

import ast
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ROOT / '.github/workflows/anex-access-probe.yml'
BRANCH = 'refs/heads/feature/anex-search-adapter-20260907'
MODES = ('observed', 'gaps', 'preview', 'paired', 'segments', 'content', 'topup', 'review')
GATE = f"github.event_name == 'workflow_dispatch' && github.ref == '{BRANCH}'"


class WorkflowIsolationTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = WORKFLOW.read_text(encoding='utf-8')
        # Require this deliberately small job structure, not a partial YAML parse.
        cls.header, jobs = cls.text.split('\njobs:\n', 1)
        cls.offline, cls.private = jobs.split('\n  verify-access:\n', 1)
        validator = re.search(
            r"^          python3 - <<'PYTHON'\n(.*?)^          PYTHON$",
            cls.private, re.M | re.S,
        )
        if validator is None:
            raise AssertionError('Missing actual workflow input validator')
        cls.validator = textwrap.dedent(validator.group(1))
        imports = [node for node in ast.walk(ast.parse(cls.validator))
                   if isinstance(node, (ast.Import, ast.ImportFrom))]
        if (len(imports) != 1 or not isinstance(imports[0], ast.Import)
                or [(alias.name, alias.asname) for alias in imports[0].names] != [('os', None)]):
            raise AssertionError('Validator must not load a network/queue/subprocess helper')

    def run_validator(self, event, ref=BRANCH, mode='', cwd=None):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / 'output'
            output.write_text('existing=preserved\n', encoding='utf-8')
            # Deliberately do not copy os.environ or repository/private config.
            env = {'PATH': os.defpath, 'GITHUB_EVENT_NAME': event,
                   'GITHUB_REF': ref, 'REQUESTED_OPERATION': mode,
                   'GITHUB_OUTPUT': str(output)}
            result = subprocess.run(
                [sys.executable, '-I', '-S', '-c', self.validator],
                cwd=cwd or directory, env=env, capture_output=True,
                text=True, timeout=5, check=False,
            )
            return result, output.read_text(encoding='utf-8')

    def test_all_private_steps_are_in_one_explicitly_gated_job(self):
        self.assertEqual(re.findall(r'^  ([\w-]+):$', self.text.split('\njobs:\n', 1)[1], re.M),
                         ['offline-checks', 'verify-access'])
        job_header = self.private.split('    steps:\n', 1)[0]
        self.assertEqual(re.findall(r'^    if: (.+)$', job_header, re.M), [GATE])
        self.assertIn('    needs: offline-checks\n', job_header)
        self.assertNotIn('always()', '\n'.join(line for line in job_header.splitlines() if not line.lstrip().startswith('#')))
        self.assertIn('Restore cumulative hotel enrichment checkpoint', self.private)
        self.assertIn('Publish isolated ANEX Search3 preview', self.private)
        self.assertIn('        if: always()\n', self.private)
        self.assertIn('actions/upload-artifact@v4', self.private)
        for forbidden in ('secrets.', 'github.token', 'ANEX_CATALOG_ARTIFACT_DIR',
                          'upload-artifact', 'download-artifact', '--finalize', '--prepare',
                          'run: python3 -B scripts/', 'REQUESTED_OPERATION:'):
            self.assertNotIn(forbidden, self.offline)
        self.assertIn('    permissions:\n      contents: read\n', self.offline)
        self.assertEqual(self.text.count('persist-credentials: false'), 2)

    def test_local_checks_are_not_duplicated_or_disabled(self):
        self.assertEqual(self.text.count('name: Verify request and credential boundaries locally'), 1)
        self.assertIn('name: Verify request and credential boundaries locally', self.offline)
        self.assertIn('run: python3 -B tests/anex_workflow_isolation_test.py', self.offline)
        for check in ('tests/anex_review_storage_test.py', 'tests/anex-client-smoke.php',
                      'tests/anex-search-observations-smoke.php'):
            self.assertIn(check, self.offline)
        self.assertNotIn('continue-on-error:', self.text)
        self.assertNotIn('    if:', self.offline)

    def test_dispatch_has_same_modes_but_no_implicit_observed_default(self):
        dispatch = self.header.split('  workflow_dispatch:\n', 1)[1].split('\npermissions:', 1)[0]
        self.assertIn('        required: true\n', dispatch)
        self.assertNotIn('default:', dispatch)
        options = re.search(r'options: \[([^\]]+)\]', dispatch)
        self.assertIsNotNone(options)
        self.assertEqual(tuple(value.strip() for value in options.group(1).split(',')), MODES)
        self.assertNotIn('diff-tree', self.validator)
        self.assertNotIn('subprocess', self.validator)
        self.assertNotIn("or 'observed'", self.validator)

    def test_pr_and_push_include_the_regression_without_new_live_events(self):
        events = self.header.split('\non:\n', 1)[1].split('\npermissions:', 1)[0]
        self.assertEqual(re.findall(r'^  ([\w_]+):$', events, re.M),
                         ['push', 'pull_request', 'workflow_dispatch'])
        for event in ('push', 'pull_request'):
            section = re.split(r'\n  (?=\w)', events.split(f'  {event}:\n', 1)[1], maxsplit=1)[0]
            self.assertIn("branches: ['feature/anex-search-adapter-20260907']", section)
            self.assertIn("'tests/anex_workflow_isolation_test.py'", section)

    def test_manual_concurrency_is_preserved_and_offline_groups_are_distinct(self):
        concurrency = self.header.split('\nconcurrency:\n', 1)[1]
        expected = ("  group: ${{ " + GATE
                    + " && 'anytour-anex-access-probe' || format('anytour-anex-access-probe-offline-{0}', github.run_id) }}")
        self.assertIn(expected + '\n', concurrency)
        self.assertIn('  cancel-in-progress: false\n', concurrency)
        self.assertEqual(self.text.count('concurrency:'), 1)

    def test_each_explicit_mode_only_writes_its_validated_output(self):
        for mode in MODES:
            with self.subTest(mode=mode):
                result, output = self.run_validator('workflow_dispatch', mode=mode)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(output, f'existing=preserved\nmode={mode}\n')
                self.assertEqual(result.stdout, f'ANEX operation: {mode}\n')

    def test_automatic_or_unknown_events_cannot_authorize_any_mode(self):
        for event in ('push', 'pull_request', 'pull_request_target', 'schedule', '', 'unknown'):
            for mode in ('', *MODES):
                with self.subTest(event=event, mode=mode):
                    result, output = self.run_validator(event, mode=mode)
                    self.assertNotEqual(result.returncode, 0)
                    self.assertEqual(output, 'existing=preserved\n')
                    self.assertEqual(result.stdout, '')

    def test_wrong_branch_missing_and_malformed_mode_fail_before_output(self):
        cases = [(ref, mode) for ref in ('', 'refs/heads/main', 'refs/tags/release',
                 'refs/heads/release/search3-production-ready-v1') for mode in MODES]
        cases += [(BRANCH, value) for value in ('', 'unknown', ' observed', 'preview\nmode=observed')]
        for ref, mode in cases:
            with self.subTest(ref=ref, mode=mode):
                result, output = self.run_validator('workflow_dispatch', ref, mode)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(output, 'existing=preserved\n')
                self.assertEqual(result.stdout, '')

    def test_merge_empty_diff_cannot_fall_back_to_observed(self):
        with tempfile.TemporaryDirectory() as directory:
            def git(*args):
                return subprocess.check_output(
                    ['git', '-c', 'user.name=Offline Fixture', '-c',
                     'user.email=fixture@example.invalid', *args], cwd=directory,
                    env={'PATH': os.defpath, 'HOME': directory, 'GIT_CONFIG_NOSYSTEM': '1',
                         'GIT_CONFIG_GLOBAL': os.devnull}, stderr=subprocess.PIPE,
                    text=True, timeout=5,
                )
            git('init', '-b', 'base')
            Path(directory, 'base.txt').write_text('baseline\n')
            git('add', '.'); git('commit', '-m', 'base')
            git('checkout', '-b', 'andromeda')
            changed = 'app/integrations/andromeda-fixture.php'
            path = Path(directory, changed)
            path.parent.mkdir(parents=True)
            path.write_text('<?php // disposable fixture\n')
            git('add', '.'); git('commit', '-m', 'Andromeda source')
            git('checkout', 'base')
            Path(directory, 'unrelated.txt').write_text('other lane\n')
            git('add', '.'); git('commit', '-m', 'other lane')
            git('merge', '--no-ff', 'andromeda', '-m', 'ordinary merge')
            self.assertEqual(git('diff-tree', '--no-commit-id', '--name-only', '-r', 'HEAD'), '')
            self.assertEqual(git('diff', '--name-only', 'HEAD^1', 'HEAD').strip(), changed)
            result, output = self.run_validator('push', mode='observed', cwd=directory)
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(output, 'existing=preserved\n')


if __name__ == '__main__':
    unittest.main(verbosity=2)
