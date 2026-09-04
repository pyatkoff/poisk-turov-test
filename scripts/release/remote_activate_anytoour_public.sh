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

for command_name in awk chmod cmp dirname find flock grep id install mkdir mv php rm sha256sum sort stat tar; do
  command -v "$command_name" >/dev/null
done

root="$HOME/www/anytoour.ru"
identity="$HOME/.anytoour-public-release.json"
handle="$HOME/.anytoour-public-prepared.$handle_token"
archive="$handle/release.tar.gz"
prepared_secret="$handle/bridge-secret"
stage="$handle/stage"
receipt="$handle/receipt"

validate_handle_name() {
  test "$handle" = "$HOME/.anytoour-public-prepared.$handle_token"
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
  exec 8>"$HOME/.anytoour-production-deploy.lock"
  if ! flock -w 5 8; then
    echo "ANYTOOUR_PUBLIC_CLEANUP_BUSY" >&2
    return 75
  fi
  cleanup_handle_unlocked
}

if [[ "$action" == "cleanup" ]]; then
  validate_handle_name
  if test ! -e "$handle"; then
    echo "ANYTOOUR_PUBLIC_PREPARED_ALREADY_CLEAN"
    exit 0
  fi
  cleanup_handle_locked
  echo "ANYTOOUR_PUBLIC_PREPARED_CLEANED"
  exit 0
fi

validate_handle

test -d "$root"
test -s "$root/config.php"
test -s "$root/images/logo.svg"

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
    if (($value["schema_version"]??null)!==1 || ($value["target"]??"")!=="anytoour-public") exit(32);
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
      foreach (["target","release_sha","repository_tree_sha","state","manifest_sha256","payload_checksums_sha256","manifest_file"] as $key) {
        if (!isset($value[$key]) || !is_string($value[$key])) exit(20);
        echo $value[$key], "\n";
      }
    ' "$stage/control/release.json"
  )
  test "${#identity_fields[@]}" -eq 7
  test "${identity_fields[0]}" = "anytoour-public"
  test "${identity_fields[1]}" = "$release_sha"
  test "${identity_fields[2]}" = "$repository_tree_sha"
  test "${identity_fields[3]}" = "active"
  test "${identity_fields[4]}" = "$(sha256sum "$stage/control/manifest.json" | awk '{print $1}')"
  test "${identity_fields[5]}" = "$(sha256sum "$stage/control/payload.sha256" | awk '{print $1}')"
  test "${identity_fields[6]}" = ".anytoour-public-release-manifest-${release_sha}.json"

  mapfile -t manifest_fields < <(
    php -r '
      $value=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
      echo ($value["target"] ?? ""), "\n";
      echo ($value["release_sha"] ?? ""), "\n";
      echo ($value["repository_tree_sha"] ?? ""), "\n";
      echo ($value["source_subtree"]["tree_sha"] ?? ""), "\n";
      echo count($value["files"] ?? []), "\n";
    ' "$stage/control/manifest.json"
  )
  test "${manifest_fields[0]}" = "anytoour-public"
  test "${manifest_fields[1]}" = "$release_sha"
  test "${manifest_fields[2]}" = "$repository_tree_sha"
  [[ "${manifest_fields[3]}" =~ ^[0-9a-f]{40}$ ]]
  test "${manifest_fields[4]}" -gt 0

  test ! -e "$stage/payload/config.php"
  test ! -e "$stage/payload/site_conf.php"
  test ! -e "$stage/payload/.anytoour-bridge-secret"
  test ! -e "$stage/payload/release.json"
  test -z "$(find "$stage/payload" -maxdepth 1 -name 'release-manifest-*.json' -print -quit)"
  cmp "$stage/payload/lead-adapter-v2.php" "$stage/payload/lead-bridge-v1.php"
  cmp "$stage/payload/index.php" "$stage/payload/home-entry-v1.php"
  grep -Fq 'v2-hmac-bridge-bitrix-lead' "$stage/payload/lead-adapter-v2.php"
  ! grep -Fq 'v2-direct-bitrix-lead' "$stage/payload/lead-adapter-v2.php"
  grep -Fq "v2_public_path('lead-adapter-v2.php')" "$stage/payload/search-page-v2.php"
  (
    cd "$stage/payload"
    sha256sum -c "$stage/control/payload.sha256" >/dev/null
  )
  while IFS= read -r -d '' php_file; do
    php -l "$php_file" >/dev/null
  done < <(find "$stage/payload" -type f -name '*.php' -print0 | sort -z)
  php -l "$root/config.php" >/dev/null
}

write_expected_receipt() {
  local destination="$1"
  {
    printf 'target=anytoour-public\n'
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
  test -s "$root/config.php" && test -s "$root/images/logo.svg"
  test -s "$root/.anytoour-bridge-secret"
  assert_private_file "$root/.anytoour-bridge-secret"
  cmp "$prepared_secret" "$root/.anytoour-bridge-secret"
  php -l "$root/config.php" >/dev/null
  for file in index.php search-page-v2.php home-entry-v1.php home-v1.php poisk-turov/index.php assets.php api-v2.php lead-adapter-v2.php bundle-v1.php analytics-config.php privacy-config.php seo-config.php; do
    php -l "$root/$file" >/dev/null
  done
  cmp "$root/lead-adapter-v2.php" "$root/lead-bridge-v1.php"
  grep -Fq 'v2-hmac-bridge-bitrix-lead' "$root/lead-adapter-v2.php"
  ! grep -Fq 'v2-direct-bitrix-lead' "$root/lead-adapter-v2.php"
}

if [[ "$action" == "prepare" ]]; then
  success=0
  trap 'if [[ "$success" -ne 1 ]]; then cleanup_handle_unlocked; fi' EXIT
  test -s "$archive" && test -s "$prepared_secret"
  chmod 600 "$archive" "$prepared_secret"
  extract_verified_archive
  validate_prepared_payload

  exec 9>"$HOME/.anytoour-production-deploy.lock"
  flock -w 300 9
  transition="$(current_transition)"
  [[ "$transition" == "apply" || "$transition" == "resume" || "$transition" == "already" ]]
  if test -e "$root/.anytoour-bridge-secret" || test -L "$root/.anytoour-bridge-secret"; then
    assert_private_file "$root/.anytoour-bridge-secret"
    test -s "$root/.anytoour-bridge-secret"
    cmp "$prepared_secret" "$root/.anytoour-bridge-secret"
  else
    test "$expected_current_sha" = "absent"
    test "$allow_identity_bootstrap" = "true"
  fi
  next_receipt="$handle/receipt.next"
  write_expected_receipt "$next_receipt"
  mv -f -- "$next_receipt" "$receipt"
  success=1
  trap - EXIT
  echo "ANYTOOUR_PUBLIC_PREPARED sha=$release_sha transition=$transition"
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

exec 9>"$HOME/.anytoour-production-deploy.lock"
flock -w 300 9
transition="$(current_transition)"

manifest_destination="$HOME/.anytoour-public-release-manifest-${release_sha}.json"
if test -e "$manifest_destination" || test -L "$manifest_destination"; then
  assert_private_file "$manifest_destination"
fi
if [[ "$transition" == "already" ]]; then
  verify_live_payload
  assert_private_file "$root/.anytoour-bridge-secret"
  cmp "$stage/control/manifest.json" "$manifest_destination"
  cmp "$stage/control/release.json" "$identity"
  echo "ANYTOOUR_PUBLIC_ALREADY_ACTIVE sha=$release_sha"
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
  local source="$1" destination="$2" mode="$3" temporary destination_directory
  destination_directory="$(dirname "$destination")"
  mkdir -p -- "$destination_directory"
  temporary="${destination}.release-${release_sha}-${run_id}-${run_attempt}.tmp"
  temporary_files+=("$temporary")
  install -m "$mode" -- "$source" "$temporary"
  if [[ "$destination" == *.php ]]; then php -l "$temporary" >/dev/null; fi
  mv -f -- "$temporary" "$destination"
}

install_bridge_secret=0
if test -e "$root/.anytoour-bridge-secret" || test -L "$root/.anytoour-bridge-secret"; then
  assert_private_file "$root/.anytoour-bridge-secret"
  test -s "$root/.anytoour-bridge-secret"
  cmp "$prepared_secret" "$root/.anytoour-bridge-secret"
else
  test "$expected_current_sha" = "absent"
  test "$allow_identity_bootstrap" = "true"
  install_bridge_secret=1
fi
if test -e "$manifest_destination"; then
  cmp "$stage/control/manifest.json" "$manifest_destination"
else
  atomic_install "$stage/control/manifest.json" "$manifest_destination" 600
fi
if [[ "$install_bridge_secret" -eq 1 ]]; then
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

critical_files=(bundle-v1.php api-v2.php poisk-turov/index.php search-page-v2.php index.php lead-adapter-v2.php)
while IFS= read -r -d '' source; do
  relative="${source#"$stage/payload/"}"
  skip=0
  for critical in "${critical_files[@]}"; do
    if [[ "$relative" == "$critical" ]]; then skip=1; break; fi
  done
  if [[ "$skip" -eq 0 ]]; then
    source_mode="$(stat -c '%a' "$source")"
    [[ "$source_mode" == "644" || "$source_mode" == "755" ]]
    atomic_install "$source" "$root/$relative" "$source_mode"
  fi
done < <(find "$stage/payload" -type f -print0 | sort -z)

for critical in "${critical_files[@]}"; do
  source_mode="$(stat -c '%a' "$stage/payload/$critical")"
  [[ "$source_mode" == "644" || "$source_mode" == "755" ]]
  atomic_install "$stage/payload/$critical" "$root/$critical" "$source_mode"
done

rm -f -- "$root/index.html"
verify_live_payload
chmod 600 "$root/config.php" "$root/.anytoour-bridge-secret"
atomic_install "$stage/control/release.json" "$identity" 600
assert_private_file "$manifest_destination"
assert_private_file "$root/.anytoour-bridge-secret"
assert_private_file "$identity"

echo "ANYTOOUR_PUBLIC_ACTIVATED sha=$release_sha tree=$repository_tree_sha transition=$transition"
