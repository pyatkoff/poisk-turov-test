#!/usr/bin/env python3
"""Execute the actual collector workflow shell with inert SSH/PHP boundaries.

No network, private config, database or supplier is contacted. The assertions
cover which entrypoints would run, rather than copying the shell's conditions.
"""
from __future__ import annotations

import ast
import fnmatch
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import stat
import subprocess
import sys
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = Path(os.environ.get("LOCAL_TV_CAPTURE_SOURCE_ROOT", str(ROOT / "local-tv-capture-source")))
DAILY_SOURCE_SHA = "4473719a21b10633fcdc506fb007ad3496282f4b"
STAT_FIELDS = ("st_dev", "st_ino", "st_mode", "st_nlink", "st_size",
               "st_mtime_ns", "st_ctime_ns", "st_uid", "st_gid")


def file_identity(path):
    value = path.lstat()
    return tuple(getattr(value, field) for field in STAT_FIELDS)


def shared_helper_shell(workflow):
    """Read the real function and its call, including its exact source argv."""
    text = (ROOT / ".github/workflows" / workflow).read_text()
    match = re.search(
        r"(?ms)^          deliver_shared_helpers\(\) \{\n.*?^          \}\n"
        r"          deliver_shared_helpers \\\n(?:            v2/data/[^\n]+\n)+", text)
    if match is None:
        raise AssertionError(workflow + ": missing actual shared-helper delivery")
    shell = textwrap.dedent(match.group())
    function, call = shell.rsplit("\ndeliver_shared_helpers ", 1)
    argv = shlex.split(call.replace("\\\n", ""))
    emitter = function.split("<<'PY'", 1)[1].split("\n", 1)[1].rsplit("\nPY", 1)[0]
    tree = ast.parse(emitter)
    remote = next(ast.literal_eval(node.value) for node in tree.body
                  if isinstance(node, ast.Assign)
                  and any(isinstance(target, ast.Name) and target.id == "REMOTE" for target in node.targets))
    return shell, argv, emitter, remote


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
    def run_scope(self, scope="demand", event="workflow_dispatch", flag="demand", probe_failure=False, tamper_db=False):
        with tempfile.TemporaryDirectory(prefix="local-workflow-") as directory:
            task_root = Path(directory)
            binaries = task_root / "bin"
            binaries.mkdir()
            log = task_root / "php.jsonl"
            fixture_home = task_root / "home"
            fixture_root = fixture_home / "www/anytoour.ru"
            fixture_data = fixture_root / "data"
            fixture_data.mkdir(parents=True)
            # Match the actual flattened root installation: MAIN owns config
            # resolution, while the pinned LOCAL source owns its catalogue.
            shutil.copyfile(ROOT / "v2/data/db-v1.php", fixture_data / "db-v1.php")
            for name in ("local-tv-catalog-v1.php", "hotel-details-v1.php"):
                shutil.copyfile(SOURCE / "v2/data" / name, fixture_data / name)
            # Only this synthetic private config is loaded by the real PHP flag probe.
            (fixture_root / "config.php").write_text(
                "<?php define('ANYTOUR_LOCAL_TV_CATALOG_ENABLED', " + ("true" if flag == "local" else "false") + ");")
            real_php = shutil.which("php")
            if real_php is None:
                raise RuntimeError("PHP is required for the actual config/feature-flag probe")
            scripts = {
                "scp": "#!/usr/bin/env python3\nimport json, os, sys\nfrom pathlib import Path\nPath(os.environ['LOCAL_WORKFLOW_COPY_LOG']).write_text(json.dumps(sys.argv[1:]))\n",
                "ssh": """#!/usr/bin/env python3
import os, subprocess, sys
from pathlib import Path
command=sys.argv[-1]
result=subprocess.run(['bash', '-c', command], input=sys.stdin.buffer.read())
# Inject corruption after the real delivery, so the old readback test still
# exercises corruption between copy and verification instead of being repaired.
if command == 'python3 -' and result.returncode == 0 and os.environ['LOCAL_WORKFLOW_TAMPER_DB'] == '1':
    (Path(os.environ['LOCAL_WORKFLOW_FIXTURE_DATA'])/'db-v1.php').write_text('synthetic corrupted copy')
raise SystemExit(result.returncode)
""",
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
            env = dict(os.environ, HOME=str(fixture_home), PATH=str(binaries) + os.pathsep + os.environ["PATH"],
                       RUNNER_TEMP=str(task_root), HOST_VALUE="fixture.invalid", USER_VALUE="fixture",
                       SCOPE_VALUE=scope, EVENT_VALUE=event, LIMIT_VALUE="20", FRESH_DAYS_VALUE="30",
                       INTERVAL_VALUE="550", MAX_ATTEMPTS_VALUE="1", HTTP_BUDGET_VALUE="0" if scope=="local" else "20",
                       LOCAL_WORKFLOW_TEST_LOG=str(log), LOCAL_WORKFLOW_COPY_LOG=str(task_root / "copies.json"), LOCAL_WORKFLOW_FLAG=flag,
                       LOCAL_WORKFLOW_REAL_PHP=real_php, LOCAL_WORKFLOW_FIXTURE_DATA=str(fixture_data),
                       LOCAL_WORKFLOW_TAMPER_DB="1" if tamper_db else "0",
                       LOCAL_WORKFLOW_PROBE_FAIL="1" if probe_failure else "0")
            env.pop("ANYTOUR_LOCAL_TV_CATALOG_ENABLED", None)
            result = subprocess.run(["bash", "-c", collector_shell()], env=env, cwd=ROOT,
                                    text=True, capture_output=True, timeout=10)
            calls = [json.loads(line) for line in log.read_text().splitlines()] if log.exists() else []
            self.copies=json.loads((task_root / "copies.json").read_text()) if (task_root / "copies.json").exists() else []
            return result, calls

    @staticmethod
    def writers(calls):
        return [(Path(args[0]).name, args[1:]) for args in calls if args and not args[0].startswith("-")]

    def assert_local_only(self, result, calls):
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("ANYTOUR_LOCAL_DB_READBACK_OK db=", result.stdout)
        self.assertEqual([name for name, _ in self.writers(calls)], ["collect-hotel-details-v1.php"])
        self.assertIn("--candidate-scope=local", self.writers(calls)[0][1])
        self.assertIn("--http-budget=0", self.writers(calls)[0][1])
        self.assertNotIn("v2/data/db-v1.php", self.copies)
        self.assertEqual(shared_helper_shell("collect-hotel-details.yml")[1], ["v2/data/db-v1.php"])
        self.assertIn('"unchanged": ["db-v1.php"]', result.stdout)
        self.assertNotIn("local-tv-source/v2/data/db-v1.php", self.copies)
        for name in ("hotel-details-v1.php", "local-tv-catalog-v1.php", "tourvisor-client-v1.php", "collect-hotel-details-v1.php"):
            self.assertIn("local-tv-source/v2/data/"+name, self.copies)
            self.assertNotIn("v2/data/"+name, self.copies)

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

    def test_mismatched_db_readback_stops_before_any_php(self):
        result, calls = self.run_scope(event="schedule", flag="local", tamper_db=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("FAILED", result.stdout)
        self.assertNotIn("ANYTOUR_LOCAL_DB_READBACK_OK", result.stdout)
        self.assertEqual(calls, [])


class CopyOwnerPins(unittest.TestCase):
    def test_both_daily_copy_owners_pin_one_exact_reviewed_source_and_never_run_on_push(self):
        for name in ("collect-hotel-details.yml", "collect-anytour-tours.yml"):
            text=(ROOT / ".github/workflows" / name).read_text()
            pinned_sources = re.findall(r"ref: ([0-9a-f]{40})\s+path: local-tv-source\s", text)
            self.assertEqual(pinned_sources, [DAILY_SOURCE_SHA, DAILY_SOURCE_SHA],
                             name + ": validation and production must use the same reviewed LOCAL source")
            live=text.split("  collect-production:\n",1)[1]
            self.assertIn("if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'", live)
            self.assertNotIn("if: github.event_name != 'pull_request'", live)
            self.assertIn("            v2/data/db-v1.php", live)
            self.assertNotIn("local-tv-source/v2/data/db-v1.php", live)
            for dependency in ("hotel-details-v1.php", "local-tv-catalog-v1.php"):
                self.assertIn("local-tv-source/v2/data/"+dependency, live)
        tour=(ROOT / ".github/workflows/collect-anytour-tours.yml").read_text()
        self.assertIn("local-tv-source/v2/data/price-observer-v1.php",tour)
        self.assertNotIn("            v2/data/price-observer-v1.php",tour)
        validation=tour.split("  validate:\n",1)[1].split("  collect-production:\n",1)[0]
        self.assertIn("path: local-tv-source",validation)
        self.assertIn("php local-tv-source/tests/local-tv-price-observer-test.php",validation)
        self.assertIn("cron: '12 * * * *'",tour)
        self.assertIn("cron: '35 3 * * *'",(ROOT / ".github/workflows/collect-hotel-details.yml").read_text())


class IdempotentSharedHelperDelivery(unittest.TestCase):
    """Execute each real inline emitter/remote with local, synthetic SSH input.

    The filesystem is real. Only the SSH transport is replaced. Hooks inject
    concurrency at actual read/rename boundaries; no delivery logic is copied.
    """
    CALLERS = {
        "collect-anytour-tours.yml": ("db-v1.php", "price-segment-v1.php", "price-confidence-v1.php", "price-intelligence-v1.php"),
        "collect-hotel-details.yml": ("db-v1.php",),
        "build-anytour-hot.yml": ("db-v1.php",),
        "rollup-anytour-prices.yml": ("db-v1.php",),
    }
    SSH = r'''#!/usr/bin/env python3
import ast, json, os, pathlib, stat, sys, types
program = ast.parse(sys.stdin.buffer.read())
state = {'mutations': [], 'foreign': None}
observing = True
injecting = False
fields = ('st_dev', 'st_ino', 'st_mode', 'st_nlink', 'st_size', 'st_mtime_ns', 'st_ctime_ns', 'st_uid', 'st_gid')
def identity(path):
    value = path.lstat()
    return [getattr(value, field) for field in fields]
def audit(event, args):
    if not observing or injecting:
        return
    mutation = event in ('os.rename', 'os.remove', 'os.rmdir', 'os.mkdir', 'os.chmod',
                         'os.chown', 'os.link', 'os.symlink', 'os.utime', 'os.truncate')
    if event == 'open' and type(args[2]) is int:
        mutation = bool(args[2] & (os.O_WRONLY | os.O_RDWR | os.O_CREAT | os.O_TRUNC | os.O_APPEND))
    if mutation:
        state['mutations'].append(event)
sys.addaudithook(audit)
namespace = {'__name__': '__main__'}
race = os.environ['HELPER_RACE']
target = pathlib.Path(os.environ['HELPER_TARGET'])
def foreign_change():
    global injecting
    injecting = True
    try:
        target.write_bytes(b'foreign concurrent content')
        target.chmod(0o644)
        state['foreign'] = identity(target)
    finally:
        injecting = False
try:
    if not program.body:
        raise SystemExit(0)  # The source emitter failed; bash pipefail owns its status.
    assert isinstance(program.body[-1], ast.Expr)
    assert isinstance(program.body[-1].value, ast.Call)
    assert isinstance(program.body[-1].value.func, ast.Name)
    assert program.body[-1].value.func.id == 'deliver'
    exec(compile(ast.Module(body=program.body[:-1], type_ignores=[]), '<actual-remote>', 'exec'), namespace)
    real_read = namespace['read_target']
    counts = {}
    def read_target(directory, name):
        counts[name] = counts.get(name, 0) + 1
        if race == 'inventory' and name == target.name and counts[name] == 2:
            foreign_change()
        return real_read(directory, name)
    namespace['read_target'] = read_target
    if race == 'unsupported_platform':
        namespace['RENAME'] = None
    if race == 'foreign_owner':
        real_fstat = namespace['os'].fstat
        def foreign_owner_fstat(fd):
            value = real_fstat(fd)
            if stat.S_ISREG(value.st_mode):
                row = {field: getattr(value, field) for field in fields}
                row['st_uid'] += 1
                return types.SimpleNamespace(**row)
            return value
        namespace['os'].fstat = foreign_owner_fstat
    if 'rename_fixed' in namespace:
        real_rename = namespace['rename_fixed']
        def rename_fixed(directory, left, right, flag):
            global injecting
            if race in ('commit_changed', 'commit_appeared') and right == target.name and state['foreign'] is None:
                foreign_change()
            state['mutations'].append('helper.rename_fixed')
            value = real_rename(directory, left, right, flag)
            if race == 'postcommit_same_bytes_replacement' and right == target.name and state['foreign'] is None:
                injecting = True
                try:
                    other = target.parent / 'foreign-replacement'
                    other.write_bytes(target.read_bytes())
                    other.chmod(0o644)
                    os.replace(other, target)
                    state['foreign'] = identity(target)
                finally:
                    injecting = False
            return value
        namespace['rename_fixed'] = rename_fixed
    if race == 'stage_write_failure':
        def failed_write(*args):
            raise OSError('synthetic write failure')
        namespace['os'].write = failed_write
    exec(compile(ast.Module(body=program.body[-1:], type_ignores=[]), '<actual-call>', 'exec'), namespace)
finally:
    observing = False
    pathlib.Path(os.environ['HELPER_AUDIT']).write_text(json.dumps(state))
'''

    def run_delivery(self, workflow, *, initial="same", unsafe="", source_shape="", race="", repeat=1):
        shell, argv, _, _ = shared_helper_shell(workflow)
        names = self.CALLERS[workflow]
        self.assertEqual(argv, ["v2/data/" + name for name in names])
        with tempfile.TemporaryDirectory(prefix="shared-delivery-") as directory:
            fixture = Path(directory)
            home = fixture / "home"
            site = home / "www/anytoour.ru"
            data = site / "data"; data.mkdir(parents=True)
            sources = fixture / "v2/data"; sources.mkdir(parents=True)
            wanted = {name: ("synthetic reviewed source: " + name).encode() for name in names}
            for name, body in wanted.items():
                (sources / name).write_bytes(body)
                (sources / name).chmod(0o644)
                if initial != "missing":
                    (data / name).write_bytes(body if initial == "same" else b"previous installed source")
                    (data / name).chmod(0o644)
            for path in (site / "config.php", site / "api-v2.php", data / "supplier-owned.php"):
                path.write_bytes(b"synthetic unrelated installed bytes")
            target = data / names[-1]
            if unsafe == "symlink":
                target.unlink(); target.symlink_to(site / "config.php")
            elif unsafe == "hardlink":
                os.link(target, fixture / "second-link")
            elif unsafe == "mode":
                target.chmod(0o666)
            elif unsafe == "parent_mode":
                data.chmod(0o777)
            elif unsafe == "directory":
                target.unlink(); target.mkdir()
            elif unsafe == "parent_symlink":
                relocated = fixture / "relocated-data"
                data.rename(relocated); data.symlink_to(relocated, target_is_directory=True)
            source = sources / names[-1]
            if source_shape == "symlink":
                source.unlink(); source.symlink_to(site / "config.php")
            elif source_shape == "hardlink":
                os.link(source, fixture / "source-link")
            elif source_shape == "mode":
                source.chmod(0o600)
            elif source_shape == "empty":
                source.write_bytes(b"")
            elif source_shape == "oversize":
                source.write_bytes(b"x" * 65537)
            def snapshot():
                result = {}
                for path in [home, home / "www", site, data] + sorted(site.rglob("*")):
                    if path.is_symlink():
                        body = ("symlink", os.readlink(path))
                    elif path.is_file():
                        body = path.read_bytes()
                    else:
                        body = None
                    result[str(path.relative_to(home))] = (body, file_identity(path))
                return result
            before = snapshot()
            binaries = fixture / "bin"; binaries.mkdir()
            ssh = binaries / "ssh"; ssh.write_text(self.SSH); ssh.chmod(0o755)
            marker = fixture / "after-helper"
            audit = fixture / "audit.json"
            env = dict(os.environ, HOME=str(home), PATH=str(binaries) + os.pathsep + os.environ["PATH"],
                       HELPER_RACE="foreign_owner" if unsafe=="foreign_owner" else race,
                       HELPER_TARGET=str(target), HELPER_AUDIT=str(audit))
            results = []
            audits = []
            for _ in range(repeat):
                code = "set -euo pipefail\nopts=()\nremote=fixture\n" + shell
                code += "\nprintf '%s' reached > " + shlex.quote(str(marker)) + "\n"
                result = subprocess.run(["bash", "-c", code], cwd=fixture, env=env,
                                        text=True, capture_output=True, timeout=10)
                results.append(result)
                audits.append(json.loads(audit.read_text()) if audit.exists() else None)
            after = snapshot()
            return results, audits, before, after, marker.exists(), wanted

    def test_every_actual_caller_uses_one_identical_delivery_and_fixed_source_scope(self):
        programs = []
        for workflow, names in self.CALLERS.items():
            with self.subTest(workflow=workflow):
                _, paths, emitter, remote = shared_helper_shell(workflow)
                self.assertEqual(paths, ["v2/data/" + name for name in names])
                programs.append((emitter, remote))
                source = (ROOT / ".github/workflows" / workflow).read_text()
                for block in re.findall(r"(?m)^          scp [\s\S]*?(?<!\\)\n", source):
                    for name in names:
                        self.assertNotIn("v2/data/" + name, block)
        self.assertTrue(all(program == programs[0] for program in programs))

    def test_identical_delivery_twice_preserves_bytes_and_all_nine_stat_fields_without_mutators(self):
        for workflow, names in self.CALLERS.items():
            with self.subTest(workflow=workflow):
                results, audits, before, after, reached, _ = self.run_delivery(workflow, repeat=2)
                self.assertTrue(reached)
                self.assertEqual(after, before, "equal bytes must preserve files AND parent identities")
                for result, audit in zip(results, audits):
                    self.assertEqual(result.returncode, 0, result.stderr)
                    receipt = json.loads(result.stdout.split("ANYTOUR_SHARED_HELPERS ", 1)[1])
                    self.assertEqual(receipt, {"status": "delivered", "unchanged": list(names), "written": []})
                    self.assertEqual(audit["mutations"], [])

    def test_changed_and_absent_targets_install_exact_bytes_mode_and_only_fixed_scope(self):
        for workflow, names in self.CALLERS.items():
            for initial in ("different", "missing"):
                with self.subTest(workflow=workflow, initial=initial):
                    results, _, before, after, reached, wanted = self.run_delivery(workflow, initial=initial)
                    self.assertEqual(results[0].returncode, 0, results[0].stderr)
                    self.assertTrue(reached)
                    receipt = json.loads(results[0].stdout.split("ANYTOUR_SHARED_HELPERS ", 1)[1])
                    self.assertEqual(receipt, {"status": "delivered", "unchanged": [], "written": list(names)})
                    for name in names:
                        key = "www/anytoour.ru/data/" + name
                        self.assertEqual(after[key][0], wanted[name])
                        self.assertEqual(stat.S_IMODE(after[key][1][2]), 0o644)
                        self.assertEqual(after[key][1][3], 1)
                    allowed = {"www/anytoour.ru/data/" + name for name in names} | {"www/anytoour.ru/data"}
                    self.assertEqual({key: value for key, value in before.items() if key not in allowed},
                                     {key: value for key, value in after.items() if key not in allowed})
                    self.assertFalse(any(".anytour-shared-" in key for key in after))

    def test_unsafe_last_target_or_parent_blocks_the_whole_preflight_without_any_mutation(self):
        for workflow in self.CALLERS:
            for unsafe in ("symlink", "hardlink", "mode", "directory", "parent_mode", "parent_symlink", "foreign_owner"):
                with self.subTest(workflow=workflow, unsafe=unsafe):
                    results, audits, before, after, reached, _ = self.run_delivery(
                        workflow, initial="different", unsafe=unsafe)
                    self.assertNotEqual(results[0].returncode, 0)
                    self.assertFalse(reached)
                    self.assertEqual(after, before)
                    self.assertEqual(audits[0]["mutations"], [])
                    self.assertIn('"written": []', results[0].stdout)

    def test_unsafe_source_stops_before_remote_writes_and_following_work(self):
        for workflow in self.CALLERS:
            for shape in ("symlink", "hardlink", "mode", "empty", "oversize"):
                with self.subTest(workflow=workflow, source=shape):
                    results, audits, before, after, reached, _ = self.run_delivery(workflow, source_shape=shape)
                    self.assertNotEqual(results[0].returncode, 0)
                    self.assertFalse(reached)
                    self.assertEqual(after, before)
                    self.assertTrue(audits[0] is None or audits[0]["mutations"] == [])

    def test_second_inventory_race_preserves_foreign_state_and_runs_no_following_work(self):
        for workflow, names in self.CALLERS.items():
            with self.subTest(workflow=workflow):
                results, audits, before, after, reached, _ = self.run_delivery(workflow, race="inventory")
                self.assertNotEqual(results[0].returncode, 0)
                self.assertFalse(reached)
                self.assertIn("shared_helper_inventory_changed", results[0].stdout)
                key = "www/anytoour.ru/data/" + names[-1]
                self.assertEqual(after[key], (b"foreign concurrent content", tuple(audits[0]["foreign"])))
                self.assertEqual(audits[0]["mutations"], [])
                self.assertEqual({k: v for k, v in after.items() if k != key},
                                 {k: v for k, v in before.items() if k != key})

    def test_stage_write_failure_does_not_replace_targets_or_run_following_work(self):
        for workflow in self.CALLERS:
            with self.subTest(workflow=workflow):
                results, _, before, after, reached, _ = self.run_delivery(workflow, initial="different", race="stage_write_failure")
                self.assertNotEqual(results[0].returncode, 0)
                self.assertFalse(reached)
                self.assertEqual({key: value for key, value in before.items() if key != "www/anytoour.ru/data"},
                                 {key: value for key, value in after.items() if key != "www/anytoour.ru/data"})
                self.assertFalse(any(".anytour-shared-" in key for key in after))

    def test_commit_races_never_overwrite_foreign_bytes_or_admit_following_work(self):
        for workflow, names in self.CALLERS.items():
            for race, initial in (("commit_changed", "different"), ("commit_appeared", "missing")):
                with self.subTest(workflow=workflow, race=race):
                    results, audits, _, after, reached, wanted = self.run_delivery(workflow, initial=initial, race=race)
                    self.assertNotEqual(results[0].returncode, 0)
                    self.assertFalse(reached)
                    self.assertIsNotNone(audits[0]["foreign"], "the race must execute at the actual native rename boundary")
                    key = "www/anytoour.ru/data/" + names[-1]
                    self.assertEqual(after[key][0], b"foreign concurrent content")
                    # Restoring an exchanged inode necessarily changes ctime. Every
                    # other identity field and its foreign content must survive.
                    for index, field in enumerate(STAT_FIELDS):
                        if race == "commit_appeared" or field != "st_ctime_ns":
                            self.assertEqual(after[key][1][index], audits[0]["foreign"][index], field)
                    self.assertFalse(any(".anytour-shared-" in key for key in after))
                    # Delivery is per-file. A later conflict must report earlier
                    # writes honestly; it must not manufacture bundle atomicity.
                    receipt = json.loads(results[0].stdout.split("ANYTOUR_SHARED_HELPERS ", 1)[1])
                    self.assertEqual(receipt["status"], "failed")
                    self.assertTrue(set(names[:-1]).issubset(receipt["written"]))
                    for name in names[:-1]:
                        self.assertEqual(after["www/anytoour.ru/data/" + name][0], wanted[name])

    def test_missing_native_rename_support_fails_before_any_target_mutation(self):
        for workflow in self.CALLERS:
            with self.subTest(workflow=workflow):
                results, audits, before, after, reached, _ = self.run_delivery(
                    workflow, initial="different", race="unsupported_platform")
                self.assertNotEqual(results[0].returncode, 0)
                self.assertFalse(reached)
                self.assertIn("shared_helper_unsupported_platform", results[0].stdout)
                self.assertEqual(after, before)
                self.assertEqual(audits[0]["mutations"], [])

    def test_same_bytes_foreign_inode_after_commit_is_not_misreported_as_success(self):
        for workflow, names in self.CALLERS.items():
            with self.subTest(workflow=workflow):
                results, audits, _, after, reached, wanted = self.run_delivery(
                    workflow, initial="different", race="postcommit_same_bytes_replacement")
                self.assertNotEqual(results[0].returncode, 0)
                self.assertFalse(reached)
                self.assertIn("shared_helper_readback_identity", results[0].stdout)
                key = "www/anytoour.ru/data/" + names[-1]
                self.assertEqual(after[key], (wanted[names[-1]], tuple(audits[0]["foreign"])))
                # The displaced old inode is retained on a post-commit conflict;
                # cleanup must never delete an object whose ownership was lost.
                retained = [value for key, value in after.items() if ".anytour-shared-" in key]
                self.assertEqual(len(retained), 1)
                self.assertEqual(retained[0][0], b"previous installed source")

    def test_workflow_only_repair_cannot_trigger_hot_or_rollup_production_on_merge(self):
        push_paths = {
            "build-anytour-hot.yml": ("v2/data/build-hot-from-observations-v1.php", "v2/data/hot-tours-read-v1.php", "v2/hot/index.php"),
            "rollup-anytour-prices.yml": ("v2/data/rollup-price-daily-v1.php",),
        }
        schedules = {"build-anytour-hot.yml": "13 * * * *", "rollup-anytour-prices.yml": "17 1 * * *"}
        changed_paths = [".github/workflows/" + name for name in self.CALLERS]
        changed_paths += [".github/workflows/sync-anytour-catalog.yml",
                          "tests/local_tv_capture_workflow_test.py", "tests/monitor_price_hook_workflow_test.py"]
        for workflow, expected in push_paths.items():
            with self.subTest(workflow=workflow):
                source = (ROOT / ".github/workflows" / workflow).read_text()
                events = source.split("\nconcurrency:", 1)[0]
                def paths(event):
                    block = re.search(r"(?ms)^  " + event + r":\n(.*?)(?=^  [a-z_]+:|\Z)", events)
                    self.assertIsNotNone(block)
                    self.assertIn("    branches: [main]", block.group(1))
                    return re.findall(r"(?m)^      - '([^']+)'$", block.group(1))
                self.assertEqual(paths("push"), list(expected))
                self.assertEqual(paths("pull_request"), list(expected) + [".github/workflows/" + workflow])
                self.assertFalse(any(fnmatch.fnmatchcase(path, pattern) for path in changed_paths for pattern in paths("push")))
                self.assertTrue(all(any(fnmatch.fnmatchcase(path, pattern) for pattern in paths("push")) for path in expected))
                self.assertIn("  workflow_dispatch:\n", events)
                self.assertIn("cron: '" + schedules[workflow] + "'", events)
                self.assertIn("    if: github.event_name != 'pull_request'\n    needs: validate\n", source)
                self.assertIn("  cancel-in-progress: false\n", source)


class CatalogSyncCopyBoundary(unittest.TestCase):
    """Exercise the real catalog shell; SSH/PHP/SCP boundaries are local doubles."""
    CLI = (
        "catalog-hotel-sync-plan-v1.php", "migrate-departures-v1.php",
        "migrate-departure-countries-v1.php", "migrate-departure-flight-matrices-v1.php",
        "migrate-hotel-media-v1.php", "sync-departures-v1.php",
        "sync-departure-flight-matrices-v1.php", "validate-departure-flight-matrices-v1.php",
        "validate-departures-v1.php", "sync-catalog-v1.php", "db-doctor-v1.php",
    )
    SHARED = (
        "db-v1.php", "tourvisor-client-v1.php", "hotel-details-v1.php",
        "local-tv-catalog-v1.php", "price-observer-v1.php",
        "collect-hotel-details-v1.php", "local-tv-catalog-enabled.json",
    )

    def run_sync(self, country="", plan="NONE", dependency="present", config_mode=0o600, config_shape="regular"):
        workflow = (ROOT / ".github/workflows/sync-anytour-catalog.yml").read_text()
        scalar = workflow.split("      - name: Sync catalog on production\n", 1)[1].split("        run: |\n", 1)[1]
        shell = textwrap.dedent(scalar)
        with tempfile.TemporaryDirectory(prefix="catalog-copy-") as directory:
            fixture = Path(directory)
            binaries = fixture / "bin"; binaries.mkdir()
            source = fixture / "v2/data"; source.mkdir(parents=True)
            home = fixture / "home"; root = home / "www/anytoour.ru"
            data = root / "data"; data.mkdir(parents=True)
            config = root / "config.php"
            config.write_text("synthetic-config")
            config.chmod(config_mode)
            if config_shape == "symlink":
                saved = root / "synthetic-config-target"
                config.rename(saved); config.symlink_to(saved)
            elif config_shape == "hardlink":
                os.link(config, fixture / "synthetic-config-link")
            self.config_before = (config.read_bytes(), file_identity(config))
            (root / "api-v2.php").write_text("installed-api")
            for name in self.CLI + self.SHARED:
                (source / name).write_text("main:" + name)
            for name in self.SHARED:
                (data / name).write_text("installed:" + name)
            protected = [root / "api-v2.php"] + [data / name for name in self.SHARED]
            before = {str(p.relative_to(root)):p.read_bytes() for p in protected}
            if dependency == "missing":
                (data / "db-v1.php").unlink()
            if dependency == "symlink":
                (data / "db-v1.php").unlink()
                (data / "db-v1.php").symlink_to(data / "tourvisor-client-v1.php")
            scripts = {
                "ssh": "#!/usr/bin/env python3\nimport subprocess,sys\nraise SystemExit(subprocess.run(['bash','-c',sys.argv[-1]],input=sys.stdin.buffer.read()).returncode)\n",
                "scp": """#!/usr/bin/env python3
import json,os,shutil,sys
from pathlib import Path
args=sys.argv[1:]
Path(os.environ['CATALOG_COPY_LOG']).write_text(json.dumps(args))
target=Path(os.environ['HOME'])/'www/anytoour.ru/data'
for raw in args[:-1]:
    if raw.startswith('v2/data/'):
        path=Path(raw)
        if path.is_dir():
            for child in path.iterdir():
                shutil.copyfile(child,target/child.name)
        else:
            shutil.copyfile(path,target/path.name)
""",
                "php": """#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
args=sys.argv[1:]
with open(os.environ['CATALOG_PHP_LOG'],'a') as out:
    out.write(json.dumps(args)+'\\n')
if args and Path(args[0]).name=='catalog-hotel-sync-plan-v1.php':
    print(os.environ['CATALOG_TEST_PLAN'])
""",
            }
            for name, body in scripts.items():
                path=binaries/name;path.write_text(body);path.chmod(0o755)
            env=dict(os.environ, HOME=str(home), PATH=str(binaries)+os.pathsep+os.environ["PATH"],
                     RUNNER_TEMP=str(fixture), HOST_VALUE="fixture.invalid", USER_VALUE="fixture",
                     COUNTRY_VALUE=country, BATCH_SIZE="2", CATALOG_TEST_PLAN=plan,
                     CATALOG_COPY_LOG=str(fixture/"copy.json"), CATALOG_PHP_LOG=str(fixture/"php.jsonl"))
            result=subprocess.run(["bash","-c",shell],cwd=fixture,env=env,text=True,capture_output=True,timeout=10)
            copies=json.loads((fixture/"copy.json").read_text()) if (fixture/"copy.json").exists() else []
            calls=[json.loads(x) for x in (fixture/"php.jsonl").read_text().splitlines()] if (fixture/"php.jsonl").exists() else []
            after={str(p.relative_to(root)):p.read_bytes() for p in protected if p.is_file()}
            self.config_after = (config.read_bytes(), file_identity(config))
            return result,copies,calls,before,after

    def assert_boundary(self, result, copies, before, after):
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(before,after,"catalog sync must preserve ALL installed shared bytes")
        copied=[x for x in copies if x.startswith("v2/data/")]
        self.assertEqual(copied,["v2/data/"+name for name in self.CLI])
        self.assertNotIn("-r",copies)

    def test_auto_none_preserves_all_shared_files_and_skips_hotel_sync(self):
        result,copies,calls,before,after=self.run_sync()
        self.assert_boundary(result,copies,before,after)
        writers=[Path(args[0]).name for args in calls if args and not args[0].startswith("-")]
        self.assertNotIn("sync-catalog-v1.php",writers)
        self.assertNotIn("db-doctor-v1.php",writers)
        self.assertIn("catalog-hotel-sync-plan-v1.php",writers)

    def test_explicit_country_and_batch_keep_existing_cli_order_and_arguments(self):
        result,copies,calls,before,after=self.run_sync(country="4,6")
        self.assert_boundary(result,copies,before,after)
        writers=[Path(args[0]).name for args in calls if args and not args[0].startswith("-")]
        self.assertEqual(writers,[name for name in self.CLI if name!="catalog-hotel-sync-plan-v1.php"])
        sync=next(args for args in calls if Path(args[0]).name=="sync-catalog-v1.php")
        self.assertEqual(sync[1:],["--country=4,6"])

    def test_auto_plan_keeps_existing_bounded_batch_argument(self):
        result,copies,calls,before,after=self.run_sync(plan="4")
        self.assert_boundary(result,copies,before,after)
        planner=next(args for args in calls if Path(args[0]).name=="catalog-hotel-sync-plan-v1.php")
        self.assertEqual(planner[1:],["--limit=2"])
        sync=next(args for args in calls if Path(args[0]).name=="sync-catalog-v1.php")
        self.assertEqual(sync[1:],["--country=4"])

    def test_missing_or_symlinked_shared_dependency_stops_before_copy_or_php(self):
        for dependency in ("missing","symlink"):
            with self.subTest(dependency=dependency):
                result,copies,calls,_,_=self.run_sync(dependency=dependency)
                self.assertNotEqual(result.returncode,0)
                self.assertEqual(copies,[])
                self.assertEqual(calls,[])

    def test_existing_private_mode_600_is_a_full_identity_noop(self):
        result,copies,_,before,after=self.run_sync(config_mode=0o600)
        self.assert_boundary(result,copies,before,after)
        self.assertIn("ANYTOUR_PRIVATE_CONFIG_MODE_OK",result.stdout)
        self.assertEqual(self.config_after,self.config_before,
                         "same mode must not change bytes or any of the nine identity fields")

    def test_safe_private_mode_is_tightened_without_replacing_or_reading_its_contents(self):
        result,copies,_,before,after=self.run_sync(config_mode=0o644)
        self.assert_boundary(result,copies,before,after)
        self.assertEqual(self.config_after[0],self.config_before[0])
        self.assertEqual(stat.S_IMODE(self.config_after[1][2]),0o600)
        for index,field in enumerate(STAT_FIELDS):
            if field not in ("st_mode","st_ctime_ns"):
                self.assertEqual(self.config_after[1][index],self.config_before[1][index],field)
        self.assertNotIn("synthetic-config",result.stdout+result.stderr)

    def test_unsafe_private_config_stops_before_php_without_metadata_or_shared_file_changes(self):
        for config_shape,config_mode in (("symlink",0o600),("hardlink",0o600),("regular",0o666),("regular",0o700)):
            with self.subTest(shape=config_shape,mode=oct(config_mode)):
                result,_,calls,before,after=self.run_sync(config_shape=config_shape,config_mode=config_mode)
                self.assertNotEqual(result.returncode,0)
                self.assertEqual(calls,[])
                self.assertIn("ANYTOUR_PRIVATE_CONFIG_MODE_REJECTED",result.stderr)
                self.assertEqual(self.config_after,self.config_before)
                self.assertEqual(after,before)
                self.assertNotIn("synthetic-config",result.stdout+result.stderr)

    def run_private_mode_guard(self, mode=0o600, fault=""):
        workflow = (ROOT / ".github/workflows/sync-anytour-catalog.yml").read_text()
        block = workflow.split("python3 - <<'PY'\n", 1)[1].split("          PY\n", 1)[0]
        actual = textwrap.dedent(block)
        ast.parse(actual)
        driver = r'''
import json, os, pathlib, stat, sys, types
config = pathlib.Path.home() / 'www/anytoour.ru/config.php'
fields = ('st_dev', 'st_ino', 'st_mode', 'st_nlink', 'st_size', 'st_mtime_ns', 'st_ctime_ns', 'st_uid', 'st_gid')
result = {'chmods': 0, 'reads': 0, 'foreign': None}
def identity(path):
    value = path.lstat()
    return [getattr(value, field) for field in fields]
original_read, original_fchmod, original_fstat = os.read, os.fchmod, os.fstat
def no_content_read(*args):
    result['reads'] += 1
    raise AssertionError('synthetic private bytes must not be read')
def checked_fchmod(fd, mode):
    result['chmods'] += 1
    if os.environ['MODE_FAULT'] == 'replace_before_chmod':
        other = config.parent / 'foreign-config'
        other.write_bytes(b'synthetic foreign config')
        other.chmod(0o644)
        os.replace(other, config)
        result['foreign'] = identity(config)
    return original_fchmod(fd, mode)
def checked_fstat(fd):
    value = original_fstat(fd)
    if os.environ['MODE_FAULT'] == 'parent_mode_changed' and stat.S_ISREG(value.st_mode):
        # The initial directory identity has already been captured. This is
        # a real chmod on the synthetic parent, not a fabricated stat result.
        config.parent.chmod(0o777)
    if os.environ['MODE_FAULT'] == 'foreign_owner' and stat.S_ISREG(value.st_mode):
        row = {field: getattr(value, field) for field in fields}
        row['st_uid'] += 1  # An unreadable owner state is supplied, never a secret.
        return types.SimpleNamespace(**row)
    return value
os.read, os.fchmod, os.fstat = no_content_read, checked_fchmod, checked_fstat
try:
    exec(compile(sys.argv[1], '<actual-private-mode-guard>', 'exec'), {'__name__': '__main__'})
finally:
    os.read, os.fchmod, os.fstat = original_read, original_fchmod, original_fstat
    pathlib.Path(os.environ['MODE_AUDIT']).write_text(json.dumps(result))
'''
        with tempfile.TemporaryDirectory(prefix="private-mode-") as directory:
            fixture = Path(directory)
            home = fixture / "home"
            site = home / "www/anytoour.ru"; site.mkdir(parents=True)
            config = site / "config.php"; config.write_bytes(b"synthetic private config"); config.chmod(mode)
            before = (config.read_bytes(), file_identity(config))
            audit = fixture / "audit.json"
            env = dict(os.environ, HOME=str(home), MODE_FAULT=fault, MODE_AUDIT=str(audit))
            result = subprocess.run([sys.executable,"-c",driver,actual],env=env,text=True,capture_output=True,timeout=10)
            after = (config.read_bytes(), file_identity(config))
            return result,json.loads(audit.read_text()),before,after

    def test_actual_private_guard_never_reads_content_and_calls_chmod_only_when_needed(self):
        for mode, expected_chmods in ((0o600,0),(0o644,1)):
            with self.subTest(mode=oct(mode)):
                result,audit,before,after=self.run_private_mode_guard(mode=mode)
                self.assertEqual(result.returncode,0,result.stderr)
                self.assertEqual(audit,{"chmods":expected_chmods,"reads":0,"foreign":None})
                self.assertEqual(result.stdout,"ANYTOUR_PRIVATE_CONFIG_MODE_OK\n")
                self.assertEqual(after[0],before[0])
                if mode==0o600:
                    self.assertEqual(after,before)

    def test_actual_private_guard_rejects_foreign_owner_path_replacement_and_parent_mode_race(self):
        for fault in ("foreign_owner","replace_before_chmod","parent_mode_changed"):
            with self.subTest(fault=fault):
                result,audit,before,after=self.run_private_mode_guard(
                    mode=0o600 if fault=="parent_mode_changed" else 0o644,fault=fault)
                self.assertNotEqual(result.returncode,0)
                self.assertEqual(result.stderr,"ANYTOUR_PRIVATE_CONFIG_MODE_REJECTED\n")
                self.assertEqual(result.stdout,"")
                self.assertEqual(audit["reads"],0)
                if fault in ("foreign_owner","parent_mode_changed"):
                    self.assertEqual(audit["chmods"],0)
                    self.assertEqual(after,before)
                else:
                    self.assertEqual(after,(b"synthetic foreign config",tuple(audit["foreign"])))

    def test_push_and_pr_only_validate_while_existing_schedule_is_preserved(self):
        workflow=(ROOT/".github/workflows/sync-anytour-catalog.yml").read_text()
        live=workflow.split("  sync-catalog:\n",1)[1]
        self.assertIn("if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'",live)
        self.assertIn("cron: '20 2,8,14,20 * * *'",workflow)
        self.assertIn("  pull_request:\n",workflow)
        self.assertIn("run: python3 tests/local_tv_capture_workflow_test.py",workflow)
        self.assertIn("ref: 8af3e190588bf61b067f81838aec3f0201205ec9",workflow)
        self.assertNotIn("\nconcurrency:\n",workflow)
        self.assertIn("    concurrency:\n      group: anytour-catalog-sync\n      cancel-in-progress: false",live)
        self.assertNotIn("-r v2/data/.",live)


class SeasonalSharedHelperBoundary(unittest.TestCase):
    """Run the real seasonal copy shell against a fully inert remote directory."""
    CLI = (
        "price-segment-v1.php", "collect-seo-seasonal-preview-inventory-v1.php",
        "build-seo-offer-snapshots-v1.php", "inspect-seo-seasonal-identities-v1.php",
    )
    SHARED = ("db-v1.php", "tourvisor-client-v1.php", "price-observer-v1.php")

    @staticmethod
    def workflow():
        return (ROOT / ".github/workflows/collect-seo-seasonal-live-evidence.yml").read_text()

    def run_refresh(self, dependency=None, shape=None, ssh_failure=False,
                    copy_failure=False, php_failure=False):
        step = self.workflow().split(
            "      - name: Refresh exact seasonal review observations\n", 1)[1]
        scalar = step.split("        run: |\n", 1)[1]
        lines = []
        for line in scalar.splitlines():
            if line.strip() and not line.startswith("          "):
                break
            lines.append(line)
        shell = textwrap.dedent("\n".join(lines))
        with tempfile.TemporaryDirectory(prefix="seasonal-copy-") as directory:
            fixture = Path(directory)
            binaries = fixture / "bin"; binaries.mkdir()
            source = fixture / "v2/data"; source.mkdir(parents=True)
            installed = fixture / "installed"; installed.mkdir()
            for name in self.CLI + self.SHARED:
                (source / name).write_text("main:" + name)
            for name in self.SHARED:
                (installed / name).write_text("installed:" + name)
            if dependency:
                target = installed / dependency
                target.unlink()
                if shape == "symlink":
                    target.symlink_to(source / dependency)
                elif shape == "directory":
                    target.mkdir()
                elif shape == "empty":
                    target.touch()
            scripts = {
                "ssh": """#!/usr/bin/env python3
import os,subprocess,sys
if os.environ['SEASONAL_TEST_SSH_FAILURE']=='1':
    raise SystemExit(73)
# Redirect only the inert SSH boundary; never alter the process HOME.
command=sys.argv[-1].replace('$HOME/www/anytoour.ru/data',os.environ['SEASONAL_TEST_DATA'])
raise SystemExit(subprocess.run(['bash','-c',command]).returncode)
""",
                "scp": """#!/usr/bin/env python3
import json,os,shutil,sys
from pathlib import Path
args=sys.argv[1:]
Path(os.environ['SEASONAL_TEST_COPY_LOG']).write_text(json.dumps(args))
if os.environ['SEASONAL_TEST_COPY_FAILURE']=='1':
    raise SystemExit(74)
target=Path(os.environ['SEASONAL_TEST_DATA'])
for raw in args[:-1]:
    if raw.startswith('v2/data/'):
        path=Path(raw)
        shutil.copyfile(path,target/path.name)
""",
                "php": """#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
args=sys.argv[1:]
with open(os.environ['SEASONAL_TEST_PHP_LOG'],'a') as out:
    out.write(json.dumps(args)+'\\n')
if os.environ['SEASONAL_TEST_PHP_FAILURE']=='1':
    raise SystemExit(75)
""",
            }
            for name, body in scripts.items():
                path=binaries/name; path.write_text(body); path.chmod(0o755)
            env=dict(os.environ, PATH=str(binaries)+os.pathsep+os.environ["PATH"],
                     RUNNER_TEMP=str(fixture), HOST_VALUE="fixture.invalid", USER_VALUE="fixture",
                     GITHUB_OUTPUT=str(fixture/"output"),
                     SEASONAL_TEST_DATA=str(installed),
                     SEASONAL_TEST_COPY_LOG=str(fixture/"copy.json"),
                     SEASONAL_TEST_PHP_LOG=str(fixture/"php.jsonl"),
                     SEASONAL_TEST_SSH_FAILURE="1" if ssh_failure else "0",
                     SEASONAL_TEST_COPY_FAILURE="1" if copy_failure else "0",
                     SEASONAL_TEST_PHP_FAILURE="1" if php_failure else "0")
            result=subprocess.run(["bash","-c",shell],cwd=fixture,env=env,
                                  text=True,capture_output=True,timeout=10)
            copies=json.loads((fixture/"copy.json").read_text()) if (fixture/"copy.json").exists() else []
            calls=[json.loads(x) for x in (fixture/"php.jsonl").read_text().splitlines()] if (fixture/"php.jsonl").exists() else []
            output=(fixture/"output").read_text() if (fixture/"output").exists() else ""
            after={name:(installed/name).read_bytes() for name in self.CLI+self.SHARED
                   if (installed/name).is_file()}
            return result,copies,calls,output,after

    def test_actual_shell_copies_only_owned_cli_and_preserves_installed_shared_bytes(self):
        result,copies,calls,output,after=self.run_refresh()
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(output,"exit_code=0\n")
        self.assertEqual([x for x in copies if x.startswith("v2/data/")],
                         ["v2/data/"+name for name in self.CLI])
        self.assertNotIn("-r",copies)
        for name in self.SHARED:
            self.assertEqual(after[name],("installed:"+name).encode())
        for name in self.CLI:
            self.assertEqual(after[name],("main:"+name).encode())
        self.assertEqual([(Path(args[1]).name,args[2:]) for args in calls if args[0]=="-l"],
                         [("price-segment-v1.php",[]),
                          ("collect-seo-seasonal-preview-inventory-v1.php",[])])
        writers=[(Path(args[0]).name,args[1:]) for args in calls if not args[0].startswith("-")]
        self.assertEqual(writers,[
            ("collect-seo-seasonal-preview-inventory-v1.php",[]),
            ("build-seo-offer-snapshots-v1.php",
             ["--fresh-hours=72","--expires-hours=72","--offers-per-page=10"])])

    def test_missing_empty_directory_or_symlinked_dependency_blocks_copy_and_php(self):
        for name in self.SHARED:
            for shape in ("missing","empty","directory","symlink"):
                with self.subTest(dependency=name,shape=shape):
                    result,copies,calls,output,_=self.run_refresh(dependency=name,shape=shape)
                    self.assertEqual(result.returncode,0,result.stderr)
                    self.assertEqual(output,"exit_code=2\n")
                    self.assertEqual(copies,[])
                    self.assertEqual(calls,[])

    def test_guard_and_copy_failures_are_reported_without_targeted_php(self):
        for kind,code in (("ssh_failure",73),("copy_failure",74)):
            with self.subTest(kind=kind):
                result,copies,calls,output,_=self.run_refresh(**{kind:True})
                self.assertEqual(result.returncode,0,result.stderr)
                self.assertEqual(output,"exit_code="+str(code)+"\n")
                self.assertEqual(calls,[])
                if kind=="ssh_failure":
                    self.assertEqual(copies,[])

    def test_php_failure_keeps_existing_step_output_for_final_rejection(self):
        result,_,calls,output,_=self.run_refresh(php_failure=True)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(output,"exit_code=75\n")
        self.assertEqual(len(calls),1)
        self.assertEqual(calls[0][0],"-l")
        self.assertIn('test "$targeted_code" = "0"',self.workflow())

    def test_pr_and_push_validate_without_live_or_concurrency_side_effects(self):
        workflow=self.workflow()
        live=workflow.split("  live-seasonal-evidence:\n",1)[1]
        self.assertIn("if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'",live)
        self.assertNotIn("if: github.event_name != 'pull_request'",live)
        self.assertNotIn("\nconcurrency:\n",workflow)
        self.assertIn("    concurrency:\n      group: seo-seasonal-live-evidence\n      cancel-in-progress: true",live)
        self.assertIn("cron: '27 */6 * * *'",workflow)
        self.assertEqual(workflow.count("      - 'tests/local_tv_capture_workflow_test.py'"),2)
        contract=workflow.split("  contract:\n",1)[1].split("  live-seasonal-evidence:\n",1)[0]
        self.assertIn("python3 tests/local_tv_capture_workflow_test.py SeasonalSharedHelperBoundary",contract)
        self.assertNotIn("secrets.",contract)


if __name__ == "__main__":
    unittest.main(verbosity=2)
