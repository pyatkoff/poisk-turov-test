#!/usr/bin/env bash
set -euo pipefail
umask 022

action="${1:-}"
handle_token="${2:-}"
release_sha="${3:-}"
repository_tree_sha="${4:-}"
run_id="${5:-}"
run_attempt="${6:-}"
archive_sha256="${7:-}"
expected_current_sha="${8:-}"
allow_identity_bootstrap="${9:-false}"

[[ "$action" == "prepare" || "$action" == "activate" || "$action" == "cleanup" ]]
[[ "$handle_token" =~ ^[A-Za-z0-9]{10}$ ]]
[[ "$release_sha" =~ ^[0-9a-f]{40}$ ]]
[[ "$repository_tree_sha" =~ ^[0-9a-f]{40}$ ]]
[[ "$run_id" =~ ^[0-9]+$ ]]
[[ "$run_attempt" =~ ^[0-9]+$ ]]
[[ "$archive_sha256" =~ ^[0-9a-f]{64}$ ]]
[[ "$expected_current_sha" == "absent" || "$expected_current_sha" =~ ^[0-9a-f]{40}$ ]]
[[ "$allow_identity_bootstrap" == "true" || "$allow_identity_bootstrap" == "false" ]]

for command_name in awk chmod cmp find flock grep id install mkdir mv openssl php rm sha256sum sort stat tar wc; do
  command -v "$command_name" >/dev/null
done

root="$HOME/www/anytour.online/poisk-turov-test/v2"
identity="$HOME/.anytoour-legacy-lead-release.json"
handle="$HOME/.anytoour-legacy-prepared.$handle_token"
archive="$handle/release.tar.gz"
prepared_secret="$handle/bridge-secret"
stage="$handle/stage"
receipt="$handle/receipt"

validate_handle_name() {
  test "$handle" = "$HOME/.anytoour-legacy-prepared.$handle_token"
}

validate_handle() {
  validate_handle_name
  test -d "$handle" && test ! -L "$handle"
  test "$(stat -c '%u' "$handle")" = "$(id -u)"
  test "$(stat -c '%a' "$handle")" = "700"
}

assert_private_file() {
  local path="$1"
  test -f "$path" && test ! -L "$path"
  test "$(stat -c '%u' "$path")" = "$(id -u)"
  test "$(stat -c '%a' "$path")" = "600"
}

cleanup_handle_unlocked() {
  validate_handle
  rm -rf -- "$handle"
}

cleanup_handle_locked() {
  exec 8>"$HOME/.anytoour-legacy-lead-deploy.lock"
  if ! flock -w 5 8; then
    echo "ANYTOOUR_LEGACY_CLEANUP_BUSY" >&2
    return 75
  fi
  cleanup_handle_unlocked
}

if [[ "$action" == "cleanup" ]]; then
  validate_handle_name
  if test ! -e "$handle"; then
    echo "ANYTOOUR_LEGACY_PREPARED_ALREADY_CLEAN"
    exit 0
  fi
  cleanup_handle_locked
  echo "ANYTOOUR_LEGACY_PREPARED_CLEANED"
  exit 0
fi

validate_handle

test -d "$root"

current_transition() {
  if test -e "$identity" || test -L "$identity"; then
    assert_private_file "$identity" || return 1
  fi
  php -r '
    [$identity,$expected,$target,$bootstrap]=array_slice($argv,1);
    if (!is_file($identity)) {
      if ($expected === "absent" && $bootstrap === "true") { echo "apply"; exit; }
      exit(31);
    }
    $value=json_decode(file_get_contents($identity),true,512,JSON_THROW_ON_ERROR);
    if (($value["schema_version"]??null)!==1 || ($value["target"]??"")!=="anytoour-legacy-lead") exit(32);
    $state=(string)($value["state"]??"");
    $sha=(string)($value["release_sha"]??"");
    if ($state === "active" && $sha === $target) { echo "already"; exit; }
    if ($expected !== "absent" && $state === "active" && $sha === $expected) { echo "apply"; exit; }
    $previous=$value["previous_release_sha"]??null;
    $wantedPrevious=$expected === "absent" ? null : $expected;
    if ($state === "activating" && $sha === $target && $previous === $wantedPrevious) { echo "resume"; exit; }
    exit(33);
  ' "$identity" "$expected_current_sha" "$release_sha" "$allow_identity_bootstrap"
}

extract_verified_archive() {
  test -s "$archive"
  printf '%s  %s\n' "$archive_sha256" "$archive" | sha256sum -c - >/dev/null
  while IFS= read -r member; do
    normalized="${member%/}"
    test -n "$normalized"
    [[ "$normalized" != /* && "$normalized" != *\\* ]]
    [[ ! "$normalized" =~ (^|/)\.\.(/|$) ]]
    [[ "$normalized" == "payload" || "$normalized" == payload/* || "$normalized" == "control" || "$normalized" == control/* ]]
  done < <(tar -tzf "$archive")
  tar -tvzf "$archive" | awk 'substr($0,1,1)!="-" && substr($0,1,1)!="d" {exit 1}'
  rm -rf -- "$stage"
  mkdir -m 700 "$stage"
  tar -xzf "$archive" --no-same-owner --no-same-permissions -C "$stage"
}

validate_prepared_payload() {
  test -s "$archive" && test -s "$prepared_secret"
  test -d "$stage/payload" && test -d "$stage/control"
  test -z "$(find "$stage" -type l -print -quit)"
  printf '%s  %s\n' "$archive_sha256" "$archive" | sha256sum -c - >/dev/null

  mapfile -t identity_fields < <(
    php -r '
      $value=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
      foreach (["target","release_sha","repository_tree_sha","state","manifest_sha256","payload_checksums_sha256"] as $key) {
        if (!isset($value[$key]) || !is_string($value[$key])) exit(20);
        echo $value[$key], "\n";
      }
    ' "$stage/control/release.json"
  )
  test "${#identity_fields[@]}" -eq 6
  test "${identity_fields[0]}" = "anytoour-legacy-lead"
  test "${identity_fields[1]}" = "$release_sha"
  test "${identity_fields[2]}" = "$repository_tree_sha"
  test "${identity_fields[3]}" = "active"
  test "${identity_fields[4]}" = "$(sha256sum "$stage/control/manifest.json" | awk '{print $1}')"
  test "${identity_fields[5]}" = "$(sha256sum "$stage/control/payload.sha256" | awk '{print $1}')"

  mapfile -t manifest_fields < <(
    php -r '
      $value=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
      echo ($value["target"] ?? ""), "\n";
      echo ($value["release_sha"] ?? ""), "\n";
      echo ($value["repository_tree_sha"] ?? ""), "\n";
      echo ($value["source_subtree"]["tree_sha"] ?? ""), "\n";
    ' "$stage/control/manifest.json"
  )
  test "${manifest_fields[0]}" = "anytoour-legacy-lead"
  test "${manifest_fields[1]}" = "$release_sha"
  test "${manifest_fields[2]}" = "$repository_tree_sha"
  [[ "${manifest_fields[3]}" =~ ^[0-9a-f]{40}$ ]]

  expected_files=(lead-adapter-v2.php lead-idempotency-v1.php lead-price-v1.php lead-receiver-v1.php)
  test "$(find "$stage/payload" -type f | wc -l)" -eq "${#expected_files[@]}"
  for file in "${expected_files[@]}"; do
    test -s "$stage/payload/$file"
    php -l "$stage/payload/$file" >/dev/null
  done
  test ! -e "$stage/payload/.anytoour-bridge-secret"
  test ! -e "$stage/payload/config.php"
  grep -Fq 'v2-direct-bitrix-lead' "$stage/payload/lead-adapter-v2.php"
  grep -Fq "require __DIR__ . '/lead-adapter-v2.php';" "$stage/payload/lead-receiver-v1.php"
  (
    cd "$stage/payload"
    sha256sum -c "$stage/control/payload.sha256" >/dev/null
  )
}

write_expected_receipt() {
  local destination="$1"
  {
    printf 'target=anytoour-legacy-lead\n'
    printf 'release_sha=%s\n' "$release_sha"
    printf 'repository_tree_sha=%s\n' "$repository_tree_sha"
    printf 'run_id=%s\n' "$run_id"
    printf 'run_attempt=%s\n' "$run_attempt"
    printf 'archive_sha256=%s\n' "$archive_sha256"
    printf 'secret_sha256=%s\n' "$(sha256sum "$prepared_secret" | awk '{print $1}')"
    printf 'expected_current_sha=%s\n' "$expected_current_sha"
    printf 'allow_identity_bootstrap=%s\n' "$allow_identity_bootstrap"
  } > "$destination"
  chmod 600 "$destination"
}

verify_live_file_metadata() {
  php -r '
    [$manifestFile,$root,$deployUid]=array_slice($argv,1);
    $manifest=json_decode(file_get_contents($manifestFile),true,512,JSON_THROW_ON_ERROR);
    $files=$manifest["files"]??null;
    if (!is_array($files) || count($files)===0) exit(40);
    $seen=[];
    foreach ($files as $record) {
      $path=$record["path"]??null;
      $mode=$record["mode"]??null;
      if (!is_string($path) || !is_string($mode) || !in_array($mode,["0644","0755"],true)) exit(41);
      if ($path==="" || $path[0]==="/" || strpos($path,"\\")!==false || preg_match("/[\\x00-\\x20\\x7f]/",$path)) exit(42);
      foreach (explode("/",$path) as $part) if ($part==="" || $part==="." || $part==="..") exit(43);
      if (isset($seen[$path])) exit(44);
      $seen[$path]=true;
      $destination=$root."/".$path;
      clearstatcache(true,$destination);
      if (!is_file($destination) || is_link($destination)) exit(45);
      $metadata=lstat($destination);
      if (!is_array($metadata) || (string)$metadata["uid"]!==$deployUid) exit(46);
      if (sprintf("%04o",$metadata["mode"] & 0777)!==$mode) exit(47);
    }
  ' "$stage/control/manifest.json" "$root" "$(id -u)"
}

verify_live_payload() {
  (
    cd "$root"
    sha256sum -c "$stage/control/payload.sha256" >/dev/null
  )
  verify_live_file_metadata
  assert_private_file "$root/.anytoour-bridge-secret"
  cmp "$prepared_secret" "$root/.anytoour-bridge-secret"
  for file in lead-adapter-v2.php lead-idempotency-v1.php lead-price-v1.php lead-receiver-v1.php; do
    php -l "$root/$file" >/dev/null
  done
}

if [[ "$action" == "prepare" ]]; then
  success=0
  trap 'if [[ "$success" -ne 1 ]]; then cleanup_handle_unlocked; fi' EXIT
  test -s "$archive"
  chmod 600 "$archive"
  if test -e "$root/.anytoour-bridge-secret" || test -L "$root/.anytoour-bridge-secret"; then
    assert_private_file "$root/.anytoour-bridge-secret"
    test -s "$root/.anytoour-bridge-secret"
    install -m 600 -- "$root/.anytoour-bridge-secret" "$prepared_secret"
  else
    test "$expected_current_sha" = "absent"
    test "$allow_identity_bootstrap" = "true"
    (umask 077; openssl rand -hex 32 > "$prepared_secret")
  fi
  test -s "$prepared_secret"
  extract_verified_archive
  validate_prepared_payload

  exec 9>"$HOME/.anytoour-legacy-lead-deploy.lock"
  flock -w 300 9
  transition="$(current_transition)"
  [[ "$transition" == "apply" || "$transition" == "resume" || "$transition" == "already" ]]
  next_receipt="$handle/receipt.next"
  write_expected_receipt "$next_receipt"
  mv -f -- "$next_receipt" "$receipt"
  success=1
  trap - EXIT
  echo "ANYTOOUR_LEGACY_PREPARED sha=$release_sha transition=$transition"
  exit 0
fi

trap cleanup_handle_unlocked EXIT
test -s "$receipt"
extract_verified_archive
validate_prepared_payload
expected_receipt="$handle/receipt.expected"
write_expected_receipt "$expected_receipt"
cmp "$expected_receipt" "$receipt"
rm -f -- "$expected_receipt"

exec 9>"$HOME/.anytoour-legacy-lead-deploy.lock"
flock -w 300 9
transition="$(current_transition)"

if [[ "$transition" == "already" ]]; then
  verify_live_payload
  cmp "$stage/control/release.json" "$identity"
  echo "ANYTOOUR_LEGACY_ALREADY_ACTIVE sha=$release_sha"
  exit 0
fi
[[ "$transition" == "apply" || "$transition" == "resume" ]]

temporary_files=()
cleanup_temporary() {
  local temporary
  for temporary in "${temporary_files[@]}"; do
    case "$temporary" in
      "$root"/*.release-"$release_sha"-"$run_id"-"$run_attempt".tmp|"$HOME"/*.release-"$release_sha"-"$run_id"-"$run_attempt".tmp)
        rm -f -- "$temporary"
        ;;
    esac
  done
}
trap 'cleanup_temporary; cleanup_handle_unlocked' EXIT

atomic_install() {
  local source="$1" destination="$2" mode="$3" temporary
  temporary="${destination}.release-${release_sha}-${run_id}-${run_attempt}.tmp"
  temporary_files+=("$temporary")
  install -m "$mode" -- "$source" "$temporary"
  if [[ "$destination" == *.php ]]; then php -l "$temporary" >/dev/null; fi
  mv -f -- "$temporary" "$destination"
}

if test -e "$root/.anytoour-bridge-secret" || test -L "$root/.anytoour-bridge-secret"; then
  assert_private_file "$root/.anytoour-bridge-secret"
  test -s "$root/.anytoour-bridge-secret"
  cmp "$prepared_secret" "$root/.anytoour-bridge-secret"
else
  test "$expected_current_sha" = "absent"
  test "$allow_identity_bootstrap" = "true"
  atomic_install "$prepared_secret" "$root/.anytoour-bridge-secret" 600
fi
chmod 600 "$root/.anytoour-bridge-secret"

activating="$stage/control/release-activating.json"
php -r '
  $value=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
  $value["state"]="activating";
  $value["previous_release_sha"]=$argv[3] === "absent" ? null : $argv[3];
  file_put_contents($argv[2],json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
' "$stage/control/release.json" "$activating" "$expected_current_sha"
atomic_install "$activating" "$identity" 600

for file in lead-price-v1.php lead-idempotency-v1.php lead-adapter-v2.php lead-receiver-v1.php; do
  source_mode="$(stat -c '%a' "$stage/payload/$file")"
  [[ "$source_mode" == "644" || "$source_mode" == "755" ]]
  atomic_install "$stage/payload/$file" "$root/$file" "$source_mode"
done

verify_live_payload
chmod 600 "$root/.anytoour-bridge-secret"
atomic_install "$stage/control/release.json" "$identity" 600
assert_private_file "$root/.anytoour-bridge-secret"
assert_private_file "$identity"

echo "ANYTOOUR_LEGACY_LEAD_ACTIVATED sha=$release_sha transition=$transition"
