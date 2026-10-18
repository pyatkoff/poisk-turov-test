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
SOURCE = Path(os.environ.get("LOCAL_TV_CAPTURE_SOURCE_ROOT", str(ROOT / "local-tv-capture-source")))


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
                shutil.copyfile(SOURCE / "v2/data" / name, fixture_data / name)
            # Only this synthetic private config is loaded by the real PHP flag probe.
            (task_root / "config.php").write_text(
                "<?php define('ANYTOUR_LOCAL_TV_CATALOG_ENABLED', " + ("true" if flag == "local" else "false") + ");")
            real_php = shutil.which("php")
            if real_php is None:
                raise RuntimeError("PHP is required for the actual config/feature-flag probe")
            scripts = {
                "scp": "#!/usr/bin/env python3\nimport json, os, sys\nfrom pathlib import Path\nPath(os.environ['LOCAL_WORKFLOW_COPY_LOG']).write_text(json.dumps(sys.argv[1:]))\n",
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
                       LOCAL_WORKFLOW_TEST_LOG=str(log), LOCAL_WORKFLOW_COPY_LOG=str(task_root / "copies.json"), LOCAL_WORKFLOW_FLAG=flag,
                       LOCAL_WORKFLOW_REAL_PHP=real_php, LOCAL_WORKFLOW_FIXTURE_DATA=str(fixture_data),
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
        self.assertEqual([name for name, _ in self.writers(calls)], ["collect-hotel-details-v1.php"])
        self.assertIn("--candidate-scope=local", self.writers(calls)[0][1])
        self.assertIn("--http-budget=0", self.writers(calls)[0][1])
        for name in ("db-v1.php", "hotel-details-v1.php", "local-tv-catalog-v1.php", "tourvisor-client-v1.php", "collect-hotel-details-v1.php"):
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


class CopyOwnerPins(unittest.TestCase):
    def test_both_daily_copy_owners_pin_one_exact_reviewed_source_and_never_run_on_push(self):
        for name in ("collect-hotel-details.yml", "collect-anytour-tours.yml"):
            text=(ROOT / ".github/workflows" / name).read_text()
            self.assertIn("ref: 8af3e190588bf61b067f81838aec3f0201205ec9", text)
            live=text.split("  collect-production:\n",1)[1]
            self.assertIn("if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'", live)
            self.assertNotIn("if: github.event_name != 'pull_request'", live)
            for dependency in ("db-v1.php", "hotel-details-v1.php", "local-tv-catalog-v1.php"):
                self.assertIn("local-tv-source/v2/data/"+dependency, live)
        tour=(ROOT / ".github/workflows/collect-anytour-tours.yml").read_text()
        self.assertIn("local-tv-source/v2/data/price-observer-v1.php",tour)
        self.assertNotIn("            v2/data/price-observer-v1.php",tour)
        self.assertIn("cron: '12 * * * *'",tour)
        self.assertIn("cron: '35 3 * * *'",(ROOT / ".github/workflows/collect-hotel-details.yml").read_text())


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

    def run_sync(self, country="", plan="NONE", dependency="present"):
        workflow = (ROOT / ".github/workflows/sync-anytour-catalog.yml").read_text()
        scalar = workflow.split("      - name: Sync catalog on production\n", 1)[1].split("        run: |\n", 1)[1]
        shell = textwrap.dedent(scalar)
        with tempfile.TemporaryDirectory(prefix="catalog-copy-") as directory:
            fixture = Path(directory)
            binaries = fixture / "bin"; binaries.mkdir()
            source = fixture / "v2/data"; source.mkdir(parents=True)
            home = fixture / "home"; root = home / "www/anytoour.ru"
            data = root / "data"; data.mkdir(parents=True)
            (root / "config.php").write_text("synthetic-config")
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
                "ssh": "#!/usr/bin/env python3\nimport subprocess,sys\nraise SystemExit(subprocess.run(['bash','-c',sys.argv[-1]]).returncode)\n",
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
