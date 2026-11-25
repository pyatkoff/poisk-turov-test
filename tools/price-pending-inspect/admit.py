"""Read the original terminal PRICE attempt. No application writes."""
import hashlib, json, os, pathlib, subprocess

BASE = pathlib.Path(__file__).parent

def api(repo, path):
    return json.loads(subprocess.check_output(
        ["gh", "api", "--method", "GET", f"repos/pyatkoff/{repo}/{path}"], timeout=30))

def main():
    repo = "poisk-turov-test"
    branch = "ops/price-hook-reviewed-pass-20261117"
    event = json.loads(pathlib.Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
    assert os.environ["GITHUB_REPOSITORY"] == f"pyatkoff/{repo}"
    assert os.environ["GITHUB_ACTOR"] == "pyatkoff" and os.environ["GITHUB_RUN_ATTEMPT"] == "1"
    assert os.environ["GITHUB_EVENT_NAME"] == "push" and event["before"] == "71270d38104fd9f12ccb9db9ced0d6515489a219"
    assert api(repo, f"git/ref/heads/{branch}")["object"]["sha"] == os.environ["GITHUB_SHA"]
    original = api(repo, "actions/runs/45720745186")
    assert (original["head_sha"], original["run_attempt"], original["status"], original["conclusion"]) == ("492ed802f0927c120953616113af4b162bc600d5", 1, "completed", "failure")
    job = api(repo, "actions/jobs/139578143630")
    assert job["run_id"] == 45720745186 and job["conclusion"] == "failure"
    for name, digest in {
        "inspect.php": "3846f0514a044a881a8862512bd7231c753916dcbad179618c76d24179533764",
        "run.py": "7440bc88ea1f21df40bcdb16590c0d16a9231018b8a11f9b564f9bae97859fc0"
    }.items():
        assert hashlib.sha256((BASE / name).read_bytes()).hexdigest() == digest
    print("ORIGINAL_PRICE_TERMINAL", 45720745186, 139578143630, flush=True)
