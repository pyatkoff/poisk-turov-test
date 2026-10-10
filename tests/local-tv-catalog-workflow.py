#!/usr/bin/env python3
"""Execute the actual collector workflow shell with inert SSH/PHP boundaries.

No network, private config, database or supplier is contacted. The assertions
cover which entrypoints would run, rather than copying the shell's conditions.
"""
from __future__ import annotations

import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]


def collector_shell() -> str:
    source = (ROOT / ".github/workflows/collect-hotel-details.yml").read_text()
    step = source.split("      - name: Enrich selected hotel acquisition universe within explicit request budget\n", 1)[1]
    scalar = step.split("        run: |\n", 1)[1]
    lines = []
    for line in scalar.splitlines():
        if line.strip() and not line.startswith("          "):
            break
        lines.append(line)
    return textwrap.dedent("\n".join(lines))


class LocalWorkflowIsolation(unittest.TestCase):
    def run_scope(self, scope="demand", event="workflow_dispatch", flag="demand", probe_failure=False):
        with tempfile.TemporaryDirectory(prefix="local-workflow-") as directory:
            task_root = Path(directory)
            binaries = task_root / "bin"
            binaries.mkdir()
            log = task_root / "php.jsonl"
            fixture_data = task_root / "data"
            fixture_data.mkdir()
            for name in ("db-v1.php", "local-tv-catalog-v1.php", "hotel-details-v1.php"):
                shutil.copyfile(ROOT / "v2/data" / name, fixture_data / name)
            # Only this synthetic private config is loaded by the real PHP flag probe.
            (task_root / "config.php").write_text(
                "<?php define('ANYTOUR_LOCAL_TV_CATALOG_ENABLED', " + ("true" if flag == "local" else "false") + ");")
            real_php = shutil.which("php")
            if real_php is None:
                raise RuntimeError("PHP is required for the actual config/feature-flag probe")
            scripts = {
                "scp": "#!/usr/bin/env python3\nraise SystemExit(0)\n",
                "ssh": "#!/usr/bin/env python3\nimport subprocess, sys\nraise SystemExit(subprocess.run(['bash', '-c', sys.argv[-1]]).returncode)\n",
                "php": """#!/usr/bin/env python3
import json, os, subprocess, sys
from pathlib import Path
args = sys.argv[1:]
with open(os.environ['LOCAL_WORKFLOW_TEST_LOG'], 'a') as out:
    out.write(json.dumps(args) + '\\n')
if args and args[0] == '-r':
    if os.environ.get('LOCAL_WORKFLOW_PROBE_FAIL') == '1':
        raise SystemExit(71)
    if os.environ['LOCAL_WORKFLOW_FLAG'] == 'unexpected':
        print('unexpected')
    else:
        args[-1] = os.environ['LOCAL_WORKFLOW_FIXTURE_DATA']
        raise SystemExit(subprocess.run([os.environ['LOCAL_WORKFLOW_REAL_PHP']] + args).returncode)
""",
            }
            for name, source in scripts.items():
                executable = binaries / name
                executable.write_text(source)
                executable.chmod(0o755)
            env = dict(os.environ, PATH=str(binaries) + os.pathsep + os.environ["PATH"],
                       RUNNER_TEMP=str(task_root), HOST_VALUE="fixture.invalid", USER_VALUE="fixture",
                       SCOPE_VALUE=scope, EVENT_VALUE=event, LIMIT_VALUE="20", FRESH_DAYS_VALUE="30",
                       INTERVAL_VALUE="550", MAX_ATTEMPTS_VALUE="1", HTTP_BUDGET_VALUE="0" if scope=="local" else "20",
                       LOCAL_WORKFLOW_TEST_LOG=str(log), LOCAL_WORKFLOW_FLAG=flag,
                       LOCAL_WORKFLOW_REAL_PHP=real_php, LOCAL_WORKFLOW_FIXTURE_DATA=str(fixture_data),
                       LOCAL_WORKFLOW_PROBE_FAIL="1" if probe_failure else "0")
            env.pop("ANYTOUR_LOCAL_TV_CATALOG_ENABLED", None)
            result = subprocess.run(["bash", "-c", collector_shell()], env=env, cwd=ROOT,
                                    text=True, capture_output=True, timeout=10)
            calls = [json.loads(line) for line in log.read_text().splitlines()] if log.exists() else []
            return result, calls

    @staticmethod
    def writers(calls):
        return [(Path(args[0]).name, args[1:]) for args in calls if args and not args[0].startswith("-")]

    def assert_local_only(self, result, calls):
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual([name for name, _ in self.writers(calls)], ["collect-hotel-details-v1.php"])
        self.assertIn("--candidate-scope=local", self.writers(calls)[0][1])
        self.assertIn("--http-budget=0", self.writers(calls)[0][1])

    def assert_legacy(self, result, calls, scope):
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual([name for name, _ in self.writers(calls)],
                         ["migrate-hotel-details-v1.php", "collect-hotel-details-v1.php", "build-seo-offer-snapshots-v1.php"])
        self.assertIn("--candidate-scope=" + scope, self.writers(calls)[1][1])

    def test_explicit_local_never_calls_legacy_migration_or_seo(self):
        self.assert_local_only(*self.run_scope(scope="local", flag="local"))

    def test_explicit_demand_retains_existing_writers_even_when_flag_is_local(self):
        self.assert_legacy(*self.run_scope(scope="demand", flag="local"), "demand")

    def test_explicit_canonical_retains_existing_writers(self):
        self.assert_legacy(*self.run_scope(scope="canonical", flag="local"), "canonical")

    def test_schedule_with_enabled_flag_is_local_only(self):
        result, calls = self.run_scope(event="schedule", flag="local")
        self.assert_local_only(result, calls)
        self.assertEqual(sum(args[0] == "-r" for args in calls), 1)

    def test_schedule_with_disabled_flag_retains_demand_writers(self):
        result, calls = self.run_scope(event="schedule", flag="demand")
        self.assert_legacy(result, calls, "demand")
        self.assertEqual(sum(args[0] == "-r" for args in calls), 1)

    def test_failed_schedule_flag_read_runs_no_writer(self):
        result, calls = self.run_scope(event="schedule", probe_failure=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.writers(calls), [])

    def test_unexpected_schedule_scope_runs_no_writer(self):
        result, calls = self.run_scope(event="schedule", flag="unexpected")
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.writers(calls), [])


if __name__ == "__main__":
    unittest.main(verbosity=2)
