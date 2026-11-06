#!/usr/bin/env python3
"""Offline regressions for SEO trigger admission; no PHP, SSH, HTTP or database work."""

import contextlib
import copy
import importlib.util
import io
import json
import os
import re
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest import mock


ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("seo_evidence_gate", ROOT / "scripts/deploy/seo_evidence_gate.py")
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)
REPOSITORY = "pyatkoff/poisk-turov-test"
SHA = "a" * 40


def parent_fixture(target, conclusion="success"):
    name, workflow_path, job_name, step_name, _ = gate.PARENTS[target]
    run = {
        "id": 41, "run_attempt": 2, "workflow_id": 17, "name": name, "path": workflow_path,
        "event": "push", "head_branch": "main", "head_sha": SHA,
        "status": "completed", "conclusion": "success",
        "repository": {"full_name": REPOSITORY}, "head_repository": {"full_name": REPOSITORY},
    }
    event = {"action": "completed", "repository": {"full_name": REPOSITORY},
             "workflow_run": copy.deepcopy(run)}
    jobs = [{"id": 51, "name": job_name, "run_id": 41, "run_attempt": 2,
             "head_branch": "main", "head_sha": SHA, "status": "completed", "conclusion": "success",
             "steps": [{"name": step_name, "status": "completed", "conclusion": conclusion}]}]
    return event, run, jobs


def workflow_jobs(filename):
    """Read only job/step conditions from the two fixed workflow layouts."""
    workflow_root = Path(os.environ.get("SEO_EVIDENCE_WORKFLOW_ROOT", str(ROOT)))
    source = (workflow_root / ".github/workflows" / filename).read_text(encoding="utf-8")
    jobs, job, step, in_jobs = {}, None, None, False
    for line in source.splitlines():
        if line == "jobs:":
            in_jobs = True
        if not in_jobs:
            continue
        match = re.fullmatch(r"  ([a-z0-9-]+):", line)
        if match:
            job = jobs.setdefault(match[1], {"steps": []})
            step = None
        elif job is not None and line.startswith("    if: "):
            job["if"] = line[8:]
        elif job is not None and line.startswith("      - name: "):
            step = {"name": line[14:]}
            job["steps"].append(step)
        elif job is not None and line.startswith("      - uses: "):
            step = {"name": line[14:]}
            job["steps"].append(step)
        elif step is not None and line.startswith("        if: "):
            step["if"] = line[12:]
    return jobs


def admitted(node, result, event="workflow_run"):
    # The exact supported expressions make a newly unrecognised condition fail the test.
    expressions = {
        "": True,
        "github.event_name != 'workflow_run' || github.event.workflow_run.conclusion == 'success'": True,
        "github.event_name != 'pull_request' && (github.event_name != 'workflow_run' || github.event.workflow_run.conclusion == 'success')": event != "pull_request",
        "github.event_name != 'pull_request' && needs.fast-contract.outputs.run_collection == 'true'": event != "pull_request" and result["run_collection"],
        "steps.gate.outputs.run_contract == 'true'": result["run_contract"],
        "steps.gate.outputs.run_collection == 'true'": result["run_collection"],
        "steps.gate.outputs.run_guard_tests == 'true'": result["run_guard_tests"],
        "always()": True,
    }
    condition = node.get("if", "")
    if condition not in expressions:
        raise AssertionError("Unrecognised workflow condition: " + condition)
    return expressions[condition]


class SeoEvidenceGateTest(unittest.TestCase):
    def test_files_only_parent_admits_no_old_work(self):
        """Also runnable against baseline YAML without executing any embedded command."""
        dossier = workflow_jobs("collect-seo-core-hotel-review-dossier.yml")
        pilot = workflow_jobs("collect-seo-hotel-pilot-live-evidence.yml")
        self.assertEqual(set(dossier), {"collect"})
        self.assertEqual(set(pilot), {"fast-contract", "live-evidence"})
        event, run, jobs = parent_fixture("dossier", "skipped")
        denied = gate.classify_parent("dossier", event, REPOSITORY, run, jobs)
        old_dossier_steps = {
            "Validate review-only dossier tooling", "Prepare SSH key",
            "Collect fresh production review dossier", "Fail closed unless all 500 records remain review-only",
            "Upload review dossier artifact",
        }
        seen = {step["name"] for step in dossier["collect"]["steps"]}
        self.assertTrue(old_dossier_steps <= seen)
        self.assertEqual([step["name"] for step in dossier["collect"]["steps"]
                          if step["name"] in old_dossier_steps and admitted(step, denied)], [])
        # A successful no-op dossier must not authorize either old pilot smokes or its always() steps.
        event, run, jobs = parent_fixture("pilot", "skipped")
        denied = gate.classify_parent("pilot", event, REPOSITORY, run, jobs)
        fast = pilot["fast-contract"]["steps"]
        old = next(step for step in fast if step["name"] == "Validate review-only live evidence collector")
        self.assertFalse(admitted(old, denied))
        self.assertFalse(admitted(pilot["live-evidence"], denied))
        self.assertTrue(any(step.get("if") == "always()" for step in pilot["live-evidence"]["steps"]))
        self.assertLess(next(i for i, step in enumerate(fast) if step["name"] == "Admit only proven SEO evidence work"), fast.index(old))

    def test_exact_completed_parent_attempt_and_required_step(self):
        for target in gate.PARENTS:
            event, run, jobs = parent_fixture(target)
            run["path"] += "@main"
            self.assertTrue(gate.classify_parent(target, event, REPOSITORY, run, jobs)["run_collection"])
            # The attempt endpoint binds older API responses that omit the optional job run_attempt.
            del jobs[0]["run_attempt"]
            self.assertTrue(gate.classify_parent(target, event, REPOSITORY, run, jobs)["run_collection"])
            for conclusion in ("skipped", "failure", None):
                jobs[0]["steps"][0]["conclusion"] = conclusion
                self.assertFalse(gate.classify_parent(target, event, REPOSITORY, run, jobs)["run_collection"])
            jobs[0]["steps"] = []
            self.assertFalse(gate.classify_parent(target, event, REPOSITORY, run, jobs)["run_contract"])
            event, run, jobs = parent_fixture(target)
            event["workflow_run"]["event"] = run["event"] = "pull_request"
            self.assertFalse(gate.classify_parent(target, event, REPOSITORY, run, jobs)["run_collection"])
        for location, field, value in (
            ("run", "id", 42), ("run", "run_attempt", 1), ("run", "head_sha", "b" * 40),
            ("run", "workflow_id", 99), ("run", "path", ".github/workflows/other.yml"),
            ("run", "repository", {"full_name": "foreign/repository"}),
            ("run", "head_repository", {"full_name": "foreign/repository"}),
            ("run", "head_branch", "topic"), ("job", "run_id", 42),
            ("job", "run_attempt", 1), ("job", "head_sha", "b" * 40),
        ):
            with self.subTest(location=location, field=field):
                event, run, jobs = parent_fixture("dossier")
                (run if location == "run" else jobs[0])[field] = value
                with self.assertRaises(gate.GateError):
                    gate.classify_parent("dossier", event, REPOSITORY, run, jobs)
        event, run, jobs = parent_fixture("dossier")
        with self.assertRaises(gate.GateError):
            gate.classify_parent("dossier", event, REPOSITORY, run, jobs + jobs)
        jobs[0]["steps"] *= 2
        with self.assertRaises(gate.GateError):
            gate.classify_parent("dossier", event, REPOSITORY, run, jobs)
        event, run, jobs = parent_fixture("dossier")
        extra = [{**jobs[0], "id": i + 100, "name": "other-" + str(i)} for i in range(100)]
        all_jobs, routes = extra + jobs, []
        def read(route):
            routes.append(route)
            if route.endswith("/attempts/2"):
                return run
            page = int(route.rsplit("=", 1)[1])
            return {"total_count": 101, "jobs": all_jobs[(page - 1) * 100:page * 100]}
        proven_run, proven_jobs = gate.read_parent(event, read)
        self.assertTrue(gate.classify_parent("dossier", event, REPOSITORY, proven_run, proven_jobs)["run_collection"])
        self.assertEqual(len(routes), 3)
        self.assertTrue(all(route.startswith("/actions/runs/41/attempts/2") for route in routes))
        with self.assertRaises(gate.GateError):
            gate.read_parent(event, lambda route: run if route.endswith("/attempts/2") else {"total_count": 2, "jobs": jobs})

    def test_changed_paths_preserve_runtime_checks_without_guard_replays(self):
        guard = set(gate.GUARD_PATHS)
        for event in ("push", "pull_request"):
            result = gate.classify_changes("pilot", event, guard, set())
            self.assertFalse(result["run_contract"])
            self.assertFalse(result["run_collection"])
            self.assertEqual(result["run_guard_tests"], event == "pull_request")
        mixed = gate.classify_changes("pilot", "pull_request", guard | {"README.md"}, set())
        self.assertTrue(mixed["run_guard_tests"])
        self.assertFalse(mixed["run_contract"])
        for target, filename in (("pilot", gate.PILOT_COMMON[0]), ("dossier", gate.DOSSIER_SOURCES[0])):
            for event in ("push", "pull_request"):
                result = gate.classify_changes(target, event, guard | {filename}, set())
                self.assertTrue(result["run_contract"])
                self.assertEqual(result["run_collection"], event == "push")
        self.assertTrue(gate.classify_changes("pilot", "push", {"v2/seo-content-pilot-turkey-catalog-v1.php"}, set())["run_collection"])
        self.assertFalse(gate.classify_changes("pilot", "push", guard | {"v2/seo-content-pilot-foo/sub-catalog-v1.php"}, set())["run_contract"])
        for paths, deleted in ((guard, guard), (guard | {"renamed.yml"}, {next(iter(guard))}), (set(), set())):
            with self.assertRaises(gate.GateError):
                gate.classify_changes("pilot", "push", paths, deleted)
        for raw in (b"", b"path", b"path\0\0", b"path\0path\0", b"../path\0", b"\xff\0"):
            with self.assertRaises(gate.GateError):
                gate.parse_paths(raw)
        pilot = workflow_jobs("collect-seo-hotel-pilot-live-evidence.yml")
        steps = {step["name"]: step for step in pilot["fast-contract"]["steps"]}
        for event in ("pull_request", "push"):
            result = gate.classify_changes("pilot", event, guard, set())
            self.assertEqual(admitted(steps["Validate SEO evidence trigger boundaries"], result, event), event == "pull_request")
            self.assertFalse(admitted(steps["Validate review-only live evidence collector"], result, event))
            self.assertFalse(admitted(pilot["live-evidence"], result, event))

    def test_pr_files_are_complete_pinned_and_include_rename_sources(self):
        pr = {"number": 7, "changed_files": 101,
              "base": {"sha": "b" * 40, "ref": "main", "repo": {"full_name": REPOSITORY}},
              "head": {"sha": SHA, "repo": {"full_name": REPOSITORY}}}
        event = {"number": 7, "pull_request": copy.deepcopy(pr)}
        files = [{"filename": f"docs/file-{i}.md", "status": "modified"} for i in range(101)]
        def reader(changed_files, metadata=pr, moved=False, short=False):
            calls = []
            def read(route):
                calls.append(route)
                if route == "/pulls/7":
                    result = copy.deepcopy(metadata)
                    if moved and calls.count(route) == 2:
                        result["head"]["sha"] = "c" * 40
                    return result
                page = int(route.rsplit("=", 1)[1])
                batch = changed_files[(page - 1) * 100:page * 100]
                return batch[:-1] if short else batch
            return read, calls
        read, calls = reader(files)
        paths, deleted = gate.prove_pr(event, REPOSITORY, read)
        self.assertEqual((len(paths), deleted), (101, set()))
        self.assertEqual(calls, ["/pulls/7", "/pulls/7/files?per_page=100&page=1", "/pulls/7/files?per_page=100&page=2", "/pulls/7"])
        for read in (reader(files, moved=True)[0], reader(files, short=True)[0], reader(files[:-1] + files[:1])[0]):
            with self.assertRaises(gate.GateError):
                gate.prove_pr(event, REPOSITORY, read)
        renamed = [{"filename": "moved.yml", "previous_filename": sorted(gate.GUARD_PATHS)[0], "status": "renamed"}]
        paths, deleted = gate.prove_pr(event, REPOSITORY, reader(renamed, {**pr, "changed_files": 1})[0])
        with self.assertRaises(gate.GateError):
            gate.classify_changes("pilot", "pull_request", paths, deleted)
        with self.assertRaises(gate.GateError):
            gate.prove_pr(event, REPOSITORY, reader([{ "filename": "file", "status": "unknown"}], {**pr, "changed_files": 1})[0])

    def test_push_uses_real_immutable_diff_and_rejects_deletion(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            def git(*args):
                return subprocess.check_output(["git", *args], cwd=root, stderr=subprocess.PIPE).decode().strip()
            def write(filename, content):
                destination = root / filename
                destination.parent.mkdir(parents=True, exist_ok=True)
                destination.write_text(content, encoding="utf-8")
            def commit():
                git("add", "--all")
                git("-c", "user.name=Gate test", "-c", "user.email=gate@example.invalid", "commit", "-qm", "fixture")
                return git("rev-parse", "HEAD")
            def prove(before, after):
                return gate.prove_push({"before": before, "after": after, "ref": "refs/heads/main"}, after, root)
            git("init", "-q")
            workflow = sorted(gate.GUARD_PATHS)[0]
            write(workflow, "initial\n")
            base = commit()
            write("scripts/deploy/seo_evidence_gate.py", "fixture\n")
            head = commit()
            result = gate.classify_changes("pilot", "push", *prove(base, head))
            self.assertFalse(result["run_contract"])
            self.assertFalse(result["run_guard_tests"])
            for before, after in ((head, head), ("e" * 40, head), (base, base), ("0" * 40, head)):
                with self.assertRaises(gate.GateError):
                    prove(before, after)
            (root / workflow).unlink()
            deleted_head = commit()
            with self.assertRaises(gate.GateError):
                gate.classify_changes("pilot", "push", *prove(head, deleted_head))
            (root / "tests").mkdir()
            git("mv", "scripts/deploy/seo_evidence_gate.py", "tests/seo_evidence_gate_test.py")
            renamed_head = commit()
            with self.assertRaises(gate.GateError):
                gate.classify_changes("pilot", "push", *prove(deleted_head, renamed_head))

    def test_explicit_events_and_invalid_proof_outputs(self):
        with tempfile.TemporaryDirectory() as directory:
            event_path, output = Path(directory) / "event.json", Path(directory) / "output"
            event_path.write_text(json.dumps({"repository": {"full_name": REPOSITORY}}), encoding="utf-8")
            env = {"GITHUB_EVENT_PATH": str(event_path), "GITHUB_REPOSITORY": REPOSITORY,
                   "GITHUB_SHA": SHA, "GITHUB_OUTPUT": str(output)}
            with mock.patch.object(gate.sys, "argv", ["gate", "--target", "pilot"]), mock.patch.object(gate, "git", return_value=(SHA + "\n").encode()), mock.patch.object(gate, "github_reader", side_effect=AssertionError("unexpected HTTP")):
                for event_name in ("workflow_dispatch", "schedule", "push", "unknown"):
                    output.write_text("", encoding="utf-8")
                    with mock.patch.dict(os.environ, {**env, "GITHUB_EVENT_NAME": event_name}, clear=True), contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
                        code = gate.main()
                    if event_name in {"workflow_dispatch", "schedule"}:
                        self.assertEqual(code, 0)
                        self.assertEqual(output.read_text(), "run_contract=true\nrun_collection=true\nrun_guard_tests=false\n")
                    else:
                        self.assertEqual(code, 1)
                        self.assertEqual(output.read_text(), "")


if __name__ == "__main__":
    unittest.main()
