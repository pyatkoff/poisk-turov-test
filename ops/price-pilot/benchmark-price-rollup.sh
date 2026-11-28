#!/usr/bin/env bash
set -euo pipefail
umask 077
export TZ=UTC
cd /var/www/fastuser/data/anytour-price-pilot
test "$(id -un)" = fastuser
stage=$(mktemp -d .price-rollup-bench.XXXXXX)
trap 'rm -rf -- "$stage"' EXIT
for file in db-v1.php price-segment-v1.php rollup-price-daily-exact-v1.php rollup-price-daily-v1.php rolling-digest.php; do
    cp -- "$file" "$stage/$file"
done
while read -r checksum file release; do
    if test -n "$release"; then
        curl -fsSL --proto '=https' --max-time 30 \
            "https://raw.githubusercontent.com/pyatkoff/poisk-turov-test/$release/ops/price-pilot/$file" -o "$stage/$file"
    fi
    echo "$checksum *$stage/$file" | sha256sum -c -
    php -l "$stage/$file"
done <<'PINS'
d796dc600061e8a8ddcd7a24d6993801e3355364e8210a12bb897a9991413ec6 rollup-price-daily-exact-v1.php
0dcf8291126b3cfb018906eb7e26e512fc42ef7dccbe4a52525600c3b6c4a425 rollup-price-daily-exact-batch.php f1885d56ce74980a274791cbdc99c46b8a3e2a99
ce9c693f3b60a05c4b79d1bf3ce4fe601d90d8a091241ff9e41cd0ceb58b7b2c benchmark-price-rollup.php e8667b381fc6a09e964ba084e04d68da44d38634
PINS
for mode in single batch; do
    /usr/bin/timeout 12m /usr/bin/nice -n 10 /usr/bin/php -d memory_limit=1024M "$stage/benchmark-price-rollup.php" "$PWD" "$mode"
done
date -u
