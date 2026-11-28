#!/bin/bash
set -euo pipefail
umask 077
test "$(id -un)" = anytoour
host_key="$1"
[[ "$host_key" =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+$ ]]
dir="$HOME/.anytour-price-result"
test -O "$dir/return_ed25519" && test "$(stat -c %a "$dir/return_ed25519")" = 600
tmp=$(mktemp -d "$dir/verify.XXXXXX")
trap 'rm -rf -- "$tmp"' EXIT
host=45.67.128.147
printf '%s %s\n' "$host" "$host_key" > "$tmp/known_hosts"
call_price() {
    timeout 90 ssh -4 -T -F /dev/null -i "$dir/return_ed25519" \
        -o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=8 \
        -o HostKeyAlgorithms=ssh-ed25519 -o StrictHostKeyChecking=yes \
        -o UserKnownHostsFile="$tmp/known_hosts" -o GlobalKnownHostsFile=/dev/null \
        "fastuser@$host" "$1"
}
test "$(call_price probe)" = '{"service":"anytour-price-result-read","version":1}'
if call_price rejected-test-command >/dev/null 2>"$tmp/denial.txt"; then
    echo PRICE_RESULT_RESTRICTION_TEST_FAILED
    exit 10
fi
grep -qx PRICE_RESULT_COMMAND_DENIED "$tmp/denial.txt"
echo PRICE_RETURN_RESTRICTION_VERIFIED
day=2026-11-26
expected=ffead7fd573b2a3c9a530b1a31c3bde06efc34f94954b360a9fb7111e9493c9b
call_price "manifest $day" > "$tmp/manifest.json"
python3 - "$tmp/manifest.json" "$day" "$expected" <<'PRICE_MANIFEST'
import json, pathlib, re, sys
manifest = json.loads(pathlib.Path(sys.argv[1]).read_text())
assert manifest["contract"] == 1 and manifest["date"] == sys.argv[2]
assert manifest["sha256"] == sys.argv[3]
assert manifest["groups"] == 638682 and manifest["observations"] == 699238
assert re.fullmatch(r"[0-9a-f]{64}", manifest["gzip_sha256"])
assert 0 < manifest["bytes"] <= 256*1024*1024
PRICE_MANIFEST
ulimit -f 524288
call_price "snapshot $day $expected" > "$tmp/snapshot.gz"
python3 - "$tmp/manifest.json" "$tmp/snapshot.gz" <<'PRICE_VERIFY_RESULT'
import gzip, hashlib, json, pathlib, re, sys
manifest = json.loads(pathlib.Path(sys.argv[1]).read_text())
archive = pathlib.Path(sys.argv[2])
assert archive.stat().st_size == manifest["bytes"]
with archive.open("rb") as source:
    assert hashlib.file_digest(source, "sha256").hexdigest() == manifest["gzip_sha256"]
rows = observations = 0
last = ""
digest = hashlib.sha256()
with gzip.open(archive, "rb") as source:
    for line in source:
        assert len(line) <= 8192 and line.endswith(b"\n")
        row = json.loads(line)
        assert len(row) == 21 and re.fullmatch("[a-f0-9]{64}", row[0]) and row[0] > last
        last = row[0]
        observations += int(row[19])
        rows += 1
        assert rows <= manifest["groups"]
        digest.update(line)
assert rows == manifest["groups"] and observations == manifest["observations"]
assert digest.hexdigest() == manifest["sha256"]
print(f"PRICE_RETURN_FULL_DAY_VERIFIED rows={rows} observations={observations} digest={digest.hexdigest()}")
PRICE_VERIFY_RESULT
