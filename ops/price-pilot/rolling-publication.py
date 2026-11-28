#!/usr/bin/env python3
"""Read verified PRICE snapshots over the existing SSH key and publish changed dates."""
import argparse
import datetime as dt
import fcntl
import json
import os
import pathlib
import pwd
import re
import resource
import stat
import subprocess
import tempfile
import time

UTC = dt.timezone.utc
PACKAGE = pathlib.Path(__file__).resolve().parent
VPS = "45.67.128.147"
DIGEST = re.compile(r"[0-9a-f]{64}")
MAX_ARCHIVE = 256 * 1024 * 1024


def private_file(path):
    mode = path.lstat()
    return (stat.S_ISREG(mode.st_mode) and mode.st_uid == os.getuid()
            and stat.S_IMODE(mode.st_mode) == 0o600)


def safe_json(path, value):
    with tempfile.NamedTemporaryFile(dir=path.parent, mode="w", delete=False) as stream:
        temporary = pathlib.Path(stream.name)
        try:
            json.dump(value, stream, separators=(",", ":"), sort_keys=True)
            stream.flush()
            os.fsync(stream.fileno())
            os.replace(temporary, path)
        finally:
            temporary.unlink(missing_ok=True)


def digest_stats(dataset):
    digest = dataset["sha256"]
    groups, observations = dataset["groups"], dataset["observations"]
    if (not isinstance(digest, str) or not DIGEST.fullmatch(digest)
            or type(groups) is not int or type(observations) is not int
            or not 0 <= groups <= observations):
        raise RuntimeError("Unexpected aggregate digest")
    return {"sha256": digest, "groups": groups, "observations": observations}


def snapshot_identity(manifest):
    return {name: digest_stats(data) for name, data in
            (("exact", manifest), ("coarse", manifest["coarse"]))}


def limit_output(size):
    def restrict():
        resource.setrlimit(resource.RLIMIT_FSIZE, (size, size))
    return restrict


class Transfer:
    def __init__(self, key, hosts):
        self.ssh = [
            "ssh", "-4", "-n", "-T", "-F", "/dev/null", "-i", str(key),
            "-o", "BatchMode=yes", "-o", "IdentitiesOnly=yes", "-o", "ConnectTimeout=8",
            "-o", "ServerAliveInterval=10", "-o", "ServerAliveCountMax=2",
            "-o", "HostKeyAlgorithms=ssh-ed25519", "-o", "StrictHostKeyChecking=yes",
            "-o", f"UserKnownHostsFile={hosts}", "-o", "GlobalKnownHostsFile=/dev/null",
            f"fastuser@{VPS}",
        ]

    def manifest(self, day):
        wire = subprocess.run(
            self.ssh + [f"manifest {day}"], capture_output=True, check=True, timeout=18
        ).stdout
        if not 0 < len(wire) <= 16384:
            raise RuntimeError("Unexpected PRICE manifest size")
        data = json.loads(wire)
        if data.get("contract") != 1 or data.get("date") != day or data.get("source", {}).get("date") != day:
            raise RuntimeError("Unexpected PRICE manifest contract")
        generated = dt.datetime.fromisoformat(data["generated_at"])
        if generated.tzinfo is None:
            raise RuntimeError("Manifest requires an absolute generation time")
        snapshot_identity(data)
        return data

    def archive(self, day, dataset, target):
        digest, size = dataset["sha256"], dataset["bytes"]
        if not DIGEST.fullmatch(digest) or type(size) is not int or not 0 < size <= MAX_ARCHIVE:
            raise RuntimeError("Unexpected aggregate archive size")
        with target.open("xb") as file:
            subprocess.run(self.ssh + [f"snapshot {day} {digest}"], stdout=file,
                           check=True, timeout=100, preexec_fn=limit_output(size))
        if target.stat().st_size != size:
            raise RuntimeError("Incomplete PRICE archive")


def compare_reference(config, day, manifest):
    data = subprocess.run(
        ["php", str(PACKAGE / "rolling-digest.php"), str(config), day],
        check=True, capture_output=True, timeout=35,
    ).stdout
    reference = json.loads(data)
    if reference.get("date") != day or snapshot_identity(reference) != snapshot_identity(manifest):
        raise RuntimeError(f"PRICE aggregates differ for {day}")


def run_transfer(transfer, config, directory, manifest, mode):
    targets = {name: directory / f"{name}.jsonl.gz" for name in ("exact", "coarse")}
    transfer.archive(manifest["date"], manifest, targets["exact"])
    transfer.archive(manifest["date"], manifest["coarse"], targets["coarse"])
    manifest_file = directory / "manifest.json"
    safe_json(manifest_file, manifest)
    result = subprocess.run(
        ["php", str(PACKAGE / "apply-rolling-result.php"), str(config), str(manifest_file),
         str(targets["exact"]), str(targets["coarse"]), mode],
        check=True, text=True, capture_output=True, timeout=240,
    )
    prefix = "PRICE_ROLLING_APPLIED " if mode == "--apply" else "PRICE_ROLLING_REHEARSED "
    if not result.stdout.startswith(prefix):
        raise RuntimeError("PRICE publication receipt is absent")
    receipt = json.loads(result.stdout.removeprefix(prefix))
    if receipt.get("date") != manifest["date"] or receipt.get("source") != manifest["source"]:
        raise RuntimeError("PRICE publication receipt does not match the snapshot")
    print(result.stdout.strip(), flush=True)


def resolve_window(today, day):
    if day:
        target = dt.date.fromisoformat(day)
        if str(target) != day or not today - dt.timedelta(days=30) <= target <= today:
            raise RuntimeError("Requested day is outside the PRICE window")
        return [day]
    return [str(today - dt.timedelta(days=offset)) for offset in range(30, -1, -1)]


def fresh_today(manifest, now):
    generated = dt.datetime.fromisoformat(manifest["generated_at"])
    if not dt.timedelta() <= now - generated < dt.timedelta(hours=2):
        raise RuntimeError("Today's PRICE candidate is stale")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("mode", choices=("rehearse", "initialize", "apply"))
    parser.add_argument("--day")
    args = parser.parse_args()
    if bool(args.day) != (args.mode == "rehearse"):
        parser.error("--day is required for rehearsal only")
    started = time.monotonic()
    os.umask(0o077)
    account = pwd.getpwuid(os.getuid())
    home = pathlib.Path(account.pw_dir).resolve()
    if account.pw_name != "anytoour" or home != pathlib.Path.home().resolve():
        raise RuntimeError("Unexpected production account")
    config = home / "www/anytoour.ru/data/db-v1.php"
    state = home / ".anytour-price-result"
    identity = state / "return_ed25519"
    if state.is_symlink() or state.stat().st_uid != os.getuid() or not private_file(identity):
        raise RuntimeError("The existing PRICE reader identity is unavailable")
    host_key = (PACKAGE / "return-vps-host-key.pub").read_text().strip()
    if not re.fullmatch(r"ssh-ed25519 [A-Za-z0-9+/=]+", host_key):
        raise RuntimeError("Invalid VPS host key")
    journal = state / "publication.json"
    lock = state / "publication.lock"
    if lock.is_symlink() or journal.is_symlink():
        raise RuntimeError("Unexpected PRICE publication state")
    with lock.open("a") as lock_handle:
        fcntl.flock(lock_handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        if journal.exists() and not private_file(journal):
            raise RuntimeError("Unexpected PRICE publication journal permissions")
        with tempfile.TemporaryDirectory(prefix="price-transfer-", dir=state) as temp:
            directory = pathlib.Path(temp)
            hosts = directory / "known_hosts"
            hosts.write_text(f"{VPS} {host_key}\n")
            transfer = Transfer(identity, hosts)
            now = dt.datetime.now(UTC)
            dates = resolve_window(now.date(), args.day)
            manifests = {day: transfer.manifest(day) for day in dates}
            if str(now.date()) in manifests:
                fresh_today(manifests[str(now.date())], dt.datetime.now(UTC))
            if args.mode == "rehearse":
                run_transfer(transfer, config, directory, manifests[dates[0]], "--rehearse")
                return
            if args.mode == "initialize":
                if journal.exists():
                    raise RuntimeError("An existing PRICE journal requires an explicit rollback")
                for day in dates:
                    compare_reference(config, day, manifests[day])
                results = {day: snapshot_identity(manifests[day]) for day in dates}
                safe_json(journal, {"version": 1, "dates": results})
                print(f"PRICE_PUBLICATION_INITIALIZED dates={len(results)}", flush=True)
                return
            if not journal.exists():
                raise RuntimeError("PRICE publication has not passed production parity")
            record = json.loads(journal.read_text())
            if record.get("version") != 1 or not isinstance(record.get("dates"), dict):
                raise RuntimeError("Unrecognized PRICE publication journal")
            results = record["dates"]
            applied = 0
            for day in dates:
                manifest = manifests[day]
                signature = snapshot_identity(manifest)
                if results.get(day) == signature:
                    continue
                with tempfile.TemporaryDirectory(prefix="day-", dir=directory) as subdirectory:
                    run_transfer(transfer, config, pathlib.Path(subdirectory), manifest, "--apply")
                results[day] = signature
                safe_json(journal, record)
                applied += 1
            record["dates"] = {day: results[day] for day in dates}
            safe_json(journal, record)
            print(f"PRICE_PUBLICATION_OK days={len(dates)} changed={applied} elapsed_seconds={time.monotonic()-started:.1f}", flush=True)


if __name__ == "__main__":
    try:
        main()
    except subprocess.CalledProcessError as error:
        # Reuse the checked PHP failure token without printing SQL, paths or keys.
        detail = error.stderr or ""
        if isinstance(detail, bytes):
            detail = detail.decode(errors="replace")
        code = re.search(r"PRICE_ROLLING_APPLY_FAILED [A-Z0-9_]+", detail)
        raise SystemExit(code[0] if code else f"PRICE publication dependency failed (exit {error.returncode})")
    except (BlockingIOError, RuntimeError, subprocess.TimeoutExpired) as error:
        raise SystemExit(str(error))
