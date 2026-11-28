#!/bin/bash
set -euo pipefail
umask 077
test "$(id -un)" = fastuser
account_shell=$(getent passwd fastuser | cut -d: -f7)
case "$account_shell" in
  /usr/sbin/nologin|/sbin/nologin|/bin/false|/usr/bin/false|'')
    echo PRICE_RETURN_ACCOUNT_SHELL_UNAVAILABLE
    exit 2
    ;;
esac
test -x "$account_shell"
d=/var/www/fastuser/data/anytour-price-pilot
test -d "$d" && test ! -L "$d" && test -O "$d" && test "$(stat -c %a "$d")" = 700
test -O "$d/db-password.txt" && test "$(stat -c %a "$d/db-password.txt")" = 600
test -r /etc/ssh/ssh_host_ed25519_key.pub
hostkey=$(awk 'NR==1 && $1=="ssh-ed25519"{print $1, $2}' /etc/ssh/ssh_host_ed25519_key.pub)
test -n "$hostkey"
ip=$(getent ahostsv4 anytoour.ru | awk '{print $1}' | sort -u)
[[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]
test "$ip" != 45.67.128.147
exec 9>"$d/price-result-return.lock"
flock -w 5 9

staging=$(mktemp -d "$d/price-return-setup.XXXXXX")
trap 'rm -rf -- "$staging"' EXIT
cat > "$staging/price-result-export.php" <<'PRICE_EXPORT_PHP'
<?php
declare(strict_types=1);
$command = getenv('SSH_ORIGINAL_COMMAND') ?: '';
if ($command === 'probe') {
    echo "{\"service\":\"anytour-price-result-read\",\"version\":1}\n";
    exit(0);
}
$isManifest = preg_match('/\Amanifest ([0-9]{4}-[0-9]{2}-[0-9]{2})\z/D', $command, $args) === 1;
if (!$isManifest && preg_match('/\Asnapshot ([0-9]{4}-[0-9]{2}-[0-9]{2}) ([0-9a-f]{64})\z/D', $command, $args) !== 1) {
    fwrite(STDERR, "PRICE_RESULT_COMMAND_DENIED\n");
    exit(2);
}
$root = __DIR__ . '/price-return-ready/' . $args[1];
$file = $isManifest ? $root . '/manifest.json' : $root . '/' . $args[2] . '.jsonl.gz';
if (!is_file($file) || is_link($file) || readfile($file) === false) {
    fwrite(STDERR, "PRICE_RESULT_NOT_READY\n");
    exit(3);
}
PRICE_EXPORT_PHP

cat > "$staging/price-result-publish.php" <<'PRICE_PUBLISH_PHP'
<?php
declare(strict_types=1);
$root = __DIR__;
$day = '2026-11-26';
$expectedDigest = 'ffead7fd573b2a3c9a530b1a31c3bde06efc34f94954b360a9fb7111e9493c9b';
$expectedGroups = 638682;
$expectedObservations = 699238;
$password = file_get_contents($root . '/db-password.txt');
if ($password === false || rtrim($password, "\r\n") === '') exit(4);
putenv('ANYTOUR_DATA_DSN=mysql:host=localhost;dbname=anytour_price_pilot;charset=utf8mb4');
putenv('ANYTOUR_DATA_DB_USER=price_pilot');
putenv('ANYTOUR_DATA_DB_PASSWORD=' . rtrim($password, "\r\n"));
unset($password);
$dir = $root . '/price-return-ready/' . $day;
$tmp = $dir . '/.payload-' . bin2hex(random_bytes(8));
$pointer = $tmp . '.manifest';
try {
    require $root . '/db-v1.php';
    $db = v2_data_db();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'anytour_price_pilot') exit(5);
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) exit(6);
    $archive = gzopen($tmp, 'wb4');
    if ($archive === false) exit(7);
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $db->exec('START TRANSACTION READ ONLY');
    $query = $db->prepare('SELECT segment_fingerprint,departure_id,country_id,region_id,subregion_id,hotel_id,
        departure_date,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,currency,
        min_price,median_price,max_price,observation_count,independent_search_count
        FROM tour_price_daily_exact FORCE INDEX (uq_price_daily_exact_segment)
        WHERE price_date=? AND segment_fingerprint>? ORDER BY segment_fingerprint LIMIT 20000');
    $hash = hash_init('sha256');
    $groups = $observations = 0;
    $cursor = '';
    do {
        $query->execute([$day, $cursor]);
        $page = 0;
        while (($row = $query->fetch(PDO::FETCH_NUM)) !== false) {
            $cursor = (string)$row[0];
            $line = json_encode(array_map(static fn($value) => $value === null ? null : (string)$value, $row),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            if (gzwrite($archive, $line) !== strlen($line)) throw new RuntimeException();
            hash_update($hash, $line);
            ++$page;
            ++$groups;
            $observations += (int)$row[19];
        }
        $query->closeCursor();
    } while ($page === 20000);
    $db->exec('ROLLBACK');
    if (!gzclose($archive)) throw new RuntimeException();
    $digest = hash_final($hash);
    if ($groups !== $expectedGroups || $observations !== $expectedObservations || $digest !== $expectedDigest) {
        fwrite(STDERR, "PRICE_RESULT_REFERENCE_MISMATCH\n");
        exit(8);
    }
    $snapshot = $dir . '/' . $digest . '.jsonl.gz';
    $manifest = [
        'contract'=>1, 'date'=>$day, 'groups'=>$groups, 'observations'=>$observations,
        'sha256'=>$digest, 'gzip_sha256'=>hash_file('sha256',$tmp), 'bytes'=>filesize($tmp),
    ];
    if (!rename($tmp, $snapshot)) throw new RuntimeException();
    $payload = json_encode($manifest, JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($pointer, $payload) !== strlen($payload) || !rename($pointer, $dir . '/manifest.json')) {
        throw new RuntimeException();
    }
    echo "PRICE_RESULT_DAY_READY " . $payload;
} catch (Throwable) {
    fwrite(STDERR, "PRICE_RESULT_PUBLICATION_FAILED\n");
    exit(9);
} finally {
    if (is_file($tmp)) unlink($tmp);
    if (is_file($pointer)) unlink($pointer);
}
PRICE_PUBLISH_PHP

/usr/bin/php -l "$staging/price-result-export.php"
/usr/bin/php -l "$staging/price-result-publish.php"
test ! -L "$d/price-result-export.php"
test ! -L "$d/price-result-publish.php"
install -m 600 "$staging/price-result-export.php" "$d/price-result-export.php"
install -m 600 "$staging/price-result-publish.php" "$d/price-result-publish.php"
/usr/bin/php "$d/price-result-publish.php"
SSH_ORIGINAL_COMMAND=probe /usr/bin/php "$d/price-result-export.php"
if SSH_ORIGINAL_COMMAND=rejected-test-command /usr/bin/php "$d/price-result-export.php"; then
  echo PRICE_RESULT_RESTRICTION_TEST_FAILED
  exit 10
fi
mkdir -p -m 700 "$HOME/.ssh"
test ! -L "$HOME/.ssh" && test -O "$HOME/.ssh" && test "$(stat -c %a "$HOME/.ssh")" = 700
auth="$HOME/.ssh/authorized_keys"
test ! -L "$auth"
if [ -e "$auth" ]; then
  test -f "$auth" && test -O "$auth" && test "$(stat -c %a "$auth")" = 600
fi
key='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBP6ZP8OqJ/PhmcReXozGshpOX2p6ERe7x3cBIEfeM+O'
public_part=$(echo "$key" | cut -d' ' -f2)
line="from=\"$ip\",restrict,command=\"/usr/bin/php $d/price-result-export.php\" $key anytour-price-result-read-20261128"
if [ -f "$auth" ] && grep -F -- "$public_part" "$auth" >/dev/null; then
  test "$(grep -F -- "$public_part" "$auth")" = "$line"
else
  authorized_tmp=$(mktemp "$HOME/.ssh/price-authorized.XXXXXX")
  if [ -f "$auth" ]; then
    cat "$auth" > "$authorized_tmp"
    printf '\n' >> "$authorized_tmp"
  fi
  printf '%s\n' "$line" >> "$authorized_tmp"
  mv -T "$authorized_tmp" "$auth"
fi
grep -Fx -- "$line" "$auth" >/dev/null
echo PRICE_RETURN_AUTHORIZED_KEY_OK
echo "PRICE_RETURN_VPS_HOST_KEY=$hostkey"
