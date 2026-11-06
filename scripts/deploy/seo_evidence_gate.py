#!/usr/bin/env python3
"""Admit existing SEO work only from proven source changes or a real parent step."""

import argparse
import fnmatch
import json
import os
import re
import subprocess
import sys
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import HTTPRedirectHandler, Request, build_opener


GUARD_PATHS = frozenset({
    ".github/workflows/collect-seo-core-hotel-review-dossier.yml",
    ".github/workflows/collect-seo-hotel-pilot-live-evidence.yml",
    "scripts/deploy/seo_evidence_gate.py",
    "tests/seo_evidence_gate_test.py",
})
DOSSIER_SOURCES = (
    "v2/seo-core-hotel-cohort-v1.php",
    "v2/data/seo-core-hotel-cohort-source-v1.php",
    "v2/data/report-seo-core-hotel-cohort-v1.php",
)
PILOT_COMMON = (
    "v2/seo-hotel-pilot-live-evidence-v1.php",
    "v2/data/report-seo-hotel-pilot-live-evidence-v1.php",
    "v2/seo-hotel-pilot-readiness-status-v1.php",
    "v2/data/report-seo-hotel-pilot-readiness-status-v1.php",
    "v2/data/report-seo-hotel-pilot-gap-matrix-v1.php",
    "tests/seo-hotel-pilot-gap-matrix-smoke.php",
)
PILOT_PR = PILOT_COMMON + (
    "tests/seo-hotel-pilot-live-evidence-smoke.php",
    "tests/seo-hotel-pilot-readiness-status-cli-smoke.php",
)
PILOT_PUSH = PILOT_COMMON + (
    "v2/seo-hotel-tour-page-v1.php",
    "v2/seo-offer-snapshot-v1.php",
    "v2/seo-hotel-launch-pilot-v1.php",
    "v2/seo-content-pilot-*-catalog-v1.php",
    "v2/seo-content-pilot-*-hotel-review-catalog-v1.php",
)
PARENTS = {
    "dossier": (
        "Bootstrap AnyTour data database", ".github/workflows/bootstrap-anytour-data.yml",
        "bootstrap-data", "Initialize changed schema and inspect database",
        {"push", "workflow_dispatch"},
    ),
    "pilot": (
        "Collect SEO Top500 hotel review dossier",
        ".github/workflows/collect-seo-core-hotel-review-dossier.yml",
        "collect", "Collect fresh production review dossier",
        {"push", "workflow_run", "workflow_dispatch", "schedule"},
    ),
}


class GateError(ValueError):
    pass


def require(condition, reason):
    if not condition:
        raise GateError(reason)


def sha(value):
    require(isinstance(value, str) and re.fullmatch(r"[0-9a-f]{40}", value)
            and value != "0" * 40, "invalid-sha")
    return value


def positive_int(value):
    require(type(value) is int and value > 0, "invalid-positive-integer")
    return value


def path(value):
    require(isinstance(value, str) and value and len(value) <= 4096
            and not any(ord(c) < 32 or ord(c) == 127 for c in value)
            and all(part not in ("", ".", "..") for part in value.split("/")),
            "invalid-changed-path")
    try:
        value.encode("utf-8", errors="strict")
    except UnicodeError as exc:
        raise GateError("invalid-path-encoding") from exc
    return value


def parse_paths(raw, allow_empty=False):
    if raw == b"" and allow_empty:
        return set()
    require(isinstance(raw, bytes) and raw and raw.endswith(b"\0"), "invalid-nul-diff")
    try:
        values = raw[:-1].decode("utf-8", errors="strict").split("\0")
    except UnicodeError as exc:
        raise GateError("invalid-path-encoding") from exc
    result = {path(value) for value in values}
    require(len(result) == len(values), "duplicate-changed-path")
    return result


def decision(reason, contract=False, collection=False, guard_tests=False, count=0):
    return {"reason": reason, "run_contract": contract, "run_collection": collection,
            "run_guard_tests": guard_tests, "changed_path_count": count}


def classify_changes(target, event_name, paths, deleted):
    require(target in PARENTS and event_name in {"push", "pull_request"}, "unknown-change-context")
    require(bool(paths) and deleted <= paths, "empty-or-inconsistent-diff")
    for value in paths:
        path(value)
    require(not (deleted & GUARD_PATHS), "guard-path-deleted-or-renamed")
    patterns = DOSSIER_SOURCES if target == "dossier" else (
        PILOT_PR if event_name == "pull_request" else PILOT_PUSH)
    relevant = any(
        len(value.split("/")) == len(pattern.split("/"))
        and all(fnmatch.fnmatchcase(part, glob) for part, glob in zip(value.split("/"), pattern.split("/")))
        for value in paths for pattern in patterns)
    guard_tests = event_name == "pull_request" and bool(paths & GUARD_PATHS)
    reason = "runtime-source-change" if relevant else (
        "guard-only-change" if paths <= GUARD_PATHS else "no-runtime-source-change")
    return decision(reason, relevant, relevant and event_name != "pull_request", guard_tests, len(paths))


def git(*args, cwd=None):
    result = subprocess.run(["git", *args], cwd=cwd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    require(result.returncode == 0, "git-proof-unavailable")
    return result.stdout


def prove_push(event, checkout_sha, cwd=None):
    before, after = sha(event.get("before")), sha(event.get("after"))
    require(event.get("ref") == "refs/heads/main" and not event.get("deleted"), "invalid-push-ref")
    require(after == sha(checkout_sha), "push-checkout-mismatch")
    require(git("rev-parse", "HEAD", cwd=cwd).decode().strip() == after, "push-head-mismatch")
    git("cat-file", "-e", before + "^{commit}", cwd=cwd)
    git("merge-base", "--is-ancestor", before, after, cwd=cwd)
    args = ("diff", "--no-ext-diff", "--no-textconv", "--name-only", "--no-renames", "-z")
    paths = parse_paths(git(*args, before, after, "--", cwd=cwd))
    deleted = parse_paths(git(*args, "--diff-filter=D", before, after, "--", cwd=cwd), True)
    require(deleted <= paths, "inconsistent-deletion-diff")
    return paths, deleted


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def github_reader(repository, token):
    require(re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repository) is not None,
            "invalid-repository")
    require(bool(token), "missing-read-token")
    opener = build_opener(NoRedirect)

    def read(route):
        request = Request("https://api.github.com/repos/" + repository + route, headers={
            "Authorization": "Bearer " + token, "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28", "User-Agent": "seo-evidence-gate",
        })
        try:
            with opener.open(request, timeout=15) as response:
                raw = response.read(5_000_001)
            require(len(raw) <= 5_000_000, "github-response-too-large")
            return json.loads(raw)
        except (HTTPError, URLError, TimeoutError, UnicodeError, json.JSONDecodeError) as exc:
            raise GateError("github-proof-unavailable") from exc

    return read


def pr_pins(pr, repository):
    require(isinstance(pr, dict), "invalid-pr-metadata")
    base, head = pr.get("base", {}), pr.get("head", {})
    require(base.get("repo", {}).get("full_name") == repository and base.get("ref") == "main",
            "wrong-pr-base")
    require(isinstance(head.get("repo", {}).get("full_name"), str), "missing-pr-head-repository")
    return sha(base.get("sha")), sha(head.get("sha")), head["repo"]["full_name"]


def prove_pr(event, repository, read):
    number = positive_int(event.get("number"))
    expected = pr_pins(event.get("pull_request"), repository)
    route = "/pulls/" + str(number)
    initial = read(route)
    require(initial.get("number") == number and pr_pins(initial, repository) == expected,
            "pr-pins-moved")
    count = positive_int(initial.get("changed_files"))
    require(count <= 3000, "pr-files-api-limit")
    files = []
    for page in range(1, (count + 99) // 100 + 1):
        batch = read(route + f"/files?per_page=100&page={page}")
        require(isinstance(batch, list) and len(batch) == min(100, count - len(files)),
                "incomplete-pr-file-proof")
        files.extend(batch)
    final = read(route)
    require(final.get("number") == number and pr_pins(final, repository) == expected
            and final.get("changed_files") == count, "pr-pins-moved")
    paths, deleted, filenames = set(), set(), set()
    for item in files:
        require(isinstance(item, dict), "invalid-pr-file")
        filename = path(item.get("filename"))
        require(filename not in filenames, "duplicate-pr-file")
        filenames.add(filename)
        paths.add(filename)
        status = item.get("status")
        require(status in {"added", "modified", "removed", "renamed", "copied", "changed", "unchanged"},
                "unknown-pr-file-status")
        if status == "removed":
            deleted.add(filename)
        if status == "renamed":
            previous = path(item.get("previous_filename"))
            require(previous != filename, "invalid-pr-rename")
            paths.add(previous)
            deleted.add(previous)
    return paths, deleted


def read_parent(event, read):
    parent = event.get("workflow_run", {})
    run_id, attempt = positive_int(parent.get("id")), positive_int(parent.get("run_attempt"))
    route = f"/actions/runs/{run_id}/attempts/{attempt}"
    run = read(route)
    first = read(route + "/jobs?per_page=100&page=1")
    total = first.get("total_count")
    require(type(total) is int and 0 <= total <= 1000, "invalid-parent-job-count")
    jobs = first.get("jobs")
    require(isinstance(jobs, list) and len(jobs) == min(100, total), "incomplete-parent-jobs")
    for page in range(2, (total + 99) // 100 + 1):
        batch = read(route + f"/jobs?per_page=100&page={page}")
        require(batch.get("total_count") == total and isinstance(batch.get("jobs"), list)
                and len(batch["jobs"]) == min(100, total - len(jobs)), "incomplete-parent-jobs")
        jobs.extend(batch["jobs"])
    ids = [positive_int(job.get("id")) for job in jobs if isinstance(job, dict)]
    require(len(ids) == total and len(set(ids)) == total, "invalid-parent-jobs")
    return run, jobs


def classify_parent(target, event, repository, run, jobs):
    name, workflow_path, job_name, step_name, allowed_events = PARENTS[target]
    expected = event.get("workflow_run", {})
    require(event.get("action") == "completed", "parent-event-not-completed")
    require(isinstance(run, dict), "invalid-parent-run")
    for record in (expected, run):
        positive_int(record.get("id"))
        positive_int(record.get("run_attempt"))
        positive_int(record.get("workflow_id"))
        sha(record.get("head_sha"))
        require(record.get("name") == name
                and record.get("path") in {workflow_path, workflow_path + "@main", workflow_path + "@refs/heads/main"}
                and record.get("repository", {}).get("full_name") == repository
                and record.get("head_repository", {}).get("full_name") == repository
                and record.get("head_branch") == "main", "wrong-parent-identity")
    for key in ("id", "run_attempt", "workflow_id", "head_sha", "event", "status", "conclusion"):
        require(expected.get(key) == run.get(key), "parent-attempt-mismatch")
    if (run.get("event") not in allowed_events or run.get("status") != "completed"
            or run.get("conclusion") != "success"):
        return decision("parent-not-eligible")
    matches = [job for job in jobs if job.get("name") == job_name]
    require(len(matches) <= 1, "ambiguous-parent-job")
    if not matches:
        return decision("parent-job-absent")
    job = matches[0]
    require(job.get("run_id") == run["id"] and job.get("head_sha") == run["head_sha"]
            and job.get("head_branch") == "main"
            and job.get("run_attempt", run["run_attempt"]) == run["run_attempt"], "parent-job-mismatch")
    if job.get("status") != "completed" or job.get("conclusion") != "success":
        return decision("parent-job-not-successful")
    steps = job.get("steps")
    require(isinstance(steps, list) and all(isinstance(step, dict) for step in steps), "invalid-parent-steps")
    matches = [step for step in steps if step.get("name") == step_name]
    require(len(matches) <= 1, "ambiguous-parent-step")
    if not matches or matches[0].get("status") != "completed" or matches[0].get("conclusion") != "success":
        return decision("parent-required-step-not-successful")
    return decision("parent-required-step-succeeded", True, True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--target", choices=tuple(PARENTS), required=True)
    args = parser.parse_args()
    try:
        event = json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text(encoding="utf-8"))
        repository = os.environ["GITHUB_REPOSITORY"]
        require(event.get("repository", {}).get("full_name") == repository, "event-repository-mismatch")
        checkout_sha = sha(os.environ["GITHUB_SHA"])
        require(git("rev-parse", "HEAD").decode().strip() == checkout_sha, "checkout-head-mismatch")
        event_name = os.environ["GITHUB_EVENT_NAME"]
        if event_name in {"workflow_dispatch", "schedule"}:
            result = decision("explicit-manual" if event_name == "workflow_dispatch" else "existing-schedule", True, True)
        elif event_name == "push":
            result = classify_changes(args.target, event_name, *prove_push(event, checkout_sha))
        elif event_name in {"pull_request", "workflow_run"}:
            read = github_reader(repository, os.environ.get("GH_TOKEN", ""))
            if event_name == "pull_request":
                result = classify_changes(args.target, event_name, *prove_pr(event, repository, read))
            else:
                result = classify_parent(args.target, event, repository, *read_parent(event, read))
        else:
            raise GateError("unsupported-event")
        with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
            for key in ("run_contract", "run_collection", "run_guard_tests"):
                output.write(key + "=" + str(result[key]).lower() + "\n")
        print(json.dumps({"target": args.target, "event": event_name, **result}, sort_keys=True))
        return 0
    except (GateError, KeyError, OSError, UnicodeError, json.JSONDecodeError, AttributeError, TypeError) as exc:
        reason = str(exc) if isinstance(exc, GateError) else "invalid-or-unavailable-proof"
        print(json.dumps({"error": reason}), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
