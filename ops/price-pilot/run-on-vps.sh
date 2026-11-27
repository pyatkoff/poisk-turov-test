#!/bin/bash
set -euo pipefail
umask 077
d=/var/www/fastuser/data/anytour-price-pilot
cd "$d"
test "$(id -un)" = fastuser
chmod 700 "$d"
exec 9>"$d/pilot.lock"
flock -n 9 || exit 0
test ! -s "$d/native-result.txt" || exit 0
payload=26cdfe5d4cc3082455c5f5e82552662067f35f7f
source=e527e41ffd95d4170e13276fea22b17e021b80a6
repo=https://raw.githubusercontent.com/pyatkoff/poisk-turov-test
fetch() {
  curl -fsSL --proto '=https' --max-time 30 "$1" -o "$2"
}
if [ ! -s "$d/sample.csv.gz" ]; then
  test -s "$d/relay-pass"
  fetch "$repo/$payload/ops/price-pilot/observations-encrypted.b64" "$d/encrypted.b64"
  printf '%s  %s\n' cfd4e7f4d14471a98fa53ca3838e2d1e7f52d5cfdb860986e8967739c315277b encrypted.b64 | sha256sum -c
  base64 -d "$d/encrypted.b64" > "$d/encrypted.bin"
  openssl enc -d -aes-256-cbc -pbkdf2 -iter 250000 -pass file:"$d/relay-pass" -in "$d/encrypted.bin" -out "$d/sample.incoming.gz"
  printf '%s  %s\n' 9ca8b599d68d41651828029ae4d51120ade63620588b8427cf4ed555f403e5bc sample.incoming.gz | sha256sum -c
  mv "$d/sample.incoming.gz" "$d/sample.csv.gz"
fi
printf '%s  %s\n' 9ca8b599d68d41651828029ae4d51120ade63620588b8427cf4ed555f403e5bc sample.csv.gz | sha256sum -c
echo PRICE_SAMPLE_DELIVERED rows_expected=5000
test -s "$d/db-password.txt"
chmod 600 "$d/db-password.txt"
export ANYTOUR_DATA_DSN='mysql:host=localhost;dbname=anytour_price_pilot;charset=utf8mb4'
export ANYTOUR_DATA_DB_USER=price_pilot
export ANYTOUR_DATA_DB_PASSWORD="$(cat "$d/db-password.txt")"
test -n "$ANYTOUR_DATA_DB_PASSWORD"
for file in db-v1.php price-segment-v1.php rollup-price-daily-exact-v1.php; do
  fetch "$repo/$source/v2/data/$file" "$d/$file"
  php -l "$d/$file"
done
php -d display_errors=0 <<'PRICE_IMPORT'
<?php
declare(strict_types=1);
require '/var/www/fastuser/data/anytour-price-pilot/db-v1.php';
$db = v2_data_db();
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'anytour_price_pilot') exit(2);
$source = fopen('compress.zlib:///var/www/fastuser/data/anytour-price-pilot/sample.csv.gz', 'rb');
$columns = explode(',', 'id,observed_at,source,search_id,departure_id,country_id,region_id,subregion_id,hotel_id,departure_date,departure_year,departure_month,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,price,fuel_charge,currency');
if (fgetcsv($source, 0, ',', '"', '') !== $columns) exit(3);
$insert = $db->prepare('INSERT INTO tour_price_observations (fingerprint,' . implode(',', $columns) .
    ') VALUES (' . implode(',', array_fill(0, count($columns) + 1, '?')) .
    ') ON DUPLICATE KEY UPDATE fingerprint=VALUES(fingerprint)');
$nullable = ['search_id','region_id','subregion_id','meal_id','room_id','room_type','operator_id','fuel_charge'];
$db->beginTransaction();
$rows = 0;
try {
    while (($line = fgetcsv($source, 0, ',', '"', '')) !== false) {
        if (count($line) !== count($columns) || ++$rows > 5000 || !str_starts_with($line[1], '2026-11-26 ')) {
            throw new RuntimeException('Unexpected input shape, date or count');
        }
        $id = $line[0];
        foreach ($columns as $index => $name) {
            if ($line[$index] === '' && in_array($name, $nullable, true)) $line[$index] = null;
        }
        $insert->execute([hash('sha256', 'price-pilot-20261126:' . $id), ...$line]);
    }
    if ($rows !== 5000 || (int)$db->query('SELECT COUNT(*) FROM tour_price_observations')->fetchColumn() !== 5000) {
        throw new RuntimeException('Pilot observation count mismatch');
    }
    $db->commit();
    echo "PRICE_SAMPLE_IMPORTED rows=$rows\n";
} catch (Throwable $error) {
    $db->rollBack();
    fwrite(STDERR, "PRICE_SAMPLE_IMPORT_FAILED\n");
    exit(4);
}
PRICE_IMPORT
/usr/bin/time -p php "$d/rollup-price-daily-exact-v1.php" --date=2026-11-26
php -d display_errors=0 <<'PRICE_VERIFY'
<?php
declare(strict_types=1);
require '/var/www/fastuser/data/anytour-price-pilot/db-v1.php';
$db = v2_data_db();
$sql = "SELECT SUM(observation_count), COUNT(*), SUM(min_price), SUM(median_price), SUM(max_price),
               SUM(independent_search_count) FROM tour_price_daily_exact WHERE price_date = '2026-11-26'";
$values = array_map('strval', $db->query($sql)->fetch(PDO::FETCH_NUM));
echo 'PRICE_NATIVE_READBACK=' . json_encode($values, JSON_THROW_ON_ERROR) . PHP_EOL;
if ($values !== ['5000','4064','1086076347.00','1091463383.50','1095933982.00','4064']) exit(5);
echo "PRICE_NATIVE_PARITY_OK\n";
PRICE_VERIFY
echo PRICE_PILOT_SUCCESS > "$d/native-result.txt"
