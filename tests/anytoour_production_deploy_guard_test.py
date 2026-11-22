#!/usr/bin/env python3
"""Exercise the existing publisher's ownership guards with inert SSH only."""
from __future__ import annotations

import fnmatch
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = (ROOT / ".github/workflows/deploy-anytoour.yml").read_text()
LEGACY_SHA256 = "9d00c5f952e0c94cbe850e9fd5c95aa9b4e2cf8bd1ff7732faa3f1e10c1a1438"
# Retained public predecessor from main f58b6bb2; independent of a future checkout.
LEGACY_ROUTE = br"""<?php
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isStandalone = $host === 'anytoour.ru' || $host === 'www.anytoour.ru' || str_starts_with($host, 'anytoour.ru:') || str_starts_with($host, 'www.anytoour.ru:');
if (!defined('V2_FORCE_SEARCH_PAGE')) define('V2_FORCE_SEARCH_PAGE', true);
if (!defined('V2_CANONICAL_PATH')) define('V2_CANONICAL_PATH', '/poisk-turov/');
if (!defined('V2_PUBLIC_BASE_PATH')) {
    if ($isStandalone) {
        define('V2_PUBLIC_BASE_PATH', '');
    } else {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = dirname(dirname($script));
        define('V2_PUBLIC_BASE_PATH', $base === '/' ? '' : $base);
    }
}
$root = dirname(__DIR__);
$searchEntry = $root . '/search-page-v2.php';
if (!is_file($searchEntry)) $searchEntry = $root . '/index.php';
require $searchEntry;
"""
SOURCE_GUARD = "Require the checked-out legacy public route"
INSTALLED_GUARD = "Require the installed legacy public route"
OLD_STEPS = [
    "Validate standalone release",
    "Prepare SSH keys",
    "Prepare authenticated legacy lead receiver",
    "Copy release to anytoour.ru",
    "Verify public pages",
    "Verify lead bridge reaches unchanged production adapter",
    "Live search smoke",
]


def job(name: str) -> str:
    value = WORKFLOW.split("\n  " + name + ":\n", 1)[1]
    return re.split(r"(?m)^  [a-z][a-z0-9-]*:\s*$", value, maxsplit=1)[0]


def step(name: str) -> str:
    return next(block for block in re.split(r"(?m)^      - ", job("deploy"))[1:]
                if block.startswith("name: " + name + "\n"))


def shell(name: str) -> str:
    return textwrap.dedent(step(name).split("        run: |\n", 1)[1])


def fixture(site: Path, kind: str) -> None:
    route = site / "poisk-turov/index.php"
    route.parent.mkdir(parents=True)
    if kind == "missing":
        return
    if kind == "directory":
        route.mkdir()
        return
    route.write_bytes(LEGACY_ROUTE if kind not in ("changed", "empty") else
                      b"<?php /* different public owner */\n" if kind == "changed" else b"")
    if kind == "symlink":
        target = site / "retained-owner.php"
        route.rename(target)
        route.symlink_to(target)
    elif kind == "parent-symlink":
        target = site / "retained-parent"
        route.parent.rename(target)
        route.parent.symlink_to(target, target_is_directory=True)
    elif kind == "root-symlink":
        target = site.with_name(site.name + "-retained")
        site.rename(target)
        site.symlink_to(target, target_is_directory=True)


def fingerprints(site: Path) -> dict:
    return {str(path.relative_to(site)): (
        "symlink", os.readlink(path)) if path.is_symlink() else (
        "file", path.read_bytes(), path.stat().st_mode)
        for path in site.rglob("*") if path.is_symlink() or path.is_file()}


class ProductionPublisherGuard(unittest.TestCase):
    def test_favicon_push_requires_artwork_and_keeps_explicit_dispatch(self):
        workflow = (ROOT / ".github/workflows/deploy-favicon.yml").read_text()
        events = workflow.split("\non:\n", 1)[1].split("\nconcurrency:\n", 1)[0]

        def event(name):
            value = events.split("  " + name + ":\n", 1)[1]
            return re.split(r"(?m)^  [a-z_]+:", value, maxsplit=1)[0]

        push = event("push")
        self.assertIn("    branches: [main]\n", push)
        paths = re.findall(r"(?m)^      - '([^']+)'$", push)
        self.assertEqual(paths, ["v2/favicon.svg"])

        def push_eligible(changed):
            return any(fnmatch.fnmatchcase(path, pattern)
                       for path in changed for pattern in paths)

        self.assertFalse(push_eligible([".github/workflows/deploy-favicon.yml"]),
                         "a CI-only merge must not write production favicon files")
        self.assertFalse(push_eligible(["v2/home-v1.php", "v2/site-header-v2.php", "v2/index.php"]),
                         "HOME source changes must not invoke the favicon publisher")
        self.assertTrue(push_eligible(["v2/favicon.svg"]), "actual artwork changes retain their publisher")
        self.assertTrue(push_eligible(["v2/favicon.svg", ".github/workflows/deploy-favicon.yml"]))
        self.assertIn("  workflow_dispatch:\n", events)
        pr_paths = re.findall(r"(?m)^      - '([^']+)'$", event("pull_request"))
        for path in (".github/workflows/deploy-favicon.yml",
                     "tests/anytoour_production_deploy_guard_test.py",
                     "tests/public-favicon-inspection-test.py"):
            self.assertIn(path, pr_paths)
        deploy = workflow.split("\n  deploy-favicon:\n", 1)[1]
        self.assertEqual(re.findall(r"(?m)^    if: (.+)$", deploy), [
            "github.event_name == 'push' || github.event_name == 'workflow_dispatch'"])

    def test_legacy_publisher_is_manual_only_without_source_push_followers(self):
        events = WORKFLOW.split("\non:\n", 1)[1].split("\npermissions:\n", 1)[0]
        self.assertEqual(WORKFLOW.splitlines()[0], "name: Deploy anytoour.ru")
        self.assertEqual(re.findall(r"(?m)^  ([a-z_]+):", events), ["workflow_dispatch"])
        self.assertNotIn("  push:", events)
        self.assertIn("  workflow_dispatch:\n", events)
        # This workflow name has post-deploy workflow_run followers. PR tests
        # belong to the existing root-gateway contract carrier, never this name.
        self.assertNotIn("pull_request", events)
        self.assertNotIn("workflow_run", events)
        self.assertEqual(re.findall(r"(?m)^    if: (.+)$", job("deploy")), [
            "github.event_name == 'workflow_dispatch'"])

    def test_contracts_are_required_and_only_deploy_owns_the_shared_lock(self):
        self.assertIn("\n  contents: read\n", WORKFLOW.split("\njobs:\n", 1)[0])
        self.assertNotIn("\nconcurrency:", WORKFLOW.split("\njobs:\n", 1)[0])
        contracts = job("contracts")
        self.assertNotIn("concurrency:", contracts)
        self.assertNotIn("secrets.", contracts)
        self.assertNotIn("if:", contracts)
        self.assertEqual(re.findall(r"(?m)^        run: (.+)$", contracts),
                         ["python3 -B tests/anytoour_production_deploy_guard_test.py -v"])
        deploy = job("deploy")
        self.assertIn("    needs: contracts\n", deploy)
        self.assertIn("    concurrency:\n      group: deploy-anytoour-production\n"
                      "      cancel-in-progress: false\n", deploy)
        self.assertEqual(WORKFLOW.count("group: deploy-anytoour-production"), 1)
        self.assertNotIn("continue-on-error", WORKFLOW)

    def test_original_steps_remain_serial_behind_the_ownership_guards(self):
        names = re.findall(r"(?m)^      - name: (.+)$", job("deploy"))
        self.assertEqual(names, [
            SOURCE_GUARD, *OLD_STEPS[:2], INSTALLED_GUARD, *OLD_STEPS[2:]])
        for name in names:
            self.assertNotRegex(step(name), r"(?m)^        if:")
            self.assertIn("          set -euo pipefail\n", step(name))
        self.assertLess(job("deploy").index("name: " + SOURCE_GUARD),
                        job("deploy").index("php -"))
        self.assertLess(job("deploy").index("name: " + INSTALLED_GUARD),
                        job("deploy").index("name: Prepare authenticated legacy lead receiver"))
        self.assertNotIn("OLD_HOST", step(INSTALLED_GUARD))
        self.assertIn('tar -C v2 -czf "$archive" .', step(OLD_STEPS[3]))
        self.assertIn('php -d display_errors=1 -r', step(OLD_STEPS[0]))
        self.assertIn('https://anytoour.ru/lead-adapter-v2.php', step(OLD_STEPS[5]))
        self.assertIn('action=search_start', step(OLD_STEPS[6]))

    def run_guards(self, source="legacy", installed="legacy", ssh_status=0,
                   host="production.invalid", user="fixture"):
        with tempfile.TemporaryDirectory(prefix="anytoour-publisher-guard-") as temporary:
            base = Path(temporary)
            checkout, remote = base / "checkout", base / "installed"
            fixture(checkout / "v2", source)
            fixture(remote, installed)
            before = (fingerprints(checkout), fingerprints(remote))
            binary = base / "bin"
            binary.mkdir()
            log, continuation = base / "calls.jsonl", base / "legacy-actions"
            blocked = textwrap.dedent("""\
                import json,os,sys
                with open(os.environ['ANYTOOUR_GUARD_LOG'],'a') as handle:
                    handle.write(json.dumps({'forbidden':os.path.basename(sys.argv[0])})+'\\n')
                raise SystemExit(98)
                """)
            for name in ("php", "curl", "scp", "ssh-keygen", "openssl", "tar",
                         "cp", "mv", "rm", "chmod", "mkdir", "cat"):
                target = binary / name
                target.write_text("#!" + sys.executable + "\n" + blocked)
                target.chmod(0o755)
            target = binary / "ssh"
            target.write_text("#!" + sys.executable + "\n" + textwrap.dedent("""\
                import json,os,subprocess,sys
                with open(os.environ['ANYTOOUR_GUARD_LOG'],'a') as handle:
                    handle.write(json.dumps({'ssh':sys.argv[1:]})+'\\n')
                if int(os.environ['ANYTOOUR_GUARD_SSH_STATUS']):
                    raise SystemExit(int(os.environ['ANYTOOUR_GUARD_SSH_STATUS']))
                assert sys.argv[-2]=='fixture@production.invalid'
                program=sys.argv[-1]
                assert program.count('$HOME/www/anytoour.ru')==1
                # Only redirect the fixed path into the disposable fixture;
                # do not alter HOME or replace the hash/ownership logic.
                program=program.replace('$HOME/www/anytoour.ru','$ANYTOOUR_GUARD_REMOTE_ROOT')
                raise SystemExit(subprocess.run(['bash','-c',program]).returncode)
                """))
            target.chmod(0o755)
            env = dict(os.environ, PATH=str(binary) + os.pathsep + os.environ["PATH"],
                       RUNNER_TEMP=str(base), NEW_HOST=host, NEW_USER=user,
                       ANYTOOUR_GUARD_LOG=str(log), ANYTOOUR_GUARD_REMOTE_ROOT=str(remote),
                       ANYTOOUR_GUARD_SSH_STATUS=str(ssh_status),
                       ANYTOOUR_GUARD_CONTINUATION=str(continuation))
            program = (shell(SOURCE_GUARD) + "\n" + shell(INSTALLED_GUARD)
                       + "\nprintf '%s\\n' admitted > \"$ANYTOOUR_GUARD_CONTINUATION\"\n")
            result = subprocess.run(["bash", "-c", program], cwd=checkout, env=env,
                                    capture_output=True, text=True, timeout=10)
            calls = [json.loads(line) for line in log.read_text().splitlines()] if log.exists() else []
            self.assertEqual(before, (fingerprints(checkout), fingerprints(remote)),
                             "the guards must not change checkout or installed files")
            self.assertFalse(any("forbidden" in call for call in calls), calls)
            return result, calls, continuation.exists()

    def test_exact_legacy_owners_admit_the_existing_sequence_without_content_export(self):
        self.assertEqual(hashlib.sha256(LEGACY_ROUTE).hexdigest(), LEGACY_SHA256)
        self.assertEqual(WORKFLOW.count(LEGACY_SHA256), 2)
        result, calls, admitted = self.run_guards()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(admitted)
        self.assertEqual(len(calls), 1)
        self.assertIn("BatchMode=yes", calls[0]["ssh"])
        self.assertEqual(calls[0]["ssh"][-2], "fixture@production.invalid")
        self.assertEqual(result.stdout.splitlines(), [
            "ANYTOOUR_LEGACY_SOURCE_OWNER_OK", "ANYTOOUR_LEGACY_INSTALLED_OWNER_OK"])
        self.assertEqual(result.stderr, "")

    def test_changed_missing_or_indirect_source_refuses_before_any_ssh(self):
        for kind in ("changed", "missing", "empty", "directory",
                     "symlink", "parent-symlink", "root-symlink"):
            with self.subTest(source=kind):
                result, calls, admitted = self.run_guards(source=kind)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(calls, [])
                self.assertFalse(admitted)
                self.assertNotIn("ANYTOOUR_LEGACY_SOURCE_OWNER_OK", result.stdout)

    def test_changed_missing_or_indirect_installed_owner_refuses_before_legacy_actions(self):
        for kind in ("changed", "missing", "empty", "directory",
                     "symlink", "parent-symlink", "root-symlink"):
            with self.subTest(installed=kind):
                result, calls, admitted = self.run_guards(installed=kind)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(len(calls), 1)
                self.assertFalse(admitted)
                self.assertNotIn("ANYTOOUR_LEGACY_INSTALLED_OWNER_OK", result.stdout)

    def test_unknown_connection_and_missing_destination_fail_closed(self):
        result, calls, admitted = self.run_guards(ssh_status=255)
        self.assertEqual(result.returncode, 255)
        self.assertEqual(len(calls), 1)
        self.assertFalse(admitted)
        for values in ({"host": ""}, {"user": ""}):
            with self.subTest(values=values):
                result, calls, admitted = self.run_guards(**values)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(calls, [])
                self.assertFalse(admitted)


if __name__ == "__main__":
    unittest.main()
