#!/usr/bin/env bash
set -euo pipefail
umask 077
export TZ=UTC
cd -- "$(dirname -- "$0")"
test "$(id -un)" = fastuser
test "$PWD" = /var/www/fastuser/data/anytour-price-pilot
test "$(stat -c %a .)" = 700
test "$(stat -c %a db-password.txt)" = 600
exec 9>rolling-price.lock
flock -n 9 || exit 0
export ANYTOUR_DATA_DSN='mysql:host=localhost;dbname=anytour_price_pilot;charset=utf8mb4'
export ANYTOUR_DATA_DB_USER=price_pilot
export ANYTOUR_DATA_DB_PASSWORD="$(cat db-password.txt)"
test -n "$ANYTOUR_DATA_DB_PASSWORD"
available_kb="$(df -Pk . | awk 'NR==2 {print $4}')"
test "$available_kb" -gt 20971520 || { echo PRICE_ROLLING_DISK_FLOOR; exit 4; }
scratch="$(mktemp -d .rolling.XXXXXX)"
trap 'rm -rf -- "$scratch"' EXIT

php -r '
require "db-v1.php";
$db = v2_data_db();
if ($db->query("SELECT DATABASE()")->fetchColumn() !== "anytour_price_pilot") exit(2);
if ($db->query("SHOW TABLES LIKE " . $db->quote("tour_price_daily"))->fetchColumn() !== "tour_price_daily") {
    $db->exec(file_get_contents("rolling-daily-schema.sql"));
}
'
today="$(date -u +%F)"
refresh_after="$(date -u -d '50 minutes ago' +%s)"
processed=0

roll_day() {
    local day="$1" directory="$PWD/price-return-ready/$1" source temp manifest exact_hash coarse_hash
    php -d date.timezone=UTC sync-day.php "$day" > "$scratch/sync" 2>&1 || {
        tail -n 4 "$scratch/sync"
        return 1
    }
    source="$(sed -n 's/^PRICE_DAY_SOURCE //p' "$scratch/sync")"
    test -n "$source"
    manifest="$directory/manifest.json"
    if test -s "$manifest" && php -r '
        $m = json_decode(file_get_contents($argv[1]), true, 16, JSON_THROW_ON_ERROR);
        if (($m["source"] ?? null) !== json_decode($argv[2], true, 8, JSON_THROW_ON_ERROR)
            || !isset($m["coarse"]["sha256"])) exit(1);
        foreach ([$m, $m["coarse"]] as $set) {
            if (!preg_match("/^[0-9a-f]{64}$/D", $set["sha256"] ?? "")
                || filesize(dirname($argv[1]) . "/" . $set["sha256"] . ".jsonl.gz") !== $set["bytes"]) exit(2);
        }
    ' "$manifest" "$source"; then
        touch -- "$manifest"
        echo "PRICE_ROLLING_CURRENT day=$day"
        return 0
    fi
    php -d date.timezone=UTC rollup-price-daily-exact-v1.php "--date=$day"
    php -d date.timezone=UTC rollup-price-daily-v1.php "--date=$day"
    php rolling-digest.php db-v1.php "$day" "$directory" > "$scratch/digest"
    temp="$(mktemp "$directory/.manifest.XXXXXX")"
    php -r '
        $m = json_decode(file_get_contents($argv[1]), true, 16, JSON_THROW_ON_ERROR);
        $m["source"] = json_decode($argv[2], true, 8, JSON_THROW_ON_ERROR);
        if ($m["date"] !== $m["source"]["date"] || $m["groups"] > $m["observations"]
            || $m["observations"] > $m["source"]["observations"] || $m["coarse"]["groups"] > $m["coarse"]["observations"]
            || $m["coarse"]["observations"] > $m["source"]["observations"]
            || ($m["source"]["observations"] > 0 && ($m["groups"] < 1 || $m["coarse"]["groups"] < 1))) exit(2);
        $m["generated_at"] = gmdate("c");
        echo json_encode($m, JSON_THROW_ON_ERROR) . PHP_EOL;
    ' "$scratch/digest" "$source" > "$temp"
    mv -T -- "$temp" "$manifest"
    read -r exact_hash coarse_hash < <(php -r '
        $m = json_decode(file_get_contents($argv[1]), true, 16, JSON_THROW_ON_ERROR);
        echo $m["sha256"] . " " . $m["coarse"]["sha256"] . PHP_EOL;
    ' "$manifest")
    # Keep superseded transport archives available for in-flight readers.
    # Database history and the two active archives are outside this cleanup.
    find "$directory" -maxdepth 1 -regextype posix-extended -type f \
        -regex '.*/[0-9a-f]{64}\.jsonl\.gz' -mmin +240 \
        ! -name "$exact_hash.jsonl.gz" ! -name "$coarse_hash.jsonl.gz" -delete
    echo "PRICE_ROLLING_DAY_READY $(cat "$manifest")"
}

for offset in $(seq 0 30); do
    day="$(date -u -d "$today - $offset days" +%F)"
    manifest="price-return-ready/$day/manifest.json"
    if test -s "$manifest" && ( test "$offset" -gt 1 || test "$(stat -c %Y "$manifest")" -ge "$refresh_after" ); then
        # Seed the previously verified exact-only day with its coarse counterpart.
        if grep -q '"coarse":' "$manifest"; then
            continue
        fi
    fi
    roll_day "$day"
    processed=$((processed + 1))
    if test "$processed" -ge 2; then
        break
    fi
done

ready=0
for offset in $(seq 0 30); do
    day="$(date -u -d "$today - $offset days" +%F)"
    manifest="price-return-ready/$day/manifest.json"
    if test -s "$manifest" && grep -q '"coarse":' "$manifest"; then
        ready=$((ready + 1))
    fi
done
echo "PRICE_ROLLING_PROGRESS today=$today ready=$ready window=31 processed=$processed"
