#!/usr/bin/env python3
"""Exercise feed admission and the real copy shell with local SSH/SCP/PHP doubles."""
from __future__ import annotations

import ast
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ".github/workflows/build-yandex-country-feeds.yml"


def production_condition():
    job = (ROOT / WORKFLOW).read_text().split("  production:\n", 1)[1]
    raw = job.split("    if: ", 1)[1]
    first, *lines = raw.splitlines()
    if first.strip() not in (">-", ">", "|", "|-"):
        return first.strip()
    folded = []
    for line in lines:
        if not line.startswith("      "):
            break
        folded.append(line.strip())
    return " ".join(folded)


def admitted(event, upstream="push", conclusion="success", branch="main"):
    expression = production_condition()
    values = {
        "github.event.workflow_run.head_branch": branch,
        "github.event.workflow_run.conclusion": conclusion,
        "github.event.workflow_run.event": upstream,
        "github.event_name": event,
    }
    for name, value in values.items():
        expression = expression.replace(name, repr(value))
    expression = expression.replace("&&", " and ").replace("||", " or ")
    tree = ast.parse(expression, mode="eval")
    allowed = (ast.Expression, ast.BoolOp, ast.And, ast.Or, ast.Compare, ast.Eq, ast.NotEq, ast.Constant)
    if any(not isinstance(node, allowed) for node in ast.walk(tree)):
        raise AssertionError("Unsupported production expression; do not silently approximate it")
    return bool(eval(compile(tree, WORKFLOW, "eval"), {"__builtins__": {}}, {}))


class FeedProductionBoundary(unittest.TestCase):
    SHARED = ("api-v2.php", "data/db-v1.php", "data/tourvisor-client-v1.php",
              "data/hotel-details-v1.php", "data/local-tv-catalog-v1.php",
              "data/price-observer-v1.php", "data/collect-hotel-details-v1.php",
              "data/local-tv-catalog-enabled.json")

    def test_ci_completion_never_admits_production(self):
        for event in ("pull_request", "push"):
            with self.subTest(event=event):
                self.assertFalse(admitted(event))
                self.assertFalse(admitted("workflow_run", upstream=event))

    def test_existing_routine_events_require_successful_main_upstream(self):
        for event in ("schedule", "workflow_dispatch"):
            self.assertTrue(admitted(event))
            self.assertTrue(admitted("workflow_run", upstream=event))
            for conclusion in ("failure", "cancelled", "skipped", ""):
                self.assertFalse(admitted("workflow_run", upstream=event, conclusion=conclusion))
            self.assertFalse(admitted("workflow_run", upstream=event, branch="foreign-preview"))
        for event in ("issue_comment", "workflow_run", "repository_dispatch"):
            self.assertFalse(admitted(event))

    def run_shell(self, dependency="present"):
        source = (ROOT / WORKFLOW).read_text()
        step = source.split("      - name: Build production feeds from snapshots\n", 1)[1]
        shell = textwrap.dedent(step.split("        run: |\n", 1)[1])
        with tempfile.TemporaryDirectory(prefix="feed-boundary-") as directory:
            fixture = Path(directory)
            binaries = fixture / "bin"; binaries.mkdir()
            data = fixture / "v2/data"; data.mkdir(parents=True)
            (data / "db-v1.php").write_text("main-db-must-not-copy")
            (data / "build-yandex-country-feeds-v1.php").write_text("fixture-builder")
            home = fixture / "home"; root = home / "www/anytoour.ru"
            for name in self.SHARED:
                path = root / name; path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text("installed:" + name)
            (root / "config.php").write_text("synthetic-config")
            before = {name:(root / name).read_bytes() for name in self.SHARED}
            if dependency != "present":
                (root / "data/db-v1.php").unlink()
                if dependency == "symlink":
                    (root / "data/db-v1.php").symlink_to(root / "data/tourvisor-client-v1.php")
            scripts = {
                "ssh": "#!/usr/bin/env python3\nimport subprocess,sys\nraise SystemExit(subprocess.run(['bash','-c',sys.argv[-1]]).returncode)\n",
                "scp": """#!/usr/bin/env python3
import json,os,shutil,sys
from pathlib import Path
args=sys.argv[1:]
Path(os.environ['FEED_COPY_LOG']).write_text(json.dumps(args))
target=Path(os.environ['HOME'])/'www/anytoour.ru/data'
for raw in args[:-1]:
    if raw.startswith('v2/data/'):
        shutil.copyfile(raw,target/Path(raw).name)
""",
                "php": """#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
args=sys.argv[1:]
with open(os.environ['FEED_PHP_LOG'],'a') as out:
    out.write(json.dumps(args)+'\\n')
if args and not args[0].startswith('-'):
    output=Path(os.environ['HOME'])/'www/anytoour.ru/feed/generated'
    output.mkdir(parents=True,exist_ok=True)
    (output/'countries.yml').write_text('<yml_catalog/>')
    (output/'manifest.json').write_text('{"rows": 1}')
""",
            }
            for name, body in scripts.items():
                path = binaries / name; path.write_text(body); path.chmod(0o755)
            env = dict(os.environ, HOME=str(home), PATH=str(binaries)+os.pathsep+os.environ["PATH"],
                       RUNNER_TEMP=str(fixture), HOST_VALUE="fixture.invalid", USER_VALUE="fixture",
                       FEED_COPY_LOG=str(fixture/"copy.json"), FEED_PHP_LOG=str(fixture/"php.jsonl"))
            result = subprocess.run(["bash", "-c", shell], cwd=fixture, env=env,
                                    text=True, capture_output=True, timeout=10)
            copies = json.loads((fixture/"copy.json").read_text()) if (fixture/"copy.json").exists() else []
            calls = [json.loads(s) for s in (fixture/"php.jsonl").read_text().splitlines()] if (fixture/"php.jsonl").exists() else []
            after = {name:(root/name).read_bytes() for name in self.SHARED if (root/name).is_file()}
            return result, copies, calls, before, after

    def test_real_shell_preserves_all_shared_bytes_and_only_copies_builder(self):
        result, copies, calls, before, after = self.run_shell()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(before, after, "feed build must preserve every installed shared runtime byte")
        self.assertEqual([a for a in copies if a.startswith("v2/data/")], ["v2/data/build-yandex-country-feeds-v1.php"])
        writers = [a for a in calls if a and not a[0].startswith("-")]
        self.assertEqual(len(writers), 1)
        self.assertEqual(Path(writers[0][0]).name, "build-yandex-country-feeds-v1.php")
        self.assertTrue(writers[0][1].endswith("/feed/generated"))

    def test_missing_or_symlinked_db_stops_before_copy_and_php(self):
        for dependency in ("missing", "symlink"):
            with self.subTest(dependency=dependency):
                result, copies, calls, _, _ = self.run_shell(dependency)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(copies, [])
                self.assertEqual(calls, [])

    def test_only_production_occupies_existing_operational_group(self):
        source = (ROOT / WORKFLOW).read_text()
        self.assertNotIn("\nconcurrency:\n", source)
        production = source.split("  production:\n", 1)[1]
        self.assertIn("    concurrency:\n      group: anytour-yandex-country-feeds\n      cancel-in-progress: true", production)
        self.assertIn("cron: '25 1,7,13,19 * * *'", source)
        self.assertIn("workflows: ['Collect AnyTour tour observations']", source)
        self.assertIn("run: python3 tests/yandex_country_feeds_workflow_test.py", source)


if __name__ == "__main__":
    unittest.main(verbosity=2)
