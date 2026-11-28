#!/usr/bin/env bash
set -euo pipefail
fixture="$(mktemp -d)"
trap 'rm -rf -- "$fixture"' EXIT
script="$(dirname "$0")/daily-digest-v1.php"
cat > "$fixture/db-v1.php" <<'PHP'
<?php
function v2_data_db(): object {
    return new class {
        public function query(string $sql): object {
            $value = match ($sql) {
                'SELECT DATABASE()' => getenv('TEST_WRONG_DB') ? 'production' : 'anytour_price_pilot',
                'SELECT VERSION()' => '8.0',
            };
            return new class($value) {
                public function __construct(private string $value) {}
                public function fetchColumn(): string { return $this->value; }
            };
        }
        public function exec(string $sql): void {
            if (!in_array($sql, ['SET SESSION MAX_EXECUTION_TIME=8000', 'START TRANSACTION READ ONLY', 'ROLLBACK'], true)) {
                throw new RuntimeException('UNEXPECTED_STATEMENT');
            }
        }
        public function setAttribute(int $key, bool $value): void {}
        public function prepare(string $sql): object {
            if (!str_starts_with($sql, 'SELECT segment_fingerprint')) throw new RuntimeException('UNEXPECTED_QUERY');
            return new class {
                private bool $hasRow = false;
                public function execute(array $args): void { $this->hasRow = $args === ['2026-11-26', '']; }
                public function fetch(int $mode): array|false {
                    if (!$this->hasRow) return false;
                    $this->hasRow = false;
                    $row = array_fill(0, 21, null);
                    $row[0] = str_repeat('a', 64);
                    $row[13] = 'Номер/A';
                    $row[19] = 3;
                    return $row;
                }
                public function closeCursor(): void {}
            };
        }
    };
}
PHP
python3 - "$fixture/expected.jsonl" <<'PY'
import json, pathlib, sys
row = [None] * 21
row[0], row[13], row[19] = 'a' * 64, 'Номер/A', '3'
pathlib.Path(sys.argv[1]).write_text(json.dumps(row, ensure_ascii=False, separators=(',', ':')) + '\n')
PY
expected="$(sha256sum "$fixture/expected.jsonl" | cut -d ' ' -f 1)"
target="$fixture/price-return-ready/2026-11-26"
day=(php "$script" "$fixture/db-v1.php" 2026-11-26)
test "$("${day[@]}")" = "PRICE_DAILY_DIGEST date=2026-11-26 groups=1 observations=3 sha256=$expected"
test ! -e "$target"
if TEST_WRONG_DB=1 "${day[@]}" --publish-ready 1 3 "$expected"; then exit 1; fi
test ! -e "$target"
"${day[@]}" --publish-ready 1 3 "$expected" > "$fixture/first.txt"
gzip -dc "$target/$expected.jsonl.gz" | cmp - "$fixture/expected.jsonl"
cp "$target/manifest.json" "$fixture/manifest.json"
python3 - "$target/manifest.json" "$target/$expected.jsonl.gz" "$expected" <<'PY'
import hashlib, json, pathlib, sys
manifest = json.loads(pathlib.Path(sys.argv[1]).read_text())
snapshot = pathlib.Path(sys.argv[2]).read_bytes()
assert manifest == {'contract': 1, 'date': '2026-11-26', 'groups': 1, 'observations': 3, 'sha256': sys.argv[3],
                    'gzip_sha256': hashlib.sha256(snapshot).hexdigest(), 'bytes': len(snapshot)}
PY
"${day[@]}" --publish-ready 1 3 "$expected" > "$fixture/retry.txt"
cmp "$fixture/first.txt" "$fixture/retry.txt"
if "${day[@]}" --publish-ready 1 4 "$expected"; then exit 1; fi
cmp "$fixture/manifest.json" "$target/manifest.json"
test -z "$(find "$target" -maxdepth 1 -name '.payload-*' -print -quit)"
if php "$script" "$fixture/db-v1.php" "$(date -u +%F)" --publish-ready 1 3 "$expected"; then exit 1; fi
echo PRICE_CLOSED_DAY_PUBLICATION_VERIFIED
