#!/usr/bin/env bash
set -euo pipefail
umask 077
cd /var/www/fastuser/data/anytour-price-pilot
test "$(id -un)" = fastuser
test "$(stat -c %a .)" = 700
exec 9>rolling-price.lock
flock -w 5 9
exec 8>full-day.lock
flock -w 5 8
stage="$(mktemp -d .rolling-install.XXXXXX)"
trap 'rm -rf -- "$stage"' EXIT
while read -r digest file source; do
    curl -fsSL --proto '=https' --max-time 30 "https://raw.githubusercontent.com/pyatkoff/poisk-turov-test/$source" -o "$stage/$file"
    echo "$digest *$stage/$file" | sha256sum -c -
    case "$file" in
        *.php) php -l "$stage/$file" ;;
        *.sh) bash -n "$stage/$file" ;;
    esac
done <<'RELEASE'
3ca03b8963f13028dd5a600e0344dfa7cdfbf9656bb1155b0cbfc6aea57d3e49 sync-day.php 65226ca0687f6b8f21d5fe15ca28bfe358339ad2/ops/price-pilot/sync-day.php
5ed83f21e7c30531491837597c59752cdd85fbbdff1adc473c9374f2d8229b10 rolling-daily-schema.sql 65226ca0687f6b8f21d5fe15ca28bfe358339ad2/ops/price-pilot/rolling-daily-schema.sql
0901560d73fd6ca1672402d94882aa4090750c45e767f340689644e5a4f60406 rolling-digest.php 65226ca0687f6b8f21d5fe15ca28bfe358339ad2/ops/price-pilot/rolling-digest.php
970ccd1d18c5efa469ba3f46c87bc07476159f73d54850f3407d692bea9558ef rolling-day.sh 65226ca0687f6b8f21d5fe15ca28bfe358339ad2/ops/price-pilot/rolling-day.sh
c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13 db-v1.php e527e41ffd95d4170e13276fea22b17e021b80a6/v2/data/db-v1.php
6db65d0a8259d447c2fc1f3acf2f8cf9b5a758a660ca4ee3fd41db6420133b6f price-segment-v1.php e527e41ffd95d4170e13276fea22b17e021b80a6/v2/data/price-segment-v1.php
d796dc600061e8a8ddcd7a24d6993801e3355364e8210a12bb897a9991413ec6 rollup-price-daily-exact-v1.php e527e41ffd95d4170e13276fea22b17e021b80a6/v2/data/rollup-price-daily-exact-v1.php
2451d3774cb51c2e67c4fb2799b816ed2ef9ae77ef7dc2f0abd84bca6c13bcd1 rollup-price-daily-v1.php e527e41ffd95d4170e13276fea22b17e021b80a6/v2/data/rollup-price-daily-v1.php
RELEASE
for file in db-v1.php price-segment-v1.php rollup-price-daily-exact-v1.php; do
    if test -e "$file" && ! cmp -s "$file" "$stage/$file"; then
        echo "PRICE_ROLLING_EXISTING_FILE_CONFLICT file=$file"
        exit 3
    fi
done
test -r db-password.txt && test -r source_ed25519 && test -r source-known-hosts
for file in "$stage"/*; do
    target="${file##*/}"
    if ! test -e "$target" || ! cmp -s "$file" "$target"; then
        chmod 600 "$file"
        mv -T -- "$file" "$target"
    fi
done
echo '65226ca0687f6b8f21d5fe15ca28bfe358339ad2' > "$stage/release"
mv -T -- "$stage/release" rolling-release
echo "PRICE_ROLLING_INSTALLED 65226ca0687f6b8f21d5fe15ca28bfe358339ad2"
