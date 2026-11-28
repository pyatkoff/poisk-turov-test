#!/usr/bin/env python3
"""Compare completed PRICE manifests with the live aggregate tables on the old host."""
import argparse
import datetime as dt
import json
import os
import pathlib
import pwd
import re
import subprocess
import tempfile


def run(command, timeout):
    output = subprocess.run(command, timeout=timeout, capture_output=True, check=False)
    if output.returncode:
        raise RuntimeError(f"subprocess failed with exit {output.returncode}")
    return output.stdout


def normalized(manifest):
    def dataset(item):
        fields = {key: item[key] for key in ("sha256", "groups", "observations")}
        if not re.fullmatch(r"[0-9a-f]{64}", fields["sha256"]):
            raise ValueError("invalid aggregate digest")
        if any(type(fields[key]) is not int or fields[key] < 0 for key in ("groups", "observations")):
            raise ValueError("invalid aggregate totals")
        return fields

    if manifest["contract"] != 1:
        raise ValueError("unexpected PRICE manifest contract")
    return {"date": manifest["date"], "exact": dataset(manifest), "coarse": dataset(manifest["coarse"])}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("pinned_host_key")
    parser.add_argument("digest_script", type=pathlib.Path)
    parser.add_argument("--days", type=int, choices=(1, 2, 3, 31), default=2)
    args = parser.parse_args()
    host = "45.67.128.147"
    if not re.fullmatch(r"ssh-ed25519 [A-Za-z0-9+/=]+", args.pinned_host_key):
        raise ValueError("invalid VPS host key")
    home = pathlib.Path.home()
    account = pwd.getpwuid(os.getuid())
    if account.pw_name != "anytoour" or home.resolve() != pathlib.Path(account.pw_dir).resolve():
        raise RuntimeError("unexpected production account")
    key = home / ".anytour-price-result/return_ed25519"
    if key.is_symlink() or not key.is_file() or key.stat().st_mode & 0o777 != 0o600:
        raise RuntimeError("unexpected PRICE SSH identity")
    config = home / "www/anytoour.ru/data/db-v1.php"
    today = dt.datetime.now(dt.timezone.utc).date()
    offsets = range(30, -1, -1) if args.days == 31 else range(args.days, 0, -1)
    dates = [today - dt.timedelta(days=i) for i in offsets]
    with tempfile.TemporaryDirectory(prefix="price-window-") as tmp:
        hosts = pathlib.Path(tmp, "known_hosts")
        hosts.write_text(f"{host} {args.pinned_host_key}\n")
        ssh = [
            "ssh", "-4", "-n", "-T", "-F", "/dev/null", "-i", str(key),
            "-o", "BatchMode=yes", "-o", "IdentitiesOnly=yes", "-o", "ConnectTimeout=8",
            "-o", "HostKeyAlgorithms=ssh-ed25519", "-o", "StrictHostKeyChecking=yes",
            "-o", f"UserKnownHostsFile={hosts}", "-o", "GlobalKnownHostsFile=/dev/null",
            f"fastuser@{host}",
        ]
        candidates = {}
        for day in dates:
            try:
                candidate = json.loads(run(ssh + [f"manifest {day}"], 15))
            except (subprocess.TimeoutExpired, RuntimeError):
                continue
            if candidate.get("date") != str(day) or "coarse" not in candidate or "source" not in candidate:
                continue
            candidates[day] = candidate
        if len(candidates) != len(dates):
            missing = ",".join(str(day) for day in dates if day not in candidates)
            print(f"PRICE_PARITY_PENDING ready={len(candidates)} days={len(dates)} missing={missing}", flush=True)
            return 2
        if today in candidates:
            generated = dt.datetime.fromisoformat(candidates[today]["generated_at"])
            age = dt.datetime.now(dt.timezone.utc) - generated
            if not dt.timedelta() <= age < dt.timedelta(hours=2):
                print(f"PRICE_PARITY_PENDING day={today} reason=shadow_manifest_not_fresh", flush=True)
                return 2
        for day, candidate in candidates.items():
            reference = json.loads(run(["php", str(args.digest_script), str(config), str(day)], 35))
            expected, actual = normalized(reference), normalized(candidate)
            if actual != expected:
                for table in ("exact", "coarse"):
                    print(
                        f"PRICE_PARITY_{'OK' if actual[table] == expected[table] else 'MISMATCH'} "
                        f"day={day} table={table} old={json.dumps(expected[table], separators=(',', ':'))} "
                        f"new={json.dumps(actual[table], separators=(',', ':'))}",
                        flush=True,
                    )
                return 3
            print(
                f"PRICE_PARITY_DAY_OK day={day} exact_groups={actual['exact']['groups']} "
                f"coarse_groups={actual['coarse']['groups']} "
                f"exact_sha256={actual['exact']['sha256']} coarse_sha256={actual['coarse']['sha256']}",
                flush=True,
            )
    print(f"PRICE_PARITY_WINDOW_OK days={len(dates)}", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
